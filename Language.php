<?php

class Language
{
    /**
     * @var string Language Code used in Url
     * @example en
     */
    public $code = '';
    /**
     * @var string Language Locale: WordPress
     * @example en_US
     */
    public $locale = '';
    /**
     * @var string Language Name
     * @example English
     */
    public $name = '';
    /**
     * @const string Language Direction Left to Right
     */
    const DIR_RTL = 'rtl';
    /**
     * @const string Language Direction Right to Left
     */
    const DIR_LTR = 'ltr';
    /**
     * @var string Set Language text write direction
     * @example $dir = self::DIR_LTR;
     * @uses Language::DIR_RTL and Language::DIR_LTR to define text write direction
     */
    public $dir = self::DIR_LTR;
    /**
     * @var bool Check if the language write direction is Right to Left
     */
    private $isRtl = false;
    /**
     * @return bool Return true if write direction is Right to Left or false for oposite case
     */
    public function isRtl(): bool
    {
        return $this->isRtl;
    }
    /**
     * @var string Language Locale: W3C valid locales for display
     * @example en_US
     */
    public $w3c = '';
    /**
     * @var string Language Locale: Facebook Standard
     * @example en_US
     */
    public $facebook;
    /**
     * @var string Code of the flag
     */
    public $flag = '';
    /**
     * @var string Url to the flag Image
     * @example http://sitename.domain/wp-content/plugins/wptranslate/flags/en.png
     */
    private $flagUrl;
    /**
     * @return string
     */
    public function getFlagHtml(): string
    {
        return $this->flagHtml;
    }
    /**
     * @var string Html Markup for the Flag Image
     * @example <img src='http://sitename.domain/wp-content/plugins/wptranslate/flags/en.png' title='English' alt='English' width='' height=''/>
     */
    private $flagHtml;
    /**
     * @return string
     */
    public function getFlagUrl(): string
    {
        return $this->flagUrl;
    }
    /**
     * Sets flag_url and flag properties
     */
    private function setFlag()
    {
        $this->flagUrl = '';
        $file = "flags/{$this->flag}.png";
        if (empty($this->flag) == false && file_exists(__DIR__ . "/{$file}")) {
            /*$fileContent = file_get_contents($file);
            $this->flag_url = 'data:image/png;base64,' . base64_encode($fileContent);*/
            $this->flagUrl = plugins_url($file, __FILE__);
        }
        $this->flagHtml = '';
        //$this->flag_url = esc_url(set_url_scheme( $this->flag_url, 'relative' ) );
        if (empty($this->flagUrl) == false) {
            $escFlagUrl = esc_url_raw($this->flagUrl);
            $escFlagName = esc_attr($this->name);
            $this->flagHtml = "<img src='{$escFlagUrl}' title='{$escFlagName}' alt='{$escFlagName}' width='' height=''/>";
        }
    }
    /**
     * Constructor
     * @param array $language object properties stored as an array
     */
    public function __construct(array $language)
    {
        // Build the object from all properties stored as an array
        foreach ($language as $prop => $value) {
            $this->$prop = $value;
        }
        $this->isRtl = ($this->dir == self::DIR_RTL);
        if (empty($this->facebook) == false && empty($this->locale)) {
            $this->locale = $this->facebook;
        }
        if (isset($this->w3c) == false) {
            $this->w3c = str_replace('_', '-', $this->locale);
        }
        $this->setFlag();
    }
}