<?php
	require_once(realpath(dirname(__FILE__)) . "/../Pluginsthext/PluginsThext.class.php");	
	
	class Supplier extends PluginsThext{
		const TABLE="supplier";

		function __construct($id = ""){
			parent::__construct(self::TABLE);
		
			if($id != "")
				$this->charger_id($id);
			
			$this->issupplier = false;
		}
		
		public function init(){
			$query = "
			CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`client` int(11) NOT NULL DEFAULT '0',
			`issupplier` tinyint(1) NOT NULL DEFAULT '0',
			PRIMARY KEY (`id`)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
			";
	
			$resul_commentaires = $this->query($query);
			
			// Pre-load table with all existing clients
			$query = "SELECT id FROM client";
			$result = $this->query($query);
				
			if ($result) {
			
				$nbres = $this->num_rows($result);
			
				if ($nbres > 0) {
						
					while( $row = $this->fetch_object($result)){
			
						$s = new Supplier();
						$s->client = $row->id;
						$s->issupplier = false;
						$s->add();
					}
				}
			}
				
		}

		function charger_client($client) {
			return $this->getVars("select * from $this->table where client=\"$client\"");
		}
		
		public function getIdFromClient($clientid) {
			if (! $this->charger_client($_SESSION['navig']->client->id)) {
				// Should never happen
				ierror('internal error (client is not a supplier) at '. __FILE__ . " " . __LINE__);
			}
			return $this->id;
		}
		
		public function getClientId() {
			if (! $this->id>0)
				ierror('internal error (supplier not loaded) at '. __FILE__ . " " . __LINE__);
				
			return $this->client;
		}
		
		public function isSupplier($supplier = 0) {
			if (! $supplier) return $this->issupplier;
			if (! $this->charger($supplier))
				ierror('internal error (invalid supplier id) at '. __FILE__ . " " . __LINE__);
			return $this->issupplier;
		}

		public function getName() {
			$c = new Client($this->client);
			return $c->prenom." ".$c->nom;
		}
		
		public function boucle($texte, $args){
			$search ="";
				
			$res="";
				
			// récupération des arguments et préparation de la requète
			foreach ($this->bddvars as $key => $val){
				$$val = lireTag($args, "$val");
				if ($$val != "") $search .= " and $val=\"". $$val . "\"";
			}

			$tablestring = self::TABLE;
			$servicesupplier = lireTag($args,'servicesupplier');
			$query = "select * from $tablestring where 1 $search";
				
			$result = $this->query($query);
				
			if ($result) {
					
				$nbres = $this->num_rows($result);
					
				if ($nbres > 0) {
						
					while( $row = $this->fetch_object($result)){
							
						$res = $texte;
		
						// Si certains champs doivent etre traites specifiquement
						// (par exemple les dates)
						// effectuer le remplacement avant la boucle par defaut
		
						// Par defaut, tous les champs sont disponibles en tag
						foreach ($this->bddvars as $key => $val){
							$htmlTag = '#'.strtoupper($val);
							$res = str_replace($htmlTag, $row->$val, $res);
						}
						// Tous les champs textuels sont remplaces automatiquement
						foreach ($this->bddvarstext as $key => $val){
							$t = new Texte();
							$t->charger(self::TABLE, $val, $row->id, $_SESSION['navig']->lang);
							$htmlTag = '#'.strtoupper($val);
							$res = str_replace($htmlTag, $t->description, $res);
						}
						$found = false;
						if ($servicesupplier>0 && isPlugin('service')) {
							$query = "select * from servicesupplierpaytype where servicesupplierpaytype.paytype=$row->id
										and servicesupplierpaytype.servicesupplier=$servicesupplier";
							$r = $this->query($query);
							if ($r) {
								$nbres = $this->num_rows($r);
								if ($nbres > 0) {
									while( $row = $this->fetch_object($r)){
										$found = true;
										$res = str_replace('#SERVICESUPPLIERPAYTYPE_PRICE', $row->price, $res);
										$res = str_replace('#SERVICESUPPLIERPAYTYPE_PRICE2', $row->price2, $res);
										$res = str_replace('#SERVICESUPPLIERPAYTYPE_DISCOUNT', $row->discount, $res);
										$res = str_replace('#SELECTED', 'selected', $res);
										$res = str_replace('#CHECKED', 'checked', $res);
									}
								}
							}
						}
						if (! $found) {
								$res = str_replace('#SERVICESUPPLIERPAYTYPE_PRICE', '', $res);
								$res = str_replace('#SERVICESUPPLIERPAYTYPE_PRICE2', '', $res);
								$res = str_replace('#SERVICESUPPLIERPAYTYPE_DISCOUNT', '', $res);
						}
								
						$out.=$res;
						
					}						
				}
					
			}
				
			return $out;
				
		
		}
		
		
		public function apresclient($client){
			$this->client = $client->id;
			$this->issupplier = false; // by default, new client is not a supplier
			$this->add();
		}

		public function apresconnexion(){
		
			if ( ! isset($_SESSION['navig']->supplier)) {
				$s = new Supplier();
				$s = $s->charger_client($_SESSION['navig']->client->id);
				$_SESSION['navig']->supplier = $s;
			}
		}
		
		public function apresdeconnexion($extclient){
				
			unset($_SESSION['navig']->supplier);
		}
		
		
		public function action() {

			switch ($_REQUEST['action']) {
				case 'supplier_init': $this->init();
					break ;

				default :
			}			
		}
		
	}

?>