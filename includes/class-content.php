<?php
/**
 * Project data, validation, and non-destructive legacy gallery compatibility.
 *
 * @package WP_Portfolio_Catalogue
 */

namespace WP_Portfolio_Catalogue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Content {
	const POST_TYPE = 'project';
	const CATEGORY  = 'wppc_category';
	const TAG       = 'wppc_tag';

	/** Register the existing project content type and namespaced taxonomies. */
	public static function register() {
		if ( ! post_type_exists( self::POST_TYPE ) ) {
			register_post_type(
				self::POST_TYPE,
				array(
					'labels' => array(
						'name'                  => __( 'Projects', 'wp-portfolio-catalogue' ),
						'singular_name'         => __( 'Project', 'wp-portfolio-catalogue' ),
						'menu_name'             => __( 'Projects', 'wp-portfolio-catalogue' ),
						'add_new'               => __( 'Add New', 'wp-portfolio-catalogue' ),
						'add_new_item'          => __( 'Add New Project', 'wp-portfolio-catalogue' ),
						'edit_item'             => __( 'Edit Project', 'wp-portfolio-catalogue' ),
						'new_item'              => __( 'New Project', 'wp-portfolio-catalogue' ),
						'view_item'             => __( 'View Project', 'wp-portfolio-catalogue' ),
						'view_items'            => __( 'View Projects', 'wp-portfolio-catalogue' ),
						'search_items'          => __( 'Search Projects', 'wp-portfolio-catalogue' ),
						'not_found'             => __( 'No projects found.', 'wp-portfolio-catalogue' ),
						'not_found_in_trash'    => __( 'No projects found in Trash.', 'wp-portfolio-catalogue' ),
						'all_items'             => __( 'All Projects', 'wp-portfolio-catalogue' ),
						'archives'              => __( 'Project Archives', 'wp-portfolio-catalogue' ),
						'attributes'            => __( 'Project Attributes', 'wp-portfolio-catalogue' ),
						'featured_image'        => __( 'Project Featured Image', 'wp-portfolio-catalogue' ),
						'set_featured_image'    => __( 'Set featured image', 'wp-portfolio-catalogue' ),
						'remove_featured_image' => __( 'Remove featured image', 'wp-portfolio-catalogue' ),
						'use_featured_image'    => __( 'Use as featured image', 'wp-portfolio-catalogue' ),
					),
					'public'       => true,
					'show_in_rest' => true,
					'rest_base'    => 'project',
					'has_archive'  => true,
					'rewrite'      => array( 'slug' => 'projects', 'with_front' => false ),
					'menu_icon'    => 'dashicons-portfolio',
					'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'page-attributes', 'custom-fields' ),
				)
			);
		}
		register_taxonomy(
			self::CATEGORY,
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'              => __( 'Project Categories', 'wp-portfolio-catalogue' ),
					'singular_name'     => __( 'Project Category', 'wp-portfolio-catalogue' ),
					'search_items'      => __( 'Search Project Categories', 'wp-portfolio-catalogue' ),
					'all_items'         => __( 'All Project Categories', 'wp-portfolio-catalogue' ),
					'parent_item'       => __( 'Parent Project Category', 'wp-portfolio-catalogue' ),
					'parent_item_colon' => __( 'Parent Project Category:', 'wp-portfolio-catalogue' ),
					'edit_item'         => __( 'Edit Project Category', 'wp-portfolio-catalogue' ),
					'update_item'       => __( 'Update Project Category', 'wp-portfolio-catalogue' ),
					'add_new_item'      => __( 'Add New Project Category', 'wp-portfolio-catalogue' ),
					'new_item_name'     => __( 'New Project Category Name', 'wp-portfolio-catalogue' ),
					'menu_name'         => __( 'Categories', 'wp-portfolio-catalogue' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'project-category', 'with_front' => false ),
			)
		);
		register_taxonomy(
			self::TAG,
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Project Tags', 'wp-portfolio-catalogue' ),
					'singular_name' => __( 'Project Tag', 'wp-portfolio-catalogue' ),
					'menu_name'     => __( 'Tags', 'wp-portfolio-catalogue' ),
				),
				'public'            => true,
				'hierarchical'      => false,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'project-tag', 'with_front' => false ),
			)
		);
		// The native edit screen saves structured fields. Keep them out of public REST metadata.
		register_post_meta( self::POST_TYPE, '_wppc_details', array( 'single' => true, 'type' => 'object', 'show_in_rest' => false, 'sanitize_callback' => array( __CLASS__, 'sanitize_details' ), 'auth_callback' => array( __CLASS__, 'authorize_meta' ) ) );
		register_post_meta( self::POST_TYPE, '_wppc_gallery_ids', array( 'single' => true, 'type' => 'array', 'show_in_rest' => false, 'sanitize_callback' => array( __CLASS__, 'sanitize_gallery' ), 'auth_callback' => array( __CLASS__, 'authorize_meta' ) ) );
		register_post_meta( self::POST_TYPE, '_wppc_featured_only', array( 'single' => true, 'type' => 'boolean', 'show_in_rest' => false, 'sanitize_callback' => 'rest_sanitize_boolean', 'auth_callback' => array( __CLASS__, 'authorize_meta' ) ) );
	}

	/** @param bool $allowed Prior authorization. @param string $key Meta key. @param int $post_id Project. @return bool */
	public static function authorize_meta( $allowed, $key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	}

	/** @return array */
	public static function detail_defaults() {
		return array(
			'client' => '', 'location' => '', 'status' => '', 'date_start' => '', 'date_end' => '',
			'contact_name' => '', 'email' => '', 'phone' => '', 'website' => '',
			'details' => array(), 'links' => array(), 'events' => array(), 'accordions' => array(),
		);
	}

	/** @param mixed $value Untrusted scalar. @param int $limit Character limit. @return string */
	private static function text( $value, $limit = 500 ) {
		$value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
	}

	/** @param mixed $value ISO date. @return string Valid date or an empty string. */
	public static function sanitize_date( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $value ) ) {
			return '';
		}
		$parts = array_map( 'intval', explode( '-', $value ) );
		return checkdate( $parts[1], $parts[2], $parts[0] ) ? $value : '';
	}

	/** @param mixed $value Website link. @return string */
	private static function url( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( strlen( $value ) > 2048 || ! preg_match( '#^https?://#i', $value ) ) {
			return '';
		}
		return esc_url_raw( $value, array( 'http', 'https' ) );
	}

	/** @param mixed $input Structured project fields, already unslashed. @return array */
	public static function sanitize_details( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$output = self::detail_defaults();
		foreach ( array( 'client', 'location', 'contact_name', 'phone' ) as $key ) {
			$output[ $key ] = self::text( isset( $input[ $key ] ) ? $input[ $key ] : '' );
		}
		$output['status'] = isset( $input['status'] ) && in_array( $input['status'], array( '', 'planned', 'ongoing', 'completed', 'on-hold' ), true ) ? $input['status'] : '';
		foreach ( array( 'date_start', 'date_end' ) as $key ) {
			$output[ $key ] = self::sanitize_date( isset( $input[ $key ] ) ? $input[ $key ] : '' );
		}
		if ( $output['date_start'] && $output['date_end'] && $output['date_end'] < $output['date_start'] ) {
			$output['date_end'] = '';
		}
		$output['email']   = isset( $input['email'] ) && is_string( $input['email'] ) ? sanitize_email( $input['email'] ) : '';
		$output['website'] = self::url( isset( $input['website'] ) ? $input['website'] : '' );
		foreach ( array( 'details', 'links', 'events', 'accordions' ) as $group ) {
			$rows = isset( $input[ $group ] ) && is_array( $input[ $group ] ) ? array_slice( $input[ $group ], 0, 50 ) : array();
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				if ( 'details' === $group ) {
					$clean = array( 'label' => self::text( isset( $row['label'] ) ? $row['label'] : '', 200 ), 'value' => self::text( isset( $row['value'] ) ? $row['value'] : '', 2000 ) );
					if ( '' !== $clean['label'] && '' !== $clean['value'] ) {
						$output[ $group ][] = $clean;
					}
				} elseif ( 'links' === $group ) {
					$clean = array( 'label' => self::text( isset( $row['label'] ) ? $row['label'] : '', 200 ), 'url' => self::url( isset( $row['url'] ) ? $row['url'] : '' ), 'kind' => isset( $row['kind'] ) && 'social' === $row['kind'] ? 'social' : 'link' );
					if ( '' !== $clean['label'] && '' !== $clean['url'] ) {
						$output[ $group ][] = $clean;
					}
				} elseif ( 'events' === $group ) {
					$clean = array( 'title' => self::text( isset( $row['title'] ) ? $row['title'] : '', 200 ), 'date' => self::sanitize_date( isset( $row['date'] ) ? $row['date'] : '' ), 'description' => isset( $row['description'] ) && is_string( $row['description'] ) ? wp_kses_post( substr( $row['description'], 0, 20000 ) ) : '', 'url' => self::url( isset( $row['url'] ) ? $row['url'] : '' ) );
					if ( '' !== $clean['title'] ) {
						$output[ $group ][] = $clean;
					}
				} else {
					$clean = array( 'title' => self::text( isset( $row['title'] ) ? $row['title'] : '', 200 ), 'content' => isset( $row['content'] ) && is_string( $row['content'] ) ? wp_kses_post( substr( $row['content'], 0, 20000 ) ) : '' );
					if ( '' !== $clean['title'] && '' !== $clean['content'] ) {
						$output[ $group ][] = $clean;
					}
				}
			}
		}
		return $output;
	}

	/** @param int $post_id Project ID. @return array */
	public static function get_details( $post_id ) {
		return self::sanitize_details( get_post_meta( $post_id, '_wppc_details', true ) );
	}

	/** @param mixed $input Image IDs or comma-separated IDs. @return array */
	public static function sanitize_gallery( $input ) {
		if ( is_string( $input ) ) {
			$input = explode( ',', $input );
		}
		$ids = array();
		foreach ( is_array( $input ) ? array_slice( $input, 0, 100 ) : array() as $id ) {
			$id = is_scalar( $id ) ? absint( $id ) : 0;
			if ( $id && wp_attachment_is_image( $id ) ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/** @param int $post_id Project ID. @return array */
	public static function get_gallery_ids( $post_id ) {
		$key = metadata_exists( 'post', $post_id, '_wppc_gallery_ids' ) ? '_wppc_gallery_ids' : '_ppg_project_gallery_ids';
		return self::sanitize_gallery( get_post_meta( $post_id, $key, true ) );
	}

	/** @param int $id Attachment ID. @return array|null */
	private static function image( $id ) {
		$image = wp_get_attachment_image_src( $id, 'large' );
		if ( ! $image || ! wp_attachment_is_image( $id ) ) {
			return null;
		}
		return array(
			'id' => absint( $id ), 'url' => $image[0], 'full' => wp_get_attachment_image_url( $id, 'full' ),
			'alt' => get_post_meta( $id, '_wp_attachment_image_alt', true ), 'caption' => wp_get_attachment_caption( $id ),
			'width' => $image[1], 'height' => $image[2],
		);
	}

	/**
	 * Return responsive image references with a read-through legacy fallback.
	 *
	 * @param int  $post_id Project ID.
	 * @param bool $include_featured Include the featured image first.
	 * @return array
	 */
	public static function get_gallery( $post_id, $include_featured = true ) {
		$items = array();
		$seen  = array();
		$featured = $include_featured ? self::image( get_post_thumbnail_id( $post_id ) ) : null;
		if ( $featured ) {
			$items[] = $featured;
			$seen[ $featured['url'] ] = true;
		}
		$featured_only = metadata_exists( 'post', $post_id, '_wppc_featured_only' ) ? get_post_meta( $post_id, '_wppc_featured_only', true ) : get_post_meta( $post_id, '_ppg_disable_gallery', true );
		if ( $featured_only ) {
			return $items;
		}
		foreach ( self::get_gallery_ids( $post_id ) as $id ) {
			$image = self::image( $id );
			if ( $image && ! isset( $seen[ $image['url'] ] ) ) {
				$items[] = $image;
				$seen[ $image['url'] ] = true;
			}
		}
		if ( ! metadata_exists( 'post', $post_id, '_wppc_gallery_ids' ) && ! self::get_gallery_ids( $post_id ) && ! get_post_meta( $post_id, '_ppg_demo_gallery_removed', true ) ) {
			$legacy = get_post_meta( $post_id, '_ppg_project_gallery', true );
			$legacy = is_string( $legacy ) ? json_decode( $legacy, true ) : $legacy;
			foreach ( is_array( $legacy ) ? array_slice( $legacy, 0, 100 ) : array() as $row ) {
				$raw_url = is_array( $row ) && isset( $row['url'] ) ? $row['url'] : ( is_string( $row ) ? $row : '' );
				$url = self::url( $raw_url );
				$path = $url ? wp_parse_url( $url, PHP_URL_PATH ) : '';
				// Preserve the old bundled placeholders even after the old plugin is removed.
				if ( is_string( $path ) && false !== strpos( $path, '/post-project-gallery' ) && preg_match( '#/assets/images/(project-[1-5]-[1-5]\.svg)$#D', $path, $match ) ) {
					$url = WPPC_URL . 'assets/images/' . $match[1];
				}
				if ( $url && ! isset( $seen[ $url ] ) ) {
					$caption = is_array( $row ) && isset( $row['caption'] ) ? self::text( $row['caption'], 2000 ) : '';
					$items[] = array( 'id' => 0, 'url' => $url, 'full' => $url, 'alt' => $caption, 'caption' => $caption, 'width' => 1600, 'height' => 1000 );
					$seen[ $url ] = true;
				}
			}
		}
		return $items;
	}

	/** @param int $post_id Project ID. @return bool */
	public static function is_public( $post_id ) {
		$post = get_post( $post_id );
		return $post && self::POST_TYPE === $post->post_type && 'publish' === $post->post_status && '' === $post->post_password;
	}

	/** @param int $post_id Project ID. @return bool */
	public static function can_view( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}
		if ( post_password_required( $post ) ) {
			return false;
		}
		if ( 'publish' === $post->post_status ) {
			return true;
		}
		// Draft/private project data belongs only in its own authorized preview,
		// never in an otherwise public page that a page cache might persist.
		return is_preview() && get_queried_object_id() === (int) $post_id && current_user_can( 'edit_post', $post_id );
	}
}
