<?php
require_once 'ILinksModel.php';

/**
 * Links model class for default Permalinks
 * for example mysite.com/?somevar=something&lang=en
 */
class LinksModelQuery implements ILinksModel
{
    const QUERY_LANGUAGE = 'lang';
    /**
     * Used to store the home url before it is filtered
     */
    protected $home;
    /**
     * @var array List of hosts managed on the website
     */
    protected $hosts;

    protected $defaultLanguage = 'en';

    protected $acceptedLanguages = 'en';

    private $patternGetLanguageCode = '';
    /**
     * @inheritdoc
     */
    public function __construct(string $defaultLanguage, string $acceptedLanguages)
    {
        if(empty($defaultLanguage) == false) {
            $this->defaultLanguage = $defaultLanguage;
        }
        if(empty($acceptedLanguages) == false) {
            $this->acceptedLanguages = $acceptedLanguages;
        }
        $this->home = home_url();
        if (empty($this->acceptedLanguages) == false) {
            $queryVar = self::QUERY_LANGUAGE;
            $this->patternGetLanguageCode = "#{$queryVar}=({$this->acceptedLanguages})#";
        }
        $this->hosts = array_values([parse_url($this->home, PHP_URL_HOST)]);
        add_filter('allowed_redirect_hosts', [$this, 'handleAllowedRedirectHosts']);
    }
    /**
     * @inheritdoc
     */
    public function setLanguageCode(string $url, string $languageCode)
    {
        $url = remove_query_arg(self::QUERY_LANGUAGE, $url);
        if (empty($languageCode) == false && $languageCode != $this->defaultLanguage) {
            $url = add_query_arg(self::QUERY_LANGUAGE, $languageCode, $url);
        }
        return $url;
    }
    /**
     * @inheritdoc
     */
    public function getLanguageCode()
    {
        $languageCode = '';
        $url = $this->getUrlFromRequest();
        if (empty($this->patternGetLanguageCode) == false &&
            preg_match($this->patternGetLanguageCode, trailingslashit($url), $matches)) {
            $languageCode = $matches[1];
        }
        return $languageCode;
    }
    /**
     * Get Url that has language parameter with value in it
     */
    function getUrlFromRequest()
    {
        if (empty($_SERVER['HTTP_ORIGIN']) == false &&
            empty($_SERVER['HTTP_REFERER']) == false &&
            $_SERVER['HTTP_ORIGIN'] == $this->home &&
            $_SERVER['HTTP_ORIGIN'] != $_SERVER['HTTP_REFERER']) {
            return $_SERVER['HTTP_REFERER'];
        } else {
            return $_SERVER['REQUEST_URI'];
        }
    }
    function getUrlForLanguage(string $languageCode){
        return $this->setLanguageCode($this->getUrlFromRequest(), $languageCode);
    }
    /**
     * Adds our Domains or SubDomains to allowed hosts for safe redirection
     * @param array $hosts Allowed hosts
     * @return array
     */
    public function handleAllowedRedirectHosts(array $hosts)
    {
        return array_unique(array_merge($hosts, $this->hosts));
    }
}