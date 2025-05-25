// Custom JavaScript for Task & Time Manager

document.addEventListener('DOMContentLoaded', function () {
    console.log('Task & Time Manager JS Loaded');

    // Example: Initialize Bootstrap tooltips (if you decide to use them)
    // var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    // var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    //   return new bootstrap.Tooltip(tooltipTriggerEl)
    // })

    // Example: Handling HTMX events for toast notifications or other UI updates
    // This requires a toast library or custom toast implementation.
    // For example, if using Bootstrap Toasts:
    /*
    function showToast(message, type = 'info') {
        // Find or create a toast container
        let toastContainer = document.getElementById('toastPlacement');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toastPlacement';
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            toastContainer.style.zIndex = 1055; // Ensure it's above other elements
            document.body.appendChild(toastContainer);
        }

        const toastId = 'toast-' + Date.now();
        const toastHTML = `
            <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        const toastElement = new bootstrap.Toast(document.getElementById(toastId));
        toastElement.show();
        // Remove the toast from DOM after it's hidden
        document.getElementById(toastId).addEventListener('hidden.bs.toast', function () {
            this.remove();
        });
    }

    // Listen for custom HTMX events to show messages (e.g., from handlers)
    document.body.addEventListener('showMessage', function(event) {
        if (event.detail.value) { // Assuming message is in event.detail.value
            showToast(event.detail.value, 'success'); // Or determine type
        } else if (event.detail.message && event.detail.level) { // For HX-Trigger: {"showMessage": {"level": "success", "message": "..."}}
            showToast(event.detail.message, event.detail.level);
        }
    });
    document.body.addEventListener('taskUpdated', function(event) {
        showToast('Task updated successfully!', 'success');
    });
    document.body.addEventListener('projectUpdated', function(event) {
        showToast('Project updated successfully!', 'success');
    });
    document.body.addEventListener('customerUpdated', function(event) {
        showToast('Customer updated successfully!', 'success');
    });
     document.body.addEventListener('timeEntryUpdated', function(event) {
        showToast('Time entry updated successfully!', 'success');
    });
    document.body.addEventListener('taskDeleted', function(event) {
        showToast('Task deleted.', 'info');
    });
    // Add more listeners for other HX-Trigger events as needed
    */

    // Clear the #quickAddTaskResult div on the dashboard after a few seconds
    // if a success message was displayed there by HTMX from task_handler.php
    const quickAddTaskResult = document.getElementById('quickAddTaskResult');
    if (quickAddTaskResult) {
        // Use a MutationObserver to detect when content changes (i.e., HTMX adds a message)
        const observer = new MutationObserver(function(mutationsList, observer) {
            for(const mutation of mutationsList) {
                if (mutation.type === 'childList' && quickAddTaskResult.innerHTML.includes('alert-success')) {
                    // If a success message is found, wait a bit then clear it
                    setTimeout(function() {
                        quickAddTaskResult.innerHTML = '';
                    }, 5000); // Clear after 5 seconds
                }
            }
        });
        observer.observe(quickAddTaskResult, { childList: true });

        // Also allow clearing it via a custom event if the modal form from tasks.php was used
         quickAddTaskResult.addEventListener('clearResult', function() {
            quickAddTaskResult.innerHTML = '';
        });
    }


    // If you have modals loaded via HTMX, Bootstrap might need to be re-initialized for them,
    // or you ensure event delegation is used for event listeners within modal content.
    // HTMX typically handles Bootstrap's JS interactions well for modals if data-bs-toggle etc. are present in the HTML.

    // Global handler for HX-Trigger'd showMessage events (alternative to individual listeners)
    // This requires the toast function from above to be uncommented and working.
    /*
    htmx.on("showMessage", function(event) {
        if (event.detail && event.detail.message && event.detail.level) {
            showToast(event.detail.message, event.detail.level);
        } else if (event.detail && event.detail.value) { // fallback for simple string value
             showToast(event.detail.value, 'info');
        }
    });
    */
});
