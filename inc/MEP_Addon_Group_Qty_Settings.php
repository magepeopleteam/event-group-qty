<?php
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'MEP_Addon_Group_Qty_Settings' ) ) {
		class MEP_Addon_Group_Qty_Settings {
			public function __construct() {
				add_filter( 'mpwem_group_qty_actual', [ $this, 'actual_qty' ], 10, 3 );
				add_filter( 'mpwem_group_qty', [ $this, 'group_qty' ], 10, 3 );
				add_filter( 'mpwem_group_qty_price', [ $this, 'group_qty_price' ], 10, 3 );
				add_action( 'mpwem_hidden_item_ticket', array( $this, 'input_data' ), 10, 2 );
				add_action( 'mpwem_before_ticket_type', array( $this, 'group_switch' ) );
				add_action( 'mpwem_add_extra_column', [ $this, 'group_qty_column' ], 20 );
				add_action( 'mpwem_add_extra_input_box', [ $this, 'group_qty_input_box' ], 20, 3 );
				add_action( 'mpwem_settings_save', [ $this, 'save_qty' ] );
				add_filter( 'mpwem_ticket_type_arr_save', [ $this, 'ticket_type_arr_save' ] );
			}
			public function group_qty( $qty, $post_id, $ticket_name ) {
				$display       = MPWEM_Global_Function::get_post_info( $post_id, 'mep_display_group_qty', 'off' );
				if ( $display == 'on' ) {
					$ticket_types = MPWEM_Global_Function::get_post_info( $post_id, 'mep_event_ticket_type', array() );
					foreach ( $ticket_types as $ticket_type ) {
						if ( $ticket_type['option_name_t'] == $ticket_name && $ticket_type['group_qty'] > 1 ) {
							$qty = $qty / $ticket_type['group_qty'];
						}
					}
				}
				return $qty;
			}
            public function group_qty_price( $price, $post_id, $ticket_name ) {
				$display       = MPWEM_Global_Function::get_post_info( $post_id, 'mep_display_group_qty', 'off' );
				if ( $display == 'on' ) {
					$ticket_types = MPWEM_Global_Function::get_post_info( $post_id, 'mep_event_ticket_type', array() );
					foreach ( $ticket_types as $ticket_type ) {
						if ( $ticket_type['option_name_t'] == $ticket_name && $ticket_type['group_qty'] > 1 ) {
							$price = $price * $ticket_type['group_qty'];
						}
					}
				}
				return $price;
			}
			public function input_data( $ticket_type_name, $post_id ) {
				$display       = MPWEM_Global_Function::get_post_info( $post_id, 'mep_display_group_qty', 'off' );
				$checked       = $display == 'off' ? '' : 'checked';
				$same_attendee = MPWEM_Global_Function::get_post_info( $post_id, 'mep_group_qty_same_attendee', 'on' );
				$same_attendee_global         = MPWEM_Global_Function::get_settings( 'general_setting_sec', 'mep_enable_same_attendee', 'no' );
				if ( $checked == 'checked' && $same_attendee_global == 'no'  && $same_attendee=='off') {
					$unit_qty = $this->actual_qty( 1, $post_id, $ticket_type_name );
					?>
                    <input type="hidden" name='mep_group_qty' value='<?php echo esc_attr( $unit_qty ); ?>'>
					<?php
				}
			}
			public function actual_qty( $qty, $post_id, $ticket_type_name ) {
				$display = MPWEM_Global_Function::get_post_info( $post_id, 'mep_display_group_qty', 'off' );
				$checked = $display == 'off' ? '' : 'checked';
				if ( $checked == 'checked' ) {
					$ticket_types = MPWEM_Global_Function::get_post_info( $post_id, 'mep_event_ticket_type', array() );
					foreach ( $ticket_types as $ticket_type ) {
						if ( $ticket_type['option_name_t'] == $ticket_type_name ) {
							$qty = $qty*$ticket_type['group_qty'];
						}
					}
				}
				return $qty;
			}
			public function group_switch( $post_id ) {
				$display               = MPWEM_Global_Function::get_post_info( $post_id, 'mep_display_group_qty', 'off' );
				$checked               = $display == 'off' ? '' : 'checked';
				$same_attendee         = MPWEM_Global_Function::get_post_info( $post_id, 'mep_group_qty_same_attendee', 'on' );
				$same_attendee_checked = $same_attendee == 'off' ? '' : 'checked';
				$active                = $display == 'on' ? 'mActive' : '';
				$same_attendee         = MPWEM_Global_Function::get_settings( 'general_setting_sec', 'mep_enable_same_attendee', 'no' );
				?>
                <div class="_padding_bt">
                    <div class="_justify_between_align_center_wrap ">
                        <label><span class="_mr"><?php esc_html_e( 'Enable Group Qty', 'mep-addon-group-qty' ); ?></span></label>
						<?php MPWEM_Custom_Layout::switch_button( 'mep_display_group_qty', $checked ); ?>
                    </div>
                    <span class="des_info"><?php esc_html_e( 'Enable this for group Qty', 'mep-addon-group-qty' ); ?></span>
                </div>
				<?php if ( $same_attendee == 'no' ) { ?>
                    <div class="_padding_bt <?php echo esc_attr( $active ); ?>" data-collapse="#mep_display_group_qty">
                        <div class="_justify_between_align_center_wrap ">
                            <label><span class="_mr"><?php esc_html_e( 'Enable Group Qty same Attendee', 'mep-addon-group-qty' ); ?></span></label>
							<?php MPWEM_Custom_Layout::switch_button( 'mep_group_qty_same_attendee', $same_attendee_checked ); ?>
                        </div>
                        <span class="des_info"><?php esc_html_e( 'Enable Group Qty same Attendee', 'mep-addon-group-qty' ); ?></span>
                    </div>
					<?php
				}
			}
			public function group_qty_column( $post_id ) {
				$display = MPWEM_Global_Function::get_post_info( $post_id, 'mep_display_group_qty', 'off' );
				?>
                <th class="_min_100 <?php echo esc_attr( $display == 'on' ? 'mActive' : '' ); ?>" data-collapse="#mep_display_group_qty"><?php esc_html_e( 'Group Qty', 'mep-addon-group-qty' ); ?></th>
				<?php
			}
			public function group_qty_input_box( $post_id, $field = [] ) {
				$display   = MPWEM_Global_Function::get_post_info( $post_id, 'mep_display_group_qty', 'off' );
				$group_qty = array_key_exists( 'group_qty', $field ) ? $field['group_qty'] : '';
				?>
                <td class="<?php echo esc_attr( $display == 'on' ? 'mActive' : '' ); ?>" data-collapse="#mep_display_group_qty">
                    <label>
                        <input type="number" class="formControl" name="group_qty[]" placeholder="Ex: 2" value="<?php echo esc_attr( $group_qty ); ?>"/>
                    </label>
                </td>
				<?php
			}
			public function save_qty( $post_id ) {
				if ( get_post_type( $post_id ) == 'mep_events' ) {
					$mep_display_group_qty = isset( $_POST['mep_display_group_qty'] ) && sanitize_text_field( wp_unslash( $_POST['mep_display_group_qty'] ) ) ? 'on' : 'off';
					update_post_meta( $post_id, 'mep_display_group_qty', $mep_display_group_qty );
					$mep_group_qty_same_attendee = isset( $_POST['mep_group_qty_same_attendee'] ) && sanitize_text_field( wp_unslash( $_POST['mep_group_qty_same_attendee'] ) ) ? 'on' : 'off';
					update_post_meta( $post_id, 'mep_group_qty_same_attendee', $mep_group_qty_same_attendee );
				}
			}
			public function ticket_type_arr_save( $data ) {
				$group_qty = isset( $_POST['group_qty'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['group_qty'] ) ) : [];
				if ( sizeof( $group_qty ) > 0 ) {
					$count = count( $group_qty );
					for ( $i = 0; $i < $count; $i ++ ) {
						if ( array_key_exists( $i, $data ) ) {
							$data[ $i ]['group_qty'] = array_key_exists( $i, $group_qty ) ? $group_qty[ $i ] : '';
						}
					}
				}
				return $data;
			}
		}
		new MEP_Addon_Group_Qty_Settings();
	}