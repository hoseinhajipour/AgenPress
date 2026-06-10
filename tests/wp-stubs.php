<?php
/**
 * Minimal WordPress function stubs for offline smoke tests.
 *
 * @package AgenPress
 */

function get_option( $option, $default = false ) {
	global $wp_options;
	return $wp_options[ $option ] ?? $default;
}

function update_option( $option, $value ) {
	global $wp_options;
	$wp_options[ $option ] = $value;
	return true;
}

function add_option( $option, $value ) {
	global $wp_options;
	if ( isset( $wp_options[ $option ] ) ) {
		return false;
	}
	$wp_options[ $option ] = $value;
	return true;
}

function delete_option( $option ) {
	global $wp_options;
	unset( $wp_options[ $option ] );
}

function sanitize_text_field( $str ) {
	return trim( strip_tags( (string) $str ) );
}

function sanitize_textarea_field( $str ) {
	return trim( (string) $str );
}

function sanitize_file_name( $filename ) {
	return preg_replace( '/[^a-zA-Z0-9._-]/', '', $filename );
}

function wp_kses_post( $data ) {
	return (string) $data;
}

function wp_json_encode( $data ) {
	return json_encode( $data );
}

function current_time( $type, $gmt = 0 ) {
	return gmdate( 'Y-m-d H:i:s' );
}

function get_bloginfo( $show = '' ) {
	$map = array(
		'name'        => 'Test Site',
		'description' => 'Test Description',
		'language'    => 'en-US',
		'version'     => '6.7',
	);
	return $map[ $show ] ?? '';
}

function get_site_url() {
	return 'https://example.com';
}

function wp_count_posts( $type = 'post' ) {
	return (object) array( 'publish' => 5 );
}

function get_posts( $args = array() ) {
	return array();
}

function get_permalink( $post ) {
	return 'https://example.com/post/1';
}

function user_can( $user_id, $cap ) {
	return true;
}

$wp_options = array();
