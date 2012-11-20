<?php

include_once(realpath(dirname(__FILE__)) . "/../Pluginsthext/PluginsThext.class.php");
	
	
	class Messagerie extends PluginsThext{

		const TABLE = 'messagerie';
		
		public function __construct( $id=0 ){
			parent::__construct("messagerie");	

			if($id > 0)
				$this->charger_id($id);
			
		}

		public function charger($id){		
			return $this->getVars("select * from $this->table where id=\"$id\"");
		}


		public function init(){									
			$query = "
				CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `client_src` int(11) NOT NULL DEFAULT '0',
				  `client_dst` int(11) NOT NULL DEFAULT '0',
				  `titre` text,
				  `message` text,
				  `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
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
			if($_REQUEST['action'] == "postmessagerie"){
			
				$m = new Messagerie();
				$m->titre = $_REQUEST['messagerie_titre'];
				$m->message = $_REQUEST['messagerie_message'];
				$m->client_src = $_REQUEST['messagerie_src'];
				$m->client_dst = $_REQUEST['messagerie_dst'];
				$m->date = date("Y-m-d H:i:s");
			
				$m->add();
			
			}
					
		}
		
		//
		// Support functions for dbbrowser plugin
		//
		
		public function dbbrowser_fieldlookup($field)
		{
			switch ($field) {
				case 'client_src':
				case 'client_dst':
					$out = 'client';
				default:
					break;
			}
			return $out;
		}
		
		// Support for dbbrowser
		// Return the tablename referenced by current record, for field snipid
		// Basically return the field sniptype
		public function dbbrowser_client_src_getReference() {
			return 'client';
		}
		
		public function dbbrowser_client_dst_getReference() {
			return 'client';
		}
		
	}
?>
