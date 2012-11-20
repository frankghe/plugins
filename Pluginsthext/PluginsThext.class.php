<?php

	/*
	 * Author	: Frank Ghenassia
	 * Date		: May 28, 2012
	 * 
	 * Ce plugin fournit les mecanismes de base qui facilitent l'ecriture de plugins Thelia
	 * Ce pluging est lui-meme un plugin Thelia standard, ce qui rend tous ceux qui utilisent 
	 * PluginsThext des plugins Thelia standards egalement !
	 * 
	 * Usage:
	 * 
	 * Votre plugin doit simplement heriter d'une des classes suivantes (en lieu et place de la
	 * classe de base Thelia du plugin correspondant), selon votre besion
	 * - PluginsThext pour un plugin classique
	 * - PluginsPaiementsThext pour un plugin de paiement
	 * - PluginsTransportsThext pour un plugin transport
	 * - BaseObjThext pour une classe interne a un plugin qui gere une table de la bdd 
	 * 
	 * Fonctionnalites:
	 * - Toutes les variables correpondant aux champs de la tabe sont crees automatiquement
	 * - Le tableau $bddvars est aussi automatiquement genere a partir de la tabe MySQL
	 * - Une boucle par defaut permet de remplacer n'importe quel champ de la table
	 *   Par exemple, si un champ est appele "foo", il est possible d'y acceder dans la boucle en
	 *   utilisant #FOO
	 * - La gestion multilangue du plugin 'texte' est geree automatiquement et les champs textuels
	 *   associes a l'enregistrement sont traites de maniere 'transparente'
	 * 
	 * 
	 */


