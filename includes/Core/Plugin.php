<?php

namespace AiSportsWriter\Core;

use AiSportsWriter\Admin\PostConfigPage;
use AiSportsWriter\Admin\CronSettingsPage;
use AiSportsWriter\Admin\ApiConfigPage;
use AiSportsWriter\Services\SportApiService;
use AiSportsWriter\Services\OpenAiService;

use Exception;
use WP_Error;

class Plugin
{
    /**
     * Singleton instance
     *
     * @var self
     */
    private static $instance = null;

    /**
     * Plugin version
     */
    private const PLUGIN_VERSION = '1.0.0';

    /**
     * Plugin text domain
     */
    private const PLUGIN_DOMAIN = 'ai-sports-writer';
    private const MENU_SLUG = 'ai-sports-writer';
    private const POST_SETTINGS_SLUG = 'ai-sports-writer-post';
    private const CRON_SETTINGS_SLUG = 'ai-sports-writer-cron';

    /**
     * Admin page instances
     *
     * @var PostConfigPage
     * @var CronSettingsPage
     * @var ApiConfigPage
     */
    private $postConfigPage;
    private $cronSettingsPage;
    private $apiConfigPage;

    /**
     * Logging levels
     */
    private const LOG_LEVELS = [
        'info' => 0,
        'warning' => 1,
        'error' => 2
    ];

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct()
    {
        $this->loadTextDomain();
        $this->initializePages();
        $this->setupHooks();
    }

    /**
     * Load plugin text domain for internationalization
     */
    private function loadTextDomain(): void
    {
        load_plugin_textdomain(
            self::PLUGIN_DOMAIN,
            false,
            dirname(plugin_basename(AI_SPORTS_WRITER_PLUGIN_FILE)) . '/languages'
        );
    }

    /**
     * Singleton instance getter
     *
     * @return self
     */
    public static function init()
    {
        if (self::$instance === null) {
            self::$instance = new self();

            // Register activation and deactivation hooks
            register_activation_hook(AI_SPORTS_WRITER_PLUGIN_FILE, [self::$instance, 'activate']);
            register_deactivation_hook(AI_SPORTS_WRITER_PLUGIN_FILE, [self::$instance, 'deactivate']);
            add_action('init', [self::class, 'initialize_content_generator']);
        }
        return self::$instance;
    }

    /**
     * Plugin activation method
     */
    public function activate(): void
    {
        // Ensure plugin is only activated by administrators
        if (!current_user_can('activate_plugins')) {
            return;
        }

        try {
            $this->create_sport_ai_writer_tables();
            $this->log('Plugin activated successfully');
        } catch (Exception $e) {
            $this->log("Activation failed: {$e->getMessage()}", 'error');

            // Prevent plugin activation and show error

            wp_die(
                sprintf(
                    // Translators: %s is the error message from an exception during plugin activation
                    esc_html__('AI Sports Writer could not be activated. Error: %s', 'ai-sports-writer'),
                    esc_html($e->getMessage())
                ),
                esc_html__('Plugin Activation Error', 'ai-sports-writer'),
                ['response' => 500]
            );
        }
    }


    /**
     * Plugin deactivation method
     */
    public function deactivate(): void
    {
        // Restrict plugin deactivation to administrators only.
        if (!current_user_can('activate_plugins')) {
            return;
        }

        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('ai_sports_writer_cron');
        wp_clear_scheduled_hook('ai_sports_writer_fetch_cron');

        $this->log('Plugin deactivated');
    }

    /**
     * Initialize admin pages
     */
    private function initializePages(
        PostConfigPage $postConfigPage = null,
        CronSettingsPage $cronSettingsPage = null,
        ApiConfigPage $apiConfigPage = null
    ): void {
        $this->postConfigPage = $postConfigPage ?? new PostConfigPage();
        $this->cronSettingsPage = $cronSettingsPage ?? new CronSettingsPage();
        $this->apiConfigPage = $apiConfigPage ?? new ApiConfigPage();
    }

