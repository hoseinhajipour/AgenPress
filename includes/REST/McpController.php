<?php
/**
 * MCP-compatible tool server for external AI clients.
 *
 * @package AgenPress
 */

namespace AgenPress\REST;

use AgenPress\Agents\ToolRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Class McpController
 */
class McpController extends RestController {

	use ExternalAuth;

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/mcp/tools',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_tools' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/mcp/call',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'call_tool' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * GET /mcp/tools — MCP tool manifest.
	 *
	 * @return \WP_REST_Response
	 */
	public function list_tools(): \WP_REST_Response {
		$key = $this->authenticate_external( $this->container, 'mcp' );

		if ( is_wp_error( $key ) ) {
			return $this->error( $key );
		}

		/** @var ToolRegistry $registry */
		$registry = $this->container->get( 'tool_registry' );
		$tools    = array();

		foreach ( $registry->get_schemas() as $schema ) {
			$fn = $schema['function'] ?? array();
			$tools[] = array(
				'name'        => $fn['name'] ?? '',
				'description' => $fn['description'] ?? '',
				'inputSchema' => $fn['parameters'] ?? array( 'type' => 'object' ),
			);
		}

		return $this->success(
			array(
				'protocol' => 'mcp-rest',
				'version'  => AGENPRESS_VERSION,
				'tools'    => $tools,
			)
		);
	}

	/**
	 * POST /mcp/call — Execute a tool (MCP tools/call equivalent).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function call_tool( \WP_REST_Request $request ): \WP_REST_Response {
		$key = $this->authenticate_external( $this->container, 'mcp' );

		if ( is_wp_error( $key ) ) {
			return $this->error( $key );
		}

		$name      = sanitize_text_field( $request->get_param( 'name' ) ?? '' );
		$arguments = $request->get_param( 'arguments' ) ?? array();
		$module    = sanitize_text_field( $request->get_param( 'module' ) ?? 'admin' );

		if ( '' === $name ) {
			return $this->error(
				new \WP_Error( 'agenpress_invalid_tool', __( 'Tool name is required.', 'agenpress' ), array( 'status' => 400 ) )
			);
		}

		if ( ! is_array( $arguments ) ) {
			$arguments = array();
		}

		/** @var ToolRegistry $registry */
		$registry = $this->container->get( 'tool_registry' );
		$result   = $registry->execute( $name, $arguments, (int) $key['user_id'], $module );

		return $this->success(
			array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => wp_json_encode( $result ),
					),
				),
				'isError' => empty( $result['success'] ),
			)
		);
	}
}
