<?php
$page_title = "Manage Customers";
require_once __DIR__ . '/php/auth/auth_check.php';
require_once __DIR__ . '/php/includes/header.php';
require_once __DIR__ . '/php/core/database.php';

$user_id = $_SESSION['user_id'];
$pdo = get_db_connection();

// Fetch customers created by the current user
// For simplicity, we are listing customers created by the user.
// In a multi-user company scenario, you might have different permission logics.
$stmt = $pdo->prepare("SELECT id, name, email, phone FROM customers WHERE created_by_user_id = :user_id ORDER BY name ASC");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Customer Management</h1>
    <button class="btn btn-primary"
            hx-get="<?php echo BASE_URL; ?>templates/customer_form.php?action=add"
            hx-target="#customerFormContainer"
            hx-swap="innerHTML"
            data-bs-toggle="modal"
            data-bs-target="#customerModal">
        Add New Customer
    </button>
</div>

<div id="customerList">
    <?php if (count($customers) > 0): ?>
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($customers as $customer): ?>
            <tr id="customer-row-<?php echo $customer['id']; ?>">
                <td><?php echo htmlspecialchars($customer['name']); ?></td>
                <td><?php echo htmlspecialchars($customer['email'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($customer['phone'] ?? '-'); ?></td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-secondary me-1"
                            hx-get="<?php echo BASE_URL; ?>templates/customer_form.php?action=edit&customer_id=<?php echo $customer['id']; ?>"
                            hx-target="#customerFormContainer"
                            hx-swap="innerHTML"
                            data-bs-toggle="modal"
                            data-bs-target="#customerModal">
                        Edit
                    </button>
                    <button class="btn btn-sm btn-outline-danger"
                            hx-confirm="Are you sure you want to delete this customer and all related projects/tasks?"
                            hx-post="<?php echo BASE_URL; ?>php/customer_handler.php?action=delete&customer_id=<?php echo $customer['id']; ?>"
                            hx-target="#customer-row-<?php echo $customer['id']; ?>"
                            hx-swap="outerHTML">
                        Delete
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="alert alert-info">No customers found. <a href="#" data-bs-toggle="modal" data-bs-target="#customerModal" hx-get="<?php echo BASE_URL; ?>templates/customer_form.php?action=add" hx-target="#customerFormContainer" hx-swap="innerHTML">Add one now!</a></div>
    <?php endif; ?>
</div>

<!-- Modal for Add/Edit Customer -->
<div class="modal fade" id="customerModal" tabindex="-1" aria-labelledby="customerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customerModalLabel">Customer Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="customerFormContainer">
                <!-- HTMX will load form here -->
                <p>Loading form...</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/php/includes/footer.php'; ?>
