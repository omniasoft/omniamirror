<?php

class Mirror extends Base
{
	private $github;
	private $actions;
	
    /**
     * Constructor
     * 
     * @param object $github 
     * @param object $actions 
     */	
	function __construct($github, $actions = array())
	{
		$this->github = new Github($github->user, $github->password);
		$this->actions = $actions;
	}
	
    /**
     * Gets the full path to the repositories directory
     * 
     * 
     * @return string
     */
	private function dirRepos()
	{
		$r = ROOT.'/repositories/';
		if (!is_dir($r))
			mkdir($r);
		$r .= $this->github->getUser();
		if (!is_dir($r))
			mkdir($r);
		return $r;
	}
	
    /**
     * Gets the full path to a repository
     * 
     * @param string $repo 
     * 
     * @return string
     */
	private function dirRepo($repo)
	{
		return $this->dirRepos().'/'.trim($repo, '/');
	}
	
    /**
     * The main run loop
     * 
     */
	function run($gitpayload)
	{	
		// Go trough all crons and run them if due
		foreach ($this->actions as &$action)
		{		
			if ($action->repository == '*' || $action->repository == $gitpayload->repository)
			{
				if ($action->branch == '*' || $action->branch == $gitpayload->branch)
				{
					// Print
					printf("    Running module %s for %s/%s\n", $action->module, $gitpayload->repository, $gitpayload->branch);
					$module = new $action->module($this->github, $action->arguments);
			
					
					// Update the branch and do it
					if ( ! $this->updateRepository($gitpayload->repository, $gitpayload->owner))
					{
						printf("    failed to update the repo, quiting\n");
					}
					else
					{
						$this->forBranch($gitpayload->repository, $gitpayload->branch, $module);
					}
				}
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
	
    /**
     * Updates all repositories you have on github
     * 
     */	
	public function updateRepositories()
	{
		$repos = $this->github->getRepositories();
		foreach ($repos as &$repo)
		{
			$this->updateRepository($repo->name, $repo->owner->login, $repo->ssh_url);
		}
	}
	
    /**
     * Updates a repository
     * 
	 * Updates or clones a repository through https with url user:pass
	 *
     * @param string $name 
     * @param string $user 
     * 
     * @return mixed
     */	
	public function updateRepository($name, $user)
	{
		if(is_dir($this->dirRepo($name).'/.git'))
		{
			chdir($this->dirRepo($name));
			$this->execute('git fetch -p --all');
			return true;
		}
		chdir($this->dirRepos());
		$ret = $this->execute('git clone '.$this->github->getUrl($user.'/'.$name.'.git'));
		return ($ret[0] != 'f');
	}
	
	
    /**
     * Do for all repositories
     * 
     * @param string $branch (can be wildcard * to match all branches)
     * @param Module $module
     */
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
	
    /**
     * Do for all branches in a specefic repository
     * 
     * @param string $repository 
     * @param Module $module 
     */	
	public function forallBranches($repository, $module)
	{
		$branches = $this->getBranches($repository);
		foreach ($branches as $branch)
		{
			$this->forBranch($repository, $branch, $module);
		}
	}
	
    /**
     * Do module for a specefic branch in a repository
     * 
     * @param string $repository 
     * @param string $branch 
     * @param Module $module 
     */
	public function forBranch($repository, $branch, $module)
	{
		if ($this->updateBranch($repository, $branch))
		{
			$info = (object) array(
				'repository' => $repository,
				'branch' => $branch,
				'path' => $this->dirRepo($repository),
				'account' => $this->github->getUser(),
				'branches' => $this->getBranches($repository),
			);
			call_user_func(array($module, 'run'), $info);
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
		if( ! is_dir($this->dirRepo($repository)))
			return false;
		chdir($this->dirRepo($repository));
		
		// Checkout this branch and reset it to mirror remote
		$e = $this->execute('git checkout --force '.$branch);
		if($e[0] == 'e')
			return false;
		
		// Hard mirror it
		$this->execute('git reset --hard origin/HEAD');
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