<?php
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MEP_Addon_Group_Qty_Dependencies')) {
		class MEP_Addon_Group_Qty_Dependencies {
			public function __construct() {
				$this->load_files();
			}
			private function load_files(): void {
				require_once MEP_Addon_Group_Qty_DIR . '/inc/MEP_Addon_Group_Qty_Settings.php';
			}
		}
		new MEP_Addon_Group_Qty_Dependencies();
	}
