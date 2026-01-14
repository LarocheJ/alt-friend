<?php 
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add settings page to WordPress admin menu
 * 
 * @return void
 */
add_action('admin_menu', 'af_add_admin_menu');
function af_add_admin_menu() {
    add_options_page(
        'Alt Friend Settings',
        'Alt Friend',
        'manage_options',
        'alt-friend',
        'af_settings_page'
    );
}

/**
 * Render the settings page HTML
 * 
 * @return void
 */
function af_settings_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.'));
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <hr>
        <form method="post" action="options.php">
            <?php
            settings_fields('af_settings_group');
            do_settings_sections('alt-friend');
            submit_button();
            ?>
        </form>
        
        <hr style="margin: 30px 0;">
        
        <h2>Bulk Alt Text Generation</h2>
        <p>Generate alt text for multiple images at once. This tool will process all images in your media library that don't have alt text.</p>
        
        <div id="af-bulk-generation-section">
            <button type="button" id="af-bulk-start" class="button button-primary">
                Start Bulk Generation
            </button>
            <button type="button" id="af-bulk-stop" class="button" style="display:none;">
                Stop Processing
            </button>
            
            <div id="af-bulk-progress" style="display:none; margin-top: 20px;">
                <p>
                    <strong>Processing images...</strong>
                    <span id="af-bulk-current">0</span> / <span id="af-bulk-total">0</span>
                </p>
                <div style="background: #f0f0f0; height: 30px; border-radius: 3px; overflow: hidden;">
                    <div id="af-bulk-progress-bar" style="background: #0073aa; height: 100%; width: 0%; transition: width 0.3s;"></div>
                </div>
                <div id="af-bulk-stats" style="margin-top: 15px;">
                    <p>
                        <span style="color: #46b450;">✓ Successful: <strong id="af-bulk-success">0</strong></span> | 
                        <span style="color: #dc3232;">✗ Failed: <strong id="af-bulk-failed">0</strong></span> | 
                        <span>⊘ Skipped: <strong id="af-bulk-skipped">0</strong></span>
                    </p>
                </div>
                <div id="af-bulk-log" style="margin-top: 15px; max-height: 300px; overflow-y: auto; background: white; border: 1px solid #ddd; padding: 10px; border-radius: 3px;">
                    <!-- Log messages will appear here -->
                </div>
            </div>
            
            <div id="af-bulk-complete" style="display:none; margin-top: 20px;">
                <div class="notice notice-success inline">
                    <p><strong>Bulk generation complete!</strong></p>
                    <p>Processed <span id="af-bulk-complete-total">0</span> images.</p>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Register settings
 * 
 * @return void
 */
add_action('admin_init', 'af_register_settings');
function af_register_settings() {
    register_setting(
        'af_settings_group',
        'openai_api_key',
        array(
            'type' => 'string',
            'sanitize_callback' => 'af_sanitize_api_key',
            'default' => ''
        )
    );
    
    register_setting(
        'af_settings_group',
        'af_auto_generate_on_upload',
        array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false
        )
    );

    add_settings_section(
        'af_settings_section',
        '',
        null,
        'alt-friend'
    );

    add_settings_field(
        'openai_api_key',
        'OpenAI API Key',
        'af_api_key_field_callback',
        'alt-friend',
        'af_settings_section'
    );

    add_settings_field(
        'af_auto_generate_on_upload',
        'Auto-generate on Upload',
        'af_auto_generate_field_callback',
        'alt-friend',
        'af_settings_section'
    );
}

/**
 * Render the API key input field
 * 
 * @return void
 */
function af_api_key_field_callback() {
    $api_key = get_option('openai_api_key', ''); 
    ?>
    <input 
        type="password" 
        name="openai_api_key" 
        value="<?php echo esc_attr($api_key); ?>" 
        class="af-api-key-field regular-text" 
        placeholder="sk-proj-..." 
        autocomplete="off"
    />
    <p class="description">
        Enter your <a href="https://platform.openai.com/settings/organization/api-keys" target="_blank" rel="noopener noreferrer">OpenAI API key</a> here.
    </p>
    <?php 
}

/**
 * Sanitize and validate OpenAI API key
 * 
 * @param string $api_key The API key to sanitize
 * @return string Sanitized API key
 */
function af_sanitize_api_key($api_key) {
    $api_key = sanitize_text_field($api_key);
    
    // Validate API key format (OpenAI keys start with 'sk-')
    if (!empty($api_key) && strpos($api_key, 'sk-') !== 0) {
        add_settings_error(
            'openai_api_key',
            'invalid_api_key_format',
            'Invalid API key format. OpenAI API keys should start with "sk-".',
            'error'
        );
        // Return the old value if validation fails
        return get_option('openai_api_key', '');
    }
    
    return $api_key;
}

/**
 * Settings page auto-generate checkbox field
 * 
 * @return void
 */
function af_auto_generate_field_callback() {
    $auto_generate = get_option('af_auto_generate_on_upload', false); ?>
    <label>
        <input type="checkbox" name="af_auto_generate_on_upload" value="1" <?php checked($auto_generate, true); ?> />
        Automatically generate alt text when images are uploaded
    </label>
    <p class="description">When enabled, alt text will be generated automatically for all new image uploads.</p>
<?php }