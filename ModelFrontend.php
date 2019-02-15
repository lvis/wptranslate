<?php
require_once 'ModelBase.php';

class ModelFrontend extends ModelBase
{
    const COOKIE_LANGUAGE = 'language';
    protected function initLanguage()
    {
        parent::initLanguage();
        $linksModel = $this->getLinksModel();
        $languageCodeInUrl = $linksModel->getLanguageCode();
        if (empty($languageCodeInUrl) &&
            isset($_COOKIE[self::COOKIE_LANGUAGE]) &&
            isset($this->getLanguages()[$_COOKIE[self::COOKIE_LANGUAGE]])) {
            $languageCodeInCookie = $_COOKIE[self::COOKIE_LANGUAGE];
            if (empty($this->getLanguages()[$languageCodeInCookie]) == false &&
                $languageCodeInCookie != $this->getDefaultLanguageCode()) {
                $requestUrl = $linksModel->getUrlFromRequest();
                $redirectUrl = $linksModel->setLanguageCode($requestUrl, $languageCodeInCookie);
                wp_redirect($redirectUrl);
                exit;
            }
            $languageCodeInUrl = $this->getDefaultLanguageCode();
        }
        $currentLanguage = $this->getLanguage($languageCodeInUrl);
        if ($this->setCurrentLanguage($currentLanguage) == false) {
            $this->setCurrentLanguage($this->getDefaultLanguage());
        }
    }
    public function setCurrentLanguage($currentLanguage): bool
    {
        $result = parent::setCurrentLanguage($currentLanguage);
        if ($result) {
            setcookie(self::COOKIE_LANGUAGE, $currentLanguage->code, time() + YEAR_IN_SECONDS, COOKIEPATH,
                COOKIE_DOMAIN, is_ssl());
        }
        return $result;
    }
    protected function addLanguageHandlers()
    {
        parent::addLanguageHandlers();
        add_filter('pre_determine_locale', [$this, 'handlePreDetermineLocale']);
        //Handle Commerce Strings. Investigate maybe must be moved to ModelFrontEnd Class
        add_filter('the_posts', [$this, 'handleThePosts'], 5, 2);
        //[Links]
        add_filter('wp_redirect', [$this, 'setLanguageCodeToCurrent']);
        add_filter('home_url', [$this, 'setLanguageCodeToCurrent']);
        add_filter('post_link', [$this, 'setLanguageCodeToCurrent']);
        add_filter('post_type_link', [$this, 'setLanguageCodeToCurrent']);
        add_filter('page_link', [$this, 'setLanguageCodeToCurrent']);
        add_filter('term_link', [$this, 'setLanguageCodeToCurrent']);
        add_filter('attachment_link', [$this, 'setLanguageCodeToCurrent']);
        add_filter('nav_menu_link_attributes', [$this, 'handleNavMenuLinkAttributes'], 10, 2);
        //[Widgets]
        add_filter('widget_title', [$this, 'getTextTranslation']);
        //[Shop]
        add_filter('woocommerce_get_privacy_policy_text', [$this, 'getTextTranslation']);
        add_filter('woocommerce_get_terms_and_conditions_checkbox_text', [$this, 'getTextTranslation']);
        add_filter('woocommerce_product_get_name', [$this, 'getTextTranslation']);
        add_filter('woocommerce_order_item_get_name', [$this, 'getTextTranslation']);
        add_filter('woocommerce_format_content', [$this, 'getTextTranslation']);
        add_filter('woocommerce_attribute_label', [$this, 'getTextTranslation']);
        //[Builder]
        add_filter('elementor/frontend/the_content', [$this, 'getTextTranslation']);
    }
    function handlePreDetermineLocale()
    {
        return $this->getCurrentLanguageLocale();
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
    function handleNavMenuLinkAttributes($attributes, $item)
    {
        if (empty($attributes['href']) == false &&
            strpos($attributes['href'], '#') !== 0 &&
            $item->type != 'custom') {
            $attributes['href'] = $this->setLanguageCodeToCurrent($attributes['href']);
        }
        return $attributes;
    }
}