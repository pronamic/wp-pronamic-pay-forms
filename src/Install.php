<?php
/**
 * Forms install.
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2023 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Pay
 */

namespace Pronamic\WordPress\Pay\Forms;

use Pronamic\WordPress\Pay\Upgrades\Upgrade;

/**
 * Install class
 */
class Install extends Upgrade {
	/**
	 * Execute.
	 *
	 * @link https://github.com/woocommerce/woocommerce/blob/4.0.0/includes/class-wc-install.php#L272-L306
	 * @return void
	 */
	public function execute() {
		// Add forms capabilities to administrator role.
		$roles = wp_roles();

		$form_capabilities = FormPostType::get_capabilities();

		foreach ( $form_capabilities as $capability ) {
			$roles->add_cap( 'administrator', $capability );
		}
	}
}
