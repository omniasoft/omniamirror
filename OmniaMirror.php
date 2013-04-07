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

class OmniaMirror extends Base
{
	function run()
	{
		// Get all cron jobs
		$actions = $this->getActions();
		printf("%d account action(s)\n", count($actions));
		
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
