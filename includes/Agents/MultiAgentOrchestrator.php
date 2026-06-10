<?php
/**
 * Multi-agent orchestration for enterprise tier.
 *
 * @package AgenPress
 */

namespace AgenPress\Agents;

use AgenPress\AI\ProviderFactory;
use AgenPress\Modules\ModuleManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class MultiAgentOrchestrator
 */
class MultiAgentOrchestrator {

	/**
	 * Specialist definitions.
	 *
	 * @var array<string, array{module: string, label: string, keywords: array<string>}>
	 */
	private const SPECIALISTS = array(
		'content' => array(
			'module'   => 'admin',
			'label'    => 'Content Specialist',
			'keywords' => array( 'post', 'blog', 'article', 'seo', 'page', 'write', 'content', 'category', 'tag' ),
		),
		'design'  => array(
			'module'   => 'elementor',
			'label'    => 'Design Specialist',
			'keywords' => array( 'design', 'layout', 'section', 'hero', 'color', 'font', 'elementor', 'widget', 'landing' ),
		),
		'sales'   => array(
			'module'   => 'sales',
			'label'    => 'Sales Specialist',
			'keywords' => array( 'product', 'order', 'cart', 'coupon', 'customer', 'woocommerce', 'shipping', 'price' ),
		),
	);

	/**
	 * Provider factory.
	 *
	 * @var ProviderFactory
	 */
	private ProviderFactory $provider_factory;

	/**
	 * Module manager.
	 *
	 * @var ModuleManager
	 */
	private ModuleManager $module_manager;

	/**
	 * Agent engine.
	 *
	 * @var AgentEngine
	 */
	private AgentEngine $agent_engine;

	/**
	 * Constructor.
	 *
	 * @param ProviderFactory $provider_factory Provider factory.
	 * @param ModuleManager   $module_manager   Module manager.
	 * @param AgentEngine     $agent_engine     Agent engine.
	 */
	public function __construct(
		ProviderFactory $provider_factory,
		ModuleManager $module_manager,
		AgentEngine $agent_engine
	) {
		$this->provider_factory = $provider_factory;
		$this->module_manager   = $module_manager;
		$this->agent_engine     = $agent_engine;
	}

	/**
	 * List available specialists.
	 *
	 * @return array<int, array{id: string, label: string, module: string}>
	 */
	public function list_specialists(): array {
		$list = array();

		foreach ( self::SPECIALISTS as $id => $spec ) {
			$module = $this->module_manager->get( $spec['module'] );
			if ( $module && ! $module->is_available() ) {
				continue;
			}

			$list[] = array(
				'id'     => $id,
				'label'  => $spec['label'],
				'module' => $spec['module'],
			);
		}

		return $list;
	}

	/**
	 * Route a message to the best specialist.
	 *
	 * @param string $message User message.
	 * @return string Specialist ID.
	 */
	public function route( string $message ): string {
		$message_lower = strtolower( $message );
		$scores        = array();

		foreach ( self::SPECIALISTS as $id => $spec ) {
			$module = $this->module_manager->get( $spec['module'] );
			if ( $module && ! $module->is_available() ) {
				continue;
			}

			$score = 0;
			foreach ( $spec['keywords'] as $keyword ) {
				if ( str_contains( $message_lower, $keyword ) ) {
					++$score;
				}
			}
			$scores[ $id ] = $score;
		}

		arsort( $scores );
		$best = array_key_first( $scores );

		if ( $best && ( $scores[ $best ] ?? 0 ) > 0 ) {
			return $best;
		}

		return $this->route_with_ai( $message );
	}

	/**
	 * Orchestrate a chat message through the best specialist.
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $message         User message.
	 * @param int    $user_id         User ID.
	 * @param string $specialist_id   Optional forced specialist.
	 * @return array{message: array<string, mixed>, tokens_used: int, model: string, specialist: string, module: string}
	 */
	public function orchestrate( int $conversation_id, string $message, int $user_id, string $specialist_id = '' ): array {
		$specialist_id = $specialist_id && isset( self::SPECIALISTS[ $specialist_id ] )
			? $specialist_id
			: $this->route( $message );

		$spec   = self::SPECIALISTS[ $specialist_id ] ?? self::SPECIALISTS['content'];
		$module = $spec['module'];

		$base_prompt = $this->module_manager->get_system_prompt( $module );
		$prompt      = implode(
			"\n\n",
			array(
				'You are the ' . $spec['label'] . ' in a multi-agent AgenPress system.',
				$base_prompt,
			)
		);

		$response = $this->agent_engine->chat(
			$conversation_id,
			$module,
			$message,
			$user_id,
			$prompt
		);

		return array_merge(
			$response,
			array(
				'specialist' => $specialist_id,
				'module'     => $module,
			)
		);
	}

	/**
	 * AI-based routing fallback.
	 *
	 * @param string $message User message.
	 * @return string
	 */
	private function route_with_ai( string $message ): string {
		try {
			$provider = $this->provider_factory->get();
			$response = $provider->chat(
				array(
					array(
						'role'    => 'system',
						'content' => 'Classify the user request into exactly one specialist: content, design, or sales. Reply with only the specialist id.',
					),
					array(
						'role'    => 'user',
						'content' => $message,
					),
				)
			);

			$choice = strtolower( trim( $response['content'] ?? '' ) );

			if ( isset( self::SPECIALISTS[ $choice ] ) ) {
				return $choice;
			}
		} catch ( \Exception $e ) {
			// Fall through to default.
		}

		return 'content';
	}
}
