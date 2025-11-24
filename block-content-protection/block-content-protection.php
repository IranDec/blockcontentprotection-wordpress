<?php
/**
 * Plugin Name:       Block Content Protection
 * Description:       A comprehensive plugin to protect website content. Blocks screenshots, screen recording, right-click, developer tools, and more.
 * Plugin URI:        https://adschi.com/
 * Version:           1.6.7
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
    add_menu_page(
        __( 'Content Protection', 'block-content-protection' ),
        __( 'Content Protection', 'block-content-protection' ),
        'manage_options',
        'block_content_protection',
        'bcp_options_page',
        'dashicons-shield-alt', // Icon
        25 // Position
    );
}
add_action( 'admin_menu', 'bcp_add_admin_menu' );

function bcp_register_settings() {
    register_setting( 'bcp_settings_group', 'bcp_options', 'bcp_sanitize_options' );

    // Protection Settings Section
    add_settings_section( 'bcp_protection_section', __( 'Protection Settings', 'block-content-protection' ), null, 'block_content_protection' );
    $protection_fields = [
        'disable_right_click'       => __( 'Disable Right Click', 'block-content-protection' ),
        'disable_devtools'          => __( 'Disable Developer Tools (F12, etc.)', 'block-content-protection' ),
        'disable_copy'              => __( 'Disable Copy (Ctrl+C)', 'block-content-protection' ),
        'disable_text_selection'    => __( 'Disable Text Selection', 'block-content-protection' ),
        'disable_image_drag'        => __( 'Disable Image Dragging', 'block-content-protection' ),
        'disable_video_download'    => __( 'Disable Video Download', 'block-content-protection' ),
        'disable_screenshot'        => __( 'Disable Screenshot Shortcuts', 'block-content-protection' ),
        'enhanced_protection'       => __( 'Enhanced Screen Protection', 'block-content-protection' ),
        'mobile_screenshot_block'   => __( 'Block Mobile Screenshots', 'block-content-protection' ),
        'video_screen_record_block' => __( 'Block Video Screen Recording', 'block-content-protection' ),
    ];
    foreach ($protection_fields as $id => $label) {
        $desc = '';
        if ($id === 'disable_screenshot') {
            $desc = __( 'Blocks PrintScreen and macOS screenshot shortcuts (Cmd+Shift+3/4).', 'block-content-protection' );
        }
        if ($id === 'enhanced_protection') {
            $desc = __( 'Applies additional CSS to interfere with screen capture. Note: These methods are not foolproof.', 'block-content-protection' );
        }
        if ($id === 'mobile_screenshot_block') {
            $desc = __( 'Attempts to block screenshots on mobile devices. Works on some Android devices.', 'block-content-protection' );
        }
        if ($id === 'video_screen_record_block') {
            $desc = __( 'Client-side methods are not foolproof. For the highest level of security, a Digital Rights Management (DRM) service is the recommended solution.', 'block-content-protection' );
        }
        add_settings_field( $id, $label, 'bcp_render_checkbox_field', 'block_content_protection', 'bcp_protection_section', [ 'id' => $id, 'description' => $desc ] );
    }

    // Exclusions Section
    add_settings_section( 'bcp_exclusions_section', __( 'Exclusion Settings', 'block-content-protection' ), null, 'block_content_protection' );
    add_settings_field( 'whitelisted_ips', __( 'Whitelisted IP Addresses', 'block-content-protection' ), 'bcp_render_textarea_field', 'block_content_protection', 'bcp_exclusions_section', [ 'id' => 'whitelisted_ips', 'description' => __( 'Enter one IP address per line. These IPs will not be affected by the protection.', 'block-content-protection' ) ] );
    add_settings_field( 'excluded_pages', __( 'Excluded Posts/Pages', 'block-content-protection' ), 'bcp_render_textfield_field', 'block_content_protection', 'bcp_exclusions_section', [ 'id' => 'excluded_pages', 'description' => __( 'Enter a comma-separated list of Post or Page IDs to exclude from protection (e.g., 1, 2, 3).', 'block-content-protection' ) ] );

    // Messages Section
    add_settings_section( 'bcp_messages_section', null, null, 'block_content_protection' );
    add_settings_field( 'enable_custom_messages', __( 'Enable Custom Messages', 'block-content-protection' ), 'bcp_render_checkbox_field', 'block_content_protection', 'bcp_messages_section', [ 'id' => 'enable_custom_messages', 'description' => __( 'Enable to override the default browser alerts with your own messages.', 'block-content-protection' ) ] );
    add_settings_field( 'screenshot_alert_message', __( 'Screenshot Alert Message', 'block-content-protection' ), 'bcp_render_textfield_field', 'block_content_protection', 'bcp_messages_section', [ 'id' => 'screenshot_alert_message', 'description' => __( 'The message shown when a user tries to take a screenshot.', 'block-content-protection' ), 'class' => 'bcp-message-field' ] );
    add_settings_field( 'recording_alert_message', __( 'Screen Recording Alert', 'block-content-protection' ), 'bcp_render_textfield_field', 'block_content_protection', 'bcp_messages_section', [ 'id' => 'recording_alert_message', 'description' => __( 'Message shown when screen recording is detected.', 'block-content-protection' ), 'class' => 'bcp-message-field' ] );

    // Watermark Section
    add_settings_section( 'bcp_watermark_section', null, null, 'block_content_protection' );
    add_settings_field( 'enable_video_watermark', __( 'Enable Video Watermark', 'block-content-protection' ), 'bcp_render_checkbox_field', 'block_content_protection', 'bcp_watermark_section', [ 'id' => 'enable_video_watermark', 'description' => __( 'Enable this to show a dynamic watermark over videos.', 'block-content-protection' ) ] );
    // add_settings_field( 'enable_page_watermark', __( 'Enable Full Page Watermark', 'block-content-protection' ), 'bcp_render_checkbox_field', 'block_content_protection', 'bcp_watermark_section', [ 'id' => 'enable_page_watermark', 'description' => __( 'Enable this to show a dynamic watermark over the entire page.', 'block-content-protection' ) ] );
    add_settings_field( 'watermark_text', __( 'Watermark Text', 'block-content-protection' ), 'bcp_render_textfield_field', 'block_content_protection', 'bcp_watermark_section', [ 'id' => 'watermark_text', 'description' => __( 'Enter text for the watermark. Use placeholders: {user_login}, {user_email}, {user_mobile}, {ip_address}, {date}.', 'block-content-protection' ) ] );
    add_settings_field( 'watermark_opacity', __( 'Watermark Opacity', 'block-content-protection' ), 'bcp_render_number_field', 'block_content_protection', 'bcp_watermark_section', [ 'id' => 'watermark_opacity', 'description' => __( 'Set the opacity from 0 (transparent) to 1 (opaque). Default: 0.5', 'block-content-protection' ), 'min' => 0, 'max' => 1, 'step' => '0.1' ] );
    add_settings_field( 'watermark_position', __( 'Watermark Position', 'block-content-protection' ), 'bcp_render_select_field', 'block_content_protection', 'bcp_watermark_section', [ 'id' => 'watermark_position', 'description' => __( 'Select the watermark position.', 'block-content-protection' ), 'options' => [ 'animated' => 'Animated', 'top_left' => 'Top Left', 'top_right' => 'Top Right', 'bottom_left' => 'Bottom Left', 'bottom_right' => 'Bottom Right', ] ] );
    add_settings_field( 'watermark_style', __( 'Watermark Style', 'block-content-protection' ), 'bcp_render_select_field', 'block_content_protection', 'bcp_watermark_section', [ 'id' => 'watermark_style', 'description' => __( 'Select the watermark style.', 'block-content-protection' ), 'options' => [ 'text' => 'Simple Text', 'pattern' => 'Pattern' ] ] );

    // Device Limit Section
    add_settings_section( 'bcp_device_limit_section', __( 'Device Limit Settings', 'block-content-protection' ), null, 'block_content_protection' );
    add_settings_field( 'enable_device_limit', __( 'Enable Device Limit', 'block-content-protection' ), 'bcp_render_checkbox_field', 'block_content_protection', 'bcp_device_limit_section', [ 'id' => 'enable_device_limit', 'description' => __( 'Enable to limit the number of devices per user.', 'block-content-protection' ) ] );
    add_settings_field( 'device_limit_number', __( 'Number of Devices Allowed', 'block-content-protection' ), 'bcp_render_number_field', 'block_content_protection', 'bcp_device_limit_section', [ 'id' => 'device_limit_number', 'description' => __( 'Set the maximum number of devices a user can log in with. Default: 3', 'block-content-protection' ), 'min' => 1, 'step' => 1 ] );
    add_settings_field( 'device_limit_message', __( 'Device Limit Reached Message', 'block-content-protection' ), 'bcp_render_textfield_field', 'block_content_protection', 'bcp_device_limit_section', [ 'id' => 'device_limit_message', 'description' => __( 'The message shown to the user when they have reached their device limit.', 'block-content-protection' ) ] );
}
add_action( 'admin_init', 'bcp_register_settings' );

function bcp_render_select_field( $args ) {
    $options = get_option( 'bcp_options', [] );
    $id = $args['id'];
    $value = isset( $options[$id] ) ? esc_attr( $options[$id] ) : '';
    echo "<select id='$id' name='bcp_options[$id]'>";
    foreach ( $args['options'] as $val => $label ) {
        echo "<option value='" . esc_attr( $val ) . "' " . selected( $value, $val, false ) . ">" . esc_html( $label ) . "</option>";
    }
    echo "</select>";
    if ( ! empty( $args['description'] ) ) {
        echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
    }
}

function bcp_render_number_field( $args ) {
    $options = get_option( 'bcp_options', [] );
    $id = $args['id'];
    $value = isset( $options[$id] ) ? esc_attr( $options[$id] ) : '';
    $min = isset( $args['min'] ) ? $args['min'] : '';
    $max = isset( $args['max'] ) ? $args['max'] : '';
    $step = isset( $args['step'] ) ? $args['step'] : '';
    echo "<input type='number' id='$id' name='bcp_options[$id]' value='$value' class='regular-text' min='$min' max='$max' step='$step' />";
    if ( ! empty( $args['description'] ) ) {
        echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
    }
}

function bcp_render_checkbox_field( $args ) {
    $options = get_option( 'bcp_options', [] );
    $id = $args['id'];
    $checked = isset( $options[$id] ) ? checked( $options[$id], 1, false ) : '';
    echo "<label for='$id'>";
    echo "<input type='checkbox' id='$id' name='bcp_options[$id]' value='1' $checked />";
    echo '<span class="bcp-switch"></span>'; // This is the toggle switch
    if ( ! empty( $args['description'] ) ) {
        echo '<span class="bcp-field-description">' . esc_html( $args['description'] ) . '</span>';
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
    // Initialize a new array to store the sanitized values.
    $new_options = [];

    // Ensure the input is an array, even if no settings are submitted.
    $input = is_array( $input ) ? $input : [];

    // Define all known checkbox fields.
    $checkboxes = [
        'disable_right_click', 'disable_devtools', 'disable_copy',
        'disable_text_selection', 'disable_image_drag', 'disable_video_download',
        'disable_screenshot', 'enhanced_protection', 'mobile_screenshot_block',
        'video_screen_record_block', 'enable_video_watermark', //'enable_page_watermark',
        'enable_custom_messages', 'enable_device_limit'
    ];

    // For each checkbox, if it was submitted (checked), set to 1. Otherwise (unchecked), set to 0.
    foreach ( $checkboxes as $field ) {
        $new_options[$field] = ! empty( $input[$field] ) ? 1 : 0;
    }

    // Sanitize text and textarea fields.
    if ( isset( $input['whitelisted_ips'] ) ) {
        $new_options['whitelisted_ips'] = implode( "\n", array_map( 'sanitize_text_field', explode( "\n", $input['whitelisted_ips'] ) ) );
    }
    if ( isset( $input['excluded_pages'] ) ) {
        $new_options['excluded_pages'] = sanitize_text_field( $input['excluded_pages'] );
    }
    if ( isset( $input['screenshot_alert_message'] ) ) {
        $new_options['screenshot_alert_message'] = sanitize_text_field( $input['screenshot_alert_message'] );
    }
    if ( isset( $input['recording_alert_message'] ) ) {
        $new_options['recording_alert_message'] = sanitize_text_field( $input['recording_alert_message'] );
    }
    if ( isset( $input['watermark_text'] ) ) {
        $new_options['watermark_text'] = sanitize_text_field( $input['watermark_text'] );
    }

    // Sanitize number and select fields.
    if ( isset( $input['watermark_opacity'] ) ) {
        $new_options['watermark_opacity'] = floatval( $input['watermark_opacity'] );
    }
    if ( isset( $input['watermark_position'] ) ) {
        $new_options['watermark_position'] = sanitize_key( $input['watermark_position'] );
    }
    if ( isset( $input['watermark_style'] ) ) {
        $new_options['watermark_style'] = sanitize_key( $input['watermark_style'] );
    }
    if ( isset( $input['device_limit_number'] ) ) {
        $new_options['device_limit_number'] = intval( $input['device_limit_number'] );
    }
    if ( isset( $input['device_limit_message'] ) ) {
        $new_options['device_limit_message'] = sanitize_text_field( $input['device_limit_message'] );
    }

    return $new_options;
}

function bcp_options_page() {
    $plugin_data = get_plugin_data( __FILE__ );
    ?>
    <div class="wrap bcp-wrap">
        <div class="bcp-header">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        </div>

        <div class="notice notice-warning">
            <p><strong>⚠️ <?php _e( 'Attention:', 'block-content-protection' ); ?></strong> <?php _e( 'Completely preventing screenshots and screen recording is impossible. These tools only make it more difficult, not impossible.', 'block-content-protection' ); ?></p>
        </div>

        <form action="options.php" method="post">
            <?php settings_fields( 'bcp_settings_group' ); ?>
            <div class="bcp-content">
                <div class="bcp-main">
                    <!-- Protection Settings Card -->
                    <div class="bcp-card">
                        <h2 class="bcp-card-header"><?php _e( 'Protection Settings', 'block-content-protection' ); ?></h2>
                        <div class="bcp-card-body">
                            <table class="form-table">
                                <?php do_settings_fields( 'block_content_protection', 'bcp_protection_section' ); ?>
                            </table>
                        </div>
                    </div>

                    <!-- Watermark Settings Card -->
                    <div class="bcp-card">
                        <h2 class="bcp-card-header"><?php _e( 'Watermark Settings', 'block-content-protection' ); ?></h2>
                        <div class="bcp-card-body">
                            <table class="form-table">
                                <?php do_settings_fields( 'block_content_protection', 'bcp_watermark_section' ); ?>
                            </table>
                        </div>
                    </div>

                    <!-- Device Limit Settings Card -->
                    <div class="bcp-card">
                        <h2 class="bcp-card-header"><?php _e( 'Device Limit Settings', 'block-content-protection' ); ?></h2>
                        <div class="bcp-card-body">
                            <table class="form-table">
                                <?php do_settings_fields( 'block_content_protection', 'bcp_device_limit_section' ); ?>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="bcp-sidebar">
                    <!-- Exclusion Settings Card -->
                    <div class="bcp-card">
                        <h2 class="bcp-card-header"><?php _e( 'Exclusion Settings', 'block-content-protection' ); ?></h2>
                        <div class="bcp-card-body">
                            <table class="form-table">
                                <?php do_settings_fields( 'block_content_protection', 'bcp_exclusions_section' ); ?>
                            </table>
                        </div>
                    </div>

                    <!-- Messages Card -->
                    <div class="bcp-card">
                        <h2 class="bcp-card-header"><?php _e( 'Custom Messages', 'block-content-protection' ); ?></h2>
                        <div class="bcp-card-body">
                            <table class="form-table">
                                <?php do_settings_fields( 'block_content_protection', 'bcp_messages_section' ); ?>
                            </table>
                        </div>
                    </div>
                     <div class="bcp-card">
                        <div class="bcp-card-body">
                             <?php submit_button( __( 'Save Settings', 'block-content-protection' ) ); ?>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div class="bcp-footer">
            <p>
                <?php
                $allowed_html = [
                    'a' => [
                        'href'   => [],
                        'target' => ['_blank'],
                    ],
                ];
                $footer_text = sprintf(
                    /* translators: 1: Plugin Name, 2: Plugin Version, 3: Author Link. */
                    __( 'Thank you for using %1$s! Version %2$s by %3$s.', 'block-content-protection' ),
                    esc_html( $plugin_data['Name'] ),
                    esc_html( $plugin_data['Version'] ),
                    '<a href="' . esc_url( $plugin_data['AuthorURI'] ) . '" target="_blank">' . esc_html( $plugin_data['Author'] ) . '</a>'
                );
                echo wp_kses( $footer_text, $allowed_html );
                ?>
            </p>
        </div>
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

