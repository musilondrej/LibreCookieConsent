<?php

namespace CCM;

class Plugin
{
    const DB_VERSION = '1.1';
    
    const TABLE_NAME = 'ccm_consent_log';

    const ALLOWED_CATEGORIES = ['necessary', 'analytics', 'marketing', 'functionality'];

    public function init()
    {
        add_action('init', [$this, 'check_db_version']);

        if (is_admin()) {
            $this->init_admin();
        } else {
            $this->init_frontend();
        }

        add_action('init', [$this, 'register_shortcodes']);
        add_action('rest_api_init', [self::class, 'register_rest_endpoints']);
        add_action('ccm_cleanup_consent_logs', [self::class, 'cleanup_old_consent_logs']);
    }

    public function check_db_version()
    {
        $current_version = get_option('ccm_db_version', '0');
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            $this->migrate_database($current_version);
        }
    }

    private function init_admin()
    {
        if (class_exists('CCM\\Admin\\SettingsPage')) {
            $settings_page = new Admin\SettingsPage();
            $settings_page->init();
        }
    }

    private function init_frontend()
    {
        if (class_exists('CCM\\Frontend\\Renderer')) {
            $renderer = new Frontend\Renderer();
            $renderer->init();
        }

        if (class_exists('CCM\\Frontend\\Services')) {
            Frontend\Services::boot();
        }
    }

    public function register_shortcodes()
    {
        add_shortcode('cookie_revisit', [$this, 'cookie_revisit_shortcode']);
    }

    public function cookie_revisit_shortcode($atts = [])
    {
        $atts = shortcode_atts([
            'text' => __('Změnit nastavení cookies', 'librecookiebar'),
            'class' => 'ccm-revisit-button'
        ], $atts, 'cookie_revisit');

        return sprintf(
            '<button class="%s" data-cc="show-preferencesModal">%s</button>',
            esc_attr($atts['class']),
            esc_html($atts['text'])
        );
    }

    public static function get_settings()
    {
        $defaults = [
            'ga4_id' => '',
            'meta_pixel_id' => '',
            'clarity_id' => '',
            'gtm_id' => '',
            'texts' => [
                'title' => __('Používáme cookies', 'librecookiebar'),
                'description' => __('Tento web používá soubory cookie pro zlepšení uživatelského zážitku a analýzu návštěvnosti.', 'librecookiebar'),
                'accept_all' => __('Přijmout vše', 'librecookiebar'),
                'accept_necessary' => __('Pouze nezbytné', 'librecookiebar'),
                'show_preferences' => __('Nastavení', 'librecookiebar')
            ],
            'ui_layout' => 'box',
            'ui_position' => 'bottom right',
            'ui_transition' => 'slide',
            'ui_flip_buttons' => false,
            'ui_equal_weight_buttons' => true,
            'custom_css' => '',
            'category_scripts' => [
                'analytics' => '',
                'marketing' => '',
                'functionality' => ''
            ],
            'force_consent' => false,
            'hide_from_bots' => true,
            'cookie_expiration' => 182,
            'cookies_to_erase' => '_ga,_gid,_gat,_gcl_,__utm,_fbp,fr,_uet,_ttp',
        ];

        $settings = get_option('ccm_settings', []);
        $settings = wp_parse_args($settings, $defaults);

        // Automatically determine mode based on GTM ID
        $settings['mode'] = ! empty($settings['gtm_id']) ? 'gtm' : 'direct';

        return $settings;
    }

    /**
     * Plugin activation hook
     */
    public static function activate_plugin()
    {
        self::ensure_secret_salt();

        // Schedule cleanup job only on activation
        if (! wp_next_scheduled('ccm_cleanup_consent_logs')) {
            wp_schedule_event(time(), 'daily', 'ccm_cleanup_consent_logs');
        }

        update_option('ccm_db_version', self::DB_VERSION);
    }

    /**
     * Plugin deactivation hook
     */
    public static function deactivate_plugin()
    {
        wp_clear_scheduled_hook('ccm_cleanup_consent_logs');
    }

    /**
     * Ensure secret salt exists for HMAC hashing
     */
    private static function ensure_secret_salt()
    {
        if (! get_option('ccm_secret_salt')) {
            $salt = bin2hex(random_bytes(32));
            add_option('ccm_secret_salt', $salt, '', false);
        }
    }

    /**
     * Versioned database migration system
     */
    private function migrate_database($from_version)
    {
        global $wpdb;

        // Always ensure table exists with correct schema
        self::create_consent_log_table();

        update_option('ccm_db_version', self::DB_VERSION);
    }


    /**
     * Create consent log table using dbDelta for proper schema management
     */
    private static function create_consent_log_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix.self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- dbDelta doesn't support prepared statements, $wpdb->prefix is safe
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            consent_hash varchar(64) NOT NULL,
            categories text NOT NULL,
            version_hash varchar(64) DEFAULT '',
            source enum('accept','change') NOT NULL DEFAULT 'accept',
            PRIMARY KEY (id),
            KEY consent_hash (consent_hash),
            KEY created_at (created_at)
        ) {$charset_collate};";
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Register REST API endpoints
     */
    public static function register_rest_endpoints()
    {
        register_rest_route('eccm/v1', '/consent', [
            'methods' => 'POST',
            'callback' => [self::class, 'handle_consent_rest'],
            'permission_callback' => '__return_true', // Anonymous consent logging
            'args' => [
                'consent_id' => [
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => function ($param) {
                        return is_string($param) && preg_match('/^[a-f0-9]{64}$/', $param);
                    },
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'categories' => [
                    'required' => true,
                    'type' => 'array',
                    'validate_callback' => [self::class, 'validate_categories'],
                    'sanitize_callback' => [self::class, 'sanitize_categories']
                ],
                'version_hash' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => '1.0',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'source' => [
                    'required' => false,
                    'type' => 'string',
                    'enum' => ['accept', 'change'],
                    'default' => 'accept',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
    }

    /**
     * Validate categories against whitelist
     */
    public static function validate_categories($categories)
    {
        if (! is_array($categories)) {
            return false;
        }

        foreach ($categories as $category) {
            if (! in_array($category, self::ALLOWED_CATEGORIES, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize categories array
     */
    public static function sanitize_categories($categories)
    {
        if (! is_array($categories)) {
            return [];
        }

        $sanitized = array_map('sanitize_text_field', $categories);
        return array_intersect($sanitized, self::ALLOWED_CATEGORIES);
    }

    /**
     * Handle consent REST API request
     */
    public static function handle_consent_rest($request)
    {
        global $wpdb;
        $table_name = $wpdb->prefix.self::TABLE_NAME;

        $consent_id = $request->get_param('consent_id');
        $categories = $request->get_param('categories');
        $version_hash = $request->get_param('version_hash') ?: '';
        $source = $request->get_param('source') ?: 'accept';

        $salt = get_option('ccm_secret_salt');
        if (! $salt) {
            return new \WP_Error('config_error', 'Plugin not properly activated', ['status' => 500]);
        }

        $consent_hash = hash_hmac('sha256', $consent_id, $salt);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom consent logging table
        $result = $wpdb->insert(
            $table_name,
            [
                'created_at' => current_time('mysql', true),
                'consent_hash' => $consent_hash,
                'categories' => wp_json_encode($categories),
                'version_hash' => $version_hash,
                'source' => $source
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            return new \WP_Error('db_error', 'Database error occurred', ['status' => 500]);
        }

        // Invalidate cache after successful insert
        wp_cache_delete('ccm_consent_log_count', 'ccm');

        return rest_ensure_response([
            'success' => true,
            'message' => 'Consent logged successfully'
        ]);
    }
    
    /**
     * Cleanup old consent logs based on retention policy
     */
    public static function cleanup_old_consent_logs(): void
    {
        global $wpdb;

        if (empty($wpdb->ccm_consent_log)) {
            $wpdb->ccm_consent_log = $wpdb->prefix.self::TABLE_NAME;
        }

        $retention_months = (int) get_option('ccm_retention_months', 12);
        $cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$retention_months} months"));

        $deleted_rows = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `{$wpdb->ccm_consent_log}` WHERE created_at < %s",
                $cutoff_date
            )
        );

        if ($deleted_rows > 0) {
            wp_cache_delete('ccm_consent_log_count', 'ccm');
        }
    }
}