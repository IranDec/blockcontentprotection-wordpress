<?php
/**
 * Plugin Name:       Block Content Protection
 * Description:       A comprehensive plugin to protect website content. Blocks screenshots, screen recording, right-click, developer tools, and more.
 * Plugin URI:        https://adschi.com/
 * Version:           1.7.1
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

/**
 * Handles generating and validating expiring links for media files.
 */
function bcp_handle_media_request() {
    // Check if the request is for a secure media link
    if ( ! isset( $_GET['bcp_media_token'] ) || ! isset( $_GET['bcp_media_src'] ) ) {
        return;
    }

    // Block download managers
    $options = get_option( 'bcp_options', [] );
    if ( ! empty( $options['disable_media_download'] ) && isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
        $user_agent = strtolower( $_SERVER['HTTP_USER_AGENT'] );
        $blocked_agents = [ 'idm', 'internet download manager', 'flashget' ];
        foreach ( $blocked_agents as $agent ) {
            if ( strpos( $user_agent, $agent ) !== false ) {
                wp_die( 'Access to this media file is restricted.', 'Forbidden', [ 'response' => 403 ] );
            }
        }
    }

    $token = sanitize_text_field( $_GET['bcp_media_token'] );
    $encoded_src = sanitize_text_field( $_GET['bcp_media_src'] );
    $expires = isset( $_GET['bcp_expires'] ) ? intval( $_GET['bcp_expires'] ) : 0;

    // --- Device Limit Check ---
    if ( ! empty( $options['enable_device_limit'] ) ) {
        if ( ! is_user_logged_in() ) {
            wp_die( 'You must be logged in to view this content.', 'Unauthorized', [ 'response' => 401 ] );
        }

        $device_id = bcp_get_device_id();
        if ( empty( $device_id ) ) {
            wp_die( 'Invalid device ID.', 'Bad Request', [ 'response' => 400 ] );
        }

        $user_id = get_current_user_id();
        $active_devices = get_user_meta( $user_id, 'bcp_active_devices', true );
        if ( ! is_array( $active_devices ) ) {
            $active_devices = [];
        }

        $device_found = false;
        foreach ( $active_devices as $device ) {
            if ( isset( $device['id'] ) && $device['id'] === $device_id ) {
                $device_found = true;
                break;
            }
        }

        if ( ! $device_found ) {
            // This device is not on the approved list for this user.
            wp_die( 'This device is not authorized to view this content.', 'Forbidden', [ 'response' => 403 ] );
        }
    }

    // Validate the token
    $ip_address = isset( $_GET['bcp_ip'] ) ? sanitize_text_field( $_GET['bcp_ip'] ) : '';
    if ( ! bcp_validate_media_token( $encoded_src, $expires, $token, $ip_address ) ) {
        wp_die( 'Invalid or expired media link.', 'Forbidden', [ 'response' => 403 ] );
    }

    $media_url = rawurldecode( $encoded_src );

    // Serve the file
    $upload_dir = wp_upload_dir();
    if ( strpos( $media_url, $upload_dir['baseurl'] ) !== false ) {
        // Local file
        $file_path = ltrim( str_replace( $upload_dir['baseurl'], '', $media_url ), '/' );
        $full_file_path = realpath( $upload_dir['basedir'] . '/' . $file_path );

        if ( ! $full_file_path || strpos( $full_file_path, realpath( $upload_dir['basedir'] ) ) !== 0 ) {
            wp_die( 'Invalid file path.', 'Bad Request', [ 'response' => 400 ] );
        }

        if ( file_exists( $full_file_path ) ) {
            bcp_stream_media( $full_file_path );
        }
    }
    // Note: Streaming/proxying external files with byte-range support is complex
    // and has been removed for this implementation to focus on local files.

    wp_die( 'File not found.', 'Not Found', [ 'response' => 404 ] );
}
add_action( 'init', 'bcp_handle_media_request' );

/**
 * Streams a local media file with support for byte-range requests.
 */
function bcp_stream_media( $file_path ) {
    $file_size = filesize( $file_path );
    $mime_type = wp_check_filetype( $file_path )['type'];

    header( 'Content-Type: ' . $mime_type );
    header( 'Accept-Ranges: bytes' );
    header( 'Content-Disposition: inline; filename="' . basename( $file_path ) . '"' );

    $range = isset( $_SERVER['HTTP_RANGE'] ) ? $_SERVER['HTTP_RANGE'] : null;

    if ( $range ) {
        list( $start, $end ) = explode( '=', $range );
        if ( strpos( $start, 'bytes' ) !== false ) {
            list( $start, $end ) = explode( '-', substr( $start, 6 ) );
        }

        $start = intval( $start );
        $end = ( $end === '' ) ? ( $file_size - 1 ) : intval( $end );

        header( 'HTTP/1.1 206 Partial Content' );
        header( 'Content-Length: ' . ( $end - $start + 1 ) );
        header( "Content-Range: bytes $start-$end/$file_size" );

        $handle = fopen( $file_path, 'rb' );
        fseek( $handle, $start );

        $buffer = 1024 * 8;
        while ( ! feof( $handle ) && ( $pos = ftell( $handle ) ) <= $end ) {
            if ( $pos + $buffer > $end ) {
                $buffer = $end - $pos + 1;
            }
            echo fread( $handle, $buffer );
            flush();
        }
        fclose( $handle );
    } else {
        header( 'Content-Length: ' . $file_size );
        readfile( $file_path );
    }
    exit;
}

