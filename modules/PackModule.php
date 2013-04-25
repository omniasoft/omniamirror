<?php

class PackModule extends Module
{
	public function run($info)
	{
		$ref = explode('/', $info->gitpayload->data->ref);
		echo $info->path."\n";
		if ($ref[1] == 'tags')
		{
			$tag = $ref[2];
			printf("       Processing tag %s\n", $tag);
			
			$file = $this->compress($info->path, $info->repository.'_'.$tag.'_'.date('YmdHis').'.gz');
		}
	}
	
	private function getReleasePath()
	{
		$r = $this->getConfig('pack', null, 'path');
		if ( ! is_dir($r))
			mkdir($r);
		return $r;
	}
}