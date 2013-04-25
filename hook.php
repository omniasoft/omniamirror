<?php
//if(!isset($_POST['payload'])) die('Need payload');
$_POST['payload'] = '{"ref":"refs/tags/0.2","after":"b3a41237b95e0db3cd842169b4cbd27db93bc98c","before":"0000000000000000000000000000000000000000","created":true,"deleted":false,"forced":true,"compare":"https://github.com/deskbookers/Tools/compare/0.2","commits":[],"head_commit":{"id":"f47cc0b683e50f1eb8144966b322814ae7a590d1","distinct":true,"message":"Small post fix","timestamp":"2013-03-17T04:13:04-07:00","url":"https://github.com/deskbookers/Tools/commit/f47cc0b683e50f1eb8144966b322814ae7a590d1","author":{"name":"Kevin Valk","email":"kevin@omniasoft.nl","username":"Omniasoft"},"committer":{"name":"Kevin Valk","email":"kevin@omniasoft.nl","username":"Omniasoft"},"added":[],"removed":[],"modified":["devdoc.php"]},"repository":{"id":8823883,"name":"Tools","url":"https://github.com/deskbookers/Tools","description":"A couple of tools and scripts","watchers":0,"stargazers":0,"forks":0,"fork":false,"size":472,"owner":{"name":"deskbookers","email":"admin@deskbookers.com"},"private":false,"open_issues":0,"has_issues":true,"has_downloads":true,"has_wiki":true,"created_at":1363460588,"pushed_at":1366884174,"master_branch":"master"},"pusher":{"name":"Omniasoft","email":"kevin@omniasoft.nl"}}';
require('OmniaMirror.php');

// System
$system = new OmniaMirror();
$payload = json_decode($_POST['payload']);

$info = print_r($payload, true);
//chdir('payloads');
file_put_contents('payload_'.date('dmYHis').'.js', $_POST['payload']."\n".$info);
// Run the OmniaMirror
$gitpayload = (object ) array(
	'branch' => end(explode('/', $payload->ref)),
	'repository' => $payload->repository->name,
	'owner' => $payload->repository->owner->name,
	'data' => $payload,
);

$system->run($gitpayload);