<?php

if(!extension_loaded('php-gtk'))
	dl('php_gtk2.so');

class StatusWindow
{
	private $_mainClass;
	private $_GTKWindow;
	private $_GTKTextBuffer;
	private $_GTKTextView;
	private $_GTKNotebook;
	private $_GTKScrollBox;
	private $_GTKMainBox;
	private $_channels;
	private $_clients;
	private $_GTKChannelsBox;
	private $_GTKChannelsStore;
	private $_GTKChannelsView;
	private $_GTKChannelUsersStore;
	private $_GTKChannelUsersView;
	private $_GTKUsersBox;
	private $_GTKUsersStore;
	private $_GTKUsersView;
	private $_GTKUserChannelsStore;
	private $_GTKUserChannelsView;
	
	public function __construct(&$main)
	{
		ob_start();
		echo 'Started logging : '.date('h:i:s').' the '.date('d M y'),"\n";
		$this->_mainClass = &$main;
		
		//Création de la boîte de contenu (GTKVBox)
		$this->_GTKMainBox = new GtkVBox();
		
		//Ajout d'un texte de base
		$headLabel = new GtkLabel('LinkIRC tiny IRC server.');
		$this->_GTKMainBox->pack_start($headLabel,false,false);
		
		//Création du Notebook
		$this->_GTKNotebook = new GtkNotebook();
		
		//Création de la fenêtre scrollée
		$this->_GTKScrollBox = new GtkScrolledWindow();
        $this->_GTKScrollBox->set_policy(Gtk::POLICY_AUTOMATIC, Gtk::POLICY_AUTOMATIC);
        $this->_GTKScrollBox->set_shadow_type(Gtk::SHADOW_IN);
		
		  ////////////////
		 // Onglet Log //
		////////////////
		
		//Création de la page de log
		$this->_GTKTextBuffer = new GtkTextBuffer();
		$this->_GTKTextView = new GtkTextView();
		$this->_GTKTextView->set_buffer($this->_GTKTextBuffer);
		$this->_GTKTextView->set_editable(false);
		$this->_GTKTextView->set_cursor_visible(false);
		$this->_GTKScrollBox->add($this->_GTKTextView);
		$this->_GTKNotebook->append_page($this->_GTKScrollBox, new GtkLabel('Log'));
		
		  /////////////////////
		 // Onglet Channels //
		/////////////////////
		
		//Création de la liste des channels
		$this->_GTKChannelsBox = new GTKHPaned();
		$this->_GTKChannelsStore = new GtkListStore(GObject::TYPE_STRING,GObject::TYPE_LONG);
		$this->_GTKChannelsView = new GtkTreeView($this->_GTKChannelsStore);
		
		//Création de l'event
		$selection = $this->_GTKChannelsView->get_selection();
		$selection->connect('changed', array($this,'channel_changed'));
		
		//Fenêtre scrollée de la liste des channels
		$chanList = new GtkScrolledWindow();
        $chanList->set_policy(Gtk::POLICY_AUTOMATIC, Gtk::POLICY_AUTOMATIC);
        $chanList->set_shadow_type(Gtk::SHADOW_IN);
		
		//Ajout des colonnes
		$col = new GtkTreeViewColumn('Channel', new GtkCellRendererText(), 'text', 0);
		$this->_GTKChannelsView->append_column($col);
		$col = new GtkTreeViewColumn('Users', new GtkCellRendererText(), 'text', 1);
		$this->_GTKChannelsView->append_column($col);
		
		//Fenêtre scrollée de la liste des users du channel sélectionné
		$usersChanList = new GtkScrolledWindow();
        $usersChanList->set_policy(Gtk::POLICY_AUTOMATIC, Gtk::POLICY_AUTOMATIC);
        $usersChanList->set_shadow_type(Gtk::SHADOW_IN);
		
		//Créaation de la liste des users du channel sélectionné
		$this->_GTKChannelUsersStore = new GtkListStore(GObject::TYPE_STRING,GObject::TYPE_STRING);
		$this->_GTKChannelUsersView = new GtkTreeView($this->_GTKChannelUsersStore);
		
		//Ajout des colonnes
		$col = new GtkTreeViewColumn('Nick', new GtkCellRendererText(), 'text', 0);
		$this->_GTKChannelUsersView->append_column($col);
		$col = new GtkTreeViewColumn('Modes', new GtkCellRendererText(), 'text', 1);
		$this->_GTKChannelUsersView->append_column($col);
		
		//Ajout des TreeViews aux fenêtres scrollées
		$chanList->add($this->_GTKChannelsView);
		$usersChanList->add($this->_GTKChannelUsersView);
		
		//Package des panels
		$this->_GTKChannelsBox->pack1($chanList);
		$this->_GTKChannelsBox->pack2($usersChanList);
		$this->_GTKChannelsBox->set_position(180);
		$this->_GTKNotebook->append_page($this->_GTKChannelsBox, new GtkLabel('Channels'));
		
		  //////////////////
		 // Onglet Users //
		//////////////////
		
		//Création de la liste des channels
		$this->_GTKUsersBox = new GTKHPaned();
		$this->_GTKUsersStore = new GtkListStore(GObject::TYPE_LONG,GObject::TYPE_STRING,GObject::TYPE_LONG);
		$this->_GTKUsersView = new GtkTreeView($this->_GTKUsersStore);
		
		//Création de l'event
		$selection = $this->_GTKUsersView->get_selection();
		$selection->connect('changed', array($this,'user_changed'));
		
		//Fenêtre scrollée de la liste des channels
		$userList = new GtkScrolledWindow();
        $userList->set_policy(Gtk::POLICY_AUTOMATIC, Gtk::POLICY_AUTOMATIC);
        $userList->set_shadow_type(Gtk::SHADOW_IN);
		
		//Ajout des colonnes
		$col = new GtkTreeViewColumn('ID', new GtkCellRendererText(), 'text', 0);
		$this->_GTKUsersView->append_column($col);
		$col = new GtkTreeViewColumn('Nick', new GtkCellRendererText(), 'text', 1);
		$this->_GTKUsersView->append_column($col);
		$col = new GtkTreeViewColumn('Channels', new GtkCellRendererText(), 'text', 2);
		$this->_GTKUsersView->append_column($col);
		
		//Fenêtre scrollée de la liste des users du channel sélectionné
		$usersChanList = new GtkScrolledWindow();
        $usersChanList->set_policy(Gtk::POLICY_AUTOMATIC, Gtk::POLICY_AUTOMATIC);
        $usersChanList->set_shadow_type(Gtk::SHADOW_IN);
		
		//Créaation de la liste des users du channel sélectionné
		$this->_GTKUserChannelsStore = new GtkListStore(GObject::TYPE_STRING,GObject::TYPE_STRING);
		$this->_GTKUserChannelsView = new GtkTreeView($this->_GTKUserChannelsStore);
		
		//Ajout des colonnes
		$col = new GtkTreeViewColumn('Channel', new GtkCellRendererText(), 'text', 0);
		$this->_GTKUserChannelsView->append_column($col);
		$col = new GtkTreeViewColumn('Modes', new GtkCellRendererText(), 'text', 1);
		$this->_GTKUserChannelsView->append_column($col);
		
		//Ajout des TreeViews aux fenêtres scrollées
		$userList->add($this->_GTKUsersView);
		$usersChanList->add($this->_GTKUserChannelsView);
		
		//Package des panels
		$this->_GTKUsersBox->pack1($userList);
		$this->_GTKUsersBox->pack2($usersChanList);
		$this->_GTKUsersBox->set_position(180);
		$this->_GTKNotebook->append_page($this->_GTKUsersBox, new GtkLabel('Users'));
		
		  ////////////
		 // Autres //
		////////////
		
		//Package du Notebook
		$this->_GTKMainBox->pack_start($this->_GTKNotebook);
		
		//Création de la fenêtre
		$this->_GTKWindow = new GtkWindow();
		$this->_GTKWindow->set_title('LinkIRC Server');
		$this->_GTKWindow->set_default_size(400,280);
		$this->_GTKWindow->connect_simple('destroy',array($this,'quit'));
		$this->_GTKWindow->add($this->_GTKMainBox);
		$this->_GTKWindow->show_all();
		
		//Création de la routine
		$this->_mainClass->pluginsClass->addRoutine('gtk','routineGtk');
		
		$this->_mainClass->pluginsClass->addEvent('NICK','gtk','eventAll');
		$this->_mainClass->pluginsClass->addEvent('QUIT','gtk','eventAll');
		$this->_mainClass->pluginsClass->addEvent('JOIN','gtk','eventAll');
		$this->_mainClass->pluginsClass->addEvent('PART','gtk','eventAll');
	}
	
