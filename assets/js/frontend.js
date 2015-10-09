jQuery( document ).ready( function ($) {

	$( 'form.edit-registration' ).submit( function(e) {

		var field_id;
		var input_val;
		var errors = new Array();
		var valid_field;
		var c = 0;

		$( 'label.required-field' ).each( function() {
			valid_field = false;
			field_id = $( this ).attr( 'for' );

			if( ( $( '#' + field_id ).is( 'input' ) && 'text' == $( '#' + field_id ).attr( 'type' ) ) || $( '#' + field_id ).is( 'select' ) ) {
				input_val = $( '#' + field_id ).val();
				if( input_val ) {
					valid_field = true;
				}
			}

			$( 'input.' + field_id ).each( function() {
				if( $( this ).is( ':checked' ) ) {
					valid_field = true;
				}
			});

			if( ! valid_field ) {
				errors[c] = field_id;
				++c;
			}

		});

		$('p').removeClass( 'error' );

		if( ! errors.length ) {
			return;
		}

		var field;
		for( var i = 0; i < errors.length; i++ ) {
		    field = errors[i];
		    $( '#' + field ).closest( 'p' ).addClass( 'error' );
		    $( '.' + field ).closest( 'p' ).addClass( 'error' );
		}

		e.preventDefault();
	});

	if( $( '#registration-print-content-container' ).length ) {

		var registration = $( '#registration-print-content-container' ).html();

		$( 'body' ).empty();
		$( 'body' ).html( registration );

		imagesLoaded( '#registration-print-content', function() {
			window.print();
		} );
	}

	if( $( '#registration-scan-form' ).length ) {

		// Focus on barcode input field
		$( '#registration-scan-form input#scan-code' ).focus( function() {
			$( this ).select();
		});

		// Detect if USB scanner has been used and submit automatically
		$( '#registration-scan-form input#scan-code' ).scannerDetection( function() {
			$( '#registration-scan-form form' ).submit();
		});

		// Handle form submission
		$( '#registration-scan-form form' ).submit( function(e) {
			e.preventDefault();

			// Show loading text
			$( '#registration-scan-loader' ).show();

			// Empty existing results
			$( '#registration-scan-result' ).html('');

			var input = $( '#registration-scan-form input#scan-code' ).val();
			var input_action = $( '#registration-scan-form #scan-action' ).val();

			$.post(
				wc_order_barcodes.ajaxurl,
				{
					action: 'scan_registration',
					barcode_input: input,
					scan_action: input_action,
					woocommerce_box_office_scan_nonce: wc_box_office.scan_nonce
				}
			).done( function( response ) {

				// Focus on registration barcode input field
				$( '#registration-scan-form input#scan-code' ).focus();

				if( ! response ) {
					return;
				}

				// Hide loading text
				$( '#registration-scan-loader' ).hide();

				// Display response
				$( '#registration-scan-result' ).html( response );

			});

		});
	}

	if( $( '#registration-checkin-form' ).length ) {

		// Handle form submission
		$( '#registration-checkin-form' ).on( 'click', 'button.edit', function(e) {
			e.preventDefault();


			var input = $(this).data('barcode');
			var input_action = 'checkin';

			$.post(
				wc_order_barcodes.ajaxurl,
				{
					action: 'scan_registration',
					barcode_input: input,
					scan_action: input_action,
					woocommerce_box_office_scan_nonce: wc_box_office.scan_nonce
				}
			).done( function( response ) {

				if( ! response ) {
					return;
				}

				// Display response
				$( '#registration-checkin-form' ).html( response );

			});

		});
	}

});