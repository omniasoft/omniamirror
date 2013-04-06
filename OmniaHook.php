<?php
include('config.php');

class OmniaHook
{
	public $github;
	private $root;
	
	// Protected
	protected $lastOutput;
	
	function __construct($root = false)
	{
		$this->root = !$root ? getcwd() : $root;
		$this->github = new Github(GITHUB_USER, GITHUB_PASSWORD);	
	}
	
	/**
	 * Gets the last message of a execute call
	 *
	 * @return string Output of the last executed command
	 */
	public function getLastOutput()
	{
		if(empty($this->lastOutput))
			return false;
		return $this->lastOutput;
	}
	
	/**
	 * Execute a shell command
	 *
	 * And redirects errors to the return of this function
	 *
	 * @param string The linux command
	 * @param bool Redirect STDERROR to script (default true)
	 * @return bool True if the command had no output and false otherwise
	 */
	protected function execute($command, $catchError = true)
	{
		$cmd = $command.($catchError ? ' 2>&1' : ' > /dev/null 2>/dev/null &');
		$this->lastOutput = trim(`$cmd`);
		return $this->lastOutput;
	}
	
	private function dirRepos()
	{
		$r = $this->root.'/'.REPOS;
		if(!is_dir($r)) mkdir($r);
		return $r;
	}

	private function dirDocs()
	{
		$r = $this->root.'/'.DOCS;
		if(!is_dir($r)) mkdir($r);
		return $r;
	}
	
	private function dirRepo($repo)
	{
		return $this->dirRepos().'/'.$repo;
	}
	
	private function dirDoc($repo, $branch)
	{
		return $this->dirDocs().'/'.$repo.'/'.$branch;
	}
	
	public function getLastCommit($repository)
	{
		// Check if dir exists
		if(!chdir($this->dirRepo($repository)))
			return false;
			
		return $this->execute('git rev-parse HEAD');
	}
	
	public function getBranches($repository)
	{
		if(!chdir($this->dirRepo($repository)))
			return false;
		
		$branches = $this->execute('git for-each-ref refs/remotes --format=\'%(refname)\' | sed \'s/refs\/remotes\/origin\///g\'');
		$branches = preg_replace('~\R~u', ";", $branches);
		$branches = explode(';', $branches);
		
		$ret = array();
		
		foreach($branches as $branch)
		{
			if(strtolower($branch) == 'head') continue;	
			$ret[] = trim($branch);
		}
		return $ret;
	}
	
	public function checkRepository($name)
	{
		$repos = $this->github->getRepositories();
		foreach($repos as &$repo)
			if($name == $repo->name)
			{
				$this->initializeRepository($repo->name, $repo->ssh_url);
				$this->cleanRepository($repo->name);
			}
	}
	
	public function initializeRepository($name, $ssh)
	{
		if(is_dir($this->dirRepo($name).'/.git'))
		{
			chdir($this->dirRepo($name));
			$this->execute('git fetch -p --all -q');
			return true;
		}
		chdir($this->dirRepos());
		$this->execute('git clone '.str_replace(GITHUB, MYGITHUB, $ssh).' '.$name);
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
	
	public function updateRepositories()
	{
		$repos = $this->github->getRepositories();
		foreach($repos as &$repo)
		{
			$this->initializeRepository($repo->name, $repo->ssh_url);
			$this->updateBranches($repo->name);
		}
	}
	
	public function updateBranches($repository)
	{
		$branches = $this->getBranches($repository);
		foreach($branches as $branch)
		{
			echo "Updating ".$repository."/".$branch."\n";
			if(!$this->updateBranch($repository, $branch))
				echo 'Failed to update branch'."\n";
			if(!$this->generateDoc($repository, $branch))
				echo 'Failed to generate docs'."\n";
		}
	}

	public function autoUpdate()
	{
		chdir($this->root);
		$this->execute('git pull');
		return $this->execute('git rev-parse HEAD');
	}
	
	/**
	 * Update branche
	 *
	 * Updates a specefic branch in a repository to mirror the remote
	 * Also updates submodules
	 *
	 * @param string	$repository		The repository name (case sensitive)
	 * @param string    $branch         The branch name (case sensitive)
	 *
	 * @return bool     State of the update (true if success, false otherwise)
	 */
	public function updateBranch($repository, $branch)
	{
		// Check if dir exists
		if(!chdir($this->dirRepo($repository)))
			return false;
			
		// Checkout this branch and reset it to mirror remote
		$e = $this->execute('git checkout -q --force '.$branch);
		if(substr($e, 0, 1) == 'e') return false;
		
		$this->execute('git reset --hard');
		$this->execute('git clean -f -d');
		
		// Update the branch
		$this->execute('git pull -q --force');
		
		// Check if it has submodules
		if(file_exists('.gitmodules'))
		{
			$this->execute('git submodule init -q');
			$this->execute('git submodule foreach --recursive git pull --force');
		}
		return true;
	}
	
	public function kill($pid)
	{
		$this->execute('kill '.$pid);	
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