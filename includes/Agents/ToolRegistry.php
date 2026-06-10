<?php
/**
 * Tool registry for agent actions.
 *
 * @package AgenPress
 */

namespace AgenPress\Agents;

use AgenPress\Security\PermissionValidator;

defined( 'ABSPATH' ) || exit;

/**
 * Class ToolRegistry
 */
class ToolRegistry {

	/**
	 * Registered tools.
	 *
	 * @var array<string, ToolInterface>
	 */
	private array $tools = array();

	/**
	 * Module assignment per tool.
	 *
	 * @var array<string, string>
	 */
	private array $tool_modules = array();

	/**
	 * Permission validator.
	 *
	 * @var PermissionValidator|null
	 */
	private ?PermissionValidator $permission_validator = null;

	/**
	 * Set permission validator.
	 *
	 * @param PermissionValidator $validator Permission validator.
	 * @return void
	 */
	public function set_permission_validator( PermissionValidator $validator ): void {
		$this->permission_validator = $validator;
	}

	/**
	 * Register a tool.
	 *
	 * @param ToolInterface $tool   Tool instance.
	 * @param string|null   $module Module slug override.
	 * @return void
	 */
	public function register( ToolInterface $tool, ?string $module = null ): void {
		$name = $tool->get_name();

		$this->tools[ $name ]        = $tool;
		$this->tool_modules[ $name ] = $module ?? ( $tool instanceof AbstractTool ? $tool->get_module() : 'admin' );
	}

	/**
	 * Get a tool by name.
	 *
	 * @param string $name Tool name.
	 * @return ToolInterface|null
	 */
	public function get( string $name ): ?ToolInterface {
		return $this->tools[ $name ] ?? null;
	}

	/**
	 * Get module for a tool.
	 *
	 * @param string $name Tool name.
	 * @return string
	 */
	public function get_module( string $name ): string {
		return $this->tool_modules[ $name ] ?? 'admin';
	}

	/**
	 * Check if tool requires confirmation.
	 *
	 * @param string $name Tool name.
	 * @return bool
	 */
	public function requires_confirmation( string $name ): bool {
		$tool = $this->get( $name );

		return $tool instanceof AbstractTool && $tool->requires_confirmation();
	}

	/**
	 * Get confirmation message for a tool.
	 *
	 * @param string               $name Tool name.
	 * @param array<string, mixed> $args Tool arguments.
	 * @return string
	 */
	public function get_confirmation_message( string $name, array $args ): string {
		$tool = $this->get( $name );

		if ( $tool instanceof AbstractTool ) {
			return $tool->get_confirmation_message( $args );
		}

		return __( 'Confirm this action?', 'agenpress' );
	}

	/**
	 * Get all registered tools.
	 *
	 * @return array<string, ToolInterface>
	 */
	public function all(): array {
		return $this->tools;
	}

	/**
	 * Get tool schemas for a specific module.
	 *
	 * @param string $module Module slug.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_schemas_for_module( string $module ): array {
		$schemas = array();

		foreach ( $this->tools as $name => $tool ) {
			if ( $this->get_module( $name ) !== $module ) {
				continue;
			}

			$schemas[] = array(
				'type'     => 'function',
				'function' => $tool->get_schema(),
			);
		}

		return $schemas;
	}

	/**
	 * Get tool schemas formatted for OpenAI function calling.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_schemas(): array {
		$schemas = array();

		foreach ( $this->tools as $tool ) {
			$schemas[] = array(
				'type'     => 'function',
				'function' => $tool->get_schema(),
			);
		}

		return $schemas;
	}

	/**
	 * Execute a tool by name.
	 *
	 * @param string               $name    Tool name.
	 * @param array<string, mixed> $args    Arguments.
	 * @param int                  $user_id User ID.
	 * @param string               $module  Module slug.
	 * @return array{success: bool, data: mixed, message: string}
	 */
	public function execute( string $name, array $args, int $user_id, string $module = 'admin' ): array {
		$tool = $this->get( $name );

		if ( ! $tool ) {
			return array(
				'success' => false,
				'data'    => null,
				'message' => sprintf( 'Tool "%s" not found.', $name ),
			);
		}

		if ( $this->permission_validator ) {
			$check = $this->permission_validator->validate_tool_action( $name, $module, $user_id );

			if ( is_wp_error( $check ) ) {
				return array(
					'success' => false,
					'data'    => null,
					'message' => $check->get_error_message(),
				);
			}
		}

		try {
			return $tool->execute( $args, $user_id );
		} catch ( \Throwable $e ) {
			return array(
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Tool execution failed: %s', 'agenpress' ),
					$e->getMessage()
				),
			);
		}
	}
}
