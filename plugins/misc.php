<?php

class Misc
{
	private $_mainClass;
	private $_pluginsClass;
	private $_queryID;
	private $_motd;
	
	//Constructeur
	public function __construct(&$main)
	{
		//Définition des références vers les classes nécéssaires
		$this->_pluginsClass = &$main->pluginsClass;
		$this->_mainClass = &$main;
		
		//Lecture du MOTD dans les fichiers de config
		$this->_motd = file('conf/motd.txt');
		
		//Ajout des évènements sur signaux
		$this->_pluginsClass->addEvent('QUIT','misc','eventQuit');
		$this->_pluginsClass->addEvent('PONG','misc','eventPong');
		$this->_pluginsClass->addEvent('PING','misc','eventPing');
		
		//Ajout des routines
		$this->_pluginsClass->addRoutine('misc','routineTimeout');
	}
	
	//Gestion du timeout
	public function routineTimeout()
	{
		if(count($this->_mainClass->clients) != 0)
		{
			foreach($this->_mainClass->clients as $id => &$client)
			{
				if($client['lastcmd'] + 120 < time() && $client['pinged'] == 0 && !in_array($id, $this->_mainClass->ignoreList))
				{
					$this->_mainClass->sendData($id,'PING :'.$this->_mainClass->serverHost,'direct');
					$client['pinged'] = 1;
				}
				elseif($client['lastcmd'] + 150 < time() && !in_array($id, $this->_mainClass->ignoreList))
				{
					socket_close($this->_mainClass->clients[$id]['socket']);
					unset($this->_mainClass->nicks[$id]);
					unset($this->_mainClass->players[$id]);
					$this->_mainClass->clientDisconnect($id,'Ping timeout');
				}
			}
		}
	}
	
	//Commande pong (ne fait rien, sauf si c'est le premier auquel le client répond, auquel cas, on envoie le MOTD etoo)
	public function eventPong($id,$cmd)
	{
		if(!$this->_mainClass->clients[$id]['firstping'])
		{
			$this->_mainClass->clients[$id]['firstping'] = TRUE;
			
			$this->_mainClass->sendData($id,Codes::RPL_WELCOME.' '.$this->_mainClass->clients[$id]['nick'].' :Welcome on the '.Main::NETWORK.' Network !');
			
			$this->_mainClass->sendData($id,Codes::RPL_MOTDSTART.' '.$this->_mainClass->clients[$id]['nick'].' :- '.Main::SERVERNAME.' Message of the day -');
			foreach($this->_motd as $motd)
				$this->_mainClass->sendData($id,Codes::RPL_MOTD.' '.$this->_mainClass->clients[$id]['nick'].' :- '.rtrim($motd));
			$this->_mainClass->sendData($id,Codes::RPL_ENDOFMOTD.' '.$this->_mainClass->clients[$id]['nick'].' :End of MOTD command');
		}
		return TRUE;
	}
	
	//Commande PING (on retourne le PONG correspondant
	public function eventPing($id,$cmd)
	{
		$cmd = explode(' ',$cmd);
		$this->_mainClass->sendData($id,'PONG '.$this->_mainClass->serverHost.' :'.str_replace(':','',$cmd[1]),'server');
	}
	
	//Déconnecte le client
	public function eventQuit($id,$cmd)
	{
		$cmd = explode(':',$cmd);
		if(count($this->_mainClass->clients[$id]['channels']))
		{
			foreach(array_keys($this->_mainClass->clients[$id]['channels']) as $curChan)
			{
				$this->_mainClass->broadcastCommand($curChan,'QUIT :'.$cmd[1],array($id),'client',$id);
				unset($this->_mainClass->channels[$curChan]['users'][$id]);
			}
		}
		socket_close($this->_mainClass->clients[$id]['socket']);
		unset($this->_mainClass->nicks[$id]);
		unset($this->_mainClass->players[$id]);
		$this->_mainClass->clientDisconnect($id,'commande QUIT');
	}
}

$this->plugins[$pluginName] = new Misc($this->_mainClass);
