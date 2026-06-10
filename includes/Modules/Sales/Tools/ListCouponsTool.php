<?php
/**
 * List active public coupons.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Sales\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class ListCouponsTool
 */
class ListCouponsTool extends SalesAbstractTool {

	public function get_name(): string {
		return 'list_coupons';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'list_coupons',
			'description' => 'List currently active store coupons (public info only)',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'limit' => array( 'type' => 'integer', 'description' => 'Max results (default 10)' ),
				),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		$coupons = get_posts(
			array(
				'post_type'      => 'shop_coupon',
				'post_status'    => 'publish',
				'posts_per_page' => min( 20, (int) ( $args['limit'] ?? 10 ) ),
			)
		);

		$data = array();

		foreach ( $coupons as $post ) {
			$coupon = new \WC_Coupon( $post->ID );
			if ( ! $coupon->get_id() ) {
				continue;
			}

			$data[] = array(
				'code'          => $coupon->get_code(),
				'discount_type' => $coupon->get_discount_type(),
				'amount'        => $coupon->get_amount(),
				'description'   => $coupon->get_description(),
				'expiry_date'   => $coupon->get_date_expires() ? $coupon->get_date_expires()->date( 'Y-m-d' ) : null,
			);
		}

		return $this->success( $data, sprintf( __( 'Found %d coupons.', 'agenpress' ), count( $data ) ) );
	}
}
