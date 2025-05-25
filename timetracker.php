<?php
$page_title = "Time Tracker";
require_once __DIR__ . '/php/auth/auth_check.php';
require_once __DIR__ . '/php/includes/header.php';
require_once __DIR__ . '/php/core/database.php';

$user_id = $_SESSION['user_id'];
$pdo = get_db_connection();

// Filtering options
$filter_task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : null;
$filter_project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
$filter_date_from = isset($_GET['date_from']) ? sanitize_string($_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? sanitize_string($_GET['date_to']) : '';

// Base SQL for fetching time entries
$sql = "SELECT
            te.id, te.start_time, te.end_time, te.hours_spent, te.notes, te.created_at,
            t.name as task_name, t.id as task_id,
            p.name as project_name, p.id as project_id
        FROM time_entries te
        JOIN tasks t ON te.task_id = t.id
        LEFT JOIN projects p ON t.project_id = p.id
        WHERE te.user_id = :user_id"; // User can only see their own time entries

$params = [':user_id' => $user_id];

if ($filter_task_id) {
    $sql .= " AND te.task_id = :task_id";
    $params[':task_id'] = $filter_task_id;
}
if ($filter_project_id) {
    $sql .= " AND t.project_id = :project_id";
    $params[':project_id'] = $filter_project_id;
}
if (!empty($filter_date_from)) {
    $sql .= " AND DATE(te.start_time) >= :date_from";
    $params[':date_from'] = $filter_date_from;
}
if (!empty($filter_date_to)) {
    $sql .= " AND DATE(te.start_time) <= :date_to"; // Using start_time for date range
    $params[':date_to'] = $filter_date_to;
}

$sql .= " ORDER BY te.start_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$time_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch tasks for filter dropdown (tasks user is involved with)
$stmt_tasks_filter = $pdo->prepare("
    SELECT DISTINCT t.id, t.name, p.name as project_name
    FROM tasks t
    LEFT JOIN projects p ON t.project_id = p.id
    WHERE (p.created_by_user_id = :user_id OR t.assigned_to_user_id = :user_id_assigned OR t.created_by_user_id = :user_id_created)
    ORDER BY p.name, t.name
");
$stmt_tasks_filter->execute([
    ':user_id' => $user_id,
    ':user_id_assigned' => $user_id,
    ':user_id_created' => $user_id
]);
$user_tasks_for_filter = $stmt_tasks_filter->fetchAll(PDO::FETCH_ASSOC);

// Fetch projects for filter
$stmt_projects_filter = $pdo->prepare("SELECT id, name FROM projects WHERE created_by_user_id = :user_id ORDER BY name ASC");
$stmt_projects_filter->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt_projects_filter->execute();
$user_projects_for_filter = $stmt_projects_filter->fetchAll(PDO::FETCH_ASSOC);

$current_task_name_filter = '';
if($filter_task_id){
    foreach($user_tasks_for_filter as $tf){
        if($tf['id'] == $filter_task_id) {$current_task_name_filter = $tf['name']; break;}
    }
}

?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Time Tracker <?php if($filter_task_id && $current_task_name_filter) echo "for Task: ".htmlspecialchars($current_task_name_filter); ?></h1>
    <button class="btn btn-primary"
            hx-get="<?php echo BASE_URL; ?>templates/time_entry_form.php?action=add<?php echo $filter_task_id ? '&task_id=' . $filter_task_id : ''; ?>"
            hx-target="#timeEntryFormContainer"
            hx-swap="innerHTML"
            data-bs-toggle="modal"
            data-bs-target="#timeEntryModal">
        Add New Time Entry
    </button>
</div>

<!-- Filter Form -->
<form method="GET" action="<?php echo BASE_URL; ?>timetracker.php" class="mb-3 p-3 bg-light rounded">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label for="filter_task_id" class="form-label">Filter by Task:</label>
            <select name="task_id" id="filter_task_id" class="form-select">
                <option value="">All Tasks</option>
                <?php foreach ($user_tasks_for_filter as $task_opt): ?>
                <option value="<?php echo $task_opt['id']; ?>" <?php if ($filter_task_id == $task_opt['id']) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($task_opt['project_name'] ? $task_opt['project_name'].' - '.$task_opt['name'] : $task_opt['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="filter_project_id" class="form-label">Filter by Project:</label>
            <select name="project_id" id="filter_project_id" class="form-select">
                <option value="">All Projects</option>
                <?php foreach ($user_projects_for_filter as $proj): ?>
                <option value="<?php echo $proj['id']; ?>" <?php if ($filter_project_id == $proj['id']) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($proj['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="filter_date_from" class="form-label">Date From:</label>
            <input type="date" name="date_from" id="filter_date_from" class="form-control" value="<?php echo $filter_date_from; ?>">
        </div>
        <div class="col-md-2">
            <label for="filter_date_to" class="form-label">Date To:</label>
            <input type="date" name="date_to" id="filter_date_to" class="form-control" value="<?php echo $filter_date_to; ?>">
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-info w-100">Filter</button>
        </div>
        <div class="col-md-1">
            <a href="<?php echo BASE_URL; ?>timetracker.php" class="btn btn-outline-secondary w-100">Clear</a>
        </div>
    </div>
</form>

<div id="timeEntryList">
    <?php if (count($time_entries) > 0): ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Task</th>
                <th>Project</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Hours Spent</th>
                <th>Notes</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $total_hours_filtered = 0;
            foreach ($time_entries as $entry):
            $total_hours_filtered += (float)$entry['hours_spent'];
            ?>
            <tr id="timeentry-row-<?php echo $entry['id']; ?>">
                <td><?php echo htmlspecialchars($entry['task_name']); ?></td>
                <td><?php echo htmlspecialchars($entry['project_name'] ?? 'N/A'); ?></td>
                <td><?php echo date("Y-m-d H:i", strtotime($entry['start_time'])); ?></td>
                <td><?php echo $entry['end_time'] ? date("Y-m-d H:i", strtotime($entry['end_time'])) : '<i>Running</i>'; ?></td>
                <td><?php echo number_format($entry['hours_spent'], 2); ?></td>
                <td><?php echo nl2br(htmlspecialchars($entry['notes'] ?? '')); ?></td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-secondary me-1"
                            hx-get="<?php echo BASE_URL; ?>templates/time_entry_form.php?action=edit&entry_id=<?php echo $entry['id']; ?>"
                            hx-target="#timeEntryFormContainer"
                            hx-swap="innerHTML"
                            data-bs-toggle="modal"
                            data-bs-target="#timeEntryModal">
                        Edit
                    </button>
                    <button class="btn btn-sm btn-outline-danger"
                            hx-confirm="Are you sure you want to delete this time entry?"
                            hx-post="<?php echo BASE_URL; ?>php/time_entry_handler.php?action=delete&entry_id=<?php echo $entry['id']; ?>"
                            hx-target="#timeentry-row-<?php echo $entry['id']; ?>"
                            hx-swap="outerHTML">
                        Delete
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4" class="text-end">Total Hours (Filtered):</th>
                <th><?php echo number_format($total_hours_filtered, 2); ?></th>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
    </div>
    <?php else: ?>
    <div class="alert alert-info">No time entries found matching your criteria. <a href="#" data-bs-toggle="modal" data-bs-target="#timeEntryModal" hx-get="<?php echo BASE_URL; ?>templates/time_entry_form.php?action=add<?php echo $filter_task_id ? '&task_id=' . $filter_task_id : ''; ?>" hx-target="#timeEntryFormContainer" hx-swap="innerHTML">Add one now!</a></div>
    <?php endif; ?>
</div>

<!-- Modal for Add/Edit Time Entry -->
<div class="modal fade" id="timeEntryModal" tabindex="-1" aria-labelledby="timeEntryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="timeEntryModalLabel">Time Entry Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="timeEntryFormContainer">
                <!-- HTMX will load form here -->
                <p>Loading form...</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/php/includes/footer.php'; ?>
