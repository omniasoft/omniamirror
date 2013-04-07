<?php

abstract class Module extends Base
{
	function __construct($arguments)
	{
		parent::__construct($arguments);
		
	}

	abstract public function run($repository, $branch, $path);
}