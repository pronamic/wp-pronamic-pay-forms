<?php
/**
 * Form Processor
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2024 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Pay\Forms
 */

namespace Pronamic\WordPress\Pay\Forms;

use Exception;
use Pronamic\WordPress\Number\Number;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\Customer;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentLines;
use Pronamic\WordPress\Pay\Plugin;
use WP_Error;
use WP_User;

/**
 * Form Processor
 *
 * @author Remco Tolsma
 * @version 2.7.1
 * @since 3.7.0
 */
class FormProcessor {
	/**
	 * Construct form processor object.
	 */
	public function __construct() {
		// Actions.
		add_action( 'init', $this->init( ... ) );
	}

	/**
	 * Get amount hash.
	 *
	 * Forms that are not stored as a payment form post, such as the payment
	 * form block, have no settings to look up on submit. The fixed amount of
	 * these forms is therefore signed when the form is rendered.
	 *
	 * @param string $source    Source.
	 * @param int    $source_id Source ID.
	 * @param Money  $amount    Amount.
	 * @return string
	 */
	public static function get_amount_hash( $source, $source_id, Money $amount ) {
		return \wp_hash( 'pronamic_pay_form_amount|' . $source . '|' . $source_id . '|' . $amount->get_minor_units()->get_value() );
	}

	/**
	 * Get posted value.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private function get_posted_value( $key ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Public form without nonce, so it keeps working on cached pages.
		if ( ! \array_key_exists( $key, $_POST ) || ! \is_string( $_POST[ $key ] ) ) {
			return '';
		}

		return \sanitize_text_field( \wp_unslash( $_POST[ $key ] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Get amount.
	 *
	 * The amount is determined from the form settings, posted values are only
	 * accepted as far as the form settings allow them.
	 *
	 * @param string $source    Source.
	 * @param int    $source_id Source ID.
	 * @return Money
	 * @throws Exception When the amount is not valid for the form.
	 */
	private function get_amount( $source, $source_id ) {
		$amount = $this->get_form_amount( $source, $source_id );

		if ( (float) $amount->get_value() <= 0 ) {
			throw new Exception( 'Amount must be greater than zero.' );
		}

		return $amount;
	}

	/**
	 * Get form amount.
	 *
	 * @param string $source    Source.
	 * @param int    $source_id Source ID.
	 * @return Money
	 * @throws Exception When the amount is not valid for the form.
	 */
	private function get_form_amount( $source, $source_id ) {
		$value = $this->get_posted_value( 'pronamic_pay_amount' );

		// Forms without stored settings, the fixed amount is signed on render.
		if ( FormsSource::PAYMENT_FORM !== $source ) {
			$amount = new Money( Number::from_string( $value ), 'EUR' );

			$hash = self::get_amount_hash( $source, $source_id, $amount );

			if ( ! \hash_equals( $hash, $this->get_posted_value( 'pronamic_pay_amount_hash' ) ) ) {
				throw new Exception( 'Amount does not match the form.' );
			}

			return $amount;
		}

		// Payment forms.
		$method  = \get_post_meta( $source_id, '_pronamic_payment_form_amount_method', true );
		$choices = \get_post_meta( $source_id, '_pronamic_payment_form_amount_choices', true );
		$choices = \is_array( $choices ) ? \array_map( intval( ... ), $choices ) : [];

		switch ( $method ) {
			case FormPostType::AMOUNT_METHOD_INPUT_FIXED:
				$choice = \reset( $choices );

				if ( false === $choice ) {
					throw new Exception( 'Form has no amount.' );
				}

				return new Money( Number::from_int( $choice )->divide( Number::from_int( 100 ) ), 'EUR' );
			case FormPostType::AMOUNT_METHOD_INPUT_ONLY:
				return new Money( Number::from_string( $value ), 'EUR' );
			case FormPostType::AMOUNT_METHOD_CHOICES_AND_INPUT:
				if ( 'other' === $value ) {
					return new Money( Number::from_string( $this->get_posted_value( 'pronamic_pay_amount_other' ) ), 'EUR' );
				}

				// Not the input field, continue with the choices.
			case FormPostType::AMOUNT_METHOD_CHOICES_ONLY:
				$amount = new Money( Number::from_string( $value ), 'EUR' );

				$minor_units = $amount->get_minor_units();

				if ( ! $minor_units->is_whole_number() || ! \in_array( $minor_units->to_int(), $choices, true ) ) {
					throw new Exception( 'Amount is not one of the form choices.' );
				}

				return $amount;
			default:
				throw new Exception( 'Form has no amount method.' );
		}
	}

