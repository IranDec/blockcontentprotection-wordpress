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
 * Domain Path:       /languages
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

    // Protection Settings Section
    add_settings_section( 'bcp_protection_section', __( 'Protection Settings', 'block-content-protection' ), null, 'block_content_protection' );
    $protection_fields = [
        'disable_right_click'    => __( 'Disable Right Click', 'block-content-protection' ),
        'disable_devtools'       => __( 'Disable Developer Tools (F12, etc.)', 'block-content-protection' ),
        'disable_copy'           => __( 'Disable Copy (Ctrl+C)', 'block-content-protection' ),
        'disable_text_selection' => __( 'Disable Text Selection', 'block-content-protection' ),
        'disable_image_drag'     => __( 'Disable Image Dragging', 'block-content-protection' ),
        'disable_video_download' => __( 'Disable Video Download', 'block-content-protection' ),
        'disable_screenshot'     => __( 'Disable Screenshot Shortcuts', 'block-content-protection' ),
        'enhanced_protection'    => __( 'Enhanced Screen Protection', 'block-content-protection' ),
    ];
    foreach ($protection_fields as $id => $label) {
        $desc = '';
        if ($id === 'disable_screenshot') {
            $desc = __( 'Blocks PrintScreen and macOS screenshot shortcuts (Cmd+Shift+3/4).', 'block-content-protection' );
        }
        if ($id === 'enhanced_protection') {
            $desc = __( 'Applies additional CSS to interfere with screen capture. Note: These methods are not foolproof and can be bypassed.', 'block-content-protection' );
        }
        add_settings_field( $id, $label, 'bcp_render_checkbox_field', 'block_content_protection', 'bcp_protection_section', [ 'id' => $id, 'description' => $desc ] );
    }

    // Exclusions Section
    add_settings_section( 'bcp_exclusions_section', __( 'Exclusion Settings', 'block-content-protection' ), null, 'block_content_protection' );
    add_settings_field( 'whitelisted_ips', __( 'Whitelisted IP Addresses', 'block-content-protection' ), 'bcp_render_textarea_field', 'block_content_protection', 'bcp_exclusions_section', [ 'id' => 'whitelisted_ips', 'description' => __( 'Enter one IP address per line. These IPs will not be affected by the protection.', 'block-content-protection' ) ] );
    add_settings_field( 'excluded_pages', __( 'Excluded Posts/Pages', 'block-content-protection' ), 'bcp_render_textfield_field', 'block_content_protection', 'bcp_exclusions_section', [ 'id' => 'excluded_pages', 'description' => __( 'Enter a comma-separated list of Post or Page IDs to exclude from protection (e.g., 1, 2, 3).', 'block-content-protection' ) ] );

    // Messages Section
    add_settings_section( 'bcp_messages_section', __( 'Custom Messages', 'block-content-protection' ), null, 'block_content_protection' );
    add_settings_field( 'screenshot_alert_message', __( 'Screenshot Alert Message', 'block-content-protection' ), 'bcp_render_textfield_field', 'block_content_protection', 'bcp_messages_section', [ 'id' => 'screenshot_alert_message', 'description' => __( 'The message shown when a user tries to take a screenshot.', 'block-content-protection' ) ] );
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

function bcp_render_textarea_field( $args ) {
    $options = get_option( 'bcp_options', [] );
    $id = $args['id'];
    $value = isset( $options[$id] ) ? esc_textarea( $options[$id] ) : '';
    echo "<textarea id='$id' name='bcp_options[$id]' rows='5' cols='50' class='large-text code'>$value</textarea>";
    if ( ! empty( $args['description'] ) ) {
        echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
    }
}

function bcp_render_textfield_field( $args ) {
    $options = get_option( 'bcp_options', [] );
    $id = $args['id'];
    $value = isset( $options[$id] ) ? esc_attr( $options[$id] ) : '';
    echo "<input type='text' id='$id' name='bcp_options[$id]' value='$value' class='regular-text' />";
    if ( ! empty( $args['description'] ) ) {
        echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
    }
}

