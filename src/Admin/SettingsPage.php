<?php

namespace CCM\Admin;

class SettingsPage
{
    private $option_group = 'ccm_settings';
    private $option_name = 'ccm_settings';
    private $page_slug = 'eccm-main';
    private $logs_slug = 'eccm-consent-log';

    public function init(): void
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_post_ccm_export_logs', [$this, 'handle_export_logs']);
        add_action('admin_post_ccm_clear_logs', [$this, 'handle_clear_logs']);
    }

    public function add_admin_menu(): void
    {
        add_menu_page(
            __('Cookies Consent', 'librecookiebar'),
            __('Cookies Consent', 'librecookiebar'),
            'manage_options',
            $this->page_slug,
            [$this, 'render_settings_page'],
            'dashicons-shield-alt',
            30
        );

        add_submenu_page(
            $this->page_slug,
            __('Nastavení Cookies', 'librecookiebar'),
            __('Nastavení', 'librecookiebar'),
            'manage_options',
            $this->page_slug,
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            $this->page_slug,
            __('Přehled souhlasů', 'librecookiebar'),
            __('Přehled souhlasů', 'librecookiebar'),
            'manage_options',
            $this->logs_slug,
            [$this, 'render_consent_log_page']
        );
    }

    public function enqueue_admin_assets($hook): void
    {
        $allowed_pages = [$this->page_slug, $this->logs_slug];
        if (! in_array($_GET['page'] ?? '', $allowed_pages, true)) {
            return;
        }

        $ver = $this->plugin_version();

        wp_enqueue_style(
            'eccm-admin-modern',
            defined('CCM_PLUGIN_URL') ? (CCM_PLUGIN_URL.'assets/css/admin-modern.css') : '',
            [],
            $ver
        );

        wp_enqueue_script(
            'eccm-admin-modern',
            defined('CCM_PLUGIN_URL') ? (CCM_PLUGIN_URL.'assets/js/admin-modern.js') : '',
            ['jquery'],
            $ver,
            true
        );

        wp_localize_script('eccm-admin-modern', 'ccm_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ccm_admin_nonce'),
        ]);
    }

    private function plugin_version(): string
    {
        return defined('CCM_PLUGIN_VERSION') ? (string) CCM_PLUGIN_VERSION : '1.0.0';
    }

    public function register_settings(): void
    {
        register_setting(
            $this->option_group,
            $this->option_name,
            ['sanitize_callback' => [$this, 'sanitize_settings']]
        );

        // Sekce
        add_settings_section('ccm_general', __('Obecné nastavení', 'librecookiebar'), [$this, 'render_general_section'], $this->page_slug);
        add_settings_section('ccm_services', __('Služby třetích stran', 'librecookiebar'), [$this, 'render_services_section'], $this->page_slug);
        add_settings_section('ccm_texts', __('Texty cookie lišty', 'librecookiebar'), [$this, 'render_texts_section'], $this->page_slug);
        add_settings_section('ccm_ui', __('Přizpůsobení vzhledu', 'librecookiebar'), [$this, 'render_ui_section'], $this->page_slug);
        add_settings_section('ccm_scripts', __('Správa skriptů', 'librecookiebar'), [$this, 'render_category_scripts_section'], $this->page_slug);
        add_settings_section('ccm_advanced', __('Pokročilé nastavení', 'librecookiebar'), [$this, 'render_advanced_section'], $this->page_slug);

        add_settings_field('config_info', __('Aktuální konfigurace', 'librecookiebar'), [$this, 'render_config_info_field'], $this->page_slug, 'ccm_general');

        $services = [
            'gtm_id' => 'Google Tag Manager Container ID',
            'ga4_id' => 'Google Analytics 4 Measurement ID',
            'meta_pixel_id' => 'Meta Pixel ID',
            'clarity_id' => 'Microsoft Clarity Project ID',
        ];
        foreach ($services as $field => $label) {
            add_settings_field($field, $label, [$this, 'render_service_field'], $this->page_slug, 'ccm_services', ['field' => $field, 'label' => $label]);
        }

        $texts = [
            'title' => __('Nadpis', 'librecookiebar'),
            'description' => __('Popis', 'librecookiebar'),
            'accept_all' => __('Tlačítko „Přijmout vše"', 'librecookiebar'),
            'accept_necessary' => __('Tlačítko „Pouze nezbytné"', 'librecookiebar'),
            'show_preferences' => __('Tlačítko „Nastavení"', 'librecookiebar'),
            'preferences_title' => __('Nadpis preferences modalu', 'librecookiebar'),
            'save_preferences' => __('Tlačítko „Uložit nastavení"', 'librecookiebar'),
            'necessary_title' => __('Nezbytné cookies - nadpis', 'librecookiebar'),
            'necessary_description' => __('Nezbytné cookies - popis', 'librecookiebar'),
            'analytics_title' => __('Analytické cookies - nadpis', 'librecookiebar'),
            'analytics_description' => __('Analytické cookies - popis', 'librecookiebar'),
            'marketing_title' => __('Marketingové cookies - nadpis', 'librecookiebar'),
            'marketing_description' => __('Marketingové cookies - popis', 'librecookiebar'),
            'functionality_title' => __('Funkční cookies - nadpis', 'librecookiebar'),
            'functionality_description' => __('Funkční cookies - popis', 'librecookiebar'),
        ];
        foreach ($texts as $field => $label) {
            add_settings_field('text_'.$field, $label, [$this, 'render_text_field'], $this->page_slug, 'ccm_texts', ['field' => $field]);
        }

        add_settings_field('ui_layout', __('Layout banneru', 'librecookiebar'), [$this, 'render_ui_layout_field'], $this->page_slug, 'ccm_ui');
        add_settings_field('ui_position', __('Pozice banneru', 'librecookiebar'), [$this, 'render_ui_position_field'], $this->page_slug, 'ccm_ui');
        add_settings_field('ui_transition', __('Animace', 'librecookiebar'), [$this, 'render_ui_transition_field'], $this->page_slug, 'ccm_ui');
        add_settings_field('ui_flip_buttons', __('Prohodit pořadí tlačítek', 'librecookiebar'), [$this, 'render_ui_flip_buttons_field'], $this->page_slug, 'ccm_ui');
        add_settings_field('ui_equal_weight_buttons', __('Stejná šířka tlačítek', 'librecookiebar'), [$this, 'render_ui_equal_weight_buttons_field'], $this->page_slug, 'ccm_ui');
        add_settings_field('custom_css', __('Vlastní CSS', 'librecookiebar'), [$this, 'render_custom_css_field'], $this->page_slug, 'ccm_ui');

        add_settings_field('category_scripts', __('Skripty podle kategorií', 'librecookiebar'), [$this, 'render_category_scripts_field'], $this->page_slug, 'ccm_scripts');

        add_settings_field('force_consent', __('Vynutit souhlas', 'librecookiebar'), [$this, 'render_force_consent_field'], $this->page_slug, 'ccm_advanced');
        add_settings_field('hide_from_bots', __('Skrýt před boty', 'librecookiebar'), [$this, 'render_hide_from_bots_field'], $this->page_slug, 'ccm_advanced');
        add_settings_field('cookie_expiration', __('Expirace cookie (dny)', 'librecookiebar'), [$this, 'render_cookie_expiration_field'], $this->page_slug, 'ccm_advanced');
        add_settings_field('retention_months', __('Doba uchovávání auditních záznamů (měsíce)', 'librecookiebar'), [$this, 'render_retention_months_field'], $this->page_slug, 'ccm_advanced');
        add_settings_field('cookies_to_erase', __('Cookies k automatickému mazání', 'librecookiebar'), [$this, 'render_cookies_to_erase_field'], $this->page_slug, 'ccm_advanced');
    }

    public function sanitize_settings($input): array
    {
        if (! is_array($input)) {
            return [];
        }

        $san = [];

        $basic_fields = ['gtm_id', 'ga4_id', 'meta_pixel_id', 'clarity_id'];
        foreach ($basic_fields as $f) {
            $san[$f] = sanitize_text_field($input[$f] ?? '');
        }

        $san['texts'] = [];
        
        $text_defaults = [
            'title' => 'Používáme cookies',
            'description' => 'Tento web používá soubory cookie ke zlepšení vašeho zážitku z prohlížení.',
            'accept_all' => 'Přijmout vše',
            'accept_necessary' => 'Pouze nezbytné',
            'show_preferences' => 'Nastavení',
            'preferences_title' => 'Nastavení cookies',
            'save_preferences' => 'Uložit nastavení',
            'necessary_title' => 'Nezbytné cookies',
            'necessary_description' => 'Tyto soubory cookie jsou nezbytné pro základní funkčnost webu.',
            'analytics_title' => 'Analytické cookies',
            'analytics_description' => 'Pomáhají nám pochopit, jak návštěvníci používají náš web.',
            'marketing_title' => 'Marketingové cookies', 
            'marketing_description' => 'Používají se pro zobrazování relevantních reklam.',
            'functionality_title' => 'Funkční cookies',
            'functionality_description' => 'Umožňují pokročilé funkce webu.',
        ];
        
        $text_fields = ['title', 'description', 'accept_all', 'accept_necessary', 'show_preferences', 'preferences_title', 'save_preferences', 'necessary_title', 'necessary_description', 'analytics_title', 'analytics_description', 'marketing_title', 'marketing_description', 'functionality_title', 'functionality_description'];
        foreach ($text_fields as $f) {
            $default_value = $text_defaults[$f] ?? '';
            if (in_array($f, ['description', 'necessary_description', 'analytics_description', 'marketing_description', 'functionality_description'])) {
                $san['texts'][$f] = wp_kses_post($input['texts'][$f] ?? $default_value);
            } else {
                $san['texts'][$f] = sanitize_text_field($input['texts'][$f] ?? $default_value);
            }
        }

        $san['ui_layout'] = in_array(($input['ui_layout'] ?? 'box'), ['box', 'cloud', 'bar'], true) ? $input['ui_layout'] : 'box';
        $san['ui_position'] = in_array(($input['ui_position'] ?? 'bottom'), ['bottom', 'top', 'middle', 'bottom-left', 'bottom-right', 'top-left', 'top-right'], true) ? $input['ui_position'] : 'bottom';
        $san['ui_transition'] = in_array(($input['ui_transition'] ?? 'slide'), ['slide', 'fade', 'zoom'], true) ? $input['ui_transition'] : 'slide';
        $san['ui_flip_buttons'] = ! empty($input['ui_flip_buttons']);
        $san['ui_equal_weight_buttons'] = ! empty($input['ui_equal_weight_buttons']);
        $san['custom_css'] = isset($input['custom_css']) ? wp_strip_all_tags((string) $input['custom_css']) : '';

        $san['category_scripts'] = [];
        $cats = ['analytics', 'marketing', 'functional'];
        foreach ($cats as $cat) {
            $san['category_scripts'][$cat] = isset($input['category_scripts'][$cat]) ? wp_kses_post($input['category_scripts'][$cat]) : '';
        }

        $san['force_consent'] = ! empty($input['force_consent']);
        $san['hide_from_bots'] = ! empty($input['hide_from_bots']);
        $san['cookie_expiration'] = isset($input['cookie_expiration']) ? max(1, min(3650, (int) $input['cookie_expiration'])) : 182;
        $san['retention_months'] = isset($input['retention_months']) ? max(1, min(120, (int) $input['retention_months'])) : 12;
        $san['cookies_to_erase'] = isset($input['cookies_to_erase']) ? sanitize_textarea_field($input['cookies_to_erase']) : '_ga,_gid,_gat,_gcl_,__utm,_fbp,fr,_uet,_ttp,_pin_';

        return $san;
    }

    public function render_settings_page(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Nemáte oprávnění k přístupu k této stránce.', 'librecookiebar'));
        }

        $active_tab = $_GET['tab'] ?? 'services';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=<?php echo esc_attr($this->page_slug); ?>&tab=services" class="nav-tab <?php echo $active_tab === 'services' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Služby', 'librecookiebar'); ?>
                </a>
                <a href="?page=<?php echo esc_attr($this->page_slug); ?>&tab=texts" class="nav-tab <?php echo $active_tab === 'texts' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Texty', 'librecookiebar'); ?>
                </a>
                <a href="?page=<?php echo esc_attr($this->page_slug); ?>&tab=ui" class="nav-tab <?php echo $active_tab === 'ui' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Vzhled', 'librecookiebar'); ?>
                </a>
                <a href="?page=<?php echo esc_attr($this->page_slug); ?>&tab=scripts" class="nav-tab <?php echo $active_tab === 'scripts' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Skripty', 'librecookiebar'); ?>
                </a>
                <a href="?page=<?php echo esc_attr($this->page_slug); ?>&tab=advanced" class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Pokročilé', 'librecookiebar'); ?>
                </a>
            </h2>

            <form method="post" action="options.php" class="settings-form">
                <?php settings_fields($this->option_group); ?>
                
                <?php $this->render_tab_content($active_tab); ?>

                <?php submit_button(__('Uložit nastavení', 'librecookiebar')); ?>
            </form>
        </div>
        <?php
    }

    private function render_tab_content(string $active_tab): void
    {
        switch ($active_tab) {
            case 'services':
                $this->render_services_tab();
                break;
            case 'texts':
                $this->render_texts_tab();
                break;
            case 'ui':
                $this->render_ui_tab();
                break;
            case 'scripts':
                $this->render_scripts_tab();
                break;
            case 'advanced':
                $this->render_advanced_tab();
                break;
            default:
                $this->render_services_tab();
        }
    }

    private function render_services_tab(): void
    {
        ?>
        <div class="metabox-holder">
            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle ui-sortable-handle"><?php esc_html_e('Služby třetích stran', 'librecookiebar'); ?></h2>
                </div>
                <div class="inside">
                    <p><?php esc_html_e('Nastavení ID pro analytické a marketingové služby.', 'librecookiebar'); ?></p>
                    <table class="form-table" role="presentation">
                        <?php
                        $services = [
                            'gtm_id' => 'Google Tag Manager Container ID',
                            'ga4_id' => 'Google Analytics 4 Measurement ID',
                            'meta_pixel_id' => 'Meta Pixel ID',
                            'clarity_id' => 'Microsoft Clarity Project ID',
                        ];
                        foreach ($services as $field => $label) {
                            $this->render_service_field_row($field, $label);
                        }
                        ?>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_texts_tab(): void
    {
        ?>
        <div class="metabox-holder">
            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle ui-sortable-handle"><?php esc_html_e('Hlavní cookie lišta', 'librecookiebar'); ?></h2>
                </div>
                <div class="inside">
                    <p><?php esc_html_e('Texty zobrazované v hlavní cookie liště na webové stránce.', 'librecookiebar'); ?></p>
                    <table class="form-table" role="presentation">
                        <?php
                        $main_texts = [
                            'title' => __('Nadpis', 'librecookiebar'),
                            'description' => __('Popis', 'librecookiebar'),
                        ];
                        foreach ($main_texts as $field => $label) {
                            $this->render_text_field_row($field, $label);
                        }
                        ?>
                    </table>
                </div>
            </div>

            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle ui-sortable-handle"><?php esc_html_e('Tlačítka v cookie liště', 'librecookiebar'); ?></h2>
                </div>
                <div class="inside">
                    <p><?php esc_html_e('Texty tlačítek pro správu souhlasů v hlavní cookie liště.', 'librecookiebar'); ?></p>
                    <table class="form-table" role="presentation">
                        <?php
                        $button_texts = [
                            'accept_all' => __('Tlačítko „Přijmout vše"', 'librecookiebar'),
                            'accept_necessary' => __('Tlačítko „Pouze nezbytné"', 'librecookiebar'),
                            'show_preferences' => __('Tlačítko „Nastavení"', 'librecookiebar'),
                        ];
                        foreach ($button_texts as $field => $label) {
                            $this->render_text_field_row($field, $label);
                        }
                        ?>
                    </table>
                </div>
            </div>

            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle ui-sortable-handle"><?php esc_html_e('Nastavení cookies (modal)', 'librecookiebar'); ?></h2>
                </div>
                <div class="inside">
                    <p><?php esc_html_e('Texty zobrazované v detailním okně pro správu cookie preferencí.', 'librecookiebar'); ?></p>
                    <table class="form-table" role="presentation">
                        <?php
                        $modal_texts = [
                            'preferences_title' => __('Nadpis preferences modalu', 'librecookiebar'),
                            'save_preferences' => __('Tlačítko „Uložit nastavení"', 'librecookiebar'),
                        ];
                        foreach ($modal_texts as $field => $label) {
                            $this->render_text_field_row($field, $label);
                        }
                        ?>
                    </table>
                </div>
            </div>

            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle ui-sortable-handle"><?php esc_html_e('Kategorie cookies', 'librecookiebar'); ?></h2>
                </div>
                <div class="inside">
                    <p><?php esc_html_e('Názvy a popisy jednotlivých kategorií cookies v preferences modalu.', 'librecookiebar'); ?></p>
                    
                    <h3><?php esc_html_e('Nezbytné cookies', 'librecookiebar'); ?></h3>
                    <table class="form-table" role="presentation">
                        <?php
                        $necessary_texts = [
                            'necessary_title' => __('Nezbytné cookies - nadpis', 'librecookiebar'),
                            'necessary_description' => __('Nezbytné cookies - popis', 'librecookiebar'),
                        ];
                        foreach ($necessary_texts as $field => $label) {
                            $this->render_text_field_row($field, $label);
                        }
                        ?>
                    </table>

                    <h3><?php esc_html_e('Analytické cookies', 'librecookiebar'); ?></h3>
                    <table class="form-table" role="presentation">
                        <?php
                        $analytics_texts = [
                            'analytics_title' => __('Analytické cookies - nadpis', 'librecookiebar'),
                            'analytics_description' => __('Analytické cookies - popis', 'librecookiebar'),
                        ];
                        foreach ($analytics_texts as $field => $label) {
                            $this->render_text_field_row($field, $label);
                        }
                        ?>
                    </table>

                    <h3><?php esc_html_e('Marketingové cookies', 'librecookiebar'); ?></h3>
                    <table class="form-table" role="presentation">
                        <?php
                        $marketing_texts = [
                            'marketing_title' => __('Marketingové cookies - nadpis', 'librecookiebar'),
                            'marketing_description' => __('Marketingové cookies - popis', 'librecookiebar'),
                        ];
                        foreach ($marketing_texts as $field => $label) {
                            $this->render_text_field_row($field, $label);
                        }
                        ?>
                    </table>

                    <h3><?php esc_html_e('Funkční cookies', 'librecookiebar'); ?></h3>
                    <table class="form-table" role="presentation">
                        <?php
                        $functionality_texts = [
                            'functionality_title' => __('Funkční cookies - nadpis', 'librecookiebar'),
                            'functionality_description' => __('Funkční cookies - popis', 'librecookiebar'),
                        ];
                        foreach ($functionality_texts as $field => $label) {
                            $this->render_text_field_row($field, $label);
                        }
                        ?>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_ui_tab(): void
    {
        ?>
        <div class="metabox-holder">
            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle ui-sortable-handle"><?php esc_html_e('Přizpůsobení vzhledu', 'librecookiebar'); ?></h2>
                </div>
                <div class="inside">
                    <p><?php esc_html_e('Nastavte pozici, styl a vzhled cookie lišty.', 'librecookiebar'); ?></p>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Layout banneru', 'librecookiebar'); ?></th>
                            <td><?php $this->render_ui_layout_field(); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Pozice banneru', 'librecookiebar'); ?></th>
                            <td><?php $this->render_ui_position_field(); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Animace', 'librecookiebar'); ?></th>
                            <td><?php $this->render_ui_transition_field(); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Prohodit pořadí tlačítek', 'librecookiebar'); ?></th>
                            <td><?php $this->render_ui_flip_buttons_field(); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Stejná šířka tlačítek', 'librecookiebar'); ?></th>
                            <td><?php $this->render_ui_equal_weight_buttons_field(); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Vlastní CSS', 'librecookiebar'); ?></th>
                            <td><?php $this->render_custom_css_field(); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_scripts_tab(): void
    {
        ?>
        <div class="metabox-holder">
            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle ui-sortable-handle"><?php esc_html_e('Správa skriptů', 'librecookiebar'); ?></h2>
                </div>
                <div class="inside">
                    <p><?php esc_html_e('Definujte HTML skripty pro jednotlivé kategorie cookies.', 'librecookiebar'); ?></p>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Skripty podle kategorií', 'librecookiebar'); ?></th>
                            <td><?php $this->render_category_scripts_field(); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_advanced_tab(): void
    {
        ?>
        <div class="metabox-holder">
            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle ui-sortable-handle"><?php esc_html_e('Pokročilé nastavení', 'librecookiebar'); ?></h2>
                </div>
                <div class="inside">
                    <p><?php esc_html_e('Pokročilé možnosti konfigurace pro administrátory.', 'librecookiebar'); ?></p>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Vynutit souhlas', 'librecookiebar'); ?></th>
                            <td><?php $this->render_force_consent_field(); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Skrýt před boty', 'librecookiebar'); ?></th>
                            <td><?php $this->render_hide_from_bots_field(); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Expirace cookie (dny)', 'librecookiebar'); ?></th>
                            <td><?php $this->render_cookie_expiration_field(); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Doba uchovávání auditních záznamů (měsíce)', 'librecookiebar'); ?></th>
                            <td><?php $this->render_retention_months_field(); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Cookies k automatickému mazání', 'librecookiebar'); ?></th>
                            <td><?php $this->render_cookies_to_erase_field(); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_config_info_field(): void
    {
        $s = \CCM\Plugin::get_settings();
        $mode = ! empty($s['gtm_id']) ? 'GTM' : 'Direct';
        ?>
        <p><strong><?php esc_html_e('Režim:', 'librecookiebar'); ?></strong> <?php echo esc_html($mode); ?></p>
        <p><em><?php echo $mode === 'GTM' ? esc_html__('Google Consent Mode v2 aktivní', 'librecookiebar') : esc_html__('Přímé řízení skriptů', 'librecookiebar'); ?></em></p>
        <?php
    }

    public function render_service_field(array $args): void
    {
        $s = \CCM\Plugin::get_settings();
        $field = $args['field'];
        $value = $s[$field] ?? '';
        ?>
        <input type="text" name="<?php echo esc_attr($this->option_name); ?>[<?php echo esc_attr($field); ?>]" value="<?php echo esc_attr($value); ?>" class="regular-text"/>
        <?php
    }

    public function render_text_field(array $args): void
    {
        $s = \CCM\Plugin::get_settings();
        $field = $args['field'];
        
        $defaults = [
            'title' => 'Používáme cookies',
            'description' => 'Tento web používá soubory cookie ke zlepšení vašeho zážitku z prohlížení.',
            'accept_all' => 'Přijmout vše',
            'accept_necessary' => 'Pouze nezbytné',
            'show_preferences' => 'Nastavení',
            'preferences_title' => 'Nastavení cookies',
            'save_preferences' => 'Uložit nastavení',
            'necessary_title' => 'Nezbytné cookies',
            'necessary_description' => 'Tyto soubory cookie jsou nezbytné pro základní funkčnost webu.',
            'analytics_title' => 'Analytické cookies',
            'analytics_description' => 'Pomáhají nám pochopit, jak návštěvníci používají náš web.',
            'marketing_title' => 'Marketingové cookies', 
            'marketing_description' => 'Používají se pro zobrazování relevantních reklam.',
            'functionality_title' => 'Funkční cookies',
            'functionality_description' => 'Umožňují pokročilé funkce webu.',
        ];
        
        $value = $s['texts'][$field] ?? $defaults[$field] ?? '';

        if (in_array($field, ['description', 'necessary_description', 'analytics_description', 'marketing_description', 'functionality_description'])) {
            ?>
            <textarea name="<?php echo esc_attr($this->option_name); ?>[texts][<?php echo esc_attr($field); ?>]" rows="3" cols="50" class="large-text"><?php echo esc_textarea($value); ?></textarea>
            <?php
            return;
        }
        ?>
        <input type="text" name="<?php echo esc_attr($this->option_name); ?>[texts][<?php echo esc_attr($field); ?>]" value="<?php echo esc_attr($value); ?>" class="large-text"/>
        <?php
    }

    public function render_ui_layout_field(): void
    {
        $s = get_option($this->option_name, []);
        $value = $s['ui_layout'] ?? 'box';
        $opts = [
            'box' => __('Box (rámeček)', 'librecookiebar'),
            'cloud' => __('Cloud (oblak)', 'librecookiebar'),
            'bar' => __('Bar (lišta)', 'librecookiebar'),
        ];
        ?>
        <select name="<?php echo esc_attr($this->option_name); ?>[ui_layout]">
            <?php foreach ($opts as $k => $label) : ?>
                <option value="<?php echo esc_attr($k); ?>" <?php selected($value, $k); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function render_ui_position_field(): void
    {
        $s = get_option($this->option_name, []);
        $value = $s['ui_position'] ?? 'bottom';
        $opts = [
            'top' => __('Nahoře', 'librecookiebar'),
            'bottom' => __('Dole', 'librecookiebar'),
            'middle' => __('Uprostřed', 'librecookiebar'),
            'top-left' => __('Nahoře vlevo', 'librecookiebar'),
            'top-right' => __('Nahoře vpravo', 'librecookiebar'),
            'bottom-left' => __('Dole vlevo', 'librecookiebar'),
            'bottom-right' => __('Dole vpravo', 'librecookiebar'),
        ];
        ?>
        <select name="<?php echo esc_attr($this->option_name); ?>[ui_position]">
            <?php foreach ($opts as $k => $label) : ?>
                <option value="<?php echo esc_attr($k); ?>" <?php selected($value, $k); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function render_ui_transition_field(): void
    {
        $s = get_option($this->option_name, []);
        $value = $s['ui_transition'] ?? 'slide';
        $opts = [
            'slide' => __('Slide (posouvání)', 'librecookiebar'),
            'fade' => __('Fade (prolínání)', 'librecookiebar'),
            'zoom' => __('Zoom (přiblížení)', 'librecookiebar'),
        ];
        ?>
        <select name="<?php echo esc_attr($this->option_name); ?>[ui_transition]">
            <?php foreach ($opts as $k => $label) : ?>
                <option value="<?php echo esc_attr($k); ?>" <?php selected($value, $k); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function render_ui_flip_buttons_field(): void
    {
        $s = get_option($this->option_name, []);
        $value = ! empty($s['ui_flip_buttons']);
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[ui_flip_buttons]" value="1" <?php checked($value, true); ?>/>
            <?php esc_html_e('Prohodit pořadí tlačítek', 'librecookiebar'); ?>
        </label>
        <?php
    }

    public function render_ui_equal_weight_buttons_field(): void
    {
        $s = get_option($this->option_name, []);
        $value = ! empty($s['ui_equal_weight_buttons']);
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[ui_equal_weight_buttons]" value="1" <?php checked($value, true); ?>/>
            <?php esc_html_e('Stejná šířka tlačítek', 'librecookiebar'); ?>
        </label>
        <?php
    }

    public function render_custom_css_field(): void
    {
        $s = get_option($this->option_name, []);
        $value = $s['custom_css'] ?? '';
        ?>
        <textarea name="<?php echo esc_attr($this->option_name); ?>[custom_css]" rows="10" cols="50" class="large-text code"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php esc_html_e('Vlastní CSS styl pro cookie lištu.', 'librecookiebar'); ?></p>
        <?php
    }

    public function render_category_scripts_field(): void
    {
        $s = get_option($this->option_name, []);
        $categories = ['analytics', 'marketing', 'functional'];
        $labels = [
            'analytics' => __('Analytické', 'librecookiebar'),
            'marketing' => __('Marketingové', 'librecookiebar'),
            'functional' => __('Funkční', 'librecookiebar'),
        ];

        foreach ($categories as $cat) {
            $value = $s['category_scripts'][$cat] ?? '';
            ?>
            <h4><?php echo esc_html($labels[$cat]); ?></h4>
            <textarea name="<?php echo esc_attr($this->option_name); ?>[category_scripts][<?php echo esc_attr($cat); ?>]" rows="5" cols="50" class="large-text code"><?php echo esc_textarea($value); ?></textarea>
            <br><br>
            <?php
        }
        ?>
        <p class="description"><?php esc_html_e('Vložte HTML kód (skripty, pixel kódy) pro jednotlivé kategorie.', 'librecookiebar'); ?></p>
        <?php
    }

    public function render_force_consent_field(): void
    {
        $s = get_option($this->option_name, []);
        $value = ! empty($s['force_consent']);
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[force_consent]" value="1" <?php checked($value, true); ?>/>
            <?php esc_html_e('Vynutit souhlas (zamezit používání webu bez rozhodnutí)', 'librecookiebar'); ?>
        </label>
        <?php
    }

    public function render_hide_from_bots_field(): void
    {
        $s = get_option($this->option_name, []);
        $value = ! empty($s['hide_from_bots']);
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[hide_from_bots]" value="1" <?php checked($value, true); ?>/>
            <?php esc_html_e('Skrýt cookie lištu před boty a crawlery', 'librecookiebar'); ?>
        </label>
        <?php
    }

    public function render_cookie_expiration_field(): void
    {
        $s = get_option($this->option_name, []);
        $value = $s['cookie_expiration'] ?? 182;
        ?>
        <input type="number" name="<?php echo esc_attr($this->option_name); ?>[cookie_expiration]" value="<?php echo esc_attr($value); ?>" min="1" max="3650" class="small-text"/>
        <p class="description"><?php esc_html_e('Počet dní, po které bude souhlas platný (1-3650 dní).', 'librecookiebar'); ?></p>
        <?php
    }

    public function render_retention_months_field(): void
    {
        $s = get_option($this->option_name, []);
        $value = $s['retention_months'] ?? 12;
        ?>
        <input type="number" name="<?php echo esc_attr($this->option_name); ?>[retention_months]" value="<?php echo esc_attr($value); ?>" min="1" max="120" class="small-text"/>
        <p class="description"><?php esc_html_e('Počet měsíců uchovávání auditních záznamů (1-120 měsíců).', 'librecookiebar'); ?></p>
        <?php
    }

    public function render_cookies_to_erase_field(): void
    {
        $s = get_option($this->option_name, []);
        $value = $s['cookies_to_erase'] ?? '_ga,_gid,_gat,_gcl_,__utm,_fbp,fr,_uet,_ttp,_pin_';
        ?>
        <textarea name="<?php echo esc_attr($this->option_name); ?>[cookies_to_erase]" rows="3" cols="50" class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php esc_html_e('Cookies k automatickému mazání při odmítnutí (oddělené čárkami).', 'librecookiebar'); ?></p>
        <?php
    }

    public function render_consent_log_page(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Nemáte oprávnění k přístupu k této stránce.', 'librecookiebar'));
        }

        global $wpdb;
        $per_page = 25;
        $page = max(1, (int) ($_GET['paged'] ?? 1));
        $offset = ($page - 1) * $per_page;
        $total = $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->ccm_consent_log}`");
        if ($total === null) $total = 0;
        $total_pages = max(1, (int) ceil($total / $per_page));

        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT id, created_at, consent_hash, categories, version_hash, source FROM `{$wpdb->ccm_consent_log}` ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        ));

        $export_url = wp_nonce_url(admin_url('admin-post.php?action=ccm_export_logs'), 'ccm_export_logs');
        $clear_url = wp_nonce_url(admin_url('admin-post.php?action=ccm_clear_logs'), 'ccm_clear_logs');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Přehled souhlasů', 'librecookiebar'); ?></h1>
            <p><?php printf(esc_html__('Celkem záznamů: %d', 'librecookiebar'), (int) $total); ?></p>
            <p>
                <a href="<?php echo esc_url($export_url); ?>" class="button"><?php esc_html_e('Exportovat CSV', 'librecookiebar'); ?></a>
                <a href="<?php echo esc_url($clear_url); ?>" class="button button-secondary" onclick="return confirm('<?php echo esc_js(__('Opravdu chcete smazat všechny záznamy?', 'librecookiebar')); ?>')"><?php esc_html_e('Smazat všechny záznamy', 'librecookiebar'); ?></a>
            </p>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th scope="col"><?php esc_html_e('ID', 'librecookiebar'); ?></th>
                    <th scope="col"><?php esc_html_e('Datum', 'librecookiebar'); ?></th>
                    <th scope="col"><?php esc_html_e('Consent Hash', 'librecookiebar'); ?></th>
                    <th scope="col"><?php esc_html_e('Kategorie', 'librecookiebar'); ?></th>
                    <th scope="col"><?php esc_html_e('Version Hash', 'librecookiebar'); ?></th>
                    <th scope="col"><?php esc_html_e('Zdroj', 'librecookiebar'); ?></th>
                </tr></thead>
                <tbody>
                    <?php if (! empty($logs)) : ?>
                        <?php foreach ($logs as $log) : ?>
                            <tr>
                                <td><?php echo esc_html($log->id); ?></td>
                                <td><?php echo esc_html($log->created_at); ?></td>
                                <td><code><?php echo esc_html(substr($log->consent_hash, 0, 16)); ?>...</code></td>
                                <td><?php echo esc_html($log->categories); ?></td>
                                <td><code><?php echo esc_html(substr($log->version_hash, 0, 16)); ?>...</code></td>
                                <td><?php echo esc_html($log->source); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="6"><?php esc_html_e('Žádné záznamy nenalezeny.', 'librecookiebar'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function handle_export_logs(): void
    {
        if (! current_user_can('manage_options') || ! wp_verify_nonce($_GET['_wpnonce'] ?? '', 'ccm_export_logs')) {
            wp_die(esc_html__('Nemáte oprávnění.', 'librecookiebar'));
        }

        global $wpdb;
        $logs = $wpdb->get_results("SELECT * FROM `{$wpdb->ccm_consent_log}` ORDER BY created_at DESC");

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ccm-consent-log-'.date('Y-m-d').'.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Created At', 'Consent Hash', 'Categories', 'Version Hash', 'Source']);
        foreach ($logs as $log) {
            // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
            fputcsv($output, [$log->id, $log->created_at, $log->consent_hash, $log->categories, $log->version_hash, $log->source]);
            // phpcs:enable
        }
        fclose($output);
        exit;
    }

    public function handle_clear_logs(): void
    {
        if (! current_user_can('manage_options') || ! wp_verify_nonce($_GET['_wpnonce'] ?? '', 'ccm_clear_logs')) {
            wp_die(esc_html__('Nemáte oprávnění.', 'librecookiebar'));
        }

        global $wpdb;
        $safe = esc_sql($wpdb->ccm_consent_log);
        $wpdb->query("TRUNCATE TABLE `{$safe}`");

        add_settings_error('ccm_messages', 'ccm_logs_cleared', __('Všechny logy byly úspěšně smazány.', 'librecookiebar'), 'updated');
        wp_safe_redirect(admin_url('admin.php?page='.$this->logs_slug));
        exit;
    }

    private function render_service_field_row(string $field, string $label): void
    {
        ?>
        <tr>
            <th scope="row"><?php echo esc_html($label); ?></th>
            <td><?php $this->render_service_field(['field' => $field, 'label' => $label]); ?></td>
        </tr>
        <?php
    }

    private function render_text_field_row(string $field, string $label): void
    {
        ?>
        <tr>
            <th scope="row"><?php echo esc_html($label); ?></th>
            <td><?php $this->render_text_field(['field' => $field]); ?></td>
        </tr>
        <?php
    }
}