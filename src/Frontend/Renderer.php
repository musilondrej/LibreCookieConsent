<?php

namespace CCM\Frontend;

class Renderer
{
    private $settings;

    public function __construct()
    {
        $this->settings = \CCM\Plugin::get_settings();
    }

    public function init()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        if ($this->settings['mode'] === 'direct') {
            add_action('wp_head', [$this, 'render_blocked_scripts'], 1);
        }

        if ($this->settings['mode'] === 'gtm' && ! empty($this->settings['gtm_id'])) {
            add_action('wp_head', [$this, 'render_gtm_script'], 1);
            add_action('wp_body_open', [$this, 'render_gtm_noscript']);
        }
    }

    public function enqueue_scripts()
    {
        wp_enqueue_style(
            'ccm-cookieconsent-bundle',
            CCM_PLUGIN_URL.'assets/dist/ccm-cookieconsent-bundle.css',
            [],
            CCM_VERSION
        );

        wp_enqueue_script(
            'ccm-cookieconsent-bundle',
            CCM_PLUGIN_URL.'assets/dist/ccm-cookieconsent-bundle.js',
            [],
            CCM_VERSION,
            true
        );

        wp_add_inline_script('ccm-cookieconsent-bundle', $this->build_config_json(), 'before');
        $this->add_custom_colors_css();

    }

    private function build_config_json()
    {
        $config = [
            'restUrl' => rest_url(),
            'nonce' => wp_create_nonce('wp_rest'),
            'version' => '1.0',
            'mode' => ! empty($this->settings['gtm_id']) ? 'gtm' : 'direct',
            'categoryScripts' => $this->settings['category_scripts'] ?? [],
            'cookiesToErase' => $this->settings['cookies_to_erase'] ?? '_ga,_gid,_gat,_gcl_,__utm,_fbp,fr,_uet,_ttp',
            'categories' => [
                'necessary' => [
                    'enabled' => true,
                    'readOnly' => true
                ],
                'analytics' => [],
                'marketing' => [],
                'functionality' => []
            ],
            'language' => [
                'default' => 'cs',
                'translations' => [
                    'cs' => [
                        'consentModal' => [
                            'title' => $this->settings['texts']['title'],
                            'description' => $this->settings['texts']['description'],
                            'acceptAllBtn' => $this->settings['texts']['accept_all'],
                            'acceptNecessaryBtn' => $this->settings['texts']['accept_necessary'],
                            'showPreferencesBtn' => $this->settings['texts']['show_preferences']
                        ],
                        'preferencesModal' => [
                            'title' => $this->settings['texts']['preferences_title'] ?? __('Nastavení cookies', 'librecookiebar'),
                            'acceptAllBtn' => $this->settings['texts']['accept_all'],
                            'acceptNecessaryBtn' => $this->settings['texts']['accept_necessary'],
                            'savePreferencesBtn' => $this->settings['texts']['save_preferences'] ?? __('Uložit nastavení', 'librecookiebar'),
                            'sections' => [
                                [
                                    'title' => $this->settings['texts']['necessary_title'] ?? __('Nezbytné cookies', 'librecookiebar'),
                                    'description' => $this->settings['texts']['necessary_description'] ?? __('Tyto soubory cookie jsou nezbytné pro základní funkčnost webu.', 'librecookiebar'),
                                    'linkedCategory' => 'necessary'
                                ],
                                [
                                    'title' => $this->settings['texts']['analytics_title'] ?? __('Analytické cookies', 'librecookiebar'),
                                    'description' => $this->settings['texts']['analytics_description'] ?? __('Pomáhají nám pochopit, jak návštěvníci používají náš web.', 'librecookiebar'),
                                    'linkedCategory' => 'analytics'
                                ],
                                [
                                    'title' => $this->settings['texts']['marketing_title'] ?? __('Marketingové cookies', 'librecookiebar'),
                                    'description' => $this->settings['texts']['marketing_description'] ?? __('Používají se pro zobrazování relevantních reklam.', 'librecookiebar'),
                                    'linkedCategory' => 'marketing'
                                ],
                                [
                                    'title' => $this->settings['texts']['functionality_title'] ?? __('Funkční cookies', 'librecookiebar'),
                                    'description' => $this->settings['texts']['functionality_description'] ?? __('Umožňují pokročilé funkce webu.', 'librecookiebar'),
                                    'linkedCategory' => 'functionality'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'guiOptions' => [
                'consentModal' => [
                    'layout' => $this->settings['ui_layout'] ?? 'box',
                    'position' => $this->settings['ui_position'] ?? 'bottom right',
                    'transition' => $this->settings['ui_transition'] ?? 'slide',
                    'flipButtons' => (bool) ($this->settings['ui_flip_buttons'] ?? false),
                    'equalWeightButtons' => (bool) ($this->settings['ui_equal_weight_buttons'] ?? true)
                ]
            ],
            'cookie' => [
                'expiresAfterDays' => (int) ($this->settings['cookie_expiration'] ?? 182)
            ]
        ];

        $js_config = 'window.CCM_CONFIG = '.wp_json_encode($config).';';

        if (! empty($this->settings['custom_css'])) {
            $js_config .= "\n".'if(typeof document !== "undefined") { 
                var customStyle = document.createElement("style"); 
                customStyle.textContent = '.wp_json_encode($this->settings['custom_css']).'; 
                document.head.appendChild(customStyle); 
            }';
        }

        return $js_config;
    }



    public function render_blocked_scripts()
    {
        if (! empty($this->settings['ga4_id'])) {
            Services::ga4($this->settings['ga4_id']);
        }

        if (! empty($this->settings['meta_pixel_id'])) {
            Services::metaPixel($this->settings['meta_pixel_id']);
        }

        if (! empty($this->settings['clarity_id'])) {
            Services::clarity($this->settings['clarity_id']);
        }
    }

    public function render_gtm_script()
    {
        $gtm_id = esc_js($this->settings['gtm_id']);
        ?>
        <!-- Google Tag Manager -->
        <script>
            (function (w, d, s, l, i) {
                w[l] = w[l] || []; w[l].push({
                    'gtm.start':
                        new Date().getTime(), event: 'gtm.js'
                }); var f = d.getElementsByTagName(s)[0],
                    j = d.createElement(s), dl = l != 'dataLayer' ? '&l=' + l : ''; j.async = true; j.src =
                        'https://www.googletagmanager.com/gtm.js?id=' + i + dl; f.parentNode.insertBefore(j, f);
            })(window, document, 'script', 'dataLayer', '<?php echo esc_js($gtm_id); ?>');
        </script>
        <!-- End Google Tag Manager -->
        <?php
    }

    public function render_gtm_noscript()
    {
        $gtm_id = esc_attr($this->settings['gtm_id']);
        ?>
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr($gtm_id); ?>" height="0"
                width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->
        <?php
    }

    private function add_custom_colors_css()
    {
        $colors = $this->settings['colors'] ?? [];
        $defaults = ['bg' => '#ffffff', 'text' => '#333333', 'accent' => '#007cba'];
        
        if (empty($colors) || ! is_array($colors)) {
            return;
        }

        $has_custom_colors = false;

        foreach ($colors as $key => $value) {
            if (isset($defaults[$key]) && $value !== $defaults[$key]) {
                $has_custom_colors = true;
                break;
            }
        }

        if (! $has_custom_colors) {
            return;
        }

        $custom_css = "
        /* EU Cookie Consent Manager - Custom Colors */
        .cc-banner, .cc-window {
            background-color: {$colors['bg']} !important;
            color: {$colors['text']} !important;
        }
        .cc-btn, .cc-allow, .cc-deny {
            background-color: {$colors['accent']} !important;
            border-color: {$colors['accent']} !important;
        }
        .cc-btn:hover, .cc-allow:hover, .cc-deny:hover {
            background-color: {$colors['accent']}dd !important;
        }
        ";

        wp_add_inline_style('ccm-cookieconsent-bundle', $custom_css);
    }
}