function bcp_add_meta_tags() {
    $options = get_option( 'bcp_options', [] );
    
    if ( ! empty( $options['mobile_screenshot_block'] ) ) {
        echo '<meta name="flags" content="FLAG_SECURE">';
        echo '<meta name="mobile-web-app-capable" content="yes">';
    }
}
add_action( 'wp_head', 'bcp_add_meta_tags' );

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

    $protection_options = [ 'disable_right_click', 'disable_devtools', 'disable_copy', 'disable_text_selection', 'disable_image_drag', 'disable_screenshot', 'enhanced_protection', 'mobile_screenshot_block', 'video_screen_record_block' ];
    $is_protection_enabled = false;
    foreach( $protection_options as $key ) {
        if ( ! empty( $options[$key] ) ) {
            $is_protection_enabled = true;
            break;
        }
    }

    if ( $is_protection_enabled || ! empty( $options['enable_video_watermark'] ) /*|| ! empty( $options['enable_page_watermark'] )*/ ) {
        // Replace watermark placeholders
        if ( ( ! empty( $options['enable_video_watermark'] ) /*|| ! empty( $options['enable_page_watermark'] )*/ ) && ! empty( $options['watermark_text'] ) ) {
            $current_user = wp_get_current_user();
            $ip_address = bcp_get_user_ip();
            $date = date( get_option( 'date_format' ) );
            $user_mobile = $current_user->user_login; // Fallback to username

            // Check for Digits plugin mobile number
            if ( function_exists( 'get_user_meta' ) && $current_user->ID ) {
                $digits_mobile = get_user_meta( $current_user->ID, 'digits_phone', true );
                if ( ! empty( $digits_mobile ) ) {
                    $user_mobile = $digits_mobile;
                }
            }

            $replacements = [
                '{user_login}'  => $current_user->user_login,
                '{user_email}'  => $current_user->user_email,
                '{user_mobile}' => $user_mobile,
                '{ip_address}'  => $ip_address,
                '{date}'        => $date,
            ];

            $options['watermark_text'] = str_replace( array_keys( $replacements ), array_values( $replacements ), $options['watermark_text'] );
        }

        // Enqueue the new module script
        wp_enqueue_script( 'bcp-protect-module', BCP_PLUGIN_URL . 'js/protect.module.js', [], '1.6.6', true );

        // Create a data bridge for the module
        add_action('wp_footer', function() use ($options) {
            echo '<script type="application/json" id="bcp-settings-data">' . wp_json_encode($options) . '</script>';
        }, 99);


        // Enqueue styles if needed
        if ( ! empty( $options['enhanced_protection'] ) || ! empty( $options['video_screen_record_block'] ) || ! empty( $options['enable_video_watermark'] ) ) {
            wp_enqueue_style( 'bcp-protect-css', BCP_PLUGIN_URL . 'css/protect.css', [], '1.6.6' );
        }
    }
}
add_action( 'wp_enqueue_scripts', 'bcp_enqueue_scripts' );

