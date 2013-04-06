<?php
define('ROOT', getcwd());
define('MODULE', 'Module');

spl_autoload_register(function($class)
{
	$include = ROOT.'/';
	if(substr($class, -strlen(MODULE)) == MODULE)
		$include .= 'modules/'.$class.'.php';
	else
		$include .= $class.'.php';
	
	if (!file_exists($include))
		throw new Exception('Could not load '.$class.', Full path: '.$include);
	else
		include($include);
});

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
		$this->updateRepositories();
		
		// Go trough all crons and run them if due
		foreach ($this->actions as $action)
		{
			printf("    Running module %s for %s/%s\n", $action->module, $action->repository, $acion->branch);
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
			call_user_func(array($module, 'run'), $this->dirRepo($repository));
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

class OmniaMirror extends Base
{
	function run()
	{
		// Get all cron jobs
		$actions = $this->getActions();
		printf("%d account acction(s)\n", count($actions));
		
		// Go trough all crons and run them if due
		foreach ($actions as $key => $action)
		{
			printf("  Executing (%d) jobs for %s\n", count($action->actions), $key);
			
			$mirror = new Mirror($action->github, $action->actions);
			$mirror->run();
		}	
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
			
			// Explode it and check
			$parts = explode(' ', $l);
			
			if(!$this->getConfig('github', $parts[0]))
				continue;
			
			// Build action object
			$action = array(
				'repository' => $parts[1],
				'branch' => $parts[2],
				'module' => $parts[3],
				'arguments' =>  array_slice($parts, 4),
			);
			
			// Check if enough arguments (5 time and 1 module) at least
			if (count($parts) < 3) continue; // Malformed line so skip
					
			// Add it to the list
			if (!array_key_exists($parts[0], $return))
				$return[$parts[0]] = (object) array(
					'github' => (object) array(
						'user' => $this->getConfig('github', $parts[0], 'user'),
						'password' => $this->getConfig('github', $parts[0], 'password'),
						'url' => $this->getConfig('github', $parts[0], 'url'),
					),
					'actions' => array(),
				);
			
			$return[$parts[0]]->actions[] = (object) $action;
		}
		return $return;
	}
}

$m = new OmniaMirror;
$m->run();
