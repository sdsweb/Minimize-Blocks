<?php
/**
 * Plugin Name: Minimize Blocks
 * Plugin URI: http://www.slocumstudio.com/
 * Description: A plugin that adds content blocks to your Front Page on Minimize.
 * Version: 1.0.3
 * Author: Slocum Design Studio
 * Author URI: http://www.slocumstudio.com/
 */

define( 'MB_VERSION', '1.0.3' ); // Version
define( 'MB_PLUGIN_FILE', __FILE__ ); // Reference to this plugin file
define( 'MB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) ); // Plugin directory path
define( 'MB_PLUGIN_URL', trailingslashit( plugins_url( '' , __FILE__ ) ) ); // Plugin url

if( ! class_exists( 'Minimize_Blocks' ) ) {
	class Minimize_Blocks {

		private static $instance; // Keep track of the instance

		/*
		 * Function used to create instance of class.
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) )
				self::$instance = new Minimize_Blocks;

			return self::$instance;
		}


		/*
		 * This function sets up all of the actions and filters on instance
		 */
		function __construct( ) {
			 // Plugin Updates
			include_once MB_PLUGIN_DIR . 'includes/plugin-update-checker.php';
			$mb_updates = new PluginUpdateChecker( 'http://theme-api.slocumstudio.com/minimize-blocks/info.php', __FILE__, 'minimize-blocks' );
			add_filter( 'puc_request_info_query_args-minimize-blocks', array( $this, 'mb_update_query_args' ) );
			add_filter( 'puc_request_info_result-minimize-blocks', array( $this, 'mb_request_info_result' ), 10, 2 );
			add_action( 'admin_init', array( $this, 'admin_init' ) ); // Remove update notices if the versions are synced
			add_action( 'wp_dashboard_setup', array( $this, 'wp_dashboard_setup' ) ); // Create dashboard notification for updates
			add_action( 'wp_ajax_dismiss_mb_update_notification', array( $this, 'wp_ajax_dismiss_mb_update_notification' ) ); // Handle AJAX request for dismissing notifications

			 // Widgets Init - Initalize widgets
			include_once MB_PLUGIN_DIR . 'includes/widgets.php';
		}

		function mb_update_query_args( $args ) {
			$args['tt'] = time();
			$args['uid'] = md5( uniqid( rand(), true ) );
			return $args;
		}

		function mb_request_info_result( $plugin_info, $result ) {
			// Update is available (store option)
			if( version_compare( MB_VERSION, $plugin_info->version, '<' ) )
				update_option( 'mb_update_available', $plugin_info->version );

			return $plugin_info;
		}

		/**
		 * This function creates a dashboard widget which displays an update notification if updates are available.
		 */
		function wp_dashboard_setup() {
			// Only display the message to administrators
			if ( current_user_can( 'update_plugins' ) ) {
				$mb_update_available = get_option( 'mb_update_available' );
				$mb_update_message_dismissed = get_option( 'mb_update_message_dismissed' );

				// If the user has not already dismissed the message for this version
				if ( version_compare( $mb_update_message_dismissed, $mb_update_available, '<' ) ) {
		?>
					<div class="updated" style="padding: 15px; position: relative;" id="mb_dashboard_message" data-version="<?php echo $mb_update_available; ?>">
						<strong>There is a new update for Minimize Blocks (v<?php echo $mb_update_available; ?>). You're currently using version <?php echo MB_VERSION; ?>. <a href="plugins.php">Download Update</a>.</strong>
						<a href="javascript:void(0);" onclick="DismissUpgradeMessage();" style="float: right;">Dismiss.</a>
					</div>
					<script type="text/javascript">
						<?php $ajax_nonce = wp_create_nonce( 'dismiss_mb_update_notification' ); ?>
						function DismissUpgradeMessage() {
							var mb_data = {
								action: 'dismiss_mb_update_notification',
								_wpnonce: '<?php echo $ajax_nonce; ?>',
								version: jQuery( '#mb_dashboard_message' ).attr( 'data-version' )
							};

							jQuery.post( ajaxurl, mb_data, function( response ) {
								jQuery( '#mb_dashboard_message').fadeOut();
							} );
						}
					</script>
		<?php
				}
			}
		}

		function wp_ajax_dismiss_mb_update_notification() {
			check_ajax_referer( 'dismiss_mb_update_notification' );

			if ( isset( $_POST['version'] ) && ! empty( $_POST['version'] ) ) {
				update_option( 'mb_update_message_dismissed', sanitize_text_field( $_POST['version'] ) );
				echo 'true';
			}
			else
				echo 'false';
			exit;
		}

		function admin_init() {
			$mb_update_available = get_option( 'mb_update_available' );
			if ( version_compare( MB_VERSION, $mb_update_available, '=' ) )
				update_option( 'mb_update_message_dismissed', MB_VERSION );
		}
	}


	function Minimize_Blocks_Instance() {
		return Minimize_Blocks::instance();
	}

	Minimize_Blocks_Instance();
}