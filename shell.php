#!/usr/bin/php 
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
	case 'deploy':
		$package = $system->getCmd(1);
		$site = $system->getCmd(2);
		
		printf("Deploying %s to %s\n", basename($package), $site);
		
		// Transfer the package
		Base::execute('scp -i /home/deploy/.ssh/id_rsa '.$package.' deploy@s1.2cnnct.com:~');
		
		// Extract the package
		$wwwDir = '/var/www';
		$siteDir = $wwwDir.'/'.$site;
		$tmpDir = $siteDir.'_';
		$oldDir = $siteDir.'__';
		
		// Build script to run
		$script  = 'sudo mkdir '.$tmpDir.'; ';
		$script .= 'cd '.$tmpDir.'; ';
		$script .= 'sudo tar xvfz /home/deploy/'.basename($package).'; ';
		$script .= 'sudo mv '.$siteDir.' '.$oldDir.'; ';
		$script .= 'sudo mv '.$tmpDir.' '.$siteDir.'; ';
		$script .= 'sudo rm -rf '.$oldDir.'; ';
		
		// Execute it and remove old package
		$script .= 'rm -f /home/deploy/'.basename($package).'; ';
		Base::execute('ssh -i /home/deploy/.ssh/id_rsa deploy@s1.2cnnct.com "'.$script.'"');
	break;
	case 'hookall':
		$account = $system->getCmd(1);
		$url = $system->getCmd(2);
		
		$github = new Github($system->getConfig('github', $account, 'user'), $system->getConfig('github', $account, 'password'));
		$github->setWebHooks($url);
	break;
	default:
		printf("Unsuported command\n");
}