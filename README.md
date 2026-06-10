# AgenPress

AI Operating System for WordPress — Admin, Elementor, and Sales AI assistants with agentic task execution.

## Requirements

- WordPress 6.0+
- PHP 8.1+
- Node.js 18+
- Composer 2.x

## Installation

```bash
cd wp-content/plugins/agenpress
npm install
npm run build
composer install   # optional — dev deps (PHPUnit) and Action Scheduler
```

Activate the plugin in WordPress admin, then go to **AgenPress > Settings** to configure your AI provider API key.

## Development

```bash
npm start    # Watch mode for React admin UI
composer install
```

## Architecture

- **Backend:** WordPress-native PHP plugin with custom REST API (`agenpress/v1`)
- **Queue:** Action Scheduler for async agent tasks
- **Frontend:** React admin app built with `@wordpress/scripts`
- **AI:** OpenAI (full) + Claude (stub) via provider abstraction

## REST API Endpoints

| Endpoint | Methods | Description |
|----------|---------|-------------|
| `/wp-json/agenpress/v1/settings` | GET, PUT | Plugin settings |
| `/wp-json/agenpress/v1/conversations` | GET, POST | Chat conversations |
| `/wp-json/agenpress/v1/conversations/{id}` | GET, DELETE | Single conversation |
| `/wp-json/agenpress/v1/chat/{module}` | POST | Send chat message |
| `/wp-json/agenpress/v1/tasks` | GET, POST | Agent tasks |
| `/wp-json/agenpress/v1/tasks/{id}` | GET, DELETE | Single task |
| `/wp-json/agenpress/v1/tasks/{id}/pause` | POST | Pause/resume task |
| `/wp-json/agenpress/v1/memory` | GET, POST | Memory entries |
| `/wp-json/agenpress/v1/memory/{id}` | GET, PUT, DELETE | Single memory entry |
| `/wp-json/agenpress/v1/upload` | POST | File upload |

## License

GPL-2.0-or-later
