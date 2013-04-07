<?php

class Mirror extends Base
{
	private $github;
	private $githubUrl;
	private $actions;
	
	function __construct($github, $actions)
	{
		$this->github = new Github($github->user, $github->password);
		$this->githubUrl = $github->url;
		$this->actions = $actions;
	}
	
	private function dirRepos()
	{
		$r = ROOT.'/repositories/'.$this->github->getUser();
		if (!is_dir($r))
			mkdir($r);
		return $r;
	}
	
	private function dirRepo($repo)
	{
		return $this->dirRepos().'/'.trim($repo, '/');
	}
	
	function run()
	{
		// Get all cron jobs
		//$this->updateRepositories();
		
		// Go trough all crons and run them if due
		foreach ($this->actions as &$action)
		{
			printf("    Running module %s for %s/%s\n", $action->module, $action->repository, $action->branch);
			$module = new $action->module($action->arguments);
			
			if ($action->repository == '*')
			{
				$this->forallRepositories($action->branch, $module);
			}
			elseif ($action->branch == '*')
			{
				$this->forallBranches($action->repository, $module);
			}
			else
			{
				$this->forBranch($action->repository, $action->branch, $module);
			}
		}
	}
	
    /**
     * Get hash
     * 
     * @param string    $repository 
     * 
     * @return string   The last hash for this repository
     */	
	public function getHash($repository)
	{
		// Check if dir exists
		if (!chdir($this->dirRepo($repository)))
			return false;
		return $this->execute('git rev-parse HEAD');
	}
	
    /**
     * Get branches
     * 
	 * Get all branches for a specefic repository
	 *
     * @param string    $repository 
     * 
     * @return array    List of branches
     */	
	private function getBranches($repository)
	{
		if (!chdir($this->dirRepo($repository)))
			return false;
		
		$branches = $this->execute('git for-each-ref refs/remotes --format=\'%(refname)\' | sed \'s/refs\/remotes\/origin\///g\'');
		$branches = preg_replace('~\R~u', ";", $branches);
		$branches = explode(';', $branches);
		
		$ret = array();
		
		foreach ($branches as $branch)
		{
			if (strtolower($branch) == 'head')
				continue;	
			$ret[] = trim($branch);
		}
		return $ret;
	}
	
	public function updateRepositories()
	{
		$repos = $this->github->getRepositories();
		foreach ($repos as &$repo)
		{
			$this->updateRepository($repo->name, $repo->owner->login, $repo->ssh_url);
		}
	}
	
	public function updateRepository($name, $user)
	{
		if(is_dir($this->dirRepo($name).'/.git'))
		{
			chdir($this->dirRepo($name));
			$this->execute('git fetch -p --all');
			return true;
		}
		chdir($this->dirRepos());
		$this->execute('git clone '.$this->github->getUrl($user.'/'.$name.'.git'));
	}
	
	
	public function forallRepositories($branch = '*', $module)
	{
		$repos = $this->github->getRepositories();
		foreach ($repos as &$repo)
		{
			if ($branch == '*')
			{
				$this->forallBranches($repo->name, $module);
			}
			else
			{
				$this->forBranch($repo->name, $branch, $module);
			}
		}
	}
	
	public function forallBranches($repository, $module)
	{
		$branches = $this->getBranches($repository);
		foreach ($branches as $branch)
		{
			$this->forBranch($repository, $branch, $module);
		}
	}
	
	public function forBranch($repository, $branch, $module)
	{
		if ($this->updateBranch($repository, $branch))
		{
			call_user_func(array($module, 'run'), $repository, $branch, $this->dirRepo($repository));
		}
	}

	/**
	 * Update branch
	 *
	 * Updates a specific branch in a repository to mirror the remote
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
		$e = $this->execute('git checkout --force '.$branch);
		if($e[0] == 'e')
			return false;
		
		// Hard mirror it
		$this->execute('git reset --hard');
		$this->execute('git clean -f -d');
		$this->execute('git pull -q --force');
		
		// Check if it has submodules
		if(file_exists('.gitmodules'))
		{
			$this->execute('git submodule init -q');
			$this->execute('git submodule foreach --recursive git pull --force');
		}
		
		// Assume success
		return true;
	}
}