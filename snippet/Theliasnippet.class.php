<?php

	//
	// Classes to manage Thelia snippets
	// 
	
	class Theliaanchor extends BaseobjThext {
	
		const TABLE="theliaanchor";
		const ANCHORPREFIX="@@";
		const ANCHORSUFFIX="@@";
		const ANCHORID="_id";
		const ANCHORTYPE="_type";
		
		function __construct($id = ""){
			parent::__construct(self::TABLE);
	
			if($id != "")
				$this->charger_id($id);
			
		}
		
		public function init()
		{
			// Create table to associate payments types with service
			// Create SQL table to manage wallets
			$query = "
		CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`parent` int(11) NOT NULL DEFAULT '0',
		`anchor` varchar(128) DEFAULT NULL,
		`child` int(11) NOT NULL DEFAULT '0',
		PRIMARY KEY (`id`)
		) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
		";
	
			$res = $this->query($query);
	
		}
		
		public function charger_anchor($parent, $anchor) {
			$query = "select * from $this->table where parent=\"$parent\" and anchor=\"$anchor\"";
			return $this->getVars($query);
		}
	
	}
	
	
	class Theliatemplate extends BaseobjThext {
	
		const TABLE="theliatemplate";
		const HTMLPREFIX='html_';
		
		var $defaultanchors = array ();
		
		function __construct($id = ""){
			parent::__construct(self::TABLE);
	
			if($id != "")
				$this->charger_id($id);
							
			$this->html_tags = array ( "description", "keywords", "title");
			
			$v = new Variable();
			if($v->charger('defaultanchors')){
				$this->defaultanchors = explode(',',$v->valeur);
			}
			else
				$this->defaultanchors = array ();
			
			// Text fields configuration for Dbbrowser 
			$this->textDbbrowserConfig['template'] = "list=>display=0";
		}
	
		public function init()
		{
			// Create table to associate payments types with service
			// Create SQL table to manage wallets
			$query = "
		CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`title` varchar(256) DEFAULT '',
		`template` text,
		`iscacheable` tinyint(1) NOT NULL DEFAULT 0,
		`ishead` tinyint(1) NOT NULL DEFAULT 0,
		PRIMARY KEY (`id`)
		) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
		";
	
			$res = $this->query($query);
	
			foreach ($this->html_tags as $tag) {
				$v = new Variable();
				if(! $v->charger(Theliatemplate::HTMLPREFIX.$tag)){
					$v->nom = Theliatemplate::HTMLPREFIX.$tag;
					$v->valeur = "";
					$v->add();
				}
			}
			$v = new Variable();
			if(! $v->charger('defaultanchors')){
				$v->nom = 'defaultanchors';
				// By default we assume 3 specific snippets managed "transparently" to the user
				// Transparently means that user can not 'edit' these anchors
				// Unless superadmin of course...
				$v->valeur = 'html_header,page_bodyheader,page_footer';
				$v->add();
			}
		}

		public function charger_title($title) {
			$query = "SELECT * FROM ".$this->table." WHERE title='".$title."'";
			return $this->charger_query($query);
		}
		
		// Get list of anchors embedded in current snippet
		function anchors() {
			$stringtomatch = "/".Theliaanchor::ANCHORPREFIX."([0-9a-zA-Z_-\s]+)".Theliaanchor::ANCHORSUFFIX."/";
			preg_match_all($stringtomatch, $this->template,$matches);
			// return array that includes anchor (without prefix and suffix)
			
			return array_unique(array_filter($matches[1],filter_anchor));
		}
		
		// Support for dbbrowser plugin
		function getName() {
			return $this->title;
		}
		
		public function getNameFieldname() {
			// By default we return 'id'
			return 'title';
		}
		
	
	}
	
	// Filter out some anchors that need specific processing
	// SO far, we remove those anchors that relate to html tags
	function filter_anchor($anchor) {
		$tag = str_replace(Theliatemplate::HTMLPREFIX,'',$anchor);
		$dummy = new Theliatemplate();
		if (in_array($tag, $dummy->html_tags)) return false;
		else return true;
	}
	
	
	
	// HML code snippet managemenent
	// This plugin enables to stores html code fragments in the database
	// and retrieve them with standard Thelia loops to fill page content
	// 4 parameters help identify code fragments:
	// - refdiv: for fragments to use in a specific div
	// - reffond: for fragments to use with a specific fond
	// - reference: a string to identify fragment
	//
	// Using above parameters it is possible to define code fragments that can be 
	// used more or less widely in the web site. Examples:
	// - To use across site, only specify reference
	// - To use for specific divs, use div...
	// - To restrict to specific fond, use reffond...
	// - Of course you can use all 3 to freeze usage on a specific fond, in a specific div
	// - It is possible to store multiple contents targetting the same div and/or fond 
	//   by defining multiple references but with the same div/fond
	// 
	// NOTE: these are only naming conventions, how these "filters" are used depends on how 
	// they are used in the templates !
	// 
	Snippet::addSnippetType("thelia");	
	class Theliasnippet extends BaseObjThext {

		const TABLE="theliasnippet";
		
		function __construct( &$snippetptr = null){
			parent::__construct("theliasnippet");
			
			// Reference to main snippet
			$this->snippet = $snippetptr ; 
			
			// Ce tableau liste les champs a rechercher dans la table de gestions des champs "linguistiques"
			$this->bddvarstext = array ("description");
			
			// Text fields configuration for Dbbrowser 
			$this->textDbbrowserConfig['description'] = "list=>display=0";
			
			if ($this->snippet->snipid > 0)
				$this->charger_id($this->snippet->snipid);
		
		}
		
		function init($bdoor = false){
			
			// Create SQL table to manage htmlsnippets
			$query = "
			CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`theliatemplate` int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY (`id`)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
			";
			
			$result = $this->query($query);
			
			$h = new Theliaanchor();
			$h->init();

			$htmpl = new Theliatemplate();
			$htmpl->init();
				
		}
		
		// Create a new htmlsnippet record (and assciated texte entry)
		// $values is an array containing the values 
		function add() {
			$this->datecreation = date("Y-m-d H:i:s");
			$this->dateupdate = $this->datecreation;
			$this->id = parent::add();		
			return $this->id;		
		}
		
		// Update a record
		function maj() {
			$this->dateupdate = date("Y-m-d H:i:s");
			parent::maj();
		}
		
		function ismainhtml() {
			$t = new Theliatemplate($this->theliatemplate);
			return $t->ishead;
		}
		
		// Creates an anchor for each default anchor
		// WARNING: it is assumed that each default snippet to be "targetted" 
		// as a default anchor has a unique name
		function add_defaultanchor($a) {
			$ns = new Snippet();
			if (! $ns->charger_title($a))
				ierror('Default snippet '.$a.' should first be created '. __FILE__ . " " . __LINE__);
			
			$anchor = new Theliaanchor();
			// if anchor already exists, update it, otherwise create it
			if ($anchor->charger_anchor($this->snippet->id, $a)) {
				$anchor->child = $ns->id;
				$anchor->maj();
			}
			else {
				$anchor->child = $ns->id;
				$anchor->anchor = $a;
				$anchor->parent = $this->snippet->id;
				$anchor->add();
			}
		}
		
		// Update default anchor if they exist in template
		// This avoids requesting from the user to select snippets
		// When we statically want to select a snippet
		// We match string stored in site variable defaultanchors 
		// with template title
		function update_defaultanchors(&$template, &$input) {

			if (! $template->ishead)
					return ;
			
			$ta = $template->anchors();
			
			foreach ($template->defaultanchors as $anchor) {
				$a = new Theliaanchor();
				
				if (! in_array($anchor, $ta))
					continue;
					
				if (! $a->charger_anchor($this->snippet->id, $anchor)) {
					// New anchor, point to default snippet
					$s = new Snippet();
					if (! $s->charger_title($anchor)) {
						echo 'Default snippet '.$anchor.' should first be created '. __FILE__ . " " . __LINE__;
						continue;
					}
					$na = new Theliaanchor();
					$na->child = $s->id;
					$na->parent = $this->snippet->id;
					$na->anchor = $anchor;
					$na->add();
				}
			}
				
		}
		
		// Update default html tags (title, description, keywords)
		public function update_html_tags(&$template, $input) {
			
			if (! $template->ishead)
					return ;
			
			// For main html pages, we create the std html tag entries
			// regardless of template content
			// ie. it may happen that template can't exploit these tags
			// (if not referenced
			
			foreach ($template->html_tags as $anchor) {
								
				$a = new Theliaanchor();
				// We will update title in any case because it might have changed
				$in['title'] = 'Ancre '.$anchor.' ('.$input['title'].')';
				// If title, we reuse the title of the snippet
				if ($anchor == 'title')
					$in['description'] = $input[$anchor];
				else
					$in['description'] = $input[Theliatemplate::HTMLPREFIX.$anchor];
					
				if ($a->charger_anchor($this->snippet->id, Theliatemplate::HTMLPREFIX.$anchor)) {
					$s = new Snippet($a->child);
					$s->update($in);
				}
				else {
					// New anchor, point to default snippet
					$na = new Theliaanchor();
					$ns = new Snippet();
					$ns_title = $in['title'];
					$in['privilege_select'] = 10; // High enough to be invisible from standard content editors
					$na->child = $ns->create('text', $in, $ns_title);
					$na->parent = $this->snippet->id;
					$na->anchor = Theliatemplate::HTMLPREFIX.$anchor;
					$na->add();
				}
			}
				
		}
		
		// FIXME: this functions needs cleanup...
		public function update(&$input) {
			
			// Detect if main html page
			if (isset($input['template']) && strpos($input['template'],'<html') !== false)
				$ishead = 1;
			else
				$ishead = 0;
			
			if (! $this->id) {
				// Create new entry
				$t = new Theliatemplate();
				$t->template = $input['template'];
				$t->ishead = $ishead;
				$this->theliatemplate = $t->add();
				$this->id = $this->add();
				// Add anchors
				$anchors = $this->anchors($t->template);
				foreach ($anchors as $a) {
					$anchor = new Theliaanchor();
					if ($anchor->charger_anchor($this->snippet->id, $a))
						ierror('should never happen at '. __FILE__ . " " . __LINE__);
					$anchor->anchor = $a;
					// We assign id of "core" snippet as anchors refer to this table
					$anchor->parent = $this->snippet->id;
					// If request for new, create element
					if ($t->defaultanchors != null && in_array($a,$t->defaultanchors)) {
						// Anchor is managed with 'default' snippet
						$this->add_defaultanchor($a); 
						continue;
					}
					if (! is_numeric($input[$a])) {
						$ns = new Snippet();
						$ns_title = 'Ancre '.$a.' ('.$input['title'].')';
						$anchor->child = $ns->create($input[$a], $input, $ns_title);
						$anchor->add();
					}
					else {
						$anchor->child = $input[$a];
						$anchor->maj();
					}
				}
			}
			else {
				$t = new Theliatemplate($this->theliatemplate);
				if (! ($t->id>0))
					ierror('should never happen at '. __FILE__ . " " . __LINE__);
				// Template itself is only edited if privilege>=$this->snippet->superadminlevel
				if ($_SESSION['navig']->extclient->privilege >= $this->snippet->superadminlevel) {
					if (isset($input['template_id']) &&
							$input['template_id'] != $this->theliatemplate) {
						// User decided to use another template
						$this->theliatemplate = $input['template_id'];
						$this->maj();
					}
					else {
						// Update template
						$t->ishead = $ishead;
						$t->template = $input['template']; // if input[template] is null, maj will discard, ouf !
						$t->maj();
					}
				}
				// Update anchors
				$anchors = $t->anchors($t->template);
				foreach ($anchors as $a) {
					$anchor = new Theliaanchor();
					if ($t->defaultanchors != null && in_array($a,$t->defaultanchors)) {
						// Anchor is managed with 'default' snippet
						$this->add_defaultanchor($a);
						continue;
					}
					
					// If user selected 'unlink', we remove entry from theliaanchor
					if ($input[$a] == 'unlink') {
						$ta = new Theliaanchor();
						if (! $ta->charger_anchor($this->snippet->id,$a))
							// User selected unlink but there is no existing anchor, we skip
							continue;
						$ta->delete();
						continue;
					}
					
					if ($anchor->charger_anchor($this->snippet->id, $a)) {
						// If request for new, create element
						if (!isset($input[$a]))
							// FIXME maybe we should d something smarter...
							continue;
						if (! is_numeric($input[$a])) {
							// New snippet
							$ns = new Snippet();
							$ns_title = 'Ancre '.$a.' ('.$input['title'].')';
							$anchor->child = $ns->create($input[$a], $input, $ns_title);
							$anchor->maj();
						}
						else {
							$anchor->child = $input[$a];
							$anchor->maj();
						}
					}
					else {
						// New anchor, potentially new snippet
						$na = new Theliaanchor();
						$ns = new Snippet();
						if (!isset($input[$a]))
							// FIXME maybe we shuold d something smarter...
							continue;
						if (! is_numeric($input[$a])) {
							// New snippet
							$ns_title = 'Ancre '.$a.' ('.$input['title'].')';
							$na->child = $ns->create($input[$a], $input, $ns_title);
						}
						else {
							$na->child = $input[$a];
						}
						$na->parent = $this->snippet->id;
						$na->anchor = $a;
						$na->add();
					}
				}
			}

			// Create or update the html standard snippets if $ishead=true
			$this->update_html_tags($t, $input);				
			$this->update_defaultanchors($t, $input);
			
			// If snippet actually corresponds to one of the default snippet
			// we propagate the (potential) new name to all anchors
			// We rely on the fact that snippet->title is updated with new
			// value only after this call returns, so that $this->snippet->title
			// still holds the 'previous' value currently
			if (in_array($this->snippet->title,$t->defaultanchors)) {
				// Update all anchors
				$query = "SELECT * FROM theliaanchor WHERE anchor='".$this->snippet->title."'";
				$l = loadItems('Theliaanchor',$query);
				foreach ($l as $a) {
					$a->anchor = $input['title'];
					$a->maj();
				}
				
				// Update all anchors in theliatemplate
				$query = "SELECT * FROM theliatemplate";
				$l = loadItems('Theliatemplate',$query);
				foreach ($l as $t) {
					$before = Theliaanchor::ANCHORPREFIX.$this->snippet->title.Theliaanchor::ANCHORSUFFIX;;
					$after = Theliaanchor::ANCHORPREFIX.$input['title'].Theliaanchor::ANCHORSUFFIX;;
					$t->template = str_replace($before, $after,$t->template);
					$t->maj();
				}
				
				// Update the variable containing the list of default anchors
				$v = new Variable();
				if (! $v->charger('defaultanchors'))
					ierror('defaultanchors variable can not be found '. __FILE__ . " " . __LINE__);
				$v->valeur = str_replace($this->snippet->title,$input['title'],$v->valeur);
				$v->maj();
			}
			
		}
		
		// Removes current snippet from cache AND
		// + recursively all snippets that depend on it
		public function cleanCache($snippet) {
			$query = "select * from theliaanchor where child='".$snippet->id."'";
			$result = $this->query($query);
				
			if ($result) {
					
				$nbres = $this->num_rows($result);
					
				if ($nbres > 0) {
						
					while( $row = $this->fetch_object($result)){
						$p = new Snippet($row->parent);
						$p->cleanCache();
					}
					
				}
				
			}					
			
		}
		
		public function create(&$input) {
			$tt = new Theliatemplate();
			if (isset($input['theliatemplate']))
				$tt->charger_id($input['theliatemplate']);
			else
				$tt->id = $tt->add();
			$this->theliatemplate = $tt->id;
			$this->id = $this->add();
			$this->update_defaultanchors($tt, $input);
			$this->update_html_tags($tt,$input);
			return $this->id;
		}
		
		// Check if snippet is cacheable and iterates through its children
		// as non-cacheable snippet would propagate its property "upwards"
		public function iscacheable() {
						
			$t = new Theliatemplate();
			$t->charger_id($this->theliatemplate);
			
			if (! $t->iscacheable) return false;
			
			// OK, snippet based on a cacheable template, but let's check
			// its children...
			
			$anchors = $t->anchors();
			
			// For each anchor in the template, check what is the snippet type
			// If of type Thelia, simply bail out, and indicate not cacheable
			foreach ($anchors as $a) {
				$ta = new Theliaanchor();
				$ta->charger_anchor($this->snippet->id,$a);
				// If child exists, check that it is cacheable
				$s = new Snippet();
				if ($s->charger_id($ta->child))
					if (! $s->iscacheable()) return false;
			}
			
			return true; 
		}
		
		
		public function boucle($texte, $args){
			$search ="";
			$res=$out="";		
			
			
			// récupération des arguments et préparation de la requète
			foreach ($this->bddvars as $key => $val){
					$$val = lireTag($args, "$val");
					if (isset($$val))
						$search .= " and $val=\"". $$val . "\"";
			}
			
			$query = "select * from ". $this->table . " where 1 $search";
			
			$result = $this->query($query);
			
			if ($result) {
			
				$nbres = $this->num_rows($result);
			
				if ($nbres > 0) {
			
					while( $row = $this->fetch_object($result)){
			
						$res = $texte;
						$curid = $row->id;
	
						// Fetch and process template
						$t = new Theliatemplate($row->theliatemplate);
						$anchors = $this->anchors($t->template);
						// Replace anchors
						foreach ($anchors as $a) {
							$anchor = new Theliaanchor();
							// Reset values and load anchor, template
							// if no anchor exist, the default defined values will be used
							$anchor->child = 0;
							$anchor->charger_anchor($this->snippet->id, $a);
							$str = Theliaanchor::ANCHORPREFIX.$a->anchor.Theliaanchor::ANCHORID.Theliaanchor::ANCHORSUFFIX;
							$t->template = str_replace($str, $a->child, $t->template);									
							$str = Theliaanchor::ANCHORPREFIX.$a->anchor.Theliaanchor::ANCHORTYPE.Theliaanchor::ANCHORSUFFIX;
							$t->template = str_replace($str, strtoupper($a->child_sniptype), $t->template);									
						}
						$this->thelia_parse($t->template);
						$res = $edit.str_replace('#DESCRIPTION', $t->template, $res);				
						$out.=$res;
					}
				}			
			}
			
			return $res;			
		}
				
		function show() {
			$res = '';
			// Fetch and process template
			$t = new Theliatemplate($this->theliatemplate);
			$anchors = $t->anchors($t->template);
			$res = $t->template;
			// Replace anchors
			foreach ($anchors as $a) {
				$anchor = new Theliaanchor();
				// Reset values and load anchor, template
				// if no anchor exist, the default defined values will be used
				$anchor->child = 0;
				$anchor->charger_anchor($this->snippet->id, $a);
				$str = Theliaanchor::ANCHORPREFIX.$a.Theliaanchor::ANCHORSUFFIX;
				$res= str_replace($str, $anchor->child, $res);
			}
			// Replace html std tags if relevant
			if ($t->ishead) {
				foreach ($t->html_tags as $anchor) {
					$a = new Theliaanchor();
					if ($a->charger_anchor($this->snippet->id, Theliatemplate::HTMLPREFIX.$anchor)) {
						$str = Theliaanchor::ANCHORPREFIX.Theliatemplate::HTMLPREFIX.$anchor.Theliaanchor::ANCHORSUFFIX;
						$res= str_replace($str, $a->child, $res);
					}
				}
			}
			$this->thelia_parse($res);
			$res = $edit.str_replace('#DESCRIPTION', $res, $res);
			return $res;
				
		}		
		
		// Use Thelia parser t prcess html snippet
		// Note no need to include all php files
		// related t parser as we assume that this class
		// is called from Thelia engine and therefore all
		// relevant files are aready included
		function thelia_parse(&$res) {
			
			$parseur = new Parseur();

			// fonctions à éxecuter avant les inclusions
			ActionsModules::instance()->appel_module("inclusion");
		
			// inclusion
			$parseur->inclusion($res);
		
			// inclusions des plugins
			// FG: actions already executed in moteur.php...
			//ActionsModules::instance()->appel_module("action");
		
			$res = $parseur->analyse($res);
		
			ActionsModules::instance()->appel_module("analyse");
		
		    Filtres::exec($res);
		
			$res = $parseur->post($res);
		
			// inclusions des plugins filtres
			ActionsModules::instance()->appel_module("post");
		
			Tlog::ecrire($res);
		
			// Résultat envoyé au navigateur
			$res = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $res);
		
			// FG add customization of html file with dedicated function calls embedded in 
			if (class_exists("ajoutPhp")){
				$aPhp = new ajoutPhp();
				$aPhp->parse($res,$fond);
			}
						
		}
				
		// Edit the part specific to this snippet type
		// Called from Snippet::edit
		public function edit(&$html, &$js) {
			
			// Fetch and process template
			$t = new Theliatemplate($this->theliatemplate);
			$anchors = $t->anchors();				
				
			$templ = new Theliatemplate($this->theliatemplate);
			$desc = $templ->template;
			$id = $this->id;

			// Edit title, description and keywords if main html page
			if ($templ->ishead) {
				// Edit html std tags (in header, for SEO)
				$dummy = new Theliatemplate();
				foreach ($dummy->html_tags as $tag) {
					// For title, we reuse snippet title...
					if ($tag == 'title') continue;
					$ta = new Theliaanchor();
					if ($ta->charger_anchor($this->snippet->id,Theliatemplate::HTMLPREFIX.$tag)) {
						$s = new Snippet();
						if ($s->charger_id($ta->child)) {
							$s->setshowConfig('edit=0');
							$html.='<p><label for="'.Theliatemplate::HTMLPREFIX.$tag.'"><h4>Champ '.$tag.':</h4></label><input type="text" name="'.Theliatemplate::HTMLPREFIX.$tag.'" value="'.$s->show().'" /></p>';
						}
					}
				}
				
			}
			
			// Edit the template 	
			if ($_SESSION['navig']->extclient->privilege >= $this->snippet->superadminlevel) {
				// Edit template		
				$html.=  '<p>Template '.$templ->title.':<br/><textarea  id="theliasnippet_edit_description" class="noEditor" cols="60" rows="20" name="template">'.
									$desc.'</textarea></p>';
				// Give possibility to switch to another template
				$html.= '<p> Choisir un autre template: <select name="template_id">';
				// Get list of available templates and display
				$query = "SELECT * FROM theliatemplate";
				$result = mysql_query($query);
				if (!$result) {
					// Should never happen
					ierror('internal error ('.$query.') at '. __FILE__ . " " . __LINE__);
					exit;
				}
				while ($row =  mysql_fetch_assoc($result)) {
					if ($row['id'] == $templ->id) $sel = 'selected';
					else $sel = '';
					$html.= '<option value="'.$row['id'].'" '.$sel.'>'.$row['title'].'</option>';
				}
				$html.= '</select>';
				
			}
			// Edit all anchors
			if (count($anchors)) {
				$html.='<h4>Edition des ancres du template</h4>';
				$html.=  <<<EOD
						<script type="text/javascript" src="lib/DataTables-1.9.1/media/js/jquery.dataTables.min.js"></script>
						<script>
							$(function() {
								$( "#listanchors" ).tabs( {
								"show": function(event, ui) {
									var oTable = $('div.dataTables_scrollBody>table.display', ui.panel).dataTable();
									if ( oTable.length > 0 ) {
										oTable.fnAdjustColumnSizing();
									}
								}
												});
							});
							</script>
							<style type="text/css" title="currentStyle">
									@import "lib/DataTables-1.9.1/media/css/demo_page.css"; 
									@import "lib/DataTables-1.9.1/media/css/demo_table.css";
						</style>
						<div id="listanchors">
EOD;
				$html.='<ul>';
				$i = 0;
				foreach ($anchors as $a) {
					if ($t->defaultanchors != null && in_array($a,$t->defaultanchors))
						continue;
					else
						$html.='<li><a href="#listanchors-'.$i++.'">'.$a.'</a></li>';
				}
				$html.='</ul>';
				$query = "select * from snippet";
				$snippets = loadItems('snippet', $query);
				$i = 0;
				foreach ($anchors as $a) {
					
					$anchor = new Theliaanchor();
					
					if ($t->defaultanchors != null && in_array($a,$t->defaultanchors)) {
						continue;
					}
					
					// Reset values and load anchor, template
					// if no anchor exist, the default defined values will be used
					$anchor->child = 0;
					$anchor->charger_anchor($this->snippet->id, $a);
						
					//$html.='<h4>'.$a.'</h4>';
					$html.='<div id="listanchors-'.$i++.'">';
					$html.='<table id="table_'.str_replace(" ","",$a).'">';
					$html.='	<thead><tr><th>--</th><th>Type</th><th>Liste</th></tr></thead><tbody>';
					// Include possibility to remove (current) anchor
					$html.='<tr><td><input type="radio" name="'.$a.'" value="unlink"></td><td></td><td>Enlever</td></tr>'.chr(13);
					foreach (Snippet::$snippettypes as $type) {
						$html.='<tr><td><input type="radio" name="'.$a.'" value="'.$type.'"></td><td>'.
								$type.'</td><td>Nouveau</td></tr>'.chr(13);
					}
					$list = '';
					foreach ($snippets as $s) {
						if ($s->privilege_select > $_SESSION['navig']->extclient->privilege) continue;
						if ($s->id == $anchor->child) 
							$html.='<tr><td><input type="radio" name="'.$a.'" value="'.$s->id.'" checked></td><td>'.
									$s->sniptype.'</td><td>'.$s->title.'</td></tr>'.chr(13);
						else $checked = '';
							$list.='<tr><td><input type="radio" name="'.$a.'" value="'.$s->id.'"></td><td>'.
									$s->sniptype.'</td><td>'.$s->title.'</td></tr>'.chr(13);
					}
					$html.=$list;
					$html.='</tbody></table></div><div style="clear:both;"></div>'.chr(13);
					$html.='<script type="text/javascript">$().ready(function() {$(\'#table_'.$a.'\').dataTable({		
							"sScrollY": "200px",
							"bJQueryUI": true, 
							"bPaginate": false,
							"aoColumnDefs": [ 
								{ "sWidth": "20px", "aTargets": [ -2 ] }
							] 
							});});</script>'.chr(13);
				}
			$html.='</div>';
				
			}
			
			return ;
		}
		
		//
		// Support functions for dbbrowser plugin
		//
		
		public function dbbrowser_dropListTable() {
			// Table includes a name field (nom), use it to show records
			$query = "SELECT ".$this->table.".id,title FROM ".$this->table.",snippet WHERE ".
						$this->table.".id=snippet.snipid AND snippet.sniptype='thelia'";
			$result = mysql_query($query);
			if (!$result) {
				// Should never happen
				ierror('internal error ('.$query.') at '. __FILE__ . " " . __LINE__);
				exit;
			}
			while ($row =  mysql_fetch_assoc($result)) {
				if ($row['id'] == $this->id) $sel = 'selected';
				else $sel = '';
				$out.= '<option value="'.$row['id'].'" '.$sel.'>'.$row['title'].'</option>';
			}
			 return $out;
		}		
		
	}

?>