/**
 * Generates a secure token for a media link.
 */
function bcp_generate_media_token( $file_path, $expires, $ip_address = '' ) {
    $options = get_option( 'bcp_options', [] );
    $secret_key = defined( 'NONCE_KEY' ) ? NONCE_KEY : get_site_option( 'secret_key' );
    $hash_data = $file_path . '|' . $expires . '|' . $secret_key;
    if ( ! empty( $options['enable_ip_binding'] ) && ! empty( $ip_address ) ) {
        $hash_data .= '|' . $ip_address;
    }
    return hash( 'sha256', $hash_data );
}


/**
 * Validates a secure media token.
 */
function bcp_validate_media_token( $file_path, $expires, $token, $ip_address = '' ) {
    $options = get_option( 'bcp_options', [] );
    if ( time() > $expires ) {
        return false; // Link has expired
    }

    $current_ip = bcp_get_user_ip();

    // Check if the IP address matches, but only if the setting is enabled
    if ( ! empty( $options['enable_ip_binding'] ) ) {
        if ( $current_ip !== $ip_address ) {
            return false;
        }
        // Regenerate the token with the IP for validation
        $expected_token = bcp_generate_media_token( $file_path, $expires, $current_ip );
    } else {
        // Regenerate the token without the IP for validation
        $expected_token = bcp_generate_media_token( $file_path, $expires );
    }


    return hash_equals( $expected_token, $token );
}

/**
 * Generates a secure, expiring URL for a given media source.
 */
function bcp_get_media_url( $src ) {
    $options = get_option( 'bcp_options', [] );
    if ( empty( $src ) || empty( $options['enable_expiring_links'] ) ) {
        return $src;
    }

    // Don't re-protect an already protected URL
    if ( strpos( $src, 'bcp_media_token=' ) !== false ) {
        return $src;
    }

    $duration = ! empty( $options['expiring_links_duration'] ) ? intval( $options['expiring_links_duration'] ) : 3600;
    $expires = time() + $duration;
    $encoded_src = rawurlencode( $src );

    $args = [
        'bcp_media_src' => $encoded_src,
        'bcp_expires'   => $expires,
    ];

    $ip_address = '';
    if ( ! empty( $options['enable_ip_binding'] ) ) {
        $ip_address = bcp_get_user_ip();
        $args['bcp_ip'] = $ip_address;
    }
    $token = bcp_generate_media_token( $encoded_src, $expires, $ip_address );
    $args['bcp_media_token'] = $token;

    if ( ! empty( $options['enable_device_limit'] ) ) {
        $args['bcp_device_id'] = bcp_get_device_id();
    }

    return add_query_arg( $args, home_url( '/' ) );
}

/**
 * Automatically protects media in post content by replacing their URLs.
 */
function bcp_protect_media_in_content( $content ) {
    $options = get_option( 'bcp_options', [] );

    // Only run if the automatic protection is enabled
    if ( empty( $options['enable_expiring_links'] ) || empty( $options['enable_automatic_protection'] ) ) {
        return $content;
    }

    // Regex to find <video> and <audio> tags and their src attributes
    $pattern = '/<(video|audio)([^>]*)src=["\']([^"\']+)["\']([^>]*)>/i';

    return preg_replace_callback( $pattern, function( $matches ) {
        $tag = $matches[1]; // video or audio
        $pre_src_attrs = $matches[2];
        $original_src = $matches[3];
        $post_src_attrs = $matches[4];

        // Generate the secure URL
        $secure_url = bcp_get_media_url( $original_src );

        // Reconstruct the tag with the new URL
        return "<{$tag}{$pre_src_attrs}src=\"" . esc_url( $secure_url ) . "\"{$post_src_attrs}>";
    }, $content );
}
add_filter( 'the_content', 'bcp_protect_media_in_content', 99 ); // High priority

/**
 * Shortcode to embed protected media.
 */
