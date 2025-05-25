<?php
$page_title = "Manage Projects";
require_once __DIR__ . '/php/auth/auth_check.php';
require_once __DIR__ . '/php/includes/header.php';
require_once __DIR__ . '/php/core/database.php';

$user_id = $_SESSION['user_id'];
$pdo = get_db_connection();

// Fetch projects created by or assigned in a way that the current user can see them.
// For this example, we'll fetch projects where created_by_user_id matches.
// We also join with customers to display customer name.
$stmt = $pdo->prepare("
    SELECT p.id, p.name, p.description, p.status, p.created_at, c.name as customer_name
    FROM projects p
    LEFT JOIN customers c ON p.customer_id = c.id
    WHERE p.created_by_user_id = :user_id
    ORDER BY p.created_at DESC
");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Project Management</h1>
    <button class="btn btn-primary"
            hx-get="<?php echo BASE_URL; ?>templates/project_form.php?action=add"
            hx-target="#projectFormContainer"
            hx-swap="innerHTML"
            data-bs-toggle="modal"
            data-bs-target="#projectModal">
        Add New Project
    </button>
</div>

<div id="projectList">
    <?php if (count($projects) > 0): ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Name</th>
                <th>Customer</th>
                <th>Status</th>
                <th>Created At</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($projects as $project): ?>
            <tr id="project-row-<?php echo $project['id']; ?>">
                <td><?php echo htmlspecialchars($project['name']); ?></td>
                <td><?php echo htmlspecialchars($project['customer_name'] ?? 'N/A'); ?></td>
                <td><span class="badge bg-secondary"><?php echo htmlspecialchars(ucfirst($project['status'])); ?></span></td>
                <td><?php echo date("Y-m-d", strtotime($project['created_at'])); ?></td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-secondary me-1"
                            hx-get="<?php echo BASE_URL; ?>templates/project_form.php?action=edit&project_id=<?php echo $project['id']; ?>"
                            hx-target="#projectFormContainer"
                            hx-swap="innerHTML"
                            data-bs-toggle="modal"
                            data-bs-target="#projectModal">
                        Edit
                    </button>
                    <button class="btn btn-sm btn-outline-danger"
                            hx-confirm="Are you sure you want to delete this project and all related tasks?"
                            hx-post="<?php echo BASE_URL; ?>php/project_handler.php?action=delete&project_id=<?php echo $project['id']; ?>"
                            hx-target="#project-row-<?php echo $project['id']; ?>"
                            hx-swap="outerHTML">
                        Delete
                    </button>
                    <a href="<?php echo BASE_URL; ?>tasks.php?project_id=<?php echo $project['id']; ?>" class="btn btn-sm btn-outline-info ms-1">View Tasks</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php else: ?>
    <div class="alert alert-info">No projects found. <a href="#" data-bs-toggle="modal" data-bs-target="#projectModal" hx-get="<?php echo BASE_URL; ?>templates/project_form.php?action=add" hx-target="#projectFormContainer" hx-swap="innerHTML">Add one now!</a></div>
    <?php endif; ?>
</div>

<!-- Modal for Add/Edit Project -->
<div class="modal fade" id="projectModal" tabindex="-1" aria-labelledby="projectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="projectModalLabel">Project Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="projectFormContainer">
                <!-- HTMX will load form here -->
                <p>Loading form...</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/php/includes/footer.php'; ?>
