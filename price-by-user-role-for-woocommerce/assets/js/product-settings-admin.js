jQuery( document ).ready(
	function() {

			jQuery( '#alg_wc_price_by_user_role_per_product_settings_enabled' ).on(
				'change',
				function() {

					if ( jQuery( '#alg_wc_price_by_user_role_per_product_settings_enabled' ).val() == 'yes' ) {
						 jQuery( '.price_by_roles_display' ).show();
					} else {
						 jQuery( '.price_by_roles_display' ).hide();
					}
				}
			);
	}
);
