<?php

class Logs
{
	//Variables privées
	private $_logFile;
	
	//Variables publiques
	public $logName;
	
	//Fonction d'initialisation des logs (et notamment d'ouverture des logs)
	public function __construct()
	{
		//On ouvre le fichier de logs puis on écrit le message d'ouverture
		$this->logName = 'logs/main.log';
		$this->_logFile = fopen($this->logName,'a+');
		$this->writeLog('Log started on '.date('r'),'log');
	}
	
	//Fonction publique d'écriture du log
	public function writeLog($str,$type = 'misc')
	{
		$this->_putToLog(' ['.strtoupper($type).'] '.$str);
		
	}
	
	//Fonction privée d'écriture directe dans le fichier
	private function _putToLog($str)
	{
		fputs($this->_logFile,'--'.date('d/m/Y h:i').'-- '.$str."\n"); //On écrit dans le log
	}
}
