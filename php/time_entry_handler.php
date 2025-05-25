<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "<div class='alert alert-danger p-3 m-3'>Error: You must be logged in.</div>";
    exit;
}

$user_id = $_SESSION['user_id']; // User logging the time
$action = $_GET['action'] ?? null;
$pdo = get_db_connection();

// For refreshing time entry list on timetracker.php, potentially with filters
$origin_task_id = isset($_REQUEST['origin_task_id']) ? (int)$_REQUEST['origin_task_id'] : null;
// We might add other filters like project_id, date_from, date_to to be passed back if needed.
// For now, we'll also grab all potential filters if they are part of the request to the handler
$origin_project_id_filter = isset($_REQUEST['origin_project_id_filter']) ? (int)$_REQUEST['origin_project_id_filter'] : null;
$origin_date_from_filter = isset($_REQUEST['origin_date_from_filter']) ? sanitize_string($_REQUEST['origin_date_from_filter']) : '';
$origin_date_to_filter = isset($_REQUEST['origin_date_to_filter']) ? sanitize_string($_REQUEST['origin_date_to_filter']) : '';


try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'add' || $action === 'edit') {
            $entry_id = ($action === 'edit') ? (int)($_GET['entry_id'] ?? $_POST['entry_id'] ?? 0) : null;
            $task_id = (int)$_POST['task_id'];
            $start_time_str = sanitize_string($_POST['start_time']);
            $end_time_str = sanitize_string($_POST['end_time'] ?? '');
            $hours_spent_input = $_POST['hours_spent'] ?? '';
            $notes = sanitize_string($_POST['notes'] ?? '');

            // Validation
            if (empty($task_id) || empty($start_time_str)) {
                http_response_code(400);
                echo "<div class='alert alert-warning p-2 m-2'>Task and Start Time are required.</div>";
                exit;
            }
            $start_time = new DateTime($start_time_str);
            $end_time = null;
            if (!empty($end_time_str)) {
                $end_time = new DateTime($end_time_str);
                if ($end_time <= $start_time) {
                    http_response_code(400);
                    echo "<div class='alert alert-warning p-2 m-2'>End Time must be after Start Time.</div>";
                    exit;
                }
            }

            // Calculate or use provided hours_spent
            $hours_spent = null;
            if (!empty($hours_spent_input)) {
                $hours_spent = filter_var($hours_spent_input, FILTER_VALIDATE_FLOAT);
                if ($hours_spent === false || $hours_spent < 0) {
                     http_response_code(400);
                     echo "<div class='alert alert-warning p-2 m-2'>Invalid Hours Spent value.</div>";
                     exit;
                }
            } elseif ($end_time) { // Calculate if end_time is set and hours_spent not manually entered
                $diff = $end_time->getTimestamp() - $start_time->getTimestamp();
                $hours_spent = round($diff / 3600, 2); // Convert seconds to hours, round to 2 decimal places
            }
            if ($hours_spent === null && empty($end_time_str)) {
                 http_response_code(400);
                 echo "<div class='alert alert-warning p-2 m-2'>Either End Time or Hours Spent must be provided if timer is not active.</div>";
                 exit;
            }


            // Validate task_id (user must have some relation to it: created task, assigned to task, or created project of task)
            $stmt_task_check = $pdo->prepare("
                SELECT t.id FROM tasks t LEFT JOIN projects p ON t.project_id = p.id
                WHERE t.id = :task_id AND (p.created_by_user_id = :user_id OR t.assigned_to_user_id = :user_id_assigned OR t.created_by_user_id = :user_id_created)
                GROUP BY t.id
            ");
            $stmt_task_check->execute([
                ':task_id' => $task_id,
                ':user_id' => $user_id,
                ':user_id_assigned' => $user_id,
                ':user_id_created' => $user_id
            ]);
            if ($stmt_task_check->fetchColumn() === false) {
                http_response_code(403);
                echo "<div class='alert alert-danger p-2 m-2'>Selected task is invalid or you don't have permission to log time for it.</div>";
                exit;
            }


            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO time_entries (task_id, user_id, start_time, end_time, hours_spent, notes) VALUES (:task_id, :user_id, :start_time, :end_time, :hours_spent, :notes)");
            } else { // edit
                if ($entry_id === 0) {
                    http_response_code(400);
                    echo "<div class='alert alert-warning p-2 m-2'>Entry ID is required for editing.</div>";
                    exit;
                }
                // Verify user owns this time entry before updating
                $stmt_check = $pdo->prepare("SELECT id FROM time_entries WHERE id = :entry_id AND user_id = :user_id");
                $stmt_check->bindParam(':entry_id', $entry_id, PDO::PARAM_INT);
                $stmt_check->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt_check->execute();
                if ($stmt_check->fetchColumn() === false) {
                    http_response_code(403);
                    echo "<div class='alert alert-danger p-3 m-3'>Error: Permission denied or time entry not found.</div>";
                    exit;
                }
                $stmt = $pdo->prepare("UPDATE time_entries SET task_id = :task_id, start_time = :start_time, end_time = :end_time, hours_spent = :hours_spent, notes = :notes WHERE id = :entry_id AND user_id = :user_id");
                $stmt->bindParam(':entry_id', $entry_id, PDO::PARAM_INT);
            }

            $stmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':start_time', $start_time->format('Y-m-d H:i:s'));
            $stmt->bindParam(':end_time', $end_time ? $end_time->format('Y-m-d H:i:s') : null, $end_time ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindParam(':hours_spent', $hours_spent, $hours_spent !== null ? PDO::PARAM_STR : PDO::PARAM_NULL); // Stored as DECIMAL(10,2)
            $stmt->bindParam(':notes', $notes);
            $stmt->execute();
            
            set_flash_message('Time entry ' . ($action === 'add' ? 'added' : 'updated') . ' successfully!', 'success');
            // List re-rendering logic is below.

        } elseif ($action === 'delete') {
            $entry_id = (int)($_GET['entry_id'] ?? 0);
             if ($entry_id === 0) {
                 http_response_code(400);
                 echo "<div class='alert alert-warning p-2 m-2'>Entry ID is required for deletion.</div>";
                 exit;
            }

            // Verify user owns this time entry
            $stmt_check = $pdo->prepare("SELECT id FROM time_entries WHERE id = :entry_id AND user_id = :user_id");
            $stmt_check->bindParam(':entry_id', $entry_id, PDO::PARAM_INT);
            $stmt_check->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt_check->execute();
            if ($stmt_check->fetchColumn() === false) {
                http_response_code(403);
                // For HTMX, if the target is the row, this might result in the row not being removed
                // if the server sends back an error. Client-side can handle this with hx-on:htmx:responseError
                echo "<div class='alert alert-danger'>Error: Permission denied or entry not found.</div>";
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM time_entries WHERE id = :entry_id AND user_id = :user_id");
            $stmt->bindParam(':entry_id', $entry_id, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                // On successful deletion, HTMX will remove the row based on hx-target and hx-swap="outerHTML".
                // Send a trigger for a toast message.
                header('HX-Trigger: {"showMessage": {"level": "success", "message": "Time entry deleted successfully!"}}');
                echo ""; // Return empty for outerHTML swap to remove the element
                exit;
            } else {
                http_response_code(404); // Or other appropriate error
                echo "<div class='alert alert-warning'>Could not delete time entry. It might have been already deleted.</div>";
                exit;
            }
        }
    }

    // After add/edit (POST requests), re-render the time entry list.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'add' || $action === 'edit')) {
        $sql_list = "SELECT te.id, te.start_time, te.end_time, te.hours_spent, te.notes, te.created_at,
                            t.name as task_name, t.id as task_id, p.name as project_name, p.id as project_id
                     FROM time_entries te
                     JOIN tasks t ON te.task_id = t.id
                     LEFT JOIN projects p ON t.project_id = p.id
                     WHERE te.user_id = :user_id";
        $params_list = [':user_id' => $user_id];

        // Re-apply filters that were active on the timetracker.php page
        // These should be passed in the hx-post URL from the form if they need to be persisted.
        // For simplicity, we are using origin_task_id, and now added others.
        // The form's hx-post URL should include these as GET params if they are to be used for re-filtering.
        // Example: hx-post="...handler.php?action=add&origin_task_id=X&origin_project_id_filter=Y..."
        
        if ($origin_task_id) {
            $sql_list .= " AND te.task_id = :origin_task_id";
            $params_list[':origin_task_id'] = $origin_task_id;
        }
        if ($origin_project_id_filter) {
            $sql_list .= " AND t.project_id = :origin_project_id_filter";
            $params_list[':origin_project_id_filter'] = $origin_project_id_filter;
        }
        if (!empty($origin_date_from_filter)) {
            $sql_list .= " AND DATE(te.start_time) >= :origin_date_from_filter";
            $params_list[':origin_date_from_filter'] = $origin_date_from_filter;
        }
        if (!empty($origin_date_to_filter)) {
            $sql_list .= " AND DATE(te.start_time) <= :origin_date_to_filter";
            $params_list[':origin_date_to_filter'] = $origin_date_to_filter;
        }


        $sql_list .= " ORDER BY te.start_time DESC";
        $stmt_list = $pdo->prepare($sql_list);
        $stmt_list->execute($params_list);
        $time_entries = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

        if (count($time_entries) > 0) {
            echo '<table class="table table-striped table-hover">';
            echo '<thead><tr><th>Task</th><th>Project</th><th>Start Time</th><th>End Time</th><th>Hours Spent</th><th>Notes</th><th class="text-end">Actions</th></tr></thead><tbody>';
            $total_hours_filtered = 0;
            foreach ($time_entries as $entry_item) {
                $total_hours_filtered += (float)$entry_item['hours_spent'];
                echo "<tr id='timeentry-row-{$entry_item['id']}'>";
                echo "<td>" . htmlspecialchars($entry_item['task_name']) . "</td>";
                echo "<td>" . htmlspecialchars($entry_item['project_name'] ?? 'N/A') . "</td>";
                echo "<td>" . date("Y-m-d H:i", strtotime($entry_item['start_time'])) . "</td>";
                echo "<td>" . ($entry_item['end_time'] ? date("Y-m-d H:i", strtotime($entry_item['end_time'])) : '<i>Running</i>') . "</td>";
                echo "<td>" . number_format($entry_item['hours_spent'], 2) . "</td>";
                echo "<td>" . nl2br(htmlspecialchars($entry_item['notes'] ?? '')) . "</td>";
                echo "<td class='text-end'>";
                // Pass along filter parameters to edit/delete calls for context
                $context_params = "";
                if ($origin_task_id) $context_params .= "&task_id=" . $origin_task_id; // For pre-selection in form
                // Potentially add other origin filters here if needed for delete or edit form context

                echo "<button class='btn btn-sm btn-outline-secondary me-1' hx-get='" . BASE_URL . "templates/time_entry_form.php?action=edit&entry_id={$entry_item['id']}{$context_params}' hx-target='#timeEntryFormContainer' hx-swap='innerHTML' data-bs-toggle='modal' data-bs-target='#timeEntryModal'>Edit</button> ";
                echo "<button class='btn btn-sm btn-outline-danger' hx-confirm='Are you sure you want to delete this time entry?' hx-post='" . BASE_URL . "php/time_entry_handler.php?action=delete&entry_id={$entry_item['id']}{$context_params}' hx-target='#timeentry-row-{$entry_item['id']}' hx-swap='outerHTML'>Delete</button>";
                echo "</td></tr>";
            }
            echo '</tbody><tfoot><tr><th colspan="4" class="text-end">Total Hours (Filtered):</th><th>'.number_format($total_hours_filtered, 2).'</th><td colspan="2"></td></tr></tfoot></table>';
        } else {
            $add_link_params = $origin_task_id ? '&task_id=' . $origin_task_id : '';
            // Add other origin filters to the "Add one now" link if desired
            echo '<div class="alert alert-info">No time entries found matching your criteria. <a href="#" data-bs-toggle="modal" data-bs-target="#timeEntryModal" hx-get="' . BASE_URL . 'templates/time_entry_form.php?action=add' . $add_link_params . '" hx-target="#timeEntryFormContainer" hx-swap="innerHTML">Add one now!</a></div>';
        }
        
        // Trigger a client-side event to show the flash message (e.g., as a toast)
        if (isset($_SESSION['message'])) {
             header('HX-Trigger: {"showMessage": {"level": "' . ($_SESSION['message_type'] ?? 'info') . '", "message": "' . addslashes($_SESSION['message']) . '"}}');
             unset($_SESSION['message']);
             unset($_SESSION['message_type']);
        }
        exit;
    }

} catch (PDOException $e) {
    error_log("Time Entry Handler Error: " . $e->getMessage());
    if (!headers_sent()) http_response_code(500);
    echo "<div class='alert alert-danger p-3 m-3'>A database error occurred: " . htmlspecialchars($e->getMessage()) . "</div>";
} catch (Exception $e) { // Catch DateTime constructor errors etc.
    error_log("Time Entry Data Error: " . $e->getMessage());
    if (!headers_sent()) http_response_code(400); // Bad request due to invalid data
    echo "<div class='alert alert-danger p-3 m-3'>Invalid data provided: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
