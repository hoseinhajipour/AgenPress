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
	 * Enable Elementor builder mode on a page and ensure an empty document exists.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function ensure_builder_page( int $post_id ): bool {
		if ( ! $this->is_available() || $post_id <= 0 ) {
			return false;
		}

		$post = get_post( $post_id );

		if ( ! $post || 'page' !== $post->post_type ) {
			return false;
		}

		$plugin   = \Elementor\Plugin::$instance;
		$document = $plugin->documents->get( $post_id, false );

		if ( ! $document && method_exists( $plugin->documents, 'create' ) ) {
			$document = $plugin->documents->create( $post_id );
		}

		if ( ! $document ) {
			return false;
		}

		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $post_id, '_elementor_template_type', 'wp-page' );

		if ( ! $document->is_built_with_elementor() ) {
			$document->save(
				array(
					'elements' => array(),
				)
			);
		}

		return true;
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
	 * Get raw Elementor elements data for editor sync.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>>|null
	 */
	public function get_raw_elements( int $post_id ): ?array {
		$document = $this->get_document( $post_id );

		if ( ! $document ) {
			return null;
		}

		$elements = $document->get_elements_data();

		return is_array( $elements ) ? $elements : array();
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
			'layout'   => $this->uses_flexbox_container_layout( $post_id ) ? 'container' : 'section',
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
		if ( $this->uses_flexbox_container_layout( $post_id ) ) {
			return $this->create_container( $post_id, $settings, $after_element_id );
		}

		$document = $this->get_document( $post_id );

		if ( ! $document ) {
			return array(
				'success'    => false,
				'element_id' => null,
				'column_id'  => null,
				'message'    => __( 'Elementor document not found.', 'agenpress' ),
			);
		}

		$elements    = $document->get_elements_data();
		$new_section = $this->build_section( $settings );
		$column_id   = (string) ( $new_section['elements'][0]['id'] ?? '' );

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
			'column_id'  => $column_id,
			'layout'     => 'section',
			'message'    => sprintf(
				/* translators: 1: section element ID, 2: column element ID */
				__( 'Section created (id: %1$s). Add widgets using column_id: %2$s.', 'agenpress' ),
				$new_section['id'],
				$column_id
			),
		);
	}

	/**
	 * Create a flexbox container on a page.
	 *
	 * @param int                  $post_id          Post ID.
	 * @param array<string, mixed> $settings         Container settings.
	 * @param string|null          $after_element_id Insert after this element ID.
	 * @return array{success: bool, element_id: string|null, column_id: string|null, layout?: string, message: string}
	 */
	public function create_container( int $post_id, array $settings = array(), ?string $after_element_id = null ): array {
		$document = $this->get_document( $post_id );

		if ( ! $document ) {
			return array(
				'success'    => false,
				'element_id' => null,
				'column_id'  => null,
				'message'    => __( 'Elementor document not found.', 'agenpress' ),
			);
		}

		$elements       = $document->get_elements_data();
		$new_container  = $this->build_container( $settings );
		$container_id   = (string) $new_container['id'];

		if ( $after_element_id ) {
			$inserted = $this->insert_after( $elements, $after_element_id, $new_container );
			if ( ! $inserted ) {
				$elements[] = $new_container;
			}
		} else {
			$elements[] = $new_container;
		}

		$this->save_elements( $document, $elements );

		return array(
			'success'    => true,
			'element_id' => $container_id,
			'column_id'  => $container_id,
			'layout'     => 'container',
			'message'    => sprintf(
				/* translators: %s: container element ID */
				__( 'Container created (id: %s). Add widgets using this id as column_id.', 'agenpress' ),
				$container_id
			),
		);
	}

	/**
	 * Whether the page uses Elementor flexbox containers at the root level.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function uses_flexbox_container_layout( int $post_id ): bool {
		$document = $this->get_document( $post_id );

		if ( ! $document ) {
			return false;
		}

		$elements = $document->get_elements_data();

		if ( ! is_array( $elements ) ) {
			return false;
		}

		foreach ( $elements as $element ) {
			if ( 'container' === ( $element['elType'] ?? '' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve a section/column/container ID to a widget parent ID.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $element_id Element ID from structure or create_section.
	 * @return string|null
	 */
	public function resolve_widget_parent_id( int $post_id, string $element_id ): ?string {
		$document = $this->get_document( $post_id );

		if ( ! $document || '' === $element_id ) {
			return null;
		}

		$elements = $document->get_elements_data();
		$element  = $this->find_element( is_array( $elements ) ? $elements : array(), $element_id );

		if ( ! $element ) {
			return null;
		}

		$el_type = (string) ( $element['elType'] ?? '' );

		if ( $this->is_widget_parent_type( $el_type ) ) {
			return $element_id;
		}

		if ( 'section' === $el_type ) {
			foreach ( $element['elements'] ?? array() as $child ) {
				$child_type = (string) ( $child['elType'] ?? '' );

				if ( $this->is_widget_parent_type( $child_type ) && ! empty( $child['id'] ) ) {
					return (string) $child['id'];
				}
			}
		}

		return null;
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
	 * Search elements on a page by widget type or text query.
	 *
	 * @param int         $post_id     Post ID.
	 * @param string      $query       Search query.
	 * @param string|null $widget_type Widget type slug.
	 * @param int         $limit       Max results.
	 * @return array<int, array<string, mixed>>|null
	 */
	public function search_elements( int $post_id, string $query = '', ?string $widget_type = null, int $limit = 20 ): ?array {
		$document = $this->get_document( $post_id );

		if ( ! $document ) {
			return null;
		}

		$elements = $document->get_elements_data();
		$matches  = array();
		$query    = strtolower( trim( $query ) );
		$type     = $widget_type ? sanitize_key( $widget_type ) : '';

		$this->collect_search_matches( is_array( $elements ) ? $elements : array(), $matches, $query, $type, $limit );

		return $matches;
	}

	/**
	 * Create a widget inside a column.
	 *
	 * @param int                  $post_id          Post ID.
	 * @param string               $column_id        Parent column element ID.
	 * @param string               $widget_type      Widget type slug.
	 * @param array<string, mixed> $settings         Widget settings.
	 * @param string|null          $after_element_id Insert after sibling element ID.
	 * @return array{success: bool, element_id: string|null, message: string}
	 */
	public function create_widget(
		int $post_id,
		string $column_id,
		string $widget_type,
		array $settings = array(),
		?string $after_element_id = null
	): array {
		$parent_id = $this->resolve_widget_parent_id( $post_id, $column_id ) ?? $column_id;

		$document = $this->get_document( $post_id );

		if ( ! $document ) {
			return array(
				'success'    => false,
				'element_id' => null,
				'message'    => __( 'Elementor document not found.', 'agenpress' ),
			);
		}

		$elements = $document->get_elements_data();
		$parent   = $this->find_element( $elements, $parent_id );

		if ( ! $parent || ! $this->is_widget_parent_type( (string) ( $parent['elType'] ?? '' ) ) ) {
			return array(
				'success'    => false,
				'element_id' => null,
				'message'    => sprintf(
					/* translators: %s: element ID */
					__( 'Could not find a column or container for widgets (id: %s). Use column_id from create_section or get_page_structure.', 'agenpress' ),
					$column_id
				),
			);
		}

		$column_id = $parent_id;

		$widget_type = sanitize_key( $widget_type );

		if ( '' === $widget_type ) {
			return array(
				'success'    => false,
				'element_id' => null,
				'message'    => __( 'Widget type is required.', 'agenpress' ),
			);
		}

		$new_widget = array(
			'id'         => $this->generate_id(),
			'elType'     => 'widget',
			'widgetType' => $widget_type,
			'settings'   => $settings,
			'elements'   => array(),
			'isInner'    => false,
		);

		$inserted = false;

		$this->walk_elements(
			$elements,
			function ( array &$element ) use ( $column_id, $new_widget, $after_element_id, &$inserted ): bool {
				if ( ( $element['id'] ?? '' ) !== $column_id ) {
					return false;
				}

				if ( ! isset( $element['elements'] ) || ! is_array( $element['elements'] ) ) {
					$element['elements'] = array();
				}

				if ( $after_element_id ) {
					$inserted = $this->insert_after( $element['elements'], $after_element_id, $new_widget );
				}

				if ( ! $inserted ) {
					$element['elements'][] = $new_widget;
					$inserted              = true;
				}

				return true;
			}
		);

		if ( ! $inserted ) {
			return array(
				'success'    => false,
				'element_id' => null,
				'message'    => __( 'Failed to insert widget.', 'agenpress' ),
			);
		}

		$this->save_elements( $document, $elements );

		return array(
			'success'    => true,
			'element_id' => $new_widget['id'],
			'message'    => __( 'Widget created.', 'agenpress' ),
		);
	}

	/**
	 * Add an image widget using a media library attachment.
	 *
	 * @param int         $post_id            Post ID.
	 * @param int         $attachment_id      Attachment ID.
	 * @param string|null $column_id          Target column ID.
	 * @param string|null $context_element_id Selected or contextual element ID.
	 * @param string|null $after_element_id   Insert after sibling element ID.
	 * @return array{success: bool, element_id: string|null, message: string, data?: array<string, mixed>}
	 */
	public function add_attached_image_widget(
		int $post_id,
		int $attachment_id,
		?string $column_id = null,
		?string $context_element_id = null,
		?string $after_element_id = null
	): array {
		if ( ! get_post( $attachment_id ) || ! wp_attachment_is_image( $attachment_id ) ) {
			return array(
				'success'    => false,
				'element_id' => null,
				'message'    => __( 'Invalid image attachment.', 'agenpress' ),
			);
		}

		$target_column = $this->resolve_target_column( $post_id, $column_id, $context_element_id );

		if ( ! $target_column ) {
			return array(
				'success'    => false,
				'element_id' => null,
				'message'    => __( 'No column or container found to insert the image widget.', 'agenpress' ),
			);
		}

		$image_url = (string) wp_get_attachment_url( $attachment_id );
		$alt_text  = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

		$settings = array(
			'image'       => array(
				'id'  => $attachment_id,
				'url' => $image_url,
			),
			'image_size'  => 'full',
			'align'       => 'center',
		);

		if ( '' !== $alt_text ) {
			$settings['caption'] = $alt_text;
		}

		$result = $this->create_widget(
			$post_id,
			$target_column,
			'image',
			$settings,
			$after_element_id ? sanitize_text_field( $after_element_id ) : null
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		return array(
			'success'    => true,
			'element_id' => $result['element_id'],
			'message'    => __( 'Image widget added to the page.', 'agenpress' ),
			'data'       => array(
				'post_id'       => $post_id,
				'column_id'     => $target_column,
				'attachment_id' => $attachment_id,
				'url'           => $image_url,
			),
		);
	}

	/**
	 * Resolve the column that should receive a new widget.
	 *
	 * @param int         $post_id            Post ID.
	 * @param string|null $column_id          Explicit column ID.
	 * @param string|null $context_element_id Context element ID.
	 * @return string|null
	 */
	public function resolve_target_column( int $post_id, ?string $column_id = null, ?string $context_element_id = null ): ?string {
		$document = $this->get_document( $post_id );

		if ( ! $document ) {
			return null;
		}

		$elements = $document->get_elements_data();

		if ( ! is_array( $elements ) ) {
			return null;
		}

		if ( $column_id ) {
			$column = $this->find_element( $elements, sanitize_text_field( $column_id ) );

			if ( $column && $this->is_widget_parent_type( (string) ( $column['elType'] ?? '' ) ) ) {
				return (string) $column['id'];
			}
		}

		if ( $context_element_id ) {
			$resolved = $this->resolve_column_from_element( $elements, sanitize_text_field( $context_element_id ) );

			if ( $resolved ) {
				return $resolved;
			}
		}

		return $this->find_first_column_id( $elements );
	}

	/**
	 * Apply a media library attachment to an image widget or section background.
	 *
	 * @param int    $post_id       Post ID.
	 * @param string $element_id    Element ID.
	 * @param int    $attachment_id Attachment ID.
	 * @return array{success: bool, message: string}
	 */
	public function apply_attachment_to_element( int $post_id, string $element_id, int $attachment_id ): array {
		$attachment = get_post( $attachment_id );

		if ( ! $attachment || ! wp_attachment_is_image( $attachment_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid image attachment.', 'agenpress' ),
			);
		}

		$image_url = (string) wp_get_attachment_url( $attachment_id );
		$element   = $this->get_element( $post_id, $element_id );

		if ( ! $element ) {
			return array(
				'success' => false,
				'message' => __( 'Element not found.', 'agenpress' ),
			);
		}

		$settings = $this->media_settings_for_element( $element, $attachment_id, $image_url );

		return $this->update_element_settings( $post_id, $element_id, $settings );
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
		$post_id = (int) $document->get_main_id();

		$document->save(
			array(
				'elements' => $elements,
			)
		);

		delete_post_meta( $post_id, '_elementor_css' );

		if ( class_exists( '\Elementor\Plugin' ) ) {
			$plugin = \Elementor\Plugin::$instance;
			$plugin->files_manager->clear_cache();

			if ( method_exists( $plugin->documents, 'remove' ) ) {
				$plugin->documents->remove( $post_id );
			}
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
	 * Build a flexbox container element.
	 *
	 * @param array<string, mixed> $settings Container settings.
	 * @return array<string, mixed>
	 */
	private function build_container( array $settings ): array {
		$defaults = array(
			'content_width'  => 'boxed',
			'flex_direction' => 'column',
			'flex_gap'       => array(
				'unit'   => 'px',
				'size'   => 20,
				'column' => '20',
				'row'    => '20',
			),
			'padding'        => array(
				'unit'       => 'px',
				'top'        => '40',
				'right'      => '20',
				'bottom'     => '40',
				'left'       => '20',
				'isLinked'   => false,
			),
		);

		return array(
			'id'       => $this->generate_id(),
			'elType'   => 'container',
			'isInner'  => false,
			'settings' => array_merge( $defaults, $settings ),
			'elements' => array(),
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
	 * Find the first column ID in the document tree.
	 *
	 * @param array<int, array<string, mixed>> $elements Elements.
	 * @return string|null
	 */
	private function find_first_column_id( array $elements ): ?string {
		foreach ( $elements as $element ) {
			if ( $this->is_widget_parent_type( (string) ( $element['elType'] ?? '' ) ) && ! empty( $element['id'] ) ) {
				return (string) $element['id'];
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$found = $this->find_first_column_id( $element['elements'] );

				if ( $found ) {
					return $found;
				}
			}
		}

		return null;
	}

	/**
	 * Whether an element type can contain widgets directly.
	 *
	 * @param string $el_type Element type.
	 * @return bool
	 */
	private function is_widget_parent_type( string $el_type ): bool {
		return in_array( $el_type, array( 'column', 'container' ), true );
	}

	/**
	 * Resolve a column ID from a selected section, column, or widget.
	 *
	 * @param array<int, array<string, mixed>> $elements   Elements.
	 * @param string                           $element_id Element ID.
	 * @return string|null
	 */
	private function resolve_column_from_element( array $elements, string $element_id ): ?string {
		return $this->resolve_column_walker( $elements, $element_id );
	}

	/**
	 * Walk the tree to resolve a column from a contextual element.
	 *
	 * @param array<int, array<string, mixed>> $elements         Elements.
	 * @param string                           $target_element_id Target element ID.
	 * @param string|null                      $parent_column_id  Parent column ID.
	 * @return string|null
	 */
	private function resolve_column_walker( array $elements, string $target_element_id, ?string $parent_column_id = null ): ?string {
		foreach ( $elements as $element ) {
			$id      = (string) ( $element['id'] ?? '' );
			$el_type = (string) ( $element['elType'] ?? '' );
			$column  = $this->is_widget_parent_type( $el_type ) ? $id : $parent_column_id;

			if ( $id === $target_element_id ) {
				if ( $this->is_widget_parent_type( $el_type ) ) {
					return $id;
				}

				if ( 'section' === $el_type ) {
					foreach ( $element['elements'] ?? array() as $child ) {
						$child_type = (string) ( $child['elType'] ?? '' );

						if ( $this->is_widget_parent_type( $child_type ) && ! empty( $child['id'] ) ) {
							return (string) $child['id'];
						}
					}
				}

				return $parent_column_id;
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$found = $this->resolve_column_walker( $element['elements'], $target_element_id, $column );

				if ( $found ) {
					return $found;
				}
			}
		}

		return null;
	}

	/**
	 * Collect search matches from the element tree.
	 *
	 * @param array<int, array<string, mixed>> $elements    Elements.
	 * @param array<int, array<string, mixed>> $matches     Matches (by ref).
	 * @param string                             $query       Query.
	 * @param string                             $widget_type Widget type.
	 * @param int                                $limit       Limit.
	 * @param array<int, string>                 $path        Path labels.
	 * @return void
	 */
	private function collect_search_matches(
		array $elements,
		array &$matches,
		string $query,
		string $widget_type,
		int $limit,
		array $path = array()
	): void {
		if ( count( $matches ) >= $limit ) {
			return;
		}

		foreach ( $elements as $element ) {
			if ( count( $matches ) >= $limit ) {
				return;
			}

			$el_type     = (string) ( $element['elType'] ?? '' );
			$widget      = (string) ( $element['widgetType'] ?? '' );
			$element_id  = (string) ( $element['id'] ?? '' );
			$label       = 'widget' === $el_type && $widget ? $widget : $el_type;
			$current_path = array_merge( $path, array( $label ) );
			$haystack    = strtolower(
				$element_id . ' ' . $label . ' ' . $this->settings_search_blob( $element['settings'] ?? array() )
			);

			$type_match  = '' === $widget_type || $widget === $widget_type;
			$query_match = '' === $query || str_contains( $haystack, $query );

			if ( $type_match && $query_match && in_array( $el_type, array( 'widget', 'section', 'column' ), true ) ) {
				$matches[] = array(
					'element_id'  => $element_id,
					'elType'      => $el_type,
					'widgetType'  => $widget ?: null,
					'path'        => implode( ' > ', $current_path ),
					'preview'     => $this->settings_preview( $element ),
				);
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$this->collect_search_matches(
					$element['elements'],
					$matches,
					$query,
					$widget_type,
					$limit,
					$current_path
				);
			}
		}
	}

	/**
	 * Flatten settings into searchable text.
	 *
	 * @param mixed $settings Settings.
	 * @return string
	 */
	private function settings_search_blob( mixed $settings ): string {
		if ( ! is_array( $settings ) ) {
			return is_scalar( $settings ) ? (string) $settings : '';
		}

		$parts = array();

		foreach ( $settings as $key => $value ) {
			if ( is_scalar( $value ) ) {
				$parts[] = (string) $key . ' ' . (string) $value;
			} elseif ( is_array( $value ) ) {
				$parts[] = (string) $key . ' ' . $this->settings_search_blob( $value );
			}
		}

		return implode( ' ', $parts );
	}

	/**
	 * Build a short preview string from element settings.
	 *
	 * @param array<string, mixed> $element Element.
	 * @return string
	 */
	private function settings_preview( array $element ): string {
		$settings = $element['settings'] ?? array();

		if ( ! is_array( $settings ) ) {
			return '';
		}

		foreach ( array( 'title', 'editor', 'text', 'caption', 'heading_title' ) as $key ) {
			if ( ! empty( $settings[ $key ] ) && is_string( $settings[ $key ] ) ) {
				return wp_trim_words( wp_strip_all_tags( $settings[ $key ] ), 12 );
			}
		}

		return '';
	}

	/**
	 * Build media settings for an element type.
	 *
	 * @param array<string, mixed> $element       Element summary.
	 * @param int                  $attachment_id Attachment ID.
	 * @param string               $url           Image URL.
	 * @return array<string, mixed>
	 */
	private function media_settings_for_element( array $element, int $attachment_id, string $url ): array {
		$el_type     = $element['elType'] ?? '';
		$widget_type = $element['widgetType'] ?? '';

		if ( 'widget' === $el_type && 'image' === $widget_type ) {
			return array(
				'image' => array(
					'id'  => $attachment_id,
					'url' => $url,
				),
			);
		}

		if ( 'section' === $el_type || 'column' === $el_type ) {
			return array(
				'background_background' => 'classic',
				'background_image'      => array(
					'id'  => $attachment_id,
					'url' => $url,
				),
			);
		}

		return array(
			'image' => array(
				'id'  => $attachment_id,
				'url' => $url,
			),
		);
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
