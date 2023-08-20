<?php
/**
 * Plugin Name: Transfer Helper
 * Plugin URI: https://honorswp.com/plugins/import-export-tool-for-learndash/
 * Description: Easily export, import, and backup your courses, lessons and other LMS content
 * Version: 0.0.1
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * Author: Suraj Singh
 * Author URI: https://profiles.wordpress.org/surajkumarsingh/
 * Text Domain: ea-transfer-helper
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Tags: LMS, Learndash, LifterLMS, Sensei, TutorLMS, LearnPress, Course Converter, Import and Export, Backups.
 *
 * @package ea_transfer_helper
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Constants for plugin details
define( 'EA_TRANSFER_HELPER_VERSION', '0.0.1' );
define( 'EA_TRANSFER_HELPER_PLUGIN_FILE', plugin_dir_path( __FILE__ ) . 'ea-transfer-helper.php' );
define( 'EA_TRANSFER_HELPER_PLUGIN_DIR_NAME', untrailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );
define( 'EA_TRANSFER_HELPER_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'EA_TRANSFER_HELPER_PLUGIN_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

/**
 * Check whether transfer plugin is enabled if not then deactivate plugin
 * and add admin notice.
 *
 * @return void
 */
function eath_check_transfer_plugin() {
	if ( ! is_plugin_active( 'ea-transfer/ea-transfer.php' ) ) {
		add_action( 'admin_notices', 'eath_add_admin_notice' );
		deactivate_plugins( EA_TRANSFER_HELPER_PLUGIN_FILE );
	}
}
add_action( 'admin_init', 'eath_check_transfer_plugin' );

/**
 * Add admin notice about plugin transfer.
 *
 * @return void
 */
function eath_add_admin_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'The required Transfer plugin is not active. Please activate it to use this functionality.', 'ea-transfer' ); ?></p>
	</div>
	<?php
}

/**
 * Add new meta boxes with settings to check the activity status
 * and run if required
 *
 * @return void
 */
function eath_add_status_check_meta_box() {
	$db_manager = EATransfer\Classes\DB_Manager::get_instance();
	$activities = $db_manager->eadb['Activity_Table']::get_activities();
	?>
	<div class="postbox">
		<h2 class="postbox-title ea-normal-cursor">
			<span><?php esc_html_e( 'Activity Status', 'ea-transfer' ); ?></span>
		</h2>
		<div class="ea_transfer-setting-section">
			<div class="ea_transfer-input-group eath-input-group">
				<div class="eath-input">
					<select class="" name="eath-activity-option" id="eath-activity-option">
						<option value=""><?php esc_html_e( 'Select Activity Id', 'ea-transfer' ); ?></option>
					<?php
					foreach ( $activities as $activity ) {
						printf(
							'<option value="%s">#%s %s</option>',
							$activity->ID,
							$activity->ID,
							$activity->activity_type
						);
					}
					?>
					</select>
					<button type="button" class="button button-primary" id="eath-process-check">
					<?php esc_html_e( 'Check' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>
	<?php
}
add_action( 'ea_transfer_settings_page_right_sidebar_content', 'eath_add_status_check_meta_box' );


/**
 * Enqueue scripts.
 *
 * @return void
 */
function enqueue_eath_process_script() {
	$page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
	if ( empty( $page ) || 'ea-transfer-settings' !== $page ) {
		return;
	}

	wp_enqueue_script( 'eath-process-script', EA_TRANSFER_HELPER_PLUGIN_URL . '/js/eath-script.js', array( 'jquery' ), EA_TRANSFER_HELPER_VERSION, true );
	wp_localize_script(
		'eath-process-script',
		'eath_object',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'eath_nonce' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'enqueue_eath_process_script' );

/**
 * Check the status for a given activity.
 *
 * @return void
 */
function eath_check_activity_status() {

	if ( ! check_ajax_referer( 'eath_nonce', 'security', false ) ) {
		wp_send_json_error( 'Invalid nonce' );
	}

	if ( empty( $_POST['activity_id'] ) ) {
		wp_send_json_error( 'Id missing' );
	}

	$activity_id    = absint( sanitize_text_field( $_POST['activity_id'] ) );
	$db_manager     = EATransfer\Classes\DB_Manager::get_instance();
	$activity       = $db_manager->eadb['Activity_Table']::get_activity( $activity_id );
	$activity->meta = $db_manager->eadb['Activity_Meta_Table']::get_activity_meta( $activity_id );
	$user_data      = get_user_by( 'id', $activity->user_id );

	if ( $user_data ) {
		$activity->user = array(
			'ID'           => $user_data->data->ID,
			'display_name' => $user_data->data->display_name,
		);
	}

	$activity->meta = array_map(
		function ( $meta ) {
			return is_serialized( $meta ) ? wp_json_encode( maybe_unserialize( $meta ) ) : $meta;
		},
		is_array( $activity->meta ) ? $activity->meta : array()
	);

	wp_send_json_success( $activity );
}
add_action( 'wp_ajax_eath_check_activity_status', 'eath_check_activity_status' );


/**
 * Resume the activity process if required.
 *
 * @return void
 */
function eath_rerun_activity() {

	if ( ! check_ajax_referer( 'eath_nonce', 'security', false ) ) {
		wp_send_json_error( 'Invalid nonce' );
	}

	if ( empty( $_POST['activity_id'] ) ) {
		wp_send_json_error( 'Activity ID missing' );
	}

	$activity_id           = absint( sanitize_text_field( $_POST['activity_id'] ) );
	$settings              = new stdClass();
	$settings->activity_id = $activity_id;

	$transfer = EATransfer\Classes\Transfer::get_instance();
	$response = $transfer->resume_process( array( 'settings' => $settings ) );

	wp_send_json_success( $response );
}

add_action( 'wp_ajax_eath_rerun_activity', 'eath_rerun_activity' );
