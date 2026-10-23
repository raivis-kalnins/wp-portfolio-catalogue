<?php
/**
 * Public catalogue, project details and progressive gallery controls.
 *
 * @package WP_Portfolio_Catalogue
 */

namespace WP_Portfolio_Catalogue;

defined( 'ABSPATH' ) || exit;

/** Keeps published catalogue queries separate from privileged editing requests. */
final class Frontend {

	/** @var int Stable per-page instance number for independent GET filters. */
	private static $instance = 0;

	/** @var bool Stops nested project shortcodes and automatic layout recursion. */
	private static $rendering_project = false;

	/** Register the public integrations. */
	public function __construct() {
		add_action( 'init', array( __CLASS__, 'register_assets' ) );
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
		add_filter( 'the_content', array( $this, 'automatic_project' ), 20 );
	}

	/** Register assets without loading third-party code. */
	public static function register_assets() {
		if ( ! wp_style_is( 'wppc-frontend', 'registered' ) ) {
			wp_register_style( 'wppc-frontend', WPPC_URL . 'assets/css/frontend.css', array(), WPPC_VERSION );
		}
		if ( ! wp_script_is( 'wppc-frontend', 'registered' ) ) {
			wp_register_script( 'wppc-frontend', WPPC_URL . 'assets/js/frontend.js', array(), WPPC_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		}
	}

	/** Enqueue early on known project/catalogue pages; shortcode rendering also enqueues. */
	public function maybe_enqueue() {
		$post = get_post();
		if ( is_singular( 'project' ) || ( $post && ( has_block( 'wp-portfolio-catalogue/catalogue', $post ) || preg_match( '/\[(?:portfolio_catalogue|portfolio_project|portfolio_gallery|project_gallery|project_images)\b/', $post->post_content ) ) ) ) {
			self::enqueue_assets();
		}
	}

	/** Ensure builder-rendered shortcodes receive their assets, including late styles. */
	private static function enqueue_assets() {
		self::register_assets();
		wp_enqueue_style( 'wppc-frontend' );
		wp_enqueue_script( 'wppc-frontend' );
	}

	/**
	 * Return a late stylesheet only when a builder first renders after wp_head.
	 * WordPress tracks the handle, so multiple catalogue instances never duplicate it.
	 *
	 * @return string
	 */
	private static function late_styles() {
		if ( did_action( 'wp_head' ) && ! wp_style_is( 'wppc-frontend', 'done' ) ) {
			ob_start();
			wp_print_styles( 'wppc-frontend' );
			return ob_get_clean();
		}
		return '';
	}

	/** Shortcodes also work in Elementor's Shortcode widget and other builders. */
	public function register_shortcodes() {
		add_shortcode( 'portfolio_catalogue', array( __CLASS__, 'render_catalogue' ) );
		add_shortcode( 'portfolio_project', array( __CLASS__, 'project_shortcode' ) );
		add_shortcode( 'portfolio_gallery', array( __CLASS__, 'gallery_shortcode' ) );
		add_shortcode( 'project_gallery', array( __CLASS__, 'render_catalogue' ) );
		add_shortcode( 'project_images', array( __CLASS__, 'gallery_shortcode' ) );
	}

	/** Convert shortcode and JSON booleans consistently. */
	private static function truthy( $value ) {
		return ! in_array( strtolower( (string) $value ), array( '', '0', 'false', 'no', 'off' ), true );
	}

	/** Canonical, bounded options shared by PHP rendering and the public REST route. */
	private static function options( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$settings = Plugin::settings();
		if ( isset( $input['posts_per_page'] ) && ! isset( $input['per_page'] ) ) {
			$input['per_page'] = $input['posts_per_page'];
		}
		$defaults = array(
			'columns'         => $settings['columns'],
			'per_page'        => $settings['posts_per_page'],
			'excerpt_words'   => $settings['excerpt_words'],
			'category'        => '',
			'tag'             => '',
			'ids'             => '',
			'filters'         => true,
			'search'          => true,
			'orderby'         => 'date',
			'order'           => 'DESC',
			'page'            => 1,
			'filter_category' => '',
			'filter_tag'      => '',
			's'               => '',
		);
		$input = array_merge( $defaults, array_intersect_key( $input, $defaults ) );
		foreach ( $input as $key => $value ) {
			if ( ! is_scalar( $value ) ) {
				$input[ $key ] = $defaults[ $key ];
			}
		}
		$input['columns']       = min( 4, max( 1, absint( $input['columns'] ) ) );
		$input['per_page']      = min( 48, max( 1, absint( $input['per_page'] ) ) );
		$input['excerpt_words'] = min( 80, max( 0, absint( $input['excerpt_words'] ) ) );
		$input['page']          = min( 10000, max( 1, absint( $input['page'] ) ) );
		$raw_ids               = trim( (string) $input['ids'] );
		$ids                   = array_slice( array_filter( wp_parse_id_list( $raw_ids ) ), 0, 100 );
		// A malformed nonempty selection must never silently become an unrestricted query.
		$input['ids']           = '' === $raw_ids ? '' : ( $ids ? implode( ',', $ids ) : '0' );
		foreach ( array( 'category', 'tag' ) as $key ) {
			$raw_terms = trim( (string) $input[ $key ] );
			$terms     = array_slice( array_filter( array_unique( array_map( 'sanitize_title', explode( ',', $raw_terms ) ) ) ), 0, 30 );
			// The internal marker cannot be a WordPress term slug and survives pagination.
			$input[ $key ] = ':wppc-invalid:' === $raw_terms || ( '' !== $raw_terms && ! $terms ) ? ':wppc-invalid:' : implode( ',', $terms );
		}
		$input['filter_category'] = sanitize_title( $input['filter_category'] );
		$input['filter_tag']      = sanitize_title( $input['filter_tag'] );
		$input['s']               = wp_html_excerpt( sanitize_text_field( $input['s'] ), 100, '' );
		$input['orderby']         = in_array( $input['orderby'], array( 'date', 'title', 'menu_order' ), true ) ? $input['orderby'] : 'date';
		$input['order']           = 'ASC' === strtoupper( $input['order'] ) ? 'ASC' : 'DESC';
		$input['filters']         = self::truthy( $input['filters'] );
		$input['search']          = self::truthy( $input['search'] );
		return $input;
	}

	/** No request can change the queried post type, publication status or password guard. */
	private static function query( $options ) {
		$args = array(
			'post_type'           => 'project',
			'post_status'         => 'publish',
			'has_password'        => false,
			'posts_per_page'      => $options['per_page'],
			'paged'               => $options['page'],
			'ignore_sticky_posts' => true,
			'orderby'             => array( $options['orderby'] => $options['order'], 'ID' => $options['order'] ),
			's'                   => $options['s'],
		);
		if ( '' !== $options['ids'] ) {
			$args['post__in'] = wp_parse_id_list( $options['ids'] );
		}
		$tax_query = array( 'relation' => 'AND' );
		foreach ( array( 'category' => 'wppc_category', 'tag' => 'wppc_tag', 'filter_category' => 'wppc_category', 'filter_tag' => 'wppc_tag' ) as $key => $taxonomy ) {
			if ( ':wppc-invalid:' === $options[ $key ] ) {
				$args['post__in'] = array( 0 );
				continue;
			}
			if ( '' !== $options[ $key ] ) {
				$tax_query[] = array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => explode( ',', $options[ $key ] ) );
			}
		}
		if ( count( $tax_query ) > 1 ) {
			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Native taxonomy filtering is required; queries are paginated and terms are bounded.
		}
		return new \WP_Query( $args );
	}

