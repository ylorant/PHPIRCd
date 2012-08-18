<?php

/*
 * Plugin de gestion des bots
 * Dépend de la BDD et du noyau (pour la connexion et l'attribution des ID)
 * 
 */

class Bots
{
	//Variables privées
	private $_mainClass;
	private $_pluginsClass;
	private $_queriesID = array();
	private $_botName;
	private $_botUser;
	private $_botID;
	private $_helpFile;
	private $_help;
	
	private $_hashChars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	
	public function __construct(&$main)
	{
		//Association des autres classes (pluigns et main)
		$this->_mainClass = $main;
		$this->_pluginsClass = &$main->pluginsClass;
		
		//Déclaration des events
		//$this->_pluginsClass->addEvent('PRIVMSG','bots','eventMsg');
		//$this->_pluginsClass->addEvent('NOTICE','bots','eventMsg');
		
		//Chargement du fichier de config
		$config = parse_ini_file('conf/bots/register.ini');
		$this->_botName = $config['botname'];
		$this->_botUser = $config['botuser'];
		$this->_helpFile = $config['helpfile'];
		
		//Création du bot
		$this->_botID = $this->_mainClass->addBot($this->_botName, $this->_botUser,'services.'.php_uname('n'));
		
		//Bind des commandes du bot
		$this->_pluginsClass->addBotEvent($this->_botID,'REGISTER','bots','eventRegister');
		$this->_pluginsClass->addBotEvent($this->_botID,'VALIDATE','bots','eventValidate');
		$this->_pluginsClass->addBotEvent($this->_botID,'LOGIN','bots','eventLogin');
		$this->_pluginsClass->addBotEvent($this->_botID,'HELP','bots','eventHelp');
		$this->_pluginsClass->addBotEvent($this->_botID,'?','bots','eventHelp');
	}
	
	public function eventLogin($id, $cmd)
	{
		$user = $this->getUser($this->_mainClass->clients[$id]['nick']);
		if(!$user)
		{
			API::notice($this->_botID, $id, 'This user does not exists.');
			return TRUE;
		}
		
		if(!$user['active'])
		{
			API::notice($this->_botID, $id, 'This account is not active.');
			return TRUE;
		}
		
		if(sha1($cmd[1]) == $user['password'])
		{
			$this->_mainClass->clients[$id]['auth'] = TRUE;
			API::notice($this->_botID, $id, 'Your are now logged.');
		}
		else
			API::notice($this->_botID, $id, 'Wrong password.');
	}
	
	public function eventValidate($id, $cmd)
	{
		$user = $this->getUser($this->_mainClass->clients[$id]['nick']);
		if(!$user)
		{
			API::notice($this->_botID, $id, 'This user does not exists.');
			return TRUE;
		}
		
		if($cmd[1] == $user['hash'])
		{
			$this->setActive($this->_mainClass->clients[$id]['nick'], 1);
			API::notice($this->_botID, $id, 'Your account is now active.');
		}
		else
			API::notice($this->_botID, $id, 'The hash is wrong.');
	}
	
	public function eventHelp($id, $cmd)
	{
		$this->readHelpFile();
		//Affichage de l'aide spécifique d'une commande
		if(isset($cmd[1]))
		{
			//Détermination de la dernière commande (si ?, alors listage des sous-commandes)
			$commandList = FALSE;
			if($cmd[count($cmd)-1] == '?')
			{
				$commandList = TRUE;
				array_pop($cmd);
			}
			
			//Suppression du premier élément de l'array (pour pouvoir parser correctement les paramètres)
			$command = &$this->_help;
			array_shift($cmd);
			
			foreach($cmd as $cur)
			{
				if(isset($command['commands'][$cur]))
					$command = $command['commands'][$cur];
				else
				{
					$command = FALSE;
					break;
				}
			}
			
			if(!$command)
			{
				API::notice($this->_botID, $id, 'This command does not exists. To get a list of available commands, type HELP.');
				return TRUE;
			}
			
			if($commandList)
			{
				API::notice($this->_botID, $id, 'Here is the command list for '.$cmd[count($cmd)-1].':');
			foreach($this->_help['commands'] as $name => $command)
				API::notice($this->_botID, $id, $name.' - '.$command['description']);
				
			API::notice($this->_botID, $id, 'For further information about a command, type HELP <command>');
			}
			else
			{
				API::notice($this->_botID, $id, 'Help for '.$cmd[count($cmd)-1].' : ');
				API::notice($this->_botID, $id, 'Usage :  '.$command['usage'].'.');
				API::notice($this->_botID, $id, 'Description :  '.$command['description'].'.');
			}
		}
		else //Affichage de la liste des commandes
		{
			API::notice($this->_botID, $id, 'Here is the command list :');
			foreach($this->_help['commands'] as $name => $command)
				API::notice($this->_botID, $id, $name.' - '.$command['description']);
			
			API::notice($this->_botID, $id, 'For further information about a command, type HELP <command>');
		}
	}
	
