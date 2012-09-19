<?php
	require_once(realpath(dirname(__FILE__)) . "/../Pluginsthext/PluginsThext.class.php");
	require_once(realpath(dirname(__FILE__)) . "./thelia_wa.php");

	//
	// Installation:
	// . copy the images included in the dbbrowser directory to $root/template/_gfx
	//
	
	// fieldFormat is used to format fields when we need to display and/or edit table fields
	class fieldFormat {
		public $table; // Table nameS
		public $name; // Field name
		public $label; // string to display as label for this field
		public $format; // one of int,char,text,bool,datetime,date
		public $dbinfo = array(); // Array of info extracted from database 
		public $maxlistfieldlen = 20; // Maximum length for a string in a cell of a table
		
		function __construct($table, $info = ''){
			
			$this->table = $table;
			if (isset($info)) $this->parse_dbinfo($info);
		}
		
		// Sets class field for $field, knowing that it is stored in texte table
		// and therefore has no dbinfo information
		// but is of type text
		public function loadTexte($field)
		{
			$this->name = $field;
			$this->format = 'text';
		}
		public function parse_dbinfo($info = '')
		{
			if (isset($info))
				$this->dbinfo = $info;
			
			if (! isset($this->dbinfo)) {
				// Should never happen
				ierror('internal error ('.$query.') at '. __FILE__ . " " . __LINE__);
				exit;
			}
			
			// Retrieve formatting info stored in Comment field of database:
			// Format is:
			// global=>param0=val0|...&&list=>param1=val1|param2=val2|...&& edit=>param3=val3|param4=val4|...&&
			// param0 is used to set global configuration information for this field
			// param1 and param2 are used to configure display when record is shown in a list (see showList)
			// param2 and param3 are used to configure display when record is shown in a forma (see editRecord)
			//
			// Defined parameters are:
			// global: 
			//    - type=bool|int|float|char|text|datetime|date
			//    - label=<string>
			//    - isreference=true|false
			//    - referenceTable=<string>
			// list:
			//    - access=rw|ro (rw= read/write ; ro=read-only)
			//    - display=true|false [default=true]
			// edit:
			//    - access=rw|ro (rw= read/write ; ro=read-only)
			//    - display=true|false [default=true]
			//
			// syntax: the string must include at least one '&&' even when using only one of
			// global, list, edit...	
			//		
			$p = explode("&&",$this->dbinfo['Comment']);

			$list = explode("=>",$p[0]);
			$listparams = explode("|",$list[1]);
			foreach ($listparams as $litem) {
				$e = explode("=",$litem);
				$this->globalvalues[$e[0]] = $e[1];
				$varname = 'global_'.$e[0];
				$this->$varname = $e[1];
			}
			$list = explode("=>",$p[1]);
			$listparams = explode("|",$list[1]);
			foreach ($listparams as $litem) {
				$e = explode("=",$litem);
				$this->listvalues[$e[0]] = $e[1];
				$varname = 'global_'.$e[0];
				$this->$varname = $e[1];
			}
			$list = explode("=>",$p[2]);
			$editparams = explode("|",$list[1]);
			foreach ($editparams as $litem) {
				$e = explode("=",$litem);
				$this->editvalues[$e[0]] = $e[1];
				$varname = 'global_'.$e[0];
				$this->$varname = $e[1];
			}
				
			if (isset($this->globalvalues['type'])) $this->format = $this->globalvalues['type'];
				else if (strpos($this->dbinfo['Type'],'int(11)') !== false) $this->format = 'int';
				else if (strpos($this->dbinfo['Type'],'int(1)') !== false) $this->format = 'bool';
				else if (strpos($this->dbinfo['Type'],'tinyint(1)') !== false) $this->format = 'bool';
				else if (strpos($this->dbinfo['Type'],'int') !== false) $this->format = 'int';
				else if (strpos($this->dbinfo['Type'],'float') !== false) $this->format = 'float';
				else if (strpos($this->dbinfo['Type'],'char') !== false) $this->format = 'char';
				else if (strpos($this->dbinfo['Type'],'text') !== false) $this->format = 'text';
				else if (strpos($this->dbinfo['Type'],'datetime') !== false) $this->format = 'datetime';
				else if (strpos($this->dbinfo['Type'],'date') !== false) $this->format = 'date';
				else if (strpos($this->dbinfo['Type'],'date') !== false) $this->format = 'unknown';

			if (! isset($this->list_display)) $this->list_display = true;
			if (! isset($this->edit_display)) $this->edit_display = true;
			
			$this->name = $this->dbinfo['Field'];	
				
			
		}
		
		// Return html input field, ready to be integrated into 
		// $namesuffix is used to extend name to edit join table record 
		public function formatEditInput($value, $namesuffix = '')
		{
			if ($this->fieldsInfo[$field]->global_access == 'ro')
					$out = $value;
			else {
				switch ($this->format) {
					case 'int':
					case 'char':
					case 'float':
					case 'datetime':						
						$out = '<input  type="text" name="'.$this->name.$namesuffix.'" value="'.$value.'"/>';
						break;
					case 'bool':
						$out ='<select name="'.$this->name.$namesuffix.'">';
						if ($value == 0) $sel = 'selected';
							else $sel = '';
						$out.='<option value="0" '.$sel.'>false</option>';
						if ($value == 1) $sel = 'selected';
							else $sel = '';
						$out.='<option value="1" '.$sel.'>true</option>';
						$out.='</select>';
						break;
					case 'text':
						$out = '<textarea  name="'.$this->name.$namesuffix.'"/>'.$value.'</textarea>';
						break;
					default:
						$out='format not supported';
				}
			}
			return $out;
		}
		
		public function formatListInput($value)
		{
			switch ($this->format) {
				case 'int':
				case 'char':
				case 'float':
					$out = $value;
				case 'datetime':
					$out = substr($value,0,11);
					break;
				case 'bool':
					if ($value) $out = 'true';
						else $out = 'false';
					break;
				case 'char':
				case 'text':
					if (strlen($value)>$this->maxlistfieldlen)
						$out = substr($value,0,$this->maxlistfieldlen)."...";
					else $out = $value;
				break;
			}	
			return '<td>'.$out.'</td>';
		}
		
		// Return string to display in label tag
		public function formatLabel()
		{
			if ($this->global_label != '') return $this->global_label;
			return $this->name;
		}
		
		// Returns the tablename that the field refers to IF the field is a reference
		// Or an empty string 
		public function isReference()
		{
			$ref = '';
			if ($this->dbinfo['Type'] == 'int(11)')
				$ref = $this->name;
			if ($this->global_isReference == 'true') 
				// If isreference is true, then table name in Comment wins over field name
				if ($this->global_referenceTable != '') $ref = $this->global_referenceTable;
			// if type is int(11) but isreference is explicitely set to false, then Comment info wins
			if ($this->global_isReference == 'false') $ref = '';
			
			return $ref;
		}
		
	}
	
	
	// Main class to browse database
	//
	// Using this class, you can navigate through the database
	//
	// Some assumptions need to be true for this class to operate correclty:
	// 1- when references to other classes are needed, fieldname=tablename
	// 2- fields used as reference to other table records are of type int(11)
	//    (this asusmption can be overwritten using Comment field in database
	//     see class fieldFormat)
	// 3- primary key field name is 'id'
	// 4- Multi-language support is managed with one of the 2 following methods:
	//     * using an associated <table>desc table, as manged in Thelia
	//     * using a texte table using IAD plugin Texte
	// 5- Join tables are identified using the naming convention <table1><table2>
	//    where table1 is the main table
	//    and table2 is used to select choices to assign to a table1 record
	//
	// 6- Field names are assumed to be 'fairly' unique. For example:
	//        - text fields stored in texte table are not fields in main table
	//        - field names matching a table name indicate a reference to this table
	//          (this can be bypassed using global setting isreference=false)
	//
	class Dbbrowser extends PluginsThext{

		const TABLE="dbbrowser";
		const RECORDPAGE="dbbrowser";
		const DEFLISTCOLUMNS = 5;
		const LNKPAGES = 2;
		const MAXPERPAGE = 10;
		
		public $tables = array();
		
		function __construct($id = 0){
			parent::__construct(self::TABLE);

			// FIXME
			//$this->lang = $_SESSION['navig']->lang;
			$this->lang = 1;
			
			// Access to workaround functions
			$this->wa = new wa_functions($this);
			
			if($id > 0)
 			  $this->charger($id);

			$this->isJoinTable = false;
		}

		public function init(){
		}
		
		public function boucle($texte, $args){
		}
		
		public function action() {
			switch ($_REQUEST['action']) {
				case 'dbbrowser_showtables':
				case 'dbbrowser_showtable':
				case 'dbbrowser_editrecord':
					// we do nothing for these actions because it must be explicitely requested
					// with function showDb
					break;
				case 'dbbrowser_update':
					$this->updateRecord($_REQUEST['id'], $_REQUEST['table']);
					break;
				default :
					break;
			}
		}		
		
		function showDb()
		{
			$this->dbTables();
			switch ($_REQUEST['action']) {
				case 'dbbrowser_showtables':
					foreach ($this->tables as $t) {
						$this->out.='<a href="'.urlfond(dbbrowser).'&action=dbbrowser_showlist&table='.$t.'">'.$t.'</a><br>';
					}
					break ;
				case 'dbbrowser_showlist':
					if (isset($_REQUEST['start'])) $s = $_REQUEST['start'];
						else $s = 0;
					// Add link for item creation
					$this->out = '<a href="'.urlfond(dbbrowser).'&action=dbbrowser_editrecord&table='.
									$_REQUEST['table'].'&id=0">Ajout</a><br />';
					$this->out.= $this->showList($_REQUEST['table'],$s,10);
					break;
				case 'dbbrowser_showtable':
					$fields = $this->tableFields($_REQUEST['table']);
					print_r($fields);
					break;
				case 'dbbrowser_editrecord':
					$this->out = '<form action="'.urlfond("dbbrowser").'" method="post" name="edit" id="edit">';
					$this->editRecord($_REQUEST['id'], $_REQUEST['table']);
					$this->out.='<button type="submit" class="button">VALIDER</button>';
					$this->out.= '</form>';
					break;
				case 'dbbrowser_update':
					$this->out = '<form action="'.urlfond("dbbrowser").'" method="post" name="edit" id="edit">';
					$this->updateRecord($_REQUEST['id'], $_REQUEST['table']);
					$this->out.='<button type="submit" class="button">VALIDER</button>';
					$this->out.= '</form>';
					break;
				case 'dbbrowser_editjoinrecord':
					$this->out = '<form action="'.urlfond("dbbrowser").'" method="post" name="edit" id="edit">';
					$this->editJoinRecord($_REQUEST['id'], $_REQUEST['parenttable'], $_REQUEST['table']);
					$this->out.='<button type="submit" class="button">VALIDER</button>';
					$this->out.= '</form>';
						
					break;
				default :
					break;
			}
			return $this->out;
		}

		// Show list of $nb records from $table, $starting at record $start
		// Return: html array containing records, ready to be displayed 
		function showList($table,$start=0, $nb = 0)
		{			
			$this->fieldsInfo = $this->tableFields($table);
			$this->textfieldsInfo = $this->textFields($table);
			
			// FIXME
			if ($this->totalTextFields > self::DEFLISTCOLUMNS){
					// Should never happen
					ierror('internal error (too many text fields - unsupported) at '. __FILE__ . " " . __LINE__);
					exit;
			}
				
			$out = '<table>';
			$out.='<thead><tr>';
			// Build 1 column for edition: edit and delete
			$out.='<td></td>';
			// List potential text fields in the table header
			$out.=$this->rowTextFields($table);
				
			$i = 0;
			foreach ($this->fieldsInfo as $fitem)
			{
				if ($i++ >= (self::DEFLISTCOLUMNS - $this->totalTextFields))
					break;
				if ($fitem->name == 'id') continue;
				$out.='<td>'.$fitem->formatLabel().'</td>';
				// Builds some kind of cache table to quickly know which fields are references to 
				// other tables
				// This table contains either classname string or empty if field is not a reference
				$clname[$fitem->name] = $fitem->isReference();
			}
			$out.='</tr></thead>';
			
			// Get records from db and display them in html table body
			$out.='<tbody>';
			if ($nb == 0) $limit = ' LIMIT 18446744073709551610';
				else $limit = " LIMIT ".$nb;
			$offset = " OFFSET ".$start;
			$query = "SELECT * FROM ".$table.$limit.$offset;
			$result = mysql_query($query);			
			if (!$result) {
				// Should never happen
	   			ierror('internal error ('.$query.') at '. __FILE__ . " " . __LINE__);
				exit;
			}

			// For each record, show table line
			$n = mysql_num_rows($result);
			while ($row = mysql_fetch_assoc($result)) {
				$out.='<tr>';
				// Add edition links (edit, delete)
				$dellink = '<a href="'.urlfond(self::RECORDPAGE).'&action=dbbrowser_editrecord&table='.
								$table.'&id='.$row['id'].'"><img src="template/_gfx/db_recordremove.png"></a>';
				$edilink = '<a href="'.urlfond(self::RECORDPAGE).'&action=dbbrowser_editrecord&table='.
								$table.'&id='.$row['id'].'"><img src="template/_gfx/db_recordedit.png"></a>';
				$out.='<td>'.$edilink.$dellink.'</td>';
				// Show text fields
				$out.= $this->listTextFields($row['id'],$table);
				$i = 0;
				foreach ($this->fieldsInfo as $fitem){
					if ($i++ >= (self::DEFLISTCOLUMNS - $this->totalTextFields) )
						break;
						
					if ($fitem->name == 'id') continue;
					
					if ($clname[$fitem->name] != '')
					{
						$claz = $clname[$fitem->name];
						// create link to referenced table record
						$cl = new $claz();
						$cl->charger_id($row[$fitem->name]);
						$name = $this->getName($cl);
						// If we could not figure out the name, simply show the value
						if (! isset($name) || $name == '') $name = $row[$fitem->name];
						
						$link = '<a href="'.urlfond(self::RECORDPAGE).'&action=dbbrowser_editrecord&table='.
								strtolower($claz).'&id='.$row[$fitem->name].'">'.$name.'</a>';
						
						$out.='<td>'.$link.'</td>';
					}
					else
						$out.='<td>'.$row[$fitem->name].'</td>';
				}
				$out.='</tr>';
			}
			$out.='</tbody>';
			
			$out.='</table>';
			
			$out.= $this->pageNavig($table,$start,$nb,$this->totalRecords($table));
			
			return $out;
		}
		
		// If field is a reference to another table, then return class name
		// to use to manipulate this table
		// otherwise empty string is returned
		// OBSOLETE
		function getClassname($table, $field)
		{
			ierror('obsolete function getClassname');die();
			if (! isset($field))
			{
				// Should never happen
				ierror('internal error (field not set) at '. __FILE__ . " " . __LINE__);
				exit;
			}
				
			if (! isset($this->fieldsInfo))
			{
				// Should never happen
				ierror('internal error (fieldsInfo not set) at '. __FILE__ . " " . __LINE__);
				exit;
			}
			// If field type is not int(11) then field can not be a reference to another table
			if ($this->fieldsInfo[$field]->isReference() == '')
				return '';
			
			if ($this->isTable($field))
				$out = ucfirst($field);
			else
			{
				$locf = $table.'_fieldlookup';
				if (method_exists(ucfirst($table),'fieldlookup')) {
					// Specific processing exists in class for this field
					$clname = ucfirst($table);
					$c = new $clname();
					$out = ucfirst($c->fieldlookup($field)); 
				}
				else if (method_exists($this->wa,$locf))
				// Specific processing exists in this for this field
					$out = ucfirst($this->wa->$locf($field));
				else
					$out = '';
			}
			return $out;
		}
		
		// Retrieve list of tables from database
		function dbTables()
		{
			$db = Cnx::$db;
			$sql = "SHOW TABLES FROM $db";
			$result = mysql_query($sql);
			
					if (!$result) {
				// Should never happen
	   			ierror('internal error ('.$query.') at '. __FILE__ . " " . __LINE__);
	   			exit;
			}
			
			while ($row = mysql_fetch_row($result)) {
			   array_push($this->tables,$row[0]);
			}
										
		}
		
		function isTable($table)
		{
			$found = false;
			
			$db = Cnx::$db;
			$sql = "SHOW TABLES FROM $db LIKE '$table'";
			$result = mysql_query($sql);
				
			if (!$result) {
				// Should never happen
	   			ierror('internal error ('.$query.') at '. __FILE__ . " " . __LINE__);
	   			exit;
			}
						
			if (mysql_num_rows($result))
				$found = true;
			
			return $found;
		}
		
		// Retrieve list of fields and associated info from table
		function tableFields($table)
		{
			$f = array();
			$result = mysql_query("SHOW FULL COLUMNS FROM $table");
			// Array includes these fields:  Field      | Type     | Null | Key | Default | Extra | Comment
			if ($result && mysql_num_rows($result) > 0) {
				while ($row = mysql_fetch_assoc($result)) {
					// Update list
					$ff = new fieldFormat($table,$row);
					$f[$row['Field']]= $ff;
				}
			}
			return $f;
				
		}
		
		function textFields($table)
		{
			$f = array();
			$this->totalTextFields = 0;
				
			$d = $table.'desc';
			if ($this->isTable($d))
			{
				// Load field info from table comment
				// FIXME: ce code n'est pas fonctionnel !!! 
	   			ierror('non-functional code at '. __FILE__ . " " . __LINE__);
				$f = $this->tableFields($d);
			}
				
			$claz = ucfirst($table);
			if (class_exists($claz))
			{
				$clinst = new $claz();
				if (isset($clinst->bddvarstext))
					foreach($clinst->bddvarstext as $field){
						$fi = new fieldFormat($table);
						$fi->loadTexte($field);
						$f[$field] = $fi;
						$this->totalTextFields++;
					}
			}
			
			return $f;
		}
		
		
		function loadFields($id, $table)
		{
			$claz = ucfirst($table);
			if (class_exists($claz))
			{
				$c = new $claz();
				if ($id) $c->charger_id($id);
			}
			else
			{
				// Should never happen
	   			ierror('internal error (class does not exist) at '. __FILE__ . " " . __LINE__);
	   			exit;
			}
			return $c;
		}
		
		// Generate html to edit record fields included in $rec
		// If $tableformat=true, html output is formatted using a table line 
		// ignorelist can not be empty otherwise it matches when $refTable='' !!!
		function editRecordFields($rec, $tableformat, $ignorelist = array ('-'))
		{
			$out = '';
			
			// Prepare suffix if necessary - used for join table
			if ($this->isJoinTable)
			{
				if ($tableformat) {
					// we assume that if tableformat is true, we are building
					// the page to edit a join table record...
					// suffix is _<parentable_id>_<optionstable_id>
					$pt = $this->parenttable;
					$ot = $this->optionstable;
					$suffix='_'.$rec->$pt.'_'.$rec->$ot;
				
				}
				else $suffix = '';
				
			}
			
			// for each field - except 'id'
			// Generate html line to edit the field
			foreach ($rec->bddvars as $field)
			{
				$refTable = $this->fieldsInfo[$field]->isReference();
				
				// FIXME: if it happens that the table contains both a field name
				// that is a reference to a table listed but with a different name vs table name
				// AND also another 'direct' reference to the same table
				// Then both fields will be ignored whereas the intend is to remove only one probably
				// AAD example: table containing both client and supplier fields 
				if (in_array($field,$ignorelist) || in_array($refTable,$ignorelist)) continue;
				
				if ($field == 'id') {
					if (! $tableformat) $out.='<input type="hidden" name="id" value="'.$rec->id.'" />';
				}
				else {
					if ($tableformat) $out.='<td>';
						else
							$out.='<p><label for="'.$this->fieldsInfo[$field]->formatLabel().'">'.
									$this->fieldsInfo[$field]->formatLabel().'</label>';
					if ($refTable != '')
					{
						// reference to another table
						$claz = ucfirst($refTable);
						$cl = new $claz();
						$cl->charger_id($rec->$field);
						$name = $this->getName($cl);
						// If we could not figure out the name, simply show the value
						if (! isset($name)) $name = $rec->$field;
						if ($this->fieldsInfo[$field]->global_access != 'ro')
						{
							$dl = $this->dropList($cl);
							if ($tableformat) $link = '';
								else 
									$link = '<a href="'.urlfond(self::RECORDPAGE).
											'&action=dbbrowser_editrecord&table='.$field.'&id='.
											$rec->$field.'">'.$name.'</a>';
							$out.=$dl.$link;
						}
						else
							$out.= $name;
					}
					else {
						if (method_exists($rec,'dbb_'.$field)) {
							// Specific processing exists in class for this field
							$locf = 'dbb_'.$field;
							$out.= $rec->$locf('edit');
						}
						elseif (method_exists($this->wa,$locf)) {
							// Specific processing exists in this for this field
							$locf = $table.'_dbb_'.$field;
							$out.= $this->wa->$locf('edit');
						}
						else {
							$out.=$this->fieldsInfo[$field]->formatEditInput($rec->$field, $suffix);
						}
					}
					if ($tableformat) $out.='</td>';
						else $out.='</p>';
				}
			}
			return $out;
				
		}
		
		function editRecord($id, $table)
		{
			
			$this->fieldsInfo = $this->tableFields($table);
			$this->textfieldsInfo = $this->textFields($table);
			
			$this->out.='<input type="hidden" name="action" value="dbbrowser_update" />';
			$this->out.='<input type="hidden" name="table" value="'.$table.'" />';
			$rec = $this->loadFields($id, $table);
			$this->out.= $this->editTextFields($rec,$id,$table);
			$this->out.= $this->editRecordFields($rec,false /*tableformat*/);
			
			// Look for join tables
			// Try building a join table name by iterating over list of MySQL table names and check if table exists !
			foreach ($this->tables as $tablename) {
				$t = $table.$tablename;
				if ($this->isTable($t)) {
					$this->out.='<p><label">'.$t.'(nom a changer)</label>';
					$link = '<a href="'.urlfond(self::RECORDPAGE).'&action=dbbrowser_editjoinrecord&'.
							'parenttable='.$table.'&table='.$t.'&id='.$id.'">'.$t.'(nom a changer!)</a></p>';
					$this->out.=$link;
				}
			}
		}
		
		// Edit record for a table used as a join table
		// Reminder of join table: a table that enables to connect a record from a 'parent' table
		// to multiple records of another 'optns' table
		function editJoinRecord($id, $parenttable, $table)
		{

			$out = '';
				
			// Include table names in form so that we can process submitted form properly
			$out.='<input type="hidden" name="parenttable" value="'.$parenttable.'">';
			$out.='<input type="hidden" name="optionstable" value="'.$optionstable.'">';
			
			// Load join table fields info
			$this->fieldsInfo = $this->tableFields($table);
			$this->textfieldsInfo = $this->textFields($table);
							
			$optionstable = substr($table,strlen($parenttable),strlen($table));

			// Define class-wide variables so that we can reuse in other methods 
			// without overloading method parameters
			$this->isJoinTable = true;
			$this->parenttable = $parenttable;
			$this->optionstable = $optionstable;
			
			$query = "SELECT id FROM ".$optionstable;
			$result = mysql_query($query);
			if (!$result) {
				// Should never happen
   				ierror('internal error ('.$query.') at '. __FILE__ . " " . __LINE__);
				exit;
			}
			// For each selectable 'option', display checkbox and also
			// other fields of the join table
			$out.='<table>';
			$out.='<thead><tr>';
			$out.='<th>'.$optionstable.'</th>';
			$out.='<th>&nbsp;</th>'; // column for the checkbox
			// Build table header
			foreach ($this->fieldsInfo as $field) {
				// If field is a reference to a table, lookup the tablename
				// to compare with parent and options table names
				$refTable = $field->isReference();
				if ($refTable != '') $f = $refTable;
					else $f = $field->name;
				if ($f != 'id' &&
					$f != $parenttable &&
					$f != $optionstable)
					$out.='<th>'.$field->name.'</th>';
			}
			foreach ($this->textfieldsInfo as $field) {
				if ($field->nomchamp != 'id' &&
						$field->nomchamp != $parenttable &&
						$field->nomchamp != $optionstable)
					$out.='<th>'.$field->name.'</th>';
			}
			$out.='</tr></thead>';
				
			while ($row =  mysql_fetch_assoc($result))
			{
				$out.='<tr>';
				$optinst = new $optionstable($row['id']);
				$out.='<td><label>'.$this->getName($optinst).'</label></td>';
				
				// Check if this option is already selected
				$jt = new $table();
				$tlj = $table.'_load_join';
				if (! method_exists($jt,"load_join") && ! function_exists($tlj))
				{
					// should never happen...
	   				ierror('internal error (load_join method does not exist for class '.$parenttable.
	   						'| '. __FILE__ . " " . __LINE__);
					die('');
				}
				if (method_exists($jt,"load_join")) {
					if ($jt->load_join($parenttable,$id,$optionstable,$row['id']))
						$checked = 'checked';
					else
						$checked = '';
				}
				else {
					// FIXME: is this line correct ?
					if ($tlj($parenttable,$id,$optionstable,$row['id']))
							$checked = 'checked';
					else
							$checked = '';
				}
				
				// we manually load the references to parent and options tables in class instance
				// so that even if record does not exist
				// this info is available to encode it in input fields of html form
				$jt->$parenttable = $id;
				$jt->$optionstable = $row['id'];
				
				$out.='<td><input type="checkbox" name="'.$optionstable.'_'.$optinst->id.'" '.$checked.'>';
				$out.='</td>';
				
				$out.= $this->editRecordFields($jt, true /*tableformat*/, array($parenttable,$optionstable) /*ignorelist*/);
				$out.= $this->editTextFields($jt,$id,$table, true /*tableformat*/);
				
				$out.='</tr>';
			}
			$out.='</table>';
			$this->out.=$out;
		}
		
		// Returns a list of <td>'s containing the text fields associated with $table
		function rowTextFields($table)
		{
			$d = $table.'desc';
			if ($this->isTable($d))
			{
				$f = $this->tableFields($d);
				foreach ($f as $fitem)
					if ($fitem['Field'] != 'id' &&
							$fitem['Field'] != $table &&
							$fitem['Field'] != 'lang')
						$out.='<td>'.$fitem['Field'].'</td>';
				
			}

			// Retrieve list from text fields associated with $table and stored in table texte
			$clinst = null;
			$claz = ucfirst($table);
			if (class_exists($claz))
				$clinst = new $claz();
			if ($clinst && count($clinst->bddvarstext))
			{
				foreach ($clinst->bddvarstext as $field)
				$out.='<td>'.$field.'</td>';
			}
				
			return $out;
				
		}
		
		// Returns a list of <td>'s containing the text values associated with $id of $table
		// Note that ordering will and should be similar to rowTextFields 
		function listTextFields($id, $table)
		{
			$d = $table.'desc';
			if ($this->isTable($d))
			{
				//std Thelia table
				// Table includes a name field, use it to show records
				$query = "SELECT * FROM ".$d." WHERE ".$table."='".$id."' and lang='".$this->lang."'";
				$result = mysql_query($query);
				if (!$result) {
					// Should never happen
	   				ierror('internal error ('.$query.') at '. __FILE__ . " " . __LINE__);
					exit;
				}
				// Normally we have only 1 record but anyway...
				while ($row =  mysql_fetch_assoc($result))
				{
					foreach ($row as $field => $val)
						// Except id, lang and reference to main table fields, print everything
						if ($field != 'id' and $field != 'lang' and $field != $table) {
							//$out.='<td>'.$val.'</td>';
							$out.=$this->textfieldsInfo[$field]->formatListInput($val);
					}
				}
			
			}
			
			// Retrieve values from table texte
			$tinst = new $table();
			if (!empty($tinst->bddvarstext)) {
				foreach ($tinst->bddvarstext as $field) {
					$t = new Texte();
					if ($t->charger($table,$field,$id)) {
						$out.=$this->textfieldsInfo[$field]->formatListInput($t->description);
					}
					else $out.='<td>-</td>';
				}
			}
				
			
			return $out;
		}
		
		// Show text fields associated with a record
		// Text fields are stored in <table>desc table for Thelia tables
		// Text fields are stored in texte table for IAD
		// if tableformat, html code generated as a line of a table
		function editTextFields($rec, $id, $table, $tableformat = false)
		{
			
			// Prepare suffix if necessary
			if ($this->isJoinTable)
			{
				// we assume that if tableformat is true, we are building
				// the page to edit a join table record...
				// suffix is _<parentable_id>_<optionstable_id>
				$pt = $this->parenttable;
				$ot = $this->optionstable;
				$suffix='_'.$rec->$pt.'_'.$rec->$ot;			
			}
			else $suffix = '';
				
				
			$d = $table.'desc';
			if ($this->isTable($d))
			{
				//std Thelia table
				// Table includes a name field, use it to show records
				$query = "SELECT * FROM ".$d." WHERE ".$table."='".$id."' and lang='".$this->lang."'";
				$result = mysql_query($query);
				if (!$result) {
					// Should never happen
	   				ierror('internal error ('.$query.') at '. __FILE__ . " " . __LINE__);
	   				exit;
				}
				// Normally we have only 1 record but anyway...
				while ($row =  mysql_fetch_assoc($result))
				{
					foreach ($row as $field => $val)
						// Except id, lang and reference to main table fields, print everything
						if ($field != 'id' and $field != 'lang' and $field != $table) {
							if ($tableformat) $out.='<td>';
								else $out.='<p>';
							$out.='<label for="'.$this->textfieldsInfo[$field]->formatLabel().'">'.$this->textfieldsInfo[$field]->formatLabel().'</label>';
							$out.= $this->textfieldsInfo[$field]->formatEditInput($val, $suffix);
							if ($tableformat) $out.='</td>';
								else $out.='</p>';
							//$out.='<input  type="text" name="'.$field.'" value="'.$val.'"/>';
					}
				}
				
			}

			// Retrieve values from table texte
			$clinst = null;
			$claz = ucfirst($table);
			if (class_exists($claz))
				$clinst = new $claz();
			if ($clinst && count($clinst->bddvarstext))
			{
				// Texte table is used for this class, let's retrieve the fields
				$query = "SELECT nomchamp,description FROM texte WHERE nomtable='".$table."' and parent_id='".$id."'".
						" and lang='".$this->lang."'";
				$result = mysql_query($query);
				if (!$result) {
					// Should never happen
					ierror('internal error ('.$query.') at '. __FILE__ . " " . __LINE__);
					exit;
				}
				// Normally we have only 1 record but anyway...
				
				$i = 0;
				$idx = array();
				while ($row[$i] =  mysql_fetch_assoc($result))
				{
					$idx[$row[$i]['nomchamp']] = $i;
					$i++;
				}
				// We use bddvarstext to order fields in specific order
				foreach ($clinst->bddvarstext as $field) {
					if ($tableformat) $out.='<td>';
						else $out.='<p><label for="'.$this->textfieldsInfo[$field]->formatLabel().
									'">'.$this->textfieldsInfo[$field]->formatLabel().'</label>';
					$out.=$this->textfieldsInfo[$field]->formatEditInput($row[$idx[$field]]['description'], $suffix);
					if ($tableformat) $out.='</td>';
						else $out.='</p>';
				}
			}

			return $out;
		}
		
		// Update record in database, according to values sent by user
		function updateRecord($id, $table)
		{
			
			$rec = $this->loadFields($id, $table);
			foreach ($rec->bddvars as $field)
			{
				$val = isset($_REQUEST[$field]);
				$val2 = $_REQUEST[$field];
				if (isset($_REQUEST[$field]))
				{
					$locf = 'dbb_'.$field;
					$locf2 = $table.'_dbb_'.$field;
					if (method_exists($rec,$locf))
						// Specific processing exists in class for this field
						$rec->$field = $rec->$locf('update');
					elseif (method_exists($this->wa,$locf2))
						// Specific processing exists in this for this field
						$rec->$field = $this->wa->$locf2('update');
					else {
						$rec->$field = $_REQUEST[$field];
					}
				}
			}
			if ($rec->id) $rec->maj();
				else $rec->id = $rec->add();
				
			// Update text fields
			$this->updateTextRecord($id,$table);
				
		}
		
		function updateTextRecord($id, $table)
		{
			$tf = array();
			$d = $table.'desc';
			$tinst = new $table();
			if ($this->isTable($d))
			{
				//std Thelia table
				// Table includes a name field, use it to show records
				
				// Get list of fields
				$result = mysql_query("SHOW COLUMNS FROM $d");					
				if ($result && mysql_num_rows($result) > 0) {
					while ($row = mysql_fetch_assoc($result)) {
						array_push($tf, $row['Field']);
					}
				}
				
				if (! count($tf)) {
					 ierror('internal error (no text fields, maybe an error) at '. __FILE__ . " " . __LINE__);
					return ;
				}
				// Create list of fields (and values) to be added in database
				foreach ($tf as $field)
				{
					if (isset($_REQUEST[$field]) && 
							$field != 'id' && 
							$field != 'lang' &&
							$field != $table)
						$data[$field] = $_REQUEST[$field];
				}
				
				// Add specific fields
				$data['lang'] = $this->lang;
				$data[$table] = $id;
				
				// Update or create db record
				if ($this->isDescRecord($id,$table))
				{
					$cond[$table] = $id;
					$cond['lang'] = $this->lang;
					$this->updateSQL($d, $data, $cond);
				}
				else
					$this->insertSQL($d, $data);
			
			}
			else if (count($tinst->bddvarstext))
			{
				foreach ($tinst->bddvarstext as $t) {
					// Check if field already stored in db
					// then either update or add
					$tfield = new Texte();
					if ($tfield->charger($table,$t,$id)) {
						$tfield->description = $_REQUEST[$t];
						$tfield->maj();
					}				
					else if (isset($_REQUEST[$t])) {
						$tfield->description = $_REQUEST[$t];
						$tfield->nomtable = $table;
						$tfield->nomchamp = $t;
						$tfield->parent_id = $id;
						$tfield->add();
					}
						
				}
			}
			return $out;
				
		}
		
		// Checks if record referring to main table record $id exists in desc table
		function isDescRecord($id, $table)
		{
			$d = $table.'desc';
			$query = "SELECT COUNT(id) FROM ".$d." WHERE ".$table."='".$id."' and lang='".$this->lang."'";
           	$result = mysql_query($query);
			if (!$result) {
				// Should never happen
	   			ierror('internal error ('.$query.') at '. __FILE__ . " " . __LINE__);
	   			exit;
			}
           	           	$row =  mysql_fetch_row($result);
           	
           	return $row[0];
		}
		
		function dropList($claz)
		{
			$desc = $claz->table.'desc';
			$dropf = $claz->table.'_'.'dropListTable';
			
			$out = '<select name="'.$claz->table.'">';
            $out.= '<option value="">choisissez</option>';
            
            // Build list of options
            if (in_array('nom',$claz->bddvars))
            {
            	// Table includes a name field (nom), use it to show records
            	$query = "SELECT nom,id FROM ".$claz->table;
            	$result = mysql_query($query);
            	if (!$result) {
					// Should never happen
	   				ierror('internal error ('.$query.') at '. __FILE__ . " " . __LINE__);
   	   				exit;
				}
            	while ($row =  mysql_fetch_assoc($result)) {
            		if ($row['id'] == $claz->id) $sel = 'selected';
            			else $sel = ''; 
            		$out.= '<option value="'.$row['id'].'" '.$sel.'>'.$row['nom'].'</option>';
            	}
            	            	 
            }
			else if (method_exists($claz,'dropListTable')) {
				// Specific processing exists in class
				$out.= $claz->dropListTable();
			}
			else if (method_exists($this->wa,$dropf)) {
				// Specific processing exists in $this for this field
				$out.= $this->wa->$dropf($claz->id);
			}
            else if ($this->isTable($desc)) {
            	// Default behavior for Thelia tables, fetch name from desc table
				$out.= $this->dropListTable($desc,'titre',$claz->table,$claz->id);  
            }
            else
            	$out.= "not done yet";
            
            $out.= '</select>';
            return $out;
		}
		
		// Generate option list from $table using $field to show to user, and $idname for value
		// For Thelia desc tables, $idname is the tablename the desc refers to
		// For other tables, $idname is probably simply 'id'
		// Current is record to pre-select, if any
		function dropListTable($table, $field, $idname, $current)
		{
			// Retrieve titre from associated desc table (in case of standard Thelia table)
			if ($this->isTexteTable($table)) $l=" AND lang='".$this->lang."'";
			$query = "SELECT ".$field.",".$idname." FROM $table WHERE 1 $l";
			$result = mysql_query($query);
			if (!$result) {
				// Should never happen
	   			ierror('internal error ('.$query.') at '. __FILE__ . " " . __LINE__);
	   			exit;
			}
			while ($row =  mysql_fetch_assoc($result)) {
				if ($row[$idname] == $current) $sel = 'selected';
					else $sel = '';
				$out.= '<option value="'.$row[$idname].'" '.$sel.'>'.$row[$field].'</option>';
			}
			
			return $out;
				
		}
		// Return a string to show to 'represent the record referenced by class instance $claz
		// returned value is for exmaple used to create link to record referenced by another table
		function getName($claz)
		{
			// Check if desc table existsand retrieve titre if it does 
			// useful for standard Thelia classes
			$desc = $claz->table.'desc';
			$locf = $claz->table.'_name';
			if (method_exists($this->wa,$locf))
				$out = $this->wa->$locf($claz->id);
			else if (method_exists($claz, 'getName'))
				$out = $claz->getName();
			else if ($this->isTable($desc))
			{
				// Retrieve titre
				$n = $claz->table;
				$query = "SELECT titre FROM $desc WHERE $n='$claz->id' AND  lang='".$this->lang."'";
				$result = mysql_query($query);
				if (!$result) {
					// Should never happen
   					ierror('internal error ('.$query.') at '. __FILE__ . " " . __LINE__);
   					exit;
				}
				$row = mysql_fetch_row($result);
				$out = $row[0];			 // only retrieved field is titre			
			}
			else
			{
				// retrieve field titre from table texte in case it exists
				// Useful for IAD plugins
				$t = new Texte();
				$t->charger($claz->table,'titre',$claz->id, $this->lang);
				// Worst case, description is empty...
				$out = $t->description;
				
				// If field not in Texte table, last attempt, try field 'nom' in current table
				if ($out == '' && in_array('nom',$claz->bddvars))
					$out = $claz->nom;
			}
			return $out;
		}
		
		
		// Retrieves a field $fname from $clname, filtered by condition: $filter_field=$filter_val
		function getField($clname, $fname, $filter_field, $filter_val)
		{
			if ($filter_val == 0)
				// filter value is unset
				return '-';
			
			if ($this->isTexteTable($clname->table)) $l=" AND lang='".$this->lang."'";
			$query = "SELECT $fname FROM $clname WHERE $filter_field='$filter_val' ".$l;
			$result = mysql_query($query);
			if (!$result) {
				// Should never happen
	   			ierror('internal error ('.$query.') at '. __FILE__ . " " . __LINE__);
	   			exit;
			}
			$row = mysql_fetch_assoc($result);
			$out = $row[$fname];
			return $out;
		}

		// Returns true of $table stores language-specific information
		// Probably this information is then used to filter per language
		function isTexteTable($table)
		{
			// 2 cases: 
			//   - table name is <table>desc
			//   - table name is texte
			if ($table == 'texte') return true;
			if (substr($table,strlen($table)-strlen('desc'),strlen('desc')) == 'desc') return true;  
		}
		
		function totalRecords($table)
		{
			$query = "SELECT COUNT(id) FROM ".$table;
			$result = mysql_query($query);
			if (!$result) {
				// Should never happen
	   			ierror('internal error ('.$query.') at '. __FILE__ . " " . __LINE__);
	   			exit;
			}
			$row = mysql_fetch_row($result);
			return $row[0];
		}

		// Display links to navigate through pages to display records over multiples html pages
		// $table is tablename
		// $start is current starting record number
		// $nb is nb of entries to show in page
		// $total is total nb of entries in table 
		function pageNavig($table,$start,$nb,$total)
		{
			$showstart = $start - (self::LNKPAGES * self::MAXPERPAGE);
			if ($showstart<0) $showstart = 0;
			$showend = $start + (self::LNKPAGES * self::MAXPERPAGE);
			if ($showend > $total) $showend = $total;
						
			$i = $showstart;
			if ($showstart>0) 
				$out.='<a href="'.urlfond('dbbrowser').'&action=dbbrowser_showlist&table='.$table.'&start=0">first</a>&nbsp;';
			while ($i <= $showend)
			{
				if ($i == $start)
					$out.= $i.'&nbsp;';
				else 
					$out.='<a href="'.urlfond('dbbrowser').'&action=dbbrowser_showlist&table='.$table.'&start='.$i.'">'.
							$i.'</a>&nbsp;';
				$i+= $nb;
			}
			if ($showend<($total-$nb)) 
				$out.='<a href="'.urlfond('dbbrowser').'&action=dbbrowser_showlist&table='.$table.'&start='.($total-$nb).'">last</a>&nbsp;';
			
			$out.='&nbsp;['.$total.']';
			return $out;
		}
						
	}
?>