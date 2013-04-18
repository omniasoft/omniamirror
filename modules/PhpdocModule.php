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
		if ($this->getConfig('phpdoc', null, 'apath'))
			$paths = array($this->getConfig('phpdoc', null, 'apath'), $info->account, $info->repository, $info->branch);
		else
			$paths = array(ROOT, trim($this->getConfig('phpdoc', null, 'rpath'), '/'), $info->account, $info->repository, $info->branch);
		
		$r = '';
		foreach($paths as $path)
		{
			$r .= rtrim($path, '/').'/';
			if (!is_dir($r))
				mkdir($r);
		}
		return $r;
	}
	
	/**
	 * Clean repository
	 *
	 * Removes all branches from the documentation when they are not longer
	 * on the remote.
	 *
	 * @param string	$repository		The repository name (case sensitive)
	 *
	 * @return int      The amount of branches deleted (so false if nothing, else true)
	 */
	public function cleanRepository($info)
	{
		$deleteNo = 0;
		
		// Get local branches
		$localBranches = array_diff(scandir($this->dirDocs().'/'.$repository), array('.', '..'));
		foreach($localBranches as $key => $dir)
			if(!is_dir($this->dirDoc($repository, $dir)))
				unset($localBranches[$key]);
		
		// Get remote branches
		$branches = $this->getBranches($repository);
		foreach($localBranches as $branch)
			if(!in_array($branch, $branches))
			{
				// This branch does not exist remote so delete it
				$this->execute('rm -rf '.$this->dirDoc($repository, $branch));
				$deleteNo++;
			}
		return $deleteNo;
	}

    /**
     * Generates doc
     * 
     * @param string $input 
     * @param string $output 
     * 
     * @return int
     */	
	public function generateDoc($input, $output)
	{
		// Check if dir exists
		if(!chdir($input))
			return false;
		$str = $this->getConfig('phpdoc', null, 'bin').' -d '.$input.' -t '.$output;
		$this->execute($str, false);
		$pid = $this->execute('ps  -A x | grep "'.$str.'" | grep -v grep | nawk \'{print $1}\'');
		return $pid;
	}
}