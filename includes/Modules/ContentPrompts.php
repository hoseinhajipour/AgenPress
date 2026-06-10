<?php
/**
 * Content generation prompt instructions for AI modules.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Class ContentPrompts
 */
class ContentPrompts {

	/**
	 * Admin content generation instructions appended to system prompt.
	 *
	 * @return string
	 */
	public static function admin_instructions(): string {
		return implode(
			"\n",
			array(
				'Content Generation Capabilities:',
				'- SEO articles: keyword-rich titles, H2/H3 structure, internal linking suggestions, meta description.',
				'- Meta title and meta description: concise, within character limits (title ~60, description ~160).',
				'- FAQ Schema: generate JSON-LD FAQPage markup from topic Q&A pairs.',
				'- Hashtags: relevant social hashtags for the content topic.',
				'- Alt text: descriptive, accessible image alt text.',
				'- When creating content, use create_post tool with status "draft" unless user asks to publish.',
				'- Suggest internal links to existing posts when relevant.',
			)
		);
	}
}
