<?php

define('MODULE', 'Module');

spl_autoload_register(function($class)
{
	if(substr($class, -strlen(MODULE)) == MODULE)
		include('modules/'.$class.'.php');
	else
		include $class.'.php';
});

class OmniaMirror extends Base
{
	public $github;
	private $root;
	
	// Protected
	protected $lastOutput;
	
	function __construct($root = false)
	{
		parent::__construct();
		$this->root = !$root ? getcwd() : $root;
		$this->github = new Github($this->getConfig('github', 'user'), $this->getConfig('github', 'password'));	
	}
	
	private function dirRepos()
	{
		$r = $this->root.'/'.REPOS;
		if (!is_dir($r))
			mkdir($r);
		return $r;
	}
	
	private function dirRepo($repo)
	{
		return $this->dirRepos().'/'.$repo;
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
	public function getBranches($repository)
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
	
	public function checkRepository($name)
	{
		$repos = $this->github->getRepositories();
		foreach ($repos as &$repo)
		{
			if ($name == $repo->name)
			{
				$this->initializeRepository($repo->name, $repo->ssh_url);
			}
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
		$e = $this->execute('git checkout -q --force '.$branch);
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
	
	/**
	 * Parses the omniashell cron
	 *
	 * @return array An array('cron', 'module', 'args') which contains all information about the job
	 */
	function getActions()
	{
		// The return array with all Cron object and module + arguments
		$return = array();
		
		// Get file contents
		$crontents = file_get_contents('conf.d/actions.conf');
		$lines = preg_split('/\r\n|\r|\n/', $crontents);
		
		// Parse all the lines
		foreach ($lines as &$l)
		{
			// Preprocess the line
			$l = preg_replace('!\s+!', ' ', trim($l));
			
			// Skip empty and comment lines
			if (empty($l)) continue;
			if ($l[0] == '#') continue;
			
			// Explode it and slice it
			$parts = explode(' ', $l);
			$action = array(
				'repository' => $parts[0],
				'branch' => $parts[1],
				'module' => $parts[2],
				'arguments' =>  array_slice($parts, 3),
			);
			
			// Check if enough arguments (5 time and 1 module) at least
			if (count($parts) < 3) continue; // Malformed line so skip
					
			// Add it to the list
			$return[] = (object) $action;
		}
		return $return;
	}
}

$m = new OmniaMirror;
print_r($m->getActions());
