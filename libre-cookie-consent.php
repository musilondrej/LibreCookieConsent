<?php
/**
 * Plugin Name: LibreCookieConsent
 * Description: EU-ready cookie banner with CookieConsent v3, Google Consent Mode v2, support for GA4/Meta/Clarity, both direct and GTM modes.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Ondřej Musil
 * Author URI: https://musiltech.com
 * License: GPL v3 or later
 * Text Domain: librecookiebar
 * Domain Path: /languages
 */

if (! defined('ABSPATH'))
    exit;

define('CCM_VERSION', '0.1.0');
define('CCM_PLUGIN_FILE', __FILE__);
define('CCM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CCM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CCM_PLUGIN_BASENAME', plugin_basename(__FILE__));
spl_autoload_register(function ($class) {
    if (strpos($class, 'CCM\\') !== 0)
        return;
    $class_file = str_replace(['CCM\\', '\\'], ['', '/'], $class);
    $file_path = CCM_PLUGIN_DIR.'src/'.$class_file.'.php';
    if (file_exists($file_path))
        require_once $file_path;
});

function ccm_init()
{
    if (class_exists('CCM\\Plugin'))
        (new CCM\Plugin())->init();
}

add_action('plugins_loaded', 'ccm_init');
register_activation_hook(__FILE__, ['\CCM\Plugin', 'activate_plugin']);
register_deactivation_hook(__FILE__, ['\CCM\Plugin', 'deactivate_plugin']);