function bcp_add_module_to_script( $tag, $handle, $src ) {
    if ( 'bcp-protect-module' === $handle ) {
        // Since the module is self-executing, we just need to add type="module"
        $tag = '<script type="module" src="' . esc_url( $src ) . '" id="' . esc_attr( $handle ) . '-js"></script>';
    }
    return $tag;
}
add_filter( 'script_loader_tag', 'bcp_add_module_to_script', 10, 3 );

/**
 * Ensures a device ID cookie is set for the visitor.
 * This function will be hooked into 'init'.
 */
function bcp_manage_device_id_cookie() {
    if ( ! isset( $_COOKIE['bcp_device_id'] ) || ! wp_is_uuid( $_COOKIE['bcp_device_id'] ) ) {
        $uuid = wp_generate_uuid4();
        $expire = isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] + ( 10 * YEAR_IN_SECONDS ) : time() + ( 10 * YEAR_IN_SECONDS );
        setcookie( 'bcp_device_id', $uuid, $expire, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        $_COOKIE['bcp_device_id'] = $uuid;
    }
}
add_action( 'init', 'bcp_manage_device_id_cookie' );

/**
 * Retrieves the unique device ID from the cookie.
 *
 * @return string|null The device ID or null if not set.
 */
function bcp_get_device_id() {
    if ( isset( $_COOKIE['bcp_device_id'] ) ) {
        return sanitize_text_field( $_COOKIE['bcp_device_id'] );
    }
    return null;
}

