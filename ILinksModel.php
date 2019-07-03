<?php
interface ILinksModel
{
    /**
     * Constructor
     * @param string $defaultLanguage
     * @param string $acceptedLanguages
     */
    public function __construct(string $defaultLanguage, string $acceptedLanguages);
    /**
     * Set the language code in url
     * @param string $url url to modify
     * @param string $languageCode
     * @return string modified url
     */
    public function setLanguageCode(string $url, string $languageCode);
    /**
     * Gets the language code from the url if present
     * @return string language code or empty string if not found
     */
    public function getLanguageCode();
    /**
     * Get Url that has language parameter with value in it
     */
    public function getUrlFromRequest();

    /**
     * Get Url that has specified language value in it
     * @param string $languageCode value that must be replaced in current url
     */
    public function getUrlForLanguage(string $languageCode);
}