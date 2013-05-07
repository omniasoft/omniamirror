<?php
if(!isset($_POST['payload'])) die('Need payload');
require('OmniaMirror.php');

// System
$system = new OmniaMirror();
$payload = json_decode($_POST['payload']);

// Debug dump
file_put_contents('payloads.log', $_POST['payload']."\n\n", FILE_APPEND);

// Run the OmniaMirror
$gitpayload = (object ) array(
	'branch' => end(explode('/', $payload->ref)),
	'repository' => $payload->repository->name,
	'owner' => $payload->repository->owner->name,
	'data' => $payload,
);

ob_start();
$system->run($gitpayload);
file_put_contents('debug.log', ob_get_flush(), FILE_APPEND);