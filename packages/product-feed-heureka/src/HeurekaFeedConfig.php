<?php

namespace Shopsys\ProductFeed\HeurekaBundle;

use Shopsys\Plugin\PluginDataStorageProviderInterface;
use Shopsys\ProductFeed\DomainConfigInterface;
use Shopsys\ProductFeed\FeedConfigInterface;
use Shopsys\ProductFeed\HeurekaCategoryNameProviderInterface;
use Shopsys\ProductFeed\StandardFeedItemInterface;

class HeurekaFeedConfig implements FeedConfigInterface
{

    /**
     * @var \Shopsys\ProductFeed\HeurekaCategoryNameProviderInterface
     */
    private $heurekaCategoryNameProvider;

    /**
     * @var \Shopsys\Plugin\PluginDataStorageProviderInterface
     */
    private $pluginDataStorageProvider;

    public function __construct(
        HeurekaCategoryNameProviderInterface $heurekaCategoryNameProvider,
        PluginDataStorageProviderInterface $pluginDataStorageProvider
    ) {
        $this->heurekaCategoryNameProvider = $heurekaCategoryNameProvider;
        $this->pluginDataStorageProvider = $pluginDataStorageProvider;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return 'Heureka';
    }

    /**
     * @return string
     */
    public function getFeedName()
    {
        return 'heureka';
    }

    /**
     * @return string
     */
    public function getTemplateFilepath()
    {
        return '@ShopsysProductFeedHeureka/feed.xml.twig';
    }

    /**
     * @return string|null
     */
    public function getAdditionalInformation()
    {
        return null;
    }

    /**
     * @param \Shopsys\ProductFeed\StandardFeedItemInterface[] $items
     * @param \Shopsys\ProductFeed\DomainConfigInterface $domainConfig
     * @return \Shopsys\ProductFeed\StandardFeedItemInterface[]
     */
    public function processItems(array $items, DomainConfigInterface $domainConfig)
    {
        $sellableItems = array_filter($items, [$this, 'isItemSellable']);
        $productsDataById = $this->getProductsDataById($sellableItems);

        foreach ($sellableItems as $key => $item) {
            $cpc = $productsDataById[$item->getId()]['cpc'][$domainConfig->getId()] ?? null;
            $item->setCustomValue('cpc', $cpc);

            $categoryName = $this->heurekaCategoryNameProvider->getHeurekaCategoryNameForItem($item, $domainConfig);
            $item->setCustomValue('category_name', $categoryName);
        }

        return $sellableItems;
    }

    /**
     * @param array $items
     * @return array
     */
    private function getProductsDataById(array $items)
    {
        $productIds = [];
        foreach ($items as $item) {
            $productIds[] = $item->getId();
        }

        $productDataStorage = $this->pluginDataStorageProvider
            ->getDataStorage(ShopsysProductFeedHeurekaBundle::class, 'product');

        return $productDataStorage->getMultiple($productIds);
    }

    /**
     * @param \Shopsys\ProductFeed\StandardFeedItemInterface $item
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod) method is used through array_filter
     */
    private function isItemSellable(StandardFeedItemInterface $item)
    {
        return !$item->isSellingDenied();
    }
}
