<?php

class Edge_CustomOptionImage_Model_Resource_Product_Option extends Mage_Catalog_Model_Resource_Product_Option
{
    /**
     * Save titles
     *
     * @param Mage_Core_Model_Abstract $object
     * @return Mage_Catalog_Model_Resource_Product_Option
     */
    protected function _saveValueTitles(Mage_Core_Model_Abstract $object)
    {
        $readAdapter  = $this->_getReadAdapter();
        $writeAdapter = $this->_getWriteAdapter();
        $titleTable = $this->getTable('catalog/product_option_title');

        //title
        if (!$object->getData('scope', 'title')) {
            $statement = $readAdapter->select()
                ->from($titleTable)
                ->where('option_id = ?', $object->getId())
                ->where('store_id  = ?', Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID);

            if ($readAdapter->fetchOne($statement)) {
                if ($object->getStoreId() == '0') {
                    $data = $this->_prepareDataForTable(
                        new Varien_Object(
                            array(
                                'title' => $object->getTitle(),
                                'class' => $object->getClass()
                            )
                        ),
                        $titleTable
                    );

                    $writeAdapter->update(
                        $titleTable,
                        $data,
                        array(
                            'option_id = ?' => $object->getId(),
                            'store_id  = ?' => Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID
                        )
                    );
                }
            } else {
                $data = $this->_prepareDataForTable(
                    new Varien_Object(
                        array(
                            'option_id' => $object->getId(),
                            'store_id'  => Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID,
                            'title'     => $object->getTitle(),
                            'class'     => $object->getClass()
                        )
                    ),
                    $titleTable
                );

                $writeAdapter->insert($titleTable, $data);
            }
        }

        if ($object->getStoreId() != '0' && !$object->getData('scope', 'title')) {
            $statement = $readAdapter->select()
                ->from($titleTable)
                ->where('option_id = ?', $object->getId())
                ->where('store_id  = ?', $object->getStoreId());

            if ($readAdapter->fetchOne($statement)) {
                $data = $this->_prepareDataForTable(
                    new Varien_Object(
                        array(
                            'title' => $object->getTitle(),
                            'class' => $object->getClass()
                        )
                    ),
                    $titleTable
                );

                $writeAdapter->update(
                    $titleTable,
                    $data,
                    array(
                        'option_id = ?' => $object->getId(),
                        'store_id  = ?' => $object->getStoreId()
                    )
                );
            } else {
                $data = $this->_prepareDataForTable(
                    new Varien_Object(
                        array(
                            'option_id' => $object->getId(),
                            'store_id'  => $object->getStoreId(),
                            'title'     => $object->getTitle(),
                            'class'     => $object->getClass()
                        )
                    ),
                    $titleTable
                );
                $writeAdapter->insert($titleTable, $data);
            }
        } elseif ($object->getData('scope', 'title')) {
            $writeAdapter->delete(
                $titleTable,
                array(
                    'option_id = ?' => $object->getId(),
                    'store_id  = ?' => $object->getStoreId()
                )
            );
        }
    }
}
