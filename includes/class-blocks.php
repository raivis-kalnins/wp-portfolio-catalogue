<?php
/**
 * Dynamic catalogue block and legacy block compatibility.
 *
 * @package WP_Portfolio_Catalogue
 */

namespace WP_Portfolio_Catalogue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Blocks {
	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/** Register a native block without requiring a Node build on user sites. */
	public function register() {
		wp_register_script(
			'wppc-catalogue-editor',
			WPPC_URL . 'assets/js/block-editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-data', 'wp-core-data', 'wp-i18n', 'wp-html-entities', 'wp-server-side-render' ),
			WPPC_VERSION,
			true
		);
		wp_set_script_translations( 'wppc-catalogue-editor', 'wp-portfolio-catalogue', WPPC_DIR . 'languages' );
		register_block_type( WPPC_DIR . 'blocks/catalogue', array( 'render_callback' => array( $this, 'render' ) ) );
		register_block_type(
			'post-project-gallery/selected-projects',
			array(
				'api_version' => 3,
				'editor_script' => 'wppc-catalogue-editor',
				'editor_style' => 'wppc-frontend',
				'render_callback' => array( $this, 'render_legacy' ),
				'attributes' => array(
					'projectIds' => array( 'type' => 'array', 'items' => array( 'type' => 'number' ), 'default' => array() ),
					'columns' => array( 'type' => 'number', 'default' => 3 ),
				),
				'supports' => array( 'inserter' => false, 'html' => false ),
			)
		);
	}

	/** @param array $attributes Block values. @return string */
	public function render( $attributes ) {
		$attributes = is_array( $attributes ) ? $attributes : array();
		$mapped = array();
		foreach ( array( 'columns', 'category', 'tag', 'filters', 'search', 'orderby', 'order' ) as $key ) {
			if ( isset( $attributes[ $key ] ) ) {
				$mapped[ $key ] = $attributes[ $key ];
			}
		}
		if ( isset( $attributes['perPage'] ) ) {
			$mapped['per_page'] = $attributes['perPage'];
		}
		if ( isset( $attributes['excerptWords'] ) ) {
			$mapped['excerpt_words'] = $attributes['excerptWords'];
		}
		if ( ! empty( $attributes['projectIds'] ) && is_array( $attributes['projectIds'] ) ) {
			$mapped['ids'] = implode( ',', array_map( 'absint', array_filter( $attributes['projectIds'], 'is_scalar' ) ) );
		}
		return '<div ' . get_block_wrapper_attributes() . '>' . Frontend::render_catalogue( $mapped ) . '</div>';
	}

	/** @param array $attributes Saved legacy values. @return string */
	public function render_legacy( $attributes ) {
		if ( empty( $attributes['projectIds'] ) || ! is_array( $attributes['projectIds'] ) ) {
			return '';
		}
		$ids = array_values( array_filter( array_map( 'absint', array_filter( $attributes['projectIds'], 'is_scalar' ) ) ) );
		if ( ! $ids ) {
			return '';
		}
		return Frontend::render_catalogue(
			array(
				'ids' => implode( ',', $ids ),
				'columns' => isset( $attributes['columns'] ) ? $attributes['columns'] : 3,
				'per_page' => min( 48, count( $ids ) ),
				'filters' => false,
				'search' => false,
			)
		);
	}
}
