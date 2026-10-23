<?php
/**
 * Remove plugin settings and optional plugin-specific metadata only.
 * Project posts, featured images, attachments and legacy data are always kept.
 *
 * @package WP_Portfolio_Catalogue
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/** Clean the current site without removing its portfolio content. */
function wppc_uninstall_site() {
	$settings = get_option( 'wppc_settings', array() );
	if ( is_array( $settings ) && ! empty( $settings['delete_data'] ) ) {
		foreach ( array( '_wppc_details', '_wppc_gallery_ids', '_wppc_featured_only' ) as $key ) {
			delete_post_meta_by_key( $key );
		}
		foreach ( array( 'wppc_category', 'wppc_tag' ) as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				register_taxonomy( $taxonomy, 'project' );
			}
			$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term_id ) {
					wp_delete_term( $term_id, $taxonomy );
				}
			}
		}
		delete_option( 'wppc_settings' );
	}
}

if ( is_multisite() ) {
	$wppc_offset = 0;
	do {
		$wppc_sites = get_sites( array( 'fields' => 'ids', 'number' => 100, 'offset' => $wppc_offset ) );
		foreach ( $wppc_sites as $wppc_site_id ) {
			switch_to_blog( $wppc_site_id );
			wppc_uninstall_site();
			restore_current_blog();
		}
		$wppc_offset += 100;
	} while ( count( $wppc_sites ) === 100 );
} else {
	wppc_uninstall_site();
}
