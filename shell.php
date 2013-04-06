<?php
if(php_sapi_name() != 'cli') die();

require('devdoc.php');
$doc = new DevDoc();
$doc->updateRepositories();