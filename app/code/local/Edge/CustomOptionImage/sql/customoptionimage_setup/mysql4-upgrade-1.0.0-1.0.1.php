<?php

$this->startSetup();

$this->getConnection()->addColumn($this->getTable('catalog/product_option_title'), 'class', 'TEXT NULL');
$this->getConnection()->addColumn($this->getTable('catalog/product_option_type_value'), 'class', 'TEXT NULL');

$this->endSetup();