<?php
/**
 * Project editor and plugin settings.
 *
 * @package WP_Portfolio_Catalogue
 */

namespace WP_Portfolio_Catalogue;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps editing inside the standard WordPress administration screens.
 */
final class Admin {

	/**
	 * Register administration hooks.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes_project', array( $this, 'meta_boxes' ) );
		add_action( 'save_post_project', array( $this, 'save_project' ), 10, 3 );
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'manage_project_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_project_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
	}

	/**
	 * Add the native editor panels.
	 */
	public function meta_boxes() {
		$compatibility = array( '__block_editor_compatible_meta_box' => true );
		add_meta_box( 'wppc-overview', __( 'Project information', 'wp-portfolio-catalogue' ), array( $this, 'overview_box' ), 'project', 'normal', 'high', $compatibility );
		add_meta_box( 'wppc-gallery', __( 'Project gallery', 'wp-portfolio-catalogue' ), array( $this, 'gallery_box' ), 'project', 'normal', 'high', $compatibility );
		add_meta_box( 'wppc-contact', __( 'Contact details and links', 'wp-portfolio-catalogue' ), array( $this, 'contact_box' ), 'project', 'normal', 'default', $compatibility );
		add_meta_box( 'wppc-extra', __( 'Additional details, events and accordions', 'wp-portfolio-catalogue' ), array( $this, 'extra_box' ), 'project', 'normal', 'default', $compatibility );
	}

