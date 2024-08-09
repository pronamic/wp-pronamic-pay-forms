<?php
/**
 * Forms admin.
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2024 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Pay
 */

namespace Pronamic\WordPress\Pay\Forms;

/**
 * Admin class
 */
class Admin {
	/**
	 * Construct and initialize forms admin.
	 */
	public function __construct() {
		\add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		\add_filter( 'parent_file', [ $this, 'admin_menu_parent_file' ] );
	}

	/**
	 * Add submenu pages.
	 *
	 * @return void
	 */
	public function admin_menu() {
		\add_submenu_page(
			'pronamic_ideal',
			\__( 'Payment Forms', 'pronamic_ideal' ),
			\__( 'Forms', 'pronamic_ideal' ),
			'edit_forms',
			'edit.php?post_type=pronamic_pay_form',
			'',
			3,
		);
	}

	/**
	 * Admin menu parent file.
	 *
	 * @param string $parent_file Parent file for admin menu.
	 * @return string
	 */
	public function admin_menu_parent_file( $parent_file ) {
		$screen = \get_current_screen();

		if ( null === $screen ) {
			return $parent_file;
		}

		switch ( $screen->id ) {
			case FormPostType::POST_TYPE:
				return 'pronamic_ideal';
		}

		return $parent_file;
	}
}
