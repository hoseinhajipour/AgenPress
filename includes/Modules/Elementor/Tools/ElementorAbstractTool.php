<?php
/**
 * Base class for Elementor module tools.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Elementor\Tools;

use AgenPress\Agents\AbstractTool;
use AgenPress\Modules\Elementor\ElementorDocumentService;

defined( 'ABSPATH' ) || exit;

/**
 * Class ElementorAbstractTool
 */
abstract class ElementorAbstractTool extends AbstractTool {

	/**
	 * Document service.
	 *
	 * @var ElementorDocumentService
	 */
	protected ElementorDocumentService $documents;

	/**
	 * Constructor.
	 *
	 * @param ElementorDocumentService $documents Document service.
	 */
	public function __construct( ElementorDocumentService $documents ) {
		$this->documents = $documents;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_module(): string {
		return 'elementor';
	}

	/**
	 * Check edit permission for a post.
	 *
	 * @param int $user_id User ID.
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	protected function can_edit_page( int $user_id, int $post_id ): bool {
		return user_can( $user_id, 'edit_post', $post_id );
	}
}
