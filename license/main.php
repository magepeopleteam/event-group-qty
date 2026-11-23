<?php
if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.


if (!function_exists('mep_license_error_code')) {
function mep_license_error_code($license_data,$item_name='this Plugin'){
    switch ($license_data->error) {
      case 'expired':
          $message = sprintf(
              __('Your license key expired on %s.'),
              date_i18n(get_option('date_format'), strtotime($license_data->expires, current_time('timestamp')))
          );
          break;
      case 'revoked':
          $message = __('Your license key has been disabled.');
          break;
      case 'missing':
          $message = __('Invalid license.');
          break;
      case 'invalid':
      case 'site_inactive':
          $message = __('Your license is not active for this URL.');
      break;
      case 'item_name_mismatch':
          $message = sprintf(__('This appears to be an invalid license key for %s.'), $item_name);
          break;
      case 'no_activations_left':
          $message = __('Your license key has reached its activation limit.');
          break;
      default:
          $message = __('An error occurred, please try again.');
          break;
  }
  return $message;
  }
}


/** Authorize this add-on's license changes independently of other add-ons. */
function mep_group_qty_license_ajax_guard() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You are not allowed to manage licenses.', 'mep-addon-group-qty' ), '', array( 'response' => 403 ) );
    }
    if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
        wp_die( esc_html__( 'Invalid license request.', 'mep-addon-group-qty' ), '', array( 'response' => 405 ) );
    }
    $nonce = isset( $_POST['nonce'] ) && is_string( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'mep_group_qty_license' ) ) {
        wp_die( esc_html__( 'Please reload the page and try again.', 'mep-addon-group-qty' ), '', array( 'response' => 403 ) );
    }
}

