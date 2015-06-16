<?php

class Edge_CustomOptionImage_Model_Observer_Product
{
    public function saveCustomOptionImages(Varien_Event_Observer $observer)
    {
        if (!isset($_FILES) || empty($_FILES) || !isset($_FILES['product'])){
            return;
        }

        $product = $observer->getEvent()->getProduct();

        $productData = $observer->getEvent()->getRequest()->getPost('product');
        if (isset($productData['options']) && !$product->getOptionsReadonly()) {
            if (isset($_FILES['product']['name']['options'])) {
                $images = array();
                foreach ($_FILES['product'] as $attr => $options) {
                    if (isset($options['options'])) {
                        foreach ($options['options'] as $optionId => $values) {
                            if (isset($values['values'])) {
                                foreach ($values['values'] as $valueId => $data) {
                                    $key = 'option_' . $optionId . '_value_' . $valueId;
                                    if (!isset($images[$key])) {
                                        $images[$key] = array();
                                    }
                                    $images[$key][$attr] = $data['image'];
                                }
                            }
                        }
                    }
                }

                foreach ($images as $imageName => $imageData) {
                    $_FILES[$imageName] = $imageData;
                }
            }

            foreach ($productData['options'] as $optionId => $option) {
                if (!empty($option['values'])) {
                    foreach ($option['values'] as $valueId => $value) {

                        $imageName = 'option_' . $optionId . '_value_' . $valueId;

                        if (!isset($_FILES[$imageName]) || empty($_FILES[$imageName]) || $_FILES[$imageName]['name'] === "") {
                            continue;
                        }

                        try {
                            $uploader = new Mage_Core_Model_File_Uploader($imageName);
                            $uploader->setAllowedExtensions(array('jpg','jpeg','gif','png'));
                            $uploader->setAllowRenameFiles(true);
                            $uploader->setFilesDispersion(false);

                            $dirPath = Mage::getBaseDir('media') . DS . 'custom_option_image' . DS;
                            $result = $uploader->save($dirPath, $_FILES[$imageName]['name']);

                        } catch (Exception $e) {
                            Mage::log($e->getMessage());
                        }

                        $productData['options'][$optionId]['values'][$valueId]['image'] = 'custom_option_image/' . $result['file'];
                        $product->setCanSaveCustomOptions(true);
                    }
                }
            }

            $product->setProductOptions($productData['options']);
        }
    }
}