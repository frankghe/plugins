<?php
	require_once(realpath(dirname(__FILE__)) . "/../Pluginsthext/PluginsThext.class.php");

	class Tag extends PluginsThext{

		const TABLE="tag";
		
		function __construct($id = 0){
			parent::__construct(self::TABLE);

			if($id > 0)
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
		
			$resul_commentaires = $this->query($query);
				
		}
		
		public function boucle($texte, $args){
			$search ="";
		
			$res="";
		
			// récupération des arguments et préparation de la requète
			foreach ($this->bddvars as $key => $val){
				$$val = lireTag($args, "$val");
				if ($$val != "") $search .= " and $val=\"". $$val . "\"";
			}
		
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
					}
		
					// Tous les champs textuels sont remplaces automatiquement
					foreach ($this->bddvarstext as $key => $val){
						$t = new Texte();
						$t->charger(self::TABLE, $val, $curid, $_SESSION['navig']->lang);
						$htmlTag = '#'.strtoupper($val);
						$res = str_replace($htmlTag, $t->description, $res);
					}
				}
					
			}
		
			return $res;
		
		
		}
		
		public function action() {
			if($_REQUEST['action'] == "tag_initdb"){
				$this->init();
			}
			else if($_REQUEST['action'] == "tag_maj") {
					if (! $this->charger_id($_REQUEST['id'])) {
						// Should never happen...
						ierror('internal error (tag does not exist) at '. __FILE__ . " " . __LINE__);
						exit;
					}

					// Tous les champs textuels sont maj
					foreach ($this->bddvarstext as $key => $val){
						$t = new Texte();
						$t->charger(self::TABLE, $val, $this->id, $_SESSION['navig']->lang);
						$t->description = $_REQUEST[$val];
						$t->maj();
					}
					
				}	
				elseif ($_REQUEST['action'] == "tag_ajout"){
					$this->table = $_REQUEST['table'];
					$id = $this->ajout();
					// Tous les champs textuels sont ajoutes
					$t = new Texte();
					$t->nomtable = self::TABLE;
					$t->parent_id = $id;
					$t->lang = $_SESSION['navig']->lang;
					$t->ajout($this->bddvarstext, $_REQUEST);
						
				}
		}		

		// Search for tags matching text
		function search($text){
		
			if(! $_SESSION["navig"]->connecte)
				return ;
		
			$search = 'description LIKE \'%'.$text. '%\'';
			$query = "select * from texte where texte.nomtable='$this->table' and
						texte.nomchamp='titre' and ($search) LIMIT 10";
			$result = $this->query($query);
			if(empty($result)) die('Requête invalide : ' . mysql_error());
		
			$list = array ();
			while( $row = $this->fetch_object($result)){
				$item['value'] = $row->description;
				$item['id'] = $row->parent_id;
				array_push($list,$item);
			}
		
			echo json_encode($list);
			return ;
			// for test
			echo json_encode(array (
					array ( 'label' => 'test1' , 'value' => 'testvalue'),
					array ('value' => 'value1')));
			//echo json_encode(array ("test", "test2"));
			return ;
		}
		
				
		
	}

?>