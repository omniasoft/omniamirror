<?php

abstract class Module extends Base
{
	protected $github;
	
	function __construct($github, $arguments)
	{
		parent::__construct($arguments);
		$this->github = $github;
	}

	abstract public function run($repository, $branch, $path);
}