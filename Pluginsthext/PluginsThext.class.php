<?php

// Author	: Frank Ghenassia
// Date		: May 28, 2012
//
// This plugin provides defaults mechanisms that can be reused by plugins deriving from it
// 
// Usage:
//
// Derive your plugin class from PluginsThext instead of PLuginsClassiques
//
//
// Features:
// All class variables are autmatically inferred from the MySQL database table fields
// $bddvars and $default_bddvars are also automatically inferred from MySQL class
// A default boucle enables to replace any field 
// For example, if a table field is named "name", then you can replace it in boucle using #NAME
 

include_once(realpath(dirname(__FILE__)) . "/../../../classes/PluginsClassiques.class.php");
include_once(realpath(dirname(__FILE__)) . "/../../../classes/PluginsPaiements.class.php");
include_once(realpath(dirname(__FILE__)) . "/../../../classes/PluginsTransports.class.php");


	// Helper function to load plugin while managing dependencies
	function loadPlugin($plugin, $version = '') {
		$plugin = strtolower($plugin);
		$Plugin = ucfirst($plugin);
		
		$plugindep = realpath(dirname(__FILE__)) . "/../$plugin/$Plugin.class.php";
		if ( ! file_exists($plugindep)) {
			// retrieve calling plugin name
			$a = debug_backtrace();
			$temp = explode('.',basename($a[0]['file']));
			$parent = $temp[0];
			die ('Plugin $parent: Vous devez installer le plugin $Plugin');
		}
		include_once($plugindep);
		
	}

	
	// Return an array of class $classnames loaded from database
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
		
					// Si certains champs doivent etre traites specifiquement
					// (par exemple les dates)
					// effectuer le remplacement avant la boucle par defaut
		
		
					// Tous les champs textuels sont remplaces automatiquement
					foreach ($i->bddvarstext as $key => $val){
						$t = new Texte();
						$t->charger(self::TABLE, $val, $curid, $_SESSION['navig']->lang);
						$i->$val = $t->description;
					}
					array_push($list, $i);
				}
			}
		}
			
		return $list;
		
	}
	
	// --------------------------------------------------
	// Plugin extension for Classiques
	// In particular, automatically manages fields from SQL content
	// --------------------------------------------------


	class PluginsThext extends PluginsClassiques{

		// Variable to be set from child class
		public $table;
		
		// List of fields to manage in this table
		public $bddvars;
		
		// Liste text fields to be managed in texte table
		// Filled in by child class
		public $bddvarstext = array ();
		
		public function __construct( $table = '' /*table is plugin name*/){			
			parent::__construct($table);

			if ($table == '')
				// Probably module initialization or pure processing plugin
				return ;
				
			$this->table = $table;
							
			// Create class instance variables according to MySQL table content
			
			$this->bddvars = array();
			$this->bddvarstext = array();
				
			$result = mysql_query("SHOW COLUMNS FROM $this->table");
			// When plugin not loaded yet, table does not exist 
			// so i remove next check to avoid unnecessary warning
			//if (!$result)
 			//	ierror('internal error (db access) at '. __FILE__ . " " . __LINE__);
			
 			if ($result && mysql_num_rows($result) > 0) {
				while ($row = mysql_fetch_assoc($result)) {
					// Create the variable and initialize it to...nothing !
					$this->$row['Field'] = '';
					// Update list
					array_push($this->bddvars, $row['Field']);
				}
			}						
		}

		public function charger_id($id){
			return $this->getVars("select * from $this->table where id=\"$id\"");
		}
		
		// This function is called from BO when "deactivating" a plugin
		public function destroy() {
			// By default, remove itself from modules table
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
				
		// Look for parameters that correspond to table fields and load them as class variables
		// Returns and search string corresponding to the list of loaded fields
		public function loadTags($args){
			foreach ($bddvars as $key => $val) {
				$this->$key = lireTag($args, $key);
				if ($this->key!="")  $search.=" and $key=\"$this->key\"";
			}
			return $search;
		}
		
		public function insertSQL($table, $data){
			$fieldstring = $valuestring = '';
			foreach ($data as $field => $value){
				$fieldstring.=$field.",";
				$valuestring.="'".mysql_real_escape_string($value)."',";
			}
			$fieldstring = substr($fieldstring,0,-strlen(','));
			$valuestring =  substr($valuestring,0,-strlen(','));
			$query = "insert into $table ($fieldstring) values ($valuestring)";
			return $this->query($query);
		}

		public function updateSQL($table, $data, $cond){
			$fieldstring = $valuestring = '';
			foreach ($data as $field => $value){
				$v = mysql_real_escape_string($value);
				$string.= "$field='$v' , ";  
			}
			$string = substr($string,0,-strlen(', '));
			foreach ($cond as $field => $value){
				$v = mysql_real_escape_string($value);
				$condstring.= "$field='$v' and ";  
			}
			$condstring = substr($condstring,0,-strlen('and '));
			$query = "update $table SET $string WHERE $condstring";
			return $this->query($query);
		}
		
		// Look for parameters that correspond to table fields and load them as class variables
		// Returns and search string corresponding to the list of loaded fields
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
		
		
	}
	
	
	// --------------------------------------------------
	// Plugin extension for payments
	// In particular, automatically manages fields from SQL content
	// --------------------------------------------------

	// php so far does NOT support multiple inheritance
	// So instead of PluginsPaiementThext to be delared as
	// class PluginsPaiementThext extends PluginsClassiques, PluginsPaiements
	// We need to copy the class...
	class PluginsPaiementsThext extends PluginsPaiements{
	
		// Variable to be set from child class
		public $table;
	
		// Liste text fields to be managed in texte table
		// Filled in by child class
		public $bddvarstext = array ();
		
		public function __construct( $table = '' /*table is plugin name*/){
			parent::__construct($table);
	
			if ($table == '')
				// Probably module initialization
				return ;
	
			$this->table = $table;
			
			// Create class instance variables according to MySQL table content
				
			$this->bddvars = array();
			$this->bddvarstext = array();
				
			$result = mysql_query("SHOW COLUMNS FROM $this->table");
			// When plugin not loaded yet, table does not exist
			// so i remove next check to avoid unnecessary warning
			//if (!$result)
			//	ierror('internal error (db access) at '. __FILE__ . " " . __LINE__);
				
			if ($result && mysql_num_rows($result) > 0) {
				while ($row = mysql_fetch_assoc($result)) {
					// Create the variable and initialize it to...nothing !
					$this->$row['Field'] = '';
					// Update list
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
	
		public function charger_id($id){
			return $this->getVars("select * from $this->table where id=\"$id\"");
		}
	
		
		// This function is called from BO when "deactivating" a plugin
		public function destroy() {
			// By default, remove itself from modules table
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
				
			
	
		// Look for parameters that correspond to table fields and load them as class variables
		// Returns and search string corresponding to the list of loaded fields
		public function loadTags($args){
			foreach ($bddvars as $key => $val) {
				$this->$key = lireTag($args, $key);
				if ($this->$key!="")  $search.=" and $key=\"$this->$key\"";
			}
			return $search;
		}
	
		// Look for parameters that correspond to table fields and load them as class variables
		// Returns and search string corresponding to the list of loaded fields
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
		
		
		
		public function insertSQL($table, $data){
			$fieldstring = $valuestring = '';
			foreach ($data as $field => $value){
				$fieldstring.=$field.",";
				$valuestring.="'".mysql_real_escape_string($value)."',";
			}
			$fieldstring = substr($fieldstring,0,-strlen(','));
			$valuestring =  substr($valuestring,0,-strlen(','));
			$query = "insert into $table ($fieldstring) values ($valuestring)";
			return $this->query($query);
		}
	
		
		public function updateSQL($table, $data, $cond){
			$fieldstring = $valuestring = '';
			foreach ($data as $field => $value){
				$v = mysql_real_escape_string($value);
				$string.= "$field='$v' , ";  
			}
			$string = substr($string,0,-strlen(', '));
			foreach ($cond as $field => $value){
				$v = mysql_real_escape_string($value);
				$condstring.= "$field='$v' and ";  
			}
			$condstring = substr($condstring,0,-strlen('and '));
			$query = "update $table SET $string WHERE $condstring";
			return $this->query($query);
		}
	}
	

	// --------------------------------------------------
	// Plugin extension for transport
	// In particular, automatically manages fields from SQL content
	// --------------------------------------------------
	
	// php so far does NOT support multiple inheritance
	// So instead of PluginsPaiementThext to be delared as
	// class PluginsPaiementThext extends PluginsClassiques, PluginsPaiements
	// We need to copy the class...
	class PluginsTransportsThext extends PluginsTransports{

		public function __construct($nom="") {
			parent::__construct($nom);
		}
		
	
		public function charger_id($id){
			return $this->getVars("select * from $this->table where id=\"$id\"");
		}
	
	
		// This function is called from BO when "deactivating" a plugin
		public function destroy() {
			// By default, remove itself from modules table
			$plugin = strtolower(get_class($this));
	
			$query="delete from modulesdesc where plugin='$plugin'";
			$this->query($query);
	
			$query="delete from modules where nom='$plugin'";
			$this->query($query);
		}
	
	}
	
	
	
	// --------------------------------------------------
	// Base class for non-plugin classes
	// --------------------------------------------------
	
	// Class to be used when class not intended to be visible as a plugin
	class BaseObjThext extends BaseObj {
		
		// Variable to be set from child class
		public $table;
		
		public $bddvars = array ();
		
		// Liste text fields to be managed in texte table
		// Filled in by child class
		public $bddvarstext = array ();
		
		public function __construct( $table = ''){			
			parent::__construct();

			if ($table == '')
				// Probably module initialization
				return ;
				
			$this->table = $table;
							
			// Create class instance variables according to MySQL table content
			
			$this->bddvars = array();
			
			$result = mysql_query("SHOW COLUMNS FROM $this->table");
			// When plugin not loaded yet, table does not exist 
			// so i remove next check to avoid unnecessary warning
			//if (!$result)
 			//	ierror('internal error (db access) at '. __FILE__ . " " . __LINE__);
			
 			if ($result && mysql_num_rows($result) > 0) {
				while ($row = mysql_fetch_assoc($result)) {
					// Create the variable and initialize it to...nothing !
					$this->$row['Field'] = '';
					// Update list
					array_push($this->bddvars, $row['Field']);
				}
			}						
		}
				
		public function charger_id($id){
			return $this->getVars("select * from $this->table where id=\"$id\"");
		}
	}

?>
