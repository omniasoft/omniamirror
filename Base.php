<?php

class Base
{
	
	private $arguments;
	private $configCache;
	
    /**
     * Constructor
     * 
     * @param array $default overloaded if you want to pass your own arguments instead of default argv
     */	
	public function __construct($default = true)
	{
		global $argc, $argv;
		$this->configCache = array();
		$this->parseCmd(is_array($default) ? $default : (($argc >= 1) ? array_slice($argv, 1) : null));
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
	static function execute($command, $catchError = true)
	{
		$cmd = $command.($catchError ? ' 2>&1' : ' > /dev/null 2>/dev/null &');
		return trim(`$cmd`);
	}
	
    /**
     * Kills a process by using the linux kill command
     * 
     * @param int $pid 
     * @param bool $force  
     * 
     * @return string
     */	
	static public function kill($pid, $force = false)
	{
		return $this->execute('kill'.($force ? ' -9 ' : ' ').$pid);
	}
	
    /**
     * Parses command line arguments passed to class
     * 
     * @param array $arguments 
     */	
	private function parseCmd($arguments)
	{
		$this->arguments = array();
		
		if( ! is_array($arguments))
			return;
			
		foreach ($arguments as &$argument)
		{
			// Flag var
			if (preg_match('/^-+(.*?)$/', $argument, $match))
			{
				$args = explode('=', $match[1]);
				$this->arguments[$args[0]] = (count($args) == 2 ? $args[1] : true);		
			}
			else
			{
				$this->arguments[] = $argument;
			}
		}
	}
	
    /**
     * Get the value for a command line flag
     * 
     * @param mixed $index 
     * 
     * @return mixed
     */
	public function getCmd($index)
	{
		return array_key_exists($index, $this->arguments) ? $this->arguments[$index] : false;
	}
	
    /**
     * Gets a configuration value from the config ini
     * 
     * @param string $name 
     * @param string $section 
     * @param string $key  
     * 
     * @return string If key not exists returns null else the value of the key in the ini
     */	
	public function getConfig($name, $section, $key = null)
	{
		if (!array_key_exists($name, $this->configCache))
			$this->configCache[$name] = @parse_ini_file('conf.d/'.$name.'.ini', ($section == null ? false : true));

		if (!is_array($this->configCache[$name]))
			throw new Exception('Configuration file does not exists');
		
		// Check if section and key exists
		if ($section == null)
		{
			if (array_key_exists($key, $this->configCache[$name]))
				return $this->configCache[$name][$key];
		}
		else
		{
			if (array_key_exists($section, $this->configCache[$name]))
				if($key === null)
					return true;
				elseif (array_key_exists($key, $this->configCache[$name][$section]))
					return $this->configCache[$name][$section][$key];
		}
		return false;
	}
		
	/** 
	 * Compress a list of files or a mix of files and folders
	 *
	 * @param string $paths An array of paths to files or folders
	 * @param bool $filesOnly True if you want only files in archive excluding directory
	 * @return string The pathname to the archive
	 */
	static function compress($root, $output)
	{
		if ( ! is_dir($root))
			return false;
		
		// Run the command
		$cwd = getcwd();
		Base::execute('tar czf "'.$output.'" --exclude=\'.git\' --exclude=\'.gitignore\' --exclude=\'.gitmodules\' -C '.$root.' .');
		chdir($cwd);
				
		// Return output
		return (file_exists($output) && filesize($output) > 0);
	}
}