include_once(realpath(dirname(__FILE__)) . "/../../../classes/PluginsClassiques.class.php");
include_once(realpath(dirname(__FILE__)) . "/../../../classes/PluginsPaiements.class.php");
include_once(realpath(dirname(__FILE__)) . "/../../../classes/PluginsTransports.class.php");


	// Error display
	function ierror($texte)
	{
		// should record into database when configured in production
		echo $texte;
		exit();
	}

	// Gestion du chargement d'un plugin
	function loadPlugin($plugin, $version = '') {
		$plugin = strtolower($plugin);
		$Plugin = ucfirst($plugin);
		
		$plugindep = realpath(dirname(__FILE__)) . "/../$plugin/$Plugin.class.php";
		if ( ! file_exists($plugindep)) {
			// retrieve calling plugin name
			$a = debug_backtrace();
			$temp = explode('.',basename($a[0]['file']));
			$parent = $temp[0];
			die ("Plugin $parent: Vous devez installer le plugin $Plugin");
		}
		include_once($plugindep);	
	}
	
	function loadInstalledPlugins() {
		$plugin_dir = realpath(dirname(__FILE__) . "\..\\");
		if ($handle = opendir($plugin_dir)) {
			while (false !== ($entry = readdir($handle))) {
				$locdir = $plugin_dir . "\\" . $entry;
				if ($entry[0] != "." && is_dir($locdir)) {
					loadPlugin($entry);
				}
			}
			closedir($handle);
		}		
	}
	
	// Verifie sur un plugin est installe
	function isPlugin($plugin, $version='') {
		$isloaded = false;
		$plugin = strtolower($plugin);
		$Plugin = ucfirst($plugin);
		
		$plugindep = realpath(dirname(__FILE__)) . "/../$plugin/$Plugin.class.php";
		if ( file_exists($plugindep)) {
			$isloaded = true;
		}
		return $isloaded;		
	}

	
	// Retourne un tableau de classe $classnames charge depuis la bdd
	function loadItems($classname, $query) {
		
		$list = array ();
		// récupération des arguments et préparation de la requète
		$table = strtolower($classname);
		$classname = ucfirst($classname);
			
		$p= new PluginsThext(); // FIXME HACK
		$result = $p->query($query);
			
		if ($result) {
				
			$nbres = $p->num_rows($result);
				
			if ($nbres > 0) {
					
				while( $row = $p->fetch_object($result)){

					$i = new $classname;
					foreach($i->bddvars as $var)
					{
						$i->$var = $row->$var;
					}
		
					// If specific fields need dedicated processing
					// (for example dates)
					// proceed with replaceent before default loop
		
		
					// All texte fields are replaced automatically
					if (isset($i->bddvarstext))
						foreach ($i->bddvarstext as $key => $val){
							if (! isPlugin('Texte'))
								ierror('internal error (texte plugin needed) at '. __FILE__ . " " . __LINE__);
							$t = new Texte();
							$t->charger($table, $val, $row->id, $_SESSION['navig']->lang);
							$i->valtext[$val] = $t->description;
						}
					
					array_push($list, $i);
				}
			}
		}
			
		return $list;
		
	}
	
	// --------------------------------------------------
	// Plugin extension pour les plugins Classiques
	// --------------------------------------------------


	class PluginsThext extends PluginsClassiques{

		// Variable a assignee par la classe fille
		public $table;
		
		// Liste des champs de la table
		public $bddvars;
		
		// Array containing texte strings - if any
		public $valtext;
		
		// Array containing instructions for text fields to format 
		// in dbbrowser plugin - if installed and used
		// Format is
		// $textDbbrowserConfig[<textFieldName>] = <config> 
		// With config as defined in Dbbrowser, e.g. global=>label=foo
		public $textDbbrowserConfig = array ();
		
		// Liste les champs textuels a gere dans le plugin
		// Les champs textuels sont geres par le plugin 'texte'
		public $bddvarstext = array ();
		
		public function __construct( $table = '' /*table is plugin name*/){			
			parent::__construct($table);

			if ($table == '')
				// Probably module initialization or pure processing plugin
				// Probablement l'initialisation du module ou un plugin sans table MySQL
				return ;
				
			$this->table = $table;
							
			// Creation des variables selon le contenu MySQL
			
			$this->bddvars = array();
			$this->bddvarstext = array();
			$this->valtext = array();
				
			$result = mysql_query("SHOW COLUMNS FROM $this->table");
			
 			if ($result && mysql_num_rows($result) > 0) {
				while ($row = mysql_fetch_assoc($result)) {
					// Creation de la variable et initiatlisation a... rien !
					$this->$row['Field'] = '';
					// Mise a jour de la liste
					array_push($this->bddvars, $row['Field']);
				}
			}						
		}

		public function charger_id($id){
			$loaded =  $this->getVars("select * from $this->table where id=\"$id\"");
			
			if ($loaded) $this->loadValtext();
			
			return $loaded;
		}
		
		public function charger_query($query) {
			$loaded =  $this->getVars($query);
				
			if ($loaded) $this->loadValtext();
				
			return $loaded;
		}
		
		public function loadValtext() {
			// Load text strings associated with this record
			if (count($this->bddvarstext)) {
				if (! isPlugin('Texte'))
					ierror('internal error (texte plugin needed) at '. __FILE__ . " " . __LINE__);
				
				foreach ($this->bddvarstext as $item) {
					$t = new Texte();
					if ( $t->charger($this->table,$item,$this->id))
						$this->valtext[$item] = $t->description;
				}
			}
		}
		
		// Called by Thelia BO when a plugin is de-activated 
		public function destroy() {
			// BY default, we remove ourselves from moduels table
			$plugin = strtolower(get_class($this));
			
			$query="delete from modulesdesc where plugin='$plugin'";
			$this->query($query);
			
			$query="delete from modules where nom='$plugin'";
			$this->query($query);
		}
		
		public function boucle($texte, $args){
			$search ="";
			
			$res=$out="";
			
			// récupération des arguments et préparation de la requète
			foreach ($this->bddvars as $key => $val){
				$$val = lireTag($args, "$val");
				if ($$val != "") $search .= " and $val=\"". $$val . "\"";
			}
			
			$query = "select * from ". $this->table . " where 1 $search";
			
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
							if (! isPlugin('Texte'))
								ierror('internal error (texte plugin needed) at '. __FILE__ . " " . __LINE__);
							$t = new Texte();
							$t->charger($this->table, $val, $curid, $_SESSION['navig']->lang);
							$htmlTag = '#'.strtoupper($val);
							$res = str_replace($htmlTag, $t->description, $res);				
						}
						$out.=$res;
					}
				}			
			}
			
			return $res;
			
				
		}
				

		// Recherche de parametres qui correspondent aux champs de la table et chargement en tant
		// que variable de la classe
		// Retourne une chaine a ajouter a une requete SQ pour filtrer sur les champs trouves
		public function loadTags($args){
			foreach ($bddvars as $key => $val) {
				$this->$key = lireTag($args, $key);
				if ($this->key!="")  $search.=" and $key=\"$this->key\"";
			}
			return $search;
		}
		
		// Recherche de parametres correspondant aux champs de a table et chargement en tant
		// que variables de l'instance de classe
		// Retourne le nombre de champs trouves
		public function loadParams($args){
			$i = 0;
			foreach ($this->bddvars as $key => $val) {
				if (isset($args[$val])) {
					$this->$val = $args[$val];
					$i++;
				}
			}
			return $i;
		}
		
		
		// Fills class variables with content stored in $a, If defined
		// $a is expected to be read-only...
		public function fillFields(&$a) {
			if (! isPlugin('Texte'))
				ierror('internal error (texte plugin needed) at '. __FILE__ . " " . __LINE__);
			foreach ($this->bddvars as $var) {
				if (array_key_exists($var, $a))
					$this->$var = $a[$var];
			}
		}
		
		// Fills class variables with content stored in $a, If defined
		// $a is expected to be read-only...
		public function fillTextFields(&$a) {
			if (! isPlugin('Texte'))
				ierror('internal error (texte plugin needed) at '. __FILE__ . " " . __LINE__);
			
			foreach ($this->bddvarstext as $var) {
				if (array_key_exists($var, $a))
					$this->valtext[$var] = $a[$var];
			}
		}
		
		// Update all text fields in database
		public function updateTextFields() {
			
			if (count($this->bddvarstext)) {
				if (! isPlugin('Texte'))
					ierror('internal error (texte plugin needed) at '. __FILE__ . " " . __LINE__);
						
				foreach ($this->bddvarstext as $var) {
					$t = new Texte();
					$t->nomtable = $this->table;
					$t->nomchamp = $var;
					$t->parent_id = $this->id;
					if (array_key_exists($var, $this->valtext)) 
						$t->description = $this->valtext[$var];
					else
						$t->description = ''; // We create it anyway...
					
					if ($t->charger($this->table, $var, $this->id)) {
						$t->description = $this->valtext[$var];
						$t->maj();
					}
					else {
						$t->description = $this->valtext[$var];
						$t->add();						
					}
				}
			}
				
		}
		
		public function add() {			
			$this->id = parent::add();
			$this->updateTextFields();
			return $this->id;
		}
		
		public function maj() {
			parent::maj();
			$this->updateTextFields();
			return ;
		}
		
		//
		// Support functions for dbbrowser plugin
		//
		
		// Generates list of options for a given table
		// Selects current record
		public function dbbrowser_dropListTable() {
			$lang = $_SESSION['navig']->lang;
			// Retrieve titre from associated desc table (in case of standard Thelia table)
			if ($this->isTexteTable($this->table)) $l=" AND lang='".$lang."'";
			$query = "SELECT * FROM ".$this->table." WHERE 1 $l";
			$result = mysql_query($query);
			if (!$result) {
				// Should never happen
				ierror('internal error ('.$query.') at '. __FILE__ . " " . __LINE__);
				exit;
			}
			while ($row =  mysql_fetch_assoc($result)) {
				if ($row['id'] == $this->id) $sel = 'selected';
				else $sel = '';
				$out.= '<option value="'.$this->id.'" '.$sel.'>'.$row[$this->getNameFieldName()].'</option>';
			}
				
			return $out;
		}
		
		// Support functions to retrieve value of field name (to display as record 'name')
		// And also the field name containing the value of the field name...
		public function dbbrowser_getName() {
			// By default we simply return the id
			return $this->id;
		}
		
		// Field name storing 
		public function dbbrowser_getNameFieldname() {
			// By default we return 'id'
			return 'id';
		}
		
	}
	
	
	// --------------------------------------------------
	// Plugin extension pour le paiement
	// --------------------------------------------------

	// php ne supporte pas l'heritage multiple
	// Au lieu de declarer PluginsPaiementThext comme ca:
	// class PluginsPaiementThext extends PluginsClassiques, PluginsPaiements
	// On est donc oblige de copier a classe )-:
	class PluginsPaiementsThext extends PluginsPaiements{
	
		public $table;
	
		public $bddvarstext = array ();
		
		public function __construct( $table = '' /*table is plugin name*/){
			parent::__construct($table);
	
			if ($table == '')
				return ;
	
			$this->table = $table;
							
			$this->bddvars = array();
			$this->bddvarstext = array();
				
			$result = mysql_query("SHOW COLUMNS FROM $this->table");
				
			if ($result && mysql_num_rows($result) > 0) {
				while ($row = mysql_fetch_assoc($result)) {
					$this->$row['Field'] = '';
					array_push($this->bddvars, $row['Field']);
					switch ($row['Type']) {
						case 'datetime':
							$def = 'SHOW COLUMNS FROM';
							break;
						default:
							if (substr($row['Type'], 0, strlen('int'))) $def = 0;
							break;
					}
					$this->defbddvals[$row['Field']] = $def;
				}
			}
		}

		public function charger_query($query) {
			$loaded =  $this->getVars($query);
				
			if ($loaded) $this->loadValtext();
				
			return $loaded;
		}
		
		public function charger_id($id){
			$query = "select * from $this->table where id=\"$id\"";
			return $this->charger_query($query);
		}
				
		public function loadValtext() {
			// Load text strings associated with this record
			if (count($this->bddvarstext)) {
				if (! isPlugin('Texte'))
					ierror('internal error (texte plugin needed) at '. __FILE__ . " " . __LINE__);
				
				foreach ($this->bddvarstext as $item) {
					$t = new Texte();
					if ( $t->charger($this->table,$item,$this->id))
						$this->valtext[$item] = $t->description;
				}
			}
		}
		
				
		public function destroy() {
			$plugin = strtolower(get_class($this));
		
			$query="delete from modulesdesc where plugin='$plugin'";
			$this->query($query);
		
			$query="delete from modules where nom='$plugin'";
			$this->query($query);
		}
		
		/*
		 * To avoid too much code duplication inside this file
		 * only few methods are copied from PluginThext.
		 * If more features are needed for Paiement plugins
		 * simply copy the code as needed
		 */
	}
	

	// --------------------------------------------------
	// Plugin extension pour le transport
	// --------------------------------------------------
	
	class PluginsTransportsThext extends PluginsTransports{

		public $bddvarstext = array ();
		
		public function __construct( $table = '' /*table is plugin name*/){
			parent::__construct($table);
	
			if ($table == '')
				return ;
	
			$this->table = $table;
							
			$this->bddvars = array();
			$this->bddvarstext = array();
				
			$result = mysql_query("SHOW COLUMNS FROM $this->table");
				
			if ($result && mysql_num_rows($result) > 0) {
				while ($row = mysql_fetch_assoc($result)) {
					$this->$row['Field'] = '';
					array_push($this->bddvars, $row['Field']);
					switch ($row['Type']) {
						case 'datetime':
							$def = 'SHOW COLUMNS FROM';
							break;
						default:
							if (substr($row['Type'], 0, strlen('int'))) $def = 0;
							break;
					}
					$this->defbddvals[$row['Field']] = $def;
				}
			}
		}

		public function charger_query($query) {
			$loaded =  $this->getVars($query);
				
			if ($loaded) $this->loadValtext();
				
			return $loaded;
		}
		
		public function charger_id($id){
			$query = "select * from $this->table where id=\"$id\"";
			return $this->charger_query($query);
		}
				
		public function loadValtext() {
			// Load text strings associated with this record
			if (count($this->bddvarstext)) {
				if (! isPlugin('Texte'))
					ierror('internal error (texte plugin needed) at '. __FILE__ . " " . __LINE__);
				
				foreach ($this->bddvarstext as $item) {
					$t = new Texte();
					if ( $t->charger($this->table,$item,$this->id))
						$this->valtext[$item] = $t->description;
				}
			}
		}
				
			
		public function destroy() {
			$plugin = strtolower(get_class($this));
	
			$query="delete from modulesdesc where plugin='$plugin'";
			$this->query($query);
	
			$query="delete from modules where nom='$plugin'";
			$this->query($query);
		}
		
		/*
		 * To avoid too much code duplication inside this file
		 * only few methods are copied from PluginThext.
		 * If more features are needed for Paiement plugins
		 * simply copy the code as needed
		 */
	
	}
	
	
	
	// --------------------------------------------------
	// Class de base pour les classes internes a un plugin
	// mais qui doivent gerees une table MySQL
	// --------------------------------------------------
	class BaseObjThext extends BaseObj {
		
		public $table;
		
		public $bddvars = array ();
		
		public $bddvarstext = array ();
		
		public function __construct( $table = '' /*table is plugin name*/){
			parent::__construct($table);
	
			if ($table == '')
				return ;
	
			$this->table = $table;
							
			$this->bddvars = array();
			$this->bddvarstext = array();
				
			$result = mysql_query("SHOW COLUMNS FROM $this->table");
				
			if ($result && mysql_num_rows($result) > 0) {
				while ($row = mysql_fetch_assoc($result)) {
					$this->$row['Field'] = '';
					array_push($this->bddvars, $row['Field']);
					switch ($row['Type']) {
						case 'datetime':
							$def = 'SHOW COLUMNS FROM';
							break;
						default:
							if (substr($row['Type'], 0, strlen('int'))) $def = 0;
							break;
					}
					$this->defbddvals[$row['Field']] = $def;
				}
			}
		}
						
		public function charger_query($query) {
			$loaded =  $this->getVars($query);
				
			if ($loaded) $this->loadValtext();
				
			return $loaded;
		}
		
		public function charger_id($id){
			$query = "select * from $this->table where id=\"$id\"";
			return $this->charger_query($query);
		}

		public function loadValtext() {
			// Load text strings associated with this record
			if (count($this->bddvarstext)) {
				if (! isPlugin('Texte'))
					ierror('internal error (texte plugin needed) at '. __FILE__ . " " . __LINE__);
				
				foreach ($this->bddvarstext as $item) {
					$t = new Texte();
					if ( $t->charger($this->table,$item,$this->id))
						$this->valtext[$item] = $t->description;
				}
			}
		}
				
		public function boucle($texte, $args){
			$search ="";
				
			$res=$out="";
				
			// récupération des arguments et préparation de la requète
			foreach ($this->bddvars as $key => $val){
				$$val = lireTag($args, "$val");
				if ($$val != "") $search .= " and $val=\"". $$val . "\"";
			}
				
			$query = "select * from ". $this->table . " where 1 $search";
				
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
		
		public function loadTags($args){
			foreach ($bddvars as $key => $val) {
				$this->$key = lireTag($args, $key);
				if ($this->$key!="")  $search.=" and $key=\"$this->$key\"";
			}
			return $search;
		}
		
		public function loadParams($args){
			$i = 0;
			foreach ($this->bddvars as $key => $val) {
				if (isset($args[$val])) {
					$this->$val = $args[$val];
					$i++;
				}
			}
			return $i;
		}
		
			// Fills class variables with content stored in $a, IF defined
		// $a is expected to be read-only...
		public function fillFields(&$a) {
			foreach ($this->bddvars as $var) {
				if (array_key_exists($var, $a))
					$this->$var = $a[$var];
			}
		}
		
		// Fills class variables with content stored in $a, IF defined
		// $a is expected to be read-only...
		public function fillTextFields(&$a) {
			foreach ($this->bddvarstext as $var) {
				if (array_key_exists($var, $a))
					$this->valtext[$var] = $a[$var];
			}
		}
		
		// Update all text fields in database
		public function updateTextFields() {
			
			if (count($this->bddvarstext)) {
				if (! isPlugin('Texte'))
					ierror('internal error (texte plugin needed) at '. __FILE__ . " " . __LINE__);
						
				foreach ($this->bddvarstext as $var) {
					$t = new Texte();
					$t->nomtable = $this->table;
					$t->nomchamp = $var;
					$t->parent_id = $this->id;
					if (count($this->valtext) && array_key_exists($var, $this->valtext)) 
						$t->description = $this->valtext[$var];
					else
						$t->description = ''; // We create it anyway...
					
					if ($t->charger($this->table, $var, $this->id)) {
						$t->description = $this->valtext[$var];
						$t->maj();
					}
					else {
						$t->description = $this->valtext[$var];
						$t->add();						
					}
				}
			}
				
		}
		
		public function add() {			
			$this->id = parent::add();
			$this->updateTextFields();
			return $this->id;
		}
		
		public function maj() {
			parent::maj();
			$this->updateTextFields();
			return ;
		}
				
			
		//
		// Support functions for dbbrowser plugin
		//
		
		// Generates list of options for a given table
		// Selects current record
		public function dbbrowser_dropListTable() {
			$lang = $_SESSION['navig']->lang;
			$query = "SELECT * FROM ".$this->table;
			$result = mysql_query($query);
			if (!$result) {
				// Should never happen
				ierror('internal error ('.$query.') at '. __FILE__ . " " . __LINE__);
				exit;
			}
			while ($row =  mysql_fetch_assoc($result)) {
				if ($row['id'] == $this->id) $sel = 'selected';
				else $sel = '';
				$out.= '<option value="'.$this->id.'" '.$sel.'>'.$row[$this->getNameFieldName()].'</option>';
			}
				
			return $out;
		}
		
		// Support functions to retrieve value of field name (to display as record 'name')
		// And also the field name containing the value of the field name...
		public function dbbrowser_getName() {
			// By default we simply return the id
			return $this->id;
		}
		
		public function dbbrowser_getNameFieldname() {
			// By default we return 'id'
			return 'id';
		}
		
	}

?>
