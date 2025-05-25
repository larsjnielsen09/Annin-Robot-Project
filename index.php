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
$total_hours_today = 0;    // Placeholder
$total_hours_week = 0;     // Placeholder

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
                    <p class="text-muted">Task activity will be shown here.</p>
                    <!-- List of tasks or activity feed -->
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Notifications / Reminders</div>
                <div class="card-body">
                    <p class="text-muted">Important updates will appear here.</p>
                    <!-- Notifications list -->
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/php/includes/footer.php'; ?>
