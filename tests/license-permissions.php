<?php
/** Run with wp eval-file on an explicitly designated disposable site. */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ||
    '1' !== getenv( 'MPAUDIT_DISPOSABLE' ) || 'audit.invalid' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
    throw new RuntimeException( 'This test requires the disposable audit site.' );
}

if ( ! defined( 'MEP_STORE_URL' ) ) {
    define( 'MEP_STORE_URL', 'https://mage-people.com/' );
}
if ( ! defined( 'MEP_GROUP_QTY_ID' ) ) {
    define( 'MEP_GROUP_QTY_ID', 137666 );
}
if ( ! defined( 'MEP_GROUP_QTY_NAME' ) ) {
    define( 'MEP_GROUP_QTY_NAME', 'WooCommerce Event Manager Addon: Group Qty' );
}
require_once dirname( __DIR__ ) . '/license/main.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

class MEP_GROUP_QTY_Test_Die extends RuntimeException {
    public $status;
    public function __construct( $message, $status ) {
        parent::__construct( (string) $message );
        $this->status = $status;
    }
}
$die = function () {
    return function ( $message, $title = '', $args = array() ) {
        throw new MEP_GROUP_QTY_Test_Die( $message, isset( $args['response'] ) ? $args['response'] : 200 );
    };
};
add_filter( 'wp_die_handler', $die );
add_filter( 'wp_die_ajax_handler', $die );
$checks = 0;
$failures = array();
$assert = function ( $condition, $label ) use ( &$checks, &$failures ) {
    ++$checks;
    if ( ! $condition ) {
        $failures[] = $label;
    }
};
$dispatch = function ( $action, $post ) {
    $_POST = $_REQUEST = $post;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    try {
        do_action( 'wp_ajax_' . $action );
    } catch ( MEP_GROUP_QTY_Test_Die $e ) {
        return $e;
    }
    throw new RuntimeException( 'Handler did not finish through wp_die.' );
};
$keys = array( 'mep_group_qty_license_key', 'mep_group_qty_license_status', 'mep_group_qty_license_expire', 'mep_group_qty_license_order_id', 'mep_group_qty_license_key_type', 'mpaudit_foreign_option' );
$saved = array();
foreach ( $keys as $key ) {
    $saved[ $key ] = get_option( $key, null );
    update_option( $key, 'original' );
}
$users = array();
$old_request = array( $_POST, $_REQUEST, $_SERVER['REQUEST_METHOD'] ?? null );
$old_user = get_current_user_id();
$requests = array();
$response = new WP_Error( 'offline', 'Test response' );
$http = function ( $pre, $args, $url ) use ( &$requests, &$response ) {
    $requests[] = array( $url, $args );
    return $response;
};
add_filter( 'pre_http_request', $http, 1000, 3 );
$warnings = array();
set_error_handler( function ( $code, $message ) use ( &$warnings ) {
    if ( error_reporting() & $code ) {
        $warnings[] = $message;
    }
    return true;
} );
$legacy_calls = 0;
$legacy = function () use ( &$legacy_calls ) { ++$legacy_calls; };
try {
    $assert( ! has_action( 'wp_ajax_nopriv_mep_group_qty_license_activate' ) && ! has_action( 'wp_ajax_nopriv_mep_group_qty_license_deactivate' ), 'No public license actions' );
    $assert( ! has_action( 'wp_ajax_nopriv_mep_wl_ajax_license_deactivate' ), 'This add-on does not install a legacy public action' );
    // Simulate another add-on retaining its shared callback. Our action stays separate.
    add_action( 'wp_ajax_mep_wl_ajax_license_activate', $legacy );
    add_action( 'wp_ajax_mep_wl_ajax_license_deactivate', $legacy );
    add_role( 'audit_customer', 'Audit customer', array( 'read' => true ) );
    add_role( 'audit_staff', 'Audit staff', array( 'read' => true, 'manage_woocommerce' => true ) );
    foreach ( array( 'subscriber', 'audit_customer', 'audit_staff', 'administrator' ) as $role ) {
        $id = wp_insert_user( array( 'user_login' => 'doxd_' . $role . '_' . wp_generate_password( 8, false ), 'user_pass' => wp_generate_password( 32 ), 'role' => $role ) );
        if ( is_wp_error( $id ) ) { throw new RuntimeException( 'Could not create disposable user.' ); }
        $users[ $role ] = $id;
    }
    foreach ( array( 'anonymous' => 0, 'subscriber' => $users['subscriber'], 'customer' => $users['audit_customer'], 'staff' => $users['audit_staff'] ) as $role => $id ) {
        wp_set_current_user( $id );
        foreach ( array( 'mep_group_qty_license_activate', 'mep_group_qty_license_deactivate' ) as $action ) {
            $e = $dispatch( $action, array( 'nonce' => wp_create_nonce( 'mep_group_qty_license' ), 'key' => 'audit-license' ) );
            $assert( 403 === $e->status, $role . ' denied ' . $action );
            $assert( 'original' === get_option( 'mep_group_qty_license_key' ), $role . ' cannot change saved key' );
        }
    }
    $assert( 0 === count( $requests ), 'Rejected roles do not reach HTTP' );
    wp_set_current_user( $users['administrator'] );
    foreach ( array( array(), array( 'nonce' => 'invalid' ), array( 'nonce' => array( 'invalid' ) ) ) as $post ) {
        foreach ( array( 'mep_group_qty_license_activate', 'mep_group_qty_license_deactivate' ) as $action ) {
            $assert( 403 === $dispatch( $action, $post )->status, 'Missing, invalid or array nonce denied' );
        }
    }
    $nonce = wp_create_nonce( 'mep_group_qty_license' );
    foreach ( array( 'mep_group_qty_license_activate', 'mep_group_qty_license_deactivate' ) as $action ) {
        $_POST = array( 'nonce' => $nonce );
        $_SERVER['REQUEST_METHOD'] = 'GET';
        try { do_action( 'wp_ajax_' . $action ); } catch ( MEP_GROUP_QTY_Test_Die $e ) {
            $assert( 405 === $e->status, 'GET request denied' );
        }
    }
    foreach ( array( null, '', array( 'invalid' ), str_repeat( 'x', 1025 ) ) as $key ) {
        $assert( 400 === $dispatch( 'mep_group_qty_license_activate', array( 'nonce' => $nonce, 'key' => $key ) )->status, 'Malformed key denied' );
    }
    $assert( 0 === count( $requests ), 'Malformed requests do not reach HTTP' );
    $post = array( 'nonce' => $nonce, 'key' => 'audit-license', 'item_id' => 999, 'item_name' => 'Other product' );
    foreach ( array( 'key', 'status', 'expire', 'order_id' ) as $part ) {
        $post[ $part . '_option_name' ] = 'mpaudit_foreign_option';
    }
    $reply = function ( $body, $code = 200 ) {
        return array( 'headers' => array(), 'body' => is_string( $body ) ? $body : wp_json_encode( $body ), 'response' => array( 'code' => $code, 'message' => 'Test' ), 'cookies' => array() );
    };
    foreach ( array(
        new WP_Error( 'offline', 'Test' ), $reply( 'Bad Gateway', 502 ), $reply( 'not json' ), $reply( array() ),
        $reply( array( 'success' => 'true' ) ), $reply( array( 'success' => false, 'error' => 'expired' ) ),
        $reply( array( 'success' => false, 'error' => array( 'invalid' ) ) ),
        $reply( array( 'success' => true, 'license' => 'valid', 'expires' => array(), 'payment_id' => 1 ) ),
        $reply( array( 'success' => true, 'license' => 'invalid', 'expires' => 'lifetime', 'payment_id' => 1 ) ),
        $reply( array( 'success' => true, 'license' => 'valid', 'expires' => 'lifetime', 'payment_id' => false ) ),
    ) as $bad ) {
        $response = $bad;
        $e = $dispatch( 'mep_group_qty_license_activate', $post );
        $assert( 200 === $e->status && 'original' === get_option( 'mep_group_qty_license_key' ) && 'original' === get_option( 'mep_group_qty_license_status' ), 'Unavailable or malformed response preserves state' );
    }
    $response = $reply( array( 'success' => true, 'license' => 'valid', 'expires' => 'lifetime', 'payment_id' => 123 ) );
    $e = $dispatch( 'mep_group_qty_license_activate', $post );
    $assert( 200 === $e->status && 'audit-license' === get_option( 'mep_group_qty_license_key' ) && 'valid' === get_option( 'mep_group_qty_license_status' ), 'Administrator activates own license' );
    $assert( '123' === get_option( 'mep_group_qty_license_order_id' ) && 'lifetime' === get_option( 'mep_group_qty_license_expire' ), 'Verified response metadata saved' );
    $assert( 'original' === get_option( 'mpaudit_foreign_option' ), 'Caller-selected option names ignored' );
    $last = end( $requests );
    $assert( MEP_STORE_URL === $last[0] && MEP_GROUP_QTY_ID === $last[1]['body']['item_id'] && true === $last[1]['sslverify'], 'Server product and TLS verification enforced' );
    $e = $dispatch( 'mep_group_qty_license_deactivate', $post );
    $assert( 200 === $e->status && '' === get_option( 'mep_group_qty_license_key' ) && 'invalid' === get_option( 'mep_group_qty_license_status' ), 'Administrator deactivates own license' );
    $assert( 'original' === get_option( 'mpaudit_foreign_option' ) && 0 === $legacy_calls, 'Independent action leaves foreign callbacks and options untouched' );
    $response = $reply( array( 'success' => true, 'license' => 'valid', 'expires' => '<b>lifetime</b>', 'payment_id' => '<b>123</b>' ) );
    $e = $dispatch( 'mep_group_qty_license_activate', $post );
    $assert( false === strpos( $e->getMessage(), '<b>' ) && 'lifetime' === get_option( 'mep_group_qty_license_expire' ), 'Response metadata and output sanitized' );
    $assert( empty( $warnings ), 'No PHP warnings in rejected or permitted paths' );
} finally {
    restore_error_handler();
    remove_filter( 'pre_http_request', $http, 1000 );
    remove_filter( 'wp_die_handler', $die );
    remove_filter( 'wp_die_ajax_handler', $die );
    remove_action( 'wp_ajax_mep_wl_ajax_license_activate', $legacy );
    remove_action( 'wp_ajax_mep_wl_ajax_license_deactivate', $legacy );
    wp_set_current_user( $old_user );
    foreach ( $users as $id ) { wp_delete_user( $id ); }
    remove_role( 'audit_customer' );
    remove_role( 'audit_staff' );
    foreach ( $saved as $key => $value ) {
        if ( null === $value ) { delete_option( $key ); } else { update_option( $key, $value ); }
    }
    list( $_POST, $_REQUEST, $_SERVER['REQUEST_METHOD'] ) = $old_request;
}
echo wp_json_encode( array( 'checks' => $checks, 'failures' => $failures, 'warnings' => $warnings ), JSON_PRETTY_PRINT ) . "\n";
if ( $failures ) { WP_CLI::error( 'License checks failed.' ); }
