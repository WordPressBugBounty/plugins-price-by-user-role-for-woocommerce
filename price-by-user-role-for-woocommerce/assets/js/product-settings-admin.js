jQuery( document ).ready( function( $ ) {
	$( '#pbur-metabox-enabled' ).on( 'change', function() {
		if ( $( this ).is( ':checked' ) ) {
			$( '#pbur-metabox-pricing' ).show();
		} else {
			$( '#pbur-metabox-pricing' ).hide();
		}
	} );
} );
