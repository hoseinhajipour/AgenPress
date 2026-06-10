=== AgenPress ===
Contributors: agenpress
Tags: ai, chatbot, openai, claude, elementor, woocommerce, agent
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI Operating System for WordPress with Admin, Elementor, and Sales AI assistants.

== Description ==

AgenPress is an advanced AI assistant plugin for WordPress with Agentic AI architecture. It acts as a central intelligent assistant inside WordPress with controlled access to all site sections.

**Features (Phase 1 Foundation):**

* AI Admin Assistant chat interface
* OpenAI and Claude provider support
* Agentic task queue with progress tracking
* Memory system for brand and site knowledge
* Role-based access control
* REST API for all operations

== Installation ==

1. Upload the plugin to `/wp-content/plugins/agenpress`
2. Run `composer install` in the plugin directory
3. Run `npm install && npm run build` in the plugin directory
4. Activate the plugin through the 'Plugins' menu in WordPress
5. Configure your AI provider API key in AgenPress > Settings

== Frequently Asked Questions ==

= Which AI providers are supported? =

OpenAI (fully supported) and Claude (stub in Phase 1).

= Does it require WooCommerce or Elementor? =

No. Core features work standalone. WooCommerce and Elementor modules activate when those plugins are present.

== Changelog ==

= 0.7.0 =
* Enterprise license tier gating for advanced features
* Multi-agent orchestration (content, design, sales specialists)
* Workflow automation builder with manual run
* Team management: assign escalated chats to support agents
* Usage analytics dashboard (tokens, messages, tool executions)
* External REST API with API key authentication
* MCP-compatible tool server for external AI clients

= 0.6.0 =
* Storefront sales chatbot with floating widget and [agenpress_chat] shortcode
* WooCommerce sales tools: product search, recommendations, cart, coupons, orders
* Guest and logged-in visitor sessions with conversation persistence
* Product catalog knowledge injected into sales AI prompts
* Admin Sales Inbox for escalated customer chats

= 0.5.0 =
* Elementor AI Assistant with in-editor floating panel
* Selection mode: reads selected section/column/widget context
* Elementor tools: page structure, create section, update settings, duplicate, delete
* DALL-E image generation for sections and backgrounds
* Brand-aware design via Memory System RAG in Elementor prompts

= 0.4.0 =
* Memory System with embeddings and semantic RAG retrieval
* Auto-extract brand info from theme, Elementor kit, and WooCommerce
* Memory Manager UI with semantic search and inline edit

= 0.3.0 =
* Full agentic task orchestration with AI planning and real tool execution
* Task templates: SEO articles batch, product descriptions, custom AI-planned tasks
* One step per queue run with exponential backoff retry
* Cancel, retry, and re-run task actions
* Step-level progress tracking and detailed task logs in UI

= 0.2.0 =
* AI Admin Assistant: full content management tools (posts, pages, categories, tags, users, media)
* WooCommerce product tools when WooCommerce is active
* Destructive action confirmation flow with modal UI
* Module-scoped tools and multi-turn tool execution
* Content generation prompts (SEO, meta, FAQ schema, alt text)
* Module-specific chat suggestions

= 0.1.0 =
* Initial foundation release
* Plugin scaffold with REST API, chat, tasks, memory, and admin UI
