<?php
/**
 * Plugin Name: Alt Friend
 * Description: Leverage OpenAI's GPT Vision API to automatically create alt texts for images uploaded to the media library.
 * Version: 0.1.1
 * Author: Jimmy Laroche
 * 
 */

/** Exit if accessed directly */
if (!defined('ABSPATH')) exit;

/** Define plugin constants */
define('ALT_FRIEND_VERSION', '0.1.0');
define('ALT_FRIEND_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ALT_FRIEND_PLUGIN_URL', plugin_dir_url(__FILE__));

/** Include required files */
require_once ALT_FRIEND_PLUGIN_DIR . 'includes/enqueue-scripts.php';
require_once ALT_FRIEND_PLUGIN_DIR . 'admin/settings-page.php';
require_once ALT_FRIEND_PLUGIN_DIR . 'includes/ajax-handler.php';
require_once ALT_FRIEND_PLUGIN_DIR . 'includes/functions.php';

/**
 * Add settings page link to plugin's row meta
 * 
 * @param array  $links Array of plugin action links
 * @param string $file  Plugin file path
 * @return array Modified array of links
 */
add_filter('plugin_row_meta', 'af_plugin_settings_link', 10, 2);
function af_plugin_settings_link($links, $file) {
    if (plugin_basename(__FILE__) === $file) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('options-general.php?page=alt-friend')),
            esc_html__('Settings')
        );
        $links[] = $settings_link;
    }
    return $links;
}
