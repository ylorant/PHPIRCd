<?php

//Classe d'API de communication serveur (singleton)

class API
{	
	private static $_instance;
	private $_main;
 
    private function __construct () {}

    private function __clone () {}
    
    public static function getInstance () {
        if (!(self::$_instance instanceof self))
            self::$_instance = new self();
 
        return self::$_instance;
    }
    
    public static function setMain(&$main)
    {
		$instance = self::getInstance();
		
		$instance->_main = $main;
	}
	
	public static function join($id, $channel)
	{
		$instance = self::getInstance();
		
		$argv = func_get_args();
		array_shift($argv);
		
		foreach($argv as $arg)
			$instance->_main->pluginsClass->plugins['channels']->eventJoin($id, 'JOIN '.$arg);
	}
	
	public static function part($id, $channel)
	{
		$instance = self::getInstance();
		
		$argv = func_get_args();
		array_shift($argv);
		
		foreach($argv as $arg)
			$instance->_main->pluginsClass->plugins['channels']->eventPart($id, 'PART '.$arg);
	}
	
	public static function privMsg($id, $to, $content)
	{
		$instance = self::getInstance();
		$instance->_main->pluginsClass->plugins['messages']->eventPrivmsg($id, 'PRIVMSG '.$instance->_main->clients[$to]['nick'].' :'.$content, FALSE);
	}
	
	public static function notice($id, $to, $content)
	{
		$instance = self::getInstance();		
		$instance->_main->pluginsClass->plugins['messages']->eventNotice($id, 'NOTICE '.$instance->_main->clients[$to]['nick'].' :'.$content);
	}
	
	public static function serverMsg($to, $msgNumber, $content, $params = NULL)
	{
		$instance = self::getInstance();
		
		if(is_array($params))
			$params = join(' ',$params);
		
		if($params)
			$instance->_main->sendData($to, $msgNumber.' '.$instance->_main->clients[$to]['nick'].' '.$params.($content !== FALSE ? ' :'.$content : ''));
		else
			$instance->_main->sendData($to, $msgNumber.' '.$instance->_main->clients[$to]['nick'].($content !== FALSE ? ' :'.$content : ''));
	}
}
API::setMain($main);
