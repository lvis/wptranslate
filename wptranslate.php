<?php
require_once 'ModelAdmin.php';
require_once 'ModelFrontend.php';

/**
 * Plugin Name: WpTranslate
 * Plugin URI:  mailto:vitaliix@gmail.com
 * Author:      Lupu Vitalie
 * Author URI:  mailto:vitaliix@gmail.com
 * Description: Adds user-friendly and database-friendly multilingual content support.
 * Tags:        translation, multilingual, bilingual
 * Version:     1.0
 * Text Domain: wptranslate
 * Domain Path: /languages
 */
final class WpTranslate
{
    const TEXT_DOMAIN = 'wptranslate';
    /**
     * @var ModelBase
     */
    protected $plugin;
    /**
     * Holds the one and only instance to the plugin
     */
    private static $instance = null;

    private $isRequestAction = false;
    final public static function i()
    {
        if (isset(static::$instance) == false) {
            static::$instance = new static();
        }
        return static::$instance;
    }
    protected function __construct()
    {
        $isRequestCron = defined('DOING_CRON');
        $isRequestCli = (defined('WP_CLI') && WP_CLI);
        if ($isRequestCron == false && $isRequestCli == false){
            $isRequestLogin = in_array($GLOBALS['pagenow'], ['wp-login.php', 'wp-register.php']);
            if ($isRequestLogin == false){
                $this->isRequestAction = isset($_REQUEST['action']);
                $notExcludedAdminAction = true;
                if ($this->isRequestAction){
                    $adminActions = ['upload-attachment', 'customize_save', 'logout', 'customer_logout'];
                    $notExcludedAdminAction = (in_array($_REQUEST['action'], $adminActions) == false);
                }
                /*$notAdminPage = strpos($_SERVER['REQUEST_URI'], 'admin') !== 0;
                if ($notExcludedAdminAction && $notAdminPage){*/
                if ($notExcludedAdminAction){
                    add_action('plugins_loaded', [$this, 'handleInit'], 1);
                }
            }
        }
    }
    function handleInit()
    {
        remove_action('plugins_loaded', [$this, 'handleInit']);
        // Some plugins use transient for handle data and
        // if plugin was activated after data was created translation not applied,
        // plugin must clear all transient on plugin activation.
        // Problem observed in  wc_attribute_taxonomies solved after modify and save an attribute
        $isRequestCustomize = isset($_REQUEST['wp_customize']);
        //$isRequestCustomizePreview = is_customize_preview();
        if ($isRequestCustomize || (is_admin() && $this->isRequestAction == false)) {
            $this->plugin = new ModelAdmin();
        } else {
            $this->plugin = new ModelFrontend();
        }
        $pluginRelativePath = basename(dirname( __FILE__ ) ) . '/languages/';
        load_plugin_textdomain(WpTranslate::TEXT_DOMAIN, false, $pluginRelativePath);
    }
}

WpTranslate::i();