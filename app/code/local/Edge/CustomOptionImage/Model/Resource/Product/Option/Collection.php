<?php

class Edge_CustomOptionImage_Model_Resource_Product_Option_Collection extends Mage_Catalog_Model_Resource_Product_Option_Collection
{
    /**
     * Add title & class to result
     *
     * @param int $storeId
     * @return Mage_Catalog_Model_Resource_Product_Option_Collection
     */
    public function addTitleToResult($storeId)
    {
        $productOptionTitleTable = $this->getTable('catalog/product_option_title');
        $adapter        = $this->getConnection();
        $titleExpr      = $adapter->getCheckSql(
            'store_option_title.title IS NULL',
            'default_option_title.title',
            'store_option_title.title'
        );
        $classExpr      = $adapter->getCheckSql(
            'store_option_title.class IS NULL',
            'default_option_title.class',
            'store_option_title.class'
        );

        $this->getSelect()
            ->join(array('default_option_title' => $productOptionTitleTable),
                'default_option_title.option_id = main_table.option_id',
                array('default_title' => 'title', 'default_class' => 'class'))
            ->joinLeft(
                array('store_option_title' => $productOptionTitleTable),
                'store_option_title.option_id = main_table.option_id AND '
                    . $adapter->quoteInto('store_option_title.store_id = ?', $storeId),
                array(
                    'store_title'   => 'title',
                    'title'         => $titleExpr,
                    'store_class'   => 'class',
                    'class'         => $classExpr
                ))
            ->where('default_option_title.store_id = ?', Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID);

        return $this;
    }
}
