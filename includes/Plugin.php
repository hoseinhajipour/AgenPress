<?php
/**
 * Main plugin orchestrator.
 *
 * @package AgenPress
 */

namespace AgenPress;

use AgenPress\Admin\AdminMenu;
use AgenPress\Core\Container;
use AgenPress\Core\Loader;
use AgenPress\Modules\Admin\AdminModule;
use AgenPress\Modules\Elementor\ElementorModule;
use AgenPress\Modules\Sales\SalesModule;
use AgenPress\REST\ChatController;
use AgenPress\REST\MemoryController;
use AgenPress\REST\SettingsController;
use AgenPress\REST\TaskController;
use AgenPress\REST\UploadController;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Service container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Hook loader.
	 *
	 * @var Loader
	 */
	private Loader $loader;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->container = new Container();
		$this->loader    = new Loader();
		$this->register_services();
	}

	/**
	 * Get the service container.
	 *
	 * @return Container
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Register all services in the container.
	 *
	 * @return void
	 */
	private function register_services(): void {
		$this->container->register(
			'settings',
			static function (): Core\Settings {
				return new Core\Settings();
			}
		);

		$this->container->register(
			'audit_logger',
			static function (): Security\AuditLogger {
				return new Security\AuditLogger();
			}
		);

		$this->container->register(
			'rate_limiter',
			static function (): Security\RateLimiter {
				return new Security\RateLimiter();
			}
		);

		$this->container->register(
			'permission_validator',
			static function ( Container $c ): Security\PermissionValidator {
				return new Security\PermissionValidator( $c->get( 'audit_logger' ) );
			}
		);

		$this->container->register(
			'provider_factory',
			static function ( Container $c ): AI\ProviderFactory {
				return new AI\ProviderFactory( $c->get( 'settings' ) );
			}
		);

		$this->container->register(
			'conversation_repository',
			static function (): Chat\ConversationRepository {
				return new Chat\ConversationRepository();
			}
		);

		$this->container->register(
			'message_repository',
			static function (): Chat\MessageRepository {
				return new Chat\MessageRepository();
			}
		);

		$this->container->register(
			'embedding_repository',
			static function (): Memory\EmbeddingRepository {
				return new Memory\EmbeddingRepository();
			}
		);

		$this->container->register(
			'embedding_service',
			static function ( Container $c ): Memory\EmbeddingService {
				return new Memory\EmbeddingService( $c->get( 'provider_factory' ) );
			}
		);

		$this->container->register(
			'memory_store',
			static function ( Container $c ): Memory\MemoryStore {
				$store = new Memory\MemoryStore();
				$store->set_embedding_services(
					$c->get( 'embedding_service' ),
					$c->get( 'embedding_repository' )
				);
				return $store;
			}
		);

		$this->container->register(
			'product_catalog',
			static function (): Sales\ProductCatalog {
				return new Sales\ProductCatalog();
			}
		);

		$this->container->register(
			'visitor_session',
			static function (): Sales\VisitorSession {
				return new Sales\VisitorSession();
			}
		);

		$this->container->register(
			'customer_memory',
			static function ( Container $c ): Sales\CustomerMemory {
				return new Sales\CustomerMemory(
					$c->get( 'conversation_repository' ),
					$c->get( 'message_repository' )
				);
			}
		);

		$this->container->register(
			'context_builder',
			static function ( Container $c ): Memory\ContextBuilder {
				return new Memory\ContextBuilder(
					$c->get( 'memory_store' ),
					$c->get( 'product_catalog' ),
					$c->get( 'settings' )
				);
			}
		);

		$this->container->register(
			'brand_extractor',
			static function ( Container $c ): Memory\BrandExtractor {
				return new Memory\BrandExtractor( $c->get( 'memory_store' ) );
			}
		);

		$this->container->register(
			'elementor_documents',
			static function (): Modules\Elementor\ElementorDocumentService {
				return new Modules\Elementor\ElementorDocumentService();
			}
		);

		$this->container->register(
			'pending_actions',
			static function (): Agents\PendingActionStore {
				return new Agents\PendingActionStore();
			}
		);

		$this->container->register(
			'module_manager',
			static function (): Modules\ModuleManager {
				return new Modules\ModuleManager();
			}
		);

		$this->container->register(
			'tool_registry',
			static function ( Container $c ): Agents\ToolRegistry {
				$registry = new Agents\ToolRegistry();
				$registry->set_permission_validator( $c->get( 'permission_validator' ) );
				return $registry;
			}
		);

		$this->container->register(
			'task_planner',
			static function (): Agents\TaskPlanner {
				return new Agents\TaskPlanner();
			}
		);

		$this->container->register(
			'task_step_executor',
			static function ( Container $c ): Agents\TaskStepExecutor {
				return new Agents\TaskStepExecutor(
					$c->get( 'provider_factory' ),
					$c->get( 'tool_registry' ),
					$c->get( 'context_builder' )
				);
			}
		);

		$this->container->register(
			'task_queue',
			static function ( Container $c ): Agents\TaskQueue {
				return new Agents\TaskQueue( $c->get( 'audit_logger' ) );
			}
		);

		$this->container->register(
			'agent_engine',
			static function ( Container $c ): Agents\AgentEngine {
				return new Agents\AgentEngine(
					$c->get( 'provider_factory' ),
					$c->get( 'tool_registry' ),
					$c->get( 'task_queue' ),
					$c->get( 'context_builder' ),
					$c->get( 'message_repository' ),
					$c->get( 'audit_logger' ),
					$c->get( 'pending_actions' ),
					$c->get( 'permission_validator' )
				);
			}
		);

		$this->container->register(
			'license_gate',
			static function ( Container $c ): Core\LicenseGate {
				return new Core\LicenseGate( $c->get( 'settings' ) );
			}
		);

		$this->container->register(
			'api_key_manager',
			static function (): Security\ApiKeyManager {
				return new Security\ApiKeyManager();
			}
		);

		$this->container->register(
			'analytics_service',
			static function (): Analytics\AnalyticsService {
				return new Analytics\AnalyticsService();
			}
		);

		$this->container->register(
			'workflow_repository',
			static function (): Workflows\WorkflowRepository {
				return new Workflows\WorkflowRepository();
			}
		);

		$this->container->register(
			'workflow_runner',
			static function ( Container $c ): Workflows\WorkflowRunner {
				return new Workflows\WorkflowRunner(
					$c->get( 'workflow_repository' ),
					$c->get( 'task_queue' ),
					$c->get( 'task_step_executor' )
				);
			}
		);

		$this->container->register(
			'multi_agent_orchestrator',
			static function ( Container $c ): Agents\MultiAgentOrchestrator {
				return new Agents\MultiAgentOrchestrator(
					$c->get( 'provider_factory' ),
					$c->get( 'module_manager' ),
					$c->get( 'agent_engine' )
				);
			}
		);
	}

	/**
	 * Boot and run the plugin.
	 *
	 * @return void
	 */
	public function run(): void {
		$this->load_textdomain();
		$this->register_modules();
		$this->register_hooks();
		$this->loader->run();
	}

	/**
	 * Load plugin text domain.
	 *
	 * @return void
	 */
	private function load_textdomain(): void {
		$this->loader->add_action(
			'init',
			$this,
			'load_translations'
		);
	}

	/**
	 * Load translations callback.
	 *
	 * @return void
	 */
	public function load_translations(): void {
		load_plugin_textdomain(
			'agenpress',
			false,
			dirname( AGENPRESS_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Register AI modules.
	 *
	 * @return void
	 */
	private function register_modules(): void {
		/** @var Modules\ModuleManager $manager */
		$manager = $this->container->get( 'module_manager' );

		$modules = array(
			new AdminModule( $this->container->get( 'tool_registry' ) ),
			new ElementorModule(
				$this->container->get( 'tool_registry' ),
				$this->container->get( 'elementor_documents' ),
				$this->container->get( 'provider_factory' )
			),
			new SalesModule(
				$this->container->get( 'tool_registry' ),
				$this->container->get( 'conversation_repository' )
			),
		);

		foreach ( $modules as $module ) {
			$manager->register( $module );
			$module->boot();
		}

		$this->container->set( 'modules', $modules );
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		$admin_menu = new AdminMenu();

		$this->loader->add_action( 'admin_menu', $admin_menu, 'register_menu' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin_menu, 'enqueue_assets' );

		$elementor_editor = new Modules\Elementor\ElementorEditor( $this->container->get( 'module_manager' ) );
		$this->loader->add_action( 'elementor/editor/before_enqueue_scripts', $elementor_editor, 'enqueue_scripts' );
		$this->loader->add_action( 'elementor/editor/footer', $elementor_editor, 'render_panel_mount' );

		$this->loader->add_action(
			'rest_api_init',
			new SettingsController( $this->container ),
			'register_routes'
		);

		$this->loader->add_action(
			'rest_api_init',
			new ChatController( $this->container ),
			'register_routes'
		);

		$this->loader->add_action(
			'rest_api_init',
			new TaskController( $this->container ),
			'register_routes'
		);

		$this->loader->add_action(
			'rest_api_init',
			new MemoryController( $this->container ),
			'register_routes'
		);

		$this->loader->add_action(
			'rest_api_init',
			new REST\SalesChatController( $this->container ),
			'register_routes'
		);

		$this->loader->add_action(
			'rest_api_init',
			new REST\InboxController( $this->container ),
			'register_routes'
		);

		$chat_widget = new Frontend\ChatWidget(
			$this->container->get( 'settings' ),
			$this->container->get( 'module_manager' )
		);
		$chat_widget->register();

		$this->loader->add_action(
			'rest_api_init',
			new UploadController( $this->container ),
			'register_routes'
		);

		$this->loader->add_action(
			'rest_api_init',
			new REST\ImageController( $this->container ),
			'register_routes'
		);

		$this->loader->add_action(
			'rest_api_init',
			new REST\TextController( $this->container ),
			'register_routes'
		);

		$this->loader->add_action(
			'rest_api_init',
			new REST\AnalyticsController( $this->container ),
			'register_routes'
		);

		$this->loader->add_action(
			'rest_api_init',
			new REST\WorkflowController( $this->container ),
			'register_routes'
		);

		$this->loader->add_action(
			'rest_api_init',
			new REST\ApiKeysController( $this->container ),
			'register_routes'
		);

		$this->loader->add_action(
			'rest_api_init',
			new REST\OrchestratorController( $this->container ),
			'register_routes'
		);

		$this->loader->add_action(
			'rest_api_init',
			new REST\ExternalApiController( $this->container ),
			'register_routes'
		);

		$this->loader->add_action(
			'rest_api_init',
			new REST\McpController( $this->container ),
			'register_routes'
		);

		$this->loader->add_action(
			'agenpress_run_task',
			new Agents\TaskRunner( $this->container ),
			'handle',
			10,
			1
		);
	}
}
