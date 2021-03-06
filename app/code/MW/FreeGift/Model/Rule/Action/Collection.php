<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace MW\FreeGift\Model\Rule\Action;

class Collection extends \Magento\Rule\Model\Action\Collection
{
    /**
     * @param \Magento\Framework\View\Asset\Repository $assetRepo
     * @param \Magento\Framework\View\LayoutInterface $layout
     * @param \Magento\Rule\Model\ActionFactory $actionFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Asset\Repository $assetRepo,
        \Magento\Framework\View\LayoutInterface $layout,
        \Magento\Rule\Model\ActionFactory $actionFactory,
        array $data = []
    ) {
        parent::__construct($assetRepo, $layout, $actionFactory, $data);
        $this->setType('MW\FreeGift\Model\Rule\Action\Collection');
    }

    /**
     * @return array
     */
    public function getNewChildSelectOptions()
    {
        $actions = parent::getNewChildSelectOptions();
        $actions = array_merge_recursive(
            $actions,
            [
                ['value' => 'MW\FreeGift\Model\Rule\Action\Product', 'label' => __('Update the Product')]
            ]
        );
        return $actions;
    }
}
