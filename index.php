<?php
$page_title = "Dashboard";
require_once __DIR__ . '/php/auth/auth_check.php'; // Ensure user is logged in
require_once __DIR__ . '/php/includes/header.php'; // Includes session_start, config, etc.
require_once __DIR__ . '/php/core/database.php'; // For database connection

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch some data for the dashboard (examples)
$pdo = get_db_connection();

// Example: Get count of active projects for the user
// This assumes 'projects' table has 'created_by_user_id' and 'status'
// $stmt_projects = $pdo->prepare("SELECT COUNT(*) as active_projects FROM projects WHERE created_by_user_id = :user_id AND status = 'active'");
// $stmt_projects->bindParam(':user_id', $user_id, PDO::PARAM_INT);
// $stmt_projects->execute();
// $active_projects_count = $stmt_projects->fetchColumn();

// Example: Get count of tasks assigned to the user that are not 'completed'
// This assumes 'tasks' table has 'assigned_to_user_id' and 'status'
// $stmt_tasks = $pdo->prepare("SELECT COUNT(*) as pending_tasks FROM tasks WHERE assigned_to_user_id = :user_id AND status != 'completed'");
// $stmt_tasks->bindParam(':user_id', $user_id, PDO::PARAM_INT);
// $stmt_tasks->execute();
// $pending_tasks_count = $stmt_tasks->fetchColumn();

// For now, these will be placeholders as project/task logic is not yet implemented
$active_projects_count = 0; // Placeholder
$pending_tasks_count = 0;   // Placeholder
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
                                    // Placeholder for project list - will be populated later
                                    // Example:
                                    // $stmt_user_projects = $pdo->prepare("SELECT id, name FROM projects WHERE created_by_user_id = :user_id ORDER BY name ASC");
                                    // $stmt_user_projects->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                                    // $stmt_user_projects->execute();
                                    // while ($project = $stmt_user_projects->fetch(PDO::FETCH_ASSOC)) {
                                    //    echo "<option value="" . htmlspecialchars($project['id']) . "">" . htmlspecialchars($project['name']) . "</option>";
                                    // }
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