function bcp_media_shortcode( $atts ) {
    $atts = shortcode_atts( [ 'src' => '' ], $atts, 'bcp_media' );
    $src = $atts['src'];

    if ( empty( $src ) ) {
        return '';
    }

    $secure_url = bcp_get_media_url( $src );
    $filetype = wp_check_filetype( $src );
    $tag_type = '';

    if ( strpos( $filetype['type'], 'video' ) !== false ) {
        $tag_type = 'video';
    } elseif ( strpos( $filetype['type'], 'audio' ) !== false ) {
        $tag_type = 'audio';
    }

    if ( $tag_type ) {
        // If expiring links are disabled, bcp_get_media_url returns original src
        return "<{$tag_type} controls src=\"" . esc_url( $secure_url ) . "\"></{$tag_type}>";
    }

    return '';
}
add_shortcode( 'bcp_media', 'bcp_media_shortcode' );

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
        'disable_media_download'    => __( 'Block Media Download (IDM, etc.)', 'block-content-protection' ),
        'disable_screenshot'        => __( 'Disable Screenshot Shortcuts', 'block-content-protection' ),
        'enhanced_protection'       => __( 'Enhanced Screen Protection', 'block-content-protection' ),
        'mobile_screenshot_block'   => __( 'Block Mobile Screenshots', 'block-content-protection' ),
        'video_screen_record_block' => __( 'Block Video Screen Recording', 'block-content-protection' ),
    ];
    foreach ($protection_fields as $id => $label) {
        $desc = '';
         if ($id === 'disable_media_download') {
            $desc = __( 'Protects video and audio files. Uses advanced techniques like Blob URLs and User-Agent blocking to prevent downloads from managers like IDM.', 'block-content-protection' );
        }
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
    add_settings_field( 'watermark_text', __( 'Watermark Text', 'block-content-protection' ), 'bcp_render_textfield_field', 'block_content_protection', 'bcp_watermark_section', [ 'id' => 'watermark_text', 'description' => __( 'Enter text for the watermark. Use placeholders: {user_login}, {user_email}, {user_mobile}, {ip_address}, {date}.', 'block-content-protection' ) ] );

    // Expiring Links Section
    add_settings_section( 'bcp_expiring_links_section', __( 'Expiring Media Links', 'block-content-protection' ), null, 'block_content_protection' );
    add_settings_field( 'enable_expiring_links', __( 'Enable Expiring Links', 'block-content-protection' ), 'bcp_render_checkbox_field', 'block_content_protection', 'bcp_expiring_links_section', [ 'id' => 'enable_expiring_links', 'description' => __( 'Enable to generate secure, time-sensitive links for media files.', 'block-content-protection' ) ] );
    add_settings_field( 'enable_automatic_protection', __( 'Enable Automatic Protection', 'block-content-protection' ), 'bcp_render_checkbox_field', 'block_content_protection', 'bcp_expiring_links_section', [ 'id' => 'enable_automatic_protection', 'description' => __( 'Automatically protect all video and audio tags in your content. If disabled, you must use the [bcp_media] shortcode.', 'block-content-protection' ) ] );
    add_settings_field( 'expiring_links_duration', __( 'Link Expiration Time (seconds)', 'block-content-protection' ), 'bcp_render_number_field', 'block_content_protection', 'bcp_expiring_links_section', [ 'id' => 'expiring_links_duration', 'description' => __( 'Set how long the media links should be valid. Default: 3600 seconds (1 hour).', 'block-content-protection' ), 'min' => 60, 'step' => 60 ] );
    add_settings_field( 'enable_ip_binding', __( 'Bind Secure Links to User IP', 'block-content-protection' ), 'bcp_render_checkbox_field', 'block_content_protection', 'bcp_expiring_links_section', [ 'id' => 'enable_ip_binding', 'description' => __( 'For the highest security, bind the expiring link to the user\'s IP address. Prevents sharing links but may cause issues for users with dynamic IPs.', 'block-content-protection' ) ] );
    add_settings_field( 'watermark_opacity', __( 'Watermark Opacity', 'block-content-protection' ), 'bcp_render_number_field', 'block_content_protection', 'bcp_watermark_section', [ 'id' => 'watermark_opacity', 'description' => __( 'Set the opacity from 0 (transparent) to 1 (opaque). Default: 0.5', 'block-content-protection' ), 'min' => 0, 'max' => 1, 'step' => '0.1' ] );
    add_settings_field( 'watermark_animated', __( 'Enable Watermark Animation', 'block-content-protection' ), 'bcp_render_checkbox_field', 'block_content_protection', 'bcp_watermark_section', [ 'id' => 'watermark_animated', 'description' => __( 'Enable to make the watermark move across the video.', 'block-content-protection' ) ] );
    add_settings_field( 'watermark_position', __( 'Watermark Position', 'block-content-protection' ), 'bcp_render_select_field', 'block_content_protection', 'bcp_watermark_section', [ 'id' => 'watermark_position', 'description' => __( 'Select the watermark position (only applies if animation is disabled).', 'block-content-protection' ), 'options' => [ 'top_left' => 'Top Left', 'top_right' => 'Top Right', 'bottom_left' => 'Bottom Left', 'bottom_right' => 'Bottom Right', ] ] );
    add_settings_field( 'watermark_style', __( 'Watermark Style', 'block-content-protection' ), 'bcp_render_select_field', 'block_content_protection', 'bcp_watermark_section', [ 'id' => 'watermark_style', 'description' => __( 'Select the watermark style.', 'block-content-protection' ), 'options' => [ 'text' => 'Simple Text', 'pattern' => 'Pattern' ] ] );

    // Device Limit Section
    add_settings_section( 'bcp_device_limit_section', __( 'Device Limit Settings', 'block-content-protection' ), null, 'block_content_protection' );
    add_settings_field( 'enable_device_limit', __( 'Enable Device Limit', 'block-content-protection' ), 'bcp_render_checkbox_field', 'block_content_protection', 'bcp_device_limit_section', [ 'id' => 'enable_device_limit', 'description' => __( 'Enable to limit the number of devices per user.', 'block-content-protection' ) ] );
    add_settings_field( 'enable_single_session', __( 'Limit Active Sessions', 'block-content-protection' ), 'bcp_render_checkbox_field', 'block_content_protection', 'bcp_device_limit_section', [ 'id' => 'enable_single_session', 'description' => __( 'Only allow one active session at a time (logs out other sessions upon login).', 'block-content-protection' ) ] );
    add_settings_field( 'device_limit_number', __( 'Number of Devices Allowed', 'block-content-protection' ), 'bcp_render_number_field', 'block_content_protection', 'bcp_device_limit_section', [ 'id' => 'device_limit_number', 'description' => __( 'Set the maximum number of devices a user can log in with. Default: 3', 'block-content-protection' ), 'min' => 1, 'step' => 1 ] );
    add_settings_field( 'device_limit_message', __( 'Device Limit Reached Message', 'block-content-protection' ), 'bcp_render_textfield_field', 'block_content_protection', 'bcp_device_limit_section', [ 'id' => 'device_limit_message', 'description' => __( 'The message shown to the user when they have reached their device limit.', 'block-content-protection' ) ] );
    add_settings_field( 'watermark_count', __( 'Watermark Count', 'block-content-protection' ), 'bcp_render_number_field', 'block_content_protection', 'bcp_watermark_section', [ 'id' => 'watermark_count', 'description' => __( 'Number of watermarks to display (for pattern style). Default: 30', 'block-content-protection' ), 'min' => 1, 'max' => 100, 'step' => 1 ] );

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
        'disable_text_selection', 'disable_image_drag', 'disable_media_download',
        'disable_screenshot', 'enhanced_protection', 'mobile_screenshot_block',
        'video_screen_record_block', 'enable_video_watermark',
        'enable_custom_messages', 'watermark_animated', 'enable_expiring_links',
        'enable_automatic_protection', 'enable_device_limit', 'enable_ip_binding',
        'enable_single_session'
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
    if ( isset( $input['device_limit_message'] ) ) {
        $new_options['device_limit_message'] = sanitize_text_field( $input['device_limit_message'] );
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
    if ( isset( $input['watermark_count'] ) ) {
        $new_options['watermark_count'] = intval( $input['watermark_count'] );
    }
    if ( isset( $input['expiring_links_duration'] ) ) {
        $new_options['expiring_links_duration'] = intval( $input['expiring_links_duration'] );
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

                    <!-- Expiring Links Settings Card -->
                    <div class="bcp-card">
                        <h2 class="bcp-card-header"><?php _e( 'Expiring Media Links', 'block-content-protection' ); ?></h2>
                        <div class="bcp-card-body">
                            <table class="form-table">
                                <?php do_settings_fields( 'block_content_protection', 'bcp_expiring_links_section' ); ?>
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

    if ( $is_protection_enabled || ! empty( $options['enable_video_watermark'] ) ) {
        // Replace watermark placeholders
        if ( ! empty( $options['enable_video_watermark'] ) && ! empty( $options['watermark_text'] ) ) {
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
        wp_enqueue_script( 'bcp-protect-module', BCP_PLUGIN_URL . 'js/protect.module.js', [], '1.7.1', true );

        // Create a data bridge for the module
        add_action('wp_footer', function() use ($options) {
            echo '<script type="application/json" id="bcp-settings-data">' . wp_json_encode($options) . '</script>';
        }, 99);


        // Enqueue styles if needed
        if ( ! empty( $options['enhanced_protection'] ) || ! empty( $options['video_screen_record_block'] ) || ! empty( $options['enable_video_watermark'] ) ) {
            wp_enqueue_style( 'bcp-protect-css', BCP_PLUGIN_URL . 'css/protect.css', [], '1.7.1' );
        }
    }
}
add_action( 'wp_enqueue_scripts', 'bcp_enqueue_scripts' );

function bcp_add_module_to_script( $tag, $handle, $src ) {
    if ( 'bcp-protect-module' === $handle ) {
        $tag = '<script type="module" src="' . esc_url( $src ) . '" id="' . esc_attr( $handle ) . '-js" defer></script>';
    }
    return $tag;
}
add_filter( 'script_loader_tag', 'bcp_add_module_to_script', 10, 3 );

/**
 * Generates a unique device ID based on browser properties.
 *
 * @return string The generated device ID.
 */
function bcp_get_device_id() {
    // A more robust device fingerprint using User-Agent and a part of the IP.
    $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
    // Using a substring of the IP allows for some network changes without creating a new ID.
    $ip_address = bcp_get_user_ip();
    $ip_substring = substr( $ip_address, 0, strrpos( $ip_address, '.' ) );

    // Create a hash from these components for a more unique ID.
    return 'bcp-' . hash( 'sha256', $user_agent . $ip_substring );
}

/**
 * Tracks the user's device upon login.
 *
 * @param string  $user_login The user's login name.
 * @param WP_User $user       The logged-in user object.
 * @param string  $token      Optional. The session token if available (e.g. from cookie hook).
 */
function bcp_track_user_device( $user_login, $user = null, $token = '' ) {
    static $tracked_requests = [];

    // Ensure we have a user object
    if ( ! $user && $user_login instanceof WP_User ) {
        $user = $user_login;
    } elseif ( ! $user ) {
        $user = get_user_by( 'login', $user_login );
    }

    if ( ! $user ) {
        return;
    }

    $user_id = $user->ID;

    // Prevent double tracking in the same request
    if ( isset( $tracked_requests[$user_id] ) ) {
        return;
    }
    $tracked_requests[$user_id] = true;

    $options = get_option( 'bcp_options', [] );
    if ( empty( $options['enable_device_limit'] ) ) {
        return;
    }

    $device_id = bcp_get_device_id();

    if ( ! $device_id ) {
        return;
    }

    $active_devices = get_user_meta( $user_id, 'bcp_active_devices', true );
    if ( ! is_array( $active_devices ) ) {
        $active_devices = [];
    }

    $device_exists = false;
    foreach ( $active_devices as &$device ) {
        if ( isset($device['id']) && $device['id'] === $device_id ) {
            $device['last_login'] = time();
            $device['ip_address'] = bcp_get_user_ip();
            $device_exists = true;
            break;
        }
    }

    // Limit enforcement logic
    $limit = ! empty( $options['device_limit_number'] ) ? intval( $options['device_limit_number'] ) : 3;

    if ( ! $device_exists ) {
        // If we are about to add a new device, check the limit
        if ( count( $active_devices ) >= $limit ) {
            // Auto-rotate: Remove the oldest device to make room
            usort($active_devices, function($a, $b) {
                return ($a['last_login'] ?? 0) <=> ($b['last_login'] ?? 0);
            });
            array_shift($active_devices); // Remove first (oldest)
        }

        $active_devices[] = [
            'id'         => $device_id,
            'last_login' => time(),
            'ip_address' => bcp_get_user_ip(),
            'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '',
        ];
    }
    update_user_meta( $user_id, 'bcp_active_devices', $active_devices );

    // Single Session Enforcement
    if ( ! empty( $options['enable_single_session'] ) ) {
        if ( ! empty( $token ) ) {
            // If we have the token (from set_auth_cookie), we can destroy others directly
            $manager = WP_Session_Tokens::get_instance( $user_id );
            $manager->destroy_others( $token );
        } else {
            // Fallback for standard login where cookie might be set later
            // or if we are in a context where wp_get_session_token() works
            $current_token = wp_get_session_token();
            if ( $current_token ) {
                $manager = WP_Session_Tokens::get_instance( $user_id );
                $manager->destroy_others( $current_token );
            }
        }
    }
}
add_action( 'wp_login', 'bcp_track_user_device', 10, 2 );

/**
 * Tracks the user's device when auth cookie is set (e.g., via Digits).
 */
function bcp_track_user_device_cookie( $auth_cookie, $expire, $expiration, $user_id, $scheme ) {
    $user = get_user_by( 'id', $user_id );
    if ( $user ) {
        // Extract the token from the cookie value
        $cookie_elements = wp_parse_auth_cookie( $auth_cookie, $scheme );
        $token = isset( $cookie_elements['token'] ) ? $cookie_elements['token'] : '';

        bcp_track_user_device( $user->user_login, $user, $token );
    }
}
add_action( 'set_auth_cookie', 'bcp_track_user_device_cookie', 10, 5 );

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
        $new_devices = [];
        foreach ( $active_devices as $device ) {
            if ( $device['id'] !== $device_id ) {
                $new_devices[] = $device;
            }
        }
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

    $active_devices = get_user_meta( $user_id, 'bcp_active_devices', true );
    if ( ! is_array( $active_devices ) ) {
        $active_devices = [];
    }

    $device_found = false;
    foreach( $active_devices as $device ) {
        if ( isset( $device['id'] ) && $device['id'] === $device_id ) {
            $device_found = true;
            break;
        }
    }

    $limit = ! empty( $options['device_limit_number'] ) ? intval( $options['device_limit_number'] ) : 3;

    if ( ! $device_found && count( $active_devices ) >= $limit ) {
        // Device limit exceeded, prepare for redirection
        $new_device_info = [
            'id'         => $device_id,
            'last_login' => time(),
            'ip_address' => bcp_get_user_ip(),
            'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '',
        ];

        // Store pending login info for 5 minutes
        set_transient( 'bcp_pending_login_' . $user_id, $new_device_info, 5 * MINUTE_IN_SECONDS );

        // Redirect to the management page
        $redirect_url = add_query_arg( [
            'bcp-manage-devices' => '1',
            'user_id' => $user_id,
            'nonce' => wp_create_nonce('bcp-device-management-' . $user_id)
        ], home_url('/') );

        wp_redirect( $redirect_url );
        // Add a fallback for browsers that don't follow redirects
        echo '<p>You have too many devices logged in. <a href="' . esc_url($redirect_url) . '">Please click here to manage your devices.</a></p>';
        exit;
    }

    return $user;
}
add_filter( 'authenticate', 'bcp_validate_device_limit', 30, 3 );

/**
 * Handles the device management page for when a user exceeds their device limit.
 */
function bcp_device_management_page_handler() {
    if ( ! isset( $_GET['bcp-manage-devices'], $_GET['user_id'], $_GET['nonce'] ) ) {
        return;
    }

    $user_id = intval( $_GET['user_id'] );
    $nonce = sanitize_text_field( $_GET['nonce'] );

    if ( ! $user_id || ! wp_verify_nonce( $nonce, 'bcp-device-management-' . $user_id ) ) {
        wp_die( 'Invalid request.', 'Error', [ 'response' => 403 ] );
    }

    $user = get_user_by( 'id', $user_id );
    if ( ! $user ) {
        wp_die( 'Invalid user.', 'Error' );
    }

    // Handle form submission
    if ( isset( $_POST['bcp_device_action_nonce'] ) && wp_verify_nonce( $_POST['bcp_device_action_nonce'], 'bcp_device_action_' . $user_id ) ) {
        $pending_device = get_transient( 'bcp_pending_login_' . $user_id );
        if ( ! $pending_device ) {
            wp_die( 'Your session has expired. Please try logging in again.', 'Error' );
        }

        $active_devices = get_user_meta( $user_id, 'bcp_active_devices', true );
        if ( ! is_array( $active_devices ) ) {
            $active_devices = [];
        }

        if ( isset( $_POST['bcp_remove_oldest'] ) ) {
            // Sort to find the oldest device
            usort($active_devices, function($a, $b) {
                return ($a['last_login'] ?? 0) <=> ($b['last_login'] ?? 0);
            });
            // Remove the oldest device
            array_shift($active_devices);
        } elseif ( isset( $_POST['bcp_remove_selected'] ) && ! empty( $_POST['device_to_remove'] ) ) {
            $device_to_remove = sanitize_text_field( $_POST['device_to_remove'] );
            $active_devices = array_filter( $active_devices, function( $device ) use ( $device_to_remove ) {
                return $device['id'] !== $device_to_remove;
            } );
        }

        // Add the new device
        $active_devices[] = $pending_device;
        update_user_meta( $user_id, 'bcp_active_devices', array_values( $active_devices ) );
        delete_transient( 'bcp_pending_login_' . $user_id );

        // Log the user in and redirect
        wp_clear_auth_cookie();
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id, true );
        wp_redirect( admin_url() );
        exit;
    }

    // Display the management page
    $active_devices = get_user_meta( $user_id, 'bcp_active_devices', true );
    if ( ! is_array( $active_devices ) ) {
        $active_devices = [];
    }
    // Sort by last login for display (newest first)
    usort($active_devices, function($a, $b) {
        return ($b['last_login'] ?? 0) <=> ($a['last_login'] ?? 0);
    });
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta charset="<?php bloginfo( 'charset' ); ?>">
        <title><?php _e( 'Manage Devices', 'block-content-protection' ); ?></title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; background: #f0f0f1; color: #444; line-height: 1.5; margin: 0; padding: 20px; }
            .bcp-manage-container { max-width: 600px; margin: 5% auto; background: #fff; padding: 20px 40px; box-shadow: 0 1px 3px rgba(0,0,0,0.13); border-radius: 4px; }
            h1 { text-align: center; color: #2271b1; font-size: 24px; margin-bottom: 10px; }
            p { font-size: 14px; text-align: center; color: #555; }
            .bcp-device-list { list-style: none; padding: 0; margin-top: 20px; }
            .bcp-device-list li { border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; display: flex; align-items: center; border-radius: 3px; }
            .bcp-device-info { flex-grow: 1; }
            .bcp-device-info input[type="radio"] { margin-right: 15px; }
            .bcp-device-info label { display: flex; align-items: center; cursor: pointer; }
            .bcp-device-details { display: flex; flex-direction: column; }
            .bcp-device-name { font-weight: 600; color: #333; }
            .bcp-device-meta { font-size: 12px; color: #666; }
            .bcp-actions { margin-top: 30px; }
            .bcp-button { display: block; box-sizing: border-box; width: 100%; text-align: center; background: #2271b1; color: #fff; border: 0; padding: 12px; font-size: 14px; cursor: pointer; text-decoration: none; margin-bottom: 10px; border-radius: 3px; }
            .bcp-button.secondary { background: #d63638; }
            .bcp-button:hover { opacity: 0.9; }
        </style>
        <?php do_action( 'wp_head' ); ?>
    </head>
    <body class="bcp-device-management-page">
        <div class="bcp-manage-container">
            <h1><?php _e( 'Device Limit Reached', 'block-content-protection' ); ?></h1>
            <p><?php echo esc_html( get_option('bcp_options')['device_limit_message'] ?: __('You have reached the maximum number of allowed devices.', 'block-content-protection') ); ?></p>
            <p><?php _e( 'To log in, please log out from one of your other devices.', 'block-content-protection' ); ?></p>

            <form method="post">
                <ul class="bcp-device-list">
                    <?php foreach ( $active_devices as $index => $device ) : ?>
                        <li>
                            <div class="bcp-device-info">
                               <label>
                                    <input type="radio" name="device_to_remove" value="<?php echo esc_attr( $device['id'] ); ?>" <?php if ($index === 0) echo 'checked'; ?>>
                                    <div class="bcp-device-details">
                                        <span class="bcp-device-name"><?php echo esc_html( bcp_parse_user_agent( $device['user_agent'] ?? '' ) ); ?></span>
                                        <span class="bcp-device-meta">
                                            <?php printf( 'Last seen: %s &mdash; IP: %s', esc_html( wp_date( get_option('date_format') . ' ' . get_option('time_format'), $device['last_login'] ?? time() ) ), esc_html( $device['ip_address'] ?? '' ) ); ?>
                                        </span>
                                    </div>
                                </label>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="bcp-actions">
                    <button type="submit" name="bcp_remove_selected" class="bcp-button secondary"><?php _e( 'Log Out Selected Device and Continue', 'block-content-protection' ); ?></button>
                    <button type="submit" name="bcp_remove_oldest" class="bcp-button"><?php _e( 'Automatically Log Out Oldest Device and Continue', 'block-content-protection' ); ?></button>
                </div>

                <input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>">
                <?php wp_nonce_field( 'bcp_device_action_' . $user_id, 'bcp_device_action_nonce' ); ?>
            </form>
        </div>
        <?php do_action( 'wp_footer' ); ?>
    </body>
    </html>
    <?php
    exit;
}
add_action( 'init', 'bcp_device_management_page_handler' );

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

    // Sort devices by last login time, newest first.
    if ( ! empty( $active_devices ) ) {
        usort($active_devices, function($a, $b) {
            return ($b['last_login'] ?? 0) <=> ($a['last_login'] ?? 0);
        });
    }

    $current_device_id = bcp_get_device_id();
    ?>
    <div class="bcp-device-management">
        <h3><?php _e( 'Manage Active Devices', 'block-content-protection' ); ?></h3>
        <p><?php _e( 'Here is the list of devices you are logged in with. You can remove devices you no longer use.', 'block-content-protection' ); ?></p>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php _e( 'Device', 'block-content-protection' ); ?></th>
                    <th><?php _e( 'Last Login', 'block-content-protection' ); ?></th>
                    <th><?php _e( 'IP Address', 'block-content-protection' ); ?></th>
                    <th><?php _e( 'Action', 'block-content-protection' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $active_devices ) ) : ?>
                    <?php foreach ( $active_devices as $device ) : ?>
                        <tr>
                            <td>
                                <?php echo esc_html( bcp_parse_user_agent($device['user_agent']) ); ?>
                                <?php if ( $device['id'] === $current_device_id ) : ?>
                                    <strong>(<?php _e( 'Current Device', 'block-content-protection' ); ?>)</strong>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( wp_date( get_option('date_format') . ' ' . get_option('time_format'), $device['last_login'] ) ); ?></td>
                            <td><?php echo esc_html( $device['ip_address'] ); ?></td>
                            <td>
                                <?php if ( $device['id'] !== $current_device_id ) : ?>
                                    <button type="submit" name="bcp_remove_device" value="<?php echo esc_attr( $device['id'] ); ?>" class="button button-secondary"><?php _e( 'Remove', 'block-content-protection' ); ?></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="4"><?php _e( 'No active devices found.', 'block-content-protection' ); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
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

    if ( isset( $_POST['bcp_remove_device'] ) && isset( $_POST['bcp_remove_device_nonce'] ) ) {
        if ( ! wp_verify_nonce( $_POST['bcp_remove_device_nonce'], 'bcp_remove_device_action' ) ) {
             wp_die( 'Nonce verification failed.', 'Error', [ 'response' => 403 ] );
        }

        $device_to_remove = sanitize_text_field( $_POST['bcp_remove_device'] );
        $active_devices = get_user_meta( $user_id, 'bcp_active_devices', true );

        if ( is_array( $active_devices ) ) {
            $new_devices = [];
            foreach( $active_devices as $device ) {
                if ( $device['id'] !== $device_to_remove ) {
                    $new_devices[] = $device;
                }
            }
            update_user_meta( $user_id, 'bcp_active_devices', $new_devices );
        }

        // Redirect to avoid form resubmission
        wp_redirect( get_edit_user_link( $user_id ) );
        exit;
    }
}
add_action( 'personal_options_update', 'bcp_handle_remove_device' );
add_action( 'edit_user_profile_update', 'bcp_handle_remove_device' );

/**
 * Verifies on each page load if the current device is still valid.
 * If not, it terminates the session.
 */
function bcp_verify_current_device_session() {
    $options = get_option( 'bcp_options', [] );

    // Only run if the feature is enabled and a user is logged in.
    if ( empty( $options['enable_device_limit'] ) || ! is_user_logged_in() ) {
        return;
    }

    $user_id = get_current_user_id();
    $device_id = bcp_get_device_id();

    $active_devices = get_user_meta( $user_id, 'bcp_active_devices', true );
    if ( ! is_array( $active_devices ) ) {
        // If there's no device list, something is wrong. For safety, log out.
        wp_logout();
        return;
    }

    $device_found = false;
    foreach ( $active_devices as $device ) {
        if ( isset( $device['id'] ) && $device['id'] === $device_id ) {
            $device_found = true;
            break;
        }
    }

    // If the current device is not in the active list, log the user out.
    if ( ! $device_found ) {
        wp_logout();
        // Redirect to the login page with a message
        wp_redirect( wp_login_url() . '?session_expired=true' );
        exit;
    }
}
add_action( 'init', 'bcp_verify_current_device_session' );

/**
 * Shows a message on the login page if the session was terminated.
 */
function bcp_show_session_expired_message() {
    if ( isset( $_GET['session_expired'] ) && $_GET['session_expired'] == 'true' ) {
        $message = '<p class="message">Your session was terminated because this device was removed from your active devices list.</p>';
        return $message;
    }
}
add_filter( 'login_message', 'bcp_show_session_expired_message' );


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
        '1.7.1'
    );

    // Enqueue Admin JS
    wp_enqueue_script(
        'bcp-admin-scripts',
        BCP_PLUGIN_URL . 'admin/js/admin-scripts.js',
        [],
        '1.7.1',
        true
    );
}
add_action( 'admin_enqueue_scripts', 'bcp_enqueue_admin_scripts' );

/**
 * Parses a user agent string to extract readable browser and OS information.
 * @param string $user_agent_string The user agent string.
 * @return string A formatted string with browser and OS.
 */
function bcp_parse_user_agent( $user_agent_string ) {
    $browser = "Unknown Browser";
    $os = "Unknown OS";

    if (preg_match('/(MSIE|Trident|Firefox|Chrome|Edge|Safari|Opera)/i', $user_agent_string, $matches)) {
        $browser = $matches[1] == 'Trident' ? 'IE' : $matches[1];
    }

    if (preg_match('/(Windows|Macintosh|Linux|Android|iOS)/i', $user_agent_string, $matches)) {
        $os = $matches[1];
    }

    return "$browser on $os";
}

function bcp_activation() {
    $defaults = [
        'disable_right_click'       => 1,
        'disable_devtools'          => 1,
        'disable_copy'              => 1,
        'disable_text_selection'    => 1,
        'disable_image_drag'        => 1,
        'disable_media_download'    => 1,
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
        'enable_expiring_links'     => 0,
        'enable_automatic_protection' => 1,
        'expiring_links_duration'   => 3600,
        'watermark_opacity'         => 0.5,
        'watermark_animated'        => 1,
        'watermark_position'        => 'top_left',
        'watermark_style'           => 'text',
        'enable_device_limit'       => 0,
        'enable_single_session'     => 0,
        'device_limit_number'       => 3,
        'device_limit_message'      => 'You have reached the maximum number of allowed devices.',
        'watermark_count'           => 30,
        'enable_ip_binding'         => 1,
    ];
    if ( false === get_option( 'bcp_options' ) ) {
        update_option( 'bcp_options', $defaults );
    }
}
register_activation_hook( __FILE__, 'bcp_activation' );
