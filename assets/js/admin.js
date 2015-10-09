jQuery( document ).ready( function ($) {

	function show_hide_registration_tab() {
		var is_registration = $('input#_registration:checked').size();

		$( '.show_if_registration' ).hide();

		if ( is_registration ) {
			$( '.show_if_registration' ).show();
		}
	}

	show_hide_registration_tab();

	$( 'input#_registration' ).change(function(){
		show_hide_registration_tab();
	});

	// Registration field inputs
	$('#poststuff').on('click','.registration_fields a.insert',function(){
		$(this).closest('.registration_fields').find('tbody').append( $(this).data( 'row' ) );
		return false;
	});
	$('#poststuff').on('click','.registration_fields a.delete',function(){
		$(this).closest('tr').next('tr').addBack().remove();
		return false;
	});
	$('#poststuff').on('change', '.registration_fields .field_type select', function(){

		var selected_type = $(this).val();

		show_hide_registration_options( $(this), selected_type );

		return false;
	});

	function show_hide_registration_options( el, selected_type ) {
		if( 'select' == selected_type || 'radio' == selected_type || 'checkbox' == selected_type ) {
			el.closest('tr').next('tr').find('.field_options textarea').show();
			el.closest('tr').next('tr').find('.field_options .birthdate-options').hide();
			el.closest('tr').next('tr').find('.field_options .email-options').hide();
		} else if( 'birthdate' == selected_type ) {
			el.closest('tr').next('tr').find('.field_options textarea').hide();
			el.closest('tr').next('tr').find('.field_options .birthdate-options').show();
			el.closest('tr').next('tr').find('.field_options .email-options').hide();
		} else if( 'email' == selected_type ) {
			el.closest('tr').next('tr').find('.field_options textarea').hide();
			el.closest('tr').next('tr').find('.field_options .birthdate-options').hide();
			el.closest('tr').next('tr').find('.field_options .email-options').show();
		} else {
			el.closest('tr').next('tr').find('.field_options textarea').hide()
			el.closest('tr').next('tr').find('.field_options .birthdate-options').hide();
			el.closest('tr').next('tr').find('.field_options .email-options').hide();
		}
	}
	
	$('#select-all-classes').on('click', function(e){
		$('#registration-email-products > option').attr('selected','selected').trigger( 'chosen:updated' );;
		return false;
	});

	$( '#registration-email-advanced-toggle' ).change( function() {
		var checked = $(this).is( ':checked' );
		if( checked ) {
			$( '#registration-email-advanced-options' ).fadeTo( 'fast', 1 );
		} else {
			$( '#registration-email-advanced-options' ).fadeTo( 'fast', 0.3 );
		}
	});

	$( '#registration-email-products' ).change( function() {

		var products = $( '#registration-email-products' ).val();
		var selected = $( '#registration-email-advanced-options select.registration-product-field-select' ).val();

		$.post(
			wc_box_office_admin.ajaxurl,
			{
				action: 'get_registration_field_options',
				products: products,
				selected: selected,
				wc_box_office_admin_registration_fields_nonce: wc_box_office_admin.registration_fields_nonce
			}
		).done( function( response ) {

			if( ! response ) {
				$( '#registration-email-advanced-options select.registration-product-field-select' ).html( '<option value=""></option>' );
				return;
			}

			// Empty registration field select
			$( '#registration-email-advanced-options select.registration-product-field-select' ).empty();

			// Add response to select box content
			$( '#registration-email-advanced-options select.registration-product-field-select' ).html( response );

			// Refresh Chosen select box
			$( '#registration-email-advanced-options select.registration-product-field-select' ).trigger( 'chosen:updated' );

		});

	});

	$('.past-emails').on('click','td a.delete',function(){

		var email_id = $(this).attr('rel');

		if( ! email_id ) {
			return false;
		}

		var confirm_delete = window.confirm( wc_box_office_admin.confirm_delete );

		if( ! confirm_delete ) {
			return false;
		}

		$.post(
			wc_box_office_admin.ajaxurl,
			{
				action: 'delete_registration_email',
				email_id: email_id,
				wc_box_office_admin_delete_email_nonce: wc_box_office_admin.delete_email_nonce
			}
		);

		$(this).closest('tr').remove();

		return false;
	});

	$( 'body.post-type-event_registration .wrap .add-new-h2' ).remove();
	$( 'body.post-type-event_registration_email .wrap .add-new-h2' ).remove();

});