/**
 * Tracks the user's device upon login.
 *
 * @param string  $user_login The user's login name.
 * @param WP_User $user       The logged-in user object.
 */
function bcp_track_user_device( $user_login, $user ) {
    $options = get_option( 'bcp_options', [] );
    if ( empty( $options['enable_device_limit'] ) ) {
        return;
    }

    $user_id = $user->ID;
    $device_id = bcp_get_device_id();

    if ( ! $device_id ) {
        return;
    }

    $active_devices = get_user_meta( $user_id, 'bcp_active_devices', true );
    if ( ! is_array( $active_devices ) ) {
        $active_devices = [];
    }

    if ( ! in_array( $device_id, $active_devices, true ) ) {
        $active_devices[] = $device_id;
        update_user_meta( $user_id, 'bcp_active_devices', $active_devices );
    }
}
add_action( 'wp_login', 'bcp_track_user_device', 10, 2 );

/**
 * Removes the user's device upon logout.
 *
 * @param int $user_id The ID of the user logging out.
 */
function bcp_untrack_user_device( $user_id ) {
    $options = get_option( 'bcp_options', [] );
    if ( empty( $options['enable_device_limit'] ) ) {
        return;
    }

    if ( ! $user_id ) {
        return;
    }

    $device_id = bcp_get_device_id();
    if ( ! $device_id ) {
        return;
    }

    $active_devices = get_user_meta( $user_id, 'bcp_active_devices', true );

    if ( is_array( $active_devices ) ) {
        $new_devices = array_values( array_diff( $active_devices, [ $device_id ] ) );
        update_user_meta( $user_id, 'bcp_active_devices', $new_devices );
    }
}
add_action( 'wp_logout', 'bcp_untrack_user_device', 10, 1 );

