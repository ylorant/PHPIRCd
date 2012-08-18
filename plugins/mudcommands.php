<?php

/////// Fonctions accessibles depuis Lua ///////

class MUDCommands
{
	//Déclare les fonctions Lua
	protected function _loadLuaEnv()
	{
		if($this->_lua)
			unset($this->_lua);
		
		$this->_luaCommands = array();
		$this->_lua = new lua();
		
		$this->_lua->expose_function("addMoney",array($this,"addMoney"));
		$this->_lua->expose_function("removeMoney",array($this,"removeMoney"));
		$this->_lua->expose_function("setMoney",array($this,"setMoney"));
		$this->_lua->expose_function("message",array($this, "message"));
		$this->_lua->expose_function("addCommand",array($this, "addCommand"));
		$this->_lua->expose_function("joinChannel", array($this, "join"));
		$this->_lua->expose_function("partChannel", array($this, "part"));
		$this->_lua->expose_function("rawCommand", array($this, "rawCommand"));
		$this->_lua->expose_function('saveGame', array($this, 'save'));
		$this->_lua->expose_function('loadGame', array($this, 'load'));
		$this->_lua->expose_function('itemInRoom', array($this, 'itemInRoom'));
		
		$this->_lua->botID = $this->_botID;
	}
	
	//Vérifie si un objet se trouve dans une pièce
	public function itemInRoom($object, $room, $take = FALSE)
	{
		$search = array();
		foreach($room['objects'] as $item)
		{
			if(!$take || $item['take'] == TRUE)
			{
				if(strtolower($item['name']) == $object)
					return TRUE;
				else
					$search[] = $item['name'];
			}
		}
		
		$suggestion = $this->getMostSimilarString($object, $search);
		
		return $suggestion;
	}
	
	public function getMostSimilarString($needle, $haystack)
	{
		$needleMetaphone = metaphone($needle);
		$metaphoneKeys = array();
		//Analyse phonétique Metaphone
		foreach($haystack as $item)
		{
			$metaphoneKeys[$item] = metaphone($item);
			if($metaphoneKeys[$item] == $needleMetaphone)
				return $item;
		}
		
		$levenshtein = 0;
		$ret = '';
		//Analyse de proximité metaphone
		foreach($haystack as $item)
		{
			if(!$levenshtein || $levenshtein > levenshtein($metaphoneKeys[$item], $needleMetaphone))
			{
				$ret = $item;
				$levenshtein = levenshtein($metaphoneKeys[$item], $needleMetaphone);
			}
		}
		
		//Analyse de la distance de Levenshtein, si l'analyse de la clé Metaphone n'a rien donné.
		//L'analyse de proximité de levenshtein prendra le pas si elle est plus petite que la proximité metaphone.
		foreach($haystack as $item)
		{
			if(!$levenshtein || $levenshtein > levenshtein(strtolower($item), $needle))
			{
				$ret = $item;
				$levenshtein = levenshtein(strtolower($item), $needle);
			}
		}
		
		return $ret;
	}
	
	//Suppression d'un custom event
	public function removeCommand($cmd, $callback)
	{
		unset($this->_luaCommands[$cmd][array_search($callback, $this->_luaCommands[$cmd])]);
		if(empty($this->_luaCommands[$cmd]))
			unset($this->_luaCommands[$cmd]);
	}
	
	//Ajoute un custom event
	public function addCommand($cmd, $callback)
	{
		if(!isset($this->_luaCommands[$cmd]))
			$this->_luaCommands[$cmd] = array();
		
		$this->_luaCommands[$cmd][] = $callback;
	}
	
	//Ajoute du pognon
	public function addMoney($id, $money)
	{
		$this->_states[$id]['money'] += $money;
	}
	
	//Enlève du pognon
	public function removeMoney($id, $money)
	{
		if($this->_states[$id]['money'] - $money > 0)
			$this->_states[$id]['money'] -= $money;
		else
			$this->_states[$id]['money'] = 0;
	}
	
