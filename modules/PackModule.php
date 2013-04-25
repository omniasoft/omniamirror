<?php

class PackModule extends Module
{
	public function run($info)
	{
		$ref = explode('/', $info->gitpayload->data->ref);
		if ($ref[1] == 'tags')
		{
			$tag = $ref[2];
			printf("       Processing tag %s\n", $tag);
			
			$file = $this->compress($info->path, $this->getReleasePath($info->repository.'_'.$tag.'_'.date('YmdHis').'.gz'));
		}
		else
			printf("       Not a tag so skipping\n");
	}
	
	private function getReleasePath($file)
	{
		$r = $this->getConfig('pack', null, 'path');
		if ( ! is_dir($r))
			mkdir($r);
		$r .= '/'.trim($file, '/');
		echo $r;
		return $r;
	}
}