<?php
require_once 'LinksModelQuery.php';

/**
 * Links model for use when the language code is added in url as a directory
 * for example mysite.com/en/something
 */
class LinksModelDirectory extends LinksModelQuery
{
    protected $root = '';
    protected $index = 'index.php';
    protected $homeRelative = '';
    /**
     * @inheritdoc
     */
    public function __construct(string $defaultLanguage, string $acceptedLanguages)
    {
        parent::__construct($defaultLanguage, $acceptedLanguages);
        $this->homeRelative = home_url('/', 'relative');
        // Inspired from wp-includes/rewrite.php
        $permalinkStructure = get_option('permalink_structure');
        if (preg_match("#^/*{$this->index}#", $permalinkStructure)) {
            $this->root = "{$this->index}/";
        }
        // Make sure to prepare rewrite rules when flushing
        if (has_filter('rewrite_rules_array', [$this, 'handleRewriteRulesArray']) == false) {
            add_filter('rewrite_rules_array', [$this, 'handleRewriteRulesArray']);
        }
    }
    /**
     * @inheritdoc
     */
    public function setLanguageCode(string $url, string $languageCode)
    {
        if (empty($this->acceptedLanguages) == false &&
            empty($languageCode) == false) {
            if (strpos($url, '://') === false) {
                $root = $this->homeRelative . $this->root;
            } else {
                $root = $this->home . '/' . $this->root;
            }
            $escRoot = str_replace('/', '\/', $root);
            //Delete Language Code
            $patternLangCodeReplace = "#{$escRoot}({$this->acceptedLanguages})(\/|$)#";
            $url = preg_replace($patternLangCodeReplace, $root, $url);
            //Add Language Code
            if ($languageCode == $this->defaultLanguage) {
                $new = $root;
            } else {
                $new = "{$root}{$languageCode}/";
            }
            if (strpos($url, $new) === false) {
                $patternLangCodeAdd = "#{$escRoot}#";
                $url = preg_replace($patternLangCodeAdd, $new, $url, 1); // Only once
            }
        }
        return $url;
    }
    /**
     * @inheritdoc
     */
    public function getLanguageCode()
    {
        $url = $this->getUrlFromRequest();
        if (strpos($url, '://') == false) {
            $root = $this->homeRelative . $this->root;
        } else {
            $root = $this->home . '/' . $this->root;
        }
        $urlHome = parse_url($root, PHP_URL_PATH);
        $urlHome = str_replace('/', '\/', $urlHome);
        $urlPath = parse_url($url, PHP_URL_PATH);
        $languageCode = '';
        if (empty($this->acceptedLanguages) == false &&
            preg_match("#{$urlHome}({$this->acceptedLanguages})(\/|$)#", trailingslashit($urlPath), $matches)) {
            $languageCode = $matches[1];
        }
        return $languageCode;
    }
    /**
     * The rewrite rules ! always make sure the default language is at the end in case the language information
     * is hidden for default language http://wordpress.org/support/topic/plugin-polylang-rewrite-rules-not-correct
     * @param array $rules rewrite rules
     * @return array modified rewrite rules
     */
    public function handleRewriteRulesArray($rules)
    {
        //TODO Investigate how to handle case when default language not followed by / Ex: sitename.com/en vs sitename.com/en/
        //WP_Rewrite::
        $languageSlug = '';
        $modifiedRules = [];
        if (empty($this->acceptedLanguages) == false) {
            $languageSlug = "{$this->root}({$this->acceptedLanguages})/";
            $modifiedRules["{$languageSlug}?$"] = $this->index . '?lang=$matches[1]'; //The home rewrite rule
        }
        //$patternExcludeJsonApi = '(wc-auth|wc-api|wc-json|wp-json|wp-app|wp-register|robots)';
        $patternExcludeJsonApi = '(wp-app|wp-register|robots)';
        $patternExcludeFeed = '(feed|trackback)';
        foreach ($rules as $key => $rule) {
            if (!preg_match($patternExcludeFeed, $key)) {
                if ($languageSlug != '' && !preg_match($patternExcludeJsonApi, $key)) {
                    $ruleKey = $languageSlug . str_replace($this->root, '', ltrim($key, '^'));
                    $modifiedRules[$ruleKey] = str_replace(['[8]', '[7]', '[6]', '[5]', '[4]', '[3]', '[2]', '[1]', '?'],
                        ['[9]', '[8]', '[7]', '[6]', '[5]', '[4]', '[3]', '[2]', '?lang=$matches[1]&'], $rule);
                    //If not root rules Hide Default language
                    $modifiedRules[$key] = str_replace('?', "?lang={$this->defaultLanguage}&", $rule);
                } else {
                    // Unmodified rules
                    $modifiedRules[$key] = $rule;
                }
            }
        }
        return $modifiedRules;
    }
}