/**
 * Validates the user's device count before allowing login.
 *
 * @param WP_User|WP_Error|null $user     The user object or error object.
 * @param string                $username The username.
 * @param string                $password The user's password.
 * @return WP_User|WP_Error The user object if login is allowed, otherwise a WP_Error.
 */
function bcp_validate_device_limit( $user, $username, $password ) {
    if ( is_wp_error( $user ) || ! $user ) {
        return $user;
    }

    $options = get_option( 'bcp_options', [] );
    if ( empty( $options['enable_device_limit'] ) ) {
        return $user;
    }

    $user_id = $user->ID;
    $device_id = bcp_get_device_id();

    if ( ! $device_id ) {
        // If there's no device ID, we can't enforce the limit, so we allow login.
        return $user;
    }

    $active_devices = get_user_meta( $user_id, 'bcp_active_devices', true );
    if ( ! is_array( $active_devices ) ) {
        $active_devices = [];
    }

    $limit = ! empty( $options['device_limit_number'] ) ? intval( $options['device_limit_number'] ) : 3;

    if ( ! in_array( $device_id, $active_devices, true ) && count( $active_devices ) >= $limit ) {
        $message = ! empty( $options['device_limit_message'] ) ? $options['device_limit_message'] : __( 'You have reached the maximum number of allowed devices.', 'block-content-protection' );
        return new WP_Error( 'device_limit_exceeded', $message );
    }

    return $user;
}
add_filter( 'authenticate', 'bcp_validate_device_limit', 30, 3 );

