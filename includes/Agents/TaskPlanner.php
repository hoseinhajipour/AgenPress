<?php
/**
 * Plans agentic tasks into executable steps.
 *
 * @package AgenPress
 */

namespace AgenPress\Agents;

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskPlanner
 */
class TaskPlanner {

	/**
	 * Plan steps for a new task.
	 *
	 * @param string               $title       Task title.
	 * @param string               $description Task description.
	 * @param string               $module      Module slug.
	 * @param array<string, mixed> $options     Options (template, params).
	 * @return array{template: string, steps: array<int, array<string, mixed>>}
	 */
	public function plan( string $title, string $description, string $module, array $options = array() ): array {
		$template_id = TaskTemplates::resolve(
			$title,
			$description,
			sanitize_key( $options['template'] ?? '' )
		);

		$params = is_array( $options['params'] ?? null ) ? $options['params'] : array();
		$steps  = TaskTemplates::build_steps( $template_id, $title, $description, $params );

		return array(
			'template' => $template_id,
			'steps'    => $steps,
		);
	}
}
