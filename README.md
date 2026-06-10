# AgenPress

سیستم‌عامل هوش مصنوعی برای وردپرس — دستیارهای AI در پنل مدیریت، المنتور و فروشگاه، با اجرای وظایف چندمرحله‌ای (Agentic Tasks).

**نسخه:** 0.7.0

## امکانات کلیدی

### سه ماژول هوش مصنوعی

| ماژول | شناسه | توضیح |
|-------|--------|--------|
| **Admin Assistant** | `admin` | مدیریت محتوا، کاربران، رسانه، ووکامرس و صف‌بندی وظایف پس‌زمینه |
| **Elementor Assistant** | `elementor` | طراحی و ویرایش صفحات، سکشن‌ها و ویجت‌های المنتور (نیاز به Elementor) |
| **Sales Assistant** | `sales` | چت فروشگاهی، تحلیل فروش در ادمین و ابزارهای ووکامرس (نیاز به WooCommerce) |

### پنل مدیریت React

رابط کاربری یکپارچه در **AgenPress** با صفحات:

- **Dashboard** — نمای کلی
- **AI Chat** — گفتگو با دستیارها (Admin / Elementor / Sales) با آپلود فایل، لینک داخلی و تأیید عملیات حساس
- **Agent Tasks** — ایجاد، پیگیری، توقف، لغو و اجرای مجدد وظایف چندمرحله‌ای
- **Memory Manager** — حافظه سایت (برند، محصولات، سیاست‌ها) با جستجوی معنایی
- **Workflows** — گردش‌کارهای زمان‌بندی‌شده
- **Sales Inbox** — صندوق پیام‌های ارجاع‌شده از چت فروشگاهی
- **Analytics** — آمار استفاده از AI
- **Storefront Sales Chat** — تنظیمات ویجت چت فروشگاه
- **Settings** — کلید API، مدل‌ها، ارائه‌دهنده و لایسنس

### وظایف عامل (Agent Tasks)

وظایف پس‌زمینه با **Action Scheduler** و قالب‌های آماده:

- **SEO Articles Batch** — تولید دسته‌ای مقالات سئو با تصویر شاخص، FAQ schema و انتشار اختیاری
- **Product Descriptions** — ایجاد/به‌روزرسانی محصولات ووکامرس با متن و تصویر AI
- **Elementor Site Pages** — ساخت صفحات المنتور (بنر، سکشن، ویجت)
- **Custom Task** — برنامه‌ریزی خودکار چندمرحله‌ای از روی توضیح کاربر

درخواست‌های بزرگ در چت ادمین به‌صورت خودکار (`ChatTaskAutoPlanner`) به صف وظایف منتقل می‌شوند.

### حافظه و زمینه (Memory)

- ذخیره دانش سایت (برند، محصولات، سیاست‌ها)
- Embedding و جستجوی معنایی
- استخراج خودکار اطلاعات برند
- تزریق زمینه به چت و وظایف

### چت فروشگاهی (Storefront)

- ویجت شناور یا شورت‌کد `[agenpress_chat]`
- جستجو و پیشنهاد محصول، وضعیت سفارش، کوپن، سبد خرید
- حافظه مکالمه مشتری
- ارجاع به اپراتور انسانی (`escalate_to_human`)
- صندوق Inbox برای تیم فروش

### یکپارچه‌سازی‌های ویرایشگر

- **Elementor Editor Panel** — چت AI داخل ویرایشگر المنتور با آگاهی از المان انتخاب‌شده
- **Classic Editor** — تولید متن و تصویر در نوار ابزار
- **Post Featured Image** — تولید تصویر شاخص با AI
- **FAQ Schema** — خروجی JSON-LD سئو از متای پست

### ارائه‌دهندگان و مدل‌های AI

پشتیبانی از چند ارائه‌دهنده از طریق لایه انتزاعی:

- **OpenAI** — GPT-5.x، DALL·E، GPT Image
- **Anthropic (Claude)** — Opus / Sonnet
- **GapGPT** — مدل‌های چینی و پروکسی چندمدلی
- **Custom** — endpoint سازگار با OpenAI

مدل‌های متنی و تصویری در تنظیمات قابل انتخاب هستند.

### API و یکپارچه‌سازی خارجی

- **REST API** — `agenpress/v1`
- **External API** — چت و اجرای ابزار با کلید API
- **MCP** — فهرست و فراخوانی ابزارها (`/mcp/tools`, `/mcp/call`)
- **Multi-Agent Orchestrator** — هماهنگی بین متخصص‌ها (`/orchestrate/chat`)

### امنیت

- قابلیت‌های سفارشی (`agenpress_use_admin_ai`, …)
- تأیید کاربر برای عملیات مخرب (حذف پست/محصول/المان)
- Rate limiting و audit log
- ذخیره رمزنگاری‌شده کلید API
- اعتبارسنجی دسترسی ابزارها (`PermissionValidator`)

## پیش‌نیازها

- WordPress 6.0+
- PHP 8.1+
- Node.js 18+
- Composer 2.x (اختیاری — PHPUnit و Action Scheduler)
- **اختیاری:** WooCommerce (ماژول Sales)، Elementor (ماژول Elementor)

## نصب

```bash
cd wp-content/plugins/AgenPress
npm install
npm run build
composer install   # اختیاری
```

افزونه را در وردپرس فعال کنید، سپس به **AgenPress → Settings** بروید و کلید API ارائه‌دهنده AI را وارد کنید.

## توسعه

