<?php
require_once __DIR__ . '/../php/core/config.php';
require_once __DIR__ . '/../php/core/database.php';
require_once __DIR__ . '/../php/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo "<div class='alert alert-danger'>Authentication required.</div>";
    exit;
}

$action = $_GET['action'] ?? 'add'; // 'add' or 'edit'
$task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : null;
$current_project_id_filter = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null; // From tasks.php filter
$user_id = $_SESSION['user_id'];
$pdo = get_db_connection();

$task = [
    'id' => null, 'name' => '', 'description' => '', 'status' => 'todo',
    'due_date' => null, 'project_id' => $current_project_id_filter, 'assigned_to_user_id' => null
];
$task_statuses = ['todo', 'in progress', 'on hold', 'testing', 'completed', 'cancelled'];

$modal_title = "Add New Task";
$form_action_url = BASE_URL . "php/task_handler.php?action=add";
if ($current_project_id_filter) {
    $form_action_url .= "&origin_project_id=" . $current_project_id_filter; // To return to filtered view
}


if ($action === 'edit' && $task_id) {
    // User can edit tasks they created, are assigned to, or are part of projects they created.
    $stmt = $pdo->prepare("
        SELECT t.* FROM tasks t
        LEFT JOIN projects p ON t.project_id = p.id
        WHERE t.id = :task_id AND (p.created_by_user_id = :user_id OR t.assigned_to_user_id = :user_id_assigned OR t.created_by_user_id = :user_id_created)
        GROUP BY t.id
    ");
    $stmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id_assigned', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id_created', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $task_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($task_data) {
        $task = $task_data;
        $modal_title = "Edit Task: " . htmlspecialchars($task['name']);
        $form_action_url = BASE_URL . "php/task_handler.php?action=edit&task_id=" . $task['id'];
        if ($current_project_id_filter) { // Persist filter context
             $form_action_url .= "&origin_project_id=" . $current_project_id_filter;
        }
    } else {
        echo "<div class='alert alert-danger'>Task not found or access denied.</div>";
        exit;
    }
}

// Fetch projects for dropdown (user's projects)
$stmt_projects = $pdo->prepare("SELECT id, name FROM projects WHERE created_by_user_id = :user_id ORDER BY name ASC");
$stmt_projects->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt_projects->execute();
$user_projects = $stmt_projects->fetchAll(PDO::FETCH_ASSOC);

// Fetch users for assignment dropdown (all users for simplicity, could be scoped)
$stmt_users = $pdo->query("SELECT id, username FROM users ORDER BY username ASC");
$assignable_users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
?>

<script>
    if (document.getElementById('taskModalLabel')) {
        document.getElementById('taskModalLabel').textContent = '<?php echo $modal_title; ?>';
    }
</script>

<form id="taskForm"
      hx-post="<?php echo $form_action_url; ?>"
      hx-target="#taskList" <?php // This might need to change if pagination/filtering is complex ?>
      hx-swap="innerHTML" <?php // innerHTML to replace the list, could be outerHTML for the #taskList div itself ?>
      hx-indicator="#taskFormSpinner"
      hx-on::after-request="if(event.detail.successful) { bootstrap.Modal.getInstance(document.getElementById('taskModal')).hide(); document.getElementById('taskForm').reset(); <?php if ($action === 'add' && $current_project_id_filter == null) echo "htmx.trigger('#quickAddTaskResult', 'clearResult');"; ?> }">
      <?php // Added clearResult for dashboard quick add ?>
    <input type="hidden" name="task_id" value="<?php echo htmlspecialchars($task['id'] ?? ''); ?>">

    <div class="mb-3">
        <label for="task_name" class="form-label">Task Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="task_name" name="name" value="<?php echo htmlspecialchars($task['name']); ?>" required>
    </div>

    <div class="mb-3">
        <label for="project_id" class="form-label">Project (Optional)</label>
        <select class="form-select" id="project_id" name="project_id">
            <option value="">None</option>
            <?php foreach ($user_projects as $project_opt): ?>
                <option value="<?php echo $project_opt['id']; ?>" <?php echo ($task['project_id'] == $project_opt['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($project_opt['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label for="assigned_to_user_id" class="form-label">Assign To (Optional)</label>
        <select class="form-select" id="assigned_to_user_id" name="assigned_to_user_id">
            <option value="">Unassigned</option>
            <?php foreach ($assignable_users as $user_opt): ?>
                <option value="<?php echo $user_opt['id']; ?>" <?php echo ($task['assigned_to_user_id'] == $user_opt['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($user_opt['username']); ?>
                </option>
            <?php endforeach; ?>
             <option value="<?php echo $user_id; ?>" <?php if (empty($task['assigned_to_user_id']) && $action == 'add') echo 'selected'; ?>>Assign to Me (<?php echo htmlspecialchars($_SESSION['username']); ?>)</option>
        </select>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="task_status" class="form-label">Status</label>
            <select class="form-select" id="task_status" name="status">
                <?php foreach ($task_statuses as $status_option): ?>
                    <option value="<?php echo $status_option; ?>" <?php echo ($task['status'] == $status_option) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(ucfirst($status_option)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6 mb-3">
            <label for="due_date" class="form-label">Due Date (Optional)</label>
            <input type="date" class="form-control" id="due_date" name="due_date" value="<?php echo htmlspecialchars($task['due_date'] ?? ''); ?>">
        </div>
    </div>

    <div class="mb-3">
        <label for="task_description" class="form-label">Description</label>
        <textarea class="form-control" id="task_description" name="description" rows="3"><?php echo htmlspecialchars($task['description'] ?? ''); ?></textarea>
    </div>

    <div class="d-flex justify-content-end align-items-center">
        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Close</button>
        <button type="submit" class="btn btn-primary">
            <?php echo ($action === 'edit' ? 'Save Changes' : 'Add Task'); ?>
        </button>
        <div id="taskFormSpinner" class="htmx-indicator spinner-border spinner-border-sm ms-2" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
</form>
