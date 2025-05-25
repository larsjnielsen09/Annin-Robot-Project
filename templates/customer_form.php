<?php
// This template is loaded via HTMX into a modal or a div.
// It requires session and database access for editing.
require_once __DIR__ . '/../php/core/config.php'; // For BASE_URL, session_start
require_once __DIR__ . '/../php/core/database.php';
require_once __DIR__ . '/../php/includes/functions.php';

// Auth check is implicitly handled by the parent page loading this,
// but good to ensure critical operations are protected.
if (!isset($_SESSION['user_id'])) {
    echo "<div class='alert alert-danger'>Authentication required.</div>";
    exit;
}

$action = $_GET['action'] ?? 'add'; // 'add' or 'edit'
$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : null;
$user_id = $_SESSION['user_id'];

$customer = [
    'id' => null,
    'name' => '',
    'email' => '',
    'phone' => '',
    'address' => ''
];

$modal_title = "Add New Customer";
$form_action_url = BASE_URL . "php/customer_handler.php?action=add";

if ($action === 'edit' && $customer_id) {
    $pdo = get_db_connection();
    // Ensure the customer belongs to the current user
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = :customer_id AND created_by_user_id = :user_id");
    $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $customer_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($customer_data) {
        $customer = $customer_data;
        $modal_title = "Edit Customer: " . htmlspecialchars($customer['name']);
        $form_action_url = BASE_URL . "php/customer_handler.php?action=edit&customer_id=" . $customer['id'];
    } else {
        echo "<div class='alert alert-danger'>Customer not found or access denied.</div>";
        exit;
    }
}
?>

<script>
    // Update modal title if it's part of the loaded content and modal is already visible
    if (document.getElementById('customerModalLabel')) {
        document.getElementById('customerModalLabel').textContent = '<?php echo $modal_title; ?>';
    }
</script>

<form id="customerForm"
      hx-post="<?php echo $form_action_url; ?>"
      hx-target="#customerList"
      hx-swap="outerHTML"
      hx-indicator="#customerFormSpinner"
      hx-on::after-request="if(event.detail.successful) { bootstrap.Modal.getInstance(document.getElementById('customerModal')).hide(); document.getElementById('customerForm').reset(); }">
    <input type="hidden" name="customer_id" value="<?php echo htmlspecialchars($customer['id'] ?? ''); ?>">

    <div class="mb-3">
        <label for="customer_name" class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="customer_name" name="name" value="<?php echo htmlspecialchars($customer['name']); ?>" required>
    </div>
    <div class="mb-3">
        <label for="customer_email" class="form-label">Email</label>
        <input type="email" class="form-control" id="customer_email" name="email" value="<?php echo htmlspecialchars($customer['email'] ?? ''); ?>">
    </div>
    <div class="mb-3">
        <label for="customer_phone" class="form-label">Phone</label>
        <input type="tel" class="form-control" id="customer_phone" name="phone" value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>">
    </div>
    <div class="mb-3">
        <label for="customer_address" class="form-label">Address</label>
        <textarea class="form-control" id="customer_address" name="address" rows="3"><?php echo htmlspecialchars($customer['address'] ?? ''); ?></textarea>
    </div>

    <div class="d-flex justify-content-end align-items-center">
        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Close</button>
        <button type="submit" class="btn btn-primary">
            <?php echo ($action === 'edit' ? 'Save Changes' : 'Add Customer'); ?>
        </button>
        <div id="customerFormSpinner" class="htmx-indicator spinner-border spinner-border-sm ms-2" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
</form>
