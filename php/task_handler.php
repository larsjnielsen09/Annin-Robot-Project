<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "<div class='alert alert-danger p-3 m-3'>Error: You must be logged in.</div>";
    exit;
}

$user_id = $_SESSION['user_id']; // Creator or modifier
$action = $_GET['action'] ?? null;
$pdo = get_db_connection();
$task_statuses = ['todo', 'in progress', 'on hold', 'testing', 'completed', 'cancelled'];

// For dashboard quick add, the target is different.
$is_quick_add = ($action === 'quick_add');
// For refreshing task list on tasks.php, potentially with filters
$origin_project_id = isset($_REQUEST['origin_project_id']) ? (int)$_REQUEST['origin_project_id'] : (isset($_POST['project_id']) ? (int)$_POST['project_id'] : null);
$origin_status_filter = isset($_REQUEST['origin_status']) ? sanitize_string($_REQUEST['origin_status']) : '';


try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'add' || $action === 'edit' || $action === 'quick_add') {
            $task_id = ($action === 'edit') ? (int)($_GET['task_id'] ?? $_POST['task_id'] ?? 0) : null;
            $name = sanitize_string($_POST['name']);
            $description = sanitize_string($_POST['description'] ?? '');
            $status = sanitize_string($_POST['status'] ?? 'todo');
            $due_date = !empty($_POST['due_date']) ? sanitize_string($_POST['due_date']) : null;
            $project_id = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
            $assigned_to_user_id = !empty($_POST['assigned_to_user_id']) ? (int)$_POST['assigned_to_user_id'] : null;

            if (empty($name)) {
                echo "<div class='alert alert-warning p-2 m-2'>Task name is required.</div>";
                exit;
            }
            if (!in_array($status, $task_statuses)) {
                echo "<div class='alert alert-warning p-2 m-2'>Invalid task status.</div>";
                exit;
            }
             if ($due_date && !DateTime::createFromFormat('Y-m-d', $due_date)) {
                echo "<div class='alert alert-warning p-2 m-2'>Invalid due date format. Please use YYYY-MM-DD.</div>";
                exit;
            }


            // Validate project_id if provided (user must own the project)
            if ($project_id) {
                $stmt_proj_check = $pdo->prepare("SELECT id FROM projects WHERE id = :project_id AND created_by_user_id = :user_id");
                $stmt_proj_check->bindParam(':project_id', $project_id, PDO::PARAM_INT);
                // For tasks, the project linked must be owned by the current session user *if* a project is specified.
                // This is because the dropdown for projects in task_form.php only lists user's own projects.
                $stmt_proj_check->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                $stmt_proj_check->execute();
                if ($stmt_proj_check->fetchColumn() === false) {
                    echo "<div class='alert alert-danger p-2 m-2'>Selected project is invalid or not owned by you.</div>";
                    exit;
                }
            }
            
            // Validate assigned_to_user_id if provided (user must exist)
            if($assigned_to_user_id) {
                $stmt_user_check = $pdo->prepare("SELECT id FROM users WHERE id = :assigned_user_id");
                $stmt_user_check->bindParam(':assigned_user_id', $assigned_to_user_id, PDO::PARAM_INT);
                $stmt_user_check->execute();
                if($stmt_user_check->fetchColumn() === false) {
                    echo "<div class='alert alert-danger p-2 m-2'>Assigned user is invalid.</div>";
                    exit;
                }
            }


            if ($action === 'add' || $action === 'quick_add') {
                $stmt = $pdo->prepare("INSERT INTO tasks (name, description, status, due_date, project_id, assigned_to_user_id, created_by_user_id) VALUES (:name, :description, :status, :due_date, :project_id, :assigned_to_user_id, :created_by_user_id)");
                $stmt->bindParam(':created_by_user_id', $user_id, PDO::PARAM_INT); // $user_id is $_SESSION['user_id']
            } else { // edit
                if ($task_id === 0) {
                    echo "<div class='alert alert-warning p-2 m-2'>Task ID is required for editing.</div>";
                    exit;
                }
                // Verify user has permission to edit this task (created it, assigned to it, or owns the project its part of)
                $stmt_check = $pdo->prepare("
                    SELECT t.id FROM tasks t 
                    LEFT JOIN projects p ON t.project_id = p.id
                    WHERE t.id = :task_id AND 
                          (t.created_by_user_id = :session_user_id OR 
                           t.assigned_to_user_id = :session_user_id_assigned OR 
                           p.created_by_user_id = :session_user_id_project_owner)
                    GROUP BY t.id 
                ");
                $stmt_check->bindParam(':task_id', $task_id, PDO::PARAM_INT);
                $stmt_check->bindParam(':session_user_id', $user_id, PDO::PARAM_INT);
                $stmt_check->bindParam(':session_user_id_assigned', $user_id, PDO::PARAM_INT);
                $stmt_check->bindParam(':session_user_id_project_owner', $user_id, PDO::PARAM_INT);
                $stmt_check->execute();
                if ($stmt_check->fetchColumn() === false) {
                    http_response_code(403);
                    echo "<div class='alert alert-danger p-3 m-3'>Error: Permission denied to edit this task or task not found.</div>";
                    exit;
                }
                $stmt = $pdo->prepare("UPDATE tasks SET name = :name, description = :description, status = :status, due_date = :due_date, project_id = :project_id, assigned_to_user_id = :assigned_to_user_id WHERE id = :task_id");
                $stmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
            }

            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':due_date', $due_date, $due_date === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindParam(':project_id', $project_id, $project_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindParam(':assigned_to_user_id', $assigned_to_user_id, $assigned_to_user_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->execute();
            
            $current_task_id = ($action === 'add' || $action === 'quick_add') ? $pdo->lastInsertId() : $task_id;


            if ($is_quick_add) {
                echo "<div class='alert alert-success alert-dismissible fade show mt-2' role='alert'>Task '".htmlspecialchars($name)."' added successfully! <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
                header("HX-Trigger: taskAddedFromDashboard"); 
                exit;
            } else {
                set_flash_message('Task ' . ($action === 'add' ? 'added' : 'updated') . ' successfully!', 'success');
            }

        } elseif ($action === 'delete') {
            $task_id = (int)($_GET['task_id'] ?? 0);
             if ($task_id === 0) {
                 echo "<div class='alert alert-warning p-2 m-2'>Task ID is required for deletion.</div>";
                 exit;
            }

            // Verify user has permission to delete this task
            $stmt_check = $pdo->prepare("
                SELECT t.id FROM tasks t 
                LEFT JOIN projects p ON t.project_id = p.id
                WHERE t.id = :task_id AND 
                      (t.created_by_user_id = :session_user_id OR 
                       t.assigned_to_user_id = :session_user_id_assigned OR 
                       p.created_by_user_id = :session_user_id_project_owner)
                GROUP BY t.id
            ");
            $stmt_check->bindParam(':task_id', $task_id, PDO::PARAM_INT);
            $stmt_check->bindParam(':session_user_id', $user_id, PDO::PARAM_INT);
            $stmt_check->bindParam(':session_user_id_assigned', $user_id, PDO::PARAM_INT);
            $stmt_check->bindParam(':session_user_id_project_owner', $user_id, PDO::PARAM_INT);
            $stmt_check->execute();
            if ($stmt_check->fetchColumn() === false) {
                http_response_code(403);
                echo "<div class='alert alert-danger p-3 m-3'>Error: Permission denied to delete this task or task not found.</div>";
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = :task_id");
            $stmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                // For delete from tasks.php, we might want to trigger a general update,
                // or rely on the hx-target="#task-row-..." hx-swap="outerHTML" to remove the row.
                // If filters are applied, this simple removal is fine.
                // A general HX-Trigger might be 'taskDeleted'.
                header('HX-Trigger: {"showMessage": {"level": "success", "message": "Task deleted successfully!"}}');
                echo ""; // Empty response for HTMX to remove the element
                exit;
            } else {
                 http_response_code(404); // Task not found or already deleted
                 echo "<div class='alert alert-warning p-2 m-2'>Could not delete task. It might have been already deleted or an error occurred.</div>";
                 exit;
            }
        }
    }

    // After add/edit (POST requests, not quick_add), re-render the task list.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'add' || $action === 'edit') && !$is_quick_add) {
        $sql = "SELECT t.id, t.name, t.description, t.status, t.due_date, p.name as project_name, p.id as project_id, u_assigned.username as assigned_to_username
                FROM tasks t
                LEFT JOIN projects p ON t.project_id = p.id
                LEFT JOIN users u_assigned ON t.assigned_to_user_id = u_assigned.id
                WHERE (p.created_by_user_id = :user_id OR t.assigned_to_user_id = :user_id_assigned OR t.created_by_user_id = :user_id_created)";
        
        $params = [
            ':user_id' => $user_id, // This is $_SESSION['user_id']
            ':user_id_assigned' => $user_id,
            ':user_id_created' => $user_id
        ];

        // Use $origin_project_id and $origin_status_filter from the beginning of the script
        if ($origin_project_id) {
            $sql .= " AND t.project_id = :project_id_filter"; // Use a different placeholder name to avoid conflict
            $params[':project_id_filter'] = $origin_project_id;
        }
        if (!empty($origin_status_filter)) {
            $sql .= " AND t.status = :status_filter";
            $params[':status_filter'] = $origin_status_filter;
        }
        $sql .= " GROUP BY t.id ORDER BY t.due_date ASC, t.created_at DESC";

        $stmt_list = $pdo->prepare($sql);
        $stmt_list->execute($params);
        $tasks = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

        if (count($tasks) > 0) {
            echo '<div class="table-responsive">';
            echo '<table class="table table-striped table-hover">';
            echo '<thead><tr><th>Name</th><th>Project</th><th>Assigned To</th><th>Status</th><th>Due Date</th><th class="text-end">Actions</th></tr></thead><tbody>';
            foreach ($tasks as $task_item) {
                echo "<tr id='task-row-{$task_item['id']}'>";
                echo "<td>" . htmlspecialchars($task_item['name']) . "</td>";
                echo "<td>" . htmlspecialchars($task_item['project_name'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($task_item['assigned_to_username'] ?? 'Unassigned') . "</td>";
                echo "<td><span class='badge bg-info text-dark'>" . htmlspecialchars(ucfirst($task_item['status'])) . "</span></td>";
                echo "<td>" . ($task_item['due_date'] ? date("Y-m-d", strtotime($task_item['due_date'])) : 'N/A') . "</td>";
                echo "<td class='text-end'>";
                $edit_params = "action=edit&task_id={$task_item['id']}";
                if ($origin_project_id) $edit_params .= "&project_id=" . $origin_project_id;

                $delete_params = "action=delete&task_id={$task_item['id']}";
                if ($origin_project_id) $delete_params .= "&origin_project_id=" . $origin_project_id;
                if ($origin_status_filter) $delete_params .= "&origin_status=" . $origin_status_filter;


                echo "<button class='btn btn-sm btn-outline-secondary me-1' hx-get='" . BASE_URL . "templates/task_form.php?{$edit_params}' hx-target='#taskFormContainer' hx-swap='innerHTML' data-bs-toggle='modal' data-bs-target='#taskModal'>Edit</button> ";
                echo "<button class='btn btn-sm btn-outline-danger' hx-confirm='Are you sure you want to delete this task?' hx-post='" . BASE_URL . "php/task_handler.php?{$delete_params}' hx-target='#task-row-{$task_item['id']}' hx-swap='outerHTML'>Delete</button> ";
                echo "<a href='" . BASE_URL . "timetracker.php?task_id={$task_item['id']}' class='btn btn-sm btn-outline-success ms-1'>Log Time</a>";
                echo "</td></tr>";
            }
            echo '</tbody></table>';
            echo '</div>'; // Closing table-responsive
        } else {
             $add_link_params = $origin_project_id ? '&project_id=' . $origin_project_id : '';
            echo '<div class="alert alert-info">No tasks found matching your criteria. <a href="#" data-bs-toggle="modal" data-bs-target="#taskModal" hx-get="' . BASE_URL . 'templates/task_form.php?action=add' . $add_link_params . '" hx-target="#taskFormContainer" hx-swap="innerHTML">Add one now!</a></div>';
        }
        
        // For add/edit on tasks.php, trigger a general event that might be used for toasts via header.php
        if (isset($_SESSION['message'])) {
            header('HX-Trigger: {"showMessage": {"level": "' . ($_SESSION['message_type'] ?? 'info') . '", "message": "' . addslashes($_SESSION['message']) . '"}}');
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
        }
        exit;
    }

} catch (PDOException $e) {
    error_log("Task Handler Error: " . $e->getMessage());
    $error_message = "<div class='alert alert-danger p-3 m-3'>A database error occurred: " . htmlspecialchars($e->getMessage()) . "</div>";
    // Set appropriate HTTP status code for errors
    if (!headers_sent()) {
        http_response_code(500); // Internal Server Error
    }
    if ($is_quick_add) {
        echo $error_message;
    } else {
        // For the main task list, this error will replace the list.
        // If the modal is open, a more targeted error display within the modal might be desired.
        echo $error_message;
    }
}
?>
