<?php
/*
 * Plugin de gestion des messages
 * Dépend du noyau (pour la connexion et l'attribution des ID)
 * 
 */

class Messages
{
	//Variables privées
	private $_mainClass;
	private $_pluginsClass;
	private $_queriesID = array();
	
	public function __construct(&$main)
	{
		//Association des autres classes (pluigns et main)
		$this->_mainClass = $main;
		$this->_pluginsClass = &$main->pluginsClass;
		
		//Déclaration des events
		$this->_pluginsClass->addEvent('PRIVMSG','messages','eventPrivmsg');
		$this->_pluginsClass->addEvent('NOTICE','messages','eventNotice');
	}
	
	//Event permettant d'envoyer des notices
	public function eventNotice($id,$cmd)
	{
		$command = explode(' ',$cmd);
		$data = explode(':',$cmd);
		
		if(strpos($command[1],'#') !== FALSE)
		{
			if(strstr($this->_mainClass->channels[$command[1]]['modes'],'m') === FALSE OR strstr($this->_mainClass->channels[$command[1]]['users'][$id]['modes'], 'o') !== FALSE OR strstr($this->_mainClass->channels[$command[1]]['users'][$id]['modes'], 'v') !== FALSE)
				$this->_mainClass->broadcastCommand($command[1],$cmd,array($id),'client',$id);
		}
		else
		{
			$ret = NULL;
			foreach($this->_mainClass->clients as $curId => $cur)
			{
				if($cur['nick'] == $command[1])
				{
					$ret = $curId;
					break;
				}
			}
			
			if($ret)
				$this->_mainClass->sendData($ret,$cmd,'client',$id);
		}
	}
	
	//Event permettant d'envoyer un message
	public function eventPrivmsg($id,$cmd,$return = TRUE)
	{
		$command = explode(' ',$cmd);
		$data = explode(':',$cmd);
		
		if(strpos($command[1],'#') !== FALSE)
		{
			if(strstr($this->_mainClass->channels[$command[1]]['modes'],'m') === FALSE OR strstr($this->_mainClass->channels[$command[1]]['users'][$id]['modes'], 'o') !== FALSE OR strstr($this->_mainClass->channels[$command[1]]['users'][$id]['modes'], 'v') !== FALSE)
				$this->_mainClass->broadcastCommand(strtolower($command[1]),$cmd,array($id),'client',$id);
		}
		else
		{
			$ret = NULL;
			foreach($this->_mainClass->clients as $curId => $cur)
			{
				if($cur['nick'] == $command[1])
				{
					if(!in_array($curId,$this->_mainClass->ignoreList))
					{
						$ret = $curId;
						break;
					}
					else
						return TRUE;
				}
			}
			
			if($ret)
				$this->_mainClass->sendData($ret,$cmd,'client',$id);
			elseif($return)
				$this->_mainClass->sendData($id,Codes::ERR_NOSUCHNICK." ".$this->_mainClass->clients[$id]['nick']." ".$command[1]." :No such nick/channel",'server');
		}
	}
}

$this->plugins[$pluginName] = new Messages($this->_mainClass);
