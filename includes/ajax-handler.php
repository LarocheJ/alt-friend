<?php 
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resize image for API to reduce token usage
 * 
 * @param string $image_url URL of the image to resize
 * @param int    $max_width Maximum width in pixels
 * @param int    $max_height Maximum height in pixels
 * @param int    $quality JPEG quality (1-100)
 * @return string|false Base64 encoded image data URI or false on failure
 */
function resize_image_for_api($image_url, $max_width = 256, $max_height = 256, $quality = 50) {
    // Check if it's a local URL
    $is_local = (strpos($image_url, '.local') !== false);
    
    if ($is_local) {
        // Get the file path from URL
        $image_path = str_replace(site_url(), ABSPATH, $image_url);
    } else {
        // Download remote image temporarily
        $tmp = download_url($image_url);
        if (is_wp_error($tmp)) {
            return false;
        }
        $image_path = $tmp;
    }

    // Get image info
    $image_info = getimagesize($image_path);
    if (!$image_info) {
        if (!$is_local && isset($tmp)) {
            wp_delete_file($tmp);
        }
        return false;
    }

    list($orig_width, $orig_height, $image_type) = $image_info;

    // Calculate new dimensions while maintaining aspect ratio
    $ratio = min($max_width / $orig_width, $max_height / $orig_height);
    
    // Only resize if image is larger than max dimensions
    if ($ratio >= 1) {
        // Image is already small enough, just encode it
        $image_data = base64_encode(file_get_contents($image_path));
        $mime_type = image_type_to_mime_type($image_type);
        if (!$is_local && isset($tmp)) {
            wp_delete_file($tmp);
        }
        return "data:{$mime_type};base64,{$image_data}";
    }

    $new_width = round($orig_width * $ratio);
    $new_height = round($orig_height * $ratio);

    // Create source image
    switch ($image_type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($image_path);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($image_path);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($image_path);
            break;
        case IMAGETYPE_WEBP:
            $source = imagecreatefromwebp($image_path);
            break;
        default:
            if (!$is_local && isset($tmp)) {
                wp_delete_file($tmp);
            }
            return false;
    }

    if (!$source) {
        if (!$is_local && isset($tmp)) {
            wp_delete_file($tmp);
        }
        return false;
    }

    // Create resized image
    $resized = imagecreatetruecolor($new_width, $new_height);
    
    // Preserve transparency for PNG/GIF
    if ($image_type == IMAGETYPE_PNG || $image_type == IMAGETYPE_GIF) {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
        imagefilledrectangle($resized, 0, 0, $new_width, $new_height, $transparent);
    }

    imagecopyresampled($resized, $source, 0, 0, 0, 0, $new_width, $new_height, $orig_width, $orig_height);

    // Convert to base64 JPEG
    ob_start();
    imagejpeg($resized, null, $quality);
    $image_data = ob_get_clean();
    
    imagedestroy($source);
    imagedestroy($resized);
    
    // Clean up temporary file
    if (!$is_local && isset($tmp)) {
        wp_delete_file($tmp);
    }

    return 'data:image/jpeg;base64,' . base64_encode($image_data);
}

/**
 * Core function to generate alt text via OpenAI API
 * 
 * @param string $image_url URL of the image to analyze
 * @param string $keywords Optional comma-separated keywords to include
 * @return array Array with 'success', 'message', 'alt_text', 'usage', and 'error_type' keys
 */