add_action( 'wp_ajax_mep_group_qty_license_activate', 'mep_group_qty_license_activate' );
function mep_group_qty_license_activate() {
    mep_group_qty_license_ajax_guard();
    $license = isset( $_POST['key'] ) && is_string( $_POST['key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['key'] ) ) ) : '';
    if ( '' === $license || strlen( $license ) > 1024 ) {
        wp_die( esc_html__( 'Please enter a valid license key.', 'mep-addon-group-qty' ), '', array( 'response' => 400 ) );
    }
    $response = wp_remote_post( MEP_STORE_URL, array(
        'timeout' => 15,
        'body'    => array(
            'edd_action' => 'activate_license',
            'license'    => $license,
            'item_id'    => MEP_GROUP_QTY_ID,
            'url'        => home_url(),
        ),
    ) );
    $message = __( 'An error occurred, please try again.', 'mep-addon-group-qty' );
    if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
        wp_die( esc_html( $message ), '', array( 'response' => 200 ) );
    }
    $data = json_decode( wp_remote_retrieve_body( $response ) );
    // A failed or incomplete response must not erase a previously saved license.
    if ( ! is_object( $data ) || ! isset( $data->success ) || true !== $data->success ||
        ! isset( $data->license, $data->expires, $data->payment_id ) || 'valid' !== $data->license ||
        ! is_string( $data->expires ) || ( ! is_string( $data->payment_id ) && ! is_int( $data->payment_id ) ) ) {
        if ( is_object( $data ) && isset( $data->error ) && is_string( $data->error ) &&
            ( ! isset( $data->expires ) || is_string( $data->expires ) ) &&
            ( 'expired' !== $data->error || isset( $data->expires ) ) ) {
            $message = mep_license_error_code( $data, MEP_GROUP_QTY_NAME );
        }
        wp_die( esc_html( $message ), '', array( 'response' => 200 ) );
    }
    $user_type = isset( $_POST['user_type'] ) && is_string( $_POST['user_type'] ) ? sanitize_key( wp_unslash( $_POST['user_type'] ) ) : 'new';
    $user_type = in_array( $user_type, array( 'new', 'old' ), true ) ? $user_type : 'new';
    $expires = sanitize_text_field( (string) $data->expires );
    $order_id = sanitize_text_field( (string) $data->payment_id );
    update_option( 'mep_group_qty_license_key', $license );
    update_option( 'mep_group_qty_license_expire', $expires );
    update_option( 'mep_group_qty_license_order_id', $order_id );
    update_option( 'mep_group_qty_license_key_type', $user_type );
    update_option( 'mep_group_qty_license_status', 'valid' );
    /* translators: 1: plugin name, 2: order ID, 3: expiry date. */
    $message = sprintf( __( 'Success, License Key is valid for the plugin %1$s. Your Order id is %2$s. Validity of this license is %3$s.', 'mep-addon-group-qty' ), MEP_GROUP_QTY_NAME, $order_id, $expires );
    wp_die( esc_html( $message ), '', array( 'response' => 200 ) );
}

add_action( 'wp_ajax_mep_group_qty_license_deactivate', 'mep_group_qty_license_deactivate' );
function mep_group_qty_license_deactivate() {
    mep_group_qty_license_ajax_guard();
    update_option( 'mep_group_qty_license_key', '' );
    update_option( 'mep_group_qty_license_expire', '' );
    update_option( 'mep_group_qty_license_order_id', '' );
    update_option( 'mep_group_qty_license_status', 'invalid' );
    wp_die( '', '', array( 'response' => 200 ) );
}

add_action('mep_license_page_addon_list', 'mep_group_qty_licensing');
if (!function_exists('mep_group_qty_license_expire_display')) {
function mep_group_qty_license_expire_display($expire){
    if (empty($expire)) {
        return '--';
    }

    if (strtolower((string) $expire) === 'lifetime') {
        return __('Lifetime');
    }

    $timestamp = strtotime($expire);
    if (!$timestamp) {
        return $expire;
    }

    return date_i18n('M j, Y g:i A', $timestamp);
}
}

function mep_group_qty_licensing()
{
    $key_option_name        = 'mep_group_qty_license_key';
    $status_option_name     = 'mep_group_qty_license_status';
    $expire_option_name     = 'mep_group_qty_license_expire';
    $order_id_option_name   = 'mep_group_qty_license_order_id';
    $active_btn_id          = 'mep_group_qty_active';
    $deactive_btn_id        = 'mep_group_qty_deactive';
    $license                = get_option($key_option_name);
    $status                 = get_option($status_option_name);
?>
    <tr>
        <td style="vertical-align:middle;"><?php _e(MEP_GROUP_QTY_NAME, 'mage-eventpress-waitlist'); ?></td>
        <td style="vertical-align:middle;white-space:nowrap;"><?php echo esc_html(get_option($order_id_option_name)); ?></td>
        <td style="vertical-align:middle;white-space:nowrap;text-transform:capitalize;"><?php echo esc_html(mep_group_qty_license_expire_display(get_option($expire_option_name))); ?></td>
        <td style="vertical-align:middle;white-space:nowrap;"><input id="<?php echo $key_option_name; ?>" name="<?php echo $key_option_name; ?>" type="text" style="width:260px;max-width:100%;" value="<?php esc_attr_e($license); ?>" /></td>
        <td style="vertical-align:middle;white-space:nowrap;">
            <?php if ($status !== false && $status == 'valid') { ?>
                <span style="color:green;text-transform:capitalize"><?php _e('Active', 'mage-eventpress-wl'); ?></span>
            <?php } else { ?>
                <span style="color:red;text-transform:capitalize"><?php _e('Deactive', 'mage-eventpress-wl'); ?></span>
            <?php } ?>
        </td>
        <td style="vertical-align:middle;white-space:nowrap;">
            <?php if ($status !== false && $status == 'valid') { ?>
                <button id='<?php echo $deactive_btn_id; ?>' class='button btn btn-license'><?php _e('Deactive', 'mage-eventpress-wl'); ?></button>
            <?php } else {
                // Each action carries the add-on-specific nonce below. ?>
                <button id='<?php echo $active_btn_id; ?>' class='button btn btn-license'><?php _e('Active', 'mage-eventpress-wl'); ?></button>
            <?php } ?>

            <script>
                (function($) {
                    'use strict';
                    jQuery('#<?php echo $active_btn_id; ?>').on('click', function() {
                        var key = jQuery('#<?php echo $key_option_name; ?>').val();
                        var nonce = '<?php echo esc_js( wp_create_nonce( 'mep_group_qty_license' ) ); ?>';
                        if (key) {
                            jQuery.ajax({
                                type: 'POST',
                                url: ajaxurl,
                                data: {
                                    "action"                : "mep_group_qty_license_activate",
                                    "nonce"                 : nonce,
                                    "key"                   : key
                                },
                                beforeSend: function() {
                                    jQuery('.mep_licensae_info').html('<h5 class=""><?php echo 'Please wait.. License Key is checking'; ?></h5>');
                                },
                                success: function(data) {
                                    jQuery('.mep_licensae_info').html(data);
                                    window.location.reload();
                                }
                            });
                        } else {
                            alert('<?php _e('Please Enter the License Key', 'mage-eventpress-waitlist'); ?>');
                        }
                        return false;
                    });

                    jQuery('#<?php echo $deactive_btn_id; ?>').on('click', function() {
                        if (confirm('Are You Sure to Deactivate this license ? \n\n 1. Ok : To Deactive . \n 2. Cancel : To Cancel .')) {
                            jQuery.ajax({
                                type: 'POST',
                                url: ajaxurl,
                                data: {
                                    "action"                : "mep_group_qty_license_deactivate",
                                    "nonce"                 : '<?php echo esc_js( wp_create_nonce( 'mep_group_qty_license' ) ); ?>',
                                },
                                beforeSend: function() {
                                    jQuery('.mep_licensae_info').html('<h5 class=""><?php echo 'Please wait.. License Key is deactivating'; ?></h5>');
                                },
                                success: function(data) {
                                    jQuery('.mep_licensae_info').html(data);
                                    window.location.reload();
                                }
                            });

                        } else {
                            return false;
                        }
                        return false;
                    });
                })(jQuery);
            </script>
        </td>
    </tr>
<?php
}



add_action('admin_footer','mep_admin_menu_license_display_style',90);
if(!function_exists('mep_admin_menu_license_display_style')){
    function mep_admin_menu_license_display_style(){
        ?>
        <style>
        a#mep_settings_licensing-tab {
            display: block!important;
        }
        </style>
        <?php
    }
}
