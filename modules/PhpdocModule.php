<?php

class PhpdocModule extends Module
{

	public function run($path)
	{
		print_r($path);
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
	public function cleanRepository($repository)
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
	
	public function generateDoc($repository, $branch)
	{
		$dir = $this->dirRepo($repository);
		// Check if dir exists
		if(!chdir($dir))
			return false;
		$str = 'phpdoc -d '.$dir.' -t '.$this->dirDoc($repository, $branch);
		$this->execute($str, false);
		$pid = $this->execute('ps  -A x | grep "'.$str.'" | grep -v grep | nawk \'{print $1}\'');
		return $pid;
	}
}