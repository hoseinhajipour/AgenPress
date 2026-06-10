<?php
/**
 * Escalate chat to human support.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Sales\Tools;

use AgenPress\Chat\ConversationRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class EscalateToHumanTool
 */
class EscalateToHumanTool extends SalesAbstractTool {

	/**
	 * Conversation repository.
	 *
	 * @var ConversationRepository
	 */
	private ConversationRepository $conversations;

	/**
	 * Constructor.
	 *
	 * @param ConversationRepository $conversations Conversation repository.
	 */
	public function __construct( ConversationRepository $conversations ) {
		$this->conversations = $conversations;
	}

	public function get_name(): string {
		return 'escalate_to_human';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'escalate_to_human',
			'description' => 'Escalate this conversation to a human support agent when the customer needs personal help',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'conversation_id' => array( 'type' => 'integer', 'description' => 'Current conversation ID' ),
					'reason'          => array( 'type' => 'string', 'description' => 'Brief reason for escalation' ),
				),
				'required'   => array( 'conversation_id' ),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		$id = (int) ( $args['conversation_id'] ?? 0 );

		if ( $id <= 0 ) {
			return $this->fail( __( 'Conversation ID is required.', 'agenpress' ) );
		}

		$conversation = $this->conversations->find( $id );

		if ( ! $conversation ) {
			return $this->fail( __( 'Conversation not found.', 'agenpress' ) );
		}

		$this->conversations->update_status(
			$id,
			'escalated',
			array(
				'escalation_reason' => sanitize_text_field( $args['reason'] ?? '' ),
				'escalated_at'      => current_time( 'mysql', true ),
			)
		);

		return $this->success(
			array( 'conversation_id' => $id, 'status' => 'escalated' ),
			__( 'A team member will follow up shortly.', 'agenpress' )
		);
	}
}
