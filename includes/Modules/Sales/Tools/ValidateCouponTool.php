<?php
/**
 * Validate a WooCommerce coupon code.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Sales\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class ValidateCouponTool
 */
class ValidateCouponTool extends SalesAbstractTool {

	public function get_name(): string {
		return 'validate_coupon';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'validate_coupon',
			'description' => 'Check if a coupon code is valid and return its discount details',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'code' => array( 'type' => 'string', 'description' => 'Coupon code' ),
				),
				'required'   => array( 'code' ),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		$code   = sanitize_text_field( $args['code'] ?? '' );
		$coupon = new \WC_Coupon( $code );

		if ( ! $coupon->get_id() ) {
			return $this->fail( __( 'Coupon not found.', 'agenpress' ) );
		}

		$valid = $coupon->is_valid();

		if ( is_wp_error( $valid ) ) {
			return $this->fail( $valid->get_error_message() );
		}

		return $this->success(
			array(
				'code'          => $coupon->get_code(),
				'discount_type' => $coupon->get_discount_type(),
				'amount'        => $coupon->get_amount(),
				'description'   => $coupon->get_description(),
				'valid'         => true,
			),
			__( 'Coupon is valid.', 'agenpress' )
		);
	}
}
