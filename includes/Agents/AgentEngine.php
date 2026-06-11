<?php
/**
 * Agent engine — orchestrates AI chat and tool execution.
 *
 * @package AgenPress
 */

namespace AgenPress\Agents;

use AgenPress\AI\ProviderFactory;
use AgenPress\Chat\MessageRepository;
use AgenPress\Media\AttachmentContext;
use AgenPress\Media\ReferenceImagePreparer;
use AgenPress\Memory\ContextBuilder;
use AgenPress\Sales\ProductLinkFixer;
use AgenPress\Security\AuditLogger;
use AgenPress\Security\PermissionValidator;

defined( 'ABSPATH' ) || exit;

/**
 * Class AgentEngine
 */
class AgentEngine {

	/**
	 * Max tool-call follow-up rounds.
	 */
	private const MAX_TOOL_ROUNDS = 5;

	/**
	 * Extra tool rounds for Elementor (multi-step page building).
	 */
	private const MAX_TOOL_ROUNDS_ELEMENTOR = 10;

	/**
	 * Provider factory.
	 *
	 * @var ProviderFactory
	 */
	private ProviderFactory $provider_factory;

	/**
	 * Tool registry.
	 *
	 * @var ToolRegistry
	 */
	private ToolRegistry $tool_registry;

	/**
	 * Task queue.
	 *
	 * @var TaskQueue
	 */
	private TaskQueue $task_queue;

	/**
	 * Context builder.
	 *
	 * @var ContextBuilder
	 */
	private ContextBuilder $context_builder;

	/**
	 * Message repository.
	 *
	 * @var MessageRepository
	 */
	private MessageRepository $message_repository;

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit_logger;

	/**
	 * Pending action store.
	 *
	 * @var PendingActionStore
	 */
	private PendingActionStore $pending_actions;

	/**
	 * Permission validator.
	 *
	 * @var PermissionValidator
	 */
	private PermissionValidator $permission_validator;

	/**
	 * Chat task auto-planner.
	 *
	 * @var ChatTaskAutoPlanner
	 */
	private ChatTaskAutoPlanner $chat_task_planner;

	/**
	 * Constructor.
	 *
	 * @param ProviderFactory      $provider_factory      Provider factory.
	 * @param ToolRegistry         $tool_registry         Tool registry.
	 * @param TaskQueue            $task_queue            Task queue.
	 * @param ContextBuilder       $context_builder       Context builder.
	 * @param MessageRepository    $message_repository    Message repository.
	 * @param AuditLogger          $audit_logger          Audit logger.
	 * @param PendingActionStore   $pending_actions       Pending actions.
	 * @param PermissionValidator  $permission_validator  Permission validator.
	 * @param ChatTaskAutoPlanner  $chat_task_planner     Chat task auto-planner.
	 */
	public function __construct(
		ProviderFactory $provider_factory,
		ToolRegistry $tool_registry,
		TaskQueue $task_queue,
		ContextBuilder $context_builder,
		MessageRepository $message_repository,
		AuditLogger $audit_logger,
		PendingActionStore $pending_actions,
		PermissionValidator $permission_validator,
		ChatTaskAutoPlanner $chat_task_planner
	) {
		$this->provider_factory     = $provider_factory;
		$this->tool_registry        = $tool_registry;
		$this->task_queue           = $task_queue;
		$this->context_builder      = $context_builder;
		$this->message_repository   = $message_repository;
		$this->audit_logger         = $audit_logger;
		$this->pending_actions      = $pending_actions;
		$this->permission_validator = $permission_validator;
		$this->chat_task_planner    = $chat_task_planner;
	}

