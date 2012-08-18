<?php

/*
 * Plugin de gestion de l'authentification au serveur 
 * Dépend de la BDD et du noyau (pour la connexion et l'attribution des ID)
 * 
 */

class Users

{
	//Variables privées
	private $_mainClass;
	private $_pluginsClass;
	private $_queriesID = array();
	
	//Constructeur
	public function __construct(&$main)
	{
		//Association des autres classes (pluigns et main)
		$this->_mainClass = $main;
		$this->_pluginsClass = &$main->pluginsClass;
		
		//Déclaration des events
		$this->_pluginsClass->addEvent('NICK','users','eventNick');
		$this->_pluginsClass->addEvent('USER','users','eventUser');
		$this->_pluginsClass->addEvent('WHOIS','users','eventWhois');
	}
	
	//Evènement d'authentification du client
	public function eventNick($id,$cmd)
	{
		$array = explode(' ',$cmd); //On explode la commande
		
		if(isset($array[1]))//Si on a rentré assez de paramètres, on traite
		{
			if(!in_array($array[1],$this->_mainClass->nicks))
			{
				if(!empty($this->_mainClass->clients[$id]['channels']))
				{
					foreach($this->_mainClass->clients[$id]['channels'] as $channel => $chanInfo)
						$this->_mainClass->broadcastCommand($channel,'NICK '.$array[1],array(),'client',$id);
				}
				
				$this->_mainClass->clients[$id]['nick'] = $array[1];
				$this->_mainClass->nicks[$id] = $array[1];
				
				if(!empty($this->_mainClass->clients[$id]['user']))
					$this->_mainClass->sendData($id,'PING :'.time(),'direct');
			}
			else
				$this->_mainClass->sendData($id,Codes::ERR_NICKNAMEINUSE.' '.$this->_mainClass->clients[$id]['nick'].' :Nick already in use');
		}
		else //Sinon on renvoie le code d'erreur correspondant
			$this->_mainClass->sendData($id,Codes::ERR_NEEDMOREPARAMS.' '.$this->_mainClass->clients[$id]['nick'].' :Not enough parameters');
	}
	
	//Event pour la configuration de l'user du client
	public function eventUser($id,$cmd)
	{
		$array = explode(' ',$cmd); //On explode la commande
		$real = explode(':',$cmd); //Utile pour le realname qui peut contenir plusieurs mots
		
		if(isset($array[4]))//Si on a rentré assez de paramètres, on traite
		{
			$this->_mainClass->clients[$id]['user'] = $array[1];
			if(isset($real[1]))
				$this->_mainClass->clients[$id]['realname'] = $real[1];
			else
				$this->_mainClass->clients[$id]['realname'] = $array[4];
			
			if(!empty($this->_mainClass->clients[$id]['nick']))
				$this->_mainClass->sendData($id,'PING :'.time(),'direct');
		}
		else //Sinon on renvoie le code d'erreur correspondant
			$this->_mainClass->sendData($id,Codes::ERR_NEEDMOREPARAMS.' '.$this->_mainClass->clients[$id]['nick'].' :Not enough parameters');
	}
	
	//Event de whois (pour savoir d'où l'user vient)
	public function eventWhois($id, $cmd)
	{
		$cmd = explode(' ', $cmd);
		
		
		
		if(isset($cmd[1]))
		{
			$match = FALSE;
			//Récupération du client
			foreach($this->_mainClass->clients as $cid => $client)
			{
				if($client['nick'] == $cmd[1])
				{
					$match = $cid;
					break;
				}
			}
			
			if($match === FALSE)
			{
				API::serverMsg($id, Codes::ERR_NOSUCHNICK, "No such nick/channel", $cmd[1]);
				return TRUE;
			}
			
			$cid = $match;
			
			API::serverMsg($id, Codes::RPL_WHOISUSER, $this->_mainClass->clients[$cid]['realname'], array($this->_mainClass->clients[$cid]['nick'], $this->_mainClass->clients[$cid]['user'], $this->_mainClass->clients[$cid]['host'], '*'));
			API::serverMsg($id, Codes::RPL_WHOISSERVER, Main::SERVERNAME, array($cmd[1], $this->_mainClass->serverHost));
			
			if(strpos($this->_mainClass->clients[$cid]['modes'], 'o'))
				API::serverMsg($id, Codes::RPL_WHOISSERVER, 'Is an IRC operator', array($cmd[1]));
			
			if(count($this->_mainClass->clients[$cid]['channels']))
			{
				$chanList = '';
				foreach($this->_mainClass->clients[$cid]['channels'] as $channel)
				{
					$mode = '';
					if(strpos($channel['modes'], 'v'))
						$mode = '+';
					if(strpos($channel['modes'], 'o'))
						$mode = '@';
					
					$chanList .= $mode.$channel['name'].' ';
				}
				
				$chanList = trim($chanList);
				API::serverMsg($id, Codes::RPL_WHOISCHANNELS, $chanList, array($cmd[1]));
			}
			
			API::serverMsg($id, Codes::RPL_WHOISIDLE, time(), array($cmd[1],time() - $this->_mainClass->clients[$cid]['lastcmd']));
			API::serverMsg($id, Codes::RPL_ENDOFWHOIS, 'End of WHOIS list', array($cmd[1]));
		}
		else
			API::serverMsg($id, Codes::ERR_NONICKNAMEGIVEN, 'No nickname given');
	}
}

$this->plugins[$pluginName] = new Users($this->_mainClass);
