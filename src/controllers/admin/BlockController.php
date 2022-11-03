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

namespace CubaDevOps\CustomHtml\controllers\admin;

use CubaDevOps\CustomHtml\controllers\AbstractController;
use CubaDevOps\CustomHtml\entities\Block;
use CubaDevOps\CustomHtml\utils\ORM;
use Hook;

class BlockController extends AbstractController
{
    public function create()
    {
        return $this->render(
            $this->getTemplatePath().'block.html.twig',
            [
                'block' => new Block(),
                'hooks' => Hook::getHooks(true, true),
            ]
        );

        /* this for ajax or API response */
        /* return $this->json('Example Admin Controller'); */
    }

    public function delete($id_block)
    {
    }

    public function save($id_block)
    {
        //TODo create Models and migrations
        return $this->redirectToRoute('block_list');
    }

    private function getConfigFormValues($id_block = null): array
    {
        if (!$id_block) {
            return [];
        }
        $block = ORM::table('cb_blocks')->find($id_block)->get();

        return $block->toArray();
    }

    public function list()
    {
        return $this->renderView($this->getTemplatePath().'list.html.twig');
    }

    private function getConfigForm(): array
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Block', 'Modules.CustomHtml.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Enable', 'Modules.CustomHtml.Admin.Label'),
                        'name' => 'ACTIVE',
                        'is_bool' => true,
                        'desc' => $this->trans('Enable or disable this module', 'Modules.CustomHtml.Admin'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->trans('Yes', 'Modules.CustomHtml.Admin.Label'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->trans('No', 'Modules.CustomHtml.Admin.Label'),
                            ],
                        ],
                    ],
                    [
                        'col' => 4,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-envelope"></i>',
                        'desc' => $this->trans('Block name', 'Modules.CustomHtml.Admin'),
                        'name' => 'NAME',
                        'label' => $this->trans('Name', 'Modules.CustomHtml.Admin.Label'),
                    ],
                    [
                        'col' => 4,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-envelope"></i>',
                        'desc' => $this->trans('Display Hook', 'Modules.CustomHtml.Admin'),
                        'name' => 'HOOK',
                        'label' => $this->trans('Hook', 'Modules.CustomHtml.Admin.Label'),
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', 'Modules.CustomHtml.Admin.Label'),
                ],
            ],
        ];
    }
}
