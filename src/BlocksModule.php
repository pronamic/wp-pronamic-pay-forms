<?php
/**
 * Editor Blocks.
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2024 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Pay
 */

namespace Pronamic\WordPress\Pay\Forms;

use Pronamic\WordPress\Number\Number;
use Pronamic\WordPress\Number\Parser as NumberParser;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Forms\FormsSource;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Plugin;
use WP_Error;

/**
 * Blocks
 *
 * @author  Reüel van der Steege
 * @since   2.5.0
 * @version 2.1.7
 */
class BlocksModule {
	/**
	 * Constructs and initializes a blocks module.
	 *
	 * @param Integration $integration Reference to the forms integration.
	 */
	public function __construct(
		/**
		 * Forms integration.
		 */
		private readonly Integration $integration
	) {
	}

	/**
	 * Setup.
	 *
	 * @return void
	 */
	public function setup() {
		global $wp_version;

		// Initialize.
		add_action( 'init', $this->register_block_types( ... ) );

		add_action( 'enqueue_block_editor_assets', $this->enqueue_styles( ... ) );

		// Source text and description.
		add_filter( 'pronamic_payment_source_url_' . FormsSource::BLOCK_PAYMENT_FORM, $this->source_url( ... ), 10, 2 );
		add_filter( 'pronamic_payment_source_text_' . FormsSource::BLOCK_PAYMENT_FORM, $this->source_text( ... ), 10, 2 );
		add_filter( 'pronamic_payment_source_description_' . FormsSource::BLOCK_PAYMENT_FORM, $this->source_description( ... ) );
	}

	/**
	 * Register block types.
	 *
	 * @return void
	 */
	public function register_block_types() {
		$block_type = register_block_type(
			__DIR__ . '/../blocks/payment-form',
			[
				'render_callback' => $this->render_payment_form_block( ... ),
			]
		);

		if ( false === $block_type ) {
			return;
		}

		// Localize editor script.
		foreach ( $block_type->editor_script_handles as $handle ) {
			wp_localize_script(
				$handle,
				'pronamic_payment_form',
				[
					'title'          => _x( 'Payment Form', 'Block', 'pronamic-pay-forms' ),
					'label_add_form' => __( 'Add form', 'pronamic-pay-forms' ),
					'label_amount'   => __( 'Amount', 'pronamic-pay-forms' ),
				]
			);
		}
	}

	/**
	 * Enqueue styles.
	 *
	 * @return void
	 */
	public function enqueue_styles() {
		\wp_enqueue_style( 'pronamic-pay-forms' );
	}

	/**
	 * Render payment form block.
	 *
	 * @param array $attributes Attributes.
	 *
	 * @return string
	 *
	 * @throws \Exception When output buffering is not working as expected.
	 */
	public function render_payment_form_block( $attributes = [] ) {
		// Amount.
		$amounts = [];

		if ( ! empty( $attributes['amount'] ) ) {
			try {
				$amounts[] = Number::from_mixed( $attributes['amount'] );
			} catch ( \Exception ) {
				/**
				 * It is possible that in the past localized numbers were stored in the amount attribute.
				 */
				try {
					$parser = new NumberParser();

					$amounts[] = $parser->parse( $attributes['amount'] );
				} catch ( \Exception ) {
					return '';
				}
			}
		}

		// Form settings.
		$args = [
			'amounts'   => $amounts,
			'html_id'   => sprintf( 'pronamic-pay-payment-form-%s', get_the_ID() ),
			'source'    => FormsSource::BLOCK_PAYMENT_FORM,
			'source_id' => get_the_ID(),
		];

		// Check valid gateway.
		$config_id = get_option( 'pronamic_pay_config_id' );

		$gateway = Plugin::get_gateway( $config_id );

		if ( null === $gateway ) {
			return \__( 'It is currently not possible to pay via this form, please contact us for more information (error: no payment gateway found).', 'pronamic-pay-forms' );
		}

		$this->enqueue_styles();

		// Return form output.
		return $this->integration->get_form_output( $args );
	}

	/**
	 * Source text filter.
	 *
	 * @param string  $text    The source text to filter.
	 * @param Payment $payment The payment for the specified source text.
	 *
	 * @return string
	 */
	public function source_text( $text, Payment $payment ) {
		$text = __( 'Payment Form Block', 'pronamic-pay-forms' );

		if ( empty( $payment->source_id ) ) {
			return $text;
		}

		$link = get_edit_post_link( intval( $payment->source_id ) );

		if ( null === $link ) {
			return $text;
		}

		$text .= '<br />';

		$text .= sprintf(
			'<a href="%s">%s</a>',
			esc_url( $link ),
			esc_html( strval( $payment->source_id ) )
		);

		return $text;
	}

	/**
	 * Source description filter.
	 *
	 * @param string $text The source text to filter.
	 *
	 * @return string
	 */
	public function source_description( $text ) {
		$text = __( 'Payment Form Block', 'pronamic-pay-forms' ) . '<br />';

		return $text;
	}

	/**
	 * Source URL.
	 *
	 * @param string  $url     Source URL.
	 * @param Payment $payment Payment.
	 *
	 * @return string
	 */
	public function source_url( $url, Payment $payment ) {
		if ( empty( $payment->source_id ) ) {
			return $url;
		}

		$link = get_edit_post_link( intval( $payment->source_id ) );

		if ( null === $link ) {
			return $url;
		}

		return $link;
	}
}
