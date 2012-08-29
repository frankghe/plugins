<?php
	require_once(realpath(dirname(__FILE__)) . "/../Pluginsthext/PluginsThext.class.php");	
	loadPlugin("Texte");
	
	class Paytype extends PluginsThext{
		const TABLE="paytype";		

		const FORFAIT='forfait';
		const PERDAY='alajournee';
		const PERHOUR='alheure';
		const PERMIN = 'alaminute';
		const SUBSCRIPTION = 'abonnement';
		
		
		function __construct($id = 0){
			parent::__construct(self::TABLE);
		
			if($id != 0)
				$this->charger_id($id);
			
			$this->bddvarstext = array (
					"titre" , "description"
			);
				
		}
		
		public function init(){
			$query = "
			CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`category` varchar(128) DEFAULT NULL,
			PRIMARY KEY (`id`)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
			";
		
			$resul_commentaires = $this->query($query);
			
			$this->category = self::FORFAIT;
			$id = $this->add();
			$this->addTexte($id, "Paiement forfaitaire", "");
				
			$this->category = self::PERMIN;
			$id = $this->add();
			$this->addTexte($id, "Paiement à la minute", "");

			$this->category = self::PERHOUR;
			$id = $this->add();
			$this->addTexte($id, "Paiement à l'heure", "");
				
			$this->category = self::PERDAY;
			$id = $this->add();
			$this->addTexte($id, "Paiement à la journée", "");

			$this->category = self::SUBSCRIPTION;
			$id = $this->add();
			$this->addTexte($id, "Paiement mensuel", "");
		}

		public function addTexte($id, $titre, $desc){
			// Create default payment methods
			$t = new Texte();
			$t->nomtable = self::TABLE;
			$t->parent_id = $id;
			$t->lang = $_SESSION['navig']->lang;
			$vars['titre'] = $titre;
			$vars['description'] = $desc;
			$t->ajout($this->bddvarstext, $vars);
			
				
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
			$serviceclient = lireTag($args,'serviceclient');
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
						if ($serviceclient>0){
							$query = "select * from serviceclientpaytype where serviceclientpaytype.paytype=$row->id
										and serviceclientpaytype.serviceclient=$serviceclient";
							$r = $this->query($query);
							if ($r) {
								$nbres = $this->num_rows($r);
								if ($nbres > 0) {
									while( $row = $this->fetch_object($r)){
										$found = true;
										$res = str_replace('#SERVICECLIENTPAYTYPE_PRICE', $row->price, $res);
										$res = str_replace('#SERVICECLIENTPAYTYPE_PRICE2', $row->price2, $res);
										$res = str_replace('#SERVICECLIENTPAYTYPE_DISCOUNT', $row->discount, $res);
										$res = str_replace('#SELECTED', 'selected', $res);
										$res = str_replace('#CHECKED', 'checked', $res);
									}
								}
							}
						}
						if (! $found) {
								$res = str_replace('#SERVICECLIENTPAYTYPE_PRICE', '', $res);
								$res = str_replace('#SERVICECLIENTPAYTYPE_PRICE2', '', $res);
								$res = str_replace('#SERVICECLIENTPAYTYPE_DISCOUNT', '', $res);
						}
								
						$out.=$res;
						
					}						
				}
					
			}
				
			return $out;
				
		
		}

		public function action() {
			if($_REQUEST['action'] == "paytype_init"){
				$this->init();
			}
		}
		
		
		
	}

?>