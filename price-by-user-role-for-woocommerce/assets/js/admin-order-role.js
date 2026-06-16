/* global alg_wc_pbur */
jQuery( document ).ready( function ( $ ) {
	'use strict';

	var $checkbox   = $( '#checkbox_pbur' );
	var $roleSelect = $( '#alg_wc_pbur_select_role' );
	var $roleLabel  = $( 'label[for="alg_wc_pbur_select_role"]' );

	function toggleRoleRow() {
		if ( $checkbox.is( ':checked' ) ) {
			$roleLabel.show();
			$roleSelect.show();
		} else {
			$roleLabel.hide();
			$roleSelect.hide();
		}
	}

	function post( action, extra ) {
		var data = $.extend(
			{
				action:   action,
				order_id: alg_wc_pbur.order_id,
				nonce:    alg_wc_pbur.nonce,
			},
			extra
		);
		$.post( alg_wc_pbur.ajax_url, data );
	}

	// Initialise visibility.
	toggleRoleRow();

	// Toggle role row when checkbox changes.
	$checkbox.on( 'change', function () {
		toggleRoleRow();
		post( 'alg_wc_pbur_checkbox_value', {
			pbur_check: $checkbox.is( ':checked' ) ? 'true' : 'false',
		} );
	} );

	// Save selected role when dropdown changes.
	$roleSelect.on( 'change', function () {
		post( 'alg_wc_pbur_order_role', {
			admin_choice: $roleSelect.val(),
			pbur_check:   $checkbox.is( ':checked' ) ? 'true' : 'false',
		} );
	} );

	// Inject role/checkbox into WooCommerce's Recalculate POST so the PHP hook
	// always has the current values — avoids a race condition with the save AJAX
	// and works even when the meta box is rendered inside an iframed editor.
	$( document.body ).on( 'wc_order_items_recalculate_filter', function ( event, data ) {
		if ( ! $checkbox.length || ! $roleSelect.length ) {
			return;
		}
		data.pbur_check = $checkbox.is( ':checked' ) ? 'true' : 'false';
		data.pbur_role  = $roleSelect.val();
	} );

	// Auto-detect role when the customer field changes.
	$( '#customer_user' ).on( 'change', function () {
		var userId = $( this ).val();
		if ( ! userId ) {
			return;
		}
		$.post(
			alg_wc_pbur.ajax_url,
			{
				action:     'alg_wc_pbur_order_detect_role',
				order_id:   alg_wc_pbur.order_id,
				nonce:      alg_wc_pbur.nonce,
				pbur_check: $checkbox.is( ':checked' ) ? 'true' : 'false',
				user_id:    userId,
			},
			function ( detectedRole ) {
				detectedRole = detectedRole.replace( '0', '' );
				if ( detectedRole ) {
					$roleSelect.val( detectedRole );
					$checkbox.prop( 'checked', true );
					toggleRoleRow();
				}
			}
		);
	} );
} );
