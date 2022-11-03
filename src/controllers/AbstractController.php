<?php

namespace CubaDevOps\CustomHtml\controllers;

use CubaDevOps\CustomHtml\utils\ORM;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;

abstract class AbstractController extends FrameworkBundleAdminController
{
    /**
     * @var string
     */
    private $template_path = '@Modules/{module}/src/views/';
    /**
     * @var ORM
     */
    protected $orm;

    public function __construct()
    {
        parent::__construct();
        $this->orm = ORM::getInstance();
    }

    public function getTemplatePath(): string
    {
        $module = basename(dirname(__FILE__, 3));

        return str_replace('{module}', $module, $this->template_path);
    }
}
