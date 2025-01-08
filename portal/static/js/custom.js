// Custom JavaScript for PHPAdminLTE portal
document.addEventListener('DOMContentLoaded', function() {
    // Initialize any custom functionality here
    console.log('Custom JavaScript loaded successfully');
    
    // Initialize error queue
    window.errorQueue = [];
    // Start processing queue
    processErrorQueue();
});

// Error logging function with retry logic
async function logError(data, retries = 3, backoff = 1000) {
    const logEndpoint = '/portal/api/log.php';
    
    try {
        const response = await fetch(logEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data)
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return true;
    } catch (error) {
        if (retries > 0) {
            // Wait using exponential backoff
            await new Promise(resolve => setTimeout(resolve, backoff));
            // Retry with one less retry and double the backoff
            return logError(data, retries - 1, backoff * 2);
        }
        
        // Add to queue if all retries failed
        window.errorQueue.push(data);
        console.error('Failed to log error after retries:', error);
        return false;
    }
}

// Process error queue periodically
function processErrorQueue() {
    setInterval(async () => {
        if (window.errorQueue.length > 0) {
            const error = window.errorQueue[0];
            if (await logError(error)) {
                window.errorQueue.shift(); // Remove successfully logged error
            }
        }
    }, 5000); // Try every 5 seconds
}

// Global error handler
window.onerror = function(msg, url, line, col, error) {
    logError({
        type: 'CLIENT_ERROR',
        message: msg,
        url: url,
        line: line,
        column: col,
        stack: error?.stack || ''
    });
    
    return false; // Let default handler run
};

// AJAX error handler for jQuery
$(document).ajaxError(function(event, jqXHR, settings, error) {
    logError({
        type: 'AJAX_ERROR',
        message: error || jqXHR.statusText,
        url: settings.url,
        line: 'Status: ' + jqXHR.status,
        column: '',
        response: jqXHR.responseText || ''
    });
});

// Unhandled promise rejection handler
window.addEventListener('unhandledrejection', function(event) {
    logError({
        type: 'PROMISE_ERROR',
        message: event.reason?.message || 'Unhandled Promise Rejection',
        url: window.location.href,
        line: event.reason?.line || '',
        column: event.reason?.column || '',
        stack: event.reason?.stack || ''
    });
});
