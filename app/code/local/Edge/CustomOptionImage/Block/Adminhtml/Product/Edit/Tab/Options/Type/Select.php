<?php

class Edge_CustomOptionImage_Block_Adminhtml_Product_Edit_Tab_Options_Type_Select
    extends Mage_Adminhtml_Block_Catalog_Product_Edit_Tab_Options_Type_Select
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('edge/customoptionimage/product/edit/options/type/select.phtml');
    }
}

