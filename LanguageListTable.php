<?php
if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/screen.php' );
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
class LanguageListTable extends WP_List_Table
{
    private $urlSettingsPage;
    private $languageList;

    public function __construct($languageList, $urlSettingPage)
    {
        parent::__construct(['screen' => 'language']);
        $this->languageList = $languageList;
        $this->urlSettingsPage = $urlSettingPage;
    }
    public function prepare_items()
    {
        $data = [];
        $textEnable = __('Enable');
        $textDisable = __('Disable');
        $textDefault = __('Default');
        /**
         * @var $language Language
        */
        foreach ($this->languageList as $code => $language) {
            $languageAction = '';
            /*if (){
                $languageAction = "<a class='edit' href='{$this->urlSettingsPage}&disable={$code}#languages'>{$textDisable}</a>";
            } else {
                $languageAction = "<a class='edit' href='{$this->urlSettingsPage}&enable={$code}#languages'>{$textEnable}</a>";
            }*/
            $data[] = [
                'name' => "{$language->getFlagHtml()} {$language->name}",
                'code' => "[:{$language->code}]",
                'locale' => "{$language->locale}",
                'facebook' => "{$language->facebook}",
                'action' => $languageAction
            ];
        }
        $this->items = $data;
    }
    public function get_columns()
    {
        return [
            'name' => __('Name'),
            'code' => __('Code'),
            'locale' => __('Wordpress'),
            'facebook' => __('Facebook'),
            'action' => __('Action')
        ];
    }
    protected function column_default($item, $column_name)
    {
        return $item[$column_name];
    }
    protected function get_default_primary_column_name()
    {
        return 'name';
    }
    protected function display_tablenav($which)
    {
    }
}