	/** Read-only filter arguments deliberately do not use an expiring nonce. */
	private static function get_filter( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, read-only catalogue filtering.
		return isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
	}

	/** Render the initial catalogue on the server, with working links without JavaScript. */
	public static function render_catalogue( $atts = array() ) {
		$options = self::options( $atts );
		self::enqueue_assets();
		++self::$instance;
		$prefix    = 'wppc_' . self::$instance . '_';
		$element   = 'wppc-catalogue-' . self::$instance;
		$query_map = array( 'category' => 'filter_category', 'tag' => 'filter_tag', 's' => 's', 'page' => 'page' );
		foreach ( $query_map as $suffix => $key ) {
			$value = self::get_filter( $prefix . $suffix );
			if ( '' !== $value ) {
				$options[ $key ] = $value;
			}
		}
		$options  = self::options( $options );
		$query    = self::query( $options );
		$settings = Plugin::settings();
		$base_url = remove_query_arg( $prefix . 'page', get_pagenum_link( 1, false ) );
		$config   = array(
			'endpoint' => esc_url_raw( rest_url( 'wp-portfolio-catalogue/v1/projects' ) ),
			'options'  => $options,
			'prefix'   => $prefix,
			'page'     => $options['page'],
			'pages'    => (int) $query->max_num_pages,
			'total'    => (int) $query->found_posts,
			'loading'  => __( 'Loading projects…', 'wp-portfolio-catalogue' ),
			'error'    => __( 'Projects could not be updated. Use Apply filters or the page links to reload.', 'wp-portfolio-catalogue' ),
			'empty'    => __( 'No projects match your filters.', 'wp-portfolio-catalogue' ),
			/* translators: 1: visible project count, 2: total matching project count. */
			'count'    => __( '%1$s of %2$s projects', 'wp-portfolio-catalogue' ),
		);
		$style = sprintf( '--wppc-columns:%d;--wppc-compact-columns:%d;--wppc-accent:%s;--wppc-radius:%dpx;--wppc-gap:%dpx;', $options['columns'], min( 2, $options['columns'] ), $settings['accent'], $settings['card_radius'], $settings['card_gap'] );
		ob_start();
		?>
		<section id="<?php echo esc_attr( $element ); ?>" class="wppc-catalogue" data-wppc-catalogue="<?php echo esc_attr( wp_json_encode( $config ) ); ?>" style="<?php echo esc_attr( $style ); ?>" aria-label="<?php esc_attr_e( 'Project catalogue', 'wp-portfolio-catalogue' ); ?>">
			<?php self::filter_form( $options, $prefix, $element, $base_url ); ?>
			<div class="wppc-results-bar">
				<p class="wppc-result-count" aria-live="polite" aria-atomic="true"><?php
				/* translators: 1: visible project count, 2: total matching project count. */
				echo esc_html( sprintf( __( '%1$s of %2$s projects', 'wp-portfolio-catalogue' ), number_format_i18n( $query->post_count ), number_format_i18n( $query->found_posts ) ) );
				?></p>
				<p class="wppc-request-status" role="status" aria-live="polite"></p>
			</div>
			<div class="wppc-grid" id="<?php echo esc_attr( $element . '-results' ); ?>">
				<?php echo self::cards( $query->posts, $options ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in cards(). ?>
			</div>
			<p class="wppc-empty" <?php echo $query->post_count ? 'hidden' : ''; ?>><?php esc_html_e( 'No projects match your filters.', 'wp-portfolio-catalogue' ); ?></p>
			<nav class="wppc-pagination" aria-label="<?php esc_attr_e( 'Project pages', 'wp-portfolio-catalogue' ); ?>">
				<a class="wppc-previous-page wppc-button wppc-button-secondary" href="<?php echo esc_url( add_query_arg( $prefix . 'page', max( 1, $options['page'] - 1 ), $base_url ) . '#' . $element ); ?>" <?php echo $options['page'] <= 1 ? 'hidden' : ''; ?>><?php esc_html_e( 'Previous page', 'wp-portfolio-catalogue' ); ?></a>
				<a class="wppc-more wppc-button" href="<?php echo esc_url( add_query_arg( $prefix . 'page', $options['page'] + 1, $base_url ) . '#' . $element ); ?>" aria-controls="<?php echo esc_attr( $element . '-results' ); ?>" <?php echo $options['page'] >= $query->max_num_pages ? 'hidden' : ''; ?>><?php esc_html_e( 'Load more projects', 'wp-portfolio-catalogue' ); ?></a>
			</nav>
		</section>
		<?php
		$html = ob_get_clean();
		return self::late_styles() . $html;
	}

	/** Render accessible GET controls. Every instance has its own parameter names. */
	private static function filter_form( $options, $prefix, $element, $base_url ) {
		if ( ! $options['filters'] && ! $options['search'] ) {
			return;
		}
		$url_parts = wp_parse_url( $base_url );
		$hidden    = array();
		if ( ! empty( $url_parts['query'] ) ) {
			wp_parse_str( $url_parts['query'], $hidden );
		}
		$action = preg_replace( '/[?#].*$/', '', $base_url );
		?>
		<form class="wppc-filters" method="get" action="<?php echo esc_url( $action ); ?>#<?php echo esc_attr( $element ); ?>" aria-label="<?php esc_attr_e( 'Filter projects', 'wp-portfolio-catalogue' ); ?>">
			<?php foreach ( $hidden as $key => $value ) : ?>
				<?php if ( is_scalar( $value ) && 0 !== strpos( $key, $prefix ) ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
				<?php endif; ?>
			<?php endforeach; ?>
			<?php if ( $options['search'] ) : ?>
				<div class="wppc-field wppc-search-field">
					<label for="<?php echo esc_attr( $element . '-search' ); ?>"><?php esc_html_e( 'Search projects', 'wp-portfolio-catalogue' ); ?></label>
					<input id="<?php echo esc_attr( $element . '-search' ); ?>" name="<?php echo esc_attr( $prefix . 's' ); ?>" type="search" maxlength="100" value="<?php echo esc_attr( $options['s'] ); ?>" placeholder="<?php esc_attr_e( 'Search by name or description…', 'wp-portfolio-catalogue' ); ?>" data-wppc-search>
				</div>
			<?php endif; ?>
			<?php
			if ( $options['filters'] ) {
				self::term_select( 'wppc_category', 'category', __( 'Category', 'wp-portfolio-catalogue' ), __( 'All categories', 'wp-portfolio-catalogue' ), $options['filter_category'], $prefix, $element );
				self::term_select( 'wppc_tag', 'tag', __( 'Tag', 'wp-portfolio-catalogue' ), __( 'All tags', 'wp-portfolio-catalogue' ), $options['filter_tag'], $prefix, $element );
			}
			?>
			<button type="submit" class="wppc-button wppc-apply"><?php esc_html_e( 'Apply filters', 'wp-portfolio-catalogue' ); ?></button>
			<a class="wppc-reset" href="<?php echo esc_url( remove_query_arg( array( $prefix . 's', $prefix . 'category', $prefix . 'tag', $prefix . 'page' ), $base_url ) . '#' . $element ); ?>"><?php esc_html_e( 'Reset', 'wp-portfolio-catalogue' ); ?></a>
		</form>
		<?php
	}

	/** Taxonomy controls are omitted when no usable terms exist. */
	private static function term_select( $taxonomy, $name, $label, $all, $selected, $prefix, $element ) {
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true, 'number' => 200 ) );
		if ( is_wp_error( $terms ) || ! $terms ) {
			return;
		}
		?>
		<div class="wppc-field">
			<label for="<?php echo esc_attr( $element . '-' . $name ); ?>"><?php echo esc_html( $label ); ?></label>
			<select id="<?php echo esc_attr( $element . '-' . $name ); ?>" name="<?php echo esc_attr( $prefix . $name ); ?>" data-wppc-filter="<?php echo esc_attr( $name ); ?>">
				<option value=""><?php echo esc_html( $all ); ?></option>
				<?php foreach ( $terms as $term ) : ?>
					<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $selected, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
	}

