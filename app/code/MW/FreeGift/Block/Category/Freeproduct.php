<?php
/**
 * Created by PhpStorm.
 * User: lap15
 * Date: 9/4/2015
 * Time: 5:10 PM
 */

namespace MW\FreeGift\Block\Category;


class Freeproduct extends \Magento\Framework\View\Element\Template
{
    protected $_coreRegistry;
    protected $helperFreeGift;
    protected $_resourceRule;
    protected $productRepository;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \MW\FreeGift\Helper\Data $helperFreeGift,
        \Magento\Framework\Registry $coreRegistry,
        \MW\FreeGift\Model\ResourceModel\Rule $resourceRule,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Customer\Model\Session $customerSession,
        array $data = []
    ) {
        $this->helperFreeGift = $helperFreeGift;
        $this->_coreRegistry = $coreRegistry;
        $this->_resourceRule = $resourceRule;
        $this->productRepository = $productRepository;
        $this->_customerSession = $customerSession;
        parent::__construct($context, $data);
    }

    public function getFreeGiftCatalog($current_product = null)
    {
        $storeId = $this->_storeManager->getStore()->getId();
        $websiteId = $this->_storeManager->getStore($storeId)->getWebsiteId();
        $customerGroupId = $this->_customerSession->getCustomerGroupId();
        $dateTs = $this->_localeDate->scopeTimeStamp($storeId);

        $freeGiftCatalogData = array();
        if ($current_product == null) {
            $current_product = $this->getCurrentProduct();
        }

        if ($additionalOption = $current_product->getCustomOption('mw_free_catalog_gift'))
        {
            if($additionalOption->getValue() == 1){
                $ruleData = $this->_resourceRule->getRulesFromProduct($dateTs, $websiteId, $customerGroupId, $current_product->getId());
                /* Sort array by column sort_order */
                array_multisort(array_column($ruleData, 'sort_order'), SORT_ASC, $ruleData);
                $ruleData = $this->_filterByActionStop($ruleData);
                if(count($ruleData)>0){
                    $freeGiftCatalogData = $this->helperFreeGift->getGiftDataByRule($ruleData);
                }
            }
        }
        return $freeGiftCatalogData;
    }

    public function getProductGiftData($productId)
    {
        $product_gift = null;
        $storeId = $this->_storeManager->getStore()->getId();
        if(isset($productId) && $productId != "")
            $product_gift = $this->productRepository->getById($productId, false, $storeId);
        return $product_gift;
    }

    public function getCurrentProduct()
    {
        return $this->_coreRegistry->registry('current_product');
    }

    private function _filterByActionStop($ruleData)
    {
        $result = [];
        foreach($ruleData as $data) {
            $result[$data['rule_id']] = $data;
            if (isset($data['action_stop']) && $data['action_stop'] == '1') {
                break;
            }
        }
        return $result;
    }
}