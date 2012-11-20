<?php
	require_once(realpath(dirname(__FILE__)) . "/../Pluginsthext/PluginsThext.class.php");	
	loadPlugin("Texte");
	
	class Category extends PluginsThext{
		const TABLE="category";
		
		function __construct($ref = ""){
			parent::__construct(self::TABLE);
		
			if($id != "")
				$this->charger($id);
			
			$this->bddvarstext = array (
				"titre" , "description"
			);
		}
		
		public function init(){
			$query = "
			CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			PRIMARY KEY (`id`)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
			";
		
			$resul = $this->query($query);
			
			$id = $this->add();
			$this->addTexte($id, "Support informatique", "");
				
			$id = $this->add();
			$this->addTexte($id, "Cours particuliers", "");

			$id = $this->add();
			$this->addTexte($id, "Conseil", "");
				
			$id = $this->add();
			$this->addTexte($id, "Formation", "");

			$id = $this->add();
			$this->addTexte($id, "Autres", "");
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
				
			$res= $out = "";
				
			// récupération des arguments et préparation de la requète
			foreach ($this->bddvars as $key => $val){
				$$val = lireTag($args, "$val");
				if ($$val != "") $search .= " and $val=\"". $$val . "\"";
			}

			$selectedid = lireTag($args,'selected');
			
			$query = "select * from ". self::TABLE . " where 1 $search";
				
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
						
						if ($selectedid == $row->id)
							$res = str_replace('#SELECTED', 'selected', $res);
						
						$out.=$res;	
					}					
				}
					
			}
				
			return $out;
				
		
		}
		
		public function action() {
			if($_REQUEST['action'] == "category_init"){
				$this->init();
			}				
		}
		
		// Fonction utilisee par dbbrowser pour lister les valeurs disponibles
		// dans une liste deroulante
		// en selectionnant l'id $this->id
		function dropListTable()
		{
			$out='';
			$liste = charger_liste_texte('category','titre');
			foreach ($liste as $inst)
			{
				if ($inst->parent_id == $this->id) $sel = 'selected';
				else $sel = '';
				$out.= '<option value="'.$inst->parent_id.'" '.$sel.'>'.$inst->description.'</option>';
			}
			return $out;
		}
		
	}

?>