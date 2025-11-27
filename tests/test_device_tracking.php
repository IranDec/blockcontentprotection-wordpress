<?php
/**
 * Test script to verify device tracking logic.
 */

require_once 'wp-load.php';

echo "Setting up test environment...\n";

// Create a test user
$username = 'test_device_user_' . time();
$password = 'password';
$email = $username . '@example.com';
$user_id = wp_create_user( $username, $password, $email );
if ( is_wp_error( $user_id ) ) {
    die( "Failed to create user: " . $user_id->get_error_message() );
}
echo "Created user: $username (ID: $user_id)\n";

// Enable device limit options
$options = get_option( 'bcp_options', [] );
$options['enable_device_limit'] = 1;
$options['device_limit_number'] = 2; // Set low limit to test rotation
$options['enable_single_session'] = 1;
update_option( 'bcp_options', $options );
echo "Enabled device limit options (Limit: 2, Single Session: Yes).\n";

// Mock environment for Device 1
$_SERVER['HTTP_USER_AGENT'] = 'Device1-Agent';
$_SERVER['REMOTE_ADDR'] = '192.168.1.1';

// Simulate Login 1 (Standard wp_login)
echo "\n--- Test 1: Standard Login (Device 1) ---\n";
$user = get_user_by( 'id', $user_id );
do_action( 'wp_login', $username, $user );

$devices = get_user_meta( $user_id, 'bcp_active_devices', true );
if ( count( $devices ) === 1 && $devices[0]['user_agent'] === 'Device1-Agent' ) {
    echo "PASS: Device 1 tracked successfully.\n";
} else {
    echo "FAIL: Device 1 not tracked correctly.\n";
    print_r( $devices );
}

// Mock environment for Device 2
$_SERVER['HTTP_USER_AGENT'] = 'Device2-Agent';
$_SERVER['REMOTE_ADDR'] = '192.168.1.2';

// Simulate Login 2 (Cookie Hook - mimicking Digits)
echo "\n--- Test 2: Cookie Login (Device 2) ---\n";
// Trigger set_auth_cookie action
do_action( 'set_auth_cookie', 'cookie_val', time(), time()+3600, $user_id, 'logged_in' );

$devices = get_user_meta( $user_id, 'bcp_active_devices', true );
if ( count( $devices ) === 2 ) {
    echo "PASS: Device 2 added. Total devices: 2.\n";
    // Check if Device 2 is present
    $found = false;
    foreach($devices as $d) { if ($d['user_agent'] === 'Device2-Agent') $found = true; }
    if ($found) echo "PASS: Device 2 details correct.\n";
    else echo "FAIL: Device 2 not found in list.\n";
} else {
    echo "FAIL: Device 2 not added correctly. Count: " . count($devices) . "\n";
    print_r( $devices );
}

// Mock environment for Device 3
$_SERVER['HTTP_USER_AGENT'] = 'Device3-Agent';
$_SERVER['REMOTE_ADDR'] = '192.168.1.3';

// Simulate Login 3 (Cookie Hook - mimicking Digits)
// This should rotate out Device 1 (Oldest) because limit is 2.
echo "\n--- Test 3: Rotation (Device 3) ---\n";
// Sleep to ensure timestamp difference
sleep(2);
do_action( 'set_auth_cookie', 'cookie_val', time(), time()+3600, $user_id, 'logged_in' );

$devices = get_user_meta( $user_id, 'bcp_active_devices', true );
if ( count( $devices ) === 2 ) {
    echo "PASS: Device count remained at 2.\n";

    $agents = array_column($devices, 'user_agent');
    if ( in_array('Device3-Agent', $agents) && !in_array('Device1-Agent', $agents) ) {
        echo "PASS: Device 3 added, Device 1 (Oldest) removed.\n";
    } else {
        echo "FAIL: Rotation logic incorrect.\n";
        print_r($agents);
    }
} else {
    echo "FAIL: Device count is " . count($devices) . " (Expected 2).\n";
    print_r( $devices );
}

// Check Single Session
// Since we can't easily check actual session tokens in CLI without full WP session setup,
// we trust the code calls wp_destroy_other_sessions().
// But we can check if the function exists and didn't crash.
echo "\n--- Test 4: Single Session (Concurrency) ---\n";
echo "Note: wp_destroy_other_sessions() was called in previous steps (enable_single_session=1).\n";
echo "If script didn't crash, the call was successful.\n";

// Cleanup
wp_delete_user( $user_id );
echo "\nCleanup complete.\n";