	/**
	 * Process a chat message and return AI response.
	 *
	 * @param int                  $conversation_id Conversation ID.
	 * @param string               $module          Module slug.
	 * @param string               $user_message    User message content.
	 * @param int                  $user_id         User ID.
	 * @param string               $system_prompt   System prompt.
	 * @param array<string, mixed> $attachments     Attachments.
	 * @param array<string, mixed> $context         Module context (e.g. Elementor selection).
	 * @return array{message: array<string, mixed>, tokens_used: int, model: string, pending_actions?: array<int, array<string, mixed>>}
	 */
	public function chat(
		int $conversation_id,
		string $module,
		string $user_message,
		int $user_id,
		string $system_prompt = '',
		array $attachments = array(),
		array $context = array()
	): array {
		$user_message_record = $this->message_repository->create(
			$conversation_id,
			'user',
			$user_message,
			$attachments
		);

		if ( 'admin' === $module ) {
			$queued = $this->chat_task_planner->try_queue( $user_message, $user_id, $attachments );

			if ( null !== $queued && ! empty( $queued['tasks'] ) ) {
				return $this->queued_tasks_response(
					$conversation_id,
					$user_message_record,
					$queued['tasks'],
					(string) ( $queued['summary'] ?? '' ),
					$user_id,
					$module
				);
			}
		}

		$messages = $this->message_repository->get_ai_messages( $conversation_id );
		$messages = $this->prepend_system_prompt( $messages, $system_prompt, $module, $user_message, $context );

		$tools        = $this->tool_registry->get_schemas_for_module( $module );
		$provider     = $this->provider_factory->get();
		$total_tokens = 0;
		$model        = '';

		try {
			$response = $provider->chat( $messages, $tools );
		} catch ( \Exception $e ) {
			return $this->error_response( $conversation_id, $e->getMessage(), $user_message_record );
		}

		$total_tokens += $response['tokens_used'];
		$model         = $response['model'];
		$content       = $response['content'] ?? '';
		$pending       = array();

		$max_tool_rounds = 'elementor' === $module ? self::MAX_TOOL_ROUNDS_ELEMENTOR : self::MAX_TOOL_ROUNDS;

		for ( $round = 0; $round < $max_tool_rounds && ! empty( $response['tool_calls'] ); $round++ ) {
			$tool_messages = array();
			$has_pending   = false;

			foreach ( $response['tool_calls'] as $call ) {
				$tool_name = $call['name'] ?? '';
				$tool_args = $this->merge_tool_context( $module, $tool_name, $call['arguments'] ?? array(), $context );

				if ( $this->tool_registry->requires_confirmation( $tool_name ) ) {
					$pending_id = $this->pending_actions->create(
						$user_id,
						$conversation_id,
						$module,
						$tool_name,
						$tool_args,
						$this->tool_registry->get_confirmation_message( $tool_name, $tool_args )
					);

					$pending[] = array(
						'id'          => $pending_id,
						'tool'        => $tool_name,
						'args'        => $tool_args,
						'message'     => $this->tool_registry->get_confirmation_message( $tool_name, $tool_args ),
					);

					$has_pending = true;
					continue;
				}

				$result = $this->tool_registry->execute( $tool_name, $tool_args, $user_id, $module );

				$this->audit_logger->log(
					$user_id,
					'tool_executed',
					$module,
					array( 'tool' => $tool_name, 'result' => $result['message'] )
				);

				$content .= "\n\n**" . $tool_name . ":** " . $result['message'];

				if ( empty( $result['success'] ) ) {
					$content .= ' [' . __( 'FAILED', 'agenpress' ) . ']';
				}

				$tool_messages[] = array(
					'role'         => 'tool',
					'tool_call_id' => $call['id'] ?? '',
					'name'         => $tool_name,
					'content'      => wp_json_encode( $result ),
				);
			}

			if ( $has_pending && empty( $tool_messages ) ) {
				$content .= "\n\n" . __( 'The following actions require your confirmation before proceeding.', 'agenpress' );
				break;
			}

			if ( empty( $tool_messages ) ) {
				break;
			}

			$messages[] = array(
				'role'       => 'assistant',
				'content'    => $content,
				'tool_calls' => $response['tool_calls'],
			);

			foreach ( $tool_messages as $tool_message ) {
				$messages[] = $tool_message;
			}

			try {
				$response = $provider->chat( $messages, $tools );
			} catch ( \Exception $e ) {
				return $this->error_response( $conversation_id, $e->getMessage(), $user_message_record );
			}

			$total_tokens += $response['tokens_used'];
			$content       = $response['content'] ?? $content;

			if ( ! empty( $response['content'] ) ) {
				$content = $response['content'];
			}
		}

		$content = $this->finalize_assistant_content( $content, $module );

		$assistant_message = $this->message_repository->create(
			$conversation_id,
			'assistant',
			$content,
			array(),
			$total_tokens
		);

		$this->audit_logger->log(
			$user_id,
			'chat_response',
			$module,
			array(
				'conversation_id' => $conversation_id,
				'tokens_used'     => $total_tokens,
			)
		);

		$result = array(
			'user_message' => $user_message_record ?? array(),
			'message'      => $assistant_message ?? array(),
			'tokens_used'  => $total_tokens,
			'model'        => $model,
		);

		if ( ! empty( $pending ) ) {
			$result['pending_actions'] = $pending;
		}

		return $result;
	}

