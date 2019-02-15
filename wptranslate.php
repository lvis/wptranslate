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
 * Text Domain: wpTranslate
 * Domain Path: /languages
 */
final class WpTranslate
{
    const TEXT_DOMAIN = 'wpTranslate';
    /**
     * @var ModelBase
     */
    protected $plugin;
    /**
     * Holds the one and only instance to the plugin
     */
    private static $instance = null;
    final public static function i()
    {
        if (isset(static::$instance) == false) {
            static::$instance = new static();
        }
        return static::$instance;
    }
    protected function __construct()
    {
        //$_REQUEST['action'] logout
        //$_SERVER['REQUEST_URI'] customer_logout   REDIRECT_URL customer_logout
        //TODO Find a right way to handle this request using permalink to avoid this workarround and not found page
        $hasCustomerLogout = strpos($_SERVER['REQUEST_URI'], 'logout');
        $notLogoutAjaxAction = $hasCustomerLogout == false && (isset($_REQUEST['action']) == false || $_REQUEST['action'] != 'logout');
        if ($notLogoutAjaxAction) {
            add_action('plugins_loaded', [$this, 'handleInit'], 1);
        }
    }
    function handleInit()
    {
        remove_action('plugins_loaded', [$this, 'handleInit']);
        //TODO Skip handle file request. Ex: .js.map files
        //Tells whether the current request is an ajax request on frontend or not
        $adminAjaxActions = ['upload-attachment', 'customize_save'];
        $isAdminAjaxAction = isset($_REQUEST['action']) && in_array($_REQUEST['action'], $adminAjaxActions);
        //TODO Some plugins use transient for handle data and if plugin was activated after data was created translation not applied, plugin must clear all transient on plugin activation. Problem observed in  wc_attribute_taxonomies solved after modify and save an attribute
        $isRequestCron = defined('DOING_CRON');
        $isRequestCli = (defined('WP_CLI') && WP_CLI);
        $isRequestAdmin = is_admin();
        $isRequestAjax = wp_doing_ajax();
        //$isRequestCustomize = isset($_REQUEST['wp_customize']);
        //$isRequestCustomizePreview = is_customize_preview();
        if ($isRequestCron || $isRequestCli || $isRequestAdmin || $isRequestAjax || $isAdminAjaxAction) {
            $this->plugin = new ModelAdmin();
        } else {
            $this->plugin = new ModelFrontend();
        }
    }
}

WpTranslate::i();