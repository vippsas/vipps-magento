<?php
/**
 * Copyright 2020 Vipps
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
 * documentation files (the "Software"), to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software,
 * and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED
 * TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
 * CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 */
namespace Vipps\Payment\Model\Profiling;

use Magento\Framework\Api\SearchCriteriaInterface;
use Vipps\Payment\Api\Profiling\Data\ItemInterface;
use Vipps\Payment\Api\Profiling\Data\ItemInterfaceFactory;
use Vipps\Payment\Api\Profiling\ItemRepositoryInterface;
use Vipps\Payment\Api\Profiling\Data\ItemSearchResultsInterfaceFactory;
use Vipps\Payment\Api\Profiling\Data\ItemSearchResultsInterface;
use Vipps\Payment\Model\ResourceModel\Profiling\Item as ItemResource;
use Vipps\Payment\Model\ResourceModel\Profiling\Item\Collection;
use Vipps\Payment\Model\ResourceModel\Profiling\Item\CollectionFactory;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Class ItemRepository
 * @package Vipps\Payment\Model\Profiling
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ItemRepository implements ItemRepositoryInterface
{
    /**
     * @var ItemResource
     */
    private $resource;

    /**
     * @var ItemFactory
     */
    private $itemFactory;

    /**
     * @var ItemInterfaceFactory
     */
    private $dataItemFactory;

    /**
     * @var ItemSearchResultsInterfaceFactory
     */
    private $searchResultsFactory;

    /**
     * @var DataObjectHelper
     */
    private $dataObjectHelper;

    /**
     * @var DataObjectProcessor
     */
    private $dataObjectProcessor;

    /**
     * ItemRepository constructor.
     *
     * @param ItemResource $resource
     * @param ItemFactory $itemFactory
     * @param ItemInterfaceFactory $dataItemFactory
     * @param ItemSearchResultsInterfaceFactory $searchResultsFactory
     * @param DataObjectHelper $dataObjectHelper
     * @param DataObjectProcessor $dataObjectProcessor
     */
    public function __construct(
        ItemResource $resource,
        ItemFactory $itemFactory,
        ItemInterfaceFactory $dataItemFactory,
        ItemSearchResultsInterfaceFactory $searchResultsFactory,
        DataObjectHelper $dataObjectHelper,
        DataObjectProcessor $dataObjectProcessor
    ) {
        $this->resource = $resource;
        $this->itemFactory = $itemFactory;
        $this->dataItemFactory = $dataItemFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->dataObjectProcessor = $dataObjectProcessor;
    }

    /**
     * @param ItemInterface $item
     *
     * @return bool|ItemInterface
     * @throws CouldNotSaveException
     */
    public function save(ItemInterface $item)
    {
        try {
            $data = $this->dataObjectProcessor->buildOutputDataArray($item, ItemInterface::class);
            $item = $this->itemFactory->create();
            $item->setData($data);

            $this->resource->save($item);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }
        return $item;
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     *
     * @return \Magento\Eav\Api\Data\AttributeGroupSearchResultsInterface|ItemSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        /** @var ItemSearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);

        /** @var Collection $collection */
        $collection = $this->itemFactory->create()->getCollection();

        $searchResults->setTotalCount($collection->getSize());
        $sortOrders = $searchCriteria->getSortOrders();
        if ($sortOrders) {
            /** @var SortOrder $sortOrder */
            foreach ($searchCriteria->getSortOrders() as $sortOrder) {
                $collection->addOrder(
                    $sortOrder->getField(),
                    ($sortOrder->getDirection() == SortOrder::SORT_ASC) ? 'ASC' : 'DESC'
                );
            }
        }
        $collection->setCurPage($searchCriteria->getCurrentPage());
        $collection->setPageSize($searchCriteria->getPageSize());
        $items = [];
        /** @var Item $itemModel */
        foreach ($collection as $itemModel) {
            $itemDataObject = $this->dataItemFactory->create();
            $this->dataObjectHelper->populateWithArray(
                $itemDataObject,
                $itemModel->getData(),
                ItemInterface::class
            );
            $items[] = $itemDataObject;
        }
        $searchResults->setItems($items);
        return $searchResults;
    }

    /**
     * @param $itemId
     *
     * @return ItemInterface
     * @throws NoSuchEntityException
     */
    public function get($itemId)
    {
        $item = $this->itemFactory->create();
        $this->resource->load($item, $itemId);
        if (!$item->getId()) {
            throw new NoSuchEntityException(__('Profiling item with id "%1" does not exist.', $itemId));
        }
        return $item;
    }

    /**
     * @param ItemInterface $item
     *
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(ItemInterface $item)
    {
        try {
            $this->resource->deleteById($item->getEntityId());
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__($exception->getMessage()));
        }
        return true;
    }

    /**
     * @param int $itemId
     *
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById($itemId)
    {
        return $this->delete($this->get($itemId));
    }
}
