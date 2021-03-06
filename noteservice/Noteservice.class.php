<?php

include_once(realpath(dirname(__FILE__)) . "/../Pluginsthext/PluginsThext.class.php");
	
	
	class Noteservice extends PluginsThext{

		const TABLE = 'noteservice';
		
		public function __construct( $id=0 ){
			parent::__construct(self::TABLE);	

			if($id > 0)
				$this->charger_id($id);
				
		}

		public function charger_wallet($wallet){
			return $this->getVars("select * from $this->table where wallet=\"$wallet\"");
		}
		
		public function stats($who, $id, $operation){
			if ($who == 'supplier')
				$query = "select $operation(note) as res from noteservice,wallet,servicesupplierpaytype,servicesupplier where
							servicesupplier.supplier=$id and servicesupplierpaytype.servicesupplier=servicesupplier.id and 
							servicesupplierpaytype.id=wallet.item and wallet.itemtype='servicesupplierpaytype' and  
							noteservice.wallet=wallet.id";
			else
				$query = "select $operation(note) as res from noteservice,wallet where
						wallet.client=$id and wallet.itemtype='servicesupplierpaytype' and noteservice.wallet=wallet.id";
				
			
			$result = $this->query($query);
			$row = $this->fetch_object($result);
			if (isset($row->res))
				$r = round($row->res, 1);
			else
				$r = '';
			return $r;
		}

		public function init(){									
			$query = "
				CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `wallet` int(11) NOT NULL DEFAULT '0',
				  `note` int(3) NOT NULL DEFAULT '0',
				  `description` text,
				  `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				  PRIMARY KEY (`id`)
				) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
									";
			
			$resul = $this->query($query);
				
		}

		public function destroy(){
		}		

		public function action(){

			switch ($_REQUEST['action']) {
				case 'ajoutnote':
					
					// We select all purchased services so that we can do batch processing of the notes
					// This enables to build web page with multiple polls
					$query = "select wallet.* from wallet where wallet.client='".$_SESSION['navig']->client->id."'";
					$result = $this->query($query);
									
					// Le formulaire contient toutes les notes pour les produits de la commande
					// chaque note est associe au produit parce que le champ contient le numero venteprod
					if ($result) {
						$nbres = $this->num_rows($result);
						if ($nbres > 0) {
							while( $row = $this->fetch_object($result)){
								if($_REQUEST['note'.$row->id] > 0){
									$note = new noteservice();
									if ($note->charger_wallet($row->id))
										// should never happen (once client has answered, he has no more
										// the possibility to answer again...
										return ierror('internal error at '. __FILE__ . " " . __LINE__);
			
									// Offer is selected, update or create it
									$note->note = $_REQUEST['note'.$row->id];
									$note->description = $_REQUEST['description'.$row->id];
									$note->wallet = $row->id;
									$note->date = date("Y-m-d H:i:s");
									$note->add();
								}
							}
						}
					}
				case 'noteservice_init':
					$this->init();
					break;
				default:
					break;
			}					
		}
		
	}
?>
