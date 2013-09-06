/**
 * Minimize Blocks Admin
 *
 * This script is used to alter the display of widget settings based on user selection on admin.
 */
jQuery( function( $ ) {
	// On change
	$( document ).on( 'change', '.mb-feature-content-pieces select', function() {
		var selected = $( ':selected', this ), // Get selected choice
		widget_parent = selected.parents( '.widget'); // Get widget instance

		mbRenderWidgetSettings( selected, widget_parent );
	} );
} );

function mbRenderWidgetSettings( selected, widget_parent ) {
	// Feature one
	if ( selected.val() === '' ) {
		widget_parent.find( '.mb-feature-many' ).fadeOut( 100, function() {
			widget_parent.find( '.mb-feature-one' ).fadeIn( 100 );
		} );
	}
	// Feature many
	if ( selected.val() === 'true' ) {
		widget_parent.find( '.mb-feature-one' ).fadeOut( 100, function() {
			widget_parent.find( '.mb-feature-many' ).fadeIn( 100 );
		} );
	}

	widget_parent.find( '.mb-feature-many, .mb-feature-one' ).removeClass( 'mb-hidden' ); // Remove hidden class
}