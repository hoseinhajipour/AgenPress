import { __ } from '@wordpress/i18n';

const SEO_OPTIONS = {
	sections_count: 4,
	featured_image: true,
	section_images: false,
	include_faq: true,
	include_conclusion: true,
	suggest_services: false,
	suggest_products: false,
};

/**
 * Example workflow templates users can clone and customize.
 *
 * @return {Array<{id: string, title: string, description: string, requiresWoo?: boolean, workflow: object}>}
 */
export function getWorkflowPresets() {
	return [
		{
			id: 'content_strategy',
			title: __( 'Content Strategy Brief', 'agenpress' ),
			description: __( 'AI analyzes your site context, then turns the strategy into 10 concrete post ideas. Edit the topic in step 1 if needed.', 'agenpress' ),
			workflow: {
				title: __( 'Content Strategy Brief', 'agenpress' ),
				description: __( 'Two-step AI chain: strategy analysis → post ideas list.', 'agenpress' ),
				enabled: true,
				trigger_type: 'manual',
				steps: [
					{
						type: 'ai',
						label: __( 'Analyze content strategy', 'agenpress' ),
						prompt: __(
							'Analyze what types of content would help this WordPress site grow. Consider SEO, audience engagement, and conversions. Write 3–5 paragraphs of plain text only (no JSON, no bullet lists).',
							'agenpress'
						),
						output_key: 'strategy',
					},
					{
						type: 'ai',
						label: __( 'Generate post ideas', 'agenpress' ),
						prompt: __(
							'Based on this content strategy:\n\n{{strategy}}\n\nList exactly 10 specific blog post titles the site should publish next. Use a plain numbered list only (1. Title…). No JSON.',
							'agenpress'
						),
						output_key: 'post_ideas',
					},
				],
			},
		},
		{
			id: 'seo_batch_3',
			title: __( '3 SEO Articles Batch', 'agenpress' ),
			description: __( 'Plans 3 article titles, writes each as a draft with FAQ and featured image. Change YOUR TOPIC in steps 1 and 2–4.', 'agenpress' ),
			workflow: {
				title: __( '3 SEO Articles Batch', 'agenpress' ),
				description: __( 'Plan titles → create 3 SEO drafts → summary.', 'agenpress' ),
				enabled: true,
				trigger_type: 'manual',
				steps: [
					{
						type: 'ai',
						label: __( 'Plan article titles', 'agenpress' ),
						prompt: __(
							'Plan 3 unique SEO blog article titles about "YOUR TOPIC HERE". Return ONLY a JSON array of 3 strings. No markdown.',
							'agenpress'
						),
						output_key: 'article_titles',
					},
					... [ 0, 1, 2 ].map( ( index ) => ( {
						type: 'seo_article',
						label: __( 'Create SEO article', 'agenpress' ) + ` ${ index + 1 }`,
						topic: 'YOUR TOPIC HERE',
						index,
						publish: false,
						options: { ...SEO_OPTIONS },
					} ) ),
					{
						type: 'ai',
						label: __( 'Batch summary', 'agenpress' ),
						prompt: __(
							'Summarize the completed SEO article batch in 2–3 sentences for the site admin.',
							'agenpress'
						),
						output_key: 'summary',
					},
				],
			},
		},
		{
			id: 'site_snapshot',
			title: __( 'Site Health Snapshot', 'agenpress' ),
			description: __( 'Fetches real site info via tool, then AI writes a short health report with recommendations.', 'agenpress' ),
			workflow: {
				title: __( 'Site Health Snapshot', 'agenpress' ),
				description: __( 'Tool: get_site_info → AI report.', 'agenpress' ),
				enabled: true,
				trigger_type: 'manual',
				steps: [
					{
						type: 'tool',
						label: __( 'Fetch site info', 'agenpress' ),
						tool: 'get_site_info',
						args: {},
						output_key: 'site_info',
					},
					{
						type: 'ai',
						label: __( 'Write health report', 'agenpress' ),
						prompt: __(
							'Write a brief WordPress site health report for the administrator. Cover content overview, technical context, and 5 actionable recommendations. Use markdown headings and bullet points. Keep it under 400 words.',
							'agenpress'
						),
						output_key: 'report',
					},
				],
			},
		},
		{
			id: 'draft_review',
			title: __( 'Draft Posts Review', 'agenpress' ),
			description: __( 'Lists recent drafts, then AI suggests a 7-day publishing plan and priorities.', 'agenpress' ),
			workflow: {
				title: __( 'Draft Posts Review', 'agenpress' ),
				description: __( 'Tool: list_posts (drafts) → AI publishing plan.', 'agenpress' ),
				enabled: true,
				trigger_type: 'manual',
				steps: [
					{
						type: 'tool',
						label: __( 'List draft posts', 'agenpress' ),
						tool: 'list_posts',
						args: { limit: 10, post_status: 'draft', post_type: 'post' },
						output_key: 'drafts',
					},
					{
						type: 'ai',
						label: __( 'Publishing plan', 'agenpress' ),
						prompt: __(
							'Review the draft posts situation on this WordPress site and suggest priorities: which drafts to finish first, common content gaps, and a practical 7-day publishing plan. Use markdown with clear sections.',
							'agenpress'
						),
						output_key: 'plan',
					},
				],
			},
		},
		{
			id: 'faq_ideas',
			title: __( 'FAQ Content Pack', 'agenpress' ),
			description: __( 'Generates 8 FAQ pairs for a topic — useful for schema markup or a new FAQ page. Edit the topic in the prompt.', 'agenpress' ),
			workflow: {
				title: __( 'FAQ Content Pack', 'agenpress' ),
				description: __( 'Single AI step: 8 Q&A pairs in markdown.', 'agenpress' ),
				enabled: true,
				trigger_type: 'manual',
				steps: [
					{
						type: 'ai',
						label: __( 'Generate FAQ pairs', 'agenpress' ),
						prompt: __(
							'Generate 8 frequently asked questions and detailed answers about "YOUR TOPIC HERE" for a WordPress site. Format as markdown: ## Question followed by answer paragraphs. Suitable for FAQ schema and an FAQ page.',
							'agenpress'
						),
						output_key: 'faq_content',
					},
				],
			},
		},
		{
			id: 'category_audit',
			title: __( 'Category & Tag Audit', 'agenpress' ),
			description: __( 'Lists categories, then AI suggests new terms and a cleaner taxonomy structure.', 'agenpress' ),
			workflow: {
				title: __( 'Category & Tag Audit', 'agenpress' ),
				description: __( 'Tool: list_terms → AI taxonomy recommendations.', 'agenpress' ),
				enabled: true,
				trigger_type: 'manual',
				steps: [
					{
						type: 'tool',
						label: __( 'List categories', 'agenpress' ),
						tool: 'list_terms',
						args: { taxonomy: 'category' },
						output_key: 'categories',
					},
					{
						type: 'tool',
						label: __( 'List tags', 'agenpress' ),
						tool: 'list_terms',
						args: { taxonomy: 'post_tag' },
						output_key: 'tags',
					},
					{
						type: 'ai',
						label: __( 'Taxonomy recommendations', 'agenpress' ),
						prompt: __(
							'Audit this WordPress site taxonomy. Suggest 5–10 new categories or tags to add, terms to merge or remove, and a simpler structure for editors. Output markdown with sections: Current state, Gaps, Recommendations.',
							'agenpress'
						),
						output_key: 'taxonomy_plan',
					},
				],
			},
		},
		{
			id: 'custom_planned_task',
			title: __( 'Custom Planned Task', 'agenpress' ),
			description: __( 'AI breaks a goal into action items, then executes. Edit title and description in step 1.', 'agenpress' ),
			workflow: {
				title: __( 'Custom Planned Task', 'agenpress' ),
				description: __( 'AI plan → AI execution pattern for open-ended admin work.', 'agenpress' ),
				enabled: true,
				trigger_type: 'manual',
				steps: [
					{
						type: 'ai_plan',
						label: __( 'Plan actions', 'agenpress' ),
						title: __( 'Improve site content quality', 'agenpress' ),
						description: __(
							'Review content gaps and suggest concrete improvements for the WordPress site (posts, pages, meta, internal links).',
							'agenpress'
						),
					},
					{
						type: 'ai',
						label: __( 'Execute planned work', 'agenpress' ),
						prompt: __(
							'Execute this WordPress admin task and describe what was done. Title: Improve site content quality. Description: Review content gaps and suggest concrete improvements.',
							'agenpress'
						),
						output_key: 'result',
					},
				],
			},
		},
		{
			id: 'product_descriptions',
			title: __( 'Product Description Refresh', 'agenpress' ),
			description: __( 'Fetches WooCommerce products and rewrites descriptions for the first 3. Requires WooCommerce.', 'agenpress' ),
			requiresWoo: true,
			workflow: {
				title: __( 'Product Description Refresh', 'agenpress' ),
				description: __( 'list_products → 3× product_description steps.', 'agenpress' ),
				enabled: true,
				trigger_type: 'manual',
				steps: [
					{
						type: 'tool',
						label: __( 'Fetch products', 'agenpress' ),
						tool: 'list_products',
						args: { limit: 3, status: 'any' },
						output_key: 'products',
					},
					... [ 0, 1, 2 ].map( ( index ) => ( {
						type: 'product_description',
						label: __( 'Write product description', 'agenpress' ) + ` ${ index + 1 }`,
						niche: 'YOUR PRODUCT NICHE',
						index,
					} ) ),
				],
			},
		},
	];
}
