<?php
/**
 * Elementor document read/write helpers.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Class ElementorDocumentService
 */
class ElementorDocumentService {

	/**
	 * Check if Elementor is available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return class_exists( '\Elementor\Plugin' );
	}

	/**
	 * Check if a post uses Elementor.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function is_elementor_page( int $post_id ): bool {
		if ( ! $this->is_available() ) {
			return false;
		}

		return (bool) get_post_meta( $post_id, '_elementor_edit_mode', true );
	}

	/**
	 * Get Elementor document instance.
	 *
	 * @param int $post_id Post ID.
	 * @return \Elementor\Core\Base\Document|null
	 */
	public function get_document( int $post_id ): ?\Elementor\Core\Base\Document {
		if ( ! $this->is_available() ) {
			return null;
		}

		$document = \Elementor\Plugin::$instance->documents->get( $post_id, false );

		return $document && $document->is_built_with_elementor() ? $document : null;
	}

	/**
	 * List Elementor-built pages.
	 *
	 * @param int $limit Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_pages( int $limit = 20 ): array {
		$query = new \WP_Query(
			array(
				'post_type'      => array( 'page', 'post', 'elementor_library' ),
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => $limit,
				'meta_key'       => '_elementor_edit_mode',
				'meta_value'     => 'builder',
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		$pages = array();

		foreach ( $query->posts as $post ) {
			$pages[] = array(
				'id'     => $post->ID,
				'title'  => $post->post_title,
				'type'   => $post->post_type,
				'status' => $post->post_status,
				'url'    => get_permalink( $post ),
			);
		}

		return $pages;
	}

	/**
	 * Get page element tree summary.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $include_settings Include element settings.
	 * @return array<string, mixed>|null
	 */
	public function get_structure( int $post_id, bool $include_settings = false ): ?array {
		$document = $this->get_document( $post_id );

		if ( ! $document ) {
			return null;
		}

		$elements = $document->get_elements_data();

		return array(
			'post_id'  => $post_id,
			'title'    => get_the_title( $post_id ),
			'elements' => array_map(
				fn( array $element ) => $this->summarize_element( $element, $include_settings ),
				is_array( $elements ) ? $elements : array()
			),
		);
	}

	/**
	 * Get a single element from a page.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $element_id Element ID.
	 * @return array<string, mixed>|null
	 */
	public function get_element( int $post_id, string $element_id ): ?array {
		$document = $this->get_document( $post_id );

		if ( ! $document ) {
			return null;
		}

		$elements = $document->get_elements_data();
		$found    = $this->find_element( $elements, $element_id );

		return $found ? $this->summarize_element( $found, true ) : null;
	}

	/**
	 * Create a new section on a page.
	 *
	 * @param int                  $post_id          Post ID.
	 * @param array<string, mixed> $settings         Section settings.
	 * @param string|null          $after_element_id Insert after this element ID.
	 * @return array{success: bool, element_id: string|null, message: string}
	 */
	public function create_section( int $post_id, array $settings = array(), ?string $after_element_id = null ): array {
		$document = $this->get_document( $post_id );

		if ( ! $document ) {
			return array(
				'success'    => false,
				'element_id' => null,
				'message'    => __( 'Elementor document not found.', 'agenpress' ),
			);
		}

		$elements    = $document->get_elements_data();
		$new_section = $this->build_section( $settings );

		if ( $after_element_id ) {
			$inserted = $this->insert_after( $elements, $after_element_id, $new_section );
			if ( ! $inserted ) {
				$elements[] = $new_section;
			}
		} else {
			$elements[] = $new_section;
		}

		$this->save_elements( $document, $elements );

		return array(
			'success'    => true,
			'element_id' => $new_section['id'],
			'message'    => __( 'Section created.', 'agenpress' ),
		);
	}

