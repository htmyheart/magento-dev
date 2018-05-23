<?php
namespace MW\FreeGift\Model\ResourceModel;

use Magento\Catalog\Model\Product;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Pricing\PriceCurrencyInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Rule extends \Magento\Rule\Model\ResourceModel\AbstractResource
{
    /**
     * Store number of seconds in a day
     */
    const SECONDS_IN_DAY = 86400;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * Store associated with rule entities information map
     *
     * @var array
     */
    protected $_associatedEntitiesMap = [
        'website' => [
            'associations_table' => 'mw_freegift_rule_website',
            'rule_id_field' => 'rule_id',
            'entity_id_field' => 'website_id',
        ],
        'customer_group' => [
            'associations_table' => 'mw_freegift_rule_customer_group',
            'rule_id_field' => 'rule_id',
            'entity_id_field' => 'customer_group_id',
        ],
    ];

    /**
     * Catalog rule data
     *
     * @var \Magento\CatalogRule\Helper\Data
     */
    protected $_catalogRuleData = null;

    /**
     * Core event manager proxy
     *
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $_eventManager = null;

    /**
     * @var \Magento\Eav\Model\Config
     */
    protected $_eavConfig;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $_coreDate;

    /**
     * @var \Magento\Catalog\Model\Product\ConditionFactory
     */
    protected $_conditionFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Framework\Stdlib\DateTime
     */
    protected $dateTime;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var \Magento\Framework\EntityManager\EntityManager
     */
    protected $entityManager;

    /**
     * Rule constructor.
     * @param \Magento\Framework\Model\ResourceModel\Db\Context $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param Product\ConditionFactory $conditionFactory
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $coreDate
     * @param \Magento\Eav\Model\Config $eavConfig
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\CatalogRule\Helper\Data $catalogRuleData
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Stdlib\DateTime $dateTime
     * @param PriceCurrencyInterface $priceCurrency
     * @param null $connectionName
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Product\ConditionFactory $conditionFactory,
        \Magento\Framework\Stdlib\DateTime\DateTime $coreDate,
        \Magento\Eav\Model\Config $eavConfig,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \MW\FreeGift\Helper\Data $catalogRuleData,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Stdlib\DateTime $dateTime,
        PriceCurrencyInterface $priceCurrency,
        $connectionName = null
    ) {
        $this->_storeManager = $storeManager;
        $this->_conditionFactory = $conditionFactory;
        $this->_coreDate = $coreDate;
        $this->_eavConfig = $eavConfig;
        $this->_eventManager = $eventManager;
        $this->_catalogRuleData = $catalogRuleData;
        $this->_logger = $logger;
        $this->dateTime = $dateTime;
        $this->priceCurrency = $priceCurrency;
//        $this->_associatedEntitiesMap = $this->getAssociatedEntitiesMap();
        parent::__construct($context, $connectionName);
    }

    /**
     * Initialize main table and table id field
     *
     * @return void
     * @codeCoverageIgnore
     */
    protected function _construct()
    {
        $this->_init('mw_freegift_rule', 'rule_id');
    }

    /**
     * @param \Magento\Framework\Model\AbstractModel $rule
     * @return $this
     */
    protected function _afterDelete(\Magento\Framework\Model\AbstractModel $rule)
    {
        $connection = $this->getConnection();
        $connection->delete(
            $this->getTable('mw_freegift_rule_product'),
            ['rule_id=?' => $rule->getId()]
        );
        $connection->delete(
            $this->getTable('mw_freegift_rule_group_website'),
            ['rule_id=?' => $rule->getId()]
        );
        return parent::_afterDelete($rule);
    }

    /**
     * Add customer group ids and website ids to rule data after load
     *
     * @param \Magento\Framework\Model\AbstractModel $object
     * @return \Magento\Framework\Model\ResourceModel\Db\AbstractDb
     */
    protected function _afterLoad(AbstractModel $object)
    {
        $object->setData('customer_group_ids', (array)$this->getCustomerGroupIds($object->getId()));
        $object->setData('website_ids', (array)$this->getWebsiteIds($object->getId()));
        return parent::_afterLoad($object);
    }

    /**
     * Bind catalog rule to customer group(s) and website(s).
     * Update products which are matched for rule.
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function _afterSave(AbstractModel $object)
    {
//        echo "_afterSave"; exit;
        if ($object->hasWebsiteIds()) {
            $websiteIds = $object->getWebsiteIds();
            if (!is_array($websiteIds)) {
                $websiteIds = explode(',', (string)$websiteIds);
            }
            $this->bindRuleToEntity($object->getId(), $websiteIds, 'website');
        }
        if ($object->hasCustomerGroupIds()) {
            $customerGroupIds = $object->getCustomerGroupIds();
            if (!is_array($customerGroupIds)) {
                $customerGroupIds = explode(',', (string)$customerGroupIds);
            }
            $this->bindRuleToEntity($object->getId(), $customerGroupIds, 'customer_group');
        }

        parent::_afterSave($object);
        return $this;
    }


    /**
     * Get catalog rules product price for specific date, website and
     * customer group
     *
     * @param \DateTime $date
     * @param int $wId
     * @param int $gId
     * @param int $pId
     * @return float|false
     */
    public function getRulePrice($date, $wId, $gId, $pId)
    {
        $data = $this->getRulePrices($date, $wId, $gId, [$pId]);
        if (isset($data[$pId])) {
            return $data[$pId];
        }

        return false;
    }

    /**
     * Retrieve product prices by catalog rule for specific date, website and customer group
     * Collect data with  product Id => price pairs
     *
     * @param \DateTime $date
     * @param int $websiteId
     * @param int $customerGroupId
     * @param array $productIds
     * @return array
     */
    public function getRulePrices(\DateTime $date, $websiteId, $customerGroupId, $productIds)
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable('mw_freegift_rule_product_price'), ['product_id', 'rule_price'])
            ->where('rule_date = ?', $date->format('Y-m-d'))
            ->where('website_id = ?', $websiteId)
            ->where('customer_group_id = ?', $customerGroupId)
            ->where('product_id IN(?)', $productIds);

        return $connection->fetchPairs($select);
    }

    /**
     * Get active rule data based on few filters
     *
     * @param int|string $date
     * @param int $websiteId
     * @param int $customerGroupId
     * @param int $productId
     * @return array
     */
    public function getRulesFromProduct($date, $websiteId, $customerGroupId, $productId)
    {
        $connection = $this->getConnection();
        if (is_string($date)) {
            $date = strtotime($date);
        }
        $select = $connection->select()
            ->from(['rp' => $this->getTable('mw_freegift_rule_product')])
            ->where('website_id = ?', $websiteId)
            ->where('customer_group_id = ?', $customerGroupId)
            ->where('rule_gift_ids <> ?', "")
            ->where('product_id = ?', $productId)
            ->where('from_time = 0 or from_time < ?', $date)
            ->where('to_time = 0 or to_time > ?', $date);

        $select->joinLeft(
            ['r' =>  $this->getTable('mw_freegift_rule')],
            'rp.rule_id=r.rule_id ',
            'r.name'
        );
        return $connection->fetchAll($select);
    }

    /**
     * @param \Magento\Framework\Model\AbstractModel $object
     * @param mixed $value
     * @param string $field
     * @return $this
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
//    public function load(\Magento\Framework\Model\AbstractModel $object, $value, $field = null)
//    {
//        $this->getEntityManager()->load($object, $value);
//        return $this;
//    }

    /**
     * @param AbstractModel $object
     * @return $this
     * @throws \Exception
     */
