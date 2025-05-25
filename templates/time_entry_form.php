<?php
require_once __DIR__ . '/../php/core/config.php';
require_once __DIR__ . '/../php/core/database.php';
require_once __DIR__ . '/../php/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo "<div class='alert alert-danger'>Authentication required.</div>";
    exit;
}

$action = $_GET['action'] ?? 'add'; // 'add' or 'edit'
$entry_id = isset($_GET['entry_id']) ? (int)$_GET['entry_id'] : null;
$preselected_task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : null; // From timetracker.php or tasks.php link
$user_id = $_SESSION['user_id'];
$pdo = get_db_connection();

$entry = [
    'id' => null, 'task_id' => $preselected_task_id,
    'start_time' => date('Y-m-d\TH:i'), // Default to now
    'end_time' => null, 'hours_spent' => null, 'notes' => ''
];

$modal_title = "Add New Time Entry";
$form_action_url = BASE_URL . "php/time_entry_handler.php?action=add";
if ($preselected_task_id) {
    $form_action_url .= "&origin_task_id=" . $preselected_task_id;
}


if ($action === 'edit' && $entry_id) {
    $stmt = $pdo->prepare("SELECT * FROM time_entries WHERE id = :entry_id AND user_id = :user_id");
    $stmt->bindParam(':entry_id', $entry_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $entry_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($entry_data) {
        $entry = $entry_data;
        // Format datetime for datetime-local input
        if ($entry['start_time']) $entry['start_time'] = date('Y-m-d\TH:i', strtotime($entry['start_time']));
        if ($entry['end_time']) $entry['end_time'] = date('Y-m-d\TH:i', strtotime($entry['end_time']));
        $modal_title = "Edit Time Entry";
        $form_action_url = BASE_URL . "php/time_entry_handler.php?action=edit&entry_id=" . $entry['id'];
        if ($preselected_task_id) { // Persist filter context if any
             $form_action_url .= "&origin_task_id=" . $preselected_task_id;
        } elseif ($entry['task_id']) { // Or use the entry's task_id for context
             $form_action_url .= "&origin_task_id=" . $entry['task_id'];
        }
    } else {
        echo "<div class='alert alert-danger'>Time entry not found or access denied.</div>";
        exit;
    }
}

// Fetch tasks for dropdown (tasks user is involved with: created, assigned, or in created projects)
$stmt_tasks = $pdo->prepare("
    SELECT DISTINCT t.id, t.name, p.name as project_name
    FROM tasks t
    LEFT JOIN projects p ON t.project_id = p.id
    WHERE (p.created_by_user_id = :user_id OR t.assigned_to_user_id = :user_id_assigned OR t.created_by_user_id = :user_id_created)
    ORDER BY p.name, t.name
");
$stmt_tasks->execute([
    ':user_id' => $user_id,
    ':user_id_assigned' => $user_id,
    ':user_id_created' => $user_id
]);
$user_tasks = $stmt_tasks->fetchAll(PDO::FETCH_ASSOC);
?>

<script>
    if (document.getElementById('timeEntryModalLabel')) {
        document.getElementById('timeEntryModalLabel').textContent = '<?php echo $modal_title; ?>';
    }
    function calculateHours() {
        const startTimeStr = document.getElementById('start_time').value;
        const endTimeStr = document.getElementById('end_time').value;
        const hoursSpentInput = document.getElementById('hours_spent');

        if (startTimeStr && endTimeStr) {
            const start = new Date(startTimeStr);
            const end = new Date(endTimeStr);
            if (end > start) {
                const diffMillis = end - start;
                const diffHours = diffMillis / (1000 * 60 * 60);
                hoursSpentInput.value = diffHours.toFixed(2);
            } else {
                hoursSpentInput.value = ''; // Or some error indication
            }
        }
    }
    // Attach listeners after HTMX swaps content or use event delegation
    // For simplicity, direct attachment here, assuming the form is fully loaded.
    // More robust: htmx.onLoad(function(elt) { ... });
    setTimeout(function() { // Ensure elements exist
        const st = document.getElementById('start_time');
        const et = document.getElementById('end_time');
        if(st) st.addEventListener('change', calculateHours);
        if(et) et.addEventListener('change', calculateHours);
    }, 100);
</script>

<form id="timeEntryForm"
      hx-post="<?php echo $form_action_url; ?>"
      hx-target="#timeEntryList"
      hx-swap="innerHTML"
      hx-indicator="#timeEntryFormSpinner"
      hx-on::after-request="if(event.detail.successful) { bootstrap.Modal.getInstance(document.getElementById('timeEntryModal')).hide(); document.getElementById('timeEntryForm').reset(); }">
    <input type="hidden" name="entry_id" value="<?php echo htmlspecialchars($entry['id'] ?? ''); ?>">

    <div class="mb-3">
        <label for="task_id" class="form-label">Task <span class="text-danger">*</span></label>
        <select class="form-select" id="task_id" name="task_id" required>
            <option value="">Select Task</option>
            <?php foreach ($user_tasks as $task_opt): ?>
                <option value="<?php echo $task_opt['id']; ?>" <?php echo ($entry['task_id'] == $task_opt['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($task_opt['project_name'] ? $task_opt['project_name'].' - '.$task_opt['name'] : $task_opt['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
            <input type="datetime-local" class="form-control" id="start_time" name="start_time" value="<?php echo htmlspecialchars($entry['start_time']); ?>" required>
        </div>
        <div class="col-md-6 mb-3">
            <label for="end_time" class="form-label">End Time</label>
            <input type="datetime-local" class="form-control" id="end_time" name="end_time" value="<?php echo htmlspecialchars($entry['end_time'] ?? ''); ?>">
        </div>
    </div>

    <div class="mb-3">
        <label for="hours_spent" class="form-label">Hours Spent (auto-calculated if End Time is set, or manual)</label>
        <input type="number" step="0.01" class="form-control" id="hours_spent" name="hours_spent" value="<?php echo htmlspecialchars($entry['hours_spent'] ?? ''); ?>" placeholder="e.g., 1.5 for 1h 30m">
    </div>

    <div class="mb-3">
        <label for="notes" class="form-label">Notes</label>
        <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($entry['notes'] ?? ''); ?></textarea>
    </div>

    <div class="d-flex justify-content-end align-items-center">
        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Close</button>
        <button type="submit" class="btn btn-primary">
            <?php echo ($action === 'edit' ? 'Save Changes' : 'Add Entry'); ?>
        </button>
        <div id="timeEntryFormSpinner" class="htmx-indicator spinner-border spinner-border-sm ms-2" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
</form>
