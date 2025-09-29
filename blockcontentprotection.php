<?php
/**
 * Plugin Name: Block Content Protection
 * Description: Protect your site content from being copied. Disables right-click, developer tools, and more.
 * Version: 1.3.0
 * Author: Mohammad Babaei
 * Author URI: https://adschi.com
 * Text Domain: block-content-protection
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Activation hook to set default options
function bcp_activate() {
    $defaults = [
        'bcp_disable_rightclick' => 'on',
        'bcp_disable_devtools' => 'on',
        'bcp_disable_screenshot' => 'on',
        'bcp_disable_video_download' => 'on',
        'bcp_disable_dblclick_copy' => 'on',
        'bcp_disable_text_selection' => 'on',
        'bcp_enhanced_protection' => 'off',
    ];

    foreach ($defaults as $key => $value) {
        if (get_option($key) === false) {
            update_option($key, $value);
        }
    }
}
register_activation_hook(__FILE__, 'bcp_activate');

// Deactivation hook to remove options
function bcp_deactivate() {
    $options = [
        'bcp_disable_rightclick',
        'bcp_disable_devtools',
        'bcp_disable_screenshot',
        'bcp_disable_video_download',
        'bcp_disable_dblclick_copy',
        'bcp_disable_text_selection',
        'bcp_enhanced_protection',
    ];
    foreach ($options as $option) {
        delete_option($option);
    }
}
register_deactivation_hook(__FILE__, 'bcp_deactivate');

// Enqueue scripts and styles
function bcp_enqueue_scripts() {
    // Enqueue JS
    wp_enqueue_script(
        'bcp-protect-js',
        plugin_dir_url(__FILE__) . 'views/js/protect.js',
        [],
        '1.3.0',
        true
    );

    // Localize script with settings
    $settings = [
        'BCP_DISABLE_RIGHTCLICK' => get_option('bcp_disable_rightclick') === 'on',
        'BCP_DISABLE_DEVTOOLS' => get_option('bcp_disable_devtools') === 'on',
        'BCP_DISABLE_SCREENSHOT' => get_option('bcp_disable_screenshot') === 'on',
        'BCP_DISABLE_VIDEO_DOWNLOAD' => get_option('bcp_disable_video_download') === 'on',
        'BCP_DISABLE_DBLCLICK_COPY' => get_option('bcp_disable_dblclick_copy') === 'on',
        'BCP_DISABLE_TEXT_SELECTION' => get_option('bcp_disable_text_selection') === 'on',
        'BCP_ENHANCED_PROTECTION' => get_option('bcp_enhanced_protection') === 'on',
    ];
    wp_localize_script('bcp-protect-js', 'bcp_settings', $settings);

    // Conditionally enqueue CSS for enhanced protection
    if ($settings['BCP_ENHANCED_PROTECTION']) {
        // Ensure the CSS file exists before enqueueing
        if (file_exists(plugin_dir_path(__FILE__) . 'views/css/protect.css')) {
             wp_enqueue_style(
                'bcp-protect-css',
                plugin_dir_url(__FILE__) . 'views/css/protect.css',
                [],
                '1.3.0'
            );
        }
    }
}
add_action('wp_enqueue_scripts', 'bcp_enqueue_scripts');

// Add settings page
function bcp_add_admin_menu() {
    add_options_page(
        'Block Content Protection',
        'Content Protection',
        'manage_options',
        'block_content_protection',
        'bcp_options_page_html'
    );
}
add_action('admin_menu', 'bcp_add_admin_menu');

// Register settings
function bcp_register_settings() {
    $options = [
        'bcp_disable_rightclick',
        'bcp_disable_devtools',
        'bcp_disable_screenshot',
        'bcp_disable_video_download',
        'bcp_disable_dblclick_copy',
        'bcp_disable_text_selection',
        'bcp_enhanced_protection',
    ];

    foreach ($options as $option) {
        register_setting('bcp_settings_group', $option);
    }
}
add_action('admin_init', 'bcp_register_settings');

// Settings page HTML
function bcp_options_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <p>Developed by Mohammad Babaei - <a href="https://adschi.com" target="_blank">adschi.com</a></p>
        <form action="options.php" method="post">
            <?php settings_fields('bcp_settings_group'); ?>
            <table class="form-table">
                <?php
                bcp_render_option('bcp_disable_rightclick', 'Disable Right Click');
                bcp_render_option('bcp_disable_devtools', 'Disable Developer Tools');
                bcp_render_option('bcp_disable_screenshot', 'Disable Screenshots (PrintScreen)');
                bcp_render_option('bcp_disable_video_download', 'Disable Video Download');
                bcp_render_option('bcp_disable_dblclick_copy', 'Disable Double Click & Copy');
                bcp_render_option('bcp_disable_text_selection', 'Disable Text Selection');
                bcp_render_option('bcp_enhanced_protection', 'Enhanced Screen Protection', 'Attempts to block screenshots and screen recording. May not work in all browsers.');
                ?>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Helper function to render a checkbox option
function bcp_render_option($option_name, $label, $description = '') {
    ?>
    <tr valign="top">
        <th scope="row"><?php echo esc_html($label); ?></th>
        <td>
            <label>
                <input type="checkbox" name="<?php echo esc_attr($option_name); ?>" <?php checked(get_option($option_name), 'on'); ?> />
                <?php if ($description) : ?>
                    <p class="description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
            </label>
        </td>
    </tr>
    <?php
}