function af_generate_alt_text_from_api($image_url, $keywords = '') {
    $api_key = get_option('openai_api_key');

    if (!$api_key) {
        return [
            'success' => false, 
            'message' => 'OpenAI API key is missing. Please add it in Settings > Alt Friend.',
            'error_type' => 'missing_api_key'
        ];
    }

    // Resize image to reduce token usage
    $resized_image_data = resize_image_for_api($image_url);
    
    if (!$resized_image_data) {
        return [
            'success' => false, 
            'message' => 'Failed to process the image. The image format may be unsupported or corrupted.',
            'error_type' => 'image_processing_error'
        ];
    }

    // Build the prompt text based on whether keywords are provided
    $prompt_text = 'Generate a concise alt text for this image. Keep it under 200 characters and focus on the most important visual elements';
    
    if (!empty($keywords)) {
        $prompt_text .= '. Make sure to incorporate these specific keywords if they are relevant to the image: ' . $keywords;
    }
    
    $prompt_text .= ':';

    // Build request to OpenAI API
    $body = [
        'model' => 'gpt-4.1-mini',
        'messages' => [[
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $prompt_text],
                [
                    'type' => 'image_url', 
                    'image_url' => [
                        'url' => $resized_image_data,
                        'detail' => 'low' // Use low detail to reduce tokens
                    ]
                ],
            ],
        ]]
    ];

    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode($body),
        'timeout' => 60,
    ]);

    if (is_wp_error($response)) {
        return [
            'success' => false, 
            'message' => 'Network error: ' . $response->get_error_message(),
            'error_type' => 'network_error'
        ];
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body, true);

    // Handle HTTP error codes
    if ($http_code !== 200) {
        $error_message = 'API request failed';
        $error_type = 'api_error';

        if (isset($data['error'])) {
            $api_error = $data['error'];
            
            // Handle specific OpenAI error types
            if (isset($api_error['type'])) {
                switch ($api_error['type']) {
                    case 'invalid_request_error':
                        $error_message = 'Invalid request: ' . ($api_error['message'] ?? 'Please check your configuration.');
                        $error_type = 'invalid_request';
                        break;
                    case 'authentication_error':
                        $error_message = 'Invalid API key. Please check your OpenAI API key in settings.';
                        $error_type = 'invalid_api_key';
                        break;
                    case 'permission_error':
                        $error_message = 'Permission denied. Your API key may not have access to this model.';
                        $error_type = 'permission_error';
                        break;
                    case 'rate_limit_error':
                        $error_message = 'Rate limit exceeded. Please try again in a few moments.';
                        $error_type = 'rate_limit';
                        break;
                    case 'insufficient_quota':
                        $error_message = 'Insufficient quota. Please check your OpenAI account billing.';
                        $error_type = 'quota_exceeded';
                        break;
                    default:
                        $error_message = $api_error['message'] ?? 'Unknown API error occurred.';
                        break;
                }
            } else {
                $error_message = $api_error['message'] ?? $error_message;
            }
        } else if ($http_code === 401) {
            $error_message = 'Authentication failed. Please check your API key.';
            $error_type = 'invalid_api_key';
        } else if ($http_code === 429) {
            $error_message = 'Rate limit exceeded. Please try again later.';
            $error_type = 'rate_limit';
        } else if ($http_code >= 500) {
            $error_message = 'OpenAI service error. Please try again later.';
            $error_type = 'server_error';
        }

        return [
            'success' => false, 
            'message' => $error_message,
            'error_type' => $error_type
        ];
    }

    // Check if response has expected data structure
    if (!isset($data['choices']) || !is_array($data['choices']) || empty($data['choices'])) {
        return [
            'success' => false, 
            'message' => 'Invalid API response: No choices returned.',
            'error_type' => 'invalid_response'
        ];
    }

    $alt_text = $data['choices'][0]['message']['content'] ?? '';
    $usage = $data['usage'] ?? null;
    
    if (empty($alt_text)) {
        return [
            'success' => false, 
            'message' => 'No alt text was generated. The API returned an empty response.',
            'error_type' => 'empty_response'
        ];
    }

    return [
        'success' => true,
        'alt_text' => $alt_text,
        'usage' => $usage
    ];
}

/**
 * AJAX handler to generate alt text using OpenAI API
 * 
 * @return void Sends JSON response
 */
add_action('wp_ajax_ai_generate_alt_text', 'ai_generate_alt_text');
function ai_generate_alt_text() {
    // Verify nonce for security
    if (!check_ajax_referer('af_generate_alt_nonce', 'nonce', false)) {
        wp_send_json_error(array(
            'message' => 'Security check failed. Please refresh the page and try again.',
            'error_type' => 'invalid_nonce'
        ));
    }

    // Check user capabilities
    if (!current_user_can('upload_files')) {
        wp_send_json_error(array(
            'message' => 'You do not have permission to perform this action.',
            'error_type' => 'insufficient_permissions'
        ));
    }

    // Sanitize and validate input
    $image_url = isset($_POST['image_url']) ? esc_url_raw($_POST['image_url']) : '';
    $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
    $keywords = isset($_POST['keywords']) ? sanitize_text_field($_POST['keywords']) : '';

    // Validate image URL
    if (empty($image_url)) {
        wp_send_json_error(array(
            'message' => 'Image URL is missing.',
            'error_type' => 'missing_image_url'
        ));
    }
    
    // Validate attachment ID
    if (empty($attachment_id)) {
        wp_send_json_error(array(
            'message' => 'Attachment ID is missing.',
            'error_type' => 'missing_attachment_id'
        ));
    }

    // Verify attachment exists and is an image
    if (!wp_attachment_is_image($attachment_id)) {
        wp_send_json_error(array(
            'message' => 'Invalid attachment. Only images are supported.',
            'error_type' => 'invalid_attachment'
        ));
    }

    // Use the core function to generate alt text
    $result = af_generate_alt_text_from_api($image_url, $keywords);

    if (!$result['success']) {
        wp_send_json_error(array(
            'message' => $result['message'],
            'error_type' => $result['error_type'] ?? 'unknown_error'
        ));
    }
    
    // Sanitize alt text before saving
    $alt_text = sanitize_text_field($result['alt_text']);
    
    // Save the alt text to the database
    update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);

    wp_send_json_success(array(
        'alt_text' => $alt_text,
        'usage' => $result['usage'] ?? null
    ));
}
/**
 * AJAX handler to get images without alt text for bulk processing
 * 
 * @return void Sends JSON response
 */
