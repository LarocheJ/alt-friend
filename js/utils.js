/**
 * Alt Friend - Utility Functions
 * Shared utility functions used across modules
 */

/**
 * Log a message to the bulk processing log
 * @param {string} message - The message to log
 * @param {string} type - The type of message (info, success, error, skip)
 */
export function logMessage(message, type = 'info') {
    const logDiv = document.getElementById('af-bulk-log');
    if (!logDiv) return;

    const timestamp = new Date().toLocaleTimeString();
    const logEntry = document.createElement('div');
    logEntry.style.marginBottom = '5px';
    logEntry.style.padding = '3px';
    
    const icons = {
        info: 'ℹ️',
        success: '✓',
        error: '✗',
        skip: '⊘'
    };
    
    const colors = {
        info: '#333',
        success: '#46b450',
        error: '#dc3232',
        skip: '#999'
    };
    
    const icon = icons[type] || icons.info;
    const color = colors[type] || colors.info;
    
    logEntry.innerHTML = `<span style="color: ${color}; font-weight: bold;">${icon}</span> <span style="color: #999; font-size: 0.9em;">[${timestamp}]</span> ${message}`;
    logDiv.appendChild(logEntry);
    logDiv.scrollTop = logDiv.scrollHeight;
}

/**
 * Get the attachment ID from various sources
 * @returns {string|null} The attachment ID or null if not found
 */
export function getAttachmentId() {
    // Try to get from input field
    const attachmentIdInput = document.querySelector('input[name="id"]');
    if (attachmentIdInput) {
        return attachmentIdInput.value;
    }
    
    // Try to get from URL
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('item');
}

/**
 * Display an error message to the user
 * @param {string} errorMessage - The error message to display
 * @param {string} errorType - The type of error
 */
export function handleError(errorMessage, errorType) {
    console.error('Alt Friend Error:', errorMessage, 'Type:', errorType);
    
    const criticalErrors = [
        'missing_api_key',
        'invalid_api_key',
        'permission_error',
        'quota_exceeded',
        'invalid_nonce',
        'insufficient_permissions'
    ];
    
    if (criticalErrors.includes(errorType)) {
        alert('Error: ' + errorMessage);
    } else {
        console.warn('Non-critical error:', errorMessage);
    }
}
