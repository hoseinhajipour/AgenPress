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
				'- AI images: use generate_image for featured images, in-content images, or media library assets on any post type.',
				'- Links: use [title](url) markdown with a short clickable title; never output bare URLs.',
				'- When creating content, use create_post tool with status "draft" unless user asks to publish.',
				'- Suggest internal links to existing posts when relevant.',
			)
		);
	}

	/**
	 * Instructions for structured SEO article batch generation (JSON output).
	 *
	 * @return string
	 */
	public static function seo_article_instructions(): string {
		return implode(
			"\n",
			array(
				'Output rules:',
				'- Return valid JSON only. No markdown code fences or commentary.',
				'- Write in the same language as the article title and topic.',
				'- meta_title ~60 chars; meta_description ~160 chars.',
				'- tags: WordPress post tags (no # prefix).',
				'- image_alt: descriptive alt text per section (separate field, never inside section content).',
				'- image_prompt: AI image prompt only (separate field, never inside section content).',
				'- Section content: reader-facing HTML only (<p>, <ul>, <ol>, <li>, <strong>, <a>).',
				'- Do not include JSON-LD, FAQPage schema, @context, image_prompt, alt text, or hashtags inside any content field.',
				'- Do not include a top-level content key; use the sections array for the article body.',
				'- FAQ schema is added automatically by the CMS from the faq array.',
				'- Links: <a href="url">title</a> in HTML.',
			)
		);
	}
}
