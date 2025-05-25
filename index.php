<?php
$page_title = "Dashboard";
require_once __DIR__ . '/php/auth/auth_check.php'; // Ensure user is logged in
require_once __DIR__ . '/php/includes/header.php'; // Includes session_start, config, etc.
require_once __DIR__ . '/php/core/database.php'; // For database connection

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch some data for the dashboard (examples)
$pdo = get_db_connection();

// Get count of active projects for the user
// Active projects are those with status 'active' or 'pending'.
$stmt_active_projects = $pdo->prepare("
    SELECT COUNT(*) 
    FROM projects 
    WHERE created_by_user_id = :user_id 
    AND (status = 'active' OR status = 'pending')
");
$stmt_active_projects->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt_active_projects->execute();
$active_projects_count = $stmt_active_projects->fetchColumn();
if ($active_projects_count === false) { // Handle case where query might fail or return no rows in a way that fetchColumn gives false
    $active_projects_count = 0;
}

// Get count of pending tasks for the user
// Pending tasks are those not 'completed' or 'cancelled'.
// Tasks are relevant if assigned to user, created by user, or in a project created by the user.
$stmt_pending_tasks = $pdo->prepare("
    SELECT COUNT(DISTINCT t.id) 
    FROM tasks t
    LEFT JOIN projects p ON t.project_id = p.id
    WHERE 
        (t.assigned_to_user_id = :user_id OR t.created_by_user_id = :user_id_created_task OR p.created_by_user_id = :user_id_project_owner)
    AND 
        (t.status NOT IN ('completed', 'cancelled'))
");
$stmt_pending_tasks->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt_pending_tasks->bindParam(':user_id_created_task', $user_id, PDO::PARAM_INT);
$stmt_pending_tasks->bindParam(':user_id_project_owner', $user_id, PDO::PARAM_INT);
$stmt_pending_tasks->execute();
$pending_tasks_count = $stmt_pending_tasks->fetchColumn();
if ($pending_tasks_count === false) {
    $pending_tasks_count = 0;
}

// For now, these will be placeholders as project/task logic is not yet implemented
// $active_projects_count = 0; // Placeholder This line is now handled by the code above
// $pending_tasks_count = 0;   // Placeholder This line is now handled by the code above

// Get total hours logged today by the user
$today_date = date('Y-m-d');
$stmt_hours_today = $pdo->prepare("
    SELECT SUM(hours_spent) 
    FROM time_entries 
    WHERE user_id = :user_id 
    AND DATE(start_time) = :today_date
");
$stmt_hours_today->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt_hours_today->bindParam(':today_date', $today_date, PDO::PARAM_STR);
$stmt_hours_today->execute();
$total_hours_today = $stmt_hours_today->fetchColumn();
if ($total_hours_today === false || $total_hours_today === null) {
    $total_hours_today = 0;
}
$total_hours_today = (float)$total_hours_today; // Cast to float

// Get total hours logged this week by the user
// Define 'this week' as from current week's Monday to current week's Sunday.
$today_day_of_week = date('N'); // 1 (for Monday) through 7 (for Sunday)
$start_of_week = date('Y-m-d', strtotime("-" . ($today_day_of_week - 1) . " days"));
$end_of_week = date('Y-m-d', strtotime("+" . (7 - $today_day_of_week) . " days"));

$stmt_hours_week = $pdo->prepare("
    SELECT SUM(hours_spent) 
    FROM time_entries 
    WHERE user_id = :user_id 
    AND DATE(start_time) BETWEEN :start_of_week AND :end_of_week
");
$stmt_hours_week->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt_hours_week->bindParam(':start_of_week', $start_of_week, PDO::PARAM_STR);
$stmt_hours_week->bindParam(':end_of_week', $end_of_week, PDO::PARAM_STR);
$stmt_hours_week->execute();
$total_hours_week = $stmt_hours_week->fetchColumn();
if ($total_hours_week === false || $total_hours_week === null) {
    $total_hours_week = 0;
}
$total_hours_week = (float)$total_hours_week; // Cast to float

?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
            <p>This is your dashboard. Here you can get a quick overview of your tasks and activities.</p>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row gy-4 mb-4">
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h5 class="card-title">Active Projects</h5>
                    <p class="card-text fs-4"><?php echo $active_projects_count; ?></p>
                    <a href="<?php echo BASE_URL; ?>projects.php" class="btn btn-sm btn-outline-primary">View Projects</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h5 class="card-title">Pending Tasks</h5>
                    <p class="card-text fs-4"><?php echo $pending_tasks_count; ?></p>
                    <a href="<?php echo BASE_URL; ?>tasks.php" class="btn btn-sm btn-outline-primary">View Tasks</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h5 class="card-title">Hours Logged Today</h5>
                    <p class="card-text fs-4"><?php echo $total_hours_today; ?></p>
                    <a href="<?php echo BASE_URL; ?>timetracker.php" class="btn btn-sm btn-outline-primary">View Time Logs</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h5 class="card-title">Hours Logged This Week</h5>
                    <p class="card-text fs-4"><?php echo $total_hours_week; ?></p>
                    <a href="<?php echo BASE_URL; ?>timetracker.php" class="btn btn-sm btn-outline-primary">View Time Logs</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Add Task Form -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4>Quick Add Task</h4>
                </div>
                <div class="card-body">
                    <form id="quickAddTaskForm"
                          hx-post="<?php echo BASE_URL; ?>php/task_handler.php?action=quick_add"
                          hx-target="#quickAddTaskResult"
                          hx-swap="innerHTML"
                          hx-indicator="#taskAddSpinner">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="task_name" class="form-label">Task Name</label>
                                <input type="text" class="form-control" id="task_name" name="task_name" required>
                            </div>
                            <div class="col-md-4">
                                <label for="project_id" class="form-label">Project (Optional)</label>
                                <select class="form-select" id="project_id" name="project_id">
                                    <option value="">Select Project</option>
                                    <?php
                                    // Fetch projects for the dropdown (user's projects)
                                    $stmt_user_projects_dropdown = $pdo->prepare("SELECT id, name FROM projects WHERE created_by_user_id = :user_id ORDER BY name ASC");
                                    $stmt_user_projects_dropdown->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                                    $stmt_user_projects_dropdown->execute();
                                    $user_projects_for_dropdown = $stmt_user_projects_dropdown->fetchAll(PDO::FETCH_ASSOC);

                                    if ($user_projects_for_dropdown) {
                                        foreach ($user_projects_for_dropdown as $project_dd_item) {
                                            echo "<option value=\"" . htmlspecialchars($project_dd_item['id']) . "\">" . htmlspecialchars($project_dd_item['name']) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                             <div class="col-md-2">
                                <label for="task_due_date" class="form-label">Due Date (Optional)</label>
                                <input type="date" class="form-control" id="task_due_date" name="due_date">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label for="task_description" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="task_description" name="description" rows="2"></textarea>
                        </div>
                        <div class="mt-3 d-flex align-items-center">
                            <button type="submit" class="btn btn-primary">Add Task</button>
                            <div id="taskAddSpinner" class="htmx-indicator spinner-border spinner-border-sm ms-2" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </form>
                    <div id="quickAddTaskResult" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Placeholder Sections for other dashboard elements -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Recently Active Tasks</div>
                <div class="card-body">
                    <?php
                    // Fetch Recently Active Tasks
                    // Tasks assigned to current user OR created by current user,
                    // not 'completed' or 'cancelled'.
                    // Order by due_date (NULLs last, then ascending), then by created_at (descending).
                    // Limit to 5.
                    $stmt_recent_tasks = $pdo->prepare("
                        SELECT 
                            t.id as task_id, 
                            t.name as task_name, 
                            t.due_date, 
                            p.name as project_name,
                            p.id as project_id -- Added for link
                        FROM tasks t
                        LEFT JOIN projects p ON t.project_id = p.id
                        WHERE 
                            (t.assigned_to_user_id = :user_id OR t.created_by_user_id = :user_id_creator)
                        AND 
                            (t.status NOT IN ('completed', 'cancelled'))
                        ORDER BY 
                            CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END, t.due_date ASC, t.created_at DESC
                        LIMIT 5
                    ");
                    $stmt_recent_tasks->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                    $stmt_recent_tasks->bindParam(':user_id_creator', $user_id, PDO::PARAM_INT);
                    $stmt_recent_tasks->execute();
                    $recent_tasks = $stmt_recent_tasks->fetchAll(PDO::FETCH_ASSOC);

                    if (count($recent_tasks) > 0):
                    ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($recent_tasks as $r_task): ?>
                                <li class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            <a href="<?php echo BASE_URL . 'tasks.php' . ($r_task['project_id'] ? '?project_id=' . htmlspecialchars($r_task['project_id']) : ''); ?>">
                                                <?php echo htmlspecialchars($r_task['task_name']); ?>
                                            </a>
                                        </h6>
                                        <small class="text-muted">
                                            <?php if ($r_task['due_date']): ?>
                                                Due: <?php echo date("M j, Y", strtotime($r_task['due_date'])); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <?php if ($r_task['project_name']): ?>
                                        <p class="mb-1 text-muted small">
                                            Project: <?php echo htmlspecialchars($r_task['project_name']); ?>
                                        </p>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">No recently active tasks.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Notifications / Reminders</div>
                <div class="card-body">
                    <?php
                    // Fetch Notifications / Reminders
                    // Tasks assigned to the user that are overdue, due today, or due tomorrow,
                    // and not 'completed' or 'cancelled'.
                    // Limit to 5, ordered by due date.

                    $today_iso = date('Y-m-d');
                    $tomorrow_iso = date('Y-m-d', strtotime('+1 day'));

                    // Using unique placeholders for each comparison
                    $stmt_reminders = $pdo->prepare("
                        SELECT 
                            t.id as task_id, 
                            t.name as task_name, 
                            t.due_date,
                            p.id as project_id,
                            p.name as project_name,
                            CASE
                                WHEN t.due_date < :case_today_iso1 THEN 'OVERDUE'
                                WHEN t.due_date = :case_today_iso2 THEN 'Due Today'
                                WHEN t.due_date = :case_tomorrow_iso THEN 'Due Tomorrow'
                                ELSE 'Upcoming' 
                            END as urgency_status
                        FROM tasks t
                        LEFT JOIN projects p ON t.project_id = p.id
                        WHERE 
                            t.assigned_to_user_id = :user_id
                        AND 
                            (t.status NOT IN ('completed', 'cancelled'))
                        AND
                            (t.due_date < :where_today_iso1 OR t.due_date = :where_today_iso2 OR t.due_date = :where_tomorrow_iso) 
                        ORDER BY t.due_date ASC
                        LIMIT 5
                    ");

                    // No bindParam calls for these specific placeholders

                    $stmt_reminders->execute([
                        ':user_id' => $user_id,
                        ':case_today_iso1' => $today_iso,
                        ':case_today_iso2' => $today_iso,
                        ':case_tomorrow_iso' => $tomorrow_iso,
                        ':where_today_iso1' => $today_iso,
                        ':where_today_iso2' => $today_iso,
                        ':where_tomorrow_iso' => $tomorrow_iso
                    ]);
                    $reminder_tasks = $stmt_reminders->fetchAll(PDO::FETCH_ASSOC);

                    if (count($reminder_tasks) > 0):
                    ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($reminder_tasks as $r_task): ?>
                                <li class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            <a href="<?php echo BASE_URL . 'tasks.php' . ($r_task['project_id'] ? '?project_id=' . htmlspecialchars($r_task['project_id']) : ''); ?>">
                                                <?php echo htmlspecialchars($r_task['task_name']); ?>
                                            </a>
                                        </h6>
                                        <small>
                                            <?php
                                            $urgency_class = 'text-muted'; // Default
                                            if ($r_task['urgency_status'] === 'OVERDUE') $urgency_class = 'text-danger fw-bold';
                                            if ($r_task['urgency_status'] === 'Due Today') $urgency_class = 'text-warning fw-bold';
                                            if ($r_task['urgency_status'] === 'Due Tomorrow') $urgency_class = 'text-info';
                                            ?>
                                            <span class="<?php echo $urgency_class; ?>"><?php echo htmlspecialchars($r_task['urgency_status']); ?></span>
                                            <?php if ($r_task['due_date']): ?>
                                                (<?php echo date("M j", strtotime($r_task['due_date'])); ?>)
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <?php if ($r_task['project_name']): ?>
                                        <p class="mb-1 text-muted small">
                                            Project: <?php echo htmlspecialchars($r_task['project_name']); ?>
                                        </p>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">No urgent reminders or upcoming deadlines.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/php/includes/footer.php'; ?>