//    public function save(\Magento\Framework\Model\AbstractModel $object)
//    {
//        $this->getEntityManager()->save($object);
//        return $this;
//    }

    /**
     * Delete the object
     *
     * @param \Magento\Framework\Model\AbstractModel $object
     * @return $this
     * @throws \Exception
     */
//    public function delete(AbstractModel $object)
//    {
//        $this->getEntityManager()->delete($object);
//        return $this;
//    }

    /**
     * @return array
     * @deprecated
     */
//    private function getAssociatedEntitiesMap()
//    {
//        if (!$this->_associatedEntitiesMap) {
//            $this->_associatedEntitiesMap = \Magento\Framework\App\ObjectManager::getInstance()
//                ->get('Magento\CatalogRule\Model\ResourceModel\Rule\AssociatedEntityMap')
//                ->getData();
//        }
//        return $this->_associatedEntitiesMap;
//    }

    /**
     * @return \Magento\Framework\EntityManager\EntityManager
     * @deprecated
     */
//    private function getEntityManager()
//    {
//        if (null === $this->entityManager) {
//            $this->entityManager = \Magento\Framework\App\ObjectManager::getInstance()
//                ->get(\Magento\Framework\EntityManager\EntityManager::class);
//        }
//        return $this->entityManager;
//    }

    /**
     * Get active rule data based on few filters
     *
     * @param int|string $date
     * @param int $websiteId
     * @param int $customerGroupId
     * @param int $productId
     * @return array
     */
    public function getRulesFromProductGift($rule_id, $rule_gift_ids,$website_id = null,$customer_group_id = null, $rule_product_id = null)
    {
        $connection = $this->getConnection();
        $select = $connection->select()->from(
            ['rp' => $this->getTable('mw_freegift_rule_product')]
        )->where(
            'rp.rule_id = ?',
            $rule_id
        )->where(
            'rp.rule_gift_ids = ?',
            $rule_gift_ids
        );

        if(isset($customer_group_id)){
            $select = $select->where(
                'rp.website_id = ?',
                $website_id
            );
        }

        if(isset($customer_group_id)){
            $select = $select->where(
                'rp.customer_group_id = ?',
                $customer_group_id
            );
        }
        if(isset($rule_product_id)){
            $select = $select->where(
                'rp.rule_product_id = ?',
                $rule_product_id
            );
        }

        $select->joinLeft(
            ['r' =>  $this->getTable('mw_freegift_rule')],
            'rp.rule_id=r.rule_id ',
            'r.name'
        );

        return $connection->fetchRow($select);
    }


}