```bash
npm start          # حالت watch برای UI ادمین
composer install
npm run i18n       # تولید فایل‌های ترجمه
```

ساختار فرانت‌اند:

| Entry | مسیر | کاربرد |
|-------|------|--------|
| `admin` | `src/admin/` | پنل React ادمین |
| `elementor-editor` | `src/elementor/` | پنل چت در المنتور |
| `post-editor` | `src/post-editor/` | دکمه‌های AI در ویرایشگر پست |
| `frontend-chat` | `src/frontend/` | ویجت چت فروشگاه |

## معماری

```
WordPress Plugin (PHP 8.1)
├── Modules (Admin, Elementor, Sales)
├── AgentEngine + ToolRegistry
├── TaskQueue → Action Scheduler → TaskRunner
├── MemoryStore + EmbeddingService
├── REST API (agenpress/v1)
└── React Admin UI (@wordpress/scripts)
```

- **Backend:** پلاگین PHP با REST API سفارشی
- **صف:** Action Scheduler برای وظایف ناهمزمان
- **Frontend:** React با `@wordpress/scripts` و Tailwind CSS
- **AI:** لایه Provider با پشتیبانی OpenAI-compatible

## ابزارهای هر ماژول

### Admin

`list_posts`, `get_post`, `create_post`, `update_post`, `delete_post`, `list_terms`, `create_term`, `list_users`, `get_user`, `update_media`, `generate_image`, `get_site_info`, `create_agent_task` — و در صورت نصب ووکامرس: `list_products`, `get_product`, `create_product`, `update_product`, `delete_product`

### Elementor

`list_elementor_pages`, `get_page_structure`, `get_element`, `create_section`, `create_widget`, `update_widget_settings`, `search_elements`, `add_attached_image_to_page`, `apply_media_to_element`, `duplicate_element`, `delete_element`, `generate_section_image`

### Sales (WooCommerce)

`search_products`, `recommend_products`, `get_product_details`, `get_cart_summary`, `list_coupons`, `validate_coupon`, `get_my_orders`, `get_order_status`, `escalate_to_human`, `get_best_sellers`, `get_sales_overview`, `list_orders`

## REST API

پایه: `/wp-json/agenpress/v1`

| Endpoint | Methods | توضیح |
|----------|---------|--------|
| `/settings` | GET, PUT | تنظیمات افزونه |
| `/conversations` | GET, POST | مکالمات چت |
| `/conversations/{id}` | GET, DELETE | یک مکالمه |
| `/conversations/{id}/messages/{message_id}` | DELETE | حذف پیام |
| `/chat/{module}` | POST | ارسال پیام چت (`admin`, `elementor`, `sales`) |
| `/chat/{module}/confirm` | POST | تأیید عملیات در انتظار |
| `/chat/links/search` | GET | جستجوی لینک داخلی برای چت |
| `/tasks/templates` | GET | قالب‌های وظیفه |
| `/tasks` | GET, POST | وظایف عامل |
| `/tasks/{id}` | GET, DELETE | یک وظیفه |
| `/tasks/{id}/pause` | POST | توقف / ادامه |
| `/tasks/{id}/cancel` | POST | لغو |
| `/tasks/{id}/retry` | POST | تلاش مجدد |
| `/tasks/{id}/rerun` | POST | اجرای مجدد |
| `/memory` | GET, POST | ورودی‌های حافظه |
| `/memory/search` | POST | جستجوی معنایی |
| `/memory/extract-brand` | POST | استخراج برند |
| `/memory/reindex` | POST | بازسازی ایندکس |
| `/memory/{id}` | GET, PUT, DELETE | یک ورودی |
| `/upload` | POST | آپلود فایل |
| `/generate-image` | POST | تولید تصویر AI |
| `/generate-text` | POST | تولید متن AI |
| `/posts/{id}/featured-image` | POST | تصویر شاخص AI |
| `/elementor/documents/{id}/elements` | GET | ساختار المنتور |
| `/sales/chat` | POST | چت فروشگاهی |
| `/sales/conversation/{id}` | GET | مکالمه فروش |
| `/sales/session` | GET, POST | نشست بازدیدکننده |
| `/sales/escalate` | POST | ارجاع به انسان |
| `/inbox` | GET | صندوق فروش |
| `/inbox/{id}` | GET, PUT | یک مکالمه inbox |
| `/inbox/team` | GET | اعضای تیم |
| `/inbox/{id}/assign` | POST | تخصیص |
| `/inbox/{id}/resolve` | POST | بستن |
| `/analytics` | GET | آمار |
| `/workflows` | GET, POST | گردش‌کارها |
| `/workflows/{id}` | GET, PUT, DELETE | یک گردش‌کار |
| `/workflows/{id}/run` | POST | اجرای دستی |
| `/api-keys` | GET, POST | کلیدهای API خارجی |
| `/api-keys/{id}` | DELETE | حذف کلید |
| `/orchestrate/specialists` | GET | متخصص‌های موجود |
| `/orchestrate/chat` | POST | چت چندعامله |
| `/external/chat` | POST | چت با کلید API |
| `/external/tools` | GET | فهرست ابزارها |
| `/external/tools/{name}` | POST | اجرای ابزار |
| `/mcp/tools` | GET | ابزارهای MCP |
| `/mcp/call` | POST | فراخوانی MCP |

## ترجمه

افزونه از `languages/` پشتیبانی می‌کند. ترجمه فارسی (`fa_IR`) با `npm run i18n` قابل به‌روزرسانی است.

## مجوز

GPL-2.0-or-later