	//Règle le pognon à une valeur spécifique
	public function setMoney($id, $money)
	{
		if($money > 0)
			$this->_states[$id]['money'] = $money;
		else
			$this->_states[$id]['money'] = 0;
	}
	
	//Récupère la salle courante selon l'user, avec gestion du savestate
	/*public function getRoom($id, $roomID)
	{
		return isset($this->_states[$id]['mudRooms'][$roomID]) ? (&$this->_states[$id]['mudRooms'][$roomID]) : (&$this->_mudRooms[$roomID]);
	}
	*/
	//Ecrit un message
	public function message($id, $content)
	{
		foreach($this->_aliases['from'] as $aliasId => $alias)
		{
			if(preg_match($alias,$content))
			{
				API::privMsg($this->_botID, $id, preg_replace($alias, $this->_aliases['to'][$aliasId], $content));
				return 0;
			}
		}

		API::privMsg($this->_botID, $id, $content);
	}
	
	//Fait joindre un channel à un client
	public function join($id, $channel)
	{
		API::join($id, $channel);
	}
	
	//Fait quitter un channel à un client
	public function part($id, $channel)
	{
		API::part($id, $channel);
	}
	
	//Envoie une commande IRC telle quelle
	public function rawCommand($id, $cmd)
	{
		$this->_mainClass->sendData($id, $cmd, 'client', $this->_botID);
	}
	
	//Permet de régler directement une propriété
	public function setProperty($id, $property, $value)
	{
		$this->_states[$id][$property] = $value;
	}
	
	public function searchObject($id, $name)
	{
		$room = $this->_states[$id]['room'];
		if(array_search($name, $this->_mudRooms[$room]['objects']))
			return $this->_mudRooms[$room]['objects'][array_search($name, $this->_mudRooms[$room]['objects'])];
		else
			return FALSE;
	}
	
	public function giveObject($id, $name, $count = 1)
	{
		if(!isset($this->_states[$id]['objects'][$name]))
			$this->_states[$id]['objects'][$name] = $count;
		else
			$this->_states[$id]['objects'][$name] += $count;
	}
	
	public function takeObject($id, $name, $count = 1)
	{
		if(!isset($this->_states[$id]['objects'][$name]))
			return FALSE;
		else
		{
			if($this->_states[$id]['objects'][$name] > $count)
				$this->_states[$id]['objects'][$name] -= $count;
			else
				unset($this->_states[$id]['objects'][$name]);
		}
	}
	
	public function hasObject($id, $name)
	{
		if(isset($this->_states[$id]['objects'][$name]))
			return $this->_states[$id]['objects'][$name];
		else
			return FALSE;
	}
	
	public function initPlayerRoom($id, $room)
	{
		if(!isset($this->_states[$id]['mudRooms'][$room]))
			$this->_states[$id]['mudRooms'][$room] = $this->_mudRooms[$room];
	}
	
	public function updateRoom($id, $room, $modifier)
	{
		$calc = preg_split('#\+|-|\*|/|=#', $modifier);
		$operand = $modifier[strlen($calc[0])];
		$op = explode('.', trim($calc[0]));
		$this->initPlayerRoom($id, $room);
		$obj = $this->getRoom($id, $room);
		foreach($op as $el)
			$obj = &$obj[$el];
		
		$calc[1] = trim($calc[1]);
		
		//Rajouter la modif
		switch($operand)
		{
			case '+':
				$obj += $calc[1];
				break;
			case '-':
				$obj -= $calc[1];
				break;
			case '*':
				$obj *= $calc[1];
				break;
			case '/':
				$obj /= $calc[1];
				break;
			case '=':
				$obj = $calc[1];
		}
	}
	
	public function getRoom($id, $room)
	{
		return isset($this->_mudRooms[$room]) ? $this->_mudRooms[$room] : NULL;
	}
	
	public function save($id)
	{
		$this->_saveGame($id);
	}
	
	public function load($name)
	{
		$this->_loadGame($name);
	}
}
