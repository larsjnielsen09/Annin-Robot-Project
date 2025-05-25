<?php
require_once __DIR__ . '/../php/core/config.php';
require_once __DIR__ . '/../php/core/database.php';
require_once __DIR__ . '/../php/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo "<div class='alert alert-danger'>Authentication required.</div>";
    exit;
}

$action = $_GET['action'] ?? 'add'; // 'add' or 'edit'
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
$user_id = $_SESSION['user_id'];
$pdo = get_db_connection();

$project = [
    'id' => null,
    'name' => '',
    'description' => '',
    'status' => 'pending',
    'customer_id' => null
];
$project_statuses = ['pending', 'active', 'on hold', 'completed', 'cancelled'];


$modal_title = "Add New Project";
$form_action_url = BASE_URL . "php/project_handler.php?action=add";

if ($action === 'edit' && $project_id) {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = :project_id AND created_by_user_id = :user_id");
    $stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $project_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($project_data) {
        $project = $project_data;
        $modal_title = "Edit Project: " . htmlspecialchars($project['name']);
        $form_action_url = BASE_URL . "php/project_handler.php?action=edit&project_id=" . $project['id'];
    } else {
        echo "<div class='alert alert-danger'>Project not found or access denied.</div>";
        exit;
    }
}

// Fetch customers for the dropdown
$stmt_customers = $pdo->prepare("SELECT id, name FROM customers WHERE created_by_user_id = :user_id ORDER BY name ASC");
$stmt_customers->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt_customers->execute();
$customers = $stmt_customers->fetchAll(PDO::FETCH_ASSOC);
?>

<script>
    if (document.getElementById('projectModalLabel')) {
        document.getElementById('projectModalLabel').textContent = '<?php echo $modal_title; ?>';
    }
</script>

<form id="projectForm"
      hx-post="<?php echo $form_action_url; ?>"
      hx-target="#projectList"
      hx-swap="outerHTML"
      hx-indicator="#projectFormSpinner"
      hx-on::after-request="if(event.detail.successful) { bootstrap.Modal.getInstance(document.getElementById('projectModal')).hide(); document.getElementById('projectForm').reset(); }">
    <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project['id'] ?? ''); ?>">

    <div class="mb-3">
        <label for="project_name" class="form-label">Project Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="project_name" name="name" value="<?php echo htmlspecialchars($project['name']); ?>" required>
    </div>

    <div class="mb-3">
        <label for="customer_id" class="form-label">Customer (Optional)</label>
        <select class="form-select" id="customer_id" name="customer_id">
            <option value="">None</option>
            <?php foreach ($customers as $customer): ?>
                <option value="<?php echo $customer['id']; ?>" <?php echo ($project['customer_id'] == $customer['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($customer['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label for="project_description" class="form-label">Description</label>
        <textarea class="form-control" id="project_description" name="description" rows="3"><?php echo htmlspecialchars($project['description'] ?? ''); ?></textarea>
    </div>

    <div class="mb-3">
        <label for="project_status" class="form-label">Status</label>
        <select class="form-select" id="project_status" name="status">
            <?php foreach ($project_statuses as $status_option): ?>
                <option value="<?php echo $status_option; ?>" <?php echo ($project['status'] == $status_option) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(ucfirst($status_option)); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="d-flex justify-content-end align-items-center">
        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Close</button>
        <button type="submit" class="btn btn-primary">
            <?php echo ($action === 'edit' ? 'Save Changes' : 'Add Project'); ?>
        </button>
        <div id="projectFormSpinner" class="htmx-indicator spinner-border spinner-border-sm ms-2" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
</form>
