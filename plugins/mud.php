<?php

/*
 * Plugin de gestion du MUD
 * Dépend du noyau (pour la connexion et l'attribution des ID)
 * 
 */

include('plugins/mudcommands.php');
include('plugins/mudevents.php');

class MUD extends MUDEvents
{
	//Variables privées
	protected $_mainClass;
	protected $_pluginsClass;
	protected $_queriesID = array();
	protected $_mudName;
	protected $_mudUser;
	protected $_mudFile;
	protected $_startRoom;
	protected $_lua;
	protected $_mudRooms = array();
	protected $_mudEvents = array();
	protected $_aliases;
	protected $_states = array();
	protected $_luaCommands = array();
	
	public $_botID;
	
	public function __construct(&$main)
	{
		//Association des autres classes (pluigns et main)
		$this->_mainClass = $main;
		$this->_pluginsClass = &$main->pluginsClass;
		
		//Chargement de la configuration
		$config = parse_ini_file('conf/mud.ini');
		$this->_mudName = $config['mudname'];
		$this->_mudUser = $config['muduser'];
		$this->_mudFile = $config['mudfile'];
		$this->_mudAdmins = explode(',', $config['mudadmins']);
		
		//Création du bot
		$this->_botID = $this->_mainClass->addBot($this->_mudName,$this->_mudUser,'mud.'.php_uname('n'));
		
		//Instanciation de Lua
		$this->_loadLuaEnv();
		
		//Chagement du fichier de mud
		$this->_parseMUD($this->_mudFile);
		
		
		
		//Binding des events
		$this->_pluginsClass->addBotEvent($this->_botID, 'hello', 'mud', 'eventStart');
		$this->_pluginsClass->addBotEvent($this->_botID, 'go', 'mud', 'eventGo');
		$this->_pluginsClass->addBotEvent($this->_botID, 'look', 'mud', 'eventLook');
		$this->_pluginsClass->addBotEvent($this->_botID, 'take', 'mud', 'eventTake');
		$this->_pluginsClass->addBotEvent($this->_botID, NULL, 'mud', 'execLuaCommands');
		
		//Event de reload du MUD
		$this->_pluginsClass->addBotEvent($this->_botID, 'reloadmud','mud','eventReloadMUD');
	}
	
	//Event de reload du MUD
	public function eventReloadMUD($id, $cmd)
	{
		if($this->_mainClass->clients[$id]['auth'] && in_array($this->_mainClass->clients[$id]['nick'], $this->_mudAdmins))
		{
			$this->_loadLuaEnv();
			$this->_parseMUD($this->_mudFile);
			API::privMsg($this->_botID, $id, 'MUD reloaded.');
		}
		else
			API::privMsg($this->_botID, $id, 'Obviously, you can\'t do this.');
	}
	
	protected function _parseBool($str)
	{
		if($str == 'true')
			return TRUE;
		else
			return FALSE;
	}
	