	public function eventRegister($id,$cmd)
	{
		if(isset($cmd[3]))
		{
			if($cmd[2] != $cmd[3])
			{
				API::notice($this->_botID, $id,'Your passwords are not equal.');
				return TRUE;
			}
			
			if(!filter_var($cmd[1], FILTER_VALIDATE_EMAIL))
			{
				API::notice($this->_botID, $id,'You have mispelled your e-mail address.');
				return TRUE;
			}
			
			if(is_file('data/users/'.$this->_mainClass->clients[$id]['nick'].'.xml'))
				API::notice($this->_botID, $id,'An user with this name already exists.');
			else
			{
				$hash = $this->userCreate($this->_mainClass->clients[$id]['nick'], $cmd[2]);
				
				$args = array(
				'hash' => $hash,
				'network' => Main::NETWORK,
				'botname' => $this->_botName,
				'user' => $this->_mainClass->clients[$id]['nick'],
				'password' => $cmd[2]
				);
				
				mail($cmd[1], '['.Main::NETWORK.'] User account validation', Main::parseTplFile('data/bots/register.mail', $args));
				API::notice($this->_botID, $id,'Your user has been created. An e-mail has been sended to you.');
				API::notice($this->_botID, $id,'You have to send the command in it to finish your registration.');
			}
		}
		else
			API::notice($this->_botID, $id,'Parameters are missing.');
	}
	
	public function readHelpFile()
	{
		//Réinitialistation de l'aide
		$this->_help = array('commands' => array());
		$dom = new DOMDocument();
		$dom->load($this->_helpFile);
		$help = $dom->getElementsByTagName('help')->item(0)->getElementsByTagName('commands')->item(0);
		$this->_readCommandNode($help, $this->_help['commands']);
	}
	
	private function _readCommandNode($node, &$parent)
	{
		$commands = $node->childNodes;
		
		foreach($commands as $cmd)
		{
			echo $cmd->nodeName."\n";
			if($cmd->nodeName == 'command')
			{
				$parent[$cmd->getAttribute('name')] = array();
				
				$cmdname = $cmd->getAttribute('name');
				foreach($cmd->childNodes as $node)
				{
					switch($node->nodeName)
					{
						case 'commands':
							$parent[$cmd->getAttribute('name')]['commands'] = array();
							$this->_readCommandNode($node, $parent[$cmd->getAttribute('name')]['commands']);
							break;
						case 'usage':
							$parent[$cmdname]['usage'] = $node->nodeValue;
							break;
						case 'description':
							$parent[$cmdname]['description'] = $node->nodeValue;
							break;
					}
				}
			}
		}
	}
	
	public function userCreate($nick, $password)
	{
		touch('data/users/'.$nick.'.xml');
		$dom = new DOMDocument();
		$user = $dom->createElement('user');
		$hash = substr(str_shuffle(str_repeat($this->_hashChars, 8)),0, 8);
		$user->setAttribute('hash', $hash);
		$nickNode = $dom->createElement('nick');
		$nickNode->setAttribute('value', $nick);
		$passwd = $dom->createElement('password');
		$passwd->setAttribute('value', sha1($password));
		$active = $dom->createElement('active');
		$active->setAttribute('value', '0');
		$channels = $dom->createElement('channels');
		$user->appendChild($nickNode);
		$user->appendChild($passwd);
		$user->appendChild($channels);
		$user->appendChild($active);
		$dom->appendChild($user);
		$dom->save('data/users/'.$nick.'.xml');
		
		return $hash;
	}
	
	public function setActive($nick, $active)
	{
		$dom = new DOMDocument();
		$dom->load('data/users/'.$nick.'.xml');
		$dom->getElementsByTagName('active')->item(0)->setAttribute('value', $active);
		$dom->save('data/users/'.$nick.'.xml');
	}
	
	public function getUser($nick)
	{
		if(!is_file('data/users/'.$nick.'.xml'))
			return FALSE;
		
		$dom = new DOMDocument();
		$dom->load('data/users/'.$nick.'.xml');
		$user = $dom->getElementsByTagName('user')->item(0);
		$nick = $user->getElementsByTagName('nick')->item(0)->getAttribute('value');
		$hash = $user->getAttribute('hash');
		$passwd = $user->getElementsByTagName('password')->item(0)->getAttribute('value');
		$active = $user->getElementsByTagName('active')->item(0)->getAttribute('value');
		$channels = array();
		$chanNodes = $user->getElementsByTagName('channels')->item(0)->childNodes;
		
		for($i = 0; $i < $chanNodes->length; $i++)
		{
			$node = $chanNodes->item($i);
			$channels[$node->getAttribute('name')] = $node->getAttribute('modes');
		}
		
		$userData = array(
		'nick' => $nick,
		'hash' => $hash,
		'password' => $passwd,
		'channels' => $channels,
		'active' => $active
		);
		
		return $userData;
	}
}

$this->plugins[$pluginName] = new Bots($this->_mainClass);
