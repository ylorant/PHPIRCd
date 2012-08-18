#!/usr/bin/php
<?php
error_reporting(E_ALL);
/*
declare(ticks = 1); //Les ticks sont nécéssaires pour PHP >= 4.3.0

$pid = pcntl_fork();
if ($pid == -1) //Si l'on a pas réussi à forker
{
	die("impossible de forker");
}
else if ($pid)
{
	file_put_contents('pid.txt',$pid);
	exit(); //Si on est le processus père, on arrête
}

// détachons le processus du terminal
if (posix_setsid() == -1)
{
	die("impossible de se détacher du terminal");
}

// configuration des gestionnaires de signaux
pcntl_signal(SIGTERM, "sig_handler");
pcntl_signal(SIGHUP, "sig_handler");
*/
//Fonction simple permettant de gérer les signaux système
function sig_handler($signo) 
{
	global $main;
	static $continue = 0;
	switch ($signo)
	{
		case SIGTERM:
			// gestion des tâches de terminaison
			$main->close();
			exit;
			break;
		case SIGHUP:
			// gestion des tâches de redémarrage
			$main->close();
			shell_exec('./server.php');
			exit();
			break;
	}

}

$load = sys_getloadavg();
echo "Average Load : ",join(', ',$load),"\n";

//Inclusion des classes nécéssaires
include('core/main.class.php');
include('core/logs.class.php');
include('core/plugins.class.php');
include('core/codes.class.php');

//Vérification du paramètre fichier de config
if($argc > 1 && array_search('--config',$argv) !== FALSE)
{
	$cfg = $argv[array_search('--config',$argv)+1];
	$main = new Main($cfg);
}
else
	$main = new Main();

//Chargement de l'API après avoir créé le main
include('core/api.class.php');

$main->run(); //On lance enfin le server
