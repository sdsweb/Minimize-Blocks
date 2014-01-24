<?php
/**
 * Minimize_Block_Widget
 *
 * Description: Display one or many featured content pieces based on settings.
 *
 * @since 1.0
 */
if( ! class_exists( 'Minimize_Block_Widget' ) ) {
	class Minimize_Block_Widget extends WP_Widget {
		private static $instance; // Keep track of the instance

		/**
		 * This function is used to get/create an instance of the class.
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) )
				self::$instance = new Minimize_Block_Widget;

			return self::$instance;
		}

		/**
		 * This function sets up all widget options including class name, description, width/height, and creates an instance of the widget
		 */
		function __construct() {
			$widget_options = array( 'classname' => 'minimize-block-widget block-widget', 'description' => 'Display one or many featured content pieces.' );
			$control_options = array( 'width' => 550, 'height' => 350, 'id_base' => 'minimize-block-widget' );
			self::WP_Widget( 'minimize-block-widget', 'Minimize Block', $widget_options, $control_options );

			// Widget specific hooks
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) ); // Enqueue admin scripts
			add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) ); // Enqueue CSS
		}


		/**
		 * This function configures the form on the Widgets Admin Page.
		 */
		function form( $instance ) {
			global $wpdb, $post, $_wp_additional_image_sizes;

			// Set up the default widget settings
			$defaults = array(
				'title' => false,
				'widget_size' => 'large',
				'feature_many' => false, // Feature one content piece
				'post_id' => false, // Post ID used if featuring only one (above)
				// Query arguments if featuring many
				'query_args' => array(
					'post_type' => 'post',
					'orderby' => 'date',
					'order' => 'DESC',
					'posts_per_page' => get_option( 'posts_per_page' ),
					'offset' => 1,
					'cat' => false,
					'post__in' => false,
					'post__not_in' => false,
				),
				'hide_title' => false,
				'hide_post_title' => false,
				'show_post_thumbnails' => false,
				'post_thumbnails_size' => false,
				'excerpt_length' => 55,
				'read_more_label' => 'Read More',
				'hide_read_more' => false,
				'css_class' => false
			);

			$instance = wp_parse_args( ( array ) $instance, apply_filters( 'mb_widget_defaults', $defaults ) ); // Parse any saved arguments into defaults

			// Get all public post types and format the list for display in drop down
			if ( ! $public_post_types = wp_cache_get( 'public_post_types', 'minimize-block-widget' ) ) {
				$public_post_types = get_post_types( array( 'public' => true ) ); // Public Post Types
				unset( $public_post_types['attachment'] ); // Remove attachments
				wp_cache_add( 'public_post_types', $public_post_types, 'minimize-block-widget' ); // Store cache
			}
		?>
			<p class="mb-widget-title">
				<?php // Widget Title ?>
				<label for="<?php echo $this->get_field_id( 'title' ) ; ?>"><strong>Title</strong></label>
				<br />
				<input type="text" class="mb-input" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo esc_attr( $instance['title'] ); ?>" />
			</p>

			<p class="mb-widget-size">
				<?php // Widget Size (size of the widget on the front end of the site) ?>
				<label for="<?php echo $this->get_field_id( 'widget_size' ); ?>"><strong>Select A Widget Size</strong></label>
				<br />
				<select name="<?php echo $this->get_field_name( 'widget_size' ); ?>" id="<?php echo $this->get_field_id( 'widget_size' ); ?>" class="mb-select">
					<option value="">Select A Widget Size</option>
					<option value="small" <?php selected( $instance['widget_size'], 'small' ); ?>>Small</option>
					<option value="medium" <?php selected( $instance['widget_size'], 'medium' ); ?>>Medium</option>
					<option value="large" <?php selected( $instance['widget_size'], 'large' ); ?>>Large</option>
				</select>
				<br />
				<small class="description mb-description">Change the size of the widget displayed on the front end.</small>
			</p>


			<div class="minimize-block-column minimize-block-first-column">
				<div class="minimize-block-section minimize-block-section-top minimize-block-section-general">
					<h3>Content Settings</h3>

					<p class="mb-feature-content-pieces">
						<?php // Feature Many (feature one or many pieces of content) ?>
						<label for="<?php echo $this->get_field_id( 'feature_many' ); ?>"><strong>Feature one or many content pieces?</strong></label>
						<br />
						<select name="<?php echo $this->get_field_name( 'feature_many' ); ?>" id="<?php echo $this->get_field_id( 'feature_many' ); ?>" class="minimize-block-feature-many mb-select">
							<option value="">One Piece of Content</option>
							<option value="true" <?php selected( $instance['feature_many'], true ); ?>>Many Pieces of Content</option>
						</select>
					</p>

					<p class="mb-select-content-piece mb-feature-one <?php echo ( $instance['feature_many'] ) ? 'mb-hidden' : false; ?>">
						<?php // Select a Post ?>
						<label for="<?php echo $this->get_field_id( 'post_id' ); ?>"><strong>Select One Content Piece</strong></label>
						<br />
						<select name="<?php echo $this->get_field_name( 'post_id' ); ?>" id="<?php echo $this->get_field_id( 'post_id' ); ?>" class="featured-post-select mb-select">
							<option value="">Select One Content Piece</option>
							<?php
								// Loop through public post types
								if ( ! empty( $public_post_types ) )
									foreach ( $public_post_types as $public_post_type ) :
										// Post Count
										if ( ! $post_count = wp_cache_get( $public_post_type . '_post_type_count', 'minimize-block-widget' ) ) {
											$post_count = wp_count_posts( $public_post_type )->publish;
											wp_cache_add( $public_post_type . '_post_type_count', $post_count, 'minimize-block-widget' ); // Store cache
										}

										// Post Data
										if ( ! $posts = wp_cache_get( $public_post_type . '_data', 'minimize-block-widget' ) ) {
											$posts =$wpdb->get_results(
												$wpdb->prepare(
													"SELECT SQL_CALC_FOUND_ROWS $wpdb->posts.ID, $wpdb->posts.post_title FROM $wpdb->posts WHERE 1=1 AND $wpdb->posts.post_type = '$public_post_type' AND $wpdb->posts.post_status = 'publish' ORDER BY $wpdb->posts.post_title ASC LIMIT 0, $post_count"
												)
											);
											wp_cache_add( $public_post_type . '_data', $posts, 'minimize-block-widget' ); // Store cache
										}

										// Display posts
										if ( ! empty( $posts ) ) :
											// Post Type Object
											if ( ! $public_post_type_object = wp_cache_get( 'public_post_type_' . $public_post_type . '_object', 'minimize-block-widget' ) ) {
												$public_post_type_object = get_post_type_object( $public_post_type );
												wp_cache_add( 'public_post_type_' . $public_post_type . '_object', $public_post_type_object, 'minimize-block-widget' ); // Store cache
											}
							?>
											<optgroup label="<?php echo $public_post_type_object->labels->name; ?>">
												<?php foreach( $posts as $post ) : ?>
													<option value="<?php echo $post->ID; ?>" <?php selected( $instance['post_id'], $post->ID ); ?>><?php echo $post->post_title; ?></option>
												<?php endforeach; ?>
											</optgroup>
							<?php
									endif;
								endforeach;
							?>
						</select>
					</p>

					<p class="mb-select-content-type mb-feature-many <?php echo ( ! $instance['feature_many'] ) ? 'mb-hidden' : false; ?>">
						<?php // Content Type (which post_type to be displayed) ?>
						<label for="<?php echo $this->get_field_id( 'post_type' ); ?>"><strong>Content Type</strong></label>
						<br />
						<select name="<?php echo $this->get_field_name( 'post_type' ); ?>" id="<?php echo $this->get_field_id( 'post_type' ); ?>" class="minimize-block-type mb-select">
							<option value="">Select A Content Type</option>
							<?php
								// Loop through public post types
								if ( ! empty( $public_post_types ) )
									foreach ( $public_post_types as $public_post_type ) :
										// Post Type Object
										if ( ! $public_post_type_object = wp_cache_get( 'public_post_type_' . $public_post_type . '_object', 'minimize-block-widget' ) ) {
											$public_post_type_object = get_post_type_object( $public_post_type );
											wp_cache_add( 'public_post_type_' . $public_post_type . '_object', $public_post_type_object, 'minimize-block-widget' ); // Store cache
										}
							?>
										<option value="<?php echo esc_attr( $public_post_type_object->name ); ?>" <?php selected( $instance['query_args']['post_type'], $public_post_type_object->name ); ?>><?php echo $public_post_type_object->labels->name; ?></option>
							<?php
									endforeach;
							?>
						</select>
					</p>

					<p class="mb-select-cat mb-feature-many <?php echo ( ! $instance['feature_many'] ) ? 'mb-hidden' : false; ?>">
						<?php // Category ?>
						<label for="<?php echo $this->get_field_id( 'cat' ); ?>"><strong>Category</strong></label>
						<br />
						<?php
							// Show a list of categories
							wp_dropdown_categories( array(
								'name' => $this->get_field_name( 'cat' ),
								'selected' => $instance['query_args']['cat'],
								'catby' => 'Name',
								'hierarchical' => 1,
								'show_option_all' => 'All Categories',
								'hide_empty'  => false,
								'class' => 'mb-select'
							) );
						?>
						<br />
						<small class="description mb-description">Use categories to filter "Posts" displayed.</small>
					</p>

					<p class="mb-select-orderby mb-feature-many <?php echo ( ! $instance['feature_many'] ) ? 'mb-hidden' : false; ?>">
						<?php // Orderby ?>
						<label for="<?php echo $this->get_field_id( 'orderby' ); ?>"><strong>Order By</strong></label>
						<br />
						<select name="<?php echo $this->get_field_name( 'orderby' ); ?>" id="<?php echo $this->get_field_id( 'orderby' ); ?>" class="minimize-block-type mb-select">
							<option value="author" <?php selected( $instance['query_args']['orderby'], 'author' ); ?>>Author</option>
							<option value="comment_count" <?php selected( $instance['query_args']['orderby'], 'comment_count' ); ?>>Comment Count</option>
							<option value="date" <?php selected( $instance['query_args']['orderby'], 'date' ); ?>>Date</option>
							<option value="ID" <?php selected( $instance['query_args']['orderby'], 'ID' ); ?>>ID</option>
							<option value="parent" <?php selected( $instance['query_args']['orderby'], 'parent' ); ?>>Parent</option>
							<option value="name" <?php selected( $instance['query_args']['orderby'], 'name' ); ?>>Post Slug</option>
							<option value="title" <?php selected( $instance['query_args']['orderby'], 'title' ); ?>>Title</option>
							<option value="rand" <?php selected( $instance['query_args']['orderby'], 'rand' ); ?>>Random</option>
						</select>
					</p>

					<p class="mb-select-order mb-feature-many <?php echo ( ! $instance['feature_many'] ) ? 'mb-hidden' : false; ?>">
						<?php // Order ?>
						<label for="<?php echo $this->get_field_id( 'order' ); ?>"><strong>Order</strong></label>
						<br />
						<select name="<?php echo $this->get_field_name( 'order' ); ?>" id="<?php echo $this->get_field_id( 'order' ); ?>" class="minimize-block-type mb-select">
							<option value="ASC" <?php selected( $instance['query_args']['order'], 'ASC' ); ?>>Ascending (1, 2, 3)</option>
							<option value="DESC" <?php selected( $instance['query_args']['order'], 'DESC' ); ?>>Descending (3, 2, 1)</option>
						</select>
					</p>

					<p class="mb-posts-per-page mb-feature-many <?php echo ( ! $instance['feature_many'] ) ? 'mb-hidden' : false; ?>">
						<?php // Number of Posts to Display ?>
						<label for="<?php echo $this->get_field_id( 'posts_per_page' ); ?>"><strong>Show a maximum of
							<input type="text" class="mb-input mb-inline-input" id="<?php echo $this->get_field_id( 'posts_per_page' ); ?>" name="<?php echo $this->get_field_name( 'posts_per_page' ); ?>" type="text" value="<?php echo esc_attr( $instance['query_args']['posts_per_page'] ); ?>" />
							posts.
						</strong></label>
					</p>

					<p class="mb-offset mb-feature-many <?php echo ( ! $instance['feature_many'] ) ? 'mb-hidden' : false; ?>">
						<?php // Offset (Number of post to offset by) ?>
						<label for="<?php echo $this->get_field_id( 'offset' ); ?>"><strong>Start at post #
							<input type="text" class="mb-input mb-inline-input" id="<?php echo $this->get_field_id( 'offset' ); ?>" name="<?php echo $this->get_field_name( 'offset' ); ?>" value="<?php echo esc_attr( $instance['query_args']['offset'] ); ?>" />
							.
						</strong></label>
					</p>

					<p class="mb-post-in mb-feature-many <?php echo ( ! $instance['feature_many'] ) ? 'mb-hidden' : false; ?>">
						<?php // Post In (posts to specifically include) ?>
						<label for="<?php echo $this->get_field_id( 'post__in' ); ?>"><strong>Include Only These Posts</strong></label>
						<br />
						<input type="text" id="<?php echo $this->get_field_id( 'post__in' ); ?>" name="<?php echo $this->get_field_name( 'post__in' ); ?>" value="<?php echo esc_attr( $instance['query_args']['post__in'] ); ?>" />
						<br />
						<small class="description mb-description">Comma separated list of post IDs. Only these posts will be displayed. Settings above will be ignored. <a href="http://codex.wordpress.org/FAQ_Working_with_WordPress#How_do_I_determine_a_Post.2C_Page.2C_Category.2C_Tag.2C_Link.2C_Link_Category.2C_or_User_ID.3F" target="_blank">How do I find an ID?</a></small>
					</p>

					<p class="mb-post-not-in mb-feature-many <?php echo ( ! $instance['feature_many'] ) ? 'mb-hidden' : false; ?>">
						<?php // Post Not In (posts to specifically exclude) ?>
						<label for="<?php echo $this->get_field_id( 'post__not_in' ); ?>"><strong>Exclude Posts</strong></label>
						<br />
						<input type="text" id="<?php echo $this->get_field_id( 'post__not_in' ); ?>" name="<?php echo $this->get_field_name( 'post__not_in' ); ?>" value="<?php echo esc_attr( $instance['query_args']['post__not_in'] ); ?>" />
						<br />
						<small class="description mb-description">Comma separated list of post IDs. Will display all posts based on settings above, except those in this list. <a href="http://codex.wordpress.org/FAQ_Working_with_WordPress#How_do_I_determine_a_Post.2C_Page.2C_Category.2C_Tag.2C_Link.2C_Link_Category.2C_or_User_ID.3F" target="_blank">How do I find an ID?</a></small>
					</p>
				</div>
			</div>

			<div class="minimize-block-column minimize-block-second-column">
				<div class="minimize-block-section minimize-block-section-display">
					<h3>Display Settings</h3>

					<p class="mb-hide-widget-title">
						<?php // Hide Widget Title ?>
						<input id="<?php echo $this->get_field_id( 'hide_title' ); ?>" name="<?php echo $this->get_field_name( 'hide_title' ); ?>" type="checkbox" <?php checked( $instance['hide_title'], true ); ?> />
						<label for="<?php echo $this->get_field_id( 'hide_title' ) ; ?>"><strong>Hide Widget Title</strong></label>
					</p>

					<p class="mb-hide-post-title">
						<?php // Hide Post Title ?>
						<input id="<?php echo $this->get_field_id( 'hide_post_title' ); ?>" name="<?php echo $this->get_field_name( 'hide_post_title' ); ?>" type="checkbox" <?php checked( $instance['hide_post_title'], true ); ?> />
						<label for="<?php echo $this->get_field_id( 'hide_post_title' ) ; ?>"><strong>Hide Post Title(s)</strong></label>
					</p>

					<p class="mb-show-post-thumbnails">
						<?php // Show Featured Image ?>
						<input id="<?php echo $this->get_field_id( 'show_post_thumbnails' ); ?>"  name="<?php echo $this->get_field_name( 'show_post_thumbnails' ); ?>" type="checkbox" <?php checked( $instance['show_post_thumbnails'], true ); ?> />
						<label for="<?php echo $this->get_field_id( 'show_post_thumbnails' ) ; ?>"><strong>Show Featured Image(s)</strong></label>
					</p>

					<p class="mb-post-thumbnails-size">
						<?php // Featured Image Size ?>
						<label for="<?php echo $this->get_field_id( 'post_thumbnails_size' ); ?>"><strong>Featured Image Size</strong></label>
						<br />
						<select name="<?php echo $this->get_field_name( 'post_thumbnails_size' ); ?>" id="<?php echo $this->get_field_id( 'post_thumbnails_size' ); ?>" class="mb-select">
							<option value="">Select A Size</option>
							<?php
								// Get all of the available image sizes
								if ( ! $avail_image_sizes = wp_cache_get( 'avail_image_sizes', 'minimize-block-widget' ) ) {
									$avail_image_sizes = array();
									foreach( get_intermediate_image_sizes() as $size ) {
										$avail_image_sizes[ $size ] = array( 0, 0 );

										// Built-in Image Sizes
										if( in_array( $size, array( 'thumbnail', 'medium', 'large' ) ) ) {
											$avail_image_sizes[ $size ][0] = get_option( $size . '_size_w' );
											$avail_image_sizes[ $size ][1] = get_option( $size . '_size_h' );
										}
										// Additional Image Sizes
										else if ( isset( $_wp_additional_image_sizes ) && isset( $_wp_additional_image_sizes[ $size ] ) )
											$avail_image_sizes[ $size ] = array( $_wp_additional_image_sizes[ $size ]['width'], $_wp_additional_image_sizes[ $size ]['height'] );
									}
									
									wp_cache_add( 'avail_image_sizes', $avail_image_sizes, 'minimize-block-widget' ); // Store cache
								}

								foreach( $avail_image_sizes as $size => $atts ) :
								?>
									<option value="<?php echo esc_attr( $size ); ?>" <?php selected( $instance['post_thumbnails_size'], $size ); ?>><?php echo $size . ' (' . implode( 'x', $atts ) . ')'; ?></option>
								<?php
								endforeach;
							?>
						</select>
						<small class="description mb-description">Featured Images are typically displayed based on the Widget Size option but you can choose a specific size if you'd like.</small>
					</p>

					<p class="mb-excerpt-length">
						<?php // Read More Link Label ?>
						<label for="<?php echo $this->get_field_id( 'excerpt_length' ); ?>"><strong>Limit content to
							<input type="text" class="mb-input mb-inline-input" id="<?php echo $this->get_field_id( 'excerpt_length' ); ?>" name="<?php echo $this->get_field_name( 'excerpt_length' ); ?>" value="<?php echo esc_attr( $instance['excerpt_length'] ); ?>" />
							words.
						</strong></label>
					</p>

					<p class="mb-read-more-label">
						<?php // Read More Link Label ?>
						<label for="<?php echo $this->get_field_id( 'read_more_label' ); ?>"><strong>Read More Link Label</strong></label>
						<br />
						<input type="text" class="mb-input" id="<?php echo $this->get_field_id( 'read_more_label' ); ?>" name="<?php echo $this->get_field_name( 'read_more_label' ); ?>" value="<?php echo esc_attr( $instance['read_more_label'] ); ?>" />
					</p>

					<p class="mb-hide-read-more">
						<?php // Hide Read More Link ?>
						<input id="<?php echo $this->get_field_id( 'hide_read_more' ); ?>"  name="<?php echo $this->get_field_name( 'hide_read_more' ); ?>" type="checkbox" <?php checked( $instance['hide_read_more'], true ); ?> />
						<label for="<?php echo $this->get_field_id( 'hide_read_more' ) ; ?>"><strong>Hide Read More Link</strong></label>
					</p>
				</div>

				<div class="minimize-block-section minimize-block-section-top minimize-block-section-advanced">
					<h3>Advanced/Other Settings</h3>

					<?php do_action( 'mb_widget_advanced_section_start', $this, $instance ); ?>

					<p class="mb-css-class">
						<?php // CSS Class ?>
						<label for="<?php echo $this->get_field_id( 'css_class' ); ?>"><strong>CSS Class(es)</strong></label>
						<br />
						<input type="text" class="mb-input" id="<?php echo $this->get_field_id( 'css_class' ); ?>" name="<?php echo $this->get_field_name( 'css_class' ); ?>" value="<?php echo esc_attr( $instance['css_class'] ); ?>" />
						<br />
						<small class="description mb-description">Space separated list of custom CSS classes which can be used to target this widget on the front-end.</small>
					</p>

					<?php do_action( 'mb_widget_advanced_section_end', $this, $instance ); ?> 
				</div>
			</div>

			<div class="clear"></div>

			<p class="mb-widget-slug">
				Content management brought to you by <a href="http://slocumstudio.com?utm_source=<?php echo home_url(); ?>&utm_medium=minimize-block-plugs&utm_campaign=MinimizeBlocks" target="_blank">Slocum Studio</a>
			</p>

		<?php
		}

		/**
		 * This function handles updating (saving) widget options
		 */
		function update( $new_instance, $old_instance ) {
			// Sanitize all input data
			$new_instance['title'] = ( ! empty( $new_instance['title'] ) ) ? sanitize_text_field( $new_instance['title'] ) : false; // Widget Title
			$new_instance['widget_size'] = ( ! empty( $new_instance['widget_size'] ) ) ? sanitize_text_field( $new_instance['widget_size'] ) : 'large'; // Widget Size (default to large)

			// General
			$new_instance['feature_many'] = ( ! empty( $new_instance['feature_many'] ) ) ? true : false; // Feature Many

			// Feature One
			$new_instance['post_id'] = ( ! $new_instance['feature_many'] && ! empty( $new_instance['post_id'] ) ) ? abs( ( int ) $new_instance['post_id'] ) : false; // Post ID (feature one)

			// Feature Many
			$new_instance['query_args']['post_type'] = ( $new_instance['feature_many'] && ! empty( $new_instance['post_type'] ) ) ? sanitize_text_field( $new_instance['post_type'] ) : 'post'; // Post Type
			$new_instance['query_args']['cat'] = ( $new_instance['feature_many'] && ! empty( $new_instance['cat'] ) ) ? abs( ( int ) $new_instance['cat'] ) : false; // Category ID
			$new_instance['query_args']['orderby'] = ( $new_instance['orderby'] && ! empty( $new_instance['orderby'] ) ) ? sanitize_text_field( $new_instance['orderby'] ) : 'date'; // Order By
			$new_instance['query_args']['order'] = ( $new_instance['orderby'] && ! empty( $new_instance['order'] ) ) ? sanitize_text_field( $new_instance['order'] ) : 'DESC'; // Order
			$new_instance['query_args']['posts_per_page'] = ( $new_instance['feature_many'] && ! empty( $new_instance['posts_per_page'] ) ) ? abs( ( int ) $new_instance['posts_per_page'] ) : get_option( 'posts_per_page' ); // Number of Posts
			$new_instance['query_args']['offset'] = ( $new_instance['feature_many'] && ! empty( $new_instance['offset'] ) ) ? abs( ( int ) $new_instance['offset'] ) : 1; // Offset

			// Post In
			if ( $new_instance['feature_many'] && ! empty( $new_instance['post__in'] ) ) {
				// Keep only digits and commas
				preg_match_all( '/\d+(?:,\d+)*/', $new_instance['post__in'], $new_instance['query_args']['post__in'] );
				$new_instance['query_args']['post__in'] = implode( ',', $new_instance['query_args']['post__in'][0] );
			}
			else
				$new_instance['query_args']['post__in'] = false;

			// Post Not In
			if ( $new_instance['feature_many'] && ! empty( $new_instance['post__not_in'] ) ) {
				// Keep only digits and commas
				preg_match_all( '/\d+(?:,\d+)*/', $new_instance['post__not_in'], $new_instance['query_args']['post__not_in'] );
				$new_instance['query_args']['post__not_in'] = implode( ',', $new_instance['query_args']['post__not_in'][0] );
			}
			else
				$new_instance['query_args']['post__not_in'] = false;

			// Display
			$new_instance['hide_title'] = ( isset( $new_instance['hide_title'] ) ) ? true : false; // Hide Widget Title
			$new_instance['hide_post_title'] = ( isset( $new_instance['hide_post_title'] ) ) ? true : false; // Hide Post Title
			$new_instance['show_post_thumbnails'] = ( isset( $new_instance['show_post_thumbnails'] ) ) ? true : false; // Featured Images
			$new_instance['post_thumbnails_size'] = ( ! empty( $new_instance['post_thumbnails_size'] ) ) ? sanitize_text_field( $new_instance['post_thumbnails_size'] ) : false; // Post Thumbnails Size
			$new_instance['read_more_label'] = ( ! empty( $new_instance['read_more_label'] ) ) ? sanitize_text_field( $new_instance['read_more_label'] ) : 'Read More'; // Read More Link Label (default to Read More)
			$new_instance['hide_read_more'] = ( isset( $new_instance['hide_read_more'] ) ) ? true : false; // Hide Read More Link

			// Advanced
			if ( ! empty( $new_instance['css_class'] ) ) {
				// Split classes
				$new_instance['css_class'] = explode( ' ', $new_instance['css_class'] );

				foreach( $new_instance['css_class'] as &$css_class )
					$css_class = sanitize_title( $css_class );

				$new_instance['css_class'] = implode( ' ', $new_instance['css_class'] );
			}
			else
				$new_instance['css_class'] = false;

			return apply_filters( 'mb_widget_update', $new_instance, $old_instance );
		}

		/**
		 * This function controls the display of the widget on the website
		 */
		function widget( $args, $instance ) {
			global $post;

			extract( $args ); // $before_widget, $after_widget, $before_title, $after_title

			// Start of widget output
			echo $before_widget;

			// Feature One
			if ( isset( $instance['post_id'] ) && ! empty( $instance['post_id'] ) ) :
				// Get "featured" post
				$featured_post = get_post( $instance['post_id'] );

				if ( ! empty( $featured_post ) ) :
					$post_classes = 'block-widget-single minimize-block-widget-single minimize-block-widget-single-' . $instance['widget_size'] . ' ' . $instance['widget_size'];
					$post_classes .= ( ! empty( $instance['css_class'] ) ) ? ' ' . str_replace( '.', '', $instance['css_class'] ) : false;

					// Display single post
					$this->display_post( $instance, $args, $featured_post, $post_classes, true );
				endif;

			// Feature Many
			elseif ( ! empty( $instance['query_args']['post_type'] ) && ( ( int ) $instance['query_args']['posts_per_page'] !== 0 || ! empty( $instance['query_args']['post__in']) ) ) :
				/**
				 * Set up query arguments
				 */
				$mb_featured_content_args = array(
					'ignore_sticky_posts' => true,
					'post_type' => $instance['query_args']['post_type'],
					'cat' => $instance['query_args']['cat'],
					'orderby' => $instance['query_args']['orderby'],
					'order' => $instance['query_args']['order'],
					'posts_per_page' => $instance['query_args']['posts_per_page'],
					'offset' => ( $instance['query_args']['offset'] > 1 ) ? ( $instance['query_args']['offset'] - 1 ) : 0
				);

				// If a posts should be excluded (and none to be included)
				if ( ! empty( $instance['query_args']['post__not_in'] ) && empty( $instance['query_args']['post__in'] ) )
					$mb_featured_content_args['post__not_in'] = explode( ',', $instance['query_args']['post__not_in'] );

				// If a posts should be included
				if ( ! empty( $instance['post__in'] ) ) {
					$mb_featured_content_args['post__in'] = ( strpos($instance['query_args']['post__in'], ',' ) !== false ) ? explode( ',', $instance['query_args']['post__in'] ) : ( array ) $instance['query_args']['post__in'];
					$mb_featured_content_args['post_type'] = get_post_types( array( 'public' => true ), 'names' ); // All post types
					$mb_featured_content_args['orderby'] = 'post__in'; // Order by order of posts specified by user
					unset( $mb_featured_content_args['post__not_in'] ); // Ignore excluded posts
					unset( $mb_featured_content_args['posts_per_page'] ); // Ignore posts per page
				}

				// Allow filtering of query arguments
				$mb_featured_content_args = apply_filters( 'mb_widget_feature_many_query_args', $mb_featured_content_args, $instance );

				$mb_featured_content_query = new WP_Query( $mb_featured_content_args );

				// Display featured content
				if ( $mb_featured_content_query->have_posts() ) :
					do_action( 'mb_widget_before_widget_title', $instance, $mb_featured_content_query->post );

					// Widget Title
					if ( isset( $instance['title'] ) && ! empty( $instance['title'] ) && ( ! isset( $instance['hide_title'] ) || ! $instance['hide_title'] ) )
						echo $before_title . apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base ) . $after_title;

					do_action( 'mb_widget_after_widget_title', $instance, $mb_featured_content_query->post );

					while ( $mb_featured_content_query->have_posts() ) : $mb_featured_content_query->next_post();
						$post_classes = ( ( $mb_featured_content_query->current_post + 1 ) % 2 === 0 ) ? 'minimize-block-widget-even block-widget-' . $instance['widget_size'] . '-even' : 'minimize-block-widget-odd block-widget-' . $instance['widget_size'] . '-odd';
						$post_classes .= ' block-widget minimize-block-widget-' . $instance['widget_size'];
						$post_classes .= ' ' . $instance['widget_size'];
						$post_classes .= ( ! empty( $instance['css_class'] ) ) ? ' ' . str_replace( '.', '', $instance['css_class'] ) : false;

						// Display post
						$this->display_post( $instance, $args, $mb_featured_content_query->post, $post_classes );
					endwhile;
				endif;
			endif;

			// End of widget output
			echo $after_widget;
		}

		/**
		 * This function enqueues the necessary styles associated with this widget on admin.
		 */
		 function admin_enqueue_scripts( $hook ) {
			// Only on Widgets Admin Page
			if ( $hook === 'widgets.php' ) {
				wp_enqueue_script( 'minimize-block-admin-js', MB_PLUGIN_URL . '/includes/widgets/minimize-block/js/minimize-block-admin.js', array( 'jquery' ) );
				wp_enqueue_style( 'minimize-block-admin', MB_PLUGIN_URL . '/includes/widgets/minimize-block/css/minimize-block-admin.css' );
			}
		 }

		/**
		 * This function enqueues the necessary styles associated with this widget.
		 */
		function wp_enqueue_scripts() {
			// Only enqueue styles if this widget is active
			if ( is_active_widget( false, false, $this->id_base, true ) )
				wp_enqueue_style( 'minimize-block', MB_PLUGIN_URL . 'includes/widgets/minimize-block/css/minimize-block.css' );
		}


		/**
		 * ------------------
		 * Internal Functions
		 * ------------------
		 */

		/**
		 * This function gets the excerpt of a specific post ID or object.
		 */
		function get_excerpt_by_id( $post, $length = 55, $tags = '', $extra = '...' ) {
			// Get the post object of the passed ID
			if( is_int( $post ) )
				$post = get_post( $post );
			else if( ! is_object( $post ) )
				return false;

			if ( post_password_required( $post ) )
				return get_the_password_form( $post );

			$the_excerpt = ( has_excerpt( $post->ID ) ) ? $post->post_excerpt : $post->post_content;
			$the_excerpt = strip_shortcodes( strip_tags( $the_excerpt ), $tags );
			$the_excerpt = preg_split( '/\b/', $the_excerpt, $length * 2 + 1 );

			array_pop( $the_excerpt );
			$the_excerpt = implode( $the_excerpt );
			$the_excerpt = ( ! empty( $the_excerpt ) ) ? $the_excerpt . $extra : false;
		 
			return apply_filters( 'the_content', $the_excerpt );
		}

		/**
		 * This function handles the display of posts on widget output.
		 */
		function display_post( $instance, $args, $post, $post_classes = '', $single = false ) {
			extract( $args ); // $before_widget, $after_widget, $before_title, $after_title

			do_action( 'mb_widget_before_widget', $instance, $post );
		?>
			<section class="<?php echo implode( ' ', get_post_class( $post_classes, $post->ID ) ); ?>">
				<?php
					if ( $single ) :
						do_action( 'mb_widget_before_widget_title', $instance, $post );

						// Widget Title
						if ( ! empty( $instance['title'] ) && ( ! isset( $instance['hide_title'] ) || ( isset( $instance['hide_title'] ) && ! $instance['hide_title'] ) ) )
							echo $before_title . apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base ) . $after_title;

						do_action( 'mb_widget_after_widget_title', $instance, $post );
					endif;
				?>

				<?php do_action( 'mb_widget_before_post_content_output', $instance, $post ); ?>

				<?php if( $instance['show_post_thumbnails'] && has_post_thumbnail( $post->ID ) ) : // Featured Image ?>
					<?php do_action( 'mb_widget_before_post_thumbnail', $instance, $post ); ?>
					<section class="thumbnail post-thumbnail featured-image">
						<a href="<?php echo get_permalink( $post->ID ); ?>">
							<?php
								// Output desired featured image size
								if ( ! empty( $instance['post_thumbnails_size'] ) )
									$mb_thumbnail_size = $instance['post_thumbnails_size'];
								else
									$mb_thumbnail_size = ( $instance['widget_size'] !== 'small' ) ? $instance['widget_size'] : 'thumbnail';

								$mb_thumbnail_size = apply_filters( 'mb_widget_post_thumbnail_size', $mb_thumbnail_size, $instance, $post );

								echo get_the_post_thumbnail( $post->ID, $mb_thumbnail_size );
							?>
						</a>
					</section>
					<?php do_action( 'mb_widget_after_post_thumbnail', $instance, $post ); ?>
				<?php endif; ?>

				<section class="content post-content <?php echo ( $instance['show_post_thumbnails'] && has_post_thumbnail( $post->ID ) ) ? 'has-post-thumbnail content-has-post-thumbnail' : false; ?>">
					<?php if ( ! $instance['hide_post_title'] ) : ?>
						<?php do_action( 'mb_widget_before_post_title', $instance, $post ); ?>
						<h3 class="post-title"><a href="<?php echo get_permalink( $post->ID ); ?>"><?php echo get_the_title( $post->ID ); ?></a></h3>
						<?php do_action( 'mb_widget_after_post_title', $instance, $post ); ?>
					<?php endif; ?>

					<?php
						do_action( 'mb_widget_before_post_content', $instance, $post );
						echo $this->get_excerpt_by_id( $post, $instance['excerpt_length'] );
						do_action( 'mb_widget_after_post_content', $instance, $post );
					?>

					<?php if ( ! $instance['hide_read_more'] ) : ?>
						<?php do_action( 'mb_widget_before_read_more', $instance, $post ); ?>
						<a class="more read-more more-link" href="<?php echo get_permalink( $post->ID ); ?>"><?php echo $instance['read_more_label']; ?></a>
						<?php do_action( 'mb_widget_after_read_more', $instance, $post ); ?>
					<?php endif; ?>
				</section>

				<?php do_action( 'mb_widget_after_post_content_output', $instance, $post ); ?>
			</section>

		<?php
			do_action( 'mb_widget_after_widget', $instance, $post );
		}
	}

	function Minimize_Block_Widget_Instance() {
		return Minimize_Block_Widget::instance();
	}

	// Start Minimize Blocks
	Minimize_Block_Widget_Instance();
}