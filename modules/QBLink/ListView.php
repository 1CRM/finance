<?php

require_once 'modules/QBLink/QBListManager.php';

$type = array_get_default($_REQUEST, 'list_type', 'Entity');
$manager = new QBListManager($type);

$manager->render();
