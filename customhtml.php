<?php
/**
 * Copyright (c) 2022.  <CubaDevOps>.
 *
 * @Author : Carlos Batista <cbatista8a@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

use CubaDevOps\CustomHtml\entities\Block;
use CubaDevOps\CustomHtml\entities\BlockLang;
use CubaDevOps\CustomHtml\migrations\BlockMigration;
use CubaDevOps\CustomHtml\utils\ORM;
use CubaDevOps\CustomHtml\utils\RoutesLoader;
use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CustomHtml extends Module implements WidgetInterface
{
    private const PREFIX = 'CH_';
    /**
     * @var int
     */
    private $shop_context;
    /**
     * @var int|null
     */
    private $shop_group;
    /**
     * @var int
     */
    private $shop_id;

    private $html;
    private $routingConfigLoader;
    private $orm;

    public function __construct()
    {
        $this->name = 'customhtml';
        $this->tab = 'administration';
        $this->version = '1.4.4';
        $this->author = 'CubaDevOps';
        $this->need_instance = 1;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Custom HTML');
        $this->description = $this->l('Add Custom HTML Blocks at any Hook');

        $this->confirmUninstall = $this->l('Are you sure you want uninstall this module?');

        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];

        $this->shop_context = Shop::getContext();
        $this->shop_group = Shop::getContextShopGroupID();
        $this->shop_id = $this->context->shop->id;

        $this->autoload();
        $this->routingConfigLoader = new RoutesLoader($this->local_path.'config');
        $this->orm = ORM::getInstance();
    }

    private function autoload()
    {
        if (file_exists(_PS_MODULE_DIR_.$this->name.'/vendor/autoload.php')) {
            require_once _PS_MODULE_DIR_.$this->name.'/vendor/autoload.php';
        }
    }

    public function install()
    {
        BlockMigration::up();

        return parent::install() &&
            $this->registerHooks();
    }

    //TODO implement install and uninstall sql and tabs

    public function registerHooks()
    {
        $hooks = [
            'header',
            'displayBackOfficeHeader',
            'moduleRoutes',
        ];

        return $this->registerHook($hooks);
    }

    public function uninstall()
    {
        BlockMigration::down();
        $this->deleteConfigValues();

        return parent::uninstall();
    }

    protected function deleteConfigValues()
    {
        $fields = $this->getFormFields();

        foreach ($fields['single_lang'] as $field) {
            Configuration::deleteByName($field);
        }

        foreach ($fields['multi_lang'] as $lang_field) {
            Configuration::deleteByName($lang_field);
        }
    }

    public function getFormFields(): array
    {
        return [
            'single_lang' => [
                self::PREFIX.'ID',
                self::PREFIX.'NAME',
                self::PREFIX.'CLASSES',
                self::PREFIX.'HOOK',
                self::PREFIX.'ACTIVE',
            ],
            'multi_lang' => [
                self::PREFIX.'CONTENT',
            ],
        ];
    }

    /**
     * Load the configuration form.
     */
    public function getContent()
    {
        if (Tools::isSubmit('submitResetcustomhtml_blocks')) {
            $this->redirectToMainController();
        }

        $this->renderConfigHeader();

        $this->postProcess();
        $this->html .= $this->renderForm($this->getConfigForm());
        $this->html .= $this->renderList();

        return $this->html;
    }

    private function renderConfigHeader(): void
    {
        $this->context->smarty->assign('module_dir', $this->_path);
        $this->context->smarty->assign(
            'module_url',
            $this->context->link->getAdminLink(
                'AdminModules',
                true,
                [],
                ['configure' => $this->name]
            )
        );
        $this->html = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');
    }

    /**
     * Save form data.
     */
    protected function postProcess(): void
    {
        if (true === Tools::isSubmit(self::PREFIX.'SAVE_PARAMS')) {
            $this->saveConfigFormValues();
            $this->redirectToMainController();
        }
        if (true === Tools::isSubmit('statuscustomhtml_blocks')) {
            $this->toggleStatus((int) Tools::getValue('id'));
            $this->redirectToMainController();
        }
        if (Tools::getIsset('action') && 'destroy' === Tools::getValue('action')) {
            $this->deleteBlock((int) Tools::getValue('id_block'));
            $this->redirectToMainController();
        }
    }

    private function redirectToMainController()
    {
        Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name])
        );
    }

    protected function saveConfigFormValues(): void
    {
        $fields = $this->getFormFields();
        $fields_data = [];
        foreach ($fields['single_lang'] as $field) {
            $field_name = strtolower(str_replace(self::PREFIX, '', $field));
            $fields_data[$field_name] = 'active' === $field_name ? (int) Tools::getValue($field) : Tools::getValue(
                $field
            );
        }

        if (!Hook::isModuleRegisteredOnHook($this, $fields_data['hook'], $this->shop_id)) {
            $this->registerHook($fields_data['hook'], [$this->shop_id]);
        }

        if (!empty(Tools::getValue(self::PREFIX.'ID'))) {
            $this->updateBlock($fields_data);
        } else {
            /** @var Block $block */
            $block = Block::create($fields_data);
            foreach (Language::getIDs() as $id_lang) {
                $block->contents()->create(
                    [
                        'content' => Tools::getValue(self::PREFIX.'CONTENT'.'_'.$id_lang),
                        'lang_id' => $id_lang,
                    ]
                );
            }
            $block->save();
        }
    }

    protected function updateBlock($fields_data): void
    {
        /** @var Block $block */
        $block = Block::findOrFail((int) Tools::getValue(self::PREFIX.'ID'));

        /** @var BlockLang $blockLang */
        foreach ($block->contents as $blockLang) {
            $blockLang->update(
                [
                    'content' => Tools::getValue(self::PREFIX.'CONTENT'.'_'.$blockLang->lang_id),
                ]
            );
        }
        $block->update($fields_data);
    }

    private function toggleStatus(int $id_block)
    {
        $block = Block::findOrFail($id_block);
        $block->active = !$block->active;
        $block->update();
    }

    private function deleteBlock(int $id_block)
    {
        Block::destroy($id_block);
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     *
     * @param array  $form_config
     * @param string $btn_submit_name
     *
     * @return string
     *
     * @throws PrestaShopException
     */
    protected function renderForm($form_config, $btn_submit_name = null)
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = strtoupper(self::PREFIX.($btn_submit_name ?: 'SAVE_PARAMS'));
        $helper->currentIndex = $this->context->link->getAdminLink(
            'AdminModules',
            true,
            [],
            ['configure' => $this->name]
        );
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$form_config]);
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues(): array
    {
        if (!Tools::getIsset('id_block') || (Tools::getIsset('action') && 'edit' !== Tools::getValue('action'))) {
            return [
                self::PREFIX.'ID' => '',
                self::PREFIX.'NAME' => '',
                self::PREFIX.'CLASSES' => '',
                self::PREFIX.'HOOK' => '',
                self::PREFIX.'ACTIVE' => '',
                self::PREFIX.'CONTENT' => array_fill_keys(Language::getIDs(), ''),
            ];
        }

        /** @var Block $block */
        $block = Block::findOrFail((int) Tools::getValue('id_block'));
        $content = [];
        /* @var BlockLang $content */
        foreach ($block->contents as $block_content) {
            $content[$block_content->lang_id] = $block_content->content;
        }

        return [
            self::PREFIX.'ID' => $block->id,
            self::PREFIX.'NAME' => $block->name,
            self::PREFIX.'CLASSES' => $block->classes,
            self::PREFIX.'ACTIVE' => $block->active,
            self::PREFIX.'HOOK' => $block->hook,
            self::PREFIX.'CONTENT' => $content,
        ];
    }

    private function getConfigForm(): array
    {
        return [
            'form' => [
                'legend' => [
                    'title' => Tools::getIsset('id_block') ? $this->l('Edit Block') : $this->l('New Block'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'name' => self::PREFIX.'ID',
                        'type' => 'hidden',
                        'default' => 0,
                    ],
                    [
                        'col' => 4,
                        'type' => 'text',
                        'desc' => $this->l('Block Name for easily search'),
                        'name' => self::PREFIX.'NAME',
                        'label' => $this->l('Block Name'),
                        'required' => true,
                    ],
                    [
                        'col' => 4,
                        'type' => 'text',
                        'desc' => $this->l('Custom css classes for this block'),
                        'name' => self::PREFIX.'CLASSES',
                        'label' => $this->l('Css Classes'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable'),
                        'name' => self::PREFIX.'ACTIVE',
                        'is_bool' => true,
                        'desc' => $this->l('Enable or disable this Block'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('No'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Hook to attach'),
                        'name' => self::PREFIX.'HOOK',
                        'class' => 'select2',
                        'desc' => $this->l('display this block on selected hook'),
                        'required' => true,
                        'options' => [
                            'query' => Hook::getHooks(true, true),
                            'id' => 'name',
                            'name' => 'name',
                            'default' => [
                                'value' => 'displayHome',
                                'label' => $this->trans('Home', [], 'Admin.Global'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'textarea',
                        'class' => 'tinymce',
                        'desc' => $this->l('Custom Html content'),
                        'name' => self::PREFIX.'CONTENT',
                        'label' => $this->l('Block Content'),
                        'cols' => 100,
                        'rows' => 30,
                        'required' => true,
                        'lang' => true,
                        'autoload_rte' => true,
                    ],
                ],
                'submit' => [
                    'title' => Tools::getIsset('id_block') ? $this->l('Save') : $this->l('Add'),
                ],
            ],
        ];
    }

    public function renderList()
    {
        $hook_list = [];
        foreach (Hook::getHooks(true, true) as $hook) {
            $hook_list[$hook['name']] = $hook['name'];
        }
        $fields_list = [
            'slug' => [
                'title' => $this->trans('Element ID', [], 'Admin.Global'),
                'search' => true,
                'orderby' => true,
            ],
            'name' => [
                'title' => $this->trans('Name', [], 'Admin.Global'),
                'search' => true,
                'orderby' => false,
            ],
            'hook' => [
                'title' => $this->trans('Hook', [], 'Admin.Global'),
                'search' => true,
                'orderby' => true,
                'type' => 'select',
                'list' => $hook_list,
                'filter_key' => 'hook',
            ],
            'active' => [
                'title' => $this->trans('Active', [], 'Admin.Global'),
                'align' => 'center',
                'active' => 'status',
                'type' => 'bool',
                'search' => true,
                'orderby' => true,
            ],
            'updated_at' => [
                'title' => $this->trans('Last Update', [], 'Modules.Customhtml.Admin'),
                'type' => 'datetime',
                'search' => false,
                'orderby' => true,
            ],
        ];

        $helper_list = new HelperList();
        $helper_list->module = $this;
        $helper_list->title = $this->trans('Custom HTML Blocks', [], 'Modules.Customhtml.Admin');
        $helper_list->shopLinkType = '';
        $helper_list->no_link = true;
        $helper_list->show_toolbar = true;
        $helper_list->simple_header = false;
        $helper_list->identifier = 'id';
        $helper_list->table = 'customhtml_blocks';
        $helper_list->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.
            $this->name;
        $helper_list->token = Tools::getAdminTokenLite('AdminModules');
        $helper_list->actions = ['EditBlock', 'DeleteBlock'];

        /* Retrieve list data */
        $leads = $this->getBlocks();
        $helper_list->listTotal = count($leads);

        /* Paginate the result */
        $page = ($page = Tools::getValue('submitFilter'.$helper_list->table)) ? $page : 1;
        $pagination = ($pagination = Tools::getValue($helper_list->table.'_pagination')) ? $pagination : 50;
        $leads = $this->paginateBlocks($leads, $page, $pagination);

        return $helper_list->generateList($leads, $fields_list);
    }

    private function getBlocks(): array
    {
        $blocks = Block::all();

        if (Tools::isSubmit('customhtml_blocksFilter_slug') &&
            $slug = Tools::getValue('customhtml_blocksFilter_slug')) {
            $blocks = $blocks->filter(function ($block) use ($slug) {
                return $block->slug === $slug;
            });
        }
        if (Tools::isSubmit('customhtml_blocksFilter_hook') &&
            $hook = Tools::getValue('customhtml_blocksFilter_hook')) {
            $blocks = $blocks->where('hook', '=', $hook);
        }
        if (Tools::isSubmit('customhtml_blocksFilter_active') &&
            ($active = Tools::getValue('customhtml_blocksFilter_active')) !== '') {
            $blocks = $blocks->where('active', '=', (int) $active);
        }
        if (Tools::isSubmit('customhtml_blocksOrderby')) {
            $order_by = Tools::getValue('customhtml_blocksOrderby');
            $order_way = Tools::getValue('customhtml_blocksOrderway');
            $blocks = $blocks->sortBy([$order_by, $order_way]);
        }

        return $blocks->toArray();
    }

    public function paginateBlocks($blocks, $page = 1, $pagination = 50)
    {
        if (count($blocks) > $pagination) {
            $blocks = array_slice($blocks, $pagination * ($page - 1), $pagination);
        }

        return $blocks;
    }

    public function displayEditBlockLink($token = null, $id = null, $name = null)
    {
        $this->smarty->assign([
                                  'href' => $this->context->link->getAdminLink(
                                      'AdminModules',
                                      true,
                                      [],
                                      [
                                          'configure' => $this->name,
                                          'id_block' => $id,
                                          'action' => 'edit',
                                      ]
                                  ),
                                  'action' => $this->trans('Edit', [], 'Modules.Customhtml.Admin'),
                              ]);

        return $this->display(__FILE__, 'views/templates/admin/list_action_editblock.tpl');
    }

    public function displayDeleteBlockLink($token = null, $id = null, $name = null)
    {
        $this->smarty->assign([
                                  'href' => $this->context->link->getAdminLink(
                                      'AdminModules',
                                      true,
                                      [],
                                      [
                                          'configure' => $this->name,
                                          'id_block' => $id,
                                          'action' => 'destroy',
                                      ]
                                  ),
                                  'action' => $this->trans('Delete', [], 'Modules.Customhtml.Admin'),
                              ]);

        return $this->display(__FILE__, 'views/templates/admin/list_action_deleteblock.tpl');
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookdisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') === $this->name) {
            $this->context->controller->addJS('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js');
            $this->context->controller->addCSS(
                'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css'
            );
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    /**
     * @param $params
     *
     * @return string[]
     *
     * @throws Exception
     */
    public function hookModuleRoutes($params)
    {
        return $this->getFrontControllersRouting();
    }

    /**
     * Load routes automatically from config/front.yml.
     *
     * @return string[]
     *
     * @throws Exception
     */
    protected function getFrontControllersRouting(): array
    {
        if (!file_exists($this->local_path.'config/front.yml')) {
            return [];
        }

        return $this->routingConfigLoader->load('front.yml', true);
    }

    public function renderWidget($hookName, array $configuration)
    {
        $result = $this->getWidgetVariables($hookName, $configuration);
        if (!$result) {
            return;
        }
        //render template and return
        $this->context->smarty->assign(['blocks' => $result]);

        return $this->context->smarty->fetch("module:$this->name/views/templates/hooks/block.tpl");
    }

    public function getWidgetVariables($hookName, array $configuration)
    {
        $blocks = Block::where('hook', $hookName)
            ->where('active', 1)
            ->get();
        $content = [];
        /** @var Block $block */
        foreach ($blocks as $block) {
            $content[$block->id]['id'] = Tools::link_rewrite($block->name);
            $content[$block->id]['classes'] = $block->classes;
            $content[$block->id]['content'] = $block->contents()->where('lang_id', $this->context->language->id)->first(
            )->content;
        }

        return $content;
    }

    protected function updateConfigValue($key, $value)
    {
        switch ($this->shop_context) {
            case Shop::CONTEXT_SHOP:
                Configuration::updateValue($key, $value, true, $this->shop_group, $this->shop_id);
                break;
            case Shop::CONTEXT_GROUP:
                Configuration::updateValue($key, $value, true, $this->shop_group, null);
                break;
            default:
                Configuration::updateValue($key, $value, true);
                break;
        }
    }

    protected function captureMultilingualValue($key)
    {
        $value = [];
        foreach (Language::getIDs(false) as $id_lang) {
            $value[$id_lang] = Tools::getValue($key.'_'.$id_lang);
        }

        return $value;
    }

    protected function getMultilingualValue($key): array
    {
        $value = [];
        foreach (Language::getIDs(false) as $id_lang) {
            $value[$id_lang] = $this->getConfigValue($key, $id_lang);
        }

        return $value;
    }

    protected function getConfigValue($key, $lang = null, $default = null)
    {
        $value = null;
        switch ($this->shop_context) {
            case Shop::CONTEXT_SHOP:
                $value = Configuration::get($key, $lang, $this->shop_group, $this->shop_id, $default);
                break;
            case Shop::CONTEXT_GROUP:
                $value = Configuration::get($key, $lang, $this->shop_group, null, $default);
                break;
            default:
                $value = Configuration::get($key, $lang, null, null, $default);
        }

        return $value;
    }
}
