<?php
require_once 'ModelBase.php';
require_once 'LanguageListTable.php';

class ModelAdmin extends ModelBase
{
    protected function initLanguage()
    {
        parent::initLanguage();
        $currentUserId = get_current_user_id();
        $currentUserLocale = get_user_locale($currentUserId);
        $currentUserLanguageCode = strtok($currentUserLocale, '_');
        $currentUserLanguage = $this->getLanguage($currentUserLanguageCode);
        if ($this->setCurrentLanguage($currentUserLanguage) == false) {
            $this->setCurrentLanguage($this->getDefaultLanguage());
        }
    }
    protected function addLanguageHandlers()
    {
        add_action('admin_menu', [$this, 'handleMenuAdmin']);
        // Add endpoints custom URLs in Appearance > Menus > Pages.
        add_action( 'admin_head-nav-menus.php', array( $this, 'handleAdminHeadNavMenus' ) );
        parent::addLanguageHandlers();
        //[Site Title]
        //add_filter('get_pages', [$this, 'handleGetPages'], 0);
        //Filters display of the term name in the terms list table. https://developer.wordpress.org/reference/hooks/term_name/
        add_filter('term_name', [$this, 'getTextTranslation']);
        //add_filter('wp_dropdown_cats', [$this, 'getTextTranslation']);
        add_filter('list_cats', [$this, 'getTextTranslation']);
        add_filter('wp_prepare_attachment_for_js', [$this, 'handlePrepareAttachmentForJs']);
        //[WooCommerce: Attributes]
        add_filter('esc_html', [$this, 'getTextTranslation']);
        add_filter('woocommerce_attribute_taxonomies', [$this, 'handleCommerceAttributes']);
        //[WooCommerce: Edit Order]
        add_filter('woocommerce_product_get_name', [$this, 'getTextTranslation']);
        add_filter('woocommerce_order_item_get_name', [$this, 'getTextTranslation']);
        add_filter('woocommerce_order_item_meta_get_name', [$this, 'getTextTranslation']);
        add_filter('woocommerce_order_item_display_meta_value', [$this, 'handleOrderItemDisplayMetaValue'], 10, 2);
        //[Customizer: Menu Items Add]
        add_filter('customize_nav_menu_available_items', [$this, 'handleCustomizeNavMenuItems'], 0);
        // Include custom items to customizer nav menu settings.
        add_filter( 'customize_nav_menu_available_item_types', array( $this, 'register_customize_nav_menu_item_types' ) );
        add_filter( 'customize_nav_menu_available_items', array( $this, 'register_customize_nav_menu_items' ), 10, 4 );
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
    function handleCustomizeNavMenuItems($items)
    {
        foreach ($items as &$item) {
            $item['title'] = $this->getTextTranslation($item['title']);
        }
        return $items;
    }
    function handleOrderItemDisplayMetaValue($display_value, WC_Meta_Data $meta)
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
        $pageTitle = __('Language Management', WpTranslate::TEXT_DOMAIN);
        $menuTitle = __('Languages', WpTranslate::TEXT_DOMAIN);
        $this->urlPageSettings = admin_url('options-general.php?page=' . self::MENU_SLUG);
        add_options_page($pageTitle, $menuTitle, 'manage_options', self::MENU_SLUG,
            [$this, 'createPageSettings']);
    }
    /**
     * Define name for options in Database
     * Default Value: Plugin Name
     */
    const OPTIONS_NAME = 'wpTranslate';
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
            $textLanguages = __('Languages');
            $textGeneral = __('General', 'qtranslate');
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
    public function handleAdminHeadNavMenus() {
        $textLanguages =  __('Languages');
        add_meta_box(self::ID_NAV_LINKS, $textLanguages, [$this, 'handleEndPointNavLink'],
            'nav-menus', 'side', 'low' );
    }
    const MENU_ITEM_ENDPOINT_LANGUAGES = 'languages_endpoint';
    const MENU_ITEM_TYPE_LANGUAGES = 'languages_nav';
    /** Output menu links. */
    public function handleEndPointNavLink() {
        // Get items from account menu.
        $textAddToMenu = __( 'Add to menu', 'woocommerce' );
        $textSelectAll = __('Select all', 'woocommerce' );
        $urlSelectAll = admin_url('nav-menus.php?page-tab=all&selectall=1#posttype-woocommerce-endpoints' );
        $urlSelectAll = esc_url($urlSelectAll);
        $i = -1;
        $content = '';
        $endpoints = wc_get_account_menu_items();
        foreach ( $endpoints as $key => $value ){
            $index = esc_attr( $i );
            $escValue =  esc_html( $value );
            $urlToLanguage = esc_url( wc_get_account_endpoint_url( $key ) );
            $content .=  "<li>
            <label class='menu-item-title'>
                <input type='checkbox' class='menu-item-checkbox' name='menu-item[{$index}][menu-item-object-id]' value='{$index}'>
                {$escValue}
            </label>
            <input type='hidden' class='menu-item-type' name='menu-item[{$index}][menu-item-type]' value='custom'>
            <input type='hidden' class='menu-item-title' name='menu-item[{$index}][menu-item-title]' value='{$escValue}'>
            <input type='hidden' class='menu-item-url' name='menu-item[{$index}][menu-item-url]' value='{$urlToLanguage}'>
            <input type='hidden' class='menu-item-classes' name='menu-item[{$index}][menu-item-classes]'></li>";
            $i--;
        }
        $postType = 'languages-enpoints';
        echo "<div id='posttype-{$postType}' class='posttypediv'>
        <div id='tabs-panel-{$postType}' class='tabs-panel tabs-panel-active'>
            <ul id='{$postType}-checklist' class='categorychecklist form-no-clear'>{$content}</ul>
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
        </p>
        </div>";
    }
    /**
     * Register customize new nav menu item types. This will register Languages endpoints as a nav menu item type.
     * @param  array $item_types Menu item types.
     * @return array
     */
    public function register_customize_nav_menu_item_types( $item_types ) {
        $item_types[] = [
            'title'      => __( 'Languages Endpoints'),
            'type_label' => __( 'Language Endpoint'),
            'type'       => self::MENU_ITEM_TYPE_LANGUAGES,
            'object'     => self::MENU_ITEM_ENDPOINT_LANGUAGES,
        ];
        return $item_types;
    }

    /**
     * Register account endpoints to customize nav menu items.
     * @param  array   $items  List of nav menu items.
     * @param  string  $type   Nav menu type.
     * @param  string  $object Nav menu object.
     * @param  integer $page   Page number.
     * @return array
     */
    public function register_customize_nav_menu_items($items = [], $type = '', $object = '', $page = 0 ) {
        // $page <= 0 Don't allow pagination since all items are loaded at once.
        if ( self::MENU_ITEM_ENDPOINT_LANGUAGES == $object && $page <= 0 ) {
            $textCustomLink = __( 'Custom Link');
            $endpoints = wc_get_account_menu_items(); // ['id'=>'en', 'title'=>'English', 'type_label' => $textCustomLink]
            foreach ( $endpoints as $endpoint) {
                $items[] = [
                    'id'         => $endpoint->id,
                    'title'      => $endpoint->title,
                    'url'        => $endpoint->url,
                    'type_label' => $textCustomLink,
                ];
            }
        }
        return $items;
    }
}