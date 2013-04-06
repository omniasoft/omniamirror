<?php
class Github
{
	private $user;
	private $password;
	
	private $cache;
	
	public function __construct($user, $password)
	{
		$this->user = $user;
		$this->password = $password;	
		$this->cache = array();
	}
	
	public function getUser()
	{
		return $this->user;
	}
	
    /**
     * Internal function to get the correct url for a given action
     * 
     * @param string $action 
     * 
     * @return string
     */
	public function getUrl($action)
	{
		return 'https://'.$this->user.':'.$this->password.'@api.github.com/'.trim($action, '/');
	}

    /**
     * Get request from github
     * 
     * @param string $action 
     * 
     * @return array Json decoded string
     */
	private function get($action)
	{
		if (!array_key_exists($action, $this->cache))
			$this->cache[$action] = json_decode(file_get_contents($this->getUrl($action)));
		return $this->cache[$action];
	}
	
	/**
	 * Post request to github
	 * 
	 * @param string $action 
	 * @param array $arguments (key => value)
	 * 
	 * @return bool 
	 */
	private function post($action, $arguments)
	{
		$ch = curl_init($this->getUrl($action));
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($arguments));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
		
		$response = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		
		return ($status != 400 && $status != 422);
	}
	
	public function getRepositories()
	{
		return $this->get('/user/repos');
	}
	
	public function getHooks($repository)
	{ 
		return $this->get('/repos/'.$this->user.'/'.$repository.'/hooks');
	}
	
	/**
	 * Set web hook
	 * 
	 * Installs a web hook for a given repository to the given url
	 *
	 * @param string $repository 
	 * @param string $url 
	 * 
	 * @return bool
	 */
	public function setWebHook($repository, $url)
	{
		$hooks = $this->getHooks($repository);
		foreach($hooks as &$hook)
		{
			if(isset($hook->config->url) && $hook->config->url == $url)
				return false;
		}
				
		$arguments = array(
			'name' => 'web',
			'active' => true,
			'events' => array('push'),
			'config' => array(
				'url' => $url,
				'content_type' => 'form',
			),
		);
		return $this->post('/repos/'.$this->user.'/'.$repository.'/hooks', $arguments);
	}
}
