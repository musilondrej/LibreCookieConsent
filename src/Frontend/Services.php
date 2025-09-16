<?php

namespace CCM\Frontend;

/**
 * Consent-aware enqueuing pro třetí strany.
 * Všechny skripty jsou enqueueované „standardně“, ale přes filter script_loader_tag
 * se jejich tag přepíše na type="text/plain" + data-src/data-category.
 */
class Services
{
    const HANDLE_PREFIX = 'ccm-consent-';

    /** @var array<string,string> handle => inline JS (bez <script>) */
    private static $inline_map = [];

    /** @var array<string,string> handle => kategorie ('analytics'|'marketing'|'functionality'|'necessary') */
    private static $category_map = [];

    /** @var array<string,string> handle => noscript HTML (pokud je potřeba) */
    private static $noscript_map = [];

    public static function boot(): void
    {
        add_filter('script_loader_tag', [__CLASS__, 'filter_script_loader_tag'], 10, 3);
        add_action('wp_footer', [__CLASS__, 'print_noscripts'], 20);
    }

    private static function ver(string $salt = ''): string
    {
        $base = defined('CCM_PLUGIN_VERSION') ? (string) CCM_PLUGIN_VERSION : '1.0.0';
        if ($salt === '') {
            return $base;
        }
        return $base.'-'.substr(md5($salt), 0, 8);
    }

    public static function filter_script_loader_tag(string $tag, string $handle, string $src): string
    {
        if (strpos($handle, self::HANDLE_PREFIX) !== 0) {
            return $tag;
        }

        $category = self::$category_map[$handle] ?? 'analytics';

        if ($src === '' || $src === false) {
            $code = self::$inline_map[$handle] ?? '';
            if ($code === '') {
                return $tag;
            }

            // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- generujeme skript pro již enqueued handle
            return sprintf(
                '<script type="text/plain" data-category="%s">%s</script>'."\n",
                esc_attr($category),
                $code
            );
        }

        return sprintf(
            // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- přepis tagu pro již enqueued handle
            '<script type="text/plain" data-category="%s" data-src="%s"></script>'."\n",
            esc_attr($category),
            esc_url($src)
        );
    }


    public static function print_noscripts(): void
    {
        if (empty(self::$noscript_map)) {
            return;
        }

        foreach (self::$noscript_map as $handle => $html) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- obsah noscriptu je statický a kontrolovaný
            echo $html."\n";
        }
    }

    public static function ga4(string $measurement_id): void
    {
        $id = trim($measurement_id);
        if ($id === '') {
            return;
        }

        $loader = self::HANDLE_PREFIX.'ga4-loader';
        $init = self::HANDLE_PREFIX.'ga4-init';

        self::$category_map[$loader] = 'analytics';
        self::$category_map[$init] = 'analytics';

        wp_register_script(
            $loader,
            'https://www.googletagmanager.com/gtag/js?id='.rawurlencode($id),
            [],
            self::ver($id),
            false
        );
        wp_enqueue_script($loader);

        self::$inline_map[$init] = sprintf(
            'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","%1$s",{anonymize_ip:true,allow_google_signals:false,allow_ad_personalization_signals:false});',
            esc_js($id)
        );
        wp_register_script($init, false, [], self::ver($id), false);
        wp_enqueue_script($init);
    }

    public static function metaPixel(string $pixel_id): void
    {
        $id = trim($pixel_id);
        if ($id === '') {
            return;
        }

        $handle = self::HANDLE_PREFIX.'meta';
        $init = self::HANDLE_PREFIX.'meta-init';

        self::$category_map[$handle] = 'marketing';
        self::$category_map[$init] = 'marketing';

        wp_register_script(
            $handle,
            'https://connect.facebook.net/en_US/fbevents.js',
            [],
            self::ver($id),
            false
        );
        wp_enqueue_script($handle);

        self::$inline_map[$init] =
            '!(function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version="2.0";n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)})(window,document,"script","https://connect.facebook.net/en_US/fbevents.js");'
            .sprintf('fbq("init","%s");fbq("track","PageView");', esc_js($id));

        wp_register_script($init, false, [], self::ver($id), false);
        wp_enqueue_script($init);

        self::$noscript_map[$handle] = sprintf(
            '<noscript data-category="marketing"><img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id=%1$s&ev=PageView&noscript=1"/></noscript>',
            esc_attr($id)
        );
    }

    public static function clarity(string $project_id): void
    {
        $id = trim($project_id);
        if ($id === '') {
            return;
        }

        $handle = self::HANDLE_PREFIX.'clarity';
        $init = self::HANDLE_PREFIX.'clarity-init';

        self::$category_map[$handle] = 'analytics';
        self::$category_map[$init] = 'analytics';

        // Clarity tag – používáme přímý tag URL (CDN)
        wp_register_script(
            $handle,
            'https://www.clarity.ms/tag/'.rawurlencode($id),
            [],
            self::ver($id),
            false
        );
        wp_enqueue_script($handle);

        // Oficiální init
        self::$inline_map[$init] = sprintf(
            '(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,"clarity","script","%s");',
            esc_js($id)
        );
        wp_register_script($init, false, [], self::ver($id), false);
        wp_enqueue_script($init);
    }
}