add_action('wp_ajax_af_get_images_without_alt', 'af_get_images_without_alt');
function af_get_images_without_alt() {
    // Verify nonce for security
    if (!check_ajax_referer('af_generate_alt_nonce', 'nonce', false)) {
        wp_send_json_error(array(
            'message' => 'Security check failed. Please refresh the page and try again.',
            'error_type' => 'invalid_nonce'
        ));
    }

    // Check user capabilities
    if (!current_user_can('upload_files')) {
        wp_send_json_error(array(
            'message' => 'You do not have permission to perform this action.',
            'error_type' => 'insufficient_permissions'
        ));
    }

    // Query for images without alt text
    $args = array(
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'post_status' => 'inherit',
        'posts_per_page' => -1,
        'meta_query' => array(
            'relation' => 'OR',
            array(
                'key' => '_wp_attachment_image_alt',
                'compare' => 'NOT EXISTS'
            ),
            array(
                'key' => '_wp_attachment_image_alt',
                'value' => '',
                'compare' => '='
            )
        )
    );

    $query = new WP_Query($args);
    $images = array();

    if ($query->have_posts()) {
        foreach ($query->posts as $post) {
            $image_url = wp_get_attachment_url($post->ID);
            if ($image_url) {
                $images[] = array(
                    'id' => $post->ID,
                    'url' => $image_url,
                    'title' => get_the_title($post->ID)
                );
            }
        }
    }

    wp_reset_postdata();

    wp_send_json_success(array(
        'images' => $images,
        'total' => count($images)
    ));
}

/**
 * AJAX handler to generate alt text for a single image in bulk processing
 * 
 * @return void Sends JSON response
 */
add_action('wp_ajax_af_bulk_generate_single', 'af_bulk_generate_single');
function af_bulk_generate_single() {
    // Verify nonce for security
    if (!check_ajax_referer('af_generate_alt_nonce', 'nonce', false)) {
        wp_send_json_error(array(
            'message' => 'Security check failed. Please refresh the page and try again.',
            'error_type' => 'invalid_nonce'
        ));
    }

    // Check user capabilities
    if (!current_user_can('upload_files')) {
        wp_send_json_error(array(
            'message' => 'You do not have permission to perform this action.',
            'error_type' => 'insufficient_permissions'
        ));
    }

    // Sanitize and validate input
    $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;

    // Validate attachment ID
    if (empty($attachment_id)) {
        wp_send_json_error(array(
            'message' => 'Attachment ID is missing.',
            'error_type' => 'missing_attachment_id'
        ));
    }

    // Verify attachment exists and is an image
    if (!wp_attachment_is_image($attachment_id)) {
        wp_send_json_error(array(
            'message' => 'Invalid attachment. Only images are supported.',
            'error_type' => 'invalid_attachment'
        ));
    }

    // Get the image URL
    $image_url = wp_get_attachment_url($attachment_id);
    if (!$image_url) {
        wp_send_json_error(array(
            'message' => 'Could not retrieve image URL.',
            'error_type' => 'missing_image_url'
        ));
    }

    // Get saved keywords (if any)
    $keywords = get_post_meta($attachment_id, '_af_keywords', true);

    // Use the core function to generate alt text
    $result = af_generate_alt_text_from_api($image_url, $keywords);

    if (!$result['success']) {
        wp_send_json_error(array(
            'message' => $result['message'],
            'error_type' => $result['error_type'] ?? 'unknown_error'
        ));
    }

    // Sanitize alt text before saving
    $alt_text = sanitize_text_field($result['alt_text']);
    
    // Save the alt text to the database
    update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);

    wp_send_json_success(array(
        'alt_text' => $alt_text,
        'attachment_id' => $attachment_id,
        'usage' => $result['usage'] ?? null
    ));
}
