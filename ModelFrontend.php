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
                $isRequestAjax = (wp_doing_ajax() || isset($_REQUEST['action']));
                if ($isRequestAjax){
                    $languageCodeInUrl = $languageCodeInCookie;
                } else {
                    $requestUrl = $linksModel->getUrlFromRequest();
                    $redirectUrl = $linksModel->setLanguageCode($requestUrl, $languageCodeInCookie);
                    wp_redirect($redirectUrl);
                    exit;
                }
            } else {
                $languageCodeInUrl = $this->getDefaultLanguageCode();
            }
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
            $timeCookieExpire = time() + YEAR_IN_SECONDS;
            setcookie(self::COOKIE_LANGUAGE, $currentLanguage->code, $timeCookieExpire, COOKIEPATH, COOKIE_DOMAIN, is_ssl());
        }
        return $result;
    }

    protected function addLanguageHandlers()
    {
        parent::addLanguageHandlers();
        add_filter('pre_determine_locale', [$this, 'getCurrentLanguageLocale']);
        //Handle Commerce Strings. Investigate maybe must be moved to ModelFrontEnd Class
        add_filter('the_posts', [$this, 'handleThePosts'], 5, 2);
        //[Links]
        //TODO Check if this filter is not needed anymore
        //add_filter('wp_redirect', [$this, 'setLanguageCodeToCurrent']);
        add_filter('home_url', [$this, 'setLanguageCodeToCurrent']);
        add_filter('post_link', [$this, 'setLanguageCodeToCurrent']);
        add_filter('post_type_link', [$this, 'setLanguageCodeToCurrent']);
        add_filter('page_link', [$this, 'setLanguageCodeToCurrent']);
        add_filter('term_link', [$this, 'setLanguageCodeToCurrent']);
        add_filter('attachment_link', [$this, 'setLanguageCodeToCurrent']);
        add_filter('nav_menu_link_attributes', [$this, 'handleNavMenuLinkAttributes'], 10, 2);
        //[Terms]
        add_filter('get_term', [$this, 'handleGetTerm']);  //Don't display Raw translation is used for ModelAdmin
        //[Page Title]
        add_filter('document_title_parts', [$this, 'handleTitleParts']);
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

    /**
     * Changes the language code in url to current language
     * @param string $url url to modify
     * @return string modified url
     */
    public function setLanguageCodeToCurrent(string $url)
    {
        return $this->getLinksModel()->setLanguageCode($url, $this->getCurrentLanguageCode());
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
        $menuItemUrl = $attributes['href'];
        if (empty($menuItemUrl) == false) {
            $urlStartWithHashTag = ($menuItemUrl[0] ==='#');
            if ($urlStartWithHashTag){
                $urlPrefix = home_url();
                $currentLanguageCode = $this->getCurrentLanguageCode();
                if (preg_match($this->patternLanguageCode, $attributes['href'], $matches) && isset($matches[1])) {
                    $lang = $this->getLanguage($matches[1]);
                    if ($lang->code == $currentLanguageCode) {
                        $attributes['class'] = 'd-xs-none';
                    } else {
                        if ($lang->code == $this->getDefaultLanguageCode()) {
                            $urlPrefix .= "/{$lang->code}";
                        }
                        $attributes['href'] = $urlPrefix . $this->getLinksModel()->getUrlForLanguage($lang->code);
                        $item->title = "<span class='lang-item'><img src='{$lang->getFlagUrl()}' width='18' style='vertical-align: baseline;'>&nbsp;{$item->title}</span>";
                    }
                } else {
                    if ($currentLanguageCode == $this->getDefaultLanguageCode()){
                        $attributes['href'] = "{$urlPrefix}/{$attributes['href']}";
                    } else {
                        $attributes['href'] = "{$urlPrefix}/{$currentLanguageCode}/{$attributes['href']}";
                    }
                }
            } else if ($item->type != 'custom'){//TODO Handle case when custom link is WooCommerce link or other plugin
                $attributes['href'] = $this->setLanguageCodeToCurrent($attributes['href']);
            }
        }
        return $attributes;
    }
    function handleGetTerm($term)
    {
        if ($term && empty($term->name) == false) {
            $term->name = $this->getTextTranslation($term->name);
        }
        return $term;
    }
    function handleTitleParts($titleParts){
        if (empty($titleParts['title']) == false){
            $titleParts['title'] = $this->getTextTranslation($titleParts['title']);
        } else if (empty($titleParts['site'])){
            $titleParts['site'] = $this->getTextTranslation($titleParts['site']);
        }
        return $titleParts;
    }
}