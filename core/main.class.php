<?php

class Main
{
	//Variables privées
	private $_configMain = array();
	private $_configDB = array();
	private $_socket;
	private $_queryID = array();
	
	//Variables publiques
	public $logClass;
	public $DBClass;
	public $pluginsClass;
	public $clients;
	public $channels = array();
	public $nicks = array();
	public $ignoreList = array(); //Liste des users ignorés
	public $idbase = array(); //Base de données de journalisation (pour les ID clients)
	public $stats = array('uldata' => '0', 'dldata' => '0');
	public $serverID = 0;
	public $serverIP;
	public $serverHost;
	
	const VERSION = '0.0.1';
	const NETWORK = 'LinkIRC';
	const SERVERNAME = 'LinkIRC Main Server';
	
	//Fonction coupant le serveur
	public function close()
	{
		$this->logClass->writeLog('fermeture du serveur...','close');
		foreach($this->clients as $id => $client) //On envoie le signal SQUIT à tous les clients et on ferme la boutique
		{
			if(!in_array($id,$this->ignoreList))
			{
				$this->sendData($id,'SQUIT Closing server...');
				socket_close($this->clients[$id]['socket']);
			}
		}
		socket_close($this->_socket);
		$this->logClass->writeLog('Serveur fermé.','close');
	}
	
	//Fonction d'initialisation du serveur (lancée à l'instanciation)
	public function __construct($maincfg = 'conf/main.ini')
	{
		echo 'Initializing...'."\n";
		
		//On initialise le serveur
		$this->_initLog(); //On initialise le log
		$this->_loadConfigFiles($maincfg); //On charge les fichiers de configuration du serveur
		
		//Initialisation des plugins
		$this->_PluginsInit();
	}
	
	//Fonction déconnectant un client
	public function clientDisconnect($id,$info = 'Aucune info')
	{
		$this->logClass->writeLog('Deconnexion : '.$this->clients[$id]['addr'].' (ID : '.$id.') : '.$info,'info');
		foreach($this->clients[$id]['channels'] as $channel => $chanInfo)
			$this->broadcastCommand($channel,"QUIT :$info.",array($id),'client',$id);
		unset($this->clients[$id]);
		unset($this->nicks[$id]);
		$this->idbase[] = $id;
		sort($this->idbase);
	}
	
	//Permet l'envoi de données via la socket
	public function sendData($c,$d,$t = 'server',$s = 0)
	{
		if($this->clients[$c]['socket'])
		{
			
			//On sélectionne le type d'en-tête (client/serveur)
			$this->stats['uldata'] += strlen($d);
			
			if($t == 'server')
				$str = ':'.$this->serverHost.' '.$d."\r\n";
			elseif($t == 'client')
				$str = ':'.$this->clients[$s]['nick'].'!'.$this->clients[$s]['user'].'@'.$this->clients[$s]['host'].' '.$d."\r\n";
			else
				$str = $d."\r\n";
			
			$ret = @socket_write($this->clients[$c]['socket'],$str);
			
			if($ret === FALSE)
			{
				if(socket_last_error($this->clients[$c]['socket']) == 32)
				{
					$this->clientDisconnect($c,'EOF From Client');
				}
			}
			else
				echo 'O['.$this->clients[$c]['nick'].'@'.$this->clients[$c]['host'].'] '.$str;
		}
	}
	
	//Permet la réception de données
	public function readData($id)
	{
		$cmd = socket_read($this->clients[$id]['socket'],1024);
		if($cmd === FALSE)
		{
			echo socket_last_error($this->clients[$id]['socket']);
			if(socket_last_error($this->clients[$id]['socket']) == SOCKET_EPIPE)
				$this->clientDisconnect($id,'EOF From Client');
		}
		$this->stats['dldata'] += strlen($cmd);
		$show = explode("\r\n",$cmd);
		unset($show[count($show)-1]);
		foreach($show as $str)
			echo 'I['.$this->clients[$id]['host'].'] '.$str."\n";
		return rtrim($cmd);
	}
	
	//Envoi d'une commande à tous les clients
	public function broadcastCommand($channel,$cmd,$ignore = array(),$t = 'client',$s = 0)
	{
		if(!is_array($ignore))
			return FALSE;
		
		foreach($this->channels[$channel]['users'] as $id => $content)
		{
			if(!in_array($id,$ignore))
				$this->sendData($id,$cmd,$t,$s);
		}
		
		return TRUE;
	}
	
