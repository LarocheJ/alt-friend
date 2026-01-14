/**
 * Alt Friend - Main Entry Point
 * Initializes all modules for alt text generation via AI
 */

import { initKeywordsToggle } from './keywords-toggle.js';
import { initAltTextGenerator } from './alt-text-generator.js';
import { initBulkProcessor } from './bulk-processor.js';

/**
 * Initialize the plugin when DOM is ready
 */
document.addEventListener('DOMContentLoaded', () => {
    initKeywordsToggle();
    initAltTextGenerator();
    initBulkProcessor();
});
