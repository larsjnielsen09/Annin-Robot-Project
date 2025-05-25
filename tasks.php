<?php
$page_title = "Manage Tasks";
require_once __DIR__ . '/php/auth/auth_check.php';
require_once __DIR__ . '/php/includes/header.php';
require_once __DIR__ . '/php/core/database.php';

$user_id = $_SESSION['user_id'];
$pdo = get_db_connection();

// Filtering options
$filter_project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
$filter_status = isset($_GET['status']) ? sanitize_string($_GET['status']) : ''; // e.g., 'todo', 'inprogress', 'completed'

$sql = "SELECT
            t.id, t.name, t.description, t.status, t.due_date,
            p.name as project_name, p.id as project_id,
            u_assigned.username as assigned_to_username
        FROM tasks t
        LEFT JOIN projects p ON t.project_id = p.id
        LEFT JOIN users u_assigned ON t.assigned_to_user_id = u_assigned.id
        WHERE (p.created_by_user_id = :user_id OR t.assigned_to_user_id = :user_id_assigned OR t.created_by_user_id = :user_id_created) "; // User can see tasks they created, are assigned to, or are part of projects they created.

$params = [
    ':user_id' => $user_id,
    ':user_id_assigned' => $user_id,
    ':user_id_created' => $user_id
];

if ($filter_project_id) {
    $sql .= " AND t.project_id = :project_id";
    $params[':project_id'] = $filter_project_id;
}
if (!empty($filter_status)) {
    $sql .= " AND t.status = :status";
    $params[':status'] = $filter_status;
}

$sql .= " GROUP BY t.id ORDER BY t.due_date ASC, t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch projects for filter dropdown (user's projects)
$stmt_projects_filter = $pdo->prepare("SELECT id, name FROM projects WHERE created_by_user_id = :user_id ORDER BY name ASC");
$stmt_projects_filter->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt_projects_filter->execute();
$user_projects_for_filter = $stmt_projects_filter->fetchAll(PDO::FETCH_ASSOC);

$task_statuses = ['todo', 'in progress', 'on hold', 'testing', 'completed', 'cancelled'];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Task Management <?php if ($filter_project_id && count($tasks) > 0) echo "for Project: " . htmlspecialchars($tasks[0]['project_name']); ?></h1>
    <button class="btn btn-primary"
            hx-get="<?php echo BASE_URL; ?>templates/task_form.php?action=add<?php echo $filter_project_id ? '&project_id=' . $filter_project_id : ''; ?>"
            hx-target="#taskFormContainer"
            hx-swap="innerHTML"
            data-bs-toggle="modal"
            data-bs-target="#taskModal">
        Add New Task
    </button>
</div>

<!-- Filter Form -->
<form method="GET" action="<?php echo BASE_URL; ?>tasks.php" class="mb-3 p-3 bg-light rounded">
    <div class="row g-3 align-items-end">
        <div class="col-md-4">
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
        <div class="col-md-3">
            <label for="filter_status" class="form-label">Filter by Status:</label>
            <select name="status" id="filter_status" class="form-select">
                <option value="">All Statuses</option>
                <?php foreach ($task_statuses as $status_val): ?>
                <option value="<?php echo $status_val; ?>" <?php if ($filter_status == $status_val) echo 'selected'; ?>>
                    <?php echo htmlspecialchars(ucfirst($status_val)); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-info w-100">Filter</button>
        </div>
         <div class="col-md-2">
            <a href="<?php echo BASE_URL; ?>tasks.php" class="btn btn-outline-secondary w-100">Clear Filters</a>
        </div>
    </div>
</form>


<div id="taskList">
    <?php if (count($tasks) > 0): ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Name</th>
                <th>Project</th>
                <th>Assigned To</th>
                <th>Status</th>
                <th>Due Date</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tasks as $task): ?>
            <tr id="task-row-<?php echo $task['id']; ?>">
                <td><?php echo htmlspecialchars($task['name']); ?></td>
                <td><?php echo htmlspecialchars($task['project_name'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($task['assigned_to_username'] ?? 'Unassigned'); ?></td>
                <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars(ucfirst($task['status'])); ?></span></td>
                <td><?php echo $task['due_date'] ? date("Y-m-d", strtotime($task['due_date'])) : 'N/A'; ?></td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-secondary me-1"
                            hx-get="<?php echo BASE_URL; ?>templates/task_form.php?action=edit&task_id=<?php echo $task['id']; ?>"
                            hx-target="#taskFormContainer"
                            hx-swap="innerHTML"
                            data-bs-toggle="modal"
                            data-bs-target="#taskModal">
                        Edit
                    </button>
                    <button class="btn btn-sm btn-outline-danger"
                            hx-confirm="Are you sure you want to delete this task?"
                            hx-post="<?php echo BASE_URL; ?>php/task_handler.php?action=delete&task_id=<?php echo $task['id']; ?>"
                            hx-target="#task-row-<?php echo $task['id']; ?>"
                            hx-swap="outerHTML">
                        Delete
                    </button>
                    <!-- Add link to time tracking for this task if needed -->
                     <a href="<?php echo BASE_URL; ?>timetracker.php?task_id=<?php echo $task['id']; ?>" class="btn btn-sm btn-outline-success ms-1">Log Time</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php else: ?>
    <div class="alert alert-info">No tasks found matching your criteria. <a href="#" data-bs-toggle="modal" data-bs-target="#taskModal" hx-get="<?php echo BASE_URL; ?>templates/task_form.php?action=add<?php echo $filter_project_id ? '&project_id=' . $filter_project_id : ''; ?>" hx-target="#taskFormContainer" hx-swap="innerHTML">Add one now!</a></div>
    <?php endif; ?>
</div>

<!-- Modal for Add/Edit Task -->
<div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="taskModalLabel">Task Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="taskFormContainer">
                <!-- HTMX will load form here -->
                <p>Loading form...</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/php/includes/footer.php'; ?>
