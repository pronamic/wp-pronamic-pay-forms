<?php
/**
 * Pronamic Pay Forms
 *
 * @author    Pronamic
 * @copyright 2005-2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\WordPress\Pay\Forms
 *
 * @wordpress-plugin
 * Plugin Name:       Pronamic Pay Forms
 * Plugin URI:        https://www.pronamic.com/
 * Description:       A modern WordPress plugin for Pronamic Pay Forms, providing a structured foundation for building scalable and maintainable plugins.
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      8.2
 * Author:            Pronamic
 * Author URI:        https://www.pronamic.com/
 * Text Domain:       pronamic-pay-forms
 * Domain Path:       /languages/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * GitHub URI:        https://github.com/pronamic/wp-pronamic-pay-forms
 * Requires Plugins:  pronamic-ideal
 */

declare(strict_types=1);

namespace Pronamic\WordPress\Pay\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Autoload.
 */
$autoload_path = __DIR__ . '/vendor/autoload_packages.php';

if ( \file_exists( $autoload_path ) ) {
	require_once $autoload_path;
}

/**
 * Bootstrap.
 */
\add_action(
	'plugins_loaded',
	function () {
		\load_plugin_textdomain( 'pronamic-pay-forms', false, \dirname( \plugin_basename( __FILE__ ) ) . '/languages' );
	}
);
