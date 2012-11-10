<?php

	require_once(realpath(dirname(__FILE__)) . "/../Pluginsthext/PluginsThext.class.php");
	
	loadPlugin('texte');	
		
	// Global variable to store snippet id in case of url rewrite
	$id_snippet;
	
	//
	// Plugin to manage html/javascript code fragments
	// Fragments (snippets) can be assembled to create complete html pages
	// Snippets are 'typed' to enable customized fragments to be generated
	// and managed in different ways.
	// For example:
	// - Text: simplest snippet managing raw text
	// - Html: derived from Text to store html code
	// - Thelia: manage Thelia template (in particular enabling recursion)
	// - Camera: provides a custom admin interface to control CameraImage jQuery plugin
	//
	// Note: most templates will assume that jQuery is already loaded on page
	//
	class Snippet extends PluginsThext {
		const TABLE="snippet";
		
		// Content of snippet
		// - either dynamically generated
		// - or loaded from cache
		var $content = ''; 
		
		// Cache activation
		var $usecache;
		
		// Configuration parameters for showing snippet
		// Passed as parameter of Thelia loop
		var $showConfig = array ("edit" => 1);
		
		// List of available snippet types
		// When a new plugin managing a snippet is loaded, it must declare itself
		// by calling static function addSnippetType()
		// Snippet name when concatenated with 'snippet', must be the class name of the plugin
		// Example: snippet name is 'Html', then class is Htmlsnippet
		static $snippettypes = array();
		
		
		function __construct($id = 0){
			parent::__construct("snippet");
		
			$this->sniptype = $sniptype;
			// Path to cached files
			$this->cachepath = realpath(dirname(__FILE__)).'/../../cache/snippet';
			
			if($id != "")
				$this->charger_id($id);

			$v = new Variable();
			if($v->charger('superadminlevel')){
				$this->superadminlevel = $v->valeur;
			}
				// Default value
				$this->superadminlevel = 5;
							
			if($v->charger('snippeturlrewrite'))
				$this->snippeturlrewrite = $v->valeur;
			else
				$this->snippeturlrewrite = false;				
				
			if($v->charger('usesnippetcache'))
				$this->usecache = $v->valeur;
			else
				$this->usecache = false;
		}
		
		public function charger_title($title) {
			$query = 'select * from snippet where title=\''.$title.'\'';
			return $this->charger_query($query);
		}
		
		public function init($bdoor = false) {
			if ( ! $bdoor )
				$this->ajout_desc("Snippet", "Manage snippets", "", 1, 1);
				
			// Create SQL table to manage htmlsnippets
			$query = "
				CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`title` varchar(128) DEFAULT NULL,
				`sniptype` varchar(128) DEFAULT NULL,
				`snipid` int(11) NOT NULL DEFAULT 0,
				`privilege_edit` int(4) DEFAULT 1,
				`privilege_select` int(1) DEFAULT 1,
				`cacheable` int(1) DEFAULT 1,
				`datecreation` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				`dateupdate` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY (`id`)
				) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
				";
				
			$result = $this->query($query);
			
			foreach (self::$snippettypes as $type) {
				$claz = ucfirst(strtolower($type)).'snippet';
				$clinst = new $claz($this);
				$clinst->init();
			}
		
			$v = new Variable();
			if(! $v->charger('usesnippetcache')){
				$v->nom = Theliatemplate::HTMLPREFIX.$tag;
				$v->valeur = 1;
				$v->add();
			}
			
			// Create cache dir if needed
			if (!is_dir($this->cachepath)) {
				mkdir($this->cachepath);
			}
		
		}
		
		public static function addSnippetType($snippet) {
			if (! in_array($snippet, self::$snippettypes))
				array_push(self::$snippettypes, $snippet);
		}
		
		public function formheader($id) {

			$urlcourante = $_SERVER["HTTP_REFERER"];
			$editheader = <<< EOD
		<h2>Edition du snippet</h2>
		<form action="$urlcourante" method="post" name="snippet_edit_form" id="snippet_edit_form">
		<input type="hidden" id="$id"  readonly="readonly" size="20" name="id" value="$id" />
		<input type="hidden" name="action" value="snippet_update" />
		<p><label for="title"><h4>Titre du snippet</h4></label><input type="text" name="title" size="80" value="$this->title" /></p>
EOD;
			return $editheader;		
					
		}

		public function getSnippetjavascript() {
			 $tinymce_javascript = <<<EOD
		<script type="text/javascript">// <![CDATA[
				$(document).ready(function() {
				    $("#save").button();
				    $("#cancel").button();
				});
		// ]]>
		</script>
EOD;
			 return $tinymce_javascript;
					
		}
		
		public function formtail() {
			$form_str =	<<<EOD
			<p><a href="javascript:void(0)" id="save" onclick="snippet_update(true)">save</a>
			<a href="javascript:void(0)" id="cancel" onclick="snippet_update(false)">cancel</a></p>
			</form>
EOD;
		return $form_str;
		}
		
		public function show($config = '') {
			
			// Check for privilege
			if ($_SESSION['navig']->connecte && $_SESSION['navig']->extclient->privilege < $this->privilege_view)
				return '';
			
			// Enable edition if privilege high enough
			if ($_SESSION['navig']->connecte && 
				$_SESSION['navig']->extclient->privilege >= $this->privilege_edit &&
				$this->showConfig['edit']) {
				$edit='<div><a href="javascript:void(0)" onclick="snippet_edit('.$this->id.');"><img src="mainslibres/images/solution.gif" width="10px"></a></div>';
			}
			else
				$edit = '';
			
			
			$claz = ucfirst($this->sniptype).'snippet';
			$clinst = new $claz($this);
				
			// Cache management - only when no privilege (otherwise content is too dynamic
			if ( ! $this->loadCache()) {
				$this->content = $clinst->show();
				$this->saveCache();
			}
			$this->content = $edit.$this->content;
			return $this->content;				
		}
		
		public function setshowConfig($config) {
			$conf = explode(',',$config);
			foreach ($conf as $param) {
				$p = explode('=',$param);
				$this->showConfig[$p[0]] = $p[1];
			}
		}
		
		public function boucle($texte, $args){
			$search ="";
				
			$res=$out="";
				
			// Legacy, we discard and issue a warning
			$refdiv = lireTag($args,"refdiv");
			if ($refdiv != '') {
				echo 'Legacy htmlsnippet - please update ('.$args.') '. __FILE__ . " " . __LINE__;
				return '';
			}

			// Thelia framework assumes a single plugin class instance
			// In our case for snippets, we rely on recursion (for Theliasnippet
			// for example), so we need a workaround...
			$s = new Snippet();
			
			$curid = lireTag($args,"id");
			
			// 0 can' exist s we return empty string
			if (isset($curid) && $curid == 0) return '';
			
			if (isset($curid)) $search = ' AND id=\''.$curid.'\'';
			$config = lireTag($args, "config");
			$htmlfilter = lireTag($args, "filter") == 'mainhtml' ? true : false;
						
			$query = "SELECT * FROM snippet WHERE 1 ".$search;
			$snippets = loadItems('Snippet',$query);
			
			if (! count($snippets)) {echo $query.'<br>'.$texte.'<br>'.$args;
				ierror('Snippet ('.$curid.') unavailable '. __FILE__ . " " . __LINE__);
			}
			foreach ($snippets as $s) {
				$s->setshowConfig($config);
				
				// HACK - but still fairly generic and only solution i found... !!!
				// If we are processing a text description that and request to replace std default
				// html fields, then we add them...
				if ($s->sniptype == 'text' &&
						strpos($texte,'HTML_') !== false) {
					$t = new Theliatemplate();
					foreach ($t->html_tags as $tag) {
						// Load default values
						$v = new Variable();
						$v->charger(Theliatemplate::HTMLPREFIX.$tag);
						$texte = str_replace('#'.strtoupper(Theliatemplate::HTMLPREFIX.$tag),$v->valeur,$texte);
					}
				}

				$content = $s->show($config);
				// If we only show snippets including main hmtl pages
				// Skip if necessary
				if ($htmlfilter && ! $s->ismainhtml($content)) continue;
				
				$out = str_replace('#REWRITEURL',$s->getUrl(),$texte);
				$out = str_replace('#DESCRIPTION',$content,$out);
				$res.=$out;
			}
			return $res;
				
		}
		
		// Check if snippet is a "main" html page
		// "main" means that the snippet is intended to be displayed as
		// a valid html page (not only as a fragment)
		function ismainhtml() {
			// If method exist to check, let's use it
			// Otherwise simply check for html tag
			// FIXME: we should instantiate $clinst once only in this class
			// then reuse across methods rather than create it every time
			$claz = ucfirst($this->sniptype).'snippet';
			$clinst = new $claz($this);
			if (method_exists($clinst,'ismainhtml'))
				return $clinst->ismainhtml();	
			else
				return false;
		}
		
		function loadCache() {
			$loaded = false;
			
			if ($this->usecache && $this->cacheable) {
				$abspath = $this->cachepath.'/'.$this->id.'.html';
				if (( $this->usecache && $this->cacheable &&
					file_exists($abspath)) &&
					((! $_SESSION['navig']->connecte) ||
					($_SESSION['navig']->connecte) && $_SESSION['navig']->extclient->privilege == 0)) {
						$this->content = file_get_contents($abspath);
						$loaded = true;
				}
			}
			return $loaded;
		}
		
		// Save content to file
		function saveCache() {
			if (! $this->ismainhtml())
				return ;
			
			$abspath = $this->cachepath.'/'.$this->id.'.html';
			if ($this->usecache && $this->cacheable &&
				(! file_exists($abspath)) &&
				((! $_SESSION['navig']->connecte) ||
				( $_SESSION['navig']->connecte) && $_SESSION['navig']->extclient->privilege == 0))
				$res = file_put_contents($abspath,$this->content);
		}
		
		// Remove cached snippet if it exists
		function cleanCache() {

			if (! $this->cacheable)
				return ;
			
			// We "broadcast to all snippet types that a snippet is
			// being removed so that they potentially can update the cache
			// accordingly (e.g. Thelia snippet)
			foreach (self::$snippettypes as $type) {
				// If a method is provided with the type to clean the cache,
				// First call it (e.g. Thelia type)
				$claz = ucfirst($type).'snippet';
				$clinst = new $claz($this);	
				if (method_exists($clinst,'cleanCache')) {
					$clinst->cleanCache($this);
				}
			}
				
			$abspath = $this->cachepath.'/'.$this->id.'.html';
			if (file_exists($abspath)) unlink($abspath);
		}
		
		// Check of snippet is cacheable
		// relying on specific snippet type call iscacheable
		// if call is not available, we assume that snippet is not cacheable
		// to be on the safe side
		public function iscacheable() {
			$claz = $this->sniptype.'snippet';
			$clinst = new $claz($this);
			if (method_exists($clinst, iscacheable)) {
				return $clinst->iscacheable();
			}
			else return false;
		}
		
		
		public function edit() {
			
			if ($_REQUEST['refdiv'] != '') {
				$h = new Htmlsnippet($this);
				return $h->edit_legacy($texte,$args);
			}	

			if (! $this->charger_id($_REQUEST['id'])) return '';
			
			$claz = ucfirst($this->sniptype).'snippet';
			$clinst = new $claz($this);
				
			// FIXME: privilege_view not delcared in this class
			if ( ($_SESSION['navig']->connecte && $_SESSION['navig']->extclient->privilege < $this->privilege_view) ||
					((! $_SESSION['navig']->connecte) && $this->privilege_view>0) )
				return '';
				
			//
			// Generate html form:
			//
			$items['javascript'] = '';
			$items['html'] = $this->formheader($this->id);
			
			// Insert type-specific edition
			$clinst->edit($items['html'], $items['javascript']);
			
			$items['html'].= $this->formtail();
			if ($this->snippeturlrewrite)
				$link = '?fond=snippet&id_snippet='.$this->id;
			else
				$link = '?fond=snippet&id_snippet='.$this->id;
			$items['html'].='<hr><p> Pour information, le lien intra-site à utiliser: '.$this->getUrl();
			$items['javascript'].= $this->getSnippetjavascript();
				
			return json_encode($items);
			//return $items['html'];
				
		}
		
		public function update(&$input) {
			if (! $this->id)
				if (! $this->charger_id($input['id'])) {
					// create new entry
					return '';
			}

			$claz = ucfirst($this->sniptype).'snippet';
			$clinst = new $claz($this);	
			$clinst->charger_id($this->snipid);
			$clinst->update($input);

			if (method_exists($clinst, iscacheable))
				$this->cacheable = $clinst->iscacheable();
				
			$this->fillFields($input);
			$this->dateupdate = date("Y-m-d H:i:s");
			
			$this->maj();

			$this->reecrire();
			
			// Remove caches files
			$this->cleanCache();
		}
		
		public function create($sniptype, &$input, $title = '') {
			// We create the snippet immediately so that id is available 
			// to snippet type instance being created
			$s = new Snippet();
			$s->dateupdate = $s->datecreation = date("Y-m-d H:i:s");
			$s->privilege_view = 0;
			$s->privilege_edit = 1;
			if (isset($input['privilege_select']))
				$s->privilege_select = $input['privilege_select'];
			$s->id = $s->add();
			if ($title != '')
				$s->title = $title;
			else
				$s->title = 'nouvelle entree ('.$s->id.')';
				
			$claz = ucfirst(strtolower($sniptype)).'snippet';
			if (! class_exists($claz))
				ierror('('.$claz.') should never happen at '. __FILE__ . " " . __LINE__);
			$clinst = new $claz($s);
			$clinst->id = $clinst->create($input);
			// Update snippet with type info
			$s->snipid = $clinst->id;
			$s->sniptype = $sniptype;
			$s->maj();
			// Manage cache
			if (method_exists($clinst, iscacheable))
				$s->cacheable = $clinst->iscacheable();

			$s->reecrire();
				
			return $s->id;
		}
		
		// Support for dbbrowser
		// Return the tablename referenced by current record, for field snipid
		// Basically return the field sniptype
		public function dbbrowser_snipid_getReference() {
			return ucfirst($this->sniptype).'snippet';
		}
		
		public function action() {
			switch ($_REQUEST['action']) {
				case self::TABLE.'_init': $this->init(true /*bdoor*/);
					break ;
				case self::TABLE.'_update': $this->update($_REQUEST);
					break;
				//Ajax calls, returned string sent to client
				case self::TABLE.'_edit': echo $this->edit();
					break ;
				case self::TABLE.'_create': 
					$newid = $this->create($_REQUEST['sniptype'], $_REQUEST, $_REQUEST['title']);
					redirige(urlfond('snippet','id_snippet='.$newid));
					break ;
				default :
			}
		}
		
		// Get rewritten url for current record
		public function getUrl() {
			return "s-" . $this->id . "-" . ereg_caracspec($this->title) . ".html";
		}
		
		// Converts string so that it can be used as part of a URL 
		function string_to_url($string_name)
		{
			return $string_name;
			// Replace accentuated (french) chars
			$vSomeSpecialChars = array("á", "é", "í", "ó", "ú", "Á", "É", "Í", "Ó", "Ú", "ñ", "Ñ");
			$vReplacementChars = array("a", "e", "i", "o", "u", "A", "E", "I", "O", "U", "n", "N");
			$string_name = str_replace($vSomeSpecialChars, $vReplacementChars, $string_name);
			
			$file_name = preg_replace("[^a-z^A-Z^0-9^ ^-]", "", $string_name); /// ALLOWED CHARS
			$file_name = strtolower($file_name); /// CHANGE STRING TO LOWERCASE
			$file_name = preg_replace('/\s+/', " ", $file_name); /// REMOVE MULTIPLE SPACES
			$file_name = substr($file_name, 0, 150); /// SHORTEN TO 150 CHARS
			$file_name = trim($file_name); /// TRIM TRAILING SPACES
			$file_name = str_replace(" ", "-", $file_name); /// CHANGE SPACES TO HYPENS
			return $file_name; /// RETURN STRING
		}		
		
		// Extracts snippet id from rewritten url and stored in global variable
		// for later use
		// This method is called from moteur.php
		public function lireVarUrl($reecriture) {
			if(preg_match('/s-([^-]*)(.*)$/', $reecriture->url, $rec))
				$_REQUEST['id_snippet'] = lireVarUrl("id_snippet", $reecriture->param);
		}

		function reecrire($url = ""){

			$lang=$_SESSION['navig']->lang;
			
			if($url == ""){
				$url = $this->getUrl();
			}

			$url = eregurl($url);

			$param = "&id_snippet=" . $this->id;

			$test = new Reecriture();
			if($test->charger($url))
				return 0;

			$reecriture = new Reecriture();
			$reecriture->charger_param("snippet", $param, $lang, 1);
			if($reecriture->url == $url)
				return 0;

			$reecriture->actif = 0;
			$reecriture->maj();

			$reecriture = new Reecriture();
			$reecriture->fond = "snippet";
			$reecriture->url = $url;
			$reecriture->param = $param;
			$reecriture->lang = $lang;
			$reecriture->actif = 1;
			$reecriture->add();

		}
				
	}
	
	// Include all snippet types here
	require_once(realpath(dirname(__FILE__)) . "/Textsnippet.class.php");
	require_once(realpath(dirname(__FILE__)) . "/Htmlsnippet.class.php");
	require_once(realpath(dirname(__FILE__)) . "/Theliasnippet.class.php");
	require_once(realpath(dirname(__FILE__)) . "/Camerasnippet.class.php");

	// rewriting snippet
	function rewrite_snippet($id, $lang=0){
	
		if(! $lang) $lang=$_SESSION['navig']->lang;
	
		$reecriture = new Reecriture();
		$reecriture->charger_param("snippet", "&id_snippet=" . $id, $lang);
		return $reecriture->url;
	
	}	
	
?>
