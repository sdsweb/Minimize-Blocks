<?php
/**
 * Minimize_Blocks_Widgets
 *
 * Description: Initalize Widgets
 *
 * @access      private
 * @since       1.0 
 * @return      void
 */

if( ! class_exists( 'Minimize_Blocks_Widgets' ) ) {
	class Minimize_Blocks_Widgets {

		private static $instance; // Keep track of the instance

		/**
		 * Function used to create instance of class.
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) )
				self::$instance = new Minimize_Blocks_Widgets;

			return self::$instance;
		}


		/**
		 * This function sets up all of the actions and filters on instance
		 */
		function __construct( ) {
			add_action( 'widgets_init', array( $this, 'widgets_init' ) ); // Register and Initalize Widgets
		}

		/**
		 * This function registers and initalizes all widgets
		 */
		function widgets_init() {
			// Minimize Block (featured content or featured post)
			include_once MB_PLUGIN_DIR . 'includes/widgets/minimize-block/minimize-block-widget.php';
			register_widget( 'Minimize_Block_Widget' );
		}
	}


	function Minimize_Blocks_Widgets_Instance() {
		return Minimize_Blocks_Widgets::instance();
	}

	Minimize_Blocks_Widgets_Instance();
}