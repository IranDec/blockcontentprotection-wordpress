<?php
/**
 * Plugin Name:       Block Content Protection
 * Description:       A comprehensive plugin to protect website content. Blocks screenshots, screen recording, right-click, developer tools, and more.
 * Plugin URI:        https://adschi.com/
 * Version:           1.5.1
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
            $desc = __( 'When screen recording is detected, videos turn black. Advanced protection.', 'block-content-protection' );
        }
        add_settings_field( $id, $label, 'bcp_render_checkbox_field', 'block_content_protection', 'bcp_protection_section', [ 'id' => $id, 'description' => $desc ] );
    }

    // Exclusions Section
    add_settings_section( 'bcp_exclusions_section', __( 'Exclusion Settings', 'block-content-protection' ), null, 'block_content_protection' );
    add_settings_field( 'whitelisted_ips', __( 'Whitelisted IP Addresses', 'block-content-protection' ), 'bcp_render_textarea_field', 'block_content_protection', 'bcp_exclusions_section', [ 'id' => 'whitelisted_ips', 'description' => __( 'Enter one IP address per line. These IPs will not be affected by the protection.', 'block-content-protection' ) ] );
    add_settings_field( 'excluded_pages', __( 'Excluded Posts/Pages', 'block-content-protection' ), 'bcp_render_textfield_field', 'block_content_protection', 'bcp_exclusions_section', [ 'id' => 'excluded_pages', 'description' => __( 'Enter a comma-separated list of Post or Page IDs to exclude from protection (e.g., 1, 2, 3).', 'block-content-protection' ) ] );

    // Messages Section
    add_settings_section( 'bcp_messages_section', null, null, 'block_content_protection' ); // Section header is in the card
    add_settings_field( 'enable_custom_messages', __( 'Enable Custom Messages', 'block-content-protection' ), 'bcp_render_checkbox_field', 'block_content_protection', 'bcp_messages_section', [ 'id' => 'enable_custom_messages', 'description' => __( 'Enable to override the default browser alerts with your own messages.', 'block-content-protection' ) ] );

    // The actual fields are rendered inside a wrapper for JS toggling
    add_settings_field(
        'custom_messages_fields',
        '', // No label for the wrapper
        'bcp_render_message_fields_wrapper',
        'block_content_protection',
        'bcp_messages_section'
    );

    // Watermark Section
    add_settings_section( 'bcp_watermark_section', null, null, 'block_content_protection' ); // Section header is in the card
    add_settings_field( 'enable_watermark', __( 'Enable Dynamic Watermark', 'block-content-protection' ), 'bcp_render_checkbox_field', 'block_content_protection', 'bcp_watermark_section', [ 'id' => 'enable_watermark', 'description' => __( 'Enable this to show a dynamic watermark over videos.', 'block-content-protection' ) ] );
    add_settings_field( 'watermark_text', __( 'Watermark Text', 'block-content-protection' ), 'bcp_render_textfield_field', 'block_content_protection', 'bcp_watermark_section', [ 'id' => 'watermark_text', 'description' => __( 'Enter text for the watermark. Use placeholders: {user_login}, {user_email}, {user_mobile}, {ip_address}, {date}.', 'block-content-protection' ) ] );
    add_settings_field( 'watermark_opacity', __( 'Watermark Opacity', 'block-content-protection' ), 'bcp_render_number_field', 'block_content_protection', 'bcp_watermark_section', [ 'id' => 'watermark_opacity', 'description' => __( 'Set the opacity from 0 (transparent) to 1 (opaque). Default: 0.5', 'block-content-protection' ), 'min' => 0, 'max' => 1, 'step' => '0.1' ] );
    add_settings_field( 'watermark_position', __( 'Watermark Position', 'block-content-protection' ), 'bcp_render_select_field', 'block_content_protection', 'bcp_watermark_section', [ 'id' => 'watermark_position', 'description' => __( 'Select the watermark position.', 'block-content-protection' ), 'options' => [ 'animated' => 'Animated', 'top_left' => 'Top Left', 'top_right' => 'Top Right', 'bottom_left' => 'Bottom Left', 'bottom_right' => 'Bottom Right', ] ] );
    add_settings_field( 'watermark_style', __( 'Watermark Style', 'block-content-protection' ), 'bcp_render_select_field', 'block_content_protection', 'bcp_watermark_section', [ 'id' => 'watermark_style', 'description' => __( 'Select the watermark style.', 'block-content-protection' ), 'options' => [ 'text' => 'Simple Text', 'pattern' => 'Pattern' ] ] );
}
add_action( 'admin_init', 'bcp_register_settings' );

function bcp_render_message_fields_wrapper() {
    $options = get_option( 'bcp_options' );
    $is_enabled = isset( $options['enable_custom_messages'] ) && $options['enable_custom_messages'];
    $hidden_class = ! $is_enabled ? 'bcp-hidden' : '';

    echo '<tbody id="bcp_messages_section_fields" class="' . esc_attr( $hidden_class ) . '">';

    // Re-creating the fields manually here since they are inside a different structure
    // Screenshot Alert Message
    echo '<tr>';
    echo '<th scope="row">' . esc_html__( 'Screenshot Alert Message', 'block-content-protection' ) . '</th>';
    echo '<td>';
    bcp_render_textfield_field([
        'id' => 'screenshot_alert_message',
        'description' => __( 'The message shown when a user tries to take a screenshot.', 'block-content-protection' )
    ]);
    echo '</td>';
    echo '</tr>';

    // Screen Recording Alert
    echo '<tr>';
    echo '<th scope="row">' . esc_html__( 'Screen Recording Alert', 'block-content-protection' ) . '</th>';
    echo '<td>';
    bcp_render_textfield_field([
        'id' => 'recording_alert_message',
        'description' => __( 'Message shown when screen recording is detected.', 'block-content-protection' )
    ]);
    echo '</td>';
    echo '</tr>';

    echo '</tbody>';
}

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

    $checkboxes = [ 'disable_right_click', 'disable_devtools', 'disable_copy', 'disable_text_selection', 'disable_image_drag', 'disable_video_download', 'disable_screenshot', 'enhanced_protection', 'mobile_screenshot_block', 'video_screen_record_block', 'enable_watermark', 'enable_custom_messages' ];
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
    if ( isset( $input['recording_alert_message'] ) ) {
        $sanitized_input['recording_alert_message'] = sanitize_text_field( $input['recording_alert_message'] );
    }
    if ( isset( $input['watermark_text'] ) ) {
        $sanitized_input['watermark_text'] = sanitize_text_field( $input['watermark_text'] );
    }
    if ( isset( $input['watermark_opacity'] ) ) {
        $sanitized_input['watermark_opacity'] = floatval( $input['watermark_opacity'] );
    }
    if ( isset( $input['watermark_position'] ) ) {
        $sanitized_input['watermark_position'] = sanitize_key( $input['watermark_position'] );
    }
    if ( isset( $input['watermark_style'] ) ) {
        $sanitized_input['watermark_style'] = sanitize_key( $input['watermark_style'] );
    }

    return $sanitized_input;
}

function bcp_options_page() {
    $plugin_data = get_plugin_data( __FILE__ );
    ?>
    <div class="wrap bcp-wrap">
        <div class="bcp-header">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <div class="bcp-author">
                <span><?php printf( __( 'Developed by %s', 'block-content-protection' ), '<a href="' . esc_url( $plugin_data['AuthorURI'] ) . '" target="_blank">' . esc_html( $plugin_data['Author'] ) . '</a>' ); ?></span>
            </div>
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
                printf(
                    __( 'Thank you for using %s! Version %s', 'block-content-protection' ),
                    esc_html( $plugin_data['Name'] ),
                    esc_html( $plugin_data['Version'] )
                );
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

    if ( $is_protection_enabled || ! empty( $options['enable_watermark'] ) ) {
        // Replace watermark placeholders
        if ( ! empty( $options['enable_watermark'] ) && ! empty( $options['watermark_text'] ) ) {
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

        wp_enqueue_script( 'bcp-protect', BCP_PLUGIN_URL . 'js/protect.js', [], '1.5.0', true );
        wp_localize_script( 'bcp-protect', 'bcp_settings', $options );

        if ( ! empty( $options['enhanced_protection'] ) || ! empty( $options['video_screen_record_block'] ) || ! empty( $options['enable_watermark'] ) ) {
            wp_enqueue_style( 'bcp-protect-css', BCP_PLUGIN_URL . 'css/protect.css', [], '1.5.0' );
        }
    }
}
add_action( 'wp_enqueue_scripts', 'bcp_enqueue_scripts' );

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
        '1.5.1'
    );

    // Enqueue Admin JS
    wp_enqueue_script(
        'bcp-admin-scripts',
        BCP_PLUGIN_URL . 'admin/js/admin-scripts.js',
        [],
        '1.5.1',
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
        'enable_watermark'          => 0,
        'watermark_opacity'         => 0.5,
        'watermark_position'        => 'animated',
        'watermark_style'           => 'text',
    ];
    if ( false === get_option( 'bcp_options' ) ) {
        update_option( 'bcp_options', $defaults );
    }
}
register_activation_hook( __FILE__, 'bcp_activation' );
