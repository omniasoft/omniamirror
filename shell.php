<?php
if(php_sapi_name() != 'cli') die();
include('OmniaMirror.php');

$system = new OmniaMirror();

// Parse main cmd
switch($system->getCmd(0))
{
	case 'mirror':
		$account = $system->getCmd(1);
		printf("Mirroring github account `%s`\n", $account);
		if (!$system->getConfig('github', $account))
			die(printf("\tThe account does not exists\n"));
		
		// Create github credentials
		$github = (object) array(
			'user' => $system->getConfig('github', $account, 'user'),
			'password' => $system->getConfig('github', $account, 'password')
		);
		
		$mirror = new Mirror($github);
		$mirror->updateRepositories();
		
	break;
	default:
		printf("Unsuported command\n");
}