<?php
/**
 * CSV Toolbox Functions
 */


/**
 * AJAX
 *
 * @see http://wordpress.stackexchange.com/questions/58834/echo-all-meta-keys-of-a-custom-post-type
 *
 * @return string
 */
function give_csv_toolbox_get_custom_fields() {

	global $wpdb;
	$post_type = 'give_payment';

	$form_id = isset( $_POST['form_id'] ) ? intval( $_POST['form_id'] ) : '';

	if ( empty( $form_id ) ) {
		return false;
	}

	$ffm_field_array          = array();
	$non_multicolumn_ffm_keys = array();

	// Is FFM available? Take care of repeater fields.
	if ( class_exists( 'Give_FFM_Render_Form' ) ) {

		// Get the custom fields for the payment's form.
		$ffm = new Give_FFM_Render_Form();
		list( $post_fields, $taxonomy_fields, $custom_fields ) = $ffm->get_input_fields( $form_id );

		foreach ( $custom_fields as $field ) {

			// Assemble multi-column repeater fields.
			if (
				isset( $field['multiple'] )
				&& 'repeat' === $field['input_type']
			) {
				$non_multicolumn_ffm_keys[] = $field['name'];

				foreach ( $field['columns'] as $column ) {

					// All other fields.
					$ffm_field_array['repeaters'][] = array(
						'subkey'      => give_csv_toolbox_create_column_key( $column ),
						'metakey'     => $field['name'],
						'label'       => $column,
						'multi'       => 'true',
						'parent_meta' => $field['name'],
						'parent_title' => $field['label'],
					);
				}
			} else {
				// All other fields.
				$ffm_field_array['single'][] = array(
					'subkey'  => $field['name'],
					'metakey' => $field['name'],
					'label'   => $field['label'],
					'multi'   => 'false',
					'parent'  => '',
				);
				$non_multicolumn_ffm_keys[]  = $field['name'];
			}
		}
	}

	$args          = array(
		'give_forms'     => array( $form_id ),
		'posts_per_page' => - 1,
		'fields'         => 'ids',
	);
	$donation_list = implode( '\',\'', (array) give_get_payments( $args ) );

	$query = "
        SELECT DISTINCT($wpdb->postmeta.meta_key) 
        FROM $wpdb->posts 
        LEFT JOIN $wpdb->postmeta 
        ON $wpdb->posts.ID = $wpdb->postmeta.post_id
        WHERE $wpdb->posts.post_type = '%s'
        AND $wpdb->posts.ID IN (%s)
        AND $wpdb->postmeta.meta_key != '' 
        AND $wpdb->postmeta.meta_key NOT RegExp '(^[_0-9].+$)'
    ";

	$meta_keys = $wpdb->get_col( $wpdb->prepare( $query, $post_type, $donation_list ) );

	// Unset ignored FFM keys.
	foreach ( $non_multicolumn_ffm_keys as $key ) {
		if ( ( $key = array_search( $key, $meta_keys ) ) !== false ) {
			unset( $meta_keys[ $key ] );
		}
	}

	$query = "
        SELECT DISTINCT($wpdb->postmeta.meta_key) 
        FROM $wpdb->posts 
        LEFT JOIN $wpdb->postmeta 
        ON $wpdb->posts.ID = $wpdb->postmeta.post_id 
        WHERE $wpdb->posts.post_type = '%s' 
        AND $wpdb->posts.ID IN (%s)
        AND $wpdb->postmeta.meta_key != '' 
        AND $wpdb->postmeta.meta_key NOT RegExp '^[^_]'
    ";

	$hidden_meta_keys   = $wpdb->get_col( $wpdb->prepare( $query, $post_type, $donation_list ) );
	$ignore_hidden_keys = array(
		'_give_payment_meta',
		'_give_payment_gateway',
		'_give_payment_form_title',
		'_give_payment_form_id',
		'_give_payment_price_id',
		'_give_payment_user_id',
		'_give_payment_user_email',
		'_give_payment_user_ip',
		'_give_payment_mode',
		'_give_payment_customer_id',
		'_give_payment_total',
		'_give-form-fields_id',
		'_give_completed_date',
	);

	// Unset ignored FFM keys.
	foreach ( $ignore_hidden_keys as $key ) {
		if ( ( $key = array_search( $key, $hidden_meta_keys ) ) !== false ) {
			unset( $hidden_meta_keys[ $key ] );
		}
	}

	wp_send_json( array(
		'ffm_fields'      => $ffm_field_array,
		'standard_fields' => $meta_keys,
		'hidden_fields'   => $hidden_meta_keys,
	) );

	give_die();

}

add_action( 'wp_ajax_give_csv_toolbox_get_custom_fields', 'give_csv_toolbox_get_custom_fields' );


/**
 * Register the payments batch exporter
 *
 * @since  1.0
 */
function give_register_csv_toolbox_batch_export() {
	add_action( 'give_batch_export_class_include', 'give_csv_toolbox_include_export_class', 10, 1 );
}

add_action( 'give_register_batch_exporter', 'give_register_csv_toolbox_batch_export', 10 );


/**
 * Includes the CSV Toolbox Custom Exporter Class.
 *
 * @param $class Give_CSV_Toolbox_Donations_Export
 */
function give_csv_toolbox_include_export_class( $class ) {
	if ( 'Give_CSV_Toolbox_Donations_Export' === $class ) {
		require_once GIVE_CSV_TOOLBOX_DIR . 'includes/give-csv-toolbox-exporter.php';
	}
}


/**
 * Create column key.
 *
 * @param $string
 *
 * @return string
 */
function give_csv_toolbox_create_column_key( $string ) {
	return sanitize_key( str_replace( ' ', '_', $string ) );
}
