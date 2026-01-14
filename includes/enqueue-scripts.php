<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue the JavaScript and CSS files
 * 
 * @param string $hook The current admin page hook
 * @return void
 */
add_action('admin_enqueue_scripts', 'af_enqueue_scripts');
function af_enqueue_scripts($hook) {
    // Only load on relevant admin pages
    $allowed_hooks = array('post.php', 'post-new.php', 'upload.php', 'settings_page_alt-friend');
    if (!in_array($hook, $allowed_hooks, true)) {
        return;
    }
    
    // Enqueue main script as ES6 module
    wp_enqueue_script(
        'af-script',
        ALT_FRIEND_PLUGIN_URL . 'js/main.js',
        array(), // No dependencies needed for modules
        ALT_FRIEND_VERSION,
        true
    );
    
    // Add type="module" attribute to the script tag
    add_filter('script_loader_tag', 'af_add_module_type', 10, 3);
    
    wp_enqueue_style(
        'af-style',
        ALT_FRIEND_PLUGIN_URL . 'css/main.css',
        array(),
        ALT_FRIEND_VERSION
    );

    // Localize script with AJAX URL and security nonce
    wp_localize_script('af-script', 'AltFriendData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('af_generate_alt_nonce'),
    ));
}

/**
 * Add type="module" attribute to the main script tag
 * 
 * @param string $tag The script tag
 * @param string $handle The script handle
 * @param string $src The script source URL
 * @return string Modified script tag
 */
function af_add_module_type($tag, $handle, $src) {
    if ('af-script' === $handle) {
        $tag = '<script type="module" src="' . esc_url($src) . '" id="' . esc_attr($handle) . '-js"></script>';
    }
    return $tag;
}