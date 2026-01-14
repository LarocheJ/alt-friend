/**
 * Alt Friend - Alt Text Generator Module
 * Handles single image alt text generation
 */

import { getAttachmentId, handleError } from './utils.js';

let buttonText = 'Generate Alt Text';

/**
 * Update button text based on whether alt text exists
 */
function updateButtonText() {
    const altTextField = document.querySelector('.alt-text textarea');
    const button = document.querySelector('#af-generate-alt');
    
    if (altTextField && button && !button.disabled) {
        if (altTextField.value.trim() !== '') {
            buttonText = 'Re-generate Alt Text';
            button.textContent = 'Re-generate Alt Text';
        } else {
            buttonText = 'Generate Alt Text';
            button.textContent = 'Generate Alt Text';
        }
    }
}

/**
 * Get keywords from the input field if checkbox is checked
 * @returns {string} The keywords string or empty string
 */
function getKeywords() {
    const keywordsCheckbox = document.querySelector('#af-keywords-checkbox');
    const keywordsInput = document.querySelector('#af-keywords-input');
    return (keywordsCheckbox?.checked && keywordsInput?.value) ? keywordsInput.value.trim() : '';
}

/**
 * Handle the generate alt text button click
 * @param {HTMLElement} button - The button element that was clicked
 */
async function handleButtonClick(button) {
    const altTextField = document.querySelector('.alt-text textarea');
    const image = document.querySelector('.attachment-details .thumbnail img');
    const imageUrl = image?.src || '';
    
    // Validate required elements
    if (!altTextField) {
        console.error('Alt text field not found');
        return;
    }
    
    if (!imageUrl) {
        console.error('No image URL found');
        return;
    }
    
    const keywords = getKeywords();
    const attachmentId = getAttachmentId();

    if (!attachmentId) {
        console.error('No attachment ID found');
        alert('Error: Could not determine attachment ID. Please refresh the page and try again.');
        return;
    }

    // Disable button and show loading state
    button.disabled = true;
    button.textContent = 'Generating...';

    // Build AJAX request
    const requestBody = new URLSearchParams({
        action: 'ai_generate_alt_text',
        nonce: window.AltFriendData.nonce,
        image_url: imageUrl,
        attachment_id: attachmentId
    });
    
    // Add keywords if provided
    if (keywords) {
        requestBody.append('keywords', keywords);
    }

    try {
        // Send AJAX request
        const response = await fetch(window.AltFriendData.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: requestBody
        });
        
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        const data = await response.json();
        
        if (data.success && data.data.alt_text) {
            // Update alt text field with generated text
            altTextField.value = data.data.alt_text.trim();
            // Trigger change event so WordPress knows the field has been updated
            altTextField.dispatchEvent(new Event('change', { bubbles: true }));
            // Update button text
            updateButtonText();
        } else {
            // Handle error response
            const errorMessage = data.data?.message || 'An unknown error occurred while generating alt text.';
            const errorType = data.data?.error_type || 'unknown_error';
            handleError(errorMessage, errorType);
        }
    } catch (err) {
        console.error('Network Error:', err);
        alert('Network Error: Failed to connect to the server. Please check your internet connection and try again.');
    } finally {
        // Re-enable button
        button.disabled = false;
        button.textContent = buttonText;
    }
}

/**
 * Initialize alt text generator functionality
 * Sets up button event listeners and text updates
 */
export function initAltTextGenerator() {
    // Initial button text setup
    updateButtonText();
    
    // Listen for changes to alt text field
    document.addEventListener('blur', (e) => {
        if (e.target && e.target.matches('.alt-text textarea')) {
            updateButtonText();
        }
    }, true);
    
    document.addEventListener('input', (e) => {
        if (e.target && e.target.matches('.alt-text textarea')) {
            updateButtonText();
        }
    });

    // Attach event listener to static button (Edit Media page)
    const staticButton = document.querySelector('#af-generate-alt');
    if (staticButton) {
        staticButton.addEventListener('click', () => handleButtonClick(staticButton));
    }

    // Attach event listener to dynamic button (Media Modal)
    document.addEventListener('click', (e) => {
        if (e.target && e.target.id === 'af-generate-alt') {
            handleButtonClick(e.target);
        }
    });
}
