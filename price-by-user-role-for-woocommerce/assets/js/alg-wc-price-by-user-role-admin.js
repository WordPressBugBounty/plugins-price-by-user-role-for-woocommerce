jQuery( document ).ready(
	function( $ ) {
        var pbur_checkbox_for_order_role = $( '#checkbox_pbur' ).val();
        $( '#alg_wc_pbur_select_role' ).change(
            function() {
                var order_id                 = pbur_order_id.order_id;
                pbur_checkbox_for_order_role = jQuery( '#alg_wc_pbur_select_role' ).val();
                if ( $( '#checkbox_pbur' ).is( ":checked" ) ) {
                    var pbur_check  = true;
                }
                var data = {
                    order_id: order_id,
                    admin_choice: pbur_checkbox_for_order_role,
                    pbur_check: pbur_check,
                    action: 'alg_wc_pbur_order_role'
                };
                jQuery.post(
                    pbur_order_role.ajax_url,
                    data,
                    function() {
                        console.log( data );
                    }
                );
            }
        );
    }
);
