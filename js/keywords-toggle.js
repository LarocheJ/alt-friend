/**
 * Alt Friend - Keywords Toggle Module
 * Handles the keywords checkbox and input field toggle
 */

/**
 * Setup checkbox toggle for keywords input field
 */
function setupKeywordsToggle() {
    const checkbox = document.querySelector('#af-keywords-checkbox');
    const inputWrapper = document.querySelector('.af-keywords-input-wrapper');
    
    if (checkbox && inputWrapper) {
        checkbox.addEventListener('change', function() {
            inputWrapper.style.display = this.checked ? 'block' : 'none';
        });
    }
}

/**
 * Initialize keywords toggle functionality
 * Sets up the initial state and observes for dynamic content changes
 */
export function initKeywordsToggle() {
    // Setup on page load
    setupKeywordsToggle();
    
    // Re-setup when media modal opens (for dynamically loaded content)
    const observer = new MutationObserver(() => {
        setupKeywordsToggle();
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
}
