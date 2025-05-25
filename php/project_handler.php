<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "<div class='alert alert-danger p-3 m-3'>Error: You must be logged in.</div>";
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? null;
$pdo = get_db_connection();
$project_statuses = ['pending', 'active', 'on hold', 'completed', 'cancelled'];


try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'add' || $action === 'edit') {
            $project_id = ($action === 'edit') ? (int)($_GET['project_id'] ?? $_POST['project_id'] ?? 0) : null;
            $name = sanitize_string($_POST['name']);
            $description = sanitize_string($_POST['description'] ?? '');
            $status = sanitize_string($_POST['status'] ?? 'pending');
            $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;

            if (empty($name)) {
                echo "<div class='alert alert-warning p-2 m-2'>Project name is required.</div>";
                exit;
            }
            if (!in_array($status, $project_statuses)) {
                echo "<div class='alert alert-warning p-2 m-2'>Invalid project status.</div>";
                exit;
            }

            // Validate customer_id if provided
            if ($customer_id) {
                $stmt_cust_check = $pdo->prepare("SELECT id FROM customers WHERE id = :customer_id AND created_by_user_id = :user_id");
                $stmt_cust_check->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
                $stmt_cust_check->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt_cust_check->execute();
                if ($stmt_cust_check->fetchColumn() === false) {
                    echo "<div class='alert alert-danger p-2 m-2'>Selected customer is invalid or not owned by you.</div>";
                    exit;
                }
            }

            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO projects (name, description, status, customer_id, created_by_user_id) VALUES (:name, :description, :status, :customer_id, :user_id)");
            } else { // edit
                if ($project_id === 0) {
                    echo "<div class='alert alert-warning p-2 m-2'>Project ID is required for editing.</div>";
                    exit;
                }
                // Verify user owns this project before updating
                $stmt_check = $pdo->prepare("SELECT id FROM projects WHERE id = :project_id AND created_by_user_id = :user_id");
                $stmt_check->bindParam(':project_id', $project_id, PDO::PARAM_INT);
                $stmt_check->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt_check->execute();
                if ($stmt_check->fetchColumn() === false) {
                    http_response_code(403);
                    echo "<div class='alert alert-danger p-3 m-3'>Error: Permission denied or project not found.</div>";
                    exit;
                }
                $stmt = $pdo->prepare("UPDATE projects SET name = :name, description = :description, status = :status, customer_id = :customer_id WHERE id = :project_id AND created_by_user_id = :user_id");
                $stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
            }

            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':customer_id', $customer_id, $customer_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();

            set_flash_message('Project ' . ($action === 'add' ? 'added' : 'updated') . ' successfully!', 'success');
            // The list will be re-rendered below.

        } elseif ($action === 'delete') {
            $project_id = (int)($_GET['project_id'] ?? 0);
             if ($project_id === 0) {
                 echo "<div class='alert alert-warning p-2 m-2'>Project ID is required for deletion.</div>";
                 exit;
            }

            // Verify user owns this project
            $stmt_check = $pdo->prepare("SELECT id FROM projects WHERE id = :project_id AND created_by_user_id = :user_id");
            $stmt_check->bindParam(':project_id', $project_id, PDO::PARAM_INT);
            $stmt_check->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt_check->execute();
            if ($stmt_check->fetchColumn() === false) {
                http_response_code(403);
                exit; // Silently fail on permission issue for delete via HTMX row removal
            }

            // ON DELETE CASCADE for tasks related to this project is in DB schema
            $stmt = $pdo->prepare("DELETE FROM projects WHERE id = :project_id AND created_by_user_id = :user_id");
            $stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT); // Double check ownership
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                // For HTMX, if hx-target was the row and hx-swap="outerHTML",
                // returning an empty 200 OK response effectively deletes the row from the DOM.
                // A success message can be sent via HX-Trigger with an event for a toast notification.
                // set_flash_message('Project deleted successfully!', 'success'); // This won't show on empty response
                header('HX-Trigger: {"showMessage": {"level": "success", "message": "Project deleted successfully!"}}');
                echo ""; // Return empty string for HTMX to remove the element
                exit;
            } else {
                 // Project not found or not owned, already handled by check or DB did not delete
                 http_response_code(404); // Or appropriate error
                 echo "<div class='alert alert-warning p-2 m-2'>Could not delete project. It might have been already deleted or an error occurred.</div>";
                 exit;
            }
        }
    }

    // After add/edit (POST requests), re-render the project list.
    // Delete action exits above if successful.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'add' || $action === 'edit')) {
        $stmt_list = $pdo->prepare("
            SELECT p.id, p.name, p.description, p.status, p.created_at, c.name as customer_name
            FROM projects p
            LEFT JOIN customers c ON p.customer_id = c.id
            WHERE p.created_by_user_id = :user_id
            ORDER BY p.created_at DESC
        ");
        $stmt_list->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt_list->execute();
        $projects = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

        // Outputting the new project list HTML
        if (count($projects) > 0) {
            echo '<table class="table table-striped table-hover">';
            echo '<thead><tr><th>Name</th><th>Customer</th><th>Status</th><th>Created At</th><th class="text-end">Actions</th></tr></thead><tbody>';
            foreach ($projects as $project) {
                echo "<tr id='project-row-{$project['id']}'>";
                echo "<td>" . htmlspecialchars($project['name']) . "</td>";
                echo "<td>" . htmlspecialchars($project['customer_name'] ?? 'N/A') . "</td>";
                echo "<td><span class='badge bg-secondary'>" . htmlspecialchars(ucfirst($project['status'])) . "</span></td>";
                echo "<td>" . date("Y-m-d", strtotime($project['created_at'])) . "</td>";
                echo "<td class='text-end'>";
                echo "<button class='btn btn-sm btn-outline-secondary me-1' hx-get='" . BASE_URL . "templates/project_form.php?action=edit&project_id={$project['id']}' hx-target='#projectFormContainer' hx-swap='innerHTML' data-bs-toggle='modal' data-bs-target='#projectModal'>Edit</button> ";
                echo "<button class='btn btn-sm btn-outline-danger' hx-confirm='Are you sure you want to delete this project and all related tasks?' hx-post='" . BASE_URL . "php/project_handler.php?action=delete&project_id={$project['id']}' hx-target='#project-row-{$project['id']}' hx-swap='outerHTML'>Delete</button> ";
                echo "<a href='" . BASE_URL . "tasks.php?project_id={$project['id']}' class='btn btn-sm btn-outline-info ms-1'>View Tasks</a>";
                echo "</td></tr>";
            }
            echo '</tbody></table>';
        } else {
            echo '<div class="alert alert-info">No projects found. <a href="#" data-bs-toggle="modal" data-bs-target="#projectModal" hx-get="' . BASE_URL . 'templates/project_form.php?action=add" hx-target="#projectFormContainer" hx-swap="innerHTML">Add one now!</a></div>';
        }
        // Trigger client-side event for flash message
        if (isset($_SESSION['message'])) {
             header('HX-Trigger: {"showMessage": {"level": "' . ($_SESSION['message_type'] ?? 'info') . '", "message": "' . addslashes($_SESSION['message']) . '"}}');
             unset($_SESSION['message']);
             unset($_SESSION['message_type']);
        }
        exit;
    }

} catch (PDOException $e) {
    error_log("Project Handler Error: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    // HTMX can swap this error message into the target.
    echo "<div class='alert alert-danger p-3 m-3'>A database error occurred. Please try again. Details: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