	/**
	 * Render basic project information.
	 *
	 * @param \WP_Post $post Current project.
	 */
	public function overview_box( $post ) {
		$data = Content::get_details( $post->ID );
		wp_nonce_field( 'wppc_save_project_' . $post->ID, 'wppc_project_nonce' );
		?>
		<div class="wppc-admin-panel">
			<p class="description"><?php esc_html_e( 'Add the project description in the main editor and choose a featured image in the editor sidebar. All fields below are optional; empty fields stay hidden on the website.', 'wp-portfolio-catalogue' ); ?></p>
			<div class="wppc-admin-fields">
				<?php
				$this->field( 'client', __( 'Client or organisation', 'wp-portfolio-catalogue' ), $data['client'] );
				$this->field( 'location', __( 'Location', 'wp-portfolio-catalogue' ), $data['location'] );
				$this->field( 'date_start', __( 'Start date', 'wp-portfolio-catalogue' ), $data['date_start'], 'date' );
				$this->field( 'date_end', __( 'End date', 'wp-portfolio-catalogue' ), $data['date_end'], 'date', __( 'Leave blank for a project that is still in progress.', 'wp-portfolio-catalogue' ) );
				?>
				<p class="wppc-admin-field">
					<label for="wppc_status"><?php esc_html_e( 'Project status', 'wp-portfolio-catalogue' ); ?></label>
					<select id="wppc_status" name="wppc_details[status]">
						<?php
						$statuses = array(
							''          => __( 'Not specified', 'wp-portfolio-catalogue' ),
							'planned'   => __( 'Planned', 'wp-portfolio-catalogue' ),
							'ongoing'   => __( 'In progress', 'wp-portfolio-catalogue' ),
							'completed' => __( 'Completed', 'wp-portfolio-catalogue' ),
							'on-hold'   => __( 'On hold', 'wp-portfolio-catalogue' ),
						);
						foreach ( $statuses as $value => $label ) :
							?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $data['status'], $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			</div>
			<p class="description"><?php esc_html_e( 'Use Project Categories for catalogue filters and Project Tags to organise related work.', 'wp-portfolio-catalogue' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render gallery editing controls.
	 *
	 * @param \WP_Post $post Current project.
	 */
	public function gallery_box( $post ) {
		$ids          = Content::get_gallery_ids( $post->ID );
		$has_new_meta = metadata_exists( 'post', $post->ID, '_wppc_gallery_ids' );
		$featured     = metadata_exists( 'post', $post->ID, '_wppc_featured_only' )
			? get_post_meta( $post->ID, '_wppc_featured_only', true )
			: get_post_meta( $post->ID, '_ppg_disable_gallery', true );
		?>
		<div class="wppc-admin-panel" data-wppc-admin-gallery>
			<p class="description"><?php esc_html_e( 'Select images from the Media Library. Drag to reorder or use the move buttons. Edit captions and alternative text in the Media Library. The featured image appears first and duplicate images are skipped.', 'wp-portfolio-catalogue' ); ?></p>
			<input type="hidden" name="wppc_gallery_ids" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>" data-wppc-gallery-ids />
			<input type="hidden" name="wppc_gallery_changed" value="0" data-wppc-gallery-changed />
			<ul class="wppc-admin-gallery" data-wppc-gallery-list aria-label="<?php esc_attr_e( 'Gallery image order', 'wp-portfolio-catalogue' ); ?>">
				<?php foreach ( $ids as $image_id ) : ?>
					<li class="wppc-admin-gallery-item" data-image-id="<?php echo esc_attr( $image_id ); ?>" draggable="true">
						<?php echo wp_get_attachment_image( $image_id, 'thumbnail', false, array( 'draggable' => 'false' ) ); ?>
						<div class="wppc-admin-gallery-tools">
							<?php $this->move_buttons( true ); ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( ! $has_new_meta && empty( $ids ) ) : ?>
				<?php $legacy = Content::get_gallery( $post->ID, false ); ?>
				<?php if ( ! empty( $legacy ) ) : ?>
					<div class="wppc-admin-legacy" data-wppc-legacy-gallery>
						<p><?php esc_html_e( 'This project has a gallery from Post Project Gallery. It is preserved until you add new images or choose Clear gallery.', 'wp-portfolio-catalogue' ); ?></p>
						<div class="wppc-admin-legacy-images">
							<?php foreach ( $legacy as $image ) : ?>
								<img src="<?php echo esc_url( $image['url'] ); ?>" alt="<?php echo esc_attr( $image['alt'] ); ?>" loading="lazy" width="80" height="60" />
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>
			<p class="wppc-admin-actions">
				<button type="button" class="button button-secondary" data-wppc-add-images><?php esc_html_e( 'Add gallery images', 'wp-portfolio-catalogue' ); ?></button>
				<button type="button" class="button" data-wppc-clear-gallery><?php esc_html_e( 'Clear gallery', 'wp-portfolio-catalogue' ); ?></button>
			</p>
			<p><label><input type="checkbox" name="wppc_featured_only" value="1" <?php checked( (bool) $featured ); ?> /> <?php esc_html_e( 'Show the featured image only for this project', 'wp-portfolio-catalogue' ); ?></label></p>
			<p class="description"><?php esc_html_e( 'This option keeps the saved gallery for later. Clearing a gallery does not delete any Media Library files.', 'wp-portfolio-catalogue' ); ?></p>
			<p class="screen-reader-text" aria-live="polite" data-wppc-gallery-status></p>
		</div>
		<?php
	}

	/**
	 * Render public contact fields and social/external links.
	 *
	 * @param \WP_Post $post Current project.
	 */
	public function contact_box( $post ) {
		$data = Content::get_details( $post->ID );
		?>
		<div class="wppc-admin-panel">
			<p class="description"><?php esc_html_e( 'These details appear publicly on the project page when filled in. Add only contact information you have permission to publish.', 'wp-portfolio-catalogue' ); ?></p>
			<div class="wppc-admin-fields">
				<?php
				$this->field( 'contact_name', __( 'Contact name', 'wp-portfolio-catalogue' ), $data['contact_name'] );
				$this->field( 'email', __( 'Contact email', 'wp-portfolio-catalogue' ), $data['email'], 'email' );
				$this->field( 'phone', __( 'Contact phone', 'wp-portfolio-catalogue' ), $data['phone'], 'tel' );
				$this->field( 'website', __( 'Project website', 'wp-portfolio-catalogue' ), $data['website'], 'url', __( 'Include https:// in the address.', 'wp-portfolio-catalogue' ) );
				?>
			</div>
			<?php
			$this->repeater(
				'links',
				__( 'Social media and other links', 'wp-portfolio-catalogue' ),
				__( 'Add link', 'wp-portfolio-catalogue' ),
				$data['links'],
				__( 'For example: LinkedIn, Instagram, a brochure, a case study or a booking page.', 'wp-portfolio-catalogue' )
			);
			?>
		</div>
		<?php
	}

	/**
	 * Render flexible project sections.
	 *
	 * @param \WP_Post $post Current project.
	 */
	public function extra_box( $post ) {
		$data = Content::get_details( $post->ID );
		?>
		<div class="wppc-admin-panel">
			<?php
			$this->repeater( 'details', __( 'Project details', 'wp-portfolio-catalogue' ), __( 'Add detail', 'wp-portfolio-catalogue' ), $data['details'], __( 'Add the information that fits this project, such as service, sector, budget, size, materials or technologies.', 'wp-portfolio-catalogue' ) );
			$this->repeater( 'events', __( 'Project events and milestones', 'wp-portfolio-catalogue' ), __( 'Add event', 'wp-portfolio-catalogue' ), $data['events'], __( 'Add dated milestones, launches, exhibitions or other project events. Only the title is needed to display an event.', 'wp-portfolio-catalogue' ) );
			$this->repeater( 'accordions', __( 'Accordion sections', 'wp-portfolio-catalogue' ), __( 'Add accordion', 'wp-portfolio-catalogue' ), $data['accordions'], __( 'Use collapsible sections for FAQs, specifications, credits or extra information. Basic HTML is allowed in the content.', 'wp-portfolio-catalogue' ) );
			?>
		</div>
		<?php
	}

	/**
	 * Render one scalar detail field.
	 *
	 * @param string $name Field key.
	 * @param string $label Visible label.
	 * @param string $value Stored value.
	 * @param string $type Input type.
	 * @param string $help Optional explanation.
	 */
	private function field( $name, $label, $value, $type = 'text', $help = '' ) {
		$id = 'wppc_' . $name;
		?>
		<p class="wppc-admin-field">
			<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $id ); ?>" name="wppc_details[<?php echo esc_attr( $name ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="widefat" <?php if ( $help ) : ?>aria-describedby="<?php echo esc_attr( $id . '_help' ); ?>"<?php endif; ?> />
			<?php if ( $help ) : ?><span id="<?php echo esc_attr( $id . '_help' ); ?>" class="description"><?php echo esc_html( $help ); ?></span><?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Render a repeatable group and its inert new-row template.
	 *
	 * @param string $key Data key.
	 * @param string $title Group title.
	 * @param string $button Add button label.
	 * @param array  $rows Stored rows.
	 * @param string $help Group explanation.
	 */
	private function repeater( $key, $title, $button, $rows, $help ) {
		$rows = is_array( $rows ) ? array_values( $rows ) : array();
		?>
		<section class="wppc-admin-section" data-wppc-repeater="<?php echo esc_attr( $key ); ?>" aria-labelledby="wppc_heading_<?php echo esc_attr( $key ); ?>">
			<h3 id="wppc_heading_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $title ); ?></h3>
			<p class="description"><?php echo esc_html( $help ); ?></p>
			<div class="wppc-admin-rows" data-wppc-rows>
				<?php foreach ( $rows as $index => $row ) : ?>
					<?php $this->repeater_row( $key, $index, $row ); ?>
				<?php endforeach; ?>
			</div>
			<p class="description wppc-admin-empty" data-wppc-empty <?php if ( $rows ) : ?>hidden<?php endif; ?>><?php esc_html_e( 'No items added.', 'wp-portfolio-catalogue' ); ?></p>
			<p><button type="button" class="button" data-wppc-add-row><?php echo esc_html( $button ); ?></button></p>
			<template data-wppc-row-template><?php $this->repeater_row( $key, '__INDEX__', array() ); ?></template>
			<p class="screen-reader-text" aria-live="polite" data-wppc-row-status></p>
		</section>
		<?php
	}

	/**
	 * Render a repeater row using fixed, translated field definitions.
	 *
	 * @param string     $key Repeater key.
	 * @param int|string $index Row index or template token.
	 * @param array      $row Stored row.
	 */
	private function repeater_row( $key, $index, $row ) {
		$definitions = array(
			'details'    => array(
				'label' => array( __( 'Label', 'wp-portfolio-catalogue' ), 'text' ),
				'value' => array( __( 'Value', 'wp-portfolio-catalogue' ), 'text' ),
			),
			'links'      => array(
				'label' => array( __( 'Link label', 'wp-portfolio-catalogue' ), 'text' ),
				'url'   => array( __( 'URL', 'wp-portfolio-catalogue' ), 'url' ),
				'kind'  => array( __( 'Link type', 'wp-portfolio-catalogue' ), 'select' ),
			),
			'events'     => array(
				'title'       => array( __( 'Event title', 'wp-portfolio-catalogue' ), 'text' ),
				'date'        => array( __( 'Event date', 'wp-portfolio-catalogue' ), 'date' ),
				'url'         => array( __( 'Event URL', 'wp-portfolio-catalogue' ), 'url' ),
				'description' => array( __( 'Event description', 'wp-portfolio-catalogue' ), 'textarea' ),
			),
			'accordions' => array(
				'title'   => array( __( 'Section heading', 'wp-portfolio-catalogue' ), 'text' ),
				'content' => array( __( 'Section content', 'wp-portfolio-catalogue' ), 'textarea' ),
			),
		);
		$row         = is_array( $row ) ? $row : array();
		?>
		<div class="wppc-admin-row" data-wppc-row>
			<div class="wppc-admin-row-head">
				<strong data-wppc-row-number><?php esc_html_e( 'Item', 'wp-portfolio-catalogue' ); ?></strong>
				<div class="wppc-admin-row-tools"><?php $this->move_buttons(); ?></div>
			</div>
			<div class="wppc-admin-fields">
				<?php foreach ( $definitions[ $key ] as $field => $definition ) : ?>
					<?php
					$id    = 'wppc_' . $key . '_' . $index . '_' . $field;
					$name  = 'wppc_details[' . $key . '][' . $index . '][' . $field . ']';
					$value = isset( $row[ $field ] ) && is_scalar( $row[ $field ] ) ? (string) $row[ $field ] : '';
					?>
					<p class="wppc-admin-field<?php echo 'textarea' === $definition[1] ? ' wppc-admin-field-wide' : ''; ?>">
						<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $definition[0] ); ?></label>
						<?php if ( 'textarea' === $definition[1] ) : ?>
							<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="4" class="widefat"><?php echo esc_textarea( $value ); ?></textarea>
						<?php elseif ( 'select' === $definition[1] ) : ?>
							<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
								<option value="link" <?php selected( $value, 'link' ); ?>><?php esc_html_e( 'Website or other link', 'wp-portfolio-catalogue' ); ?></option>
								<option value="social" <?php selected( $value, 'social' ); ?>><?php esc_html_e( 'Social media', 'wp-portfolio-catalogue' ); ?></option>
							</select>
						<?php else : ?>
							<input type="<?php echo esc_attr( $definition[1] ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="widefat" />
						<?php endif; ?>
					</p>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render accessible reordering/removal buttons.
	 *
	 * @param bool $gallery Whether these controls are for an image.
	 */
	private function move_buttons( $gallery = false ) {
		$labels = $gallery
			? array( __( 'Move image earlier', 'wp-portfolio-catalogue' ), __( 'Move image later', 'wp-portfolio-catalogue' ), __( 'Remove image', 'wp-portfolio-catalogue' ) )
			: array( __( 'Move item up', 'wp-portfolio-catalogue' ), __( 'Move item down', 'wp-portfolio-catalogue' ), __( 'Remove item', 'wp-portfolio-catalogue' ) );
		$icons  = array( 'arrow-up-alt2', 'arrow-down-alt2', 'no-alt' );
		$ops    = array( 'up', 'down', 'remove' );
		foreach ( $ops as $index => $op ) :
			?>
			<button type="button" class="button wppc-admin-icon" data-wppc-action="<?php echo esc_attr( $op ); ?>" aria-label="<?php echo esc_attr( $labels[ $index ] ); ?>" title="<?php echo esc_attr( $labels[ $index ] ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $icons[ $index ] ); ?>" aria-hidden="true"></span></button>
			<?php
		endforeach;
	}

	/**
	 * Save only a deliberate submission of this editor's panels.
	 *
	 * @param int      $post_id Project ID.
	 * @param \WP_Post $post Project object.
	 * @param bool     $update Whether this is an update.
	 */
	public function save_project( $post_id, $post, $update ) {
		if ( 'project' !== $post->post_type || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || ! isset( $_POST['wppc_project_nonce'] ) || ! is_string( $_POST['wppc_project_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wppc_project_nonce'] ) ), 'wppc_save_project_' . $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['wppc_details'] ) || ! is_array( $_POST['wppc_details'] ) ) {
			return;
		}
		// The schema-specific sanitizer also validates all nested repeater values.
		$data = Content::sanitize_details( wp_unslash( $_POST['wppc_details'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately by the complete schema validator.
		update_post_meta( $post_id, '_wppc_details', wp_slash( $data ) );

		if ( isset( $_POST['wppc_gallery_ids'] ) && is_string( $_POST['wppc_gallery_ids'] ) ) {
			$changed = isset( $_POST['wppc_gallery_changed'] ) && is_string( $_POST['wppc_gallery_changed'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['wppc_gallery_changed'] ) );
			if ( $changed || metadata_exists( 'post', $post_id, '_wppc_gallery_ids' ) ) {
				$raw_ids = sanitize_text_field( wp_unslash( $_POST['wppc_gallery_ids'] ) );
				$ids     = Content::sanitize_gallery( explode( ',', $raw_ids ) );
				// Keep an explicit empty array: it intentionally overrides a legacy gallery.
				update_post_meta( $post_id, '_wppc_gallery_ids', $ids );
			}
			$featured = isset( $_POST['wppc_featured_only'] ) && is_string( $_POST['wppc_featured_only'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['wppc_featured_only'] ) );
			update_post_meta( $post_id, '_wppc_featured_only', $featured ? '1' : '0' );
		}
	}

	/**
	 * Add settings beneath Projects.
	 */
	public function menu() {
		add_submenu_page( 'edit.php?post_type=project', __( 'WP Portfolio Catalogue settings', 'wp-portfolio-catalogue' ), __( 'Settings', 'wp-portfolio-catalogue' ), 'manage_options', 'wppc-settings', array( $this, 'settings_page' ) );
	}

	/**
	 * Register the settings schema and fields with WordPress.
	 */
	public function register_settings() {
		register_setting(
			'wppc_settings_group',
			'wppc_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Plugin::class, 'sanitize_settings' ),
				'show_in_rest'      => false,
			)
		);
		add_settings_section( 'wppc_display', __( 'Catalogue appearance', 'wp-portfolio-catalogue' ), '__return_false', 'wppc-settings' );
		$display = array(
			'posts_per_page' => __( 'Projects per page', 'wp-portfolio-catalogue' ),
			'columns'        => __( 'Desktop columns', 'wp-portfolio-catalogue' ),
			'excerpt_words'  => __( 'Excerpt length', 'wp-portfolio-catalogue' ),
			'accent'         => __( 'Accent colour', 'wp-portfolio-catalogue' ),
			'card_radius'    => __( 'Card corner radius', 'wp-portfolio-catalogue' ),
			'card_gap'       => __( 'Space between cards', 'wp-portfolio-catalogue' ),
		);
		foreach ( $display as $key => $label ) {
			add_settings_field( 'wppc_' . $key, $label, array( $this, 'settings_field' ), 'wppc-settings', 'wppc_display', array( 'key' => $key, 'label_for' => 'wppc_setting_' . $key ) );
		}
		add_settings_section( 'wppc_project_pages', __( 'Project pages and data', 'wp-portfolio-catalogue' ), '__return_false', 'wppc-settings' );
		$behaviour = array(
			'auto_details'   => __( 'Automatic project layout', 'wp-portfolio-catalogue' ),
			'catalogue_page' => __( 'Catalogue page', 'wp-portfolio-catalogue' ),
			'delete_data'    => __( 'Uninstall clean-up', 'wp-portfolio-catalogue' ),
		);
		foreach ( $behaviour as $key => $label ) {
			add_settings_field( 'wppc_' . $key, $label, array( $this, 'settings_field' ), 'wppc-settings', 'wppc_project_pages', array( 'key' => $key, 'label_for' => 'wppc_setting_' . $key ) );
		}
	}

	/**
	 * Render a settings field from a known key.
	 *
	 * @param array $args Field arguments.
	 */
	public function settings_field( $args ) {
		$settings = Plugin::settings();
		$key      = $args['key'];
		$id       = 'wppc_setting_' . $key;
		$name     = 'wppc_settings[' . $key . ']';
		$value    = $settings[ $key ];
		$numbers  = array(
			'posts_per_page' => array( 1, 48, __( 'The default number of projects in each results page.', 'wp-portfolio-catalogue' ) ),
			'columns'        => array( 1, 4, __( 'Cards automatically adapt to tablet and mobile widths.', 'wp-portfolio-catalogue' ) ),
			'excerpt_words'  => array( 0, 80, __( 'Number of words shown on each card. Set to 0 to hide the excerpt.', 'wp-portfolio-catalogue' ) ),
			'card_radius'    => array( 0, 40, __( 'Corner radius in pixels.', 'wp-portfolio-catalogue' ) ),
			'card_gap'       => array( 8, 64, __( 'Grid spacing in pixels.', 'wp-portfolio-catalogue' ) ),
		);
		if ( isset( $numbers[ $key ] ) ) {
			?>
			<input type="number" class="small-text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" min="<?php echo esc_attr( $numbers[ $key ][0] ); ?>" max="<?php echo esc_attr( $numbers[ $key ][1] ); ?>" step="1" aria-describedby="<?php echo esc_attr( $id . '_help' ); ?>" />
			<p id="<?php echo esc_attr( $id . '_help' ); ?>" class="description"><?php echo esc_html( $numbers[ $key ][2] ); ?></p>
			<?php
		} elseif ( 'accent' === $key ) {
			?>
			<input type="color" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" aria-describedby="<?php echo esc_attr( $id . '_help' ); ?>" />
			<p id="<?php echo esc_attr( $id . '_help' ); ?>" class="description"><?php esc_html_e( 'Choose a dark accent with good contrast against white button text.', 'wp-portfolio-catalogue' ); ?></p>
			<?php
		} elseif ( 'catalogue_page' === $key ) {
			wp_dropdown_pages(
				array(
					'id'                => esc_attr( $id ),
					'name'              => esc_attr( $name ),
					'selected'          => absint( $value ),
					'show_option_none'  => esc_html__( 'Project archive (default)', 'wp-portfolio-catalogue' ),
					'option_none_value' => '0',
				)
			);
			?><p class="description"><?php esc_html_e( 'The destination of the Back to projects link. Add the catalogue block or shortcode to the selected page yourself.', 'wp-portfolio-catalogue' ); ?></p><?php
		} elseif ( 'auto_details' === $key ) {
			?>
			<label><input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( ! empty( $value ) ); ?> /> <?php esc_html_e( 'Show gallery and project information automatically on single project pages', 'wp-portfolio-catalogue' ); ?></label>
			<p class="description"><?php esc_html_e( 'Turn this off when building your own project template. Insert [portfolio_project] and [portfolio_gallery] where you need them.', 'wp-portfolio-catalogue' ); ?></p>
			<?php
		} elseif ( 'delete_data' === $key ) {
			?>
			<label><input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( ! empty( $value ) ); ?> /> <?php esc_html_e( 'Remove this plugin’s settings, project metadata and taxonomy data when the plugin is deleted', 'wp-portfolio-catalogue' ); ?></label>
			<p class="description"><?php esc_html_e( 'Off by default. Project posts, descriptions, featured images, media files and original Post Project Gallery data are retained. Deactivating the plugin never removes content.', 'wp-portfolio-catalogue' ); ?></p>
			<?php
		}
	}

	/**
	 * Render the settings screen and builder quick start.
	 */
	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap wppc-admin-settings">
			<h1><?php esc_html_e( 'WP Portfolio Catalogue', 'wp-portfolio-catalogue' ); ?></h1>
			<p class="wppc-admin-intro"><?php esc_html_e( 'Create projects once and display them anywhere with the Portfolio Catalogue block or a shortcode.', 'wp-portfolio-catalogue' ); ?></p>
			<?php settings_errors( 'wppc_settings' ); ?>
			<div class="wppc-admin-quickstart">
				<h2><?php esc_html_e( 'Get started', 'wp-portfolio-catalogue' ); ?></h2>
				<ol>
					<li><?php esc_html_e( 'Add a Project with a title, description, featured image and optional gallery or project information.', 'wp-portfolio-catalogue' ); ?></li>
					<li><?php esc_html_e( 'Assign Project Categories to create catalogue filters.', 'wp-portfolio-catalogue' ); ?></li>
					<li><?php esc_html_e( 'Add the Portfolio Catalogue block to your homepage or any other page. In Elementor, Divi or another builder, use its Shortcode widget/module.', 'wp-portfolio-catalogue' ); ?></li>
				</ol>
				<div class="wppc-admin-shortcode"><code>[portfolio_catalogue]</code><button type="button" class="button" data-wppc-copy="[portfolio_catalogue]"><?php esc_html_e( 'Copy shortcode', 'wp-portfolio-catalogue' ); ?></button></div>
				<p class="description"><?php esc_html_e( 'Examples: choose a category by its slug, set columns, or show specific projects.', 'wp-portfolio-catalogue' ); ?></p>
				<p><code>[portfolio_catalogue category="web-design" columns="3" per_page="6"]</code></p>
				<p><code>[portfolio_catalogue ids="12,34,56" filters="false" search="false"]</code></p>
				<p class="description"><?php esc_html_e( 'To edit individual projects with Elementor, enable Projects in Elementor → Settings → General → Post Types. A dedicated Elementor add-on is not required.', 'wp-portfolio-catalogue' ); ?></p>
				<p class="screen-reader-text" aria-live="polite" data-wppc-copy-status></p>
			</div>
			<form method="post" action="options.php">
				<?php settings_fields( 'wppc_settings_group' ); ?>
				<?php do_settings_sections( 'wppc-settings' ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Load only on this plugin's screens.
	 *
	 * @param string $hook Current admin page.
	 */
	public function assets( $hook ) {
		$screen = get_current_screen();
		$editor = $screen && 'project' === $screen->post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true );
		if ( ! $editor && 'project_page_wppc-settings' !== $hook ) {
			return;
		}
		$dependencies = array();
		if ( $editor ) {
			wp_enqueue_media();
			$dependencies[] = 'media-views';
		}
		wp_enqueue_style( 'wppc-admin', WPPC_URL . 'assets/css/admin.css', array(), WPPC_VERSION );
		wp_enqueue_script( 'wppc-admin', WPPC_URL . 'assets/js/admin.js', $dependencies, WPPC_VERSION, true );
		wp_localize_script(
			'wppc-admin',
			'WPPCAdmin',
			array(
				'mediaTitle'       => __( 'Choose project gallery images', 'wp-portfolio-catalogue' ),
				'mediaButton'      => __( 'Add to project gallery', 'wp-portfolio-catalogue' ),
				'moveImageUp'      => __( 'Move image earlier', 'wp-portfolio-catalogue' ),
				'moveImageDown'    => __( 'Move image later', 'wp-portfolio-catalogue' ),
				'removeImage'      => __( 'Remove image', 'wp-portfolio-catalogue' ),
				'galleryUpdated'   => __( 'Gallery updated. Save the project to keep your changes.', 'wp-portfolio-catalogue' ),
				'galleryLimit'     => __( 'A project gallery can contain up to 100 images.', 'wp-portfolio-catalogue' ),
				'rowLimit'         => __( 'You can add up to 50 items in each section.', 'wp-portfolio-catalogue' ),
				'item'             => __( 'Item', 'wp-portfolio-catalogue' ),
				'itemAdded'        => __( 'Item added.', 'wp-portfolio-catalogue' ),
				'itemRemoved'      => __( 'Item removed.', 'wp-portfolio-catalogue' ),
				'itemMoved'        => __( 'Item moved.', 'wp-portfolio-catalogue' ),
				'copied'           => __( 'Shortcode copied.', 'wp-portfolio-catalogue' ),
				'copyManually'     => __( 'Select the shortcode and copy it with your keyboard.', 'wp-portfolio-catalogue' ),
				'endDateInvalid'   => __( 'The end date must be on or after the start date.', 'wp-portfolio-catalogue' ),
			)
		);
	}

	/**
	 * Add a featured-image column without replacing other plugin columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function columns( $columns ) {
		$output = array();
		foreach ( $columns as $key => $label ) {
			$output[ $key ] = $label;
			if ( 'cb' === $key ) {
				$output['wppc_image'] = __( 'Image', 'wp-portfolio-catalogue' );
			}
		}
		return $output;
	}

	/**
	 * Render a project's thumbnail in the list.
	 *
	 * @param string $column Column name.
	 * @param int    $post_id Project ID.
	 */
	public function column_content( $column, $post_id ) {
		if ( 'wppc_image' === $column ) {
			if ( has_post_thumbnail( $post_id ) ) {
				echo get_the_post_thumbnail( $post_id, array( 60, 45 ), array( 'alt' => '', 'loading' => 'lazy' ) );
			} else {
				echo '<span aria-label="' . esc_attr__( 'No featured image', 'wp-portfolio-catalogue' ) . '">—</span>';
			}
		}
	}

}