	public function channel_changed($selection)
	{
		//get_selected returns the store and the iterator for that row
		list($foo, $iter) = $selection->get_selected();
		if($iter)
		{
			//get one single value of the model via get_value
			$channel = $this->_GTKChannelsStore->get_value($iter, 0);
			$this->_GTKChannelUsersStore->clear();
			
			foreach($this->_channels[$channel]['users'] as $user)
				$this->_GTKChannelUsersStore->append(array($user['nick'],$user['modes']));
		}
	}
	
	public function user_changed($selection)
	{
		//get_selected returns the store and the iterator for that row
		list($foo, $iter) = $selection->get_selected();
		if($iter)
		{
			//get one single value of the model via get_value
			$user = $this->_GTKUsersStore->get_value($iter, 0);
			$this->_GTKUserChannelsStore->clear();
			
			foreach($this->_clients[$user]['channels'] as $channel)
				$this->_GTKUserChannelsStore->append(array($channel['name'],$channel['modes']));
		}
	}
	
	public function routineGtk()
	{
		while(Gtk::events_pending())
			Gtk::main_iteration();
		$out = ob_get_contents();
		if($out)
		{
			$lastIter = $this->_GTKTextBuffer->get_end_iter();
			$this->_GTKTextBuffer->insert($lastIter,$out);
			ob_clean();
		}
		
		if($this->_channels != $this->_mainClass->channels)
		{
			$this->_channels = $this->_mainClass->channels;
			$selection = $this->_GTKChannelsView->get_selection();
			$this->channel_changed($selection);
			$this->_GTKChannelsStore->clear();
			foreach($this->_channels as $name => $channel)
				$this->_GTKChannelsStore->append(array($name,count($channel['users'])));
		}
	}
	
	public function eventAll()
	{
		$selection = $this->_GTKUsersView->get_selection();
		$this->user_changed($selection);
		$this->_GTKUsersStore->clear();
		foreach($this->_clients as $id => $user)
			$this->_GTKUsersStore->append(array($id,$user['nick'],count($user['channels'])));
	}
	
	public function quit()
	{
		$this->_mainClass->close();
		$this->routineGtk();
		exit();
	}
}

$this->plugins[$pluginName] = new StatusWindow($this->_mainClass);
