/**
 * Alt Friend - Bulk Processor Module
 * Handles bulk alt text generation for multiple images
 */

import { logMessage } from './utils.js';

let isBulkProcessing = false;
let shouldStopProcessing = false;
let currentImages = [];
let currentIndex = 0;
let stats = {
    success: 0,
    failed: 0,
    skipped: 0
};

/**
 * Update the progress bar and stats display
 */
function updateProgress() {
    const total = currentImages.length;
    const current = currentIndex;
    const percentage = total > 0 ? (current / total) * 100 : 0;

    document.getElementById('af-bulk-current').textContent = current;
    document.getElementById('af-bulk-total').textContent = total;
    document.getElementById('af-bulk-progress-bar').style.width = percentage + '%';
    document.getElementById('af-bulk-success').textContent = stats.success;
    document.getElementById('af-bulk-failed').textContent = stats.failed;
    document.getElementById('af-bulk-skipped').textContent = stats.skipped;
}

/**
 * Process a single image
 * @param {Object} image - The image object containing id and title
 * @returns {Promise<boolean>} True if successful, false otherwise
 */
async function processSingleImage(image) {
    const requestBody = new URLSearchParams({
        action: 'af_bulk_generate_single',
        nonce: window.AltFriendData.nonce,
        attachment_id: image.id
    });

    try {
        const response = await fetch(window.AltFriendData.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: requestBody
        });
        
        const data = await response.json();
        
        if (data.success) {
            stats.success++;
            logMessage(`Generated alt text for "${image.title || 'Untitled'}" (ID: ${image.id})`, 'success');
            return true;
        } else {
            stats.failed++;
            const errorMsg = data.data?.message || 'Unknown error';
            logMessage(`Failed to generate alt text for "${image.title || 'Untitled'}" (ID: ${image.id}): ${errorMsg}`, 'error');
            return false;
        }
    } catch (err) {
        stats.failed++;
        logMessage(`Network error for "${image.title || 'Untitled'}" (ID: ${image.id}): ${err.message}`, 'error');
        return false;
    }
}

/**
 * Process all images sequentially
 * @param {HTMLElement} bulkStartButton - The start button element
 * @param {HTMLElement} bulkStopButton - The stop button element
 */
async function processImages(bulkStartButton, bulkStopButton) {
    for (let i = 0; i < currentImages.length; i++) {
        if (shouldStopProcessing) {
            logMessage('Processing stopped by user.', 'info');
            break;
        }

        currentIndex = i + 1;
        updateProgress();
        
        const image = currentImages[i];
        await processSingleImage(image);
        
        // Small delay to avoid overwhelming the API
        await new Promise(resolve => setTimeout(resolve, 500));
    }

    // Processing complete
    isBulkProcessing = false;
    bulkStartButton.style.display = 'inline-block';
    bulkStopButton.style.display = 'none';
    
    if (!shouldStopProcessing) {
        document.getElementById('af-bulk-complete').style.display = 'block';
        document.getElementById('af-bulk-complete-total').textContent = currentImages.length;
        logMessage(`Bulk processing complete! Processed ${currentImages.length} images.`, 'success');
    }
}

/**
 * Fetch images without alt text from the server
 * @returns {Promise<Array>} Array of images without alt text
 */
async function fetchImagesWithoutAlt() {
    const requestBody = new URLSearchParams({
        action: 'af_get_images_without_alt',
        nonce: window.AltFriendData.nonce
    });

    const response = await fetch(window.AltFriendData.ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: requestBody
    });
    
    const data = await response.json();
    
    if (data.success && data.data.images) {
        return data.data.images;
    } else {
        throw new Error(data.data?.message || 'Unknown error');
    }
}

/**
 * Handle the start bulk processing button click
 * @param {HTMLElement} bulkStartButton - The start button element
 * @param {HTMLElement} bulkStopButton - The stop button element
 */
async function handleStartClick(bulkStartButton, bulkStopButton) {
    if (isBulkProcessing) return;

    // Reset state
    isBulkProcessing = true;
    shouldStopProcessing = false;
    currentIndex = 0;
    stats = { success: 0, failed: 0, skipped: 0 };
    
    // Update UI
    bulkStartButton.style.display = 'none';
    bulkStopButton.style.display = 'inline-block';
    document.getElementById('af-bulk-progress').style.display = 'block';
    document.getElementById('af-bulk-complete').style.display = 'none';
    document.getElementById('af-bulk-log').innerHTML = '';

    logMessage('Fetching images without alt text...', 'info');

    try {
        currentImages = await fetchImagesWithoutAlt();
        
        if (currentImages.length === 0) {
            logMessage('No images found without alt text. All images already have alt text!', 'success');
            isBulkProcessing = false;
            bulkStartButton.style.display = 'inline-block';
            bulkStopButton.style.display = 'none';
            return;
        }

        logMessage(`Found ${currentImages.length} images without alt text. Starting generation...`, 'info');
        updateProgress();
        await processImages(bulkStartButton, bulkStopButton);
    } catch (err) {
        logMessage('Error fetching images: ' + err.message, 'error');
        isBulkProcessing = false;
        bulkStartButton.style.display = 'inline-block';
        bulkStopButton.style.display = 'none';
    }
}

/**
 * Handle the stop bulk processing button click
 * @param {HTMLElement} bulkStopButton - The stop button element
 */
function handleStopClick(bulkStopButton) {
    shouldStopProcessing = true;
    bulkStopButton.disabled = true;
    bulkStopButton.textContent = 'Stopping...';
    logMessage('Stop requested. Will stop after current image completes.', 'info');
}

/**
 * Initialize bulk processor functionality
 * Only runs on the settings page
 */
export function initBulkProcessor() {
    const bulkStartButton = document.getElementById('af-bulk-start');
    const bulkStopButton = document.getElementById('af-bulk-stop');
    
    if (!bulkStartButton) {
        return; // Not on settings page
    }

    bulkStartButton.addEventListener('click', () => {
        handleStartClick(bulkStartButton, bulkStopButton);
    });

    bulkStopButton.addEventListener('click', () => {
        handleStopClick(bulkStopButton);
    });
}