	protected function _parseMUD($mudfile)
	{
		$this->_mudRooms = array();
		$this->_mudEvents = array();
		$this->_aliases = array();
		$dom = new DOMDocument();
		$dom->load($this->_mudFile);
		$mud = $dom->getElementsByTagName('mud')->item(0);
		$rooms = $mud->getElementsByTagName('rooms')->item(0);
		$rooms = $rooms->getElementsByTagName('room');
		if($rooms->length)
		{
			for($i=0;$i < $rooms->length;$i++)
			{
				$roomNode = $rooms->item($i);
				$roomID = $roomNode->getAttribute('id');
				
				if($roomNode->hasAttribute('start'))
					$this->_startRoom = $roomID;
				
				$this->_mudRooms[$roomID] = array('description' => '', 'doors' => array(), 'objects' => array(), 'events' => array());
				
				if((!$roomNode->childNodes->length) OR ($roomNode->childNodes->length == 1 && $roomNode->childNodes->item(0) == 'DOMText'))
					die('Parse error : A room cannot be totally empty.'."\n");
				else
				{
					$roomChilds = $roomNode->childNodes;
					foreach($roomChilds as $child)
					{
						if(get_class($child) == 'DOMText')
						{
							if(trim($child->wholeText))
								$this->_mudRooms[$roomID]['description'] .= trim($child->wholeText).' ';
						}
						elseif(get_class($child) == 'DOMElement')
						{
							switch($child->nodeName)
							{
								case 'door':
									$or = $child->getAttribute('orientation');
									$go = $child->getAttribute('goto');
									$lock = $this->_parseBool($child->getAttribute('locked'));
									$key = $child->getAttribute('key');
									if(empty($or) or empty($go))
										die('Parse error : Missing parameter for door '.$go.'.'."\n");
									if(!in_array($or, array('north', 'south', 'east', 'west')))
										die('Parse error : This is not a direction : '.$or.'.'."\n");
									$this->_mudRooms[$roomID]['doors'][$or] = array('goto' => $go, 'lock' => $lock, 'key' => $key);
									break;
								case 'object':
									$name = $child->getAttribute('name');
									if(empty($name))
										die('Parse error : Object unnamed.'."\n");
									else
									{
										$take = $child->getAttribute('take');
										if($take == '1' or $take == 'true' or $take == 'take')
											$take = TRUE;
										else
											$take = FALSE;
										
										if($child->hasAttribute('count'))
											$count = intval($child->getAttribute('count'));
										else
											$count = 1;
										
										$this->_mudRooms[$roomID]['objects'][$name] = array('name' => $name, 'description' => $child->getAttribute('description'), 'event' => $child->getAttribute('event'), 'take' => $take, 'id' => $child->getAttribute('id'), 'count' => $count, 'taken' => 0);
									}
									break;
								case 'event':
									$name = $child->getAttribute('name');
									if(empty($name))
										die('Parse error : event unnamed.'."\n");
									
									$priority = $child->getAttribute('priority');
									if(empty($priority))
										$priority = 0;
									
									if(isset($this->_mudRooms[$roomID]['events'][$priority]))
									{
										for($i = 0; isset($this->_mudRooms[$roomID]['events'][$priority.'-'.$i]); $i++);
										$priority = $priority.'-'.$i;
									}
									
									$this->_mudRooms[$roomID]['events'][$priority] = $name;
									break;
							}
						}
					}
					ksort($this->_mudRooms[$roomID]['events']);
				}
			}
			echo 'Found '.count($this->_mudRooms).' room(s).'."\n";
		}
		else
			die('Parse error : There is no room.'."\n");
			
		//Parsing des events
		$events = $mud->getElementsByTagName('events')->item(0);
		$events = $events->getElementsByTagName('event');
		$calledFiles = array();
		if($events->length)
		{
			for($i = 0;$i < $events->length;$i++)
			{
				
				$eventNode = $events->item($i);
				$eventID = $eventNode->getAttribute('id');
				$this->_mudEvents[$eventID] = array();
				$childNodes = $eventNode->childNodes;
				$this->_mudEvents[$eventID]['calls'] = array();
				for($j = 0; $j < $childNodes->length; $j++)
				{
					$node = $childNodes->item($j);
					
					switch($node->nodeName)
					{
						case 'call':
							$call = $node->nodeValue;
							$this->_mudEvents[$eventID]['calls'][] = $call;
							break;
					}
				}
			}
			echo 'Found '.count($this->_mudEvents).' event(s).'."\n";
		}
		
		$dir = scandir('data/mud/events');
		foreach($dir as $el)
		{
			if(!in_array($el, array('.','..')))
			{
				$ext = explode('.',$el);
				$ext = strtolower(array_pop($ext));
				if(is_file('data/mud/events/'.$el) AND $ext == 'lua')
					$this->_lua->evaluatefile('data/mud/events/'.$el);
			}
			
		}
		
		//Parsing des alias
		$aliases = $mud->getElementsByTagName('aliases')->item(0);
		$aliases = $aliases->getElementsByTagName('alias');
		$this->_aliases = array('from' => array(), 'to' => array());
		if($aliases->length)
		{
			for($i = 0; $i < $aliases->length;$i++)
			{
				$node = $aliases->item($i);
				if(!$from = $node->getAttribute('from'))
					die('Parse error : no mask for alias.'."\n");
				if(!$to = $node->getAttribute('to'))
					die('Parse error : no replacement for alias'."\n");
				
				$this->_aliases['from'][] = '#'.$from.'#isU';
				$this->_aliases['to'][] = $to;
			}
		}
		
		//Init des events Lua
		$this->_lua->call_function('__init', array());
	}
	
	//Entre dans une salle
	protected function _enterRoom($id, $room)
	{
		$this->_states[$id]['room'] = $room;
		$room = $this->getRoom($id, $room);
		
		if($room['description'])
			API::privMsg($this->_botID, $id, $room['description']);
		
		foreach($room['events'] as $event)
			$this->_execEvent($id, $event);
	}
	
	//Exécution d'un event
	protected function _execEvent($id, $event)
	{
		foreach($this->_mudEvents[$event]['calls'] as $call)
			$this->callLuaFunc($call, array('player' => $this->_states[$id], 'ircdata' => $this->_mainClass->clients[$id]));
	}
	
	//Fonction d'appel d'une fonction Lua
	public function callLuaFunc($call, $state)
	{
		@$this->_lua->call_function($call, $state);
	}
	
	//Exécution des custom commandes Lua
	public function execLuaCommands($id, $cmd)
	{
		if(!isset($this->_states[$id]))
		{
			$this->eventStart($id, $cmd);
			return TRUE;
		}
		
		if(isset($this->_luaCommands[$cmd[0]]))
		{
			foreach($this->_luaCommands[$cmd[0]] as $call)
				$this->callLuaFunc($call, array('player' => $this->_states[$id], 'ircdata' => $this->_mainClass->clients[$id], 'cmd' => $cmd));
		}
	}
	
	protected function _loadGame($name)
	{
		
	}
	
	protected function _saveGame($id)
	{
		$state = $this->_states[$id];
		$player = $this->_mainClass->clients[$id];
		
		$dom = new DOMDocument();
		$root = $dom->createElement('save');
		$nick = $dom->createElement('nick');
		$room = $dom->createElement('room');
		$money = $dom->createElement('money');
		$objects = $dom->createElement('objects');
		$level = $dom->createElement('level');
		$hp = $dom->createElement('hp');
		$mp = $dom->createElement('mp');
		
		foreach($state['objects'] as $name => $count)
		{
			$node = $dom->createElement('object');
			$node->setAttribute('id', $name);
			$node->setAttribute('count', $count);
			$objects->appendChild($node);
		}
		
		$nick->nodeValue = $player['nick'];
		$room->nodeValue = $state['room'];
		$money->nodeValue = $state['money'];
		$level->nodeValue = $state['level'];
		$hp->nodeValue = $state['hp'];
		$mp->nodeValue = $state['mp'];
		
		$root->appendChild($nick);
		$root->appendChild($room);
		$root->appendChild($money);
		$root->appendChild($objects);
		$root->appendChild($level);
		$root->appendChild($hp);
		$root->appendChild($mp);
		$dom->appendChild($root);
		
		$dom->save('data/mud/states/'.$player['nick'].'.xml');
	}
}

$this->plugins[$pluginName] = new MUD($this->_mainClass);
