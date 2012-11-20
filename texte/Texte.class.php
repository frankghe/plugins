<?php

include_once(realpath(dirname(__FILE__)) . "/../Pluginsthext/PluginsThext.class.php");


	// Cette fonction renvoie un tableau d'instance de classes Texte
	// qui contient la liste des champs de la table Texte filtrees avec
	// les champs nomtable et eventuellement nomchamp
	function charger_liste_texte($table,$nomchamp='')
	{
		$l = array();
		if ($nomchamp != '') $nc = " AND nomchamp='".$nomchamp."'";
			else $nc = '';
		$query = "SELECT * FROM texte WHERE nomtable='".$table."' AND lang='"
					.$_SESSION['navig']->lang."'".$nc;
		$result = mysql_query($query);
		if (!$result) {
			// Should never happen
			ierror('internal error ('.$query.') at '. __FILE__ . " " . __LINE__);
			exit;
		}
		while ($row =  mysql_fetch_assoc($result)) {
			$inst = new Texte();
			foreach ($inst->bddvars as $field)
				$inst->$field = $row[$field];
			array_push($l,$inst);
		}
		return $l;
		
	}
	
	class Texte extends PluginsThext{

		const TABLE = 'texte';
		
		public function __construct( $id=0 ){
			parent::__construct("texte");	
			
			if($id > 0)
				$this->charger_id($id);
			
		}
		
		public function charger($table, $champ, $champ_id, $lang = 1){
			$query = "select * from ". self::TABLE . " where nomtable=\"$table\" and nomchamp=\"$champ\"".
						" and parent_id=\"$champ_id\" and lang=\"$lang\"";
			return $this->getVars($query);
		}

		// Pour chaque element de $liste, on cherche la valeur dans $val
		// et on cree ou maj la table 
		// le nom du champ dans le tableau de valeur est attendu comme:
		// <nomtable>_<nomchamp>
		public function ajout(&$liste, &$tabval){
			// Tous les champs textuels sont mis a jour egalement
			foreach ($liste as $key => $val){
				// Si le champ existe , une simple mise a jour, sinon creation
				if ($this->charger($this->nomtable, $val, $this->parent_id, $this->lang)){
					$this->description = $tabval[$val];
					$this->maj();
				}
				else{
					$this->nomchamp = $val;
					$this->description = $tabval[$val];
					$this->add();
				}
					
			}
				
		} 
		
		
		public function init(){									
			$query = "
				CREATE TABLE IF NOT EXISTS `texte` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `nomtable` varchar(256) DEFAULT NULL,
				  `nomchamp` varchar(256) DEFAULT NULL,
				  `lang` int(11) NOT NULL DEFAULT '1',
				  `parent_id` int(11) NOT NULL DEFAULT '0',
				  `description` text,
				  PRIMARY KEY (`id`)
				) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
						";
			
			$result = $this->query($query);
				
		}

		// Support for dbbrowser
		// Return the tablename referenced by current record, for field snipid
		// Basically returns the tablename used by the class
		public function dbbrowser_parent_id_getReference() {
			$claz = ucfirst($this->nomtable);
			$clinst = new $claz();
				
			return $clinst->table;
		}
		
		
		
		public function destroy(){
		}		

		public function boucle($texte, $args){
			
			// récupération des arguments			
				
		}	

		public function action(){
					
		}
		
	}
?>