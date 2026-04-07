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
			private const VERSION = '1.0.0';

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
					if ( ! defined( 'MEP_STORE_URL' ) ) {
						define( 'MEP_STORE_URL', 'https://mage-people.com/' );
					}
					if ( ! defined( 'MEP_GROUP_QTY_ID' ) ) {
						define( 'MEP_GROUP_QTY_ID', 137666 );
					}
					if ( ! defined( 'MEP_GROUP_QTY_NAME' ) ) {
						define( 'MEP_GROUP_QTY_NAME', 'WooCommerce Event Manager Addon: Group Qty' );
					}
					if ( ! class_exists( 'EDD_SL_Plugin_Updater' ) ) {
						require_once MEP_Addon_Group_Qty_DIR . '/license/EDD_SL_Plugin_Updater.php';
					}
					require_once MEP_Addon_Group_Qty_DIR . '/license/main.php';
					$license_key = trim( get_option( 'mep_group_qty_license_key' ) );
					new EDD_SL_Plugin_Updater(
						MEP_STORE_URL,
						__FILE__,
						array(
							'version'   => self::VERSION,
							'license'   => $license_key,
							'item_name' => MEP_GROUP_QTY_NAME,
							'item_id'   => MEP_GROUP_QTY_ID,
							'author'    => 'MagePeople Team',
							'url'       => home_url(),
							'beta'      => false,
						)
					);
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
