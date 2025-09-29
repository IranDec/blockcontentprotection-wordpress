<?php
/**
 * Plugin Name:       Block Content Protection
 * Description:       A simple plugin to protect website content from being copied. Disables right-click, developer tools, and more.
 * Plugin URI:        https://adschi.com/
 * Version:           1.0.0
 * Author:            Mohammad Babaei
 * Author URI:        https://adschi.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       block-content-protection
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define plugin constants
define( 'BCP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'BCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Add the admin menu page.
 */
function bcp_add_admin_menu() {
    add_options_page(
        __( 'Block Content Protection', 'block-content-protection' ),
        __( 'Block Content Protection', 'block-content-protection' ),
        'manage_options',
        'block_content_protection',
        'bcp_options_page'
    );
}
add_action( 'admin_menu', 'bcp_add_admin_menu' );

/**
 * Register the settings.
 */
function bcp_register_settings() {
    register_setting( 'bcp_settings_group', 'bcp_options', 'bcp_sanitize_options' );

    add_settings_section(
        'bcp_settings_section',
        __( 'Protection Settings', 'block-content-protection' ),
        null,
        'block_content_protection'
    );

    $fields = [
        'disable_right_click' => __( 'Disable Right Click', 'block-content-protection' ),
        'disable_devtools' => __( 'Disable Developer Tools (F12, Ctrl+Shift+I)', 'block-content-protection' ),
        'disable_screenshot' => __( 'Disable Screenshot (PrintScreen)', 'block-content-protection' ),
        'disable_video_download' => __( 'Disable Video Download', 'block-content-protection' ),
        'disable_image_drag' => __( 'Disable Image Drag', 'block-content-protection' ),
        'disable_text_selection' => __( 'Disable Text Selection', 'block-content-protection' ),
        'disable_copy' => __( 'Disable Copy (Ctrl+C)', 'block-content-protection' ),
    ];

    foreach ($fields as $id => $label) {
        add_settings_field(
            $id,
            $label,
            'bcp_render_checkbox_field',
            'block_content_protection',
            'bcp_settings_section',
            [ 'id' => $id ]
        );
    }
}
add_action( 'admin_init', 'bcp_register_settings' );

/**
 * Render a checkbox field for a setting.
 *
 * @param array $args The arguments for the field.
 */
function bcp_render_checkbox_field( $args ) {
    $options = get_option( 'bcp_options' );
    $id = $args['id'];
    $checked = isset( $options[$id] ) ? checked( $options[$id], 1, false ) : '';
    echo "<label for='$id'><input type='checkbox' id='$id' name='bcp_options[$id]' value='1' $checked /> " . __( 'Enable', 'block-content-protection' ) . "</label>";
}

/**
 * Sanitize the option values.
 *
 * @param array $input The input options.
 * @return array The sanitized options.
 */
function bcp_sanitize_options( $input ) {
    $sanitized_input = [];
    $fields = [
        'disable_right_click',
        'disable_devtools',
        'disable_screenshot',
        'disable_video_download',
        'disable_image_drag',
        'disable_text_selection',
        'disable_copy',
    ];

    if ( is_array( $input ) ) {
        foreach ( $fields as $field ) {
            if ( ! empty( $input[$field] ) ) {
                $sanitized_input[$field] = 1;
            } else {
                 $sanitized_input[$field] = 0;
            }
        }
    }
    return $sanitized_input;
}

/**
 * Render the options page.
 */
function bcp_options_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'bcp_settings_group' );
            do_settings_sections( 'block_content_protection' );
            submit_button( __( 'Save Settings', 'block-content-protection' ) );
            ?>
        </form>
        <div style="margin-top: 20px; padding-top: 10px; border-top: 1px solid #ccc; text-align: center;">
            <p>
                <a href="https://adschi.com/" target="_blank">مشاوره حرفه ای راه اندازی کمپین های تبلیغاتی و طراحی تخصصی سایت ادزچی</a>
            </p>
        </div>
    </div>
    <?php
}

/**
 * Enqueue scripts.
 */
function bcp_enqueue_scripts() {
    $options = get_option( 'bcp_options' );

    // Only enqueue if there are any settings saved.
    if ( ! empty( $options ) && is_array( $options ) ) {
        // Check if at least one protection is enabled.
        $is_protection_enabled = false;
        foreach($options as $option) {
            if ($option) {
                $is_protection_enabled = true;
                break;
            }
        }

        if($is_protection_enabled){
             wp_enqueue_script(
                'bcp-protect',
                BCP_PLUGIN_URL . 'js/protect.js',
                [],
                '1.0.0',
                true
            );
            wp_localize_script( 'bcp-protect', 'bcp_settings', $options );
        }
    }
}
add_action( 'wp_enqueue_scripts', 'bcp_enqueue_scripts' );