function bcp_sanitize_options( $input ) {
    $sanitized_input = [];
    if ( ! is_array( $input ) ) {
        return $sanitized_input;
    }

    $checkboxes = [ 'disable_right_click', 'disable_devtools', 'disable_copy', 'disable_text_selection', 'disable_image_drag', 'disable_video_download', 'disable_screenshot', 'enhanced_protection' ];
    foreach ( $checkboxes as $field ) {
        $sanitized_input[$field] = ! empty( $input[$field] ) ? 1 : 0;
    }

    if ( isset( $input['whitelisted_ips'] ) ) {
        $sanitized_input['whitelisted_ips'] = implode( "\n", array_map( 'sanitize_text_field', explode( "\n", $input['whitelisted_ips'] ) ) );
    }
    if ( isset( $input['excluded_pages'] ) ) {
        $sanitized_input['excluded_pages'] = sanitize_text_field( $input['excluded_pages'] );
    }
    if ( isset( $input['screenshot_alert_message'] ) ) {
        $sanitized_input['screenshot_alert_message'] = sanitize_text_field( $input['screenshot_alert_message'] );
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

function bcp_get_user_ip() {
    $ip_keys = [ 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' ];
    foreach ( $ip_keys as $key ) {
        if ( array_key_exists( $key, $_SERVER ) === true ) {
            foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
                $ip = trim( $ip );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) !== false ) {
                    return $ip;
                }
            }
        }
    }
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}

function bcp_enqueue_scripts() {
    $options = get_option( 'bcp_options', [] );

    // --- Exclusion Logic ---
    if ( ! empty( $options['whitelisted_ips'] ) ) {
        $whitelisted_ips = array_map( 'trim', explode( "\n", $options['whitelisted_ips'] ) );
        if ( in_array( bcp_get_user_ip(), $whitelisted_ips, true ) ) {
            return;
        }
    }
    if ( ! empty( $options['excluded_pages'] ) && is_singular() ) {
        $excluded_pages = array_map( 'trim', explode( ',', $options['excluded_pages'] ) );
        if ( in_array( (string) get_the_ID(), $excluded_pages, true ) ) {
            return;
        }
    }

    $protection_options = [ 'disable_right_click', 'disable_devtools', 'disable_copy', 'disable_text_selection', 'disable_image_drag', 'disable_screenshot', 'enhanced_protection' ];
    $is_protection_enabled = false;
    foreach( $protection_options as $key ) {
        if ( ! empty( $options[$key] ) ) {
            $is_protection_enabled = true;
            break;
        }
    }

    if ( $is_protection_enabled ) {
        wp_enqueue_script( 'bcp-protect', BCP_PLUGIN_URL . 'js/protect.js', [], '1.3.0', true );
        wp_localize_script( 'bcp-protect', 'bcp_settings', $options );

        if ( ! empty( $options['enhanced_protection'] ) ) {
            wp_enqueue_style( 'bcp-protect-css', BCP_PLUGIN_URL . 'css/protect.css', [], '1.3.0' );
        }
    }
}
add_action( 'wp_enqueue_scripts', 'bcp_enqueue_scripts' );

function bcp_activation() {
    $defaults = [
        'disable_right_click'      => 1,
        'disable_devtools'         => 1,
        'disable_copy'             => 1,
        'disable_text_selection'   => 1,
        'disable_image_drag'       => 1,
        'disable_video_download'   => 1,
        'disable_screenshot'       => 1,
        'enhanced_protection'      => 0,
        'whitelisted_ips'          => '',
        'excluded_pages'           => '',
        'screenshot_alert_message' => 'Screenshots are disabled on this site.',
    ];
    if ( false === get_option( 'bcp_options' ) ) {
        update_option( 'bcp_options', $defaults );
    }
}
register_activation_hook( __FILE__, 'bcp_activation' );
