<?php

include_once(realpath(dirname(__FILE__)) . "/../Pluginsthext/PluginsThext.class.php");
include_once(realpath(dirname(__FILE__)) . "/chat.php");

	
	class Chat extends PluginsThext{

		const TABLE = 'chat';
		
		public function __construct( $id = 0 ){
			parent::__construct("chat");	

			if($id > 0)
 			  $this->charger_id($id);
				
		}

		public function init(){									
			$query = "
				CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
				  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
				  `from` varchar(255) DEFAULT NULL,
				  `to` varchar(255) DEFAULT NULL,
				  `message` text,
				  `sent` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				  `recd` int(10) unsigned NOT NULL DEFAULT '0',
				  PRIMARY KEY (`id`)
				) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
						";
			
			$resul_commentaires = $this->query($query);
				
		}

		public function destroy(){
		}		


		public function action(){
			if($_REQUEST['action'] == "chat"){
				if ($_GET['chat'] == "chatheartbeat") { chatHeartbeat(); } 
				if ($_GET['chat'] == "sendchat") { sendChat(); } 
				if ($_GET['chat'] == "closechat") { closeChat(); } 
				if ($_GET['chat'] == "startchatsession") { startChatSession(); } 
				if (!isset($_SESSION['chatHistory'])) {
					$_SESSION['chatHistory'] = array();
				}
					
				if (!isset($_SESSION['openChatBoxes'])) {
					$_SESSION['openChatBoxes'] = array();
				}
				
			}
		}
					
		public function apresconnexion(){
			// Useful for chat plugin
			// FIXME: move this code t chat plugin...
			$_SESSION['username'] = $client->prenom.$client->nom;
		}

		public function apresdeconnexion($extclient){
		
			unset($_SESSION['username']);
		}
		
	}
?>
