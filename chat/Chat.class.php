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

		public function boucle($texte, $args){
			
			// récupération des arguments
			$id = lireTag($args, "id");
			$client_src = lireTag($args, "client_src");
			$client_dst = lireTag($args, "client_dst");
			
			$search ="";
			
			$res="";
			
			// préparation de la requête
			if ($id!="")  $search.=" and id=\"$id\"";
			if ($client_src!="")  $search.=" and client_src=\"$client_src\"";
			if ($client_dst!="")  $search.=" and client_dst=\"$client_dst\"";
				
			
			$query = "select * from ". self::TABLE . " where 1 $search";
			
			$result = $this->query($query);
			
			if ($result) {
			
				$nbres = $this->num_rows($result);
			
				if ($nbres > 0) {
			
					while( $row = $this->fetch_object($result)){
			
						$temp = $texte;
			
						$temp = str_replace("#TITRE", $row->titre, $texte);
						$temp = str_replace("#MESSAGE", $row->message, $temp);
						$temp = str_replace("#DATE", substr($row->date, 0, 10), $temp);
						$temp = str_replace("#HEURE", substr($row->date, 11), $temp);
						$temp = str_replace("#CLIENT_SRC", $row->client_src, $temp);
						$temp = str_replace("#CLIENT_DST", $row->client_dst, $temp);
						$res .= $temp;
					}
				}
			
			}
			
			return $res;
			
				
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
		
	}
?>
