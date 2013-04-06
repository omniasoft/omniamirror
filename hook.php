<?php
if(!isset($_POST['payload'])) die();
require('devdoc.php');
$dir = getcwd();
$doc = new DevDoc();

function fileGet($file)
{
	global $dir; chdir($dir);
	return file_get_contents($file);
}

function filePut($file, $data)
{
	global $dir; chdir($dir);
	return file_put_contents($file, $data);
}

// Parse the payload
$json = json_decode($_POST['payload']);
$branch = end(explode('/', $json->ref));
$repository = $json->repository->name;

// Check if we should update this system
if($repository == 'Tools' && $branch == 'doc')
{
	$rev = $doc->autoUpdate();
	filePut('version', $rev);
}

// Save last hook call
$map = unserialize(fileGet('lastrun'));
$map['hook'] = time();
filePut('lastrun', serialize($map));

// Set or wait for lock
chdir($dir);
while(file_exists('lock')) sleep(1);
touch('lock');

// Update branch and generate doc
$doc->checkRepository($repository);
$doc->updateBranch($repository, $branch);

// Delete lock
chdir($dir);
unlink('lock');

// Generate docs but kill active doc generating process first
$runs = unserialize(fileGet('runs'));
if(isset($runs[$repository][$branch]))
	$doc->kill($runs[$repository][$branch]);
$runs[$repository][$branch] = $doc->generateDoc($repository, $branch);
filePut('runs', serialize($runs));

// Update the map
$map = unserialize(fileGet('lastrun'));
$map[$repository][$branch] = time();
filePut('lastrun', serialize($map));