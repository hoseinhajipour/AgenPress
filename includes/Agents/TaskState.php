<?php
/**
 * Task state constants.
 *
 * @package AgenPress
 */

namespace AgenPress\Agents;

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskState
 */
class TaskState {

	public const PENDING   = 'pending';
	public const RUNNING   = 'running';
	public const PAUSED    = 'paused';
	public const COMPLETED = 'completed';
	public const FAILED    = 'failed';
	public const CANCELLED = 'cancelled';

	/**
	 * Valid status transitions.
	 *
	 * @return array<string, array<string>>
	 */
	public static function transitions(): array {
		return array(
			self::PENDING   => array( self::RUNNING, self::CANCELLED ),
			self::RUNNING   => array( self::PAUSED, self::COMPLETED, self::FAILED, self::CANCELLED ),
			self::PAUSED    => array( self::RUNNING, self::CANCELLED ),
			self::COMPLETED => array(),
			self::FAILED    => array( self::PENDING, self::CANCELLED ),
			self::CANCELLED => array(),
		);
	}

	/**
	 * Check if a status transition is valid.
	 *
	 * @param string $from Current status.
	 * @param string $to   Target status.
	 * @return bool
	 */
	public static function can_transition( string $from, string $to ): bool {
		$transitions = self::transitions();

		return in_array( $to, $transitions[ $from ] ?? array(), true );
	}
}
