<?php
/**
 * Form Shortcode
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2024 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Pay\Forms
 */

namespace Pronamic\WordPress\Pay\Forms;

/**
 * Form Shortcode
 *
 * @author  Remco Tolsma
 * @version 2.2.6
 * @since   3.7.0
 */
class FormShortcode {
	/**
	 * Forms integration.
	 *
	 * @var Integration
	 */
	private $integration;

	/**
	 * Constructs and initializes an post types object.
	 *
	 * @param Integration $integration Reference to the forms integration.
	 */
	public function __construct( Integration $integration ) {
		$this->integration = $integration;

		add_shortcode( 'pronamic_payment_form', [ $this, 'shortcode_form' ] );
	}

	/**
	 * Shortcode form.
	 *
	 * @link https://github.com/WordImpress/Give/blob/1.1/includes/shortcodes.php#L39-L65
	 * @link https://github.com/WordImpress/Give/blob/1.1/includes/forms/template.php#L18-L140
	 *
	 * @param array $atts Shortcode attributes array.
	 *
	 * @return string
	 *
	 * @throws \Exception Throws exception if output buffering is not active.
	 */
	public function shortcode_form( $atts ) {
		$atts = shortcode_atts(
			[
				'id' => null,
			],
			$atts,
			'pronamic_payment_form'
		);

		if ( empty( $atts['id'] ) ) {
			return '';
		}

		return $this->integration->get_form_output_by_id( (int) $atts['id'] );
	}
}