	/**
	 * Initialize.
	 *
	 * @return void
	 * @throws Exception When processing form fails on creating WordPress user.
	 */
	public function init() {
		global $pronamic_pay_errors;

		$pronamic_pay_errors = [];

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Public form without nonce, so it keeps working on cached pages.

		// Form submit.
		if ( ! \array_key_exists( 'pronamic_pay', $_POST ) ) {
			return;
		}

		// Source.
		$source    = array_key_exists( 'pronamic_pay_source', $_POST ) ? \sanitize_text_field( \wp_unslash( $_POST['pronamic_pay_source'] ) ) : '';
		$source_id = array_key_exists( 'pronamic_pay_source_id', $_POST ) ? (int) \sanitize_text_field( \wp_unslash( $_POST['pronamic_pay_source_id'] ) ) : 0;

		if ( ! FormsSource::is_valid( $source ) ) {
			return;
		}

		if ( FormsSource::PAYMENT_FORM === $source && 'pronamic_pay_form' !== \get_post_type( $source_id ) ) {
			return;
		}

		// Validate.
		$valid = $this->validate( $source, $source_id );

		if ( ! $valid ) {
			return;
		}

		// Config ID, the payment form block uses the default configuration.
		$config_id = get_option( 'pronamic_pay_config_id' );

		if ( FormsSource::PAYMENT_FORM === $source ) {
			$config_id = get_post_meta( $source_id, '_pronamic_payment_form_config_id', true );
		}

		/*
		 * Start payment.
		 */
		$first_name = array_key_exists( 'pronamic_pay_first_name', $_POST ) ? \sanitize_text_field( \wp_unslash( $_POST['pronamic_pay_first_name'] ) ) : '';
		$last_name  = array_key_exists( 'pronamic_pay_last_name', $_POST ) ? \sanitize_text_field( \wp_unslash( $_POST['pronamic_pay_last_name'] ) ) : '';
		$email      = filter_input( INPUT_POST, 'pronamic_pay_email', FILTER_VALIDATE_EMAIL );
		$order_id   = (string) time();

		$description = null;

		if ( FormsSource::PAYMENT_FORM === $source ) {
			$description = get_post_meta( $source_id, '_pronamic_payment_form_description', true );

			if ( ! empty( $description ) ) {
				$description = sprintf( '%s %s', $description, $order_id );
			}
		}

		if ( empty( $description ) ) {
			$description = sprintf(
				/* translators: %s: order id */
				__( 'Payment Form %s', 'pronamic-pay-forms' ),
				$order_id
			);
		}

		$payment = new Payment();

		$payment->title = sprintf(
			/* translators: %s: payment data title */
			__( 'Payment for %s', 'pronamic-pay-forms' ),
			$description
		);

		$payment->set_config_id( $config_id );
		$payment->set_description( $description );
		$payment->set_origin_id( $source_id );

		$payment->order_id  = $order_id;
		$payment->source    = $source;
		$payment->source_id = $source_id;

		// Name.
		$name = null;

		if ( ! empty( $first_name ) || ! empty( $last_name ) ) {
			$name = new ContactName();

			if ( ! empty( $first_name ) ) {
				$name->set_first_name( $first_name );
			}

			if ( ! empty( $last_name ) ) {
				$name->set_last_name( $last_name );
			}
		}

		// Customer.
		$customer = null;

		if ( null !== $name || ! empty( $email ) ) {
			$customer = new Customer();

			$customer->set_name( $name );

			if ( ! empty( $email ) ) {
				$customer->set_email( $email );
			}
		}

		$payment->set_customer( $customer );

		// Amount.
		$payment->set_total_amount( $this->get_amount( $source, $source_id ) );

		// Method.
		$payment_method = \array_key_exists( 'payment_method', $_POST ) ? \sanitize_text_field( \wp_unslash( $_POST['payment_method'] ) ) : null;

		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$payment->set_payment_method( $payment_method );

		// Payment lines.
		$payment->lines = new PaymentLines();

		$line = $payment->lines->new_line();

		// Set line properties.
		$line->set_id( strval( $order_id ) );
		$line->set_name( $description );
		$line->set_quantity( new Number( 1 ) );
		$line->set_unit_price( $payment->get_total_amount() );
		$line->set_total_amount( $payment->get_total_amount() );

		// Gateway.
		$gateway = Plugin::get_gateway( $config_id );

		if ( null === $gateway ) {
			return;
		}

		// Start payment.
		try {
			$payment = Plugin::start_payment( $payment );
		} catch ( \Exception $e ) {
			Plugin::render_exception( $e );

			exit;
		}

		$gateway->redirect( $payment );

		exit;
	}

	/**
	 * Validate.
	 *
	 * @param string $source    Source.
	 * @param int    $source_id Source ID.
	 * @return boolean True if valid, false otherwise.
	 */
	private function validate( $source, $source_id ) {
		global $pronamic_pay_errors;

		// Amount.
		try {
			$this->get_amount( $source, $source_id );
		} catch ( \Exception ) {
			$pronamic_pay_errors['amount'] = __( 'Please enter a valid amount', 'pronamic-pay-forms' );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Public form without nonce, so it keeps working on cached pages.

		// First Name.
		if ( \array_key_exists( 'pronamic_pay_first_name', $_POST ) ) {
			$first_name = \sanitize_text_field( \wp_unslash( $_POST['pronamic_pay_first_name'] ) );

			if ( empty( $first_name ) ) {
				$pronamic_pay_errors['first_name'] = __( 'Please enter your first name', 'pronamic-pay-forms' );
			}
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// E-mail.
		$email = filter_input( INPUT_POST, 'pronamic_pay_email', FILTER_VALIDATE_EMAIL );

		if ( empty( $email ) ) {
			$pronamic_pay_errors['email'] = __( 'Please enter a valid email address', 'pronamic-pay-forms' );
		}

		return empty( $pronamic_pay_errors );
	}
}
