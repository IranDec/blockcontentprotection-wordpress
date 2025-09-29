<?php
/**
 * Plugin Name:       Block Content Protection
 * Description:       A simple plugin to protect website content from being copied. Disables right-click, developer tools, and more.
 * Plugin URI:        https://adschi.com/
 * Version:           1.3.0
 * Author:            Mohammad Babaei
 * Author URI:        https://adschi.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       block-content-protection
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'BCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

function bcp_add_admin_menu() {
    add_options_page(
        __( 'Content Protection', 'block-content-protection' ),
        __( 'Content Protection', 'block-content-protection' ),
        'manage_options',
        'block_content_protection',
        'bcp_options_page'
    );
}
add_action( 'admin_menu', 'bcp_add_admin_menu' );

function bcp_register_settings() {
    register_setting( 'bcp_settings_group', 'bcp_options', 'bcp_sanitize_options' );

    add_settings_section(
        'bcp_settings_section',
        __( 'Protection Settings', 'block-content-protection' ),
        null,
        'block_content_protection'
    );

    $fields = [
        'disable_right_click'    => __( 'Disable Right Click', 'block-content-protection' ),
        'disable_devtools'       => __( 'Disable Developer Tools (F12, etc.)', 'block-content-protection' ),
        'disable_copy'           => __( 'Disable Copy (Ctrl+C)', 'block-content-protection' ),
        'disable_text_selection' => __( 'Disable Text Selection', 'block-content-protection' ),
        'disable_image_drag'     => __( 'Disable Image Dragging', 'block-content-protection' ),
        'enhanced_protection'    => __( 'Enhanced Screen Protection', 'block-content-protection' ),
    ];

    foreach ($fields as $id => $label) {
        $description = ($id === 'enhanced_protection') ? __( 'Attempts to block screenshots and screen recording. May not work in all browsers.', 'block-content-protection' ) : '';
        add_settings_field(
            $id,
            $label,
            'bcp_render_checkbox_field',
            'block_content_protection',
            'bcp_settings_section',
            [ 'id' => $id, 'description' => $description ]
        );
    }
}
add_action( 'admin_init', 'bcp_register_settings' );

function bcp_render_checkbox_field( $args ) {
    $options = get_option( 'bcp_options', [] );
    $id = $args['id'];
    $checked = isset( $options[$id] ) ? checked( $options[$id], 1, false ) : '';
    echo "<label for='$id'><input type='checkbox' id='$id' name='bcp_options[$id]' value='1' $checked />";
    if ( ! empty( $args['description'] ) ) {
        echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
    }
    echo "</label>";
}

function bcp_sanitize_options( $input ) {
    $sanitized_input = [];
    $fields = [
        'disable_right_click',
        'disable_devtools',
        'disable_copy',
        'disable_text_selection',
        'disable_image_drag',
        'enhanced_protection',
    ];

    if ( is_array( $input ) ) {
        foreach ( $fields as $field ) {
            $sanitized_input[$field] = ! empty( $input[$field] ) ? 1 : 0;
        }
    }
    return $sanitized_input;
}

function bcp_options_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <p>Developed by Mohammad Babaei - <a href="https://adschi.com" target="_blank">adschi.com</a></p>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'bcp_settings_group' );
            do_settings_sections( 'block_content_protection' );
            submit_button( __( 'Save Settings', 'block-content-protection' ) );
            ?>
        </form>
    </div>
    <?php
}

function bcp_enqueue_scripts() {
    $options = get_option( 'bcp_options', [] );

    // Check if at least one protection is enabled.
    $is_protection_enabled = in_array(1, $options);

    if ( $is_protection_enabled ) {
        wp_enqueue_script(
            'bcp-protect',
            BCP_PLUGIN_URL . 'js/protect.js',
            [],
            '1.3.0',
            true
        );
        wp_localize_script( 'bcp-protect', 'bcp_settings', $options );

        // Conditionally enqueue CSS for enhanced protection
        if ( ! empty( $options['enhanced_protection'] ) ) {
            wp_enqueue_style(
                'bcp-protect-css',
                BCP_PLUGIN_URL . 'css/protect.css',
                [],
                '1.3.0'
            );
        }
    }
}
add_action( 'wp_enqueue_scripts', 'bcp_enqueue_scripts' );

function bcp_activation() {
    $defaults = [
        'disable_right_click'    => 1,
        'disable_devtools'       => 1,
        'disable_copy'           => 1,
        'disable_text_selection' => 1,
        'disable_image_drag'     => 1,
        'enhanced_protection'    => 0,
    ];
    if ( false === get_option( 'bcp_options' ) ) {
        update_option( 'bcp_options', $defaults );
    }
}
register_activation_hook( __FILE__, 'bcp_activation' );