	/**
	 * Update element settings.
	 *
	 * @param int                  $post_id    Post ID.
	 * @param string               $element_id Element ID.
	 * @param array<string, mixed> $settings   Settings to merge.
	 * @return array{success: bool, message: string}
	 */
	public function update_element_settings( int $post_id, string $element_id, array $settings ): array {
		$document = $this->get_document( $post_id );

		if ( ! $document ) {
			return array(
				'success' => false,
				'message' => __( 'Elementor document not found.', 'agenpress' ),
			);
		}

		$elements = $document->get_elements_data();
		$updated  = false;

		$this->walk_elements(
			$elements,
			function ( array &$element ) use ( $element_id, $settings, &$updated ): bool {
				if ( ( $element['id'] ?? '' ) !== $element_id ) {
					return false;
				}

				$element['settings'] = array_merge( $element['settings'] ?? array(), $settings );
				$updated             = true;

				return true;
			}
		);

		if ( ! $updated ) {
			return array(
				'success' => false,
				'message' => __( 'Element not found.', 'agenpress' ),
			);
		}

		$this->save_elements( $document, $elements );

		return array(
			'success' => true,
			'message' => __( 'Element settings updated.', 'agenpress' ),
		);
	}

	/**
	 * Duplicate an element.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $element_id Element ID.
	 * @return array{success: bool, element_id: string|null, message: string}
	 */
	public function duplicate_element( int $post_id, string $element_id ): array {
		$document = $this->get_document( $post_id );

		if ( ! $document ) {
			return array(
				'success'    => false,
				'element_id' => null,
				'message'    => __( 'Elementor document not found.', 'agenpress' ),
			);
		}

		$elements = $document->get_elements_data();
		$source   = $this->find_element( $elements, $element_id );

		if ( ! $source ) {
			return array(
				'success'    => false,
				'element_id' => null,
				'message'    => __( 'Element not found.', 'agenpress' ),
			);
		}

		$clone = $this->clone_element( $source );

		if ( ! $this->insert_after( $elements, $element_id, $clone ) ) {
			$elements[] = $clone;
		}

		$this->save_elements( $document, $elements );

		return array(
			'success'    => true,
			'element_id' => $clone['id'],
			'message'    => __( 'Element duplicated.', 'agenpress' ),
		);
	}

	/**
	 * Delete an element from a page.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $element_id Element ID.
	 * @return array{success: bool, message: string}
	 */
	public function delete_element( int $post_id, string $element_id ): array {
		$document = $this->get_document( $post_id );

		if ( ! $document ) {
			return array(
				'success' => false,
				'message' => __( 'Elementor document not found.', 'agenpress' ),
			);
		}

		$elements = $document->get_elements_data();
		$deleted  = $this->remove_element( $elements, $element_id );

		if ( ! $deleted ) {
			return array(
				'success' => false,
				'message' => __( 'Element not found.', 'agenpress' ),
			);
		}

		$this->save_elements( $document, $elements );

		return array(
			'success' => true,
			'message' => __( 'Element deleted.', 'agenpress' ),
		);
	}

	/**
	 * Save elements to document and clear cache.
	 *
	 * @param \Elementor\Core\Base\Document $document Document.
	 * @param array<int, array<string, mixed>> $elements Elements tree.
	 * @return void
	 */
	private function save_elements( \Elementor\Core\Base\Document $document, array $elements ): void {
		$document->save(
			array(
				'elements' => $elements,
			)
		);

		if ( class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}

	/**
	 * Build a default section with one column.
	 *
	 * @param array<string, mixed> $settings Section settings.
	 * @return array<string, mixed>
	 */
	private function build_section( array $settings ): array {
		return array(
			'id'       => $this->generate_id(),
			'elType'   => 'section',
			'isInner'  => false,
			'settings' => $settings,
			'elements' => array(
				array(
					'id'       => $this->generate_id(),
					'elType'   => 'column',
					'settings' => array( '_column_size' => 100 ),
					'elements' => array(),
					'isInner'  => false,
				),
			),
		);
	}

	/**
	 * Generate a random Elementor element ID.
	 *
	 * @return string
	 */
	private function generate_id(): string {
		if ( class_exists( '\Elementor\Utils' ) ) {
			return \Elementor\Utils::generate_random_string();
		}

		return wp_generate_password( 7, false, false );
	}

	/**
	 * Deep-clone an element with new IDs.
	 *
	 * @param array<string, mixed> $element Element data.
	 * @return array<string, mixed>
	 */
	private function clone_element( array $element ): array {
		$element['id'] = $this->generate_id();

		if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
			$element['elements'] = array_map(
				array( $this, 'clone_element' ),
				$element['elements']
			);
		}

		return $element;
	}