	//Fonction permettant d'ajouter un faux utilisateur
	public function addBot($nick, $user, $host)
	{
		//Journalisation de l'ID : voir main.class.php à la ligne 161
		if(isset($this->idbase[0]))
		{
			$id = $this->idbase[0];// On attribue le premier ID sauvegardé (en partant de 0)
			unset($this->idbase[0]);
			sort($this->idbase);
		}
		else
			$id = count($this->clients); //On attribue l'ID correspondant à la clé suivante
		
		//Connexion du faux utilisateur correspondant au bot
		$this->clients[$id] = array();
		$this->clients[$id]['socket'] = NULL;
		$this->clients[$id]['nick'] = $nick;
		$this->clients[$id]['user'] = $user;
		$this->clients[$id]['host'] = $host;
		$this->clients[$id]['modes'] = '';
		$this->clients[$id]['auth'] = NULL;
		$this->clients[$id]['login'] = NULL;
		$this->clients[$id]['channels'] = array();
		$this->clients[$id]['lastcmd'] = time();
		$this->clients[$id]['pinged'] = 0;
		$this->clients[$id]['firstping'] = FALSE;
		//Mise du bot en liste d'ignorés (pour ne pas interférer avec les autres plugins)
		$this->ignoreList[] = $id;
		
		return $id;
	}
	
	//Fonction de lancement du server (initialisation, bindage... de la socket) puis boucle principale
	public function run()
	{
		echo 'Launching...',"\n";
		
		$this->logClass->writeLog('Création du serveur...','init');
		
		//Ouverture de la socket et écriture des erreurs dans le log
		if(($this->_socket = socket_create(AF_INET, SOCK_STREAM, 0)) === false)
    	{
    		$this->logClass->writeLog('La création de la socket a échoué : '.socket_strerror($socket),'error');
			exit("Erreur ! Voir le log.\n");
		}
	
		//Assignation de la socket et toujours écriture des erreurs dans le log
		if(($assignation = socket_bind($this->_socket, $this->_configMain['bind_addr'], $this->_configMain['port'])) == FALSE)
    	{
    		$this->logClass->writeLog('Le bind de la socket à l\'adresse "'.$this->_configMain['bind_addr'].'" a échoué : '.socket_strerror($assignation),'error');
			exit("Erreur ! Voir le log.\n");
		}
		
		//Réglage de la socket en mode non-bloquant (le script ne s'arrête pas pour écouter la socket)
		socket_set_nonblock($this->_socket);
		
		//Réglage de l'IP du bot et du serveur
		socket_getsockname($this->_socket,$this->serverIP);
		$this->serverHost = php_uname('n');
		
		$this->logClass->writeLog('Serveur cree. Pret a recevoir.','init');
		
		echo 'Ready.'."\n";
		//Boucle principale du programme (casée dans une méthode, eh oui c'est bizarre)
		while(1)
		{
			//Etape 1 : on vérifie si il n'y a pas de connexion entrante ($listen contiendra l'erreur éventuelle)
			if(($listen = socket_listen($this->_socket)) === false)
			{
				$this->logClass->writeLog('L\'écoute de la socket a échoué : '.socket_strerror($ecoute),'error');
				exit();
			}
			//Etape 2 : on accepte une connexion éventuelle
			if(($socketTemp = @socket_accept($this->_socket)) !== FALSE)
			{
				//Journalisation de l'ID : si il y a un ID libre (dans les ID déjà utilisés), on le prend, sinon on attribue un nouvel ID
				if(isset($this->idbase[0]))
				{
					$id = $this->idbase[0];// On attribue le premier ID sauvegardé (en partant de 0)
					unset($this->idbase[0]);
					sort($this->idbase);
				}
				else
					$id = count($this->clients); //On attribue l'ID correspondant à la clé suivante
				$this->clients[$id]['socket'] = $socketTemp;
				$this->clients[$id]['addr'] = NULL;
				$this->clients[$id]['nick'] = '';
				$this->clients[$id]['user'] = '';
				$this->clients[$id]['host'] = '';
				$this->clients[$id]['modes'] = '';
				$this->clients[$id]['auth'] = NULL;
				$this->clients[$id]['login'] = NULL;
				$this->clients[$id]['channels'] = array();
				$this->clients[$id]['lastcmd'] = time();
				$this->clients[$id]['pinged'] = 0;
				$this->clients[$id]['firstping'] = FALSE;
				$this->clients[$id]['channels'] = array();
				
				$this->sendData($id,"NOTICE AUTH :***Looking up your hostname",'direct');
				$this->sendData($id,"NOTICE AUTH :***Checking ident",'direct');
				$this->sendData($id,"NOTICE AUTH :***No ident found",'direct');
				socket_getpeername($this->clients[$id]['socket'],$this->clients[$id]['addr']);
				$this->clients[$id]['host'] = gethostbyaddr($this->clients[$id]['addr']);
				if($this->clients[$id]['host'])
					$this->sendData($id,"NOTICE AUTH :***Found your hostname",'direct');
				else
				{
					$this->clients[$id]['host'] = $this->clients[$id]['addr'];
					$this->sendData($id,"NOTICE AUTH :***Hostname not found, using your IP instead",'server');
				}
					
				
				$this->logClass->writeLog('Connexion : '.$this->clients[$id]['addr'].' (ID : '.$id.')','info');
				
			}
			
			//Etape 3 : On sélectionne les sockets qui veulent envoyer des données
			//On initialise les variables pour le socket_select
			$socketsList = array();
			$socketOut = array();
			$ignore = array();
			$initialSockets = array();
			$socketNb = 0;
			//On sélectionne si au moins 1 client est connecté
			if(count($this->clients) > count($this->ignoreList))
			{
				foreach($this->clients as $currentId => &$client)
				{
					if(!in_array($currentId,$this->ignoreList))
						$socketList[$currentId] = $client['socket'];
				}
				$initialSockets = $socketList;
				$socketNb = socket_select($socketList,$socketOut,$ignore,0); //On regarde si au moins 1 client veut envoyer des données
			}
			//Etape 4 : On lit et on traite les données pour chaque socket
			//Si il y a au moins 1 socket ayant envoyé des données
			if($socketNb > 0)
			{
				foreach($socketList as &$socket)
				{
					$id = array_search($socket,$initialSockets);
					if(!in_array($id,$this->ignoreList))
					{
						$return = $this->readData($id);
						$this->clients[$id]['lastcmd'] = time();
						$this->clients[$id]['pinged'] = 0;
						$cmdlist = explode("\r\n",$return);
						$this->cmdSended = 1;
						foreach($cmdlist as $cmd)
							$this->pluginsClass->execEvents($id,trim($cmd)); //On exécute les différents évents
						usleep(5000); //On économise le CPU
					}
				}
			}
			$this->pluginsClass->execRoutines();
			unset($socketList);
			usleep(1000); //On économise le CPU
		}
	}
	