	/** Render only safe, public card fields. Do not run post-content shortcodes in cards. */
	private static function cards( $posts, $options ) {
		ob_start();
		foreach ( $posts as $project ) {
			if ( ! Content::is_public( $project->ID ) ) {
				continue;
			}
			// Most cards need only the cover; avoid resolving a large gallery per result.
			$cover   = get_post_thumbnail_id( $project->ID );
			$images  = $cover && wp_attachment_is_image( $cover ) ? array( array( 'id' => $cover ) ) : Content::get_gallery( $project->ID );
			$details = Content::get_details( $project->ID );
			$terms   = get_the_terms( $project->ID, 'wppc_category' );
			$title   = get_the_title( $project->ID );
			$title   = '' !== $title ? $title : __( 'Untitled project', 'wp-portfolio-catalogue' );
			$excerpt = $project->post_excerpt ? $project->post_excerpt : $project->post_content;
			$excerpt = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $excerpt ), true ), $options['excerpt_words'], '…' );
			?>
			<article class="wppc-card" data-project-id="<?php echo esc_attr( $project->ID ); ?>">
				<a class="wppc-card-image" href="<?php echo esc_url( get_permalink( $project->ID ) ); ?>" tabindex="-1" aria-hidden="true">
					<?php if ( $images ) : ?>
						<?php echo self::image( $images[0], 'large', 'wppc-cover-image', '(max-width: 640px) 100vw, (max-width: 960px) 50vw, 33vw' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in image(). ?>
					<?php else : ?>
						<span class="wppc-image-placeholder" aria-hidden="true"><svg viewBox="0 0 64 64" width="56" height="56" fill="none"><rect x="10" y="14" width="44" height="36" rx="5" stroke="currentColor" stroke-width="2"/><path d="m11 42 12-13 13 14 7-8 10 11" stroke="currentColor" stroke-width="2"/><circle cx="42" cy="25" r="4" stroke="currentColor" stroke-width="2"/></svg></span>
					<?php endif; ?>
				</a>
				<div class="wppc-card-body">
					<?php if ( $terms && ! is_wp_error( $terms ) ) : ?>
						<div class="wppc-card-categories"><?php foreach ( array_slice( $terms, 0, 3 ) as $term ) : ?><span><?php echo esc_html( $term->name ); ?></span><?php endforeach; ?></div>
					<?php endif; ?>
					<h3 class="wppc-card-title"><a href="<?php echo esc_url( get_permalink( $project->ID ) ); ?>"><?php echo esc_html( $title ); ?></a></h3>
					<?php if ( ! empty( $details['location'] ) || ! empty( $details['date_start'] ) || ! empty( $details['date_end'] ) ) : ?>
						<p class="wppc-card-meta"><?php echo esc_html( implode( ' · ', array_filter( array( $details['location'], self::date_range( $details['date_start'], $details['date_end'] ) ) ) ) ); ?></p>
					<?php endif; ?>
					<?php if ( $options['excerpt_words'] && '' !== $excerpt ) : ?><p class="wppc-card-excerpt"><?php echo esc_html( $excerpt ); ?></p><?php endif; ?>
					<a class="wppc-card-link" href="<?php echo esc_url( get_permalink( $project->ID ) ); ?>"><?php esc_html_e( 'View project', 'wp-portfolio-catalogue' ); ?><span class="wppc-sr-only">: <?php echo esc_html( $title ); ?></span><span aria-hidden="true">↗</span></a>
				</div>
			</article>
			<?php
		}
		return ob_get_clean();
	}

	/** Responsive attachment output, including safe legacy URL fallbacks. */
	private static function image( $image, $size, $class, $sizes = '100vw' ) {
		if ( ! empty( $image['id'] ) ) {
			return wp_get_attachment_image( $image['id'], $size, false, array( 'class' => $class, 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => $sizes ) );
		}
		return sprintf( '<img class="%1$s" src="%2$s" alt="%3$s" loading="lazy" decoding="async"%4$s>', esc_attr( $class ), esc_url( $image['url'] ), esc_attr( isset( $image['alt'] ) ? $image['alt'] : '' ), ! empty( $image['width'] ) && ! empty( $image['height'] ) ? ' width="' . absint( $image['width'] ) . '" height="' . absint( $image['height'] ) . '"' : '' );
	}

	/** Public, read-only endpoint: no login or cache-expiring nonce is needed. */
	public function register_routes() {
		register_rest_route( 'wp-portfolio-catalogue/v1', '/projects', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'rest_projects' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'page'     => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 10000, 'default' => 1 ),
				'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 48, 'default' => 6 ),
			),
		) );
	}

	/** Return bounded HTML/results for progressively enhanced catalogue controls. */
	public function rest_projects( $request ) {
		$options = self::options( $request->get_params() );
		$query   = self::query( $options );
		return rest_ensure_response( array(
			'html'  => self::cards( $query->posts, $options ),
			'page'  => $options['page'],
			'pages' => (int) $query->max_num_pages,
			'total' => (int) $query->found_posts,
			'count' => (int) $query->post_count,
		) );
	}

	/** Display a date without converting a date-only field across time zones. */
	private static function date( $date ) {
		if ( ! is_string( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return '';
		}
		$stamp = strtotime( $date . ' 12:00:00 UTC' );
		return false !== $stamp ? wp_date( get_option( 'date_format' ), $stamp, new \DateTimeZone( 'UTC' ) ) : '';
	}

	/** Date ranges allow either boundary to be omitted. */
	private static function date_range( $from, $to ) {
		$from = self::date( $from );
		$to   = self::date( $to );
		if ( $from && $to ) {
			return $from === $to ? $from : $from . ' – ' . $to;
		}
		if ( $from ) {
			/* translators: %s: project start date. */
			return sprintf( __( 'From %s', 'wp-portfolio-catalogue' ), $from );
		}
		/* translators: %s: project end date. */
		return $to ? sprintf( __( 'Until %s', 'wp-portfolio-catalogue' ), $to ) : '';
	}

	/** Gallery shortcode: works inside custom Elementor or theme templates. */
	public static function gallery_shortcode( $atts = array() ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'portfolio_gallery' );
		$id   = absint( $atts['id'] ) ?: get_the_ID();
		return Content::can_view( $id ) ? self::render_gallery( $id ) : '';
	}

	/** Render a gallery with native swipe/scroll and a progressively enhanced lightbox. */
	public static function render_gallery( $id ) {
		if ( ! Content::can_view( $id ) ) {
			return '';
		}
		$images = Content::get_gallery( $id );
		if ( ! $images ) {
			return '';
		}
		self::enqueue_assets();
		$uid = wp_unique_id( 'wppc-gallery-' );
		$count = count( $images );
		$settings = Plugin::settings();
		$style = sprintf( '--wppc-accent:%s;--wppc-radius:%dpx;', $settings['accent'], $settings['card_radius'] );
		ob_start();
		?>
		<div class="wppc-gallery" style="<?php echo esc_attr( $style ); ?>" data-wppc-gallery data-dialog-label="<?php esc_attr_e( 'Project image viewer', 'wp-portfolio-catalogue' ); ?>" data-close-label="<?php esc_attr_e( 'Close image viewer', 'wp-portfolio-catalogue' ); ?>" data-original-label="<?php esc_attr_e( 'Open original image', 'wp-portfolio-catalogue' ); ?>" data-prev-label="<?php esc_attr_e( 'Previous image', 'wp-portfolio-catalogue' ); ?>" data-next-label="<?php esc_attr_e( 'Next image', 'wp-portfolio-catalogue' ); ?>">
			<div class="wppc-gallery-viewport" id="<?php echo esc_attr( $uid ); ?>" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Project images. Swipe or use the arrow keys to browse.', 'wp-portfolio-catalogue' ); ?>">
				<?php foreach ( $images as $index => $image ) : ?>
					<figure class="wppc-gallery-slide">
						<a class="wppc-gallery-open" href="<?php echo esc_url( ! empty( $image['full'] ) ? $image['full'] : $image['url'] ); ?>" target="_blank" rel="noopener" data-wppc-caption="<?php echo esc_attr( $image['caption'] ); ?>" aria-label="<?php
						/* translators: %d: image number in the project gallery. */
						echo esc_attr( sprintf( __( 'Enlarge image %d (opens an image viewer)', 'wp-portfolio-catalogue' ), $index + 1 ) );
						?>">
							<?php echo self::image( $image, 'large', 'wppc-gallery-image' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in image(). ?>
						</a>
						<?php if ( '' !== $image['caption'] ) : ?><figcaption><?php echo esc_html( $image['caption'] ); ?></figcaption><?php endif; ?>
					</figure>
				<?php endforeach; ?>
			</div>
			<?php if ( $count > 1 ) : ?>
				<div class="wppc-gallery-controls">
					<button class="wppc-gallery-prev" type="button" aria-controls="<?php echo esc_attr( $uid ); ?>" aria-label="<?php esc_attr_e( 'Previous image', 'wp-portfolio-catalogue' ); ?>" disabled><span aria-hidden="true">←</span></button>
					<span class="wppc-gallery-position" aria-live="polite" aria-atomic="true">1 / <?php echo esc_html( $count ); ?></span>
					<button class="wppc-gallery-next" type="button" aria-controls="<?php echo esc_attr( $uid ); ?>" aria-label="<?php esc_attr_e( 'Next image', 'wp-portfolio-catalogue' ); ?>"><span aria-hidden="true">→</span></button>
				</div>
				<div class="wppc-gallery-thumbs" aria-label="<?php esc_attr_e( 'Choose a project image', 'wp-portfolio-catalogue' ); ?>">
					<?php foreach ( $images as $index => $image ) : ?>
						<button type="button" data-wppc-image="<?php echo esc_attr( $index ); ?>" aria-controls="<?php echo esc_attr( $uid ); ?>" aria-current="<?php echo 0 === $index ? 'true' : 'false'; ?>" aria-label="<?php
						/* translators: %d: image number in the project gallery. */
						echo esc_attr( sprintf( __( 'Show image %d', 'wp-portfolio-catalogue' ), $index + 1 ) );
						?>"><?php echo self::image( $image, 'thumbnail', 'wppc-thumb-image', '80px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in image(). ?></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		$html = ob_get_clean();
		return self::late_styles() . $html;
	}

	/** Optional project fields are omitted completely when they have no content. */
	public static function render_details( $id ) {
		if ( ! Content::can_view( $id ) ) {
			return '';
		}
		$data     = Content::get_details( $id );
		$statuses = array( 'planned' => __( 'Planned', 'wp-portfolio-catalogue' ), 'ongoing' => __( 'Ongoing', 'wp-portfolio-catalogue' ), 'completed' => __( 'Completed', 'wp-portfolio-catalogue' ), 'on-hold' => __( 'On hold', 'wp-portfolio-catalogue' ) );
		$facts    = array(
			array( 'label' => __( 'Client', 'wp-portfolio-catalogue' ), 'value' => $data['client'] ),
			array( 'label' => __( 'Location', 'wp-portfolio-catalogue' ), 'value' => $data['location'] ),
			array( 'label' => __( 'Status', 'wp-portfolio-catalogue' ), 'value' => isset( $statuses[ $data['status'] ] ) ? $statuses[ $data['status'] ] : '' ),
			array( 'label' => __( 'Project dates', 'wp-portfolio-catalogue' ), 'value' => self::date_range( $data['date_start'], $data['date_end'] ) ),
		);
		$facts   = array_merge( $facts, $data['details'] );
		$facts   = array_filter( $facts, static function ( $row ) { return '' !== $row['label'] && '' !== $row['value']; } );
		$contact = $data['contact_name'] || $data['email'] || $data['phone'] || $data['website'];
		$has_any = $facts || $contact || $data['links'] || $data['events'] || $data['accordions'];
		if ( ! $has_any ) {
			return '';
		}
		self::enqueue_assets();
		$settings = Plugin::settings();
		$style = sprintf( '--wppc-accent:%s;--wppc-radius:%dpx;', $settings['accent'], $settings['card_radius'] );
		ob_start();
		?>
		<div class="wppc-project-details" style="<?php echo esc_attr( $style ); ?>" data-wppc-project-details>
			<?php if ( $facts || $contact ) : ?>
				<div class="wppc-detail-columns">
					<?php if ( $facts ) : ?>
						<section class="wppc-facts"><h2><?php esc_html_e( 'Project details', 'wp-portfolio-catalogue' ); ?></h2><dl><?php foreach ( $facts as $row ) : ?><div><dt><?php echo esc_html( $row['label'] ); ?></dt><dd><?php echo esc_html( $row['value'] ); ?></dd></div><?php endforeach; ?></dl></section>
					<?php endif; ?>
					<?php if ( $contact ) : ?>
						<section class="wppc-contact"><h2><?php esc_html_e( 'Contact', 'wp-portfolio-catalogue' ); ?></h2>
							<?php if ( $data['contact_name'] ) : ?><p class="wppc-contact-name"><?php echo esc_html( $data['contact_name'] ); ?></p><?php endif; ?>
							<ul>
								<?php if ( $data['email'] ) : ?><li><span><?php esc_html_e( 'Email', 'wp-portfolio-catalogue' ); ?></span><a href="<?php echo esc_url( 'mailto:' . $data['email'] ); ?>"><?php echo esc_html( $data['email'] ); ?></a></li><?php endif; ?>
								<?php if ( $data['phone'] ) : ?><li><span><?php esc_html_e( 'Phone', 'wp-portfolio-catalogue' ); ?></span><a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $data['phone'] ) ); ?>"><?php echo esc_html( $data['phone'] ); ?></a></li><?php endif; ?>
								<?php if ( $data['website'] ) : ?><li><span><?php esc_html_e( 'Website', 'wp-portfolio-catalogue' ); ?></span><a href="<?php echo esc_url( $data['website'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( wp_parse_url( $data['website'], PHP_URL_HOST ) ?: $data['website'] ); ?><span class="wppc-sr-only"> <?php esc_html_e( '(opens in a new tab)', 'wp-portfolio-catalogue' ); ?></span></a></li><?php endif; ?>
							</ul>
						</section>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php foreach ( array( 'social' => __( 'Social media', 'wp-portfolio-catalogue' ), 'link' => __( 'Project links', 'wp-portfolio-catalogue' ) ) as $kind => $heading ) : ?>
				<?php $links = array_filter( $data['links'], static function ( $row ) use ( $kind ) { return $kind === $row['kind'] && $row['url']; } ); ?>
				<?php if ( $links ) : ?><section class="wppc-links"><h2><?php echo esc_html( $heading ); ?></h2><ul><?php foreach ( $links as $link ) : ?><li><a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $link['label'] ?: ( wp_parse_url( $link['url'], PHP_URL_HOST ) ?: $link['url'] ) ); ?><span aria-hidden="true">↗</span><span class="wppc-sr-only"> <?php esc_html_e( '(opens in a new tab)', 'wp-portfolio-catalogue' ); ?></span></a></li><?php endforeach; ?></ul></section><?php endif; ?>
			<?php endforeach; ?>
			<?php if ( $data['events'] ) : ?>
				<section class="wppc-events"><h2><?php esc_html_e( 'Events & milestones', 'wp-portfolio-catalogue' ); ?></h2>
					<?php foreach ( $data['events'] as $event ) : ?>
						<details class="wppc-accordion"><summary><span><?php echo esc_html( $event['title'] ); ?></span><?php if ( $event['date'] ) : ?><time datetime="<?php echo esc_attr( $event['date'] ); ?>"><?php echo esc_html( self::date( $event['date'] ) ); ?></time><?php endif; ?></summary><div class="wppc-accordion-body"><?php echo wp_kses_post( wpautop( $event['description'] ) ); ?><?php if ( $event['url'] ) : ?><p><a href="<?php echo esc_url( $event['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Event details', 'wp-portfolio-catalogue' ); ?><span class="wppc-sr-only"> <?php esc_html_e( '(opens in a new tab)', 'wp-portfolio-catalogue' ); ?></span></a></p><?php endif; ?></div></details>
					<?php endforeach; ?>
				</section>
			<?php endif; ?>
			<?php if ( $data['accordions'] ) : ?>
				<section class="wppc-accordions"><h2><?php esc_html_e( 'More information', 'wp-portfolio-catalogue' ); ?></h2><?php foreach ( $data['accordions'] as $row ) : ?><details class="wppc-accordion"><summary><?php echo esc_html( $row['title'] ); ?></summary><div class="wppc-accordion-body"><?php echo wp_kses_post( wpautop( $row['content'] ) ); ?></div></details><?php endforeach; ?></section>
			<?php endif; ?>
		</div>
		<?php
		$html = ob_get_clean();
		return self::late_styles() . $html;
	}

	/** Automatic presentation retains the active theme's wrappers and main description. */
	public function automatic_project( $content ) {
		if ( self::$rendering_project || ! is_singular( 'project' ) || ! in_the_loop() || ! is_main_query() || ! Plugin::settings()['auto_details'] ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post || (int) $post->ID !== (int) get_queried_object_id() || ! Content::can_view( $post->ID ) || post_password_required( $post ) || false !== strpos( $content, 'data-wppc-project-full' ) || has_shortcode( $post->post_content, 'portfolio_project' ) ) {
			return $content;
		}
		$has_gallery = false !== strpos( $content, 'data-wppc-gallery' ) || has_shortcode( $post->post_content, 'portfolio_gallery' ) || has_shortcode( $post->post_content, 'project_images' );
		$gallery     = $has_gallery ? '' : self::render_gallery( $post->ID );
		$details     = false !== strpos( $content, 'data-wppc-project-details' ) ? '' : self::render_details( $post->ID );
		return self::back_link() . $gallery . $content . $details;
	}

	/** Link back only to a public catalogue page, otherwise the normal project archive. */
	private static function back_link() {
		$settings = Plugin::settings();
		$page     = $settings['catalogue_page'] ? get_post( $settings['catalogue_page'] ) : null;
		$url      = $page && 'page' === $page->post_type && 'publish' === $page->post_status && '' === $page->post_password ? get_permalink( $page->ID ) : get_post_type_archive_link( 'project' );
		if ( ! $url ) {
			return '';
		}
		return '<p class="wppc-back" style="--wppc-accent:' . esc_attr( $settings['accent'] ) . '"><a class="wppc-back-link" href="' . esc_url( $url ) . '"><span aria-hidden="true">←</span> ' . esc_html__( 'Back to projects', 'wp-portfolio-catalogue' ) . '</a></p>';
	}

	/** Full project shortcode with optional title/content/gallery/details controls. */
	public static function project_shortcode( $atts = array() ) {
		if ( self::$rendering_project ) {
			return '';
		}
		$atts = shortcode_atts( array( 'id' => 0, 'title' => 'true', 'content' => 'true', 'gallery' => 'true', 'details' => 'true' ), $atts, 'portfolio_project' );
		$id   = absint( $atts['id'] ) ?: get_the_ID();
		if ( ! Content::can_view( $id ) ) {
			return '';
		}
		$project  = get_post( $id );
		$previous = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$content  = '';
		self::$rendering_project = true;
		try {
			if ( self::truthy( $atts['content'] ) ) {
				$GLOBALS['post'] = $project; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored below for shortcode template context.
				setup_postdata( $project );
				$content = apply_filters( 'the_content', $project->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This is WordPress's core content hook for blocks and builder compatibility.
			}
		} finally {
			$GLOBALS['post'] = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore caller's exact post context.
			if ( $previous instanceof \WP_Post ) {
				setup_postdata( $previous );
			}
			self::$rendering_project = false;
		}
		self::enqueue_assets();
		$html = '<article class="wppc-project" data-wppc-project-full>';
		if ( is_singular( 'project' ) && (int) $id === (int) get_queried_object_id() && ( self::truthy( $atts['title'] ) || self::truthy( $atts['content'] ) || self::truthy( $atts['gallery'] ) ) ) {
			$html .= self::back_link();
		}
		if ( self::truthy( $atts['title'] ) ) {
			$html .= '<h2 class="wppc-project-title">' . esc_html( get_the_title( $id ) ) . '</h2>';
		}
		if ( self::truthy( $atts['gallery'] ) && false === strpos( $content, 'data-wppc-gallery' ) ) {
			$html .= self::render_gallery( $id );
		}
		$html .= '<div class="wppc-project-description">' . $content . '</div>';
		if ( self::truthy( $atts['details'] ) ) {
			$html .= self::render_details( $id );
		}
		return self::late_styles() . $html . '</article>';
	}
}
