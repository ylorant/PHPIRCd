<?php

class MUDEvents extends MUDCommands
{
	/////// Commandes du jeu ///////
	
	//Event pour la commande hello : on initialise les variables et tout, et on appelle la première salle
	public function eventStart($id,$cmd)
	{
		if(!$this->_mainClass->clients[$id]['auth'])
		{
			API::privMsg($this->_botID, $id, "You are not authed. You need to be authed to play.");
			return TRUE;
		}
		
		if(!isset($this->_states[$id]))
		{
			$this->_states[$id] = array(
			'id' => $id,
			'room' => NULL,
			'money' => 0,
			'objects' => array(),
			'level' => 1,
			'hp' => 11,
			'mp' => 10,
			'exp' => 0
			);
			
			//How to get HP from level (method 1):
			// r = (maxhp - minhp)/maxlevel
			// s = atan(r);
			// hp = (tan(s)*level)+minhp
			// HP = (50*(x-1))+50
			// MP = (10*(x-1))+10
			/* 	Player-Side Math:
				Level: (All based on level)
				Modifier: Level*50
				XP To Next: (Modifier+((Level+(Level/2))*(Level/2)+(Level/5))*2+3)/10)*10
				HP: (Level/10)+(Level/2)+(Level*10)
				MP: (Modifier/100+(Level/2))*2+2
				ST: (Modifier/20+(MP/2))
				Base Damage: (Modifier/10)+((Level/2)-(Level/5))
				------------------------------------------------------------
				Monster-Side Math:
				Level: (All based on level just like the Player)
				Modifier: Level*0.1+6
				XP Award: Modifier+((Level+(Level/2))*((Level/2)+(Level/5))
				Gold Award: (Level/Modifier)*10+(XP Award/100)
				Attribute Point Award: (Rounded Up) ((Level/10)+(XP Award/100))/Modifier
				HP: (Level+(Level/2)*(Level/10))*10
				MP: (Modifier/2)+(HP/3)
				ST: HP/2+(Modifier-10)+Modifier
				Base Damage: Modifier*(Level/2)-(ST/10)*(Modifier/100)
			*/
			
			$this->_enterRoom($id, $this->_startRoom);
		}
	}
	
	public function eventSave($id, $cmd)
	{
		if(!isset($this->_states[$id]))
		{
			$this->message($id, "You haven't started gaming yet, you can't save now.");
			return TRUE;
		}
		
		$this->save($id);
	}
	
	public function eventLoad($id, $cmd)
	{
		$this->load($id);
	}
	
	public function eventTake($id, $cmd)
	{
		//Démarrage
		if(!isset($this->_states[$id]))
		{
			$this->eventStart($id, $cmd);
			return TRUE;
		}
		
		//Récupération de la map
		$room = $this->getRoom($id, $this->_states[$id]['room']);
		
		//Le mec il a pas sélectionné d'objet
		if(!isset($cmd[1]))
		{
			$this->message($id, 'What item dou you want to pick ?');
			return TRUE;
		}
		
		//On fait un strtolower pour faire une vérification insensible à la casse
		$cmd[1] = strtolower($cmd[1]);
		
		//On regarde si l'item est dans la salle, et si il n'y est pas, on affiche une approximation
		$approx = $this->itemInRoom($cmd[1], $room);
		if($approx !== TRUE)
		{
			$this->message($id, 'This item does not exists.');
			if($approx)
				$this->message($id, 'Did you mean : '.$approx.' ?');
			return TRUE;
		}
		
		//Récupération de l'objet
		$object = $this->searchItem($cmd[1]);
		//~ $object = $room['objects'][$cmd[1]];
		
		//Récupération du nombre d'items
		if(isset($cmd[2]))
			$count = max($cmd[2], $object['count']);
		else
			$count = 1;
		
		//On donne l'objet
		$this->giveObject($id, $object['name'], $count);
		
		//On retire l'objet de la salle
		$this->updateRoom($id, $this->_states[$id]['room'], 'objects/'.$cmd[1].' - '.$count);
	}
	
	//Event pour la commande go : se déplace de salle en salle
	public function eventGo($id, $cmd)
	{
		if(!isset($this->_states[$id]))
		{
			$this->eventStart($id, $cmd);
			return TRUE;
		}
		
		$state = $this->_states[$id];
		$room = $this->getRoom($id, $state['room']);
		
		if(!in_array($cmd[1], array('north', 'south', 'east', 'west')))
		{
			$this->message($id, 'This is not a direction.');
			return TRUE;
		}
		
		if(!isset($room['doors'][$cmd[1]]))
		{
			$this->message($id, 'You can\'t go there.');
			return TRUE;
		}
		
		if($room['doors'][$cmd[1]]['lock'])
		{
			if($room['doors'][$cmd[1]]['key'])
			{
				if(in_array($room['doors'][$cmd[1]]['key'], $state['objects']))
					$this->_enterRoom($id, $room['doors'][$cmd[1]]['goto']);
				else
					$this->message($id, 'You can\'t go there.');
			}
			else
				$this->message($id, 'You can\'t go there.');
		}
		else
			$this->_enterRoom($id, $room['doors'][$cmd[1]]['goto']);
	}
	
	//Affiche la description de la salle dans laquelle on se trouve
	public function eventLook($id, $cmd)
	{
		if(!isset($this->_states[$id]))
		{
			$this->eventStart($id, $cmd);
			return TRUE;
		}
		
		$state = $this->_states[$id];
		$this->message($id, $this->_mudRooms[$state['room']]['description']);
	}
}
