<?php

class Base
{
	
	private $arguments;
	
	public function __construct($parse = true)
	{
		global $argc, $argv;
		if ($parse)
		{
			$this->parseCmd($argv);
		}
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
	
	private function parseCmd($argv)
	{
		$this->arguments = array();
		for ($i = 1; $i < count($argv); $i++)
		{
			// Flag var
			if (preg_match('/^-+(.*?)$/', $argv[$i], $match))
			{
				$args = explode('=', $match[1]);
				$this->arguments[$args[0]] = (count($args) == 2 ? $args[1] : true);		
			}
			else
			{
				$this->arguments[] = $argv[$i];
			}		
		}
	}
	
	public function getCmd($index)
	{
		return array_key_exists($index, $this->arguments) ? $this->arguments[$index] : false;
	}
}