<?php

$this->startSetup();

// Custom Option Image - Custom Options Images
$this->getConnection()->addColumn($this->getTable('catalog/product_option_type_value'), 'image', 'TEXT NULL');

$this->endSetup();