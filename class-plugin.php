<?php
/**
 * Plugin lifecycle and site settings.
 *
 * @package WP_Portfolio_Catalogue
 */

namespace WP_Portfolio_Catalogue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	/** @var bool */
	private static $booted = false;

	/** Register services after all active plugins are loaded. */
	public static function boot() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		if ( class_exists( 'PPG_Post_Project_Gallery', false ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'legacy_notice' ) );
			return;
		}
		add_action( 'init', array( '\WP_Portfolio_Catalogue\Content', 'register' ), 5 );
		add_action( 'init', array( '\WP_Portfolio_Catalogue\Frontend', 'register_assets' ), 7 );
		new Frontend();
		new Blocks();
		new Admin();
		add_filter( 'plugin_action_links_' . plugin_basename( WPPC_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/** Do not run two implementations over the same project data. */
	public static function legacy_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		esc_html_e( 'WP Portfolio Catalogue is paused while Post Project Gallery is active. Deactivate Post Project Gallery to enable this plugin. Your existing projects, featured images and galleries are retained.', 'wp-portfolio-catalogue' );
		echo '</p></div>';
	}

	/** @param array $links Existing plugin row links. @return array */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'edit.php?post_type=project&page=wppc-settings' ) ) . '">' . esc_html__( 'Settings', 'wp-portfolio-catalogue' ) . '</a>' );
		return $links;
	}

	/** Create only settings; no content is imported or published on activation. */
	public static function activate() {
		Content::register();
		add_option( 'wppc_settings', self::defaults(), '', false );
		flush_rewrite_rules();
	}

	/** Flush rewrite cache without deleting any user content. */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/** @return array */
	public static function defaults() {
		return array(
			'posts_per_page' => 6,
			'columns'        => 3,
			'excerpt_words'  => 22,
			'accent'         => '#245b47',
			'card_radius'    => 16,
			'card_gap'       => 24,
			'auto_details'   => 1,
			'catalogue_page' => 0,
			'delete_data'    => 0,
		);
	}

	/** @return array Sanitized settings with complete defaults. */
	public static function settings() {
		$settings = get_option( 'wppc_settings', array() );
		return self::sanitize_settings( wp_parse_args( is_array( $settings ) ? $settings : array(), self::defaults() ) );
	}

	/**
	 * Settings API callback. WordPress has already unslashed option input.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array
	 */
	public static function sanitize_settings( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$output   = $defaults;
		$limits   = array(
			'posts_per_page' => array( 1, 48 ),
			'columns'        => array( 1, 4 ),
			'excerpt_words'  => array( 0, 80 ),
			'card_radius'    => array( 0, 40 ),
			'card_gap'       => array( 8, 64 ),
		);
		foreach ( $limits as $key => $bounds ) {
			if ( isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ) {
				$output[ $key ] = max( $bounds[0], min( $bounds[1], absint( $input[ $key ] ) ) );
			}
		}
		$color            = isset( $input['accent'] ) && is_string( $input['accent'] ) ? sanitize_hex_color( $input['accent'] ) : '';
		$output['accent'] = $color ? $color : $defaults['accent'];
		foreach ( array( 'auto_details', 'delete_data' ) as $key ) {
			$output[ $key ] = isset( $input[ $key ] ) && in_array( $input[ $key ], array( 1, '1', true ), true ) ? 1 : 0;
		}
		$page = isset( $input['catalogue_page'] ) && is_scalar( $input['catalogue_page'] ) ? absint( $input['catalogue_page'] ) : 0;
		$output['catalogue_page'] = $page && 'page' === get_post_type( $page ) ? $page : 0;
		return $output;
	}
}
