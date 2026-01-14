<?php 
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add Alt Friend controls to media attachment edit screen
 * 
 * @param array   $form_fields Array of attachment form fields
 * @param WP_Post $post        The attachment post object
 * @return array Modified form fields
 */
add_filter('attachment_fields_to_edit', 'af_add_alt_friend_button', 10, 2);
function af_add_alt_friend_button($form_fields, $post) {
    // If alt text exists, change button text to "Re-generate Alt Text"
    $alt_text = get_post_meta($post->ID, '_wp_attachment_image_alt', true);
    $button_text = !empty($alt_text) ? 'Re-generate Alt Text' : 'Generate Alt Text';

    // Get saved keywords
    $saved_keywords = get_post_meta($post->ID, '_af_keywords', true);

    // Add a single combined field that will be positioned after the alt text
    $form_fields['af_controls'] = array(
        'label' => '',
        'input' => 'html',
        'html'  => '
            <div class="af-controls-wrapper" data-move-after-alt="true"> 
                <div class="af-controls-header"><h3>Alt Friend</h3></div>   
                <div class="af-controls-body">         
                    <button type="button" class="af-button af-generate-alt-button" id="af-generate-alt">' . $button_text . '</button>
                    <div class="af-keywords-section">
                        <label>
                            <input type="checkbox" id="af-keywords-checkbox" class="af-keywords-checkbox"' . (!empty($saved_keywords) ? ' checked' : '') . '>
                            Add keywords
                        </label>
                        <div class="af-keywords-input-wrapper" style="' . (empty($saved_keywords) ? 'display:none;' : '') . 'margin-top:8px;">
                            <input type="text" 
                                name="attachments[' . $post->ID . '][af_keywords]" 
                                id="af-keywords-input" 
                                class="af-keywords-input" 
                                value="' . esc_attr($saved_keywords) . '" 
                                placeholder="e.g., product, blue, modern" 
                                style="width:100%;">
                            <p class="description">Enter comma-separated keywords to include in the alt text generation</p>
                        </div>
                    </div>
                    <small class="af-controls-notes">Use AI to generate an alternative text for the image. Optionally add specific keywords to help with SEO.</small>
                </div>   
            </div>',
    );

    return $form_fields;  
}

/**
 * Save keywords when attachment is updated
 * 
 * @param array $post       An array of post data
 * @param array $attachment An array of attachment metadata
 * @return array Modified post data
 */
add_filter('attachment_fields_to_save', 'af_save_keywords', 10, 2);
function af_save_keywords($post, $attachment) {
    if (isset($attachment['af_keywords'])) {
        update_post_meta($post['ID'], '_af_keywords', sanitize_text_field($attachment['af_keywords']));
    }
    return $post;
}

/**
 * Automatically generate alt text when image is uploaded
 * 
 * @param int $attachment_id The attachment post ID
 * @return void
 */
add_action('add_attachment', 'af_auto_generate_on_upload');
function af_auto_generate_on_upload($attachment_id) {
    // Check if auto-generation is enabled
    $auto_generate = get_option('af_auto_generate_on_upload', false);
    if (!$auto_generate) {
        return;
    }

    // Check if attachment is an image
    if (!wp_attachment_is_image($attachment_id)) {
        return;
    }

    // Check if alt text already exists
    $existing_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
    if (!empty($existing_alt)) {
        return;
    }

    // Get the image URL
    $image_url = wp_get_attachment_url($attachment_id);
    if (!$image_url) {
        return;
    }

    // Get saved keywords (if any)
    $keywords = get_post_meta($attachment_id, '_af_keywords', true);

    // Generate alt text using the core function
    $result = af_generate_alt_text_from_api($image_url, $keywords);

    // Save the alt text if successful
    if ($result['success'] && !empty($result['alt_text'])) {
        update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($result['alt_text']));
        
        // Log the auto-generation for debugging (optional)
        error_log('Alt Friend: Auto-generated alt text for attachment #' . $attachment_id);
    }
}