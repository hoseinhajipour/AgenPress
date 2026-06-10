<?php
/**
 * Queue a background agent task from admin chat.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Admin\Tools;

use AgenPress\Agents\AbstractTool;
use AgenPress\Agents\TaskTemplates;
use AgenPress\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class CreateAgentTaskTool
 */
class CreateAgentTaskTool extends AbstractTool {

	public function get_name(): string {
		return 'create_agent_task';
	}

	public function get_schema(): array {
		$templates = array( 'seo_articles', 'product_descriptions', 'site_pages', 'custom' );

		if ( ! class_exists( 'WooCommerce' ) ) {
			$templates = array( 'seo_articles', 'site_pages', 'custom' );
		}

		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			$templates = array_values( array_diff( $templates, array( 'site_pages' ) ) );
		}

		return array(
			'name'        => 'create_agent_task',
			'description' => 'Queue a background agent task for multi-step work (SEO article batches, product batches, or custom planned tasks). Use when the user asks to run work in the task queue or when a request has multiple deliverables that should execute step-by-step in Agent Tasks.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'title'       => array( 'type' => 'string', 'description' => 'Short task title' ),
					'description' => array( 'type' => 'string', 'description' => 'Full instructions for the task executor' ),
					'template'    => array(
						'type'        => 'string',
						'description' => 'Task template ID',
						'enum'        => $templates,
					),
					'params'      => array(
						'type'        => 'object',
						'description' => 'Template parameters (e.g. count, topic, niche, publish)',
					),
				),
				'required'   => array( 'title', 'description' ),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		if ( ! $this->user_can( $user_id, Capabilities::RUN_AGENTS ) ) {
			return $this->fail( __( 'You do not have permission to queue agent tasks.', 'agenpress' ) );
		}

		$title = sanitize_text_field( $args['title'] ?? '' );

		if ( '' === $title ) {
			return $this->fail( __( 'Task title is required.', 'agenpress' ) );
		}

		$description = sanitize_textarea_field( $args['description'] ?? '' );
		$template    = sanitize_key( $args['template'] ?? '' );
		$params      = is_array( $args['params'] ?? null ) ? $args['params'] : array();

		if ( '' !== $template && ! TaskTemplates::exists( $template ) ) {
			$template = '';
		}

		/** @var \AgenPress\Agents\AgentEngine $engine */
		$engine = agenpress()->container()->get( 'agent_engine' );

		$task = $engine->create_task(
			$user_id,
			'admin',
			$title,
			$description,
			array(
				'template' => $template,
				'params'   => $params,
			)
		);

		if ( ! $task ) {
			return $this->fail( __( 'Failed to queue agent task.', 'agenpress' ) );
		}

		return $this->success(
			array(
				'id'          => (int) ( $task['id'] ?? 0 ),
				'title'       => (string) ( $task['title'] ?? $title ),
				'template'    => (string) ( $task['template'] ?? 'custom' ),
				'status'      => (string) ( $task['status'] ?? 'pending' ),
				'total_steps' => (int) ( $task['total_steps'] ?? 0 ),
			),
			sprintf(
				/* translators: %s: task title */
				__( 'Queued agent task "%s". It will run step-by-step in Agent Tasks.', 'agenpress' ),
				(string) ( $task['title'] ?? $title )
			)
		);
	}
}