	/**
	 * Find element by ID in tree.
	 *
	 * @param array<int, array<string, mixed>> $elements   Elements.
	 * @param string                           $element_id Element ID.
	 * @return array<string, mixed>|null
	 */
	private function find_element( array $elements, string $element_id ): ?array {
		$found = null;

		$this->walk_elements(
			$elements,
			function ( array $element ) use ( $element_id, &$found ): bool {
				if ( ( $element['id'] ?? '' ) === $element_id ) {
					$found = $element;
					return true;
				}

				return false;
			}
		);

		return $found;
	}

	/**
	 * Insert element after a target ID.
	 *
	 * @param array<int, array<string, mixed>> $elements       Elements (by ref).
	 * @param string                           $target_id      Target element ID.
	 * @param array<string, mixed>             $new_element    New element.
	 * @return bool
	 */
	private function insert_after( array &$elements, string $target_id, array $new_element ): bool {
		foreach ( $elements as $index => &$element ) {
			if ( ( $element['id'] ?? '' ) === $target_id ) {
				array_splice( $elements, $index + 1, 0, array( $new_element ) );
				return true;
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				if ( $this->insert_after( $element['elements'], $target_id, $new_element ) ) {
					return true;
				}
			}
		}
		unset( $element );

		return false;
	}

	/**
	 * Remove element by ID from tree.
	 *
	 * @param array<int, array<string, mixed>> $elements   Elements (by ref).
	 * @param string                           $element_id Element ID.
	 * @return bool
	 */
	private function remove_element( array &$elements, string $element_id ): bool {
		foreach ( $elements as $index => &$element ) {
			if ( ( $element['id'] ?? '' ) === $element_id ) {
				array_splice( $elements, $index, 1 );
				return true;
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				if ( $this->remove_element( $element['elements'], $element_id ) ) {
					return true;
				}
			}
		}
		unset( $element );

		return false;
	}

	/**
	 * Walk element tree recursively.
	 *
	 * @param array<int, array<string, mixed>> $elements Elements (by ref).
	 * @param callable                         $callback   Callback receives element by ref, returns true to stop.
	 * @return void
	 */
	private function walk_elements( array &$elements, callable $callback ): void {
		foreach ( $elements as &$element ) {
			if ( $callback( $element ) ) {
				return;
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$this->walk_elements( $element['elements'], $callback );
			}
		}
	}

	/**
	 * Summarize element for API output.
	 *
	 * @param array<string, mixed> $element          Element data.
	 * @param bool                 $include_settings Include settings.
	 * @return array<string, mixed>
	 */
	private function summarize_element( array $element, bool $include_settings ): array {
		$summary = array(
			'id'         => $element['id'] ?? '',
			'elType'     => $element['elType'] ?? '',
			'widgetType' => $element['widgetType'] ?? null,
		);

		if ( $include_settings && ! empty( $element['settings'] ) ) {
			$summary['settings'] = $element['settings'];
		}

		if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
			$summary['children'] = array_map(
				fn( array $child ) => $this->summarize_element( $child, $include_settings ),
				$element['elements']
			);
		}

		return $summary;
	}
}
