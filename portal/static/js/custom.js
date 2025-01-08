// Custom JavaScript for PHPAdminLTE portal
document.addEventListener('DOMContentLoaded', function() {
    // Initialize any custom functionality here
    console.log('Custom JavaScript loaded successfully');
});

// Global error handler
window.onerror = function(msg, url, line, col, error) {
    // Send error to local logging endpoint
    fetch('/portal/includes/logging/log_error.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            type: 'CLIENT_ERROR',
            message: msg,
            url: url,
            line: line,
            column: col
        })
    }).catch(function(error) {
        console.error('Failed to log error:', error);
    });
    
    return false; // Let default handler run
};

// AJAX error handler for jQuery
$(document).ajaxError(function(event, jqXHR, settings, error) {
    fetch('/portal/includes/logging/log_error.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            type: 'AJAX_ERROR',
            message: error || jqXHR.statusText,
            url: settings.url,
            line: 'Status: ' + jqXHR.status,
            column: ''
        })
    }).catch(function(error) {
        console.error('Failed to log AJAX error:', error);
    });
});
