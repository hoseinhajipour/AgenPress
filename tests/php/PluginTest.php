<?php
/**
 * Basic plugin structure test.
 *
 * @package AgenPress
 */

namespace AgenPress\Tests;

use AgenPress\Agents\TaskState;
use AgenPress\Memory\MemoryStore;
use PHPUnit\Framework\TestCase;

/**
 * Class PluginTest
 */
class PluginTest extends TestCase {

	/**
	 * Test task state transitions.
	 */
	public function test_task_state_transitions(): void {
		$this->assertTrue( TaskState::can_transition( TaskState::PENDING, TaskState::RUNNING ) );
		$this->assertTrue( TaskState::can_transition( TaskState::RUNNING, TaskState::PAUSED ) );
		$this->assertFalse( TaskState::can_transition( TaskState::COMPLETED, TaskState::RUNNING ) );
	}

	/**
	 * Test memory categories are defined.
	 */
	public function test_memory_categories(): void {
		$this->assertContains( 'brand', MemoryStore::CATEGORIES );
		$this->assertContains( 'seo', MemoryStore::CATEGORIES );
	}
}
