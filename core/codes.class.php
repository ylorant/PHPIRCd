<?php

class Codes
{
	//Constantes de retour informatif
	const RPL_WELCOME = '001';
	const RPL_YOURHOST = '002';
	const RPL_CREATED = '003';
	const RPL_MYINFO = '004';
	const RPL_BOUNCE = '005';
	const RPL_AWAY = '301';
	const RPL_UNAWAY = '305';
	const RPL_NOWAWAY = '306';
	const RPL_WHOISUSER = '311';
	const RPL_WHOISSERVER = '312';
	const RPL_WHOISOPERATOR = '313';
	const RPL_WHOISCHANNELS = '319';
	const RPL_WHOISIDLE = '317';
	const RPL_ENDOFWHOIS = '318';
	const RPL_LIST = '322';
	const RPL_LISTEND = '323';
	const RPL_CHANNELMODEIS = '324';
	const RPL_AUTHEDAS = '330';
	const RPL_NOTOPIC = '331';
	const RPL_TOPIC = '332';
	const RPL_VERSION = '351';
	const RPL_NAMREPLY = '353';
	const RPL_ENDOFNAMES = '366';
	const RPL_MOTD = '372';
	const RPL_MOTDSTART = '375';
	const RPL_ENDOFMOTD = '376';
	
	//Constantes de retour d'erreur
	const ERR_NOSUCHNICK = '401';
	const ERR_NOSUCHCHANNEL = '403';
	const ERR_WASNOSUCHNICK = '406';
	const ERR_UNKNOWNCOMMAND = '421';
	const ERR_NOMOTD = '422';
	const ERR_NONICKNAMEGIVEN = '431';
	const ERR_NICKNAMEINUSE = '433';
	const ERR_USERNOTINCHANNEL = '441';
	const ERR_NOTONCHANNEL = '442';
	const ERR_NOTREGISTERED = '451';
	const ERR_NEEDMOREPARAMS = '461';
	const ERR_UNKNOWNMODE = '472';
	const ERR_ALREADYONCHANNEL = '479';
	const ERR_CHANOPRIVSNEEDED = '482';
}