/**
 * Adds the device management section to the user profile page.
 *
 * @param WP_User $user The current user object.
 */
function bcp_show_device_management_section( $user ) {
    $options = get_option( 'bcp_options', [] );
    if ( empty( $options['enable_device_limit'] ) ) {
        return;
    }

    $active_devices = get_user_meta( $user->ID, 'bcp_active_devices', true );
    if ( ! is_array( $active_devices ) ) {
        $active_devices = [];
    }

    $current_device_id = bcp_get_device_id();
    ?>
    <div class="bcp-device-management">
        <h3><?php _e( 'Manage Active Devices', 'block-content-protection' ); ?></h3>
        <p><?php _e( 'Here you can see the list of devices you are currently logged in with. You can remove devices you no longer use.', 'block-content-protection' ); ?></p>
        <table class="form-table">
            <tr>
                <th><label><?php _e( 'Your Devices', 'block-content-protection' ); ?></label></th>
                <td>
                    <?php if ( ! empty( $active_devices ) ) : ?>
                        <ul class="bcp-device-list">
                            <?php foreach ( $active_devices as $device_id ) : ?>
                                <li>
                                    <span class="device-id"><?php echo esc_html( $device_id ); ?></span>
                                    <?php if ( $device_id === $current_device_id ) : ?>
                                        <span class="current-device-label">(<?php _e( 'Current Device', 'block-content-protection' ); ?>)</span>
                                    <?php else : ?>
                                        <button type="submit" name="bcp_remove_device" value="<?php echo esc_attr( $device_id ); ?>" class="button button-secondary"><?php _e( 'Remove', 'block-content-protection' ); ?></button>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p><?php _e( 'No active devices found.', 'block-content-protection' ); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php wp_nonce_field( 'bcp_remove_device_action', 'bcp_remove_device_nonce' ); ?>
    </div>
    <?php
}
add_action( 'show_user_profile', 'bcp_show_device_management_section' );
add_action( 'edit_user_profile', 'bcp_show_device_management_section' );

