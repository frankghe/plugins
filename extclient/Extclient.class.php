<?php
	require_once(realpath(dirname(__FILE__)) . "/../Pluginsthext/PluginsThext.class.php");	
	loadPlugin("Texte");
	
	class Extclient extends PluginsThext{
		const TABLE="extclient";

		function __construct($id = ""){
			parent::__construct(self::TABLE);
		
			if($id != "")
				$this->charger_id($id);
			
		}
		
		public function init(){
			$query = "
			CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`client` int(11) NOT NULL DEFAULT '0',
			`issupplier` tinyint(1) NOT NULL DEFAULT '0',
			`isselectactive` tinyint(1) NOT NULL DEFAULT '0',
			`adistance` tinyint(1) NOT NULL DEFAULT '0',
			`credit` int NOT NULL DEFAULT '0',
			`privilege` int(4) NOT NULL DEFAULT '0',
			PRIMARY KEY (`id`)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
			";
		
			$resul_commentaires = $this->query($query);
		}

		public function charger_client($client) {
			return $this->getVars("select * from $this->table where client=\"$client\"");
		}
		
		// WARNING:
		// Only access this function IF
		// the Supplier plugin has been separately loaded  
		public function charge_supplier($supplier) {
			if (! isPLugingLoaded('Supplier'))
				ierror('Supplier pluging is not loaded at '. __FILE__ . " " . __LINE__);
			
			$s = new Supplier($supplier);
			$query = "select * from $this->table where client=\"".$s->getClientId()."\"";
			return $this->getVars($query);
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
		
		public function processboucle($texte, $global = false)
		{

			if ($global) $prefix = 'CLIENT_';
				else $prefix = '';
				
			if ($this->isselectactive) $sel = 'selected';
			else $sel = '';
			$texte = str_replace("#".$prefix."ISSELECTACTIVE", "$sel", $texte);
			
			$conn = new Connexion();
			$conn->charger_client_enligne($this->client);
			if ($conn->isenligne()) $sel = 'selected';
				else $sel = '';
			$texte = str_replace("#".$prefix."ISENLIGNE", "$sel", $texte);
			
			if ($row->adistance) $sel = 'selected';
				else $sel = '';
			$texte = str_replace("#".$prefix."ADISTANCE", "$sel", $texte);
			
			if ($this->sursite) $sel = 'selected';
				else $sel = '';
			$texte = str_replace("#".$prefix."SURSITE", "$sel", $texte);
			
			if ($this->issupplier) $sel = "checked";
				else $sel = "";
			$texte = str_replace("#".$prefix."ISSUPPLIER", $sel, $texte);
			
			if ($this->isselectactive) $sel = "checked";
				else $sel = "";
			$texte = str_replace("#".$prefix."ISSELECTACTIVE", $sel, $texte);
			
			if ($c->adistance) $sel = 'checked';
				else $sel = '';
			$texte = str_replace("#".$prefix."ADISTANCE", $sel, $texte);
			
			if ($c->sursite) $sel = 'checked';
				else $sel = '';
			$texte = str_replace("#".$prefix."SUPPLIER_SURSITE", $sel, $texte);
				
			if ($c->sursite) $sel = 'checked';
				else $sel = '';
			$texte = str_replace("#".$prefix."SUPPLIER_SURSITE", $sel, $texte);
			
			return $texte;
		}
		
		public function apresclient($client){
			$this->client = $client->id;
			$this->add();
		}

		public function apresconnexion(){
			// Useful for chat plugin
			// FIXME: move this cde t chat plugin...
			$_SESSION['username'] = $client->prenom.$client->nom;			
			$ec = new Extclient();
			$ec->charger_client($_SESSION['navig']->client->id);
			$_SESSION['navig']->extclient = $ec;
		}
		
		public function apresdeconnexion($extclient){
				
			unset($_SESSION['navig']->extclient);
		}
		
		
		public function action() {

			switch ($_REQUEST['action']) {
				case 'extclient_init': $this->init();
					break ;
				case 'extclient_config': 			
					if (! $this->charger_client($_SESSION['navig']->client->id))
						// ne devrait jamais arriver
						return ;
				
					$this->loadParams($_REQUEST);
				
					// on ne peut pas activer a selection sur e cleint n'est pas un fournisseur
					if (!$this->issupplier && $this->isselectactive) $client->isselectactive = 0;
				
					$this->maj();
					break;

				default :
			}			
		}
		
		public function creditTransaction($amount) {
			$this->credit += $amount;
		}
					
	}

?>