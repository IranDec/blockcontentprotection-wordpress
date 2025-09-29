<?php
if (!defined('_PS_VERSION_')) exit;

class BlockContentProtection extends Module
{
    public function __construct()
    {
        $this->name = 'blockcontentprotection';
        $this->tab = 'front_office_features';
        $this->version = '1.2.3';
        $this->author = 'Mohammad Babaei';
        $this->website = 'https://adschi.com';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Content Protection with Settings - ADSCHI');
        $this->description = $this->l('Protect your site content with customizable options.');
        $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('displayBackOfficeHeader') &&
            Configuration::updateValue('BCP_DISABLE_RIGHTCLICK', true) &&
            Configuration::updateValue('BCP_DISABLE_DEVTOOLS', true) &&
            Configuration::updateValue('BCP_DISABLE_SCREENSHOT', true) &&
            Configuration::updateValue('BCP_DISABLE_VIDEO_DOWNLOAD', true) &&
            Configuration::updateValue('BCP_DISABLE_DBLCLICK_COPY', true) &&
            Configuration::updateValue('BCP_DISABLE_TEXT_SELECTION', true);
    }

    public function uninstall()
    {
        return parent::uninstall() &&
            Configuration::deleteByName('BCP_DISABLE_RIGHTCLICK') &&
            Configuration::deleteByName('BCP_DISABLE_DEVTOOLS') &&
            Configuration::deleteByName('BCP_DISABLE_SCREENSHOT') &&
            Configuration::deleteByName('BCP_DISABLE_VIDEO_DOWNLOAD') &&
            Configuration::deleteByName('BCP_DISABLE_DBLCLICK_COPY') &&
            Configuration::deleteByName('BCP_DISABLE_TEXT_SELECTION');
    }

    public function hookHeader()
    {
        $this->context->controller->registerJavascript(
            'module-blockcontentprotection-js',
            'modules/'.$this->name.'/views/js/protect.js',
            ['position' => 'bottom', 'priority' => 150]
        );

        Media::addJsDef([
            'BCP_DISABLE_RIGHTCLICK' => Configuration::get('BCP_DISABLE_RIGHTCLICK'),
            'BCP_DISABLE_DEVTOOLS' => Configuration::get('BCP_DISABLE_DEVTOOLS'),
            'BCP_DISABLE_SCREENSHOT' => Configuration::get('BCP_DISABLE_SCREENSHOT'),
            'BCP_DISABLE_VIDEO_DOWNLOAD' => Configuration::get('BCP_DISABLE_VIDEO_DOWNLOAD'),
            'BCP_DISABLE_DBLCLICK_COPY' => Configuration::get('BCP_DISABLE_DBLCLICK_COPY'),
            'BCP_DISABLE_TEXT_SELECTION' => Configuration::get('BCP_DISABLE_TEXT_SELECTION'),
        ]);
    }

    public function hookDisplayBackOfficeHeader()
    {
        $this->context->controller->addCSS($this->_path.'views/css/admin.css');
    }

    public function getContent()
    {
        $output = '<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
            <img src="'.$this->_path.'logo.png" style="height: 50px;" alt="Module Logo">
            <strong>Developed by Mohammad Babaei - <a href="https://adschi.com" target="_blank">adschi.com</a></strong>
        </div>';

        if (Tools::isSubmit('submitBlockContentProtection')) {
            Configuration::updateValue('BCP_DISABLE_RIGHTCLICK', (bool)Tools::getValue('BCP_DISABLE_RIGHTCLICK'));
            Configuration::updateValue('BCP_DISABLE_DEVTOOLS', (bool)Tools::getValue('BCP_DISABLE_DEVTOOLS'));
            Configuration::updateValue('BCP_DISABLE_SCREENSHOT', (bool)Tools::getValue('BCP_DISABLE_SCREENSHOT'));
            Configuration::updateValue('BCP_DISABLE_VIDEO_DOWNLOAD', (bool)Tools::getValue('BCP_DISABLE_VIDEO_DOWNLOAD'));
            Configuration::updateValue('BCP_DISABLE_DBLCLICK_COPY', (bool)Tools::getValue('BCP_DISABLE_DBLCLICK_COPY'));
            Configuration::updateValue('BCP_DISABLE_TEXT_SELECTION', (bool)Tools::getValue('BCP_DISABLE_TEXT_SELECTION'));
            $output .= $this->displayConfirmation($this->l('Settings updated'));
        }

        return $output . $this->renderForm();
    }

    protected function renderForm()
    {
        $fields_form = [
            'form' => [
                'legend' => ['title' => $this->l('Settings')],
                'submit' => ['title' => $this->l('Save')],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Disable Right Click'),
                        'name' => 'BCP_DISABLE_RIGHTCLICK',
                        'is_bool' => true,
                        'values' => [['id' => 'active_on', 'value' => 1], ['id' => 'active_off', 'value' => 0]],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Disable Developer Tools'),
                        'name' => 'BCP_DISABLE_DEVTOOLS',
                        'is_bool' => true,
                        'values' => [['id' => 'active_on', 'value' => 1], ['id' => 'active_off', 'value' => 0]],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Disable Screenshots'),
                        'name' => 'BCP_DISABLE_SCREENSHOT',
                        'is_bool' => true,
                        'values' => [['id' => 'active_on', 'value' => 1], ['id' => 'active_off', 'value' => 0]],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Disable Video Download'),
                        'name' => 'BCP_DISABLE_VIDEO_DOWNLOAD',
                        'is_bool' => true,
                        'values' => [['id' => 'active_on', 'value' => 1], ['id' => 'active_off', 'value' => 0]],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Disable Double Click + Copy'),
                        'name' => 'BCP_DISABLE_DBLCLICK_COPY',
                        'is_bool' => true,
                        'values' => [['id' => 'active_on', 'value' => 1], ['id' => 'active_off', 'value' => 0]],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Disable Text Selection'),
                        'name' => 'BCP_DISABLE_TEXT_SELECTION',
                        'is_bool' => true,
                        'values' => [['id' => 'active_on', 'value' => 1], ['id' => 'active_off', 'value' => 0]],
                    ]
                ]
            ]
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int)Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->title = $this->displayName;
        $helper->show_cancel_button = false;
        $helper->submit_action = 'submitBlockContentProtection';
        $helper->fields_value = [
            'BCP_DISABLE_RIGHTCLICK' => Configuration::get('BCP_DISABLE_RIGHTCLICK'),
            'BCP_DISABLE_DEVTOOLS' => Configuration::get('BCP_DISABLE_DEVTOOLS'),
            'BCP_DISABLE_SCREENSHOT' => Configuration::get('BCP_DISABLE_SCREENSHOT'),
            'BCP_DISABLE_VIDEO_DOWNLOAD' => Configuration::get('BCP_DISABLE_VIDEO_DOWNLOAD'),
            'BCP_DISABLE_DBLCLICK_COPY' => Configuration::get('BCP_DISABLE_DBLCLICK_COPY'),
            'BCP_DISABLE_TEXT_SELECTION' => Configuration::get('BCP_DISABLE_TEXT_SELECTION'),
        ];

        return $helper->generateForm([$fields_form]);
    }
}