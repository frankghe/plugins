<?php

include_once(realpath(dirname(__FILE__)) . "/../Pluginsthext/PluginsThext.class.php");

loadPlugin("Texte");
	
	class Cvitae extends PluginsThext {
		
		const TABLE = 'cvitae';		
		
		function __construct( $id = 0){
			parent::__construct("cvitae");
			
			// Ce tableau liste les champs a rechercher dans la table de gestions des champs "linguistiques"
			$this->bddvarstext = array (
					"titre" , "intro" , "descdiplome" , "descexperience", "descetudes"
			);
			
			if ($id > 0)
				$this->charger_id($id);
		}

				
		public function charger($id){		
			return $this->getVars("select * from $this->table where id=\"$id\"");
		}

		public function charger_client($client){
			return $this->getVars("select * from $this->table where client=\"$client\"");
		}
		
		
		public function init(){									
			$query = "
				CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `client` int(11) NOT NULL DEFAULT '0',
				  `nvxetude` int(11) NOT NULL DEFAULT '0',
				  `experience` int(11) NOT NULL DEFAULT '0',
				  `diplome` int(11) NOT NULL DEFAULT '0',
				  `creation` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				  `maj` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				  PRIMARY KEY (`id`)
				) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
						";
			
			$resul_commentaires = $this->query($query);
				
		}

		public function destroy(){
		}		

		public function boucleS($texte, $args){
			global $id_supplier;
			$search ="";
			
			$res=$out="";
			
			// récupération des arguments et préparation de la requète
			foreach ($this->bddvars as $key => $val){
				$$val = lireTag($args, "$val");
				if ($$val != "") $search .= " and $val=\"". $$val . "\"";
			}
			
			// Warning: id_supplier en parametre de l'url est exclusif de 'client' en tag de la boucle ! 
			if ($id_supplier > 0) $search .= " and client=\"". $id_supplier . "\"";
			
			$query = "select * from ". self::TABLE . " where 1 $search";
			
			$result = $this->query($query);
			
			if ($result) {
			
				$nbres = $this->num_rows($result);
			
				if ($nbres > 0) {
			
					while( $row = $this->fetch_object($result)){
			
						$res = $texte;
						$curid = $row->id;
						
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
							$t->charger(self::TABLE, $val, $curid, $_SESSION['navig']->lang);
							$htmlTag = '#'.strtoupper($val);
							$res = str_replace($htmlTag, $t->description, $res);				
						}
						$out.=$res;
					}
				}
			}
			
			return $res;
			
				
		}	

		public function action(){

			$cv = new Cvitae();
				
			if ($_GET['action'] == "majcvitae"){
			
				$c = new Cvitae();
				if ($c->charger_client($_SESSION['navig']->client->id))
					// cv exists, nothing to do
					return ;
				else{
					$c->creation = date ("Y-m-d H:i:s");
					$c->maj = date ("Y-m-d H:i:s");
					$c->client = $_SESSION['navig']->client->id;
					$c->add();
				}
			
			}
			
			else if ($_REQUEST['action'] == "modifiercvitae"){
				if ($_REQUEST['client'] > 0)
					$cv->charger_client($_REQUEST['client']);
				else
					// should never happen
					return ;
				 
				// récupération des arguments et préparation de la requète
				foreach ($this->bddvars as $key => $val){
					if ($_REQUEST[$val]) $cv->$val = $_REQUEST[$val];
				}
				
				// Ajouter ici la recuperation specifique des valeurs
				// ...
				
				$cv->maj();

				$t = new Texte();
				$t->nomtable = self::TABLE;
				$t->parent_id = $cv->id;
				$t->lang = $_SESSION['navig']->lang;
				$t->ajout($this->bddvarstext, $_POST);
				
				
			}		
		}
		
	}
	
?>