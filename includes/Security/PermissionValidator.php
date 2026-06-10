<?php
/**
 * Permission validation for AI actions.
 *
 * @package AgenPress
 */

namespace AgenPress\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Class PermissionValidator
 */
class PermissionValidator {

	/**
	 * Tools that always require administrator role.
	 *
	 * @var array<string>
	 */
	private const ADMIN_ONLY_TOOLS = array(
		'delete_post',
		'delete_product',
		'change_order_status',
		'delete_widget',
		'delete_element',
	);

	/**
	 * Customer-facing storefront chat mode.
	 *
	 * @var bool
	 */
	private bool $sales_customer_mode = false;

	/**
	 * Public sales tools allowed in storefront chat.
	 *
	 * @var array<string>
	 */
	private const SALES_CUSTOMER_TOOLS = array(
		'search_products',
		'recommend_products',
		'get_product_details',
		'get_cart_summary',
		'list_coupons',
		'validate_coupon',
		'get_my_orders',
		'get_order_status',
		'escalate_to_human',
	);

	/**
	 * Audit logger instance.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit_logger;

	/**
	 * Constructor.
	 *
	 * @param AuditLogger $audit_logger Audit logger.
	 */
	public function __construct( AuditLogger $audit_logger ) {
		$this->audit_logger = $audit_logger;
	}

	/**
	 * Enable customer storefront mode for sales tool execution.
	 *
	 * @param bool $enabled Enabled state.
	 * @return void
	 */
	public function set_sales_customer_mode( bool $enabled ): void {
		$this->sales_customer_mode = $enabled;
	}

	/**
	 * Check if current user can access a module.
	 *
	 * @param string $module Module slug.
	 * @return bool
	 */
	public function can_access_module( string $module ): bool {
		$cap = Capabilities::for_module( $module );

		return current_user_can( $cap );
	}

	/**
	 * Check if current user can manage settings.
	 *
	 * @return bool
	 */
	public function can_manage_settings(): bool {
		return current_user_can( Capabilities::MANAGE_SETTINGS );
	}

	/**
	 * Check if current user can run agent tasks.
	 *
	 * @return bool
	 */
	public function can_run_agents(): bool {
		return current_user_can( Capabilities::RUN_AGENTS );
	}

	/**
	 * Check if current user can manage memory.
	 *
	 * @return bool
	 */
	public function can_manage_memory(): bool {
		return current_user_can( Capabilities::MANAGE_MEMORY );
	}

	/**
	 * Validate module access or return WP_Error.
	 *
	 * @param string $module Module slug.
	 * @return true|\WP_Error
	 */
	public function validate_module_access( string $module ) {
		if ( 'sales' === $module && $this->sales_customer_mode ) {
			return true;
		}

		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'agenpress_unauthorized',
				__( 'You must be logged in.', 'agenpress' ),
				array( 'status' => 401 )
			);
		}

		if ( ! $this->can_access_module( $module ) ) {
			$this->audit_logger->log(
				get_current_user_id(),
				'access_denied',
				$module,
				array( 'reason' => 'insufficient_capabilities' )
			);

			return new \WP_Error(
				'agenpress_forbidden',
				__( 'You do not have permission to access this module.', 'agenpress' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Validate a tool action.
	 *
	 * @param string $tool_name Tool name.
	 * @param string $module    Module slug.
	 * @param int    $user_id   User ID.
	 * @return true|\WP_Error
	 */
	public function validate_tool_action( string $tool_name, string $module, int $user_id = 0 ) {
		if ( 'sales' === $module && $this->sales_customer_mode ) {
			if ( ! in_array( $tool_name, self::SALES_CUSTOMER_TOOLS, true ) ) {
				return new \WP_Error(
					'agenpress_forbidden',
					__( 'This action is not available in the storefront chat.', 'agenpress' ),
					array( 'status' => 403 )
				);
			}

			return true;
		}

		$module_check = $this->validate_module_access( $module );

		if ( is_wp_error( $module_check ) ) {
			return $module_check;
		}

		$cap_map = array(
			'create_post'       => 'edit_posts',
			'update_post'         => 'edit_posts',
			'delete_post'         => 'delete_posts',
			'get_post'            => 'edit_posts',
			'list_posts'          => 'edit_posts',
			'list_terms'          => 'manage_categories',
			'create_term'         => 'manage_categories',
			'list_users'          => 'list_users',
			'get_user'            => 'list_users',
			'update_media'        => 'upload_files',
			'list_products'       => 'edit_products',
			'get_product'         => 'edit_products',
			'create_product'      => 'edit_products',
			'update_product'      => 'edit_products',
			'delete_product'      => 'delete_products',
			'get_site_info'       => 'read',
		);

		if ( isset( $cap_map[ $tool_name ] ) && ! user_can( $user_id, $cap_map[ $tool_name ] ) ) {
			return new \WP_Error(
				'agenpress_forbidden',
				__( 'You do not have permission to perform this action.', 'agenpress' ),
				array( 'status' => 403 )
			);
		}

		if ( in_array( $tool_name, self::ADMIN_ONLY_TOOLS, true ) && ! user_can( $user_id, 'manage_options' ) ) {
			return new \WP_Error(
				'agenpress_sensitive_action',
				__( 'This action requires administrator privileges.', 'agenpress' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}
}
