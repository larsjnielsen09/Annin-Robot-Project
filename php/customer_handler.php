<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    // For HTMX requests, returning an error message that can be displayed is often better than redirecting.
    // However, if it's not an HTMX request or critical, a redirect might be okay.
    http_response_code(401); // Unauthorized
    echo "<div class='alert alert-danger p-3 m-3'>Error: You must be logged in to perform this action.</div>";
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? null;
$pdo = get_db_connection();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'add') {
            $name = sanitize_string($_POST['name']);
            $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $phone = sanitize_string($_POST['phone'] ?? '');
            $address = sanitize_string($_POST['address'] ?? '');

            if (empty($name)) {
                // This message could be displayed by HTMX if the target is set appropriately
                // For now, we'll let the client-side validation or a general error message handle this.
                // Or, to send a specific HTMX response for an error within the modal:
                http_response_code(400); // Bad Request
                // It's often better to have a dedicated error display area within the modal.
                // For simplicity, we'll rely on the modal not closing and the required field indicator.
                // Or, send a specific error message to be swapped into an error div.
                echo "<div class='alert alert-danger'>Customer name is required. Form not submitted.</div>";
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO customers (name, email, phone, address, created_by_user_id) VALUES (:name, :email, :phone, :address, :user_id)");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();

            set_flash_message('Customer added successfully!', 'success');
            // The list will be re-rendered below.

        } elseif ($action === 'edit') {
            $customer_id = (int)($_POST['customer_id'] ?? 0); // GET customer_id for action, POST for data
            $name = sanitize_string($_POST['name']);
            $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $phone = sanitize_string($_POST['phone'] ?? '');
            $address = sanitize_string($_POST['address'] ?? '');

            if (empty($name) || $customer_id === 0) {
                 http_response_code(400);
                 echo "<div class='alert alert-danger'>Customer name and ID are required. Form not submitted.</div>";
                 exit;
            }

            // Verify user owns this customer before updating
            $stmt_check = $pdo->prepare("SELECT id FROM customers WHERE id = :customer_id AND created_by_user_id = :user_id");
            $stmt_check->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
            $stmt_check->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt_check->execute();
            if ($stmt_check->fetchColumn() === false) {
                http_response_code(403); // Forbidden
                echo "<div class='alert alert-danger p-3 m-3'>Error: You do not have permission to edit this customer or customer not found.</div>";
                exit;
            }

            $stmt = $pdo->prepare("UPDATE customers SET name = :name, email = :email, phone = :phone, address = :address WHERE id = :customer_id AND created_by_user_id = :user_id");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            set_flash_message('Customer updated successfully!', 'success');
            // The list will be re-rendered below.

        } elseif ($action === 'delete') {
            // Delete is also a POST request triggered by HTMX from a button, not a form.
            // customer_id comes from GET parameter in hx-post URL
            $customer_id = (int)($_GET['customer_id'] ?? 0);
             if ($customer_id === 0) {
                 http_response_code(400);
                 // This message might not be displayed if the target row is removed by HTMX on error.
                 // Consider HX-Reswap: none on error, or target an error div.
                 echo "Error: Customer ID is required for deletion.";
                 exit;
            }

            // Verify user owns this customer before deleting
            $stmt_check = $pdo->prepare("SELECT id FROM customers WHERE id = :customer_id AND created_by_user_id = :user_id");
            $stmt_check->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
            $stmt_check->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt_check->execute();
            if ($stmt_check->fetchColumn() === false) {
                http_response_code(403);
                // Error message for delete failure (e.g., if target is not just the row but a status area)
                // For hx-target="outerHTML" on the row, an error status code without content might be enough
                // or a specific error message if you have an error display area.
                exit; // Silently fail or return error message
            }

            $stmt = $pdo->prepare("DELETE FROM customers WHERE id = :customer_id AND created_by_user_id = :user_id");
            $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                // For HTMX, if hx-target was the row and hx-swap="outerHTML",
                // returning an empty 200 OK response effectively deletes the row from the DOM.
                // A success message can be sent via HX-Trigger with an event for a toast notification.
                // set_flash_message('Customer deleted successfully!', 'success'); // This won't show on empty response
                header('HX-Trigger: {"showMessage": {"level": "success", "message": "Customer deleted successfully!"}}');
                echo ""; // Return empty string for HTMX to remove the element
                exit;
            } else {
                http_response_code(404); // Not Found or already deleted
                // echo "Error deleting customer or customer not found.";
                exit;
            }
        }
    } // End of POST check

    // After add/edit (POST requests), re-render the customer list for HTMX.
    // This block will only be reached for add/edit, as delete exits earlier.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'add' || $action === 'edit')) {
        $stmt_list = $pdo->prepare("SELECT id, name, email, phone FROM customers WHERE created_by_user_id = :user_id ORDER BY name ASC");
        $stmt_list->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt_list->execute();
        $customers = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

        // This output will replace #customerList content due to hx-target in the form
        // We need to ensure the flash message set earlier is displayed.
        // One way is to include the message display logic here if it's not part of the main layout that persists.
        // However, header.php (included in customers.php) should display flash messages.
        // If the HTMX swap replaces the part of the page containing flash messages, they might not show.
        // The `hx-on::after-request` on the form handles modal closing.
        // For messages, HX-Trigger is a good pattern.

        // Outputting the new customer list HTML
        if (count($customers) > 0) {
            echo '<table class="table table-striped table-hover">';
            echo '<thead><tr><th>Name</th><th>Email</th><th>Phone</th><th class="text-end">Actions</th></tr></thead><tbody>';
            foreach ($customers as $customer) {
                echo "<tr id='customer-row-{$customer['id']}'>";
                echo "<td>" . htmlspecialchars($customer['name']) . "</td>";
                echo "<td>" . htmlspecialchars($customer['email'] ?? '-') . "</td>";
                echo "<td>" . htmlspecialchars($customer['phone'] ?? '-') . "</td>";
                echo "<td class='text-end'>";
                echo "<button class='btn btn-sm btn-outline-secondary me-1' hx-get='" . BASE_URL . "templates/customer_form.php?action=edit&customer_id={$customer['id']}' hx-target='#customerFormContainer' hx-swap='innerHTML' data-bs-toggle='modal' data-bs-target='#customerModal'>Edit</button> ";
                echo "<button class='btn btn-sm btn-outline-danger' hx-confirm='Are you sure you want to delete this customer and all related projects/tasks?' hx-post='" . BASE_URL . "php/customer_handler.php?action=delete&customer_id={$customer['id']}' hx-target='#customer-row-{$customer['id']}' hx-swap='outerHTML'>Delete</button>";
                echo "</td></tr>";
            }
            echo '</tbody></table>';
        } else {
            echo '<div class="alert alert-info">No customers found. <a href="#" data-bs-toggle="modal" data-bs-target="#customerModal" hx-get="' . BASE_URL . 'templates/customer_form.php?action=add" hx-target="#customerFormContainer" hx-swap="innerHTML">Add one now!</a></div>';
        }
        // Trigger a client-side event to show the flash message (e.g., as a toast)
        // This assumes you have JavaScript on the main page listening for 'showMessage'
        if (isset($_SESSION['message'])) {
             header('HX-Trigger: {"showMessage": {"level": "' . ($_SESSION['message_type'] ?? 'info') . '", "message": "' . addslashes($_SESSION['message']) . '"}}');
             unset($_SESSION['message']);
             unset($_SESSION['message_type']);
        }
        exit;
    }

} catch (PDOException $e) {
    error_log("Customer Handler Error: " . $e->getMessage());
    http_response_code(500);
    // Send a user-friendly error message that HTMX can display
    echo "<div class='alert alert-danger p-3 m-3'>A database error occurred. Please try again. Details: " . htmlspecialchars($e->getMessage()) . "</div>";
    // For non-HTMX or as a fallback, you might redirect with a flash message,
    // but for HTMX, directly outputting the error is often preferred for the targeted swap.
}
?>
