<?php

class Base
{
	
	private $arguments;
	private $configCache;
	
	public function __construct($default = true)
	{
		global $argc, $argv;
		$this->configCache = array();
		$this->parseCmd(is_array($default) ? $default : array_slice($argv, 1));
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

	static public function kill($pid, $force = false)
	{
		return $this->execute('kill'.($force ? ' -9 ' : ' ').$pid);
	}
	
	private function parseCmd($arguments)
	{
		$this->arguments = array();
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
	
	public function getCmd($index)
	{
		return array_key_exists($index, $this->arguments) ? $this->arguments[$index] : false;
	}
	
	/**
	 * Get a config value
	 *
	 * @return string If key not exists returns null else the value of the key in the ini
	 */
	protected function getConfig($name, $section, $key = null)
	{
		if (!array_key_exists($name, $this->configCache))
			$this->configCache[$name] = @parse_ini_file('conf.d/'.$name.'.ini', true);

		if (!is_array($this->configCache[$name]))
			throw new Exception('Configuration file does not exists');
		
		// Check if section and key exists
		if (array_key_exists($section, $this->configCache[$name]))
			if($key === null)
				return true;
			elseif (array_key_exists($key, $this->configCache[$name][$section]))
				return $this->configCache[$name][$section][$key];
		return false;
	}
}