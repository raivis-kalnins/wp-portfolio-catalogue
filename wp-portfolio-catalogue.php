<?php
/**
 * Plugin Name: WP Portfolio Catalogue
 * Plugin URI: https://github.com/raivis-kalnins/wp-portfolio-catalogue
 * Description: A flexible project portfolio with filterable catalogues, a block, shortcodes, galleries, contacts, events and expandable details.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Raivis Kalnins
 * Author URI: https://digitalpulse.click/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-portfolio-catalogue
 * Domain Path: /languages
 *
 * @package WP_Portfolio_Catalogue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPPC_VERSION', '1.0.0' );
define( 'WPPC_FILE', __FILE__ );
define( 'WPPC_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPPC_URL', plugin_dir_url( __FILE__ ) );

require_once WPPC_DIR . 'includes/class-plugin.php';
require_once WPPC_DIR . 'includes/class-content.php';
require_once WPPC_DIR . 'includes/class-frontend.php';
require_once WPPC_DIR . 'includes/class-blocks.php';
require_once WPPC_DIR . 'includes/class-admin.php';

register_activation_hook( __FILE__, array( '\WP_Portfolio_Catalogue\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\WP_Portfolio_Catalogue\Plugin', 'deactivate' ) );
add_action( 'plugins_loaded', array( '\WP_Portfolio_Catalogue\Plugin', 'boot' ), 20 );