    /**
     * Setup WordPress hooks
     */
    private function setupHooks(): void
    {
        add_action('init', [$this, 'registerPages']);
        add_action('admin_menu', [$this, 'addMainPluginMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueueAdminScripts($hook): void
    {
        // Only enqueue on plugin pages
        if (strpos($hook, 'ai-sports-writer') === false) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'ai-sports-writer-js',
            plugin_dir_url(AI_SPORTS_WRITER_PLUGIN_FILE) . 'assets/js/ai-sports-writer.js',
            ['jquery'],
            self::PLUGIN_VERSION,
            true
        );

        // Add localized variables to the JS file
        wp_localize_script(
            'ai-sports-writer-js',
            'fcg_ajax_object',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('fcg_nonce')
            ]
        );
    }


    /**
     * Register admin pages
     */
    public function registerPages(): void
    {
        $this->postConfigPage->register();
        $this->cronSettingsPage->register();
        $this->apiConfigPage->register();
    }

    /**
     * Add main plugin menu and submenus
     */
    public function addMainPluginMenu(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        add_menu_page(
            __('AI Sports Writer', 'ai-sports-writer'),
            __('AI Sports Writer', 'ai-sports-writer'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderMainPage'],
            'dashicons-admin-site-alt3',
            20
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Post Settings', 'ai-sports-writer'),
            __('Post Config', 'ai-sports-writer'),
            'manage_options',
            self::POST_SETTINGS_SLUG,
            [$this, 'post_settings_page']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Cron Settings', 'ai-sports-writer'),
            __('Cron status', 'ai-sports-writer'),
            'manage_options',
            self::CRON_SETTINGS_SLUG,
            [$this, 'post_settings_cron']
        );
    }

    public static function initialize_content_generator()
    {
        $sport_api_service = new SportApiService();
        $openai_service = new OpenAiService();

        $content_generator = new ContentGenerator($sport_api_service, $openai_service);
    }

    /**
     * Render main plugin page
     */
    public function renderMainPage(): void
    {
?>
<div class="wrap">
    <h1><?php echo esc_html__('AI Sports Writer', 'ai-sports-writer'); ?></h1>

    <form method="post" action="options.php">
        <?php
                wp_nonce_field('ai_sports_writer_regions', 'regions_nonce');
                settings_fields('ai_sports_writer_api_settings');
                do_settings_sections(self::MENU_SLUG);
                submit_button(__('Save Settings', 'ai-sports-writer'), 'primary', 'save-settings');
                ?>
    </form>

    <h2><?php echo esc_html__('Region Selection', 'ai-sports-writer'); ?></h2>

    <select id="region-selection" name="selected_regions[]" multiple="multiple" style="width: 100%;">

    </select>

    <button id="save-regions" class="button-primary">
        <?php echo esc_html__('Save Regions', 'ai-sports-writer'); ?>
    </button>
</div>

<?php
    }

    /**
     * Render post settings page
     */
    public function post_settings_page(): void
    {
    ?>
<div class="wrap">
    <h1><?php echo esc_html__('Post Configuration', 'ai-sports-writer'); ?></h1>
    <form method="post" action="options.php">
        <?php
                settings_fields('ai_sports_writer_post_settings');
                do_settings_sections(self::POST_SETTINGS_SLUG);
                submit_button();
                ?>
    </form>
</div>
<?php
    }

    /**
     * Render cron settings page
     */
    public function post_settings_cron(): void
    {
        $cronSettingsPage = new CronSettingsPage();
        $cronSettingsPage->renderPage();
    ?>
<div class="wrap">

    <form method="post" action="options.php">
        <?php
                settings_fields('ai_sports_writer_settings');
                do_settings_sections(self::CRON_SETTINGS_SLUG);

                ?>
    </form>
</div>
<?php
    }

    /**
     * Create plugin database tables
     *
     * @throws Exception If table creation fails
     */
    public function create_sport_ai_writer_tables(): void
    {
        global $wpdb;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();
        $table_prefix = $wpdb->prefix;

        $tables = [
            'content_regions' => "CREATE TABLE IF NOT EXISTS {$table_prefix}content_regions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                region_id INT NOT NULL UNIQUE
            ) $charset_collate;",

            'football_regions' => "CREATE TABLE IF NOT EXISTS {$table_prefix}football_regions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) UNIQUE NOT NULL,
                leagues TEXT NOT NULL
            ) $charset_collate;",

            'football_games' => "CREATE TABLE IF NOT EXISTS {$table_prefix}football_games (
                id BIGINT NOT NULL AUTO_INCREMENT,
                match_code VARCHAR(255),
                region VARCHAR(255),
                team VARCHAR(255),
                home VARCHAR(255),
                away VARCHAR(255),
                match_datetime DATETIME,
                time_zone VARCHAR(50),
                provider VARCHAR(100),
                odds TEXT,
                processed BOOLEAN DEFAULT 0,
                processed_started_at TIMESTAMP NULL,
                process_completed_at TIMESTAMP NULL,
                processed_failed_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY match_code (match_code)
            ) $charset_collate;"
        ];

        foreach ($tables as $table_name => $sql) {
            $result = dbDelta($sql);

            if ($result === false) {
                throw new Exception(sprintf(
                    /* translators: %s: table name */
                    esc_html__('Failed to create table: %s', 'ai-sports-writer'),
                    esc_html($table_name)
                ));
            }
        }

        update_option('sport_ai_writer_db_version', self::PLUGIN_VERSION);
    }

    /**
     * Logging method
     *
     * @param string $message Log message
     * @param string $level Log level
     */
    private function log(string $message, string $level = 'info'): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_message = sprintf(
            '[%s] [%s] %s',
            self::PLUGIN_DOMAIN,
            strtoupper($level),
            $message
        );

    }

    /**
     * Prevent cloning of the instance
     */
    private function __clone() {}

    /**
     * Prevent unserializing of the instance
     */
    public function __wakeup() {}
}