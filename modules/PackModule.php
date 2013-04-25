<?php

class PhpdocModule extends Module
{
	public function run($info)
	{
		$pid = $this->generateDoc($info->path, $this->getOutputPath($info));
		printf("       Job for %s/%s with pid %d\n", $info->repository, $info->branch, $pid);
		
	}
	
	private function getOutputPath($info)
	{
		if ($this->getConfig('pack', null, 'apath'))
			$paths = array($this->getConfig('pack', null, 'apath'), $info->account, $info->repository, $info->branch);
		else
			$paths = array(ROOT, trim($this->getConfig('pack', null, 'rpath'), '/'), $info->account, $info->repository, $info->branch);
		
		$r = '';
		foreach($paths as $path)
		{
			$r .= rtrim($path, '/').'/';
			if (!is_dir($r))
				mkdir($r);
		}
		return $r;
	}
}