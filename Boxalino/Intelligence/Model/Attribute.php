<?php

namespace Boxalino\Intelligence\Model;

/**
 * Class Attribute
 * @package Boxalino\Intelligence\Model
 */
class Attribute extends \Magento\Catalog\Model\Layer\Filter\Attribute {

    /**
     * @var null
     */
    private $bxFacets = null;

    /**
     * @var array
     */
    private $fieldName = array();

    /**
     * @var \Boxalino\Intelligence\Helper\Data
     */
    private $bxDataHelper;

    /**
     * @var \Magento\Catalog\Model\CategoryFactory
     */
    private $categoryFactory;

    /**
     * Attribute constructor.
     * @param \Magento\Catalog\Model\Layer\Filter\ItemFactory $filterItemFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Model\Layer $layer
     * @param \Magento\Catalog\Model\Layer\Filter\Item\DataBuilder $itemDataBuilder
     * @param \Magento\Catalog\Model\ResourceModel\Layer\Filter\AttributeFactory $filterAttributeFactory
     * @param \Magento\Framework\Stdlib\StringUtils $string
     * @param \Magento\Framework\Filter\StripTags $tagFilter
     * @param \Boxalino\Intelligence\Helper\Data $bxDataHelper
     * @param \Magento\Catalog\Model\CategoryFactory $categoryFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Catalog\Model\Layer\Filter\ItemFactory $filterItemFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Layer $layer,
        \Magento\Catalog\Model\Layer\Filter\Item\DataBuilder $itemDataBuilder,
        \Magento\Catalog\Model\ResourceModel\Layer\Filter\AttributeFactory $filterAttributeFactory,
        \Magento\Framework\Stdlib\StringUtils $string,
        \Magento\Framework\Filter\StripTags $tagFilter,
        \Boxalino\Intelligence\Helper\Data $bxDataHelper,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory,
        array $data=[]
    )
    {
        $this->categoryFactory = $categoryFactory;
        $this->bxDataHelper = $bxDataHelper;
        parent::__construct($filterItemFactory, $storeManager, $layer, $itemDataBuilder, $filterAttributeFactory, $string, $tagFilter, $data);
    }

    /**
     * @param $bxFacets
     */
    public function setFacets($bxFacets) {
        
        $this->bxFacets = $bxFacets;
    }

    /**
     * @param $fieldName
     */
    public function setFieldName($fieldName) {
        
        $this->fieldName = $fieldName;
    }

    /**
     * @return mixed
     */
    public function getName(){
        
        return $this->bxFacets->getFacetLabel($this->fieldName);
    }

    /**
     * @return array
     */
    public function getFieldName(){
        
        return $this->fieldName;
    }

    /**
     * @return $this|\Magento\Catalog\Model\Layer\Filter\AbstractFilter
     */
    public function _initItems(){
        
        if($this->bxDataHelper->isFilterLayoutEnabled()){
            $data = $this->_getItemsData();
            $items = [];
            foreach ($data as $itemData) {
                $selected = isset($itemData['selected']) ? $itemData['selected'] : null;
                $type = isset($itemData['type']) ? $itemData['type'] : null;
                $items[] = $this->_createItem($itemData['label'], $itemData['value'], $itemData['count'], $selected, $type);
            }
            $this->_items = $items;
            return $this;
        }
        return parent::_initItems();
    }

    /**
     * @param string $label
     * @param mixed $value
     * @param int $count
     * @param null $selected
     * @param null $type
     * @return \Magento\Catalog\Model\Layer\Filter\Item
     */
    public function _createItem($label, $value, $count = 0, $selected = null, $type = null){
        
        if($this->bxDataHelper->isFilterLayoutEnabled()) {
            return $this->_filterItemFactory->create()
                ->setFilter($this)
                ->setLabel($label)
                ->setValue($value)
                ->setCount($count)
                ->setSelected($selected)
                ->setType($type);
        }
        return parent::_createItem($label, $value, $count);
    }

    /**
     * @return array
     */
    protected function _getItemsData(){
        
        $this->_requestVar = $this->bxFacets->getFacetParameterName($this->fieldName);
        if (!$this->bxDataHelper->isHierarchical($this->fieldName)) {
            foreach ($this->bxFacets->getFacetValues($this->fieldName) as $facetValue) {
                if ($this->bxFacets->getSelectedValues($this->fieldName) && $this->bxFacets->getSelectedValues($this->fieldName)[0] == $facetValue) {
                    $value = $this->bxFacets->getSelectedValues($this->fieldName)[0] == $facetValue ? true : false;
                    $this->itemDataBuilder->addItemData(
                        $this->tagFilter->filter($this->bxFacets->getFacetValueLabel($this->fieldName, $facetValue)),
                        0,
                        $this->bxFacets->getFacetValueCount($this->fieldName, $facetValue),
                        $value,
                        'flat'
                    );
                } else {
                    $value = false;
                    $this->itemDataBuilder->addItemData(
                        $this->tagFilter->filter($this->bxFacets->getFacetValueLabel($this->fieldName, $facetValue)),
                        $this->bxFacets->getFacetValueParameterValue($this->fieldName, $facetValue),
                        $this->bxFacets->getFacetValueCount($this->fieldName, $facetValue),
                        $value,
                        'flat'
                    );
                }
            }
        } else {
            $count = 1;
            $facetValues = array();
            $parentCategories = $this->bxFacets->getParentCategories();
            $parentCount = count($parentCategories);
            $value = false;
            foreach ($parentCategories as $key => $parentCategory) {
                if ($count == 1) {
                    $count++;
                    $this->itemDataBuilder->addItemData(
                        $this->tagFilter->filter("Home"),
                        2,
                        $this->bxFacets->getParentCategoriesHitCount($key),
                        $value,
                        'home parent'
                    );
                    continue;
                }
                if ($parentCount == $count++) {
                    $value = true;
                }
                $this->itemDataBuilder->addItemData(
                    $this->tagFilter->filter($parentCategory),
                    $key,
                    $this->bxFacets->getParentCategoriesHitCount($key),
                    $value,
                    'parent'
                );
            }

            if($this->bxDataHelper->getCategoriesSortOrder() == 2){
                $categorySorting = array();
                end($parentCategories);
                $cat = $this->categoryFactory->create()->load(key($parentCategories));
                foreach($cat->getChildrenCategories() as $category){
                    $categorySorting[] = $category->getName();
                }
                $facetValues = $this->sortCategories($this->bxFacets->getCategoriesKeyLabels(), $categorySorting);

            }
            if($facetValues == null){
                $facetValues = $this->bxFacets->getCategories();
            }

            foreach ($facetValues as $facetValue) {
                $this->itemDataBuilder->addItemData(
                    $this->tagFilter->filter($this->bxFacets->getFacetValueLabel($this->fieldName, $facetValue)),
                    $this->bxFacets->getFacetValueParameterValue($this->fieldName, $facetValue),
                    $this->bxFacets->getFacetValueCount($this->fieldName, $facetValue),
                    false,
                    $value ? 'children' : 'home children'
                );
            }
        }
        return $this->itemDataBuilder->build();
    }

    /**
     * @param array $categories
     * @param array $categorySorting
     * @return array
     */
    private function sortCategories($categories, $categorySorting){
        
        $sortedCategories = array();
		foreach($categorySorting as $node){
			if(isset($categories[$node])) {
				$sortedCategories[] = $categories[$node];
			}
		}		
        return $sortedCategories;
    }
}
