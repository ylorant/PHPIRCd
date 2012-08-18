<?php

/* plugins.class.php : classe de gestion des commandes et des plugins du serveur central
 * 
 * Mémo :
 * - Routines : fonctions exécutées à chaque tour de boucle, tout le temps.
 * - Évènements : actions effectuées uniquement lors de la réception de la commande correspondante (appelée "signal") renseignée lors de la déclaration
 */

class Plugins
{
	//Variables privées
	private $_mainClass;
	private $_commands = array(); //Liste des commandes
	private $_events = array(); //Conteneur des events
	private $_routines = array(); //Conteneurs des routines
	private $_botEvents = array(); //Conteneurs des events de bots
	private $_botStaticEvents = array();
	private $_currentPlugin;
	
	//Variables publiques
	public $pluginNames;
	public $plugins;
	public $commandNames = array();
	
	//Fonction permettant de définir la classe Main dans celle-ci
	public function setMainClass(&$main)
	{
		$this->_mainClass = $main;
	}
	
	//Fonction chargeant un plugin
	public function loadPlugin($pluginName)
	{
		$this->_mainClass->logClass->writeLog('Chargement du plugin "'.$pluginName.'"...','plugin');
		//On vérifie si le fichier du plugin existe (sinon ça sert à rien de le charger) et sinon on écrit dans le log un message d'erreur
		if(!is_file('plugins/'.$pluginName.'.php'))
			$this->_mainClass->logClass->writeLog('Plugin "'.$pluginName.'" inexistant.','error');
		else
			include('plugins/'.$pluginName.'.php'); //On inclus le fichier de plugin
	}
	
	//Charge un fichier contenant une liste de plugins
	public function loadPluginFile($file)
	{
		$this->_mainClass->logClass->writeLog('Chargement du fichier de plugin "'.$file.'"...','plugin');
		//On lit et on splitte le fichier
		$load = file_get_contents($file);
		$load = explode("\n",$load);
		//On pense à retirer le dernier élément si celui-ci est vide (Geany me rajoute une ligne vide à la fin de chaque fichier)
		if(empty($load[count($load)-1]))
			unset($load[count($load)-1]);
		//On parcourt l'array en chargeant chaque élément
		foreach($load as $current)
			$this->loadPlugin($current);
	}
	
	//Supprime un plugin
	public function removePlugin($pluginName)
	{
		unset($this->plugins[$pluginName]);
	}
	
	//Ajoute un évènement et sa fonction associée
	public function addEvent($signal,$plugin,$fct,$authed = FALSE)
	{
		//On initialise l'array des events si il n'y en a pas
		if(!isset($this->_events[$signal]))
			$this->_events[$signal] = array();
		$this->_events[$signal][$fct] = array(&$this->plugins[$plugin],$fct,$authed); //On ajoute notre event sous forme d'array
	}
	
	public function addBotEvent($id,$signal,$plugin,$callback,$authed = FALSE)
	{
		if($signal !== NULL)
		{
			if(!isset($this->_botEvents[$signal]))
				$this->_botEvents[$signal] = array();
			$this->_botEvents[$signal][$callback] = array($plugin,$callback,$id,$authed);
		}
		else
			$this->_botStaticEvents[$callback] = array($plugin,$callback,$id,$authed);
	}
	
	//Ajoute une routine et sa fonction associée
	public function addRoutine($plugin,$fct)
	{
		$this->_routines[$fct] = array(&$this->plugins[$plugin],$fct);
	}
	
	//Supprime une routine et sa fonction associée
	public function removeRoutine($fct)
	{
		unset($this->_routines[$fct]);
	}
	
	//Supprime un évènement et sa fonction associée
	public function removeEvent($signal,$fct)
	{
		unset($this->_events[$signal][$fct]);
		if(count($this->_events[$signal]))
			unset($this->_events[$signal]);
	}
	
	//Exécute les évènements au niveau du programme
	public function execEvents($id,$cmd)
	{
		$eventFound = FALSE;
		$event = explode(' ',$cmd);
		$data = explode(':',$cmd);
		if(isset($data[1]))
			$data = explode(' ',$data[1]);
		$eventfound = 0;
		foreach($this->_events as $signal => $exec)
		{
			if($event[0] == $signal)
			{
				$eventFound = 1;
				foreach($exec as $name => $fct)
				{
					if($this->_mainClass->clients[$id]['auth'] || !$fct[2])
						$fct[0]->$fct[1]($id,$cmd);
					else
						$this->_mainClass->sendData($id,Codes::ERR_NOTREGISTERED.' '.$this->_mainClass->clients[$id]['nick'].' :You have not registered.','client',0);
				}
			}
		}
		
		if($event[0] == 'PRIVMSG' OR $event[0] == 'NOTICE')
		{
			$eventFound = TRUE;
			$data = explode(':',$cmd);
			$cmd = explode(' ',$data[1]);
			foreach($this->_botEvents as $signal => $exec)
			{
				if($cmd[0] == $signal)
				{
					foreach($this->_botEvents[$signal] as $name => $fct)
					{
						if($event[1] == $this->_mainClass->clients[$fct[2]]['nick'])
						{
							
							
							if($this->_mainClass->clients[$id]['auth'] || !$fct[3])
								$this->plugins[$fct[0]]->$fct[1]($id,$cmd);
							else
								$this->_mainClass->sendData($id,Codes::ERR_NOTREGISTERED.' '.$this->_mainClass->clients[$id]['nick'].' :You have not registered.','client',0);
						}
					}
				}
			}
			
			foreach($this->_botStaticEvents as $fct)
			{
				if($event[1] == $this->_mainClass->clients[$fct[2]]['nick'])
				{
					if($this->_mainClass->clients[$id]['auth'] || !$fct[3])
						$this->plugins[$fct[0]]->$fct[1]($id,$cmd);
					else
						$this->_mainClass->sendData($id,Codes::ERR_NOTREGISTERED.' '.$this->_mainClass->clients[$id]['nick'].' :You have not registered.','client',0);
				}
			}
		}
		
		if(!$eventFound)
			$this->_mainClass->sendData($id,Codes::ERR_UNKNOWNCOMMAND.' '.$this->_mainClass->clients[$id]['nick'].' :Unknown command');
	}
	
	//Exécute les routines au niveau du programme
	public function execRoutines()
	{
		foreach($this->_routines as $routine)
			$routine[0]->$routine[1]();
	}
}