	private function _PluginsInit()
	{
		$this->pluginsClass = new Plugins();
		$this->pluginsClass->setMainClass($this);
		$this->pluginsClass->loadPluginFile($this->_configMain['pluginsfile']);
	}
	
	//Fonction d'initialisation du log
	private function _initLog()
	{
		$this->logClass = new Logs();
	}
	
	//Fonction chargeant les configurations du serveur
	private function _loadConfigFiles($maincfgfile)
	{
		$this->logClass->writeLog('Chargement des fichiers de configuration...','init');
		if(is_file($maincfgfile))
			$this->_configMain = parse_ini_file($maincfgfile); //On charge la configuration principale (selon le paramètre renseigné)
		else
			$this->logClass->writeLog('Fichier de configuration inexistant.','error');
	}
	
	//Fonction basique de parsing de template, juste remplaçant les variables entre crochet par la valeur de celle contenu dans $args (dont la clé correspond au nom)
	//Note : peut être accédée statiquement
	public function parseTplFile($file,$args)
	{
		$text = file_get_contents($file);
		foreach($args as $str => $el)
		{
			if(strpos($text,'{'.$str.'}') !== FALSE)
				$text = str_replace('{'.$str.'}',$el,$text);
		}
		return $text;
	}
	
	public function parseTplStr($text,$args)
	{
		foreach($args as $str => $el)
		{
			if(strpos($text,'{'.$str.'}') !== FALSE)
				$text = str_replace('{'.$str.'}',$el,$text);
		}
		return $text;
	}
}
