<?php

class PhpdocModule extends Module
{
	public function run($repository, $branch, $path)
	{
		printf("       Job for %s/%s with path %s\n", $repository, $branch, basename($path));
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
		$str = 'phpdoc -d '.$input.' -t '.$output;
		$this->execute($str, false);
		$pid = $this->execute('ps  -A x | grep "'.$str.'" | grep -v grep | nawk \'{print $1}\'');
		return $pid;
	}
}