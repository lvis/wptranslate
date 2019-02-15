<?php
require_once 'Language.php';
require_once 'LinksModelDirectory.php';

/**
 * Base class for both Admin and Frontend
 */
abstract class ModelBase
{
    public function __construct()
    {
        $this->initLanguage();
        $this->addLanguageHandlers();
    }
    protected function initLanguage(){
        //TODO Fix case when languages have different locale in wordpress
        $defaultLanguageCode = strtok(get_bloginfo('language'), '_');
        $defaultLanguage = $this->getLanguage($defaultLanguageCode);
        if ($defaultLanguage) {
            $this->defaultLanguageCode = $this->defaultLanguage->code;
        } else {
            $this->defaultLanguageCode = 'en';
            $this->defaultLanguage = $this->getLanguage($this->defaultLanguageCode);
        }
        $this->getLinksModel();
    }
    protected function addLanguageHandlers(){
        //apply_filters_ref_array( 'the_posts', array( $this->posts, &$this ) );  Try to investigate this filter if is good for translation when retrieve the posts
        //Generic Handler for case when no other way to translate text
        add_filter('translate_text', [$this, 'getTextTranslation']);
        //[NavMenuItem Title] Make this optional because is override also the placeholder in Customizer
        add_filter('the_title', [$this, 'getTextTranslation']);
        //[Terms]
        add_filter('get_term', [$this, 'handleGetTerm']);
        //[WooCommerce]
        add_filter('woocommerce_gateway_title', [$this, 'getTextTranslation']);
        add_filter('woocommerce_gateway_description', [$this, 'getTextTranslation']);
    }
    function handleGetTerm($term)
    {
        if ($term && empty($term->name) == false) {
            $term->name = $this->getTextTranslation($term->name);
        }
        return $term;
    }
    /**
     * @var ILinksModel Url Links Model that adjust handle links according to current language
     */
    private $linksModel;
    /**
     * @return ILinksModel
     */
    public function getLinksModel(): ILinksModel
    {
        if (!$this->linksModel){
            $isPermalinkStructure = get_option('permalink_structure');
            if ($isPermalinkStructure) {
                $this->linksModel = new LinksModelDirectory($this->defaultLanguageCode, $this->getLanguagesCodesVisibleInUrl());
            } else {
                $this->linksModel = new LinksModelQuery($this->defaultLanguageCode, $this->getLanguagesCodesVisibleInUrl());
            }
        }
        return $this->linksModel;
    }
    /**
     * @var Language current Language object
     */
    private $currentLanguage;
    /**
     * Returns the default Site language
     * @return Language object or null if no language found
     */
    public function getCurrentLanguage()
    {
        return $this->currentLanguage;
    }
    /**
     * @param Language $currentLanguage
     * @return bool Return true if language was set of false otherwise
     */
    public function setCurrentLanguage($currentLanguage): bool
    {
        $result = false;
        if ($currentLanguage instanceof Language && empty($currentLanguage->code) == false) {
            $this->currentLanguage = $currentLanguage;
            $this->currentLanguageCode = $currentLanguage->code;
            $this->currentLanguageLocale = $currentLanguage->locale;
            $result = true;
        }
        return $result;
    }
    /**
     * Changes the language code in url to current language
     * @param string $url url to modify
     * @return string modified url
     */
    public function setLanguageCodeToCurrent(string $url)
    {
        return $this->getLinksModel()->setLanguageCode($url, $this->currentLanguageCode);
    }

    private $currentLanguageCode;
    /**
     * @return mixed
     */
    public function getCurrentLanguageCode()
    {
        return $this->currentLanguageCode;
    }

    private $currentLanguageLocale;
    /**
     * @return mixed
     */
    public function getCurrentLanguageLocale()
    {
        return $this->currentLanguageLocale;
    }

    private $defaultLanguage;
    /**
     * @return Language
     */
    public function getDefaultLanguage(): Language
    {
        return $this->defaultLanguage;
    }

    private $defaultLanguageCode;
    /**
     * @return string
     */
    public function getDefaultLanguageCode(): string
    {
        return $this->defaultLanguageCode;
    }

    private $languagesList = [];
    /**
     * Returns the list of available languages caches the list in array
     * @param string $property - Language object property name
     * @return array list of Language object properties associate with own Language object
     */
    public function getLanguages(string $property = 'code'): array
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
    private $languageCodesVisible = '';
    function getLanguagesCodesVisibleInUrl()
    {
        if (empty($this->languageCodesVisible)) {
            $langLocales = array_keys($this->getLanguages('locale'));
            $commaSeparateLocales = implode(',',$langLocales);
            $langFiles = glob( WP_LANG_DIR  . "/{{$commaSeparateLocales}}.mo", GLOB_BRACE);
            $this->languageCodesVisible = 'en';
            if ($langFiles) {
                foreach ($langFiles as $langFile) {
                    $langFile = basename($langFile, '.mo' );
                    $this->languageCodesVisible .= '|'.substr($langFile,0, 2);
                }
            }
        }
        return $this->languageCodesVisible;
    }
    /**
     * Returns the language by Wordpress Locale
     * @param string $propertyValue value of the queried language property
     * @param string $property name the queried language property
     * @return Language object, null if no language found
     */
    public function getLanguage(string $propertyValue, string $property = 'code')
    {
        $result = null;
        $languagesByProperty = $this->getLanguages($property);
        if ($languagesByProperty && empty($languagesByProperty[$propertyValue]) == false) {
            $result = $languagesByProperty[$propertyValue];
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
            if (isset($textTranslations[$this->currentLanguageCode])) {
                $text = $textTranslations[$this->currentLanguageCode];
            } else if ($this->currentLanguageCode != $this->defaultLanguageCode && isset($textTranslations[$this->defaultLanguageCode])) {
                $text = $textTranslations[$this->defaultLanguageCode];
            }
            //TODO Add case when no language found use $textOfLanguageTagEmpty value if defined as default one language value
        }
        return $text;
    }
}