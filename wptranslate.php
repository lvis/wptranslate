<?php
/**
 * Plugin Name: WP Translate
 * Plugin URI:  mailto:vitaliix@gmail.com
 * Description: Adds user-friendly and database-friendly multilingual content support.
 * Version:     2.0
 * Author:      Vitalie Lupu
 * Author URI:  mailto:vitaliix@gmail.com
 * Text Domain: wptranslate
 * Domain Path: /languages
 */

require_once 'Language.php';

final class WpTranslate
{
    const TEXT_DOMAIN = 'wptranslate';
    const MENU_ITEM_TYPE_LANGUAGE = 'language';
    const COOKIE_LANGUAGE = 'language';
    const COOKIE_LANGUAGE_LOCALE = 'language-locale';
    const LANGUAGE_DEFAULT_CODE = 'en';

    private $enabledLanguages = [];
    private $acceptedLanguages = self::LANGUAGE_DEFAULT_CODE;
    private $defaultLanguageCode = self::LANGUAGE_DEFAULT_CODE;
    private $isRequestAction = false;
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
        $isRequestCron = defined('DOING_CRON');
        $isRequestCli = (defined('WP_CLI') && WP_CLI);
        if ($isRequestCron == false && $isRequestCli == false) {
            $this->isRequestAction = isset($_REQUEST['action']);
            $isExcludedAdminAction = false;
            if ($this->isRequestAction) {
                $requestActionValue = $_REQUEST['action'];
                $adminActions = ['upload-attachment', 'customize_save', 'logout', 'customer_logout', 'heartbeat'];//, 'edit']; TODO Investigate for which case edit action is not recommended
                $isExcludedAdminAction = in_array($requestActionValue, $adminActions);
            }
            if ($isExcludedAdminAction == false &&  $this->isPageLogin() == false) { //$_SERVER['REQUEST_METHOD'] !== 'POST' &&
                //add_action('plugins_loaded', [$this, 'handlePluginLoaded'], 1);
                add_action('plugins_loaded', [$this, 'handlePluginLoaded'], 4);
            }
        }
    }

    function handlePluginLoaded()
    {
        remove_action('plugins_loaded', [$this, 'handlePluginLoaded']);
        // Some plugins use transient for handle data and
        // if plugin was activated after data was created translation not applied,
        // plugin must clear all transient on plugin activation.
        // Problem observed in  wc_attribute_taxonomies solved after modify and save an attribute
        //$isRequestCustomizePreview = is_customize_preview();
        if ($this->isBuilder() ||  $this->isCustomizer() || $this->isBackend() ) {
            //Language: Set Default
            $currentUserId = get_current_user_id();
            $currentLocale = get_user_locale($currentUserId);
            $this->setDefaultLanguage($currentLocale);
            //Language: Get All
            $this->initEnabledLanguages();
            //Language: Set Current
            $this->setCurrentLanguage($this->defaultLanguage);
            //Language: Add Handlers
            $this->addLanguageHandlers();
            $this->addLanguageHandlersAdmin();
        } else {
            //Language: Set Default
            $currentLocale = get_bloginfo('language');
            $this->setDefaultLanguage($currentLocale);
            //Language: Get All
            $this->initEnabledLanguages();
            //Language: Set Current
            $languageCodeInUrl = $this->linksModel->getLanguageCode();
            $enabledLanguages = $this->getLanguages();
            if (empty($languageCodeInUrl) &&
                isset($_COOKIE[self::COOKIE_LANGUAGE]) &&
                isset($enabledLanguages[$_COOKIE[self::COOKIE_LANGUAGE]])) {
                $languageCodeInCookie = $_COOKIE[self::COOKIE_LANGUAGE];
                if (empty($enabledLanguages[$languageCodeInCookie]) == false &&
                    $languageCodeInCookie != $this->defaultLanguageCode) {
                    $isRequestAjax = (wp_doing_ajax() || $this->isRequestAction);
                    if ($isRequestAjax) {
                        $languageCodeInUrl = $languageCodeInCookie;
                    } else {
                        $requestUrl = $this->linksModel->getUrlFromRequest();
                        $redirectUrl = $this->linksModel->setLanguageCode($requestUrl, $languageCodeInCookie);
                        wp_redirect($redirectUrl);
                        exit;
                    }
                } else {
                    $languageCodeInUrl = $this->defaultLanguageCode;
                }
            }
            $currentLanguage = $this->getLanguage($languageCodeInUrl);
            $this->setCurrentLanguage($currentLanguage);
            //Language: Set Cookie
            $timeCookieExpire = time() + YEAR_IN_SECONDS;
            setcookie(self::COOKIE_LANGUAGE, $this->currentLanguage->code, $timeCookieExpire, COOKIEPATH, COOKIE_DOMAIN);
            $_COOKIE[self::COOKIE_LANGUAGE] = $this->currentLanguage->code;
            setcookie(self::COOKIE_LANGUAGE_LOCALE, $this->currentLanguage->locale, $timeCookieExpire, COOKIEPATH, COOKIE_DOMAIN);
            $_COOKIE[self::COOKIE_LANGUAGE_LOCALE] = $this->currentLanguage->locale;
            //Language: Add Handlers
            $this->addLanguageHandlers();
            $this->addLanguageHandlersSite();
        }
        $pluginRelativePath = basename(dirname(__FILE__)) . '/languages/';
        load_plugin_textdomain(WpTranslate::TEXT_DOMAIN, false, $pluginRelativePath);
    }

    /**
     * @var ILinksModel Url Links Model that adjust handle links according to current language
     */
    private $linksModel;

    private $defaultLanguage;

    function setDefaultLanguage($siteLanguageLocale)
    {
        $siteLanguageLocale = str_replace('-', '_', $siteLanguageLocale);
        $this->defaultLanguageCode = strtok($siteLanguageLocale, '_');
        $this->defaultLanguage = $this->getLanguage($this->defaultLanguageCode);
        if (!$this->defaultLanguage) {
            $this->defaultLanguageCode = self::LANGUAGE_DEFAULT_CODE;
            $this->defaultLanguage = $this->getLanguage($this->defaultLanguageCode);
        }
    }
    function setCurrentLanguage($language)
    {
        if ($language instanceof Language) {
            $this->currentLanguage = $language;
        } else {
            $this->currentLanguage = $this->getLanguage($this->defaultLanguageCode);
        }
    }
    /**
     * @var Language current Language object
     */
    private $currentLanguage;
    /**
     * Changes the language code in url to current language
     * @param string $url url to modify
     * @return string modified url
     */
    function setLanguageCodeToCurrent(string $url)
    {
        return $this->linksModel->setLanguageCode($url, $this->currentLanguage->code);
    }

    private $languagesList = [];
    /**
     * Returns the language by Wordpress Locale
     * @param string $propertyValue value of the queried language property
     * @param string $property name the queried language property
     * @return Language object, null if no language found
     */
    function getLanguage(string $propertyValue, string $property = 'code')
    {
        $result = null;
        $languagesByProperty = $this->getLanguages($property);
        if ($languagesByProperty && empty($languagesByProperty[$propertyValue]) == false) {
            $result = $languagesByProperty[$propertyValue];
        }
        return $result;
    }
    /**
     * Returns the list of available languages caches the list in array
     * @param string $property - Language object property name
     * @return array list of Language object properties associate with own Language object
     */
    function getLanguages(string $property = 'code'): array
    {
        $result = [];
        if (isset($this->languagesList[$property])) {
            $result = $this->languagesList[$property];
        } else {
            $languages = include('Languages.php');
            if (empty($languages) == false) {
                foreach ($languages as $code => $languageData) {
                    $language = new Language($languageData);
                    $this->languagesList['code'][$language->code] = $language;
                    $this->languagesList['locale'][$language->locale] = $language;
                    $this->languagesList['w3c'][$language->w3c] = $language;
                    $this->languagesList['facebook'][$language->facebook] = $language;
                }
            }
            if (empty($this->languagesList[$property]) == false) {
                $result = $this->languagesList[$property];
            }
        }

        return $result;
    }

    function getTextTranslation(string $text)
    {
        //TODO Check if after the text string are some html tags then add translation close tag [:]
        //Prevent User to make mistake and broke the UI when add text translations
        $languageTagStart = '[:';
        $languageTagEnd = ']';
        $languageTagEmpty = $languageTagStart . $languageTagEnd;
        $languageCodeEmpty = '';
        $regExpBlockSplit = "#(\\{$languageTagStart}[a-z]{2}\\{$languageTagEnd}|\\{$languageTagEmpty})#ism";
        $regExpLanguageBlockMatch = "#^\\{$languageTagStart}([a-z]{2})\\{$languageTagEnd}$#ism";
        $blocks = preg_split($regExpBlockSplit, $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        //var_dump($blocks);
        if (count($blocks) > 1) {
            $languageCodeOfLastBlock = $languageCodeEmpty;
            $textOfLanguageTagEmpty = '';
            $textTranslations = [];
            $hasTranslation = false;
            foreach ($blocks as $block) {
                //var_dump($textTranslations);
                if (preg_match($regExpLanguageBlockMatch, $block, $matches)) {
                    //var_dump($matches);
                    $languageCodeOfLastBlock = $matches[1];
                } else if ($block === $languageTagEmpty) {
                    $languageCodeOfLastBlock = $languageCodeEmpty;
                } else if ($languageCodeOfLastBlock == $languageCodeEmpty) {
                    if ($hasTranslation) {
                        $textOfLanguageTagEmpty = $block;
                    } else {
                        $textOfLanguageTagEmpty .= $block;
                    }
                } else if (isset($textTranslations[$languageCodeOfLastBlock])) {
                    $textTranslations[$languageCodeOfLastBlock] .= $textOfLanguageTagEmpty . $block;
                    $hasTranslation = true;
                } else {
                    $textTranslations[$languageCodeOfLastBlock] = $textOfLanguageTagEmpty . $block;
                    $hasTranslation = true;
                }
            }
            if (empty($textOfLanguageTagEmpty) == false) {
                foreach ($textTranslations as &$translation) {
                    $translation .= $textOfLanguageTagEmpty;
                }
            }
            //var_dump($textTranslations);
            $currentLanguageCode = $this->currentLanguage->code;
            if (isset($textTranslations[$currentLanguageCode])) {
                $text = $textTranslations[$currentLanguageCode];
            } else if ($currentLanguageCode != $this->defaultLanguageCode && isset($textTranslations[$this->defaultLanguageCode])) {
                $text = $textTranslations[$this->defaultLanguageCode];
            }
            //TODO Add case when no language found use $textOfLanguageTagEmpty value if defined as default one language value
        }
        return $text;
    }

    function isPageLogin()
    {
        return in_array($_SERVER['PHP_SELF'], ['/wp-login.php', '/wp-register.php']) || stripos(wp_login_url(), $_SERVER['SCRIPT_NAME']) !== false;
    }

    function isBackend()
    {
        $posOfWpAdminInUri = strpos($_SERVER['REQUEST_URI'],'wp-admin');
        $isBackendRequest = ($posOfWpAdminInUri !== false);
        if((defined('DOING_AJAX') && DOING_AJAX)) {
            //https://snippets.khromov.se/determine-if-wordpress-ajax-request-is-a-backend-of-frontend-request/
            //From wp-includes/functions.php, wp_get_referer() function.
            //Required to fix: https://core.trac.wordpress.org/ticket/25294
            if ( empty($_REQUEST['_wp_http_referer']) == false ) {
                $httpReferrer = wp_unslash($_REQUEST['_wp_http_referer']);
            } elseif ( empty($_SERVER['HTTP_REFERER']) == false) {
                $httpReferrer = wp_unslash($_SERVER['HTTP_REFERER']);
            }
            $urlToAdminPage = admin_url();
            $posOfWpAdminInReferrer = strpos($httpReferrer, $urlToAdminPage);
            $isBackendRequest = ($posOfWpAdminInReferrer !== false);
            $scriptFileName = '';
            if (isset($_SERVER['SCRIPT_FILENAME'])){
                $scriptFileName = $_SERVER['SCRIPT_FILENAME'];
                $scriptFileName = basename($scriptFileName);
            }
            $isBackendRequest = ($isBackendRequest && $scriptFileName === 'admin-ajax.php');
        } else if (wp_is_json_request()){
            $httpReferrer = '';
            if ( empty($_REQUEST['_wp_http_referer']) == false ) {
                $httpReferrer = wp_unslash($_REQUEST['_wp_http_referer']);
            } elseif ( empty($_SERVER['HTTP_REFERER']) == false) {
                $httpReferrer = wp_unslash($_SERVER['HTTP_REFERER']);
            }
            $posOfWpAdminInReferrer = strpos($httpReferrer, 'wp-admin');
            $isBackendRequest = ($posOfWpAdminInReferrer !== false);
        }
        return $isBackendRequest;
    }

    function isBuilder()
    {
        return ($this->isRequestAction && $_REQUEST['action'] === 'elementor');
    }

    function isCustomizer()
    {
        return isset($_REQUEST['wp_customize']);
    }

    function isNotPageEditTerm()
    {
        global $pagenow;
        return $pagenow !== 'term.php';
    }
    function isNotPageEditNavMenu()
    {
        global $pagenow;
        return $pagenow !== 'nav-menus.php';
    }

    //-----------------------------------------------[Generic]
    function initEnabledLanguages()
    {
        $textCustomLink = __('Custom Link');
        $languageDefault = $this->getLanguage(self::LANGUAGE_DEFAULT_CODE);
        $this->enabledLanguages [] = [
            'id' => $languageDefault->code,
            'title' => $languageDefault->name,
            'url' => "#{$languageDefault->code}",
            'type_label' => $textCustomLink
        ];
        $langLocales = array_keys($this->getLanguages('locale'));
        $commaSeparateLocales = implode(',', $langLocales);
        $langFiles = glob(WP_LANG_DIR . "/{{$commaSeparateLocales}}.mo", GLOB_BRACE);
        if ($langFiles) {
            $langCodes = $this->getLanguages();
            foreach ($langFiles as $langFile) {
                $langFile = basename($langFile, '.mo');
                $langCode = substr($langFile, 0, 2);
                $this->acceptedLanguages .= "|{$langCode}";
                $lang = $langCodes[$langCode];
                $this->enabledLanguages [] = [
                    'id' => $lang->code,
                    'title' => $lang->name,
                    'url' => "#{$lang->code}",
                    'type_label' => $textCustomLink
                ];
            }
        }
        $isPermalinkStructure = get_option('permalink_structure');
        if ($isPermalinkStructure) {
            require_once 'LinksModelDirectory.php';
            $this->linksModel = new LinksModelDirectory($this->defaultLanguageCode, $this->acceptedLanguages);
        } else {
            require_once 'LinksModelQuery.php';
            $this->linksModel = new LinksModelQuery($this->defaultLanguageCode, $this->acceptedLanguages);
        }
    }

    protected function addLanguageHandlers()
    {
        //Generic Handler for case when no other way to translate text
        add_filter('translate_text', [$this, 'getTextTranslation']);
        if ($this->isNotPageEditNavMenu()){
            add_filter('the_title', [$this, 'getTextTranslation']); //[NavMenuItem Title] This override the others NavMenuItem titles after save
        }
        //[Terms]
        if ($this->isNotPageEditTerm() && wp_is_json_request()) {
            add_filter('get_term', [$this, 'handleGetTerm']);  //Don't display Raw translation is used for ModelAdmin
        }
        //[WooCommerce]
        add_filter('woocommerce_gateway_title', [$this, 'getTextTranslation']);
        add_filter('woocommerce_gateway_description', [$this, 'getTextTranslation']);
        add_filter('woocommerce_shipping_rate_label', [$this, 'getTextTranslation']);
        add_filter('woocommerce_shipping_zone_shipping_methods', function($methods){
            foreach ( $methods as $item ) {
                $item->title = $this->getTextTranslation($item->title);
            }
            return $methods;
        });
        //[Builder]
        add_filter('elementor/frontend/the_content', [$this, 'getTextTranslation']);
        add_filter('elementor/widget/render_content', [$this, 'getTextTranslation']);
    }

    function handleGetTerm($term)
    {
        if ($term && empty($term->name) == false) {
            $term->name = $this->getTextTranslation($term->name);
        }
        return $term;
    }

    function handleNavMenuLinkAttributes($attributes, $item)
    {
        $menuItemUrl = $attributes['href'];
        if (empty($menuItemUrl) == false) {
            $urlStartWithHashTag = ($menuItemUrl[0] === '#');
            if ($urlStartWithHashTag) {
                $urlPrefix = home_url();
                $currentLanguageCode = $this->currentLanguage->code;
                if (preg_match("/^#({$this->acceptedLanguages})/", $attributes['href'], $matches) && isset($matches[1])) {
                    $lang = $this->getLanguage($matches[1]);
                    if ($currentLanguageCode == $lang->code) {
                        $item->title = "<style type='text/css'>.menu-item-{$item->ID}{display: none !important;}</style>";
                    } else {
                        if ($lang->code == $this->defaultLanguageCode) {
                            $urlPrefix .= "/{$lang->code}";
                        }
                        $attributes['href'] = $urlPrefix . $this->linksModel->getUrlForLanguage($lang->code);
                        $item->title = "<figure>
                        <img src='{$lang->getFlagUrl()}' width='18' style='vertical-align: baseline;'>
                        <figcaption style='display: inline-block'>{$item->title}</figcaption>
                        </figure>";
                    }
                } else if ($currentLanguageCode == $this->defaultLanguageCode) {
                    $attributes['href'] = "{$urlPrefix}/{$attributes['href']}";
                } else {
                    $attributes['href'] = "{$urlPrefix}/{$currentLanguageCode}/{$attributes['href']}";
                }
            } else if ($item->type != 'custom') {
                //TODO Handle case when custom link is WooCommerce link or other plugin
                $attributes['href'] = $this->setLanguageCodeToCurrent($attributes['href']);
            }
        }
        return $attributes;
    }

    //--------------------------------------------------------[Admin]
    protected function addLanguageHandlersAdmin()
    {
        //[Site Title]
        add_action('admin_menu', [$this, 'handleMenuAdmin']);
        //add_filter('get_pages', [$this, 'handleGetPages'], 0);
        //Filters display of the term name in the terms list table. https://developer.wordpress.org/reference/hooks/term_name/
        add_filter('term_name', [$this, 'getTextTranslation']);
        //add_filter('wp_dropdown_cats', [$this, 'getTextTranslation']);
        add_filter('list_cats', [$this, 'getTextTranslation']);
        add_filter('wp_prepare_attachment_for_js', [$this, 'handlePrepareAttachmentForJs']);
        //[WooCommerce: Attributes]
        if ($this->isNotPageEditTerm()) {
            add_filter('esc_html', [$this, 'getTextTranslation']); // Post Title & WooCommerce Attributes
        }
        add_filter('woocommerce_attribute_taxonomies', [$this, 'handleCommerceAttributes']);
        //[WooCommerce: Edit Order]
        add_filter('woocommerce_product_get_name', [$this, 'getTextTranslation']);
        add_filter('woocommerce_order_item_get_name', [$this, 'getTextTranslation']);
        add_filter('woocommerce_order_item_meta_get_name', [$this, 'getTextTranslation']);
        add_filter('woocommerce_order_item_display_meta_value', [$this, 'handleOrderItemDisplayMetaValue'], 10, 2);
        //[Customizer: Menu Items Add]
        // Include custom items to customizer nav menu settings.
        add_filter('customize_nav_menu_available_item_types', [$this, 'registerCustomizerNavMenuItemTypes']);
        add_filter('customize_nav_menu_available_items', [$this, 'registerCustomizerNavMenuItems'], 10, 2);
        // Add endpoints custom URLs in Appearance > Menus > Pages.
        add_action('admin_head-nav-menus.php', [$this, 'handleAdminHeadNavMenus']);
    }

    function handleGetPages($pages)
    {
        if (empty($pages) == false && is_array($pages)) {
            foreach ($pages as &$page) {
                $page->post_title = $this->getTextTranslation($page->post_title);
            }
        }
        return $pages;
    }

    function handleOrderItemDisplayMetaValue($display_value, $meta)
    {
        $metaData = $meta->get_data();
        if ($metaData && isset($metaData['key']) && $metaData['key'] == 'Items') {
            $display_value = $this->getTextTranslation($display_value);
        }
        return $display_value;
    }

    function handlePrepareAttachmentForJs($response)
    {
        if (isset($response['uploadedToTitle'])) {
            $response['uploadedToTitle'] = $this->getTextTranslation($response['uploadedToTitle']);
        }
        return $response;
    }

    private $translatedProductAttributes;

    function handleCommerceAttributes($attributes)
    {
        if (is_array($attributes)) {
            if (empty($this->translatedProductAttributes)) {
                $attributesTranslated = [];
                foreach ($attributes as $attribute) {
                    $attribute->attribute_label = $this->getTextTranslation($attribute->attribute_label);
                    $attributesTranslated[] = $attribute;
                }
                $attributes = $attributesTranslated;
                $this->translatedProductAttributes = $attributes;
            } else {
                $attributes = $this->translatedProductAttributes;
            }
        }
        return $attributes;
    }

    const MENU_SLUG = 'languages';
    private $urlPageSettings = '';

    /** Adds the link to the languages Settings in the WordPress Admin Menu. */
    function handleMenuAdmin()
    {
        $this->urlPageSettings = admin_url('options-general.php?page=' . self::MENU_SLUG);
        $pageTitle = __('Language Management', WpTranslate::TEXT_DOMAIN);
        $menuTitle = __('Languages', WpTranslate::TEXT_DOMAIN);
        require_once 'LanguageListTable.php';
        add_options_page($pageTitle, $menuTitle, 'manage_options', self::MENU_SLUG,
            [$this, 'createPageSettings']);
    }

    /**
     * Default language for the front page is set by browser preference
     * Default Value: 1
     */
    const OPTION_BROWSER = 'detectBrowserLanguage';
    /**
     * Define Mode of language editing 1 for RAW or 0 Language Handlers
     * Default Value: 1
     */
    const OPTION_EDITOR_MODE_RAW = 'editor_mode_raw';

    public $options = [
        self::OPTION_BROWSER => 1,
        self::OPTION_EDITOR_MODE_RAW => 1
    ];
    const NONCE_NAME = 'nonceWpTranslate';

    function verifyNonce()
    {
        return empty($_POST) || check_admin_referer(self::NONCE_NAME, self::NONCE_NAME);
    }

    function createPageSettings()
    {
        if ($this->verifyNonce()) {
            $textSettings = __('Settings');
            $textLanguages = __('Languages', WpTranslate::TEXT_DOMAIN);
            $textSaveChanges = __('Save Changes', 'qtranslate');
            //[Tab: General]
            $textDetectBrowserLanguage = __('Detect Browser Language', 'qtranslate');
            $textWhenFrontPageIsVisited = __('When the frontpage is visited via bookmark/external link/type-in, the visitor will be forwarded to the correct URL for the language specified by his browser.', 'qtranslate');
            $checkedDetectBrowser = checked(1, $this->options[self::OPTION_BROWSER], false);
            //[Editor Raw Mode]
            $textEditorModeRaw = __('Editor Raw Mode', 'qtranslate');
            $textDoNotUseLSB = __('Do not use Language Switching Buttons to edit multi-language text entries.', 'qtranslate');
            $checkedEditorModeRaw = checked(1, $this->options[self::OPTION_EDITOR_MODE_RAW], false);
            //Languages
            $textOnlyEnabledLanguages = __('Only enabled languages are loaded at front-end, while all %d configured languages are listed here.', 'qtranslate');
            $languages = $this->getLanguages();
            $languagesCount = count($languages);
            $textOnlyEnabledLanguages = sprintf($textOnlyEnabledLanguages, $languagesCount);
            $languageListTable = new LanguageListTable($languages, $this->urlPageSettings);
            $languageListTable->prepare_items();
            ob_start();
            $languageListTable->display();
            $contentLanguagesAll = ob_get_clean();
            $nonceLanguageFormEdit = wp_nonce_field(self::NONCE_NAME, self::NONCE_NAME, true, false);
            echo "<div class='wrap'><h1>{$textLanguages} {$textSettings}</h1>
            <form method='post'  action='{$this->urlPageSettings}'>
            {$nonceLanguageFormEdit}
            <fieldset>
                <h3><label for='editor_mode_raw'>
                    <span>{$textEditorModeRaw}</span>
                    <input type='checkbox' name='editor_mode_raw' id='editor_mode_raw' {$checkedEditorModeRaw}>
                </label></h3>
                <p class='qtranxs_notes'>{$textDoNotUseLSB}</p>
            </fieldset>
            <fieldset>
                <h3><label for='detect_browser_language'>
                    <span>{$textDetectBrowserLanguage}</span>
                    <input {$checkedDetectBrowser} type='checkbox' name='detect_browser_language' 
                    id='detect_browser_language' value='1'>
                </label></h3>
                <p class='qtranxs_notes'>$textWhenFrontPageIsVisited</p>
            </fieldset>
            <fieldset>
                <h3>{$textLanguages}</h3>
                <p>{$textOnlyEnabledLanguages}</p>
                {$contentLanguagesAll}
            </fieldset>
            <p class='submit'>
                <input value='{$textSaveChanges}' type='submit' name='submit' class='button-primary'>
            </p></form></div>";
        }
    }

    const ID_NAV_LINKS = 'wptranslate_endpoints_nav_link';

    /**
     * Add custom nav meta box.
     * Adapted from http://www.johnmorrisonline.com/how-to-add-a-fully-functional-custom-meta-box-to-wordpress-navigation-menus/.
     */
    public function handleAdminHeadNavMenus()
    {
        $textLanguages = __('Languages', WpTranslate::TEXT_DOMAIN);
        add_meta_box(self::ID_NAV_LINKS, $textLanguages, [$this, 'handleEndPointNavLink'],
            'nav-menus', 'side', 'low');
    }

    /** Output menu links. */
    public function handleEndPointNavLink()
    {
        // Get items from account menu.
        $i = -1;
        $content = '';
        foreach ($this->enabledLanguages as $endpoint) {
            $index = esc_attr($i);
            $endpointTitle = esc_html($endpoint['title']);
            $endpointUrl = esc_url($endpoint['url']);
            $content .= "<li>
            <label class='menu-item-title'>
                <input type='checkbox' class='menu-item-checkbox' name='menu-item[{$index}][menu-item-object-id]' value='{$index}'>
                {$endpointTitle}
            </label>
            <input type='hidden' class='menu-item-type' name='menu-item[{$index}][menu-item-type]' value='custom'>
            <input type='hidden' class='menu-item-title' name='menu-item[{$index}][menu-item-title]' value='{$endpointTitle}'>
            <input type='hidden' class='menu-item-url' name='menu-item[{$index}][menu-item-url]' value='{$endpointUrl}'>
            <input type='hidden' class='menu-item-classes' name='menu-item[{$index}][menu-item-classes]'></li>";
            $i--;
        }
        $textAddToMenu = __('Add to menu');
        $textSelectAll = __('Select all');
        $postType = self::MENU_ITEM_TYPE_LANGUAGE;
        $urlSelectAll = admin_url("nav-menus.php?page-tab=all&selectall=1#posttype-{$postType}");
        $urlSelectAll = esc_url($urlSelectAll);
        echo "<div id='posttype-{$postType}' class='posttypediv'>
        <div id='tabs-panel-{$postType}' class='tabs-panel tabs-panel-active'>
            <ul id='{$postType}-checklist' class='categorychecklist form-no-clear'>
                {$content}
            </ul>
        </div>
        <p class='button-controls'>
            <span class='list-controls'>
                <a href='{$urlSelectAll}' class='select-all'>{$textSelectAll}</a>
            </span>
            <span class='add-to-menu'>
                <button id='submit-posttype-{$postType}' type='submit' value='{$textAddToMenu}' 
                        class='button-secondary submit-add-to-menu right' name='add-post-type-menu-item'>
                    {$textAddToMenu}
                </button>
                <span class='spinner'></span>
            </span>
        </p></div>";
    }

    /**
     * Register customize new nav menu item types. This will register Languages endpoints as a nav menu item type.
     * @param array $itemTypes Menu item types.
     * @return array
     */
    public function registerCustomizerNavMenuItemTypes($itemTypes)
    {
        $itemTypes[] = [
            'title' => __('Languages', WpTranslate::TEXT_DOMAIN),
            'type_label' => __('Site Language'),
            'type' => self::MENU_ITEM_TYPE_LANGUAGE,
            'object' => self::MENU_ITEM_TYPE_LANGUAGE,
        ];
        return $itemTypes;
    }

    /**
     * Register Language endpoints to customize nav menu items.
     * @param array $items List of nav menu items.
     * @param string $type Nav menu type.
     * @return array
     */
    public function registerCustomizerNavMenuItems($items = [], $type = '')
    {
        if (empty($items) && $type == self::MENU_ITEM_TYPE_LANGUAGE) {
            $items = $this->enabledLanguages;
        } else {
            foreach ($items as &$item) {
                $item['title'] = $this->getTextTranslation($item['title']);
            }
        }
        return $items;
    }

    //---------------------------------[Site]
    function addLanguageHandlersSite()
    {
        add_filter('pre_determine_locale', [$this, 'handlePreDetermineLocale']);
        //Handle Commerce Strings. Investigate maybe must be moved to ModelFrontEnd Class
        add_filter('the_posts', [$this, 'handleThePosts'], 5, 2);
        //[Links]
        add_filter('home_url', [$this, 'setLanguageCodeToCurrent']);
        add_filter('post_link', [$this, 'setLanguageCodeToCurrent']);
        add_filter('post_type_link', [$this, 'setLanguageCodeToCurrent']);
        add_filter('page_link', [$this, 'setLanguageCodeToCurrent']);
        add_filter('term_link', [$this, 'setLanguageCodeToCurrent']);
        add_filter('attachment_link', [$this, 'setLanguageCodeToCurrent']);
        //[Page Title]
        add_filter('document_title_parts', [$this, 'handleTitleParts']);
        //Nav Menu Item Title
        add_filter('nav_menu_link_attributes', [$this, 'handleNavMenuLinkAttributes'], 10, 2);
        //[Widgets]
        add_filter('widget_title', [$this, 'getTextTranslation']);
        //[Shop]
        //Temp Solution TODO: Investigate a better way to handle option translation
        add_filter('pre_option_woocommerce_checkout_privacy_policy_text', [$this, 'handleOptionWcGetPrivacyPolicyText'],10,3);
        add_filter('pre_option_woocommerce_registration_privacy_policy_text', [$this, 'handleOptionWcGetPrivacyPolicyText'],10,3);
        add_filter('woocommerce_get_terms_and_conditions_checkbox_text', [$this, 'getTextTranslation']);
        add_filter('woocommerce_product_get_name', [$this, 'getTextTranslation']);
        add_filter('woocommerce_order_item_get_name', [$this, 'getTextTranslation']);
        add_filter('woocommerce_format_content', [$this, 'getTextTranslation']);
        add_filter('woocommerce_attribute_label', [$this, 'getTextTranslation']);
    }
    function handleOptionWcGetPrivacyPolicyText($changed, $option, $value){
        if (is_string($value)){
            $changed = $this->getTextTranslation($value);
        }
        return $changed;
    }
    function handlePreDetermineLocale()
    {
        return $this->currentLanguage->locale;
    }

    function handleThePosts(array $posts, WP_Query $query)
    {
        if ($query->query_vars['post_type'] !== 'nav_menu_item') {
            foreach ($posts as $post) {
                foreach (get_object_vars($post) as $varName => $varValue) {
                    switch ($varName) {
                        case 'post_title':
                        case 'post_excerpt':
                        case 'post_content_filtered':
                        case 'post_content':
                            $post->$varName = $this->getTextTranslation($varValue);
                            break;
                    }
                }
            }
        }
        return $posts;
    }

    function handleTitleParts($titleParts)
    {
        if (empty($titleParts['title']) == false) {
            $titleParts['title'] = $this->getTextTranslation($titleParts['title']);
        } else if (empty($titleParts['site'])) {
            $titleParts['site'] = $this->getTextTranslation($titleParts['site']);
        }
        return $titleParts;
    }
}
register_deactivation_hook(__FILE__, function(){
    flush_rewrite_rules();
});
WpTranslate::i();