	/**
	 * Confirm and execute a pending tool action.
	 *
	 * @param string $pending_id      Pending action ID.
	 * @param int    $user_id         User ID.
	 * @param int    $conversation_id Conversation ID.
	 * @return array{message: array<string, mixed>, tokens_used: int, model: string}
	 */
	public function confirm_action( string $pending_id, int $user_id, int $conversation_id ): array {
		$pending = $this->pending_actions->get_for_user( $pending_id, $user_id );

		if ( ! $pending ) {
			return $this->error_response( $conversation_id, __( 'Pending action not found or expired.', 'agenpress' ) );
		}

		if ( (int) $pending['conversation_id'] !== $conversation_id ) {
			return $this->error_response( $conversation_id, __( 'Invalid conversation for this action.', 'agenpress' ) );
		}

		$result = $this->tool_registry->execute(
			$pending['tool_name'],
			$pending['args'],
			$user_id,
			$pending['module']
		);

		$this->pending_actions->delete( $pending_id );

		$this->audit_logger->log(
			$user_id,
			'tool_confirmed',
			$pending['module'],
			array( 'tool' => $pending['tool_name'], 'result' => $result['message'] )
		);

		$content = sprintf(
			/* translators: 1: tool name, 2: result message */
			__( 'Confirmed action **%1$s**: %2$s', 'agenpress' ),
			$pending['tool_name'],
			$result['message']
		);

		$assistant_message = $this->message_repository->create(
			$conversation_id,
			'assistant',
			$content
		);

		return array(
			'message'     => $assistant_message ?? array(),
			'tokens_used' => 0,
			'model'       => '',
			'tool_result' => $result,
		);
	}

	/**
	 * Create an agentic task from a user request.
	 *
	 * @param int                  $user_id     User ID.
	 * @param string               $module      Module slug.
	 * @param string               $title       Task title.
	 * @param string               $description Task description.
	 * @param array<string, mixed> $options     Planner options (template, params).
	 * @return array<string, mixed>|null
	 */
	public function create_task( int $user_id, string $module, string $title, string $description = '', array $options = array() ): ?array {
		/** @var TaskPlanner $planner */
		$planner = agenpress()->container()->get( 'task_planner' );
		$plan    = $planner->plan( $title, $description, $module, $options );

		return $this->task_queue->create(
			$user_id,
			$module,
			$title,
			$description,
			$plan['steps'],
			$plan['template']
		);
	}

	/**
	 * Prepend system prompt with memory context.
	 *
	 * @param array<int, array{role: string, content: string}> $messages      Messages.
	 * @param string                                           $system_prompt System prompt.
	 * @param string                                           $module        Module slug.
	 * @param string                                           $query         User query for RAG.
	 * @param array<string, mixed>                             $context       Module context.
	 * @return array<int, array{role: string, content: string}>
	 */
	private function prepend_system_prompt( array $messages, string $system_prompt, string $module, string $query, array $context = array() ): array {
		$full_system = trim( $this->context_builder->build( $module, $system_prompt, $query ) );
		$extra       = $this->build_module_context( $module, $context );

		if ( $extra ) {
			$full_system = trim( $full_system . "\n\n" . $extra );
		}

		array_unshift(
			$messages,
			array(
				'role'    => 'system',
				'content' => $full_system ?: 'You are AgenPress, an AI assistant for WordPress.',
			)
		);

		return $messages;
	}

	/**
	 * Build module-specific runtime context for prompts.
	 *
	 * @param string               $module  Module slug.
	 * @param array<string, mixed> $context Context payload.
	 * @return string
	 */
	private function build_module_context( string $module, array $context ): string {
		if ( empty( $context ) ) {
			return '';
		}

		if ( 'sales' === $module ) {
			if ( 'admin' === sanitize_key( (string) ( $context['audience'] ?? '' ) ) ) {
				return $this->build_admin_sales_context( $context );
			}

			return $this->build_sales_context( $context );
		}

		if ( 'elementor' !== $module ) {
			return '';
		}

		$lines = array( 'Elementor Editor Context:' );

		if ( ! empty( $context['post_id'] ) ) {
			$lines[] = sprintf( 'Page ID: %d (always pass this as post_id in tool calls)', (int) $context['post_id'] );
		}

		if ( ! empty( $context['element_id'] ) ) {
			$lines[] = sprintf( 'Selected element ID: %s', sanitize_text_field( (string) $context['element_id'] ) );

			if ( ! empty( $context['el_type'] ) ) {
				$lines[] = sprintf( 'Element type: %s', sanitize_text_field( (string) $context['el_type'] ) );
			}

			if ( ! empty( $context['widget_type'] ) ) {
				$lines[] = sprintf( 'Widget type: %s', sanitize_text_field( (string) $context['widget_type'] ) );
			}
		} else {
			$lines[] = 'No element selected. Use get_page_structure to inspect the page before editing.';
		}

		if ( ! empty( $context['attachments'] ) && is_array( $context['attachments'] ) ) {
			$attachment_block = AttachmentContext::format_context_block( $context['attachments'], 'elementor' );

			if ( '' !== trim( $attachment_block ) ) {
				$lines[] = '';
				$lines[] = trim( $attachment_block );
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Merge editor runtime context into tool arguments when the model omits them.
	 *
	 * @param string               $module    Module slug.
	 * @param string               $tool_name Tool name.
	 * @param array<string, mixed> $args      Tool arguments from the model.
	 * @param array<string, mixed> $context   Runtime context payload.
	 * @return array<string, mixed>
	 */
	private function merge_tool_context( string $module, string $tool_name, array $args, array $context ): array {
		if ( 'elementor' !== $module || empty( $context ) ) {
			return $args;
		}

		if ( empty( $args['post_id'] ) && ! empty( $context['post_id'] ) ) {
			$args['post_id'] = (int) $context['post_id'];
		}

		if ( empty( $args['context_element_id'] ) && ! empty( $context['element_id'] ) ) {
			$args['context_element_id'] = sanitize_text_field( (string) $context['element_id'] );
		}

		if ( 'generate_section_image' === $tool_name && empty( $args['reference_attachment_id'] ) && ! empty( $context['attachments'] ) && is_array( $context['attachments'] ) ) {
			$reference_id = ReferenceImagePreparer::first_image_attachment_id( $context['attachments'] );

			if ( $reference_id > 0 ) {
				$args['reference_attachment_id'] = $reference_id;
			}
		}

		return $args;
	}

	/**
	 * Build wp-admin sales staff context for prompts.
	 *
	 * @param array<string, mixed> $context Context payload.
	 * @return string
	 */
	private function build_admin_sales_context( array $context ): string {
		$user    = wp_get_current_user();
		$lines   = array( 'Shop Staff Context:' );
		$lines[] = __( 'Audience: WordPress admin — the user is a shop employee or manager, NOT a storefront customer.', 'agenpress' );

		if ( $user instanceof \WP_User && $user->exists() ) {
			$lines[] = sprintf(
				/* translators: %s: user display name */
				__( 'Staff member: %s', 'agenpress' ),
				$user->display_name
			);
		}

		if ( ! empty( $context['conversation_id'] ) ) {
			$lines[] = sprintf( 'Conversation ID: %d', (int) $context['conversation_id'] );
		}

		$lines[] = __( 'Respond with internal sales analytics and operational insights. Do not pitch products to this user.', 'agenpress' );

		return implode( "\n", $lines );
	}

	/**
	 * Build sales storefront context for prompts.
	 *
	 * @param array<string, mixed> $context Context payload.
	 * @return string
	 */
	private function build_sales_context( array $context ): string {
		$lines = array( 'Customer Context:' );

		if ( ! empty( $context['conversation_id'] ) ) {
			$lines[] = sprintf( 'Conversation ID: %d', (int) $context['conversation_id'] );
		}

		if ( ! empty( $context['visitor_id'] ) ) {
			$lines[] = sprintf( 'Visitor ID: %s', sanitize_text_field( (string) $context['visitor_id'] ) );
		}

		if ( ! empty( $context['user_id'] ) ) {
			$lines[] = sprintf( 'Logged-in customer user ID: %d', (int) $context['user_id'] );
		} else {
			$lines[] = 'Guest visitor (not logged in).';
		}

		if ( class_exists( 'WooCommerce' ) && WC()->cart ) {
			$lines[] = sprintf( 'Cart items: %d', WC()->cart->get_cart_contents_count() );
		}

		if ( ! empty( $context['customer_history'] ) ) {
			$lines[] = '';
			$lines[] = __( 'Customer conversation history:', 'agenpress' );
			$lines[] = (string) $context['customer_history'];
		}

		return implode( "\n", $lines );
	}

	/**
	 * Normalize assistant content before it is stored.
	 *
	 * @param string $content Message content.
	 * @param string $module  Module slug.
	 * @return string
	 */
	private function finalize_assistant_content( string $content, string $module ): string {
		if ( '' === trim( $content ) ) {
			return $content;
		}

		if ( 'sales' === $module ) {
			return ( new ProductLinkFixer() )->fix( $content );
		}

		if ( in_array( $module, array( 'admin', 'elementor' ), true ) ) {
			return ( new \AgenPress\Content\MarkdownLinkFormatter() )->format( $content );
		}

		return $content;
	}

	/**
	 * Build a chat response after auto-queuing agent tasks.
	 *
	 * @param int                            $conversation_id     Conversation ID.
	 * @param array<string, mixed>|null      $user_message_record User message record.
	 * @param array<int, array<string, mixed>> $tasks             Created tasks.
	 * @param string                         $summary             Assistant summary.
	 * @param int                            $user_id             User ID.
	 * @param string                         $module              Module slug.
	 * @return array{user_message: array<string, mixed>, message: array<string, mixed>, tokens_used: int, model: string, created_tasks: array<int, array<string, mixed>>}
	 */
	private function queued_tasks_response(
		int $conversation_id,
		?array $user_message_record,
		array $tasks,
		string $summary,
		int $user_id,
		string $module
	): array {
		$content = $this->finalize_assistant_content( $summary, $module );

		$assistant_message = $this->message_repository->create(
			$conversation_id,
			'assistant',
			$content
		);

		$this->audit_logger->log(
			$user_id,
			'chat_tasks_queued',
			$module,
			array(
				'conversation_id' => $conversation_id,
				'task_ids'        => array_map(
					static fn( array $task ): int => (int) ( $task['id'] ?? 0 ),
					$tasks
				),
			)
		);

		return array(
			'user_message'  => $user_message_record ?? array(),
			'message'       => $assistant_message ?? array(),
			'tokens_used'   => 0,
			'model'         => '',
			'created_tasks' => array_map(
				static function ( array $task ): array {
					return array(
						'id'          => (int) ( $task['id'] ?? 0 ),
						'title'       => (string) ( $task['title'] ?? '' ),
						'template'    => (string) ( $task['template'] ?? 'custom' ),
						'status'      => (string) ( $task['status'] ?? 'pending' ),
						'total_steps' => (int) ( $task['total_steps'] ?? 0 ),
					);
				},
				$tasks
			),
		);
	}

	/**
	 * Build an error response.
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $error_message   Error message.
	 * @return array{message: array<string, mixed>, tokens_used: int, model: string}
	 */
	private function error_response( int $conversation_id, string $error_message, ?array $user_message = null ): array {
		$assistant_message = $this->message_repository->create(
			$conversation_id,
			'assistant',
			sprintf(
				/* translators: %s: error message */
				__( 'Error: %s', 'agenpress' ),
				$error_message
			)
		);

		return array(
			'user_message' => $user_message ?? array(),
			'message'      => $assistant_message ?? array(),
			'tokens_used'  => 0,
			'model'        => '',
		);
	}
}