/**
 * Handles the removal of a device from the user's active devices list.
 *
 * @param int $user_id The ID of the user being updated.
 */
function bcp_handle_remove_device( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return;
    }

    if ( isset( $_POST['bcp_remove_device'] ) ) {
        check_admin_referer( 'bcp_remove_device_action', 'bcp_remove_device_nonce' );

        $device_to_remove = sanitize_text_field( $_POST['bcp_remove_device'] );
        $active_devices = get_user_meta( $user_id, 'bcp_active_devices', true );

        if ( is_array( $active_devices ) ) {
            $new_devices = array_values( array_diff( $active_devices, [ $device_to_remove ] ) );
            update_user_meta( $user_id, 'bcp_active_devices', $new_devices );
        }

        // Redirect to avoid form resubmission
        wp_redirect( get_edit_user_link( $user_id ) );
        exit;
    }
}
add_action( 'personal_options_update', 'bcp_handle_remove_device' );
add_action( 'edit_user_profile_update', 'bcp_handle_remove_device' );

function bcp_enqueue_admin_scripts( $hook ) {
    // Only load on our plugin's settings page
    if ( 'toplevel_page_block_content_protection' !== $hook ) {
        return;
    }

    // Enqueue Admin CSS
    wp_enqueue_style(
        'bcp-admin-styles',
        BCP_PLUGIN_URL . 'admin/css/admin-styles.css',
        [],
        '1.5.8'
    );

    // Enqueue Admin JS
    wp_enqueue_script(
        'bcp-admin-scripts',
        BCP_PLUGIN_URL . 'admin/js/admin-scripts.js',
        [],
        '1.5.8',
        true
    );
}
add_action( 'admin_enqueue_scripts', 'bcp_enqueue_admin_scripts' );

function bcp_activation() {
    $defaults = [
        'disable_right_click'       => 1,
        'disable_devtools'          => 1,
        'disable_copy'              => 1,
        'disable_text_selection'    => 1,
        'disable_image_drag'        => 1,
        'disable_video_download'    => 1,
        'disable_screenshot'        => 1,
        'enhanced_protection'       => 0,
        'mobile_screenshot_block'   => 1,
        'video_screen_record_block' => 1,
        'whitelisted_ips'           => '',
        'excluded_pages'            => '',
        'screenshot_alert_message'  => 'Screenshots are disabled on this site.',
        'recording_alert_message'   => 'Screen recording detected. Video playback blocked.',
        'enable_custom_messages'    => 0,
        'watermark_text'            => '',
        'enable_video_watermark'    => 0,
        //'enable_page_watermark'     => 0,
        'watermark_opacity'         => 0.5,
        'watermark_position'        => 'animated',
        'watermark_style'           => 'text',
        'enable_device_limit'       => 0,
        'device_limit_number'       => 3,
        'device_limit_message'      => 'You have reached the maximum number of allowed devices.',
    ];
    if ( false === get_option( 'bcp_options' ) ) {
        update_option( 'bcp_options', $defaults );
    }
}
register_activation_hook( __FILE__, 'bcp_activation' );
