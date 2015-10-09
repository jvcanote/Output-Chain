<?php
$type_options = '';
foreach( $field_types as $k => $v ) {
	$type_options .= '<option value="' . $k . '"' . selected( $field['type'], $k, false ) . '>' . $v . '</option>' . "\n";
}

$autofill_select = '';
if( $autofill_options ) {
	$autofill_select = '<select name="_registration_field_autofill[]">' . "\n";
	$autofill_select .= '<option value="none">' . __( 'Auto-fill from:', 'woocommerce-box-office' ) . '</option>' . "\n";
	$autofill_select .= '<option value="" disabled="disabled">' . __( '--------', 'woocommerce-box-office' ) . '</option>' . "\n";
	foreach( $autofill_options as $k => $v ) {
		$autofill_select .= '<option value="' . $k . '"' . selected( $field['autofill'], $k, false ) . '>' . $v . '</option>' . "\n";
	}
	$autofill_select .= '</select>' . "\n";
	// $autofill_select .= '<br/><br/>' . "\n";
}

$options_style = '';
$options_note = 'Bar-separated list of available options';
if( ! in_array( $field['type'], array( 'select', 'radio', 'checkbox' ) ) ) {
	$options_style = 'display:none';
}

$email_style = '';
if( 'email' != $field['type'] ) {
	$email_style = 'display:none';
}

$birthdate_style = '';
$birthdate_mask = 'data-mask="00/00/0000" data-mask-clearifnotmatch="true"';
if( 'birthdate' != $field['type'] ) {
	$birthdate_mask = '';
	$birthdate_style = 'display:none';
}
?>
<tr class="<?php echo $row; ?>">
	<td class="column_view" rowspan="2">
		<span class="tips" data-tip="<?php _e( 'Display this data in tables and overviews.', 'woocommerce-box-office' ); ?>">
			<lable><input type="hidden" name="_registration_field_column[]" value="<?php echo esc_attr( $field['column'] ); ?>"><input type="checkbox" class="input_checkbox" onclick="this.previousSibling.value=('yes'==this.previousSibling.value)?'no':'yes';" <?php echo ( 'yes' == $field['column'] ) ? 'checked="checked"' : '' ; ?>></lable>
		</span>
	</td>
	<td class="field_label">
		<input type="text" class="input_text" placeholder="<?php _e( 'Field Label', 'woocommerce-box-office' ); ?>" name="_registration_field_labels[]" value="<?php echo esc_attr( $field['label'] ); ?>" <?php echo $birthdate_mask; ?> />
	</td>
	<td class="field_type">
		<select name="_registration_field_types[]"><?php echo $type_options; ?></select>
	</td>
	<td class="field_options">
		<?php echo $autofill_select; ?>		
	</td>
	<td class="field_required" width="1%">
		<select name="_registration_field_required[]">
			<option value="yes" <?php selected( $field['required'], 'yes', true ); ?>><?php _e( 'Yes', 'woocommerce-box-office' ); ?></option>
			<option value="no" <?php selected( $field['required'], 'no', true ); ?>><?php _e( 'No', 'woocommerce-box-office' ); ?></option>
		</select>	
	</td>
	<td width="1%" rowspan="2">
		<a href="#" class="delete">
			<?php _e( 'Delete', 'woocommerce-box-office' ); ?>
		</a>
	</td>
</tr>
<tr class="<?php echo $row; ?>">
	<td class="field_label">
		<!-- <input type="text" class="input_text" placeholder="<?php// _e( 'Field Key', 'woocommerce-box-office' ); ?>" value="<?php// echo esc_attr( $key ); ?>" disabled="disabled" /> -->
		<input type="hidden" class="hidden" name="_registration_field_keys[]" value="<?php echo esc_attr( $key ); ?>" />
	</td>
	<td class="field_options" colspan="3">
		<div class="birthdate-options" style="<?php echo $birthdate_style; ?>">
			<span class="tips" data-tip="<?php _e( 'Calculate and display Age in tables and views.', 'woocommerce-box-office' ); ?>">
				<lable><input type="hidden" name="_registration_field_age_column[]" value="<?php echo esc_attr( $field['age_column'] ); ?>"><input type="checkbox" class="input_checkbox" onclick="this.previousSibling.value=('yes'==this.previousSibling.value)?'no':'yes';" <?php echo ( 'yes' == $field['age_column']  ) ? 'checked="checked"' : '' ; ?>> <?php echo "Age column"; ?></lable>
			</span>
		</div>
		<textarea style="<?php echo $options_style; ?>" placeholder="<?php _e( $options_note, 'woocommerce-box-office'); ?>" name="_registration_field_options[]"><?php esc_html_e( $field['options'] ); ?></textarea>
		<div class="email-options" style="<?php echo $email_style; ?>">
			<span class="tips" data-tip="<?php _e( 'Use this email address to contact the registration holder.', 'woocommerce-box-office' ); ?>">
				<?php _e( 'Contact: ', 'woocommerce-box-office' ); ?>
				<select id="" name="_registration_field_email_contact[]">
					<option value="yes" <?php selected( $field['email_contact'], 'yes', true ); ?>><?php _e( 'Yes', 'woocommerce-box-office' ); ?></option>
					<option value="no" <?php selected( $field['email_contact'], 'no', true ); ?>><?php _e( 'No', 'woocommerce-box-office' ); ?></option>
				</select>
			</span>
			<span class="tips" data-tip="<?php _e( 'Use this email address for the registration holder\'s gravatar.', 'woocommerce-box-office' ); ?>">
				<?php _e( 'Gravatar: ', 'woocommerce-box-office' ); ?>
				<select name="_registration_field_email_gravatar[]">
					<option value="yes" <?php selected( $field['email_gravatar'], 'yes', true ); ?>><?php _e( 'Yes', 'woocommerce-box-office' ); ?></option>
					<option value="no" <?php selected( $field['email_gravatar'], 'no', true ); ?>><?php _e( 'No', 'woocommerce-box-office' ); ?></option>
				</select>
			</span>
		</div>
	</td>
</tr>