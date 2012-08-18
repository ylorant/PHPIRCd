<?php

/*
 * Plugin de gestion des channels
 * Dépend de la BDD et du noyau (pour la connexion et l'attribution des ID)
 * TODO : Quitter les channels lors du QUIT
 */

class Channels
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
		$this->_pluginsClass->addEvent('JOIN','channels','eventJoin');
		$this->_pluginsClass->addEvent('PART','channels','eventPart');
		$this->_pluginsClass->addEvent('MODE','channels','eventMode');
		$this->_pluginsClass->addEvent('KICK','channels','eventKick');
		$this->_pluginsClass->addEvent('TOPIC','channels','eventTopic');
		$this->_pluginsClass->addEvent('LIST','channels','eventList');
		
		//Déclaration des routines
		$this->_pluginsClass->addRoutine('channels','routineDeleteEmptyChans');
	}
	
	//Routine supprimant les channels vides
	public function routineDeleteEmptyChans()
	{
		foreach($this->_mainClass->channels as $name => $channel)
		{
			if(count($channel['users']) == 0)
				unset($this->_mainClass->channels[$name]);
		}
	}
	
	//Fonction listant les channels disponibles sur le serveur
	public function eventList($id,$cmd)
	{
		$cmd = explode(' ',$cmd, 2);
		
		if(count($cmd) == 1)
		{
			foreach($this->_mainClass->channels as $name => $channel)
				API::serverMsg($id,Codes::RPL_LIST, ($channel['topic'] ? $channel['topic'] : ''), $name);
			API::serverMsg($id,Codes::RPL_LISTEND, 'End of LIST');
		}
	}
	
	//Fonction permettant d'obtenir et/ou de redéfinir le topic du channel
	public function eventTopic($id,$cmd)
	{
		$cmd = explode(' ',$cmd, 3);
		$channel = strtolower($cmd[1]);
		
		//On vérifie que le channel existe
		if(!isset($this->_mainClass->channels[$channel]))
		{
			API::serverMsg($id, Codes::ERR_NOSUCHCHANNEL, 'No such channel');
			return TRUE;
		}
		
		if(count($cmd) > 2) //On demande à redéfinir le topic
		{
			if(strstr($this->_mainClass->channels[$channel]['users'][$id]['modes'],'o') !== FALSE) //On vérifie qu'il a l'OP
			{
				if($cmd[2][0] == ':') //On vire le double point initial si il y en a un
					$cmd[2] = substr($cmd[2],1,strlen($cmd[2])-1);
				
				$this->_mainClass->channels[$channel]['topic'] = $cmd[2];
				$this->_mainClass->broadcastCommand($channel, 'TOPIC '.$channel.' :'.$cmd[2],array(),'client',$id);
			}
			else
				API::serverMsg($id, Codes::ERR_CHANOPRIVSNEEDED, "You're not channel operator.");
		}
		else //Sinon c'est qu'on demande à avoir le topic
		{
			if(strlen($this->_mainClass->channels[$channel]['topic']))
				$this->_mainClass->sendData($id,Codes::RPL_TOPIC." :".$this->_mainClass->channels[$channel]['topic'],'server');
			else
				API::serverMsg($id, Codes::RPL_NOTOPIC, 'No topic is set.');
		}
	}
	
	//Fonction permettant de joindre un canal, et de créer ce canal le cas échéant
	public function eventJoin($id,$cmd)
	{
		$cmd = explode(' ',$cmd);
		$channel = strtolower($cmd[1]);
		
		if(in_array($channel,array_keys($this->_mainClass->channels))) //Le channel existe
		{
			if(!in_array($id,array_keys($this->_mainClass->channels[$cmd[1]]['users']))) //On join juste le channel si on est pas dedans
			{
				$this->_mainClass->channels[$channel]['users'][$id] = array();
				$this->_mainClass->channels[$channel]['users'][$id]['nick'] = $this->_mainClass->clients[$id]['nick'];
				$this->_mainClass->channels[$channel[1]]['users'][$id]['modes'] = '';
				$this->_mainClass->clients[$id]['channels'][$channel] = array('name' => $channel, 'modes' => '');
				$this->_mainClass->broadcastCommand($channel,"JOIN ".$channel,array(),'client',$id);
				$nickList = array();
				foreach($this->_mainClass->channels[$channel]['users'] as $user)
				{
					if(strpos($user['modes'],'o') !== FALSE)
						$prefix = '@';
					elseif(strpos($user['modes'],'v') !== FALSE)
						$prefix = '+';
					else
						$prefix = '';
					
					$nickList[] = $prefix.$user['nick'];
				}
				API::serverMsg($id, Codes::RPL_NAMREPLY, join(' ',$nickList), array("=", $this->_mainClass->clients[$id]['nick'], $channel));
				API::serverMsg($id, Codes::RPL_ENDOFNAMES, "End of NAMES list", array($this->_mainClass->clients[$id]['nick'], $channel));
			}
			else //Si on est déjà su le channel -> erreur
				API::serverMsg($id, Codes::ERR_ALREADYONCHANNEL, "You're already on this channel");
		}
		else //Le channel n'existe pas -> on le crée
		{
			$this->_mainClass->channels[$channel] = array();
			$this->_mainClass->channels[$channel]['users'] = array();
			$this->_mainClass->channels[$channel]['topic'] = '';
			$this->_mainClass->channels[$channel]['modes'] = 'tn';
			$this->_mainClass->channels[$channel]['users'][$id] = array();
			$this->_mainClass->channels[$channel]['users'][$id]['nick'] = $this->_mainClass->clients[$id]['nick'];
			$this->_mainClass->channels[$channel]['users'][$id]['modes'] = 'o';
			$this->_mainClass->clients[$id]['channels'][$channel] = array('name' => $channel, 'modes' => 'o');
			$this->_mainClass->sendData($id,"JOIN ".$channel,'client',$id);
			$this->_mainClass->sendData($id,Codes::RPL_NAMREPLY." = ".$this->_mainClass->clients[$id]['nick']." ".$channel." :@".$this->_mainClass->clients[$id]['nick'],'server');
			$this->_mainClass->sendData($id,Codes::RPL_ENDOFNAMES." ".$this->_mainClass->clients[$id]['nick']." ".$channel." :End of NAMES list",'server');
		}
	}
	
	//Event de commande PART (on vire le client de la liste des users du chan et on broadcast le message de PART)
	public function eventPart($id,$cmd)
	{
		$msg = explode(':',$cmd);
		$cmd = explode(' ',$cmd);
		$cmd[1] = strtolower($cmd[1]);
		
		//Il faut que le client ayant envoyé la commande soit connecté au channel
		if(in_array($id,array_keys($this->_mainClass->channels[$cmd[1]]['users'])))
		{
			if(empty($msg[1])) //On envoie la raison uniquement si elle est fournie (attention, condition à l'envers)
				$this->_mainClass->broadcastCommand($cmd[1],'PART '.$cmd[1],array(),'client',$id);
			else
				$this->_mainClass->broadcastCommand($cmd[1],'PART '.$cmd[1].' :'.$msg[1],array(),'client',$id);
		
			unset($this->_mainClass->channels[$cmd[1]]['users'][$id]);
			if(!count($this->_mainClass->channels[$cmd[1]]['users']))
				unset($this->_mainClass->channels[$cmd[1]]);
		}
		else
			API::serverMsg($id, Codes::ERR_NOTONCHANNEL, 'You\'re not on that channel.', $cmd[1]);
	}
	
	public function eventKick($id,$cmd)
	{
		$command = $cmd;
		$cmd = explode(' ',$cmd);
		$cmd[1] = strtolower($cmd[1]);
		$msg = explode(':',$command);
		
		//Si l'user entre un nom de channel valide
		if($cmd[1][0] != '#')
		{
			API::serverMsg($id,Codes::ERR_NOSUCHCHANNEL, "No such channel");
			return TRUE;
		}
		
		//Si le channel existe
		if(!in_array($cmd[1],array_keys($this->_mainClass->channels)))
		{
			API::serverMsg($id, Codes::ERR_NOSUCHNICK, "No such nick/channel");
			return TRUE;	
		}
		
		//Si l'user est dans le channel
		if(!in_array($id,array_keys($this->_mainClass->channels[$cmd[1]]['users'])))
		{
			API::serverMsg($id,Codes::ERR_NOTONCHANNEL, 'You\'re not on that channel', $cmd[1]);
			return TRUE;
		}
		
		//Si il a l'op
		if(strpos($this->_mainClass->channels[$cmd[1]]['users'][$id]['modes'],'o') === FALSE)
		{
			API::serverMsg($id,Codes::ERR_CHANOPRIVSNEEDED, 'You\'re not channel operator.', $cmd[1]);
			return TRUE;
		}
		
		$clientID = array_search($cmd[2],$this->_mainClass->nicks);
		
		//Si l'user à kicker est présent sur le canal
		if(!in_array($clientID,array_keys($this->_mainClass->channels[$cmd[1]]['users'])))
		{
			API::serverMsg($id, Codes::ERR_USERNOTINCHANNEL, 'They aren\'t on that channel', $cmd[1]);
			return TRUE;
		}
		
		//On broadcaste la commande de kick
		if(isset($msg[1]))
			$this->_mainClass->broadcastCommand($cmd[1],$command,array(),'client',$id);
		else
			$this->_mainClass->broadcastCommand($cmd[1],$command.' :'.$cmd[2],array(),'client',$id);
		
		unset($this->_mainClass->channels[$cmd[1]]['users'][$clientID]);
		unset($this->_mainClass->clients[$clientID]['channels'][$cmd[1]]);
	}
	
	//Event de gestion des modes de channels
	public function eventMode($id,$cmd)
	{
		$command = $cmd;
		$cmd = explode(' ',$cmd);
		$cmd[1] = strtolower($cmd[1]);
		
		if($cmd[1][0] == '#') //On vérifie que c'est un channel
		{
			//Uniquement si l'user est dans le channel et qu'il a les droits (op)
			if(!in_array($id,array_keys($this->_mainClass->channels[$cmd[1]]['users'])))
			{
				API::serverMsg($id,Codes::ERR_NOTONCHANNEL, 'You\'re not on that channel.', $cmd[1]);
				return TRUE;
			}
			else
			{
				if(strpos($this->_mainClass->channels[$cmd[1]]['users'][$id]['modes'],'o') === FALSE)
				{
					API::serverMsg($id,Codes::ERR_CHANOPRIVSNEEDED, 'You\'re not channel operator.', $cmd[1]);
					return TRUE;
				}
			}
			
			//On vérifie que la syntaxe est bonne
			if(isset($cmd[2]))
			{
				if($cmd[1][0] == '#' && in_array($cmd[2][0],array('+','-')))
				{
					//On vérifie que les modes sont reconnus
					if(count(array_intersect(str_split($cmd[2]),array('o','b','v','+','-'))) != strlen($cmd[2]) && count($cmd) > 3)
					{
						API::serverMsg($id,Codes::ERR_UNKNOWNMODE, ' '.$cmd[1].' :Unknown mode.');
						return FALSE;
					}
					elseif(count(array_intersect(str_split($cmd[2]),array('m','+','-'))) != strlen($cmd[2]) && count($cmd) <= 3)
					{
						API::serverMsg($id,Codes::ERR_UNKNOWNMODE, 'Unknown mode.');
						return FALSE;
					}
					if(count($cmd) > 3) //Si un pseudo a été entré, c'est un mode de client
						$clientID = array_search($cmd[3],$this->_mainClass->nicks);
					else
						$clientID = FALSE;
					var_dump($clientID);
					$modes = str_split($cmd[2]);
					foreach($modes as $currentMode) //On parse tous les caractères de mode
					{
						switch ($currentMode) //Switch contenant les différents types de caractères
						{
							//+ ou -, on le note, et on passe à la suite (0 pour + et 1 pour -)
							case '+':
								if(!isset($operation)) //On vérifie si l'opération n'a pas déjà été effectuée (auquel cas on indiquera l'erreur)
									$operation = 0;
								else
									$operation = -1;
								break;
							case '-':
								if(!isset($operation)) //Pareil que pour l'opération +
									$operation = 1;
								else
									$operation = -1;
									break;
							//Pour le reste, on vérifie si + ou - est déjà réglé, sinon on sauvegare l'erreur
							default:
								if(isset($operation))
								{
									if($operation != -1) //On pense à régler les modes uniquement si il n'y a pas d'erreur
									{
										if($clientID) //Réglage du mode user
										{
											$done = 1;
											if(!$operation && strpos($this->_mainClass->channels[$cmd[1]]['users'][$clientID]['modes'],$currentMode) === FALSE) //C'est un ajout de mode et que le mode n'est pas déjà activé
												$this->_mainClass->channels[$cmd[1]]['users'][$clientID]['modes'] .= $currentMode;
											elseif(!$operation) //Sinon, on indique une erreur (type présent)
												$done = 0;
											if($operation && strpos($this->_mainClass->channels[$cmd[1]]['users'][$clientID]['modes'],$currentMode) !== FALSE) //C'est une suppression de mode et que le mode est déjà activé
												$this->_mainClass->channels[$cmd[1]]['users'][$clientID]['modes'] = str_replace($currentMode,'',$this->_mainClass->channels[$cmd[1]]['users'][$clientID]['modes']);
											elseif($operation) //Sinon, on indique aussi une erreur (type absent)
												$done = 0;
										}
										else //Réglage du mode channel
										{
											$done = 1;
											if(!$operation && strpos($this->_mainClass->channels[$cmd[1]]['modes'],$currentMode) === FALSE) //C'est un ajout de mode et que le mode n'est pas déjà activé
												$this->_mainClass->channels[$cmd[1]]['modes'] .= $currentMode;
											elseif(!$operation) //Sinon, on indique une erreur (type présent)
												$done = 0;
											if($operation && strpos($this->_mainClass->channels[$cmd[1]]['modes'],$currentMode) !== FALSE) //C'est une suppression de mode et que le mode est déjà activé
												$this->_mainClass->channels[$cmd[1]]['modes'] = str_replace($currentMode,'',$this->_mainClass->channels[$cmd[1]]['modes']);
											elseif($operation) //Sinon, on indique aussi une erreur (type absent)
												$done = 0;
										}
									}
								}
								else
									$operation = -1;
						}
					}
					if($operation == -1)
						API::serverMsg($id,Codes::ERR_UNKNOWNMODE, 'Unknown mode.');
					else
						$this->_mainClass->broadcastCommand($cmd[1],$command,array(),'client',$id);
				}
			}
			//Mode inconnu -> ?
			//elseif(!empty($cmd[2]))
				//API::serverMsg($id,Codes::ERR_UNKNOWNMODE, 'Unknown mode.');
			else
				API::serverMsg($id,Codes::RPL_CHANNELMODEIS, FALSE, array($cmd[1], $this->_mainClass->channels[$cmd[1]]['modes']));
		}
	}
}

$this->plugins[$pluginName] = new Channels($this->_mainClass);
