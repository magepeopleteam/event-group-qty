<?php
	/**
	 * Plugin Name: WooCommerce Event Manager Addon: Group Qty
	 * Plugin URI: http://mage-people.com
	 * Description: A Group Qyantity Addon for WooCommerce Event Manager by MagePeople.
	 * Version: 1.0.0
	 * Author: MagePeople Team
	 * Author URI: http://www.mage-people.com/
	 * Text Domain: mep-addon-group-qty
	 * Domain Path: /languages/
	 */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'MEP_Addon_Group_Qty' ) ) {
		class MEP_Addon_Group_Qty {
			public function __construct() {
				$this->load_plugin();
			}
			private function load_plugin(): void {
				include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
				if ( ! defined( 'MEP_Addon_Group_Qty_DIR' ) ) {
					define( 'MEP_Addon_Group_Qty_DIR', dirname( __FILE__ ) );
				}
				if ( ! defined( 'MEP_Addon_Group_Qty_URL' ) ) {
					define( 'MEP_Addon_Group_Qty_URL', plugins_url() . '/' . plugin_basename( dirname( __FILE__ ) ) );
				}
				if ( self::check_plugin() == 1 ) {
					require_once MEP_Addon_Group_Qty_DIR . '/inc/MEP_Addon_Group_Qty_Dependencies.php';
				}
			}
			public static function check_plugin(): int {
				include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
				$plugin_dir = ABSPATH . 'wp-content/plugins/mage-eventpress';
				if ( is_plugin_active( 'woocommerce/woocommerce.php' ) && is_plugin_active( 'mage-eventpress/woocommerce-event-press.php' ) ) {
					return 1;
				} elseif ( is_dir( $plugin_dir ) ) {
					return 2;
				} else {
					return 0;
				}
			}
		}
		new MEP_Addon_Group_Qty();
	}
