<?php
if(!isset($_POST['payload'])) die();
require('OmniaMirror.php');

// System
$system = new OmniaMirror();
$payload = json_decode($_POST['payload']);

// Run the OmniaMirror
$gitpayload = (object ) array(
	'branch' => end(explode('/', $payload->ref)),
	'repository' => $payload->repository->name,
	'owner' => $payload->repository->owner->name,
);

$system->run($gitpayload);