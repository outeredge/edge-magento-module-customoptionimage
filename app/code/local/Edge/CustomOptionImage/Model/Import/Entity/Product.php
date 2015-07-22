<?php

class Edge_CustomOptionImage_Model_Import_Entity_Product extends Mage_ImportExport_Model_Import_Entity_Product
{
    /**
     * Column names that holds values with particular meaning.
     *
     * @var array
     */
    protected $_particularAttributes = array(
        '_store', '_attribute_set', '_type', self::COL_CATEGORY, self::COL_ROOT_CATEGORY, '_product_websites',
        '_tier_price_website', '_tier_price_customer_group', '_tier_price_qty', '_tier_price_price',
        '_links_related_sku', '_group_price_website', '_group_price_customer_group', '_group_price_price',
        '_links_related_position', '_links_crosssell_sku', '_links_crosssell_position', '_links_upsell_sku',
        '_links_upsell_position', '_custom_option_store', '_custom_option_type', '_custom_option_title',
        '_custom_option_is_required', '_custom_option_price', '_custom_option_sku', '_custom_option_max_characters',
        '_custom_option_sort_order', '_custom_option_file_extension', '_custom_option_image_size_x',
        '_custom_option_image_size_y', '_custom_option_class', '_custom_option_row_title', '_custom_option_row_price',
        '_custom_option_row_sku', '_custom_option_row_sort', '_custom_option_row_image', '_custom_option_row_class',
        '_media_attribute_id', '_media_image', '_media_lable', '_media_position', '_media_is_disabled'
    );

    /**
     * Custom options save.
     *
     * @return Mage_ImportExport_Model_Import_Entity_Product
     */
    protected function _saveCustomOptions()
    {
        /** @var $coreResource Mage_Core_Model_Resource */
        $coreResource   = Mage::getSingleton('core/resource');
        $productTable   = $coreResource->getTableName('catalog/product');
        $optionTable    = $coreResource->getTableName('catalog/product_option');
        $priceTable     = $coreResource->getTableName('catalog/product_option_price');
        $titleTable     = $coreResource->getTableName('catalog/product_option_title');
        $typePriceTable = $coreResource->getTableName('catalog/product_option_type_price');
        $typeTitleTable = $coreResource->getTableName('catalog/product_option_type_title');
        $typeValueTable = $coreResource->getTableName('catalog/product_option_type_value');
        $nextOptionId   = Mage::getResourceHelper('importexport')->getNextAutoincrement($optionTable);
        $nextValueId    = Mage::getResourceHelper('importexport')->getNextAutoincrement($typeValueTable);
        $priceIsGlobal  = Mage::helper('catalog')->isPriceGlobal();
        $type           = null;
        $typeSpecific   = array(
            'date'      => array('price', 'sku'),
            'date_time' => array('price', 'sku'),
            'time'      => array('price', 'sku'),
            'field'     => array('price', 'sku', 'max_characters'),
            'area'      => array('price', 'sku', 'max_characters'),
            //'file'      => array('price', 'sku', 'file_extension', 'image_size_x', 'image_size_y'),
            'drop_down' => true,
            'radio'     => true,
            'checkbox'  => true,
            'multiple'  => true
        );

        $alreadyUsedProductIds = array();
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $customOptions = array(
                'product_id'    => array(),
                $optionTable    => array(),
                $priceTable     => array(),
                $titleTable     => array(),
                $typePriceTable => array(),
                $typeTitleTable => array(),
                $typeValueTable => array()
            );

            foreach ($bunch as $rowNum => $rowData) {
                $this->_filterRowData($rowData);
                if (!$this->isRowAllowedToImport($rowData, $rowNum)) {
                    continue;
                }
                if (self::SCOPE_DEFAULT == $this->getRowScope($rowData)) {
                    $productId = $this->_newSku[$rowData[self::COL_SKU]]['entity_id'];
                } elseif (!isset($productId)) {
                    continue;
                }
                if (!empty($rowData['_custom_option_store'])) {
                    if (!isset($this->_storeCodeToId[$rowData['_custom_option_store']])) {
                        continue;
                    }
                    $storeId = $this->_storeCodeToId[$rowData['_custom_option_store']];
                } else {
                    $storeId = Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID;
                }
                if (!empty($rowData['_custom_option_type'])) { // get CO type if its specified
                    if (!isset($typeSpecific[$rowData['_custom_option_type']])) {
                        $type = null;
                        continue;
                    }
                    $type = $rowData['_custom_option_type'];
                    $rowIsMain = true;
                } else {
                    if (null === $type) {
                        continue;
                    }
                    $rowIsMain = false;
                }
                if (!isset($customOptions['product_id'][$productId])) { // for update product entity table
                    $customOptions['product_id'][$productId] = array(
                        'entity_id'        => $productId,
                        'has_options'      => 0,
                        'required_options' => 0,
                        'updated_at'       => now()
                    );
                }
                if ($rowIsMain) {
                    $solidParams = array(
                        'option_id'      => $nextOptionId,
                        'sku'            => '',
                        'max_characters' => 0,
                        'file_extension' => null,
                        'image_size_x'   => 0,
                        'image_size_y'   => 0,
                        'product_id'     => $productId,
                        'type'           => $type,
                        'is_require'     => empty($rowData['_custom_option_is_required']) ? 0 : 1,
                        'sort_order'     => empty($rowData['_custom_option_sort_order'])
                                            ? 0 : abs($rowData['_custom_option_sort_order'])
                    );

                    if (true !== $typeSpecific[$type]) { // simple option may have optional params
                        $priceTableRow = array(
                            'option_id'  => $nextOptionId,
                            'store_id'   => Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID,
                            'price'      => 0,
                            'price_type' => 'fixed'
                        );

                        foreach ($typeSpecific[$type] as $paramSuffix) {
                            if (isset($rowData['_custom_option_' . $paramSuffix])) {
                                $data = $rowData['_custom_option_' . $paramSuffix];

                                if (array_key_exists($paramSuffix, $solidParams)) {
                                    $solidParams[$paramSuffix] = $data;
                                } elseif ('price' == $paramSuffix) {
                                    if ('%' == substr($data, -1)) {
                                        $priceTableRow['price_type'] = 'percent';
                                    }
                                    $priceTableRow['price'] = (float) rtrim($data, '%');
                                }
                            }
                        }
                        $customOptions[$priceTable][] = $priceTableRow;
                    }
                    $customOptions[$optionTable][] = $solidParams;
                    $customOptions['product_id'][$productId]['has_options'] = 1;

                    if (!empty($rowData['_custom_option_is_required'])) {
                        $customOptions['product_id'][$productId]['required_options'] = 1;
                    }
                    $prevOptionId = $nextOptionId++; // increment option id, but preserve value for $typeValueTable
                }
                if ($typeSpecific[$type] === true && !empty($rowData['_custom_option_row_title'])
                        && empty($rowData['_custom_option_store'])) {
                    // complex CO option row
                    $customOptions[$typeValueTable][$prevOptionId][] = array(
                        'option_type_id' => $nextValueId,
                        'sort_order'     => empty($rowData['_custom_option_row_sort'])
                                            ? 0 : abs($rowData['_custom_option_row_sort']),
                        'sku'            => !empty($rowData['_custom_option_row_sku'])
                                            ? $rowData['_custom_option_row_sku'] : '',
                        'image'          => !empty($rowData['_custom_option_row_image'])
                                            ? $rowData['_custom_option_row_image'] : '',
                        'class'          => !empty($rowData['_custom_option_row_class'])
                                            ? $rowData['_custom_option_row_class'] : ''
                    );
                    if (!isset($customOptions[$typeTitleTable][$nextValueId][0])) { // ensure default title is set
                        $customOptions[$typeTitleTable][$nextValueId][0] = $rowData['_custom_option_row_title'];
                    }
                    $customOptions[$typeTitleTable][$nextValueId][$storeId] = $rowData['_custom_option_row_title'];

                    if (!empty($rowData['_custom_option_row_price'])) {
                        $typePriceRow = array(
                            'price'      => (float) rtrim($rowData['_custom_option_row_price'], '%'),
                            'price_type' => 'fixed'
                        );
                        if ('%' == substr($rowData['_custom_option_row_price'], -1)) {
                            $typePriceRow['price_type'] = 'percent';
                        }
                        if ($priceIsGlobal) {
                            $customOptions[$typePriceTable][$nextValueId][0] = $typePriceRow;
                        } else {
                            // ensure default price is set
                            if (!isset($customOptions[$typePriceTable][$nextValueId][0])) {
                                $customOptions[$typePriceTable][$nextValueId][0] = $typePriceRow;
                            }
                            $customOptions[$typePriceTable][$nextValueId][$storeId] = $typePriceRow;
                        }
                    }
                    $nextValueId++;
                }
                if (!empty($rowData['_custom_option_title']) || !empty($rowData['_custom_option_class'])) {
                    if (!isset($customOptions[$titleTable][$prevOptionId][0])) { // ensure default title is set
                        $customOptions[$titleTable][$prevOptionId][0] = array(
                            'title' => !empty($rowData['_custom_option_title']) ? $rowData['_custom_option_title'] : '',
                            'class' => !empty($rowData['_custom_option_class']) ? $rowData['_custom_option_class'] : ''
                        );
                    }
                    $customOptions[$titleTable][$prevOptionId][$storeId] = array(
                        'title' => !empty($rowData['_custom_option_title']) ? $rowData['_custom_option_title'] : '',
                        'class' => !empty($rowData['_custom_option_class']) ? $rowData['_custom_option_class'] : ''
                    );
                }
            }
            $productIds = array_keys($customOptions['product_id']);
            $productIds = array_diff($productIds, $alreadyUsedProductIds);
            if ($this->getBehavior() != Mage_ImportExport_Model_Import::BEHAVIOR_APPEND
                && !empty($productIds)
            ) { // remove old data?
                $this->_connection->delete(
                    $optionTable,
                    $this->_connection->quoteInto('product_id IN (?)', $productIds)
                );
            }
            // if complex options does not contain values - ignore them
            foreach ($customOptions[$optionTable] as $key => $optionData) {
                if ($typeSpecific[$optionData['type']] === true
                        && !isset($customOptions[$typeValueTable][$optionData['option_id']])
                ) {
                    unset($customOptions[$optionTable][$key], $customOptions[$titleTable][$optionData['option_id']]);
                }
            }

            if ($customOptions[$optionTable]) {
                $this->_connection->insertMultiple($optionTable, $customOptions[$optionTable]);
            }
            $titleRows = array();

            foreach ($customOptions[$titleTable] as $optionId => $storeInfo) {
                foreach ($storeInfo as $storeId => $data) {
                    $titleRows[] = array('option_id' => $optionId, 'store_id' => $storeId, 'title' => $data['title'], 'class' => $data['class']);
                }
            }
            if ($titleRows) {
                $this->_connection->insertOnDuplicate($titleTable, $titleRows, array('title'));
            }
            if ($customOptions[$priceTable]) {
                $this->_connection->insertOnDuplicate(
                    $priceTable,
                    $customOptions[$priceTable],
                    array('price', 'price_type')
                );
            }
            $typeValueRows = array();

            foreach ($customOptions[$typeValueTable] as $optionId => $optionInfo) {
                foreach ($optionInfo as $row) {
                    $row['option_id'] = $optionId;
                    $typeValueRows[]  = $row;
                }
            }
            if ($typeValueRows) {
                $this->_connection->insertMultiple($typeValueTable, $typeValueRows);
            }
            $optionTypePriceRows = array();
            $optionTypeTitleRows = array();

            foreach ($customOptions[$typePriceTable] as $optionTypeId => $storesData) {
                foreach ($storesData as $storeId => $row) {
                    $row['option_type_id'] = $optionTypeId;
                    $row['store_id']       = $storeId;
                    $optionTypePriceRows[] = $row;
                }
            }
            foreach ($customOptions[$typeTitleTable] as $optionTypeId => $storesData) {
                foreach ($storesData as $storeId => $title) {
                    $optionTypeTitleRows[] = array(
                        'option_type_id' => $optionTypeId,
                        'store_id'       => $storeId,
                        'title'          => $title
                    );
                }
            }
            if ($optionTypePriceRows) {
                $this->_connection->insertOnDuplicate(
                    $typePriceTable,
                    $optionTypePriceRows,
                    array('price', 'price_type')
                );
            }
            if ($optionTypeTitleRows) {
                $this->_connection->insertOnDuplicate($typeTitleTable, $optionTypeTitleRows, array('title'));
            }

            if ($productIds) { // update product entity table to show that product has options
                $customOptionsProducts = $customOptions['product_id'];

                foreach ($customOptionsProducts as $key => $value) {
                    if (!in_array($key, $productIds)) {
                        unset($customOptionsProducts[$key]);
                    }
                }
                $this->_connection->insertOnDuplicate(
                    $productTable,
                    $customOptionsProducts,
                    array('has_options', 'required_options', 'updated_at')
                );
            }

            $alreadyUsedProductIds = array_merge($alreadyUsedProductIds, $productIds);
        }
        return $this;
    }
}
