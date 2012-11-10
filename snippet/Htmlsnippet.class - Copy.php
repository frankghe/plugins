<?php

	
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
	Snippet::addSnippetType("html");
	class Htmlsnippet extends BaseObjThext {

		const TABLE="htmlsnippet";
		
		function __construct( $snippetptr){
			parent::__construct("htmlsnippet");
			
			// Reference to main snippet
			$this->snippet = $snippetptr ; 
			
			// Ce tableau liste les champs a rechercher dans la table de gestions des champs "linguistiques"
			$this->bddvarstext = array ("description");
			
			// Text fields configuration for Dbbrowser 
			$this->textDbbrowserConfig['description'] = "list=>display=0";
			
			if ($this->snippet->snipid > 0)
				$this->charger_id($this->snippet->snipid);
		
		}
				
		function charger_snippet($refdiv, $reffond) {
			$query = 'select * from '.$this->table.' where reffond=\''.$reffond.'\' and refdiv=\''.$refdiv.'\'';
			$loaded = $this->getVars($query);
			if ($loaded) $this->loadValtext();
			return $loaded;
				
		}

		function init($bdoor = false){
						
			// Create SQL table to manage htmlsnippets
			$query = "
			CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`refdiv` varchar(128) DEFAULT NULL,
			`reffond` varchar(128) DEFAULT NULL,
			`title` varchar(128) DEFAULT NULL,
			`sniptype` varchar(128) DEFAULT NULL,
			`privilege_edit` int(4) DEFAULT 1,
			`datecreation` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			`dateupdate` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (`id`)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
			";
			
			$result = $this->query($query);
		}
		
		
		public function update() {
			if (! $this->id) {
				// Create entry
				$this->fillFields($_REQUEST);
				$this->fillTextFields($_REQUEST);
				$this->add();
			}
			else {
				// Update
				$this->fillTextFields($_REQUEST);
				$this->maj();
			}				
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
			$res = $parseur->inclusion(explode("\n", $res));
				
			// inclusions des plugins
			// we remove actions otherwise for each snippet they are executed again !
			//ActionsModules::instance()->appel_module("action");
				
			$res = $parseur->analyse($res);
				
			ActionsModules::instance()->appel_module("analyse");
				
			Filtres::exec($res);
				
			$res = $parseur->post($res);
				
			// inclusions des plugins filtres
			ActionsModules::instance()->appel_module("post");
				
			// FG add customization of html file with dedicated function calls embedded in
			if (class_exists("ajoutPhp")){
				$aPhp = new ajoutPhp();
				$aPhp->parse($res,$fond);
			}
				
			// Résultat envoyé au navigateur
			$res = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $res);
				
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
	
						// Check for privilege
						if ($_SESSION['navig']->connecte && $_SESSION['navig']->extclient->privilege < $row->privilege_view)
							return '';

						// Enable edition if privilege high enough
						if ($_SESSION['navig']->connecte && $_SESSION['navig']->extclient->privilege >= $row->privilege_edit) {
							$edit='<div><a href="#" onclick="htmlsnippet_edit('.$curid.');">edit</a></div>';							
						}
						
						
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
							if ($t->nomchamp == 'description') {
								$this->thelia_parse($t->description);
							}
							$res = $edit.str_replace($htmlTag, $t->description, $res);				
						}
						$out.=$res;
					}
				}			
			}
			
			return $res;			
		}
				
		public function show() {
			return $this->valtext['description'];
		}
				
		public function edit(&$html, &$js) {
			$this->charger_id($_REQUEST['id']);
			
			$desc = $this->valtext['description'];
			$id = $this->id;
						
			// Edit the template 			
			$html.=  '<p><textarea  id="htmlsnippet_edit_description" class="tinymce" cols="30" rows="20" name="description">'.
								$desc.'</textarea></p>';
			//
			// Generate html form:
			//
			// FIXME: Can't get tineMCE to work through Ajax so it
			// it loaded in htmlsnippet.html
			$js_DUMMY = <<<EOD
		<script type="text/javascript" src="lib/tinymce/jscripts/tiny_mce/tiny_mce.js"></script>
		<script type="text/javascript">// <![CDATA[

				function apply_tinymce() {
					tinyMCE.init({
					// General options
					mode : "specific_textareas",
					editor_selector : "tinymce",
					theme : "advanced",
					plugins : "autolink,lists,spellchecker,pagebreak,style,layer,table,save,advhr,advimage,advlink,emotions,iespell,inlinepopups,insertdatetime,preview,media,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,template",
					
					// Theme options
					theme_advanced_buttons1 : "save,newdocument,|,bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,|,styleselect,formatselect,fontselect,fontsizeselect",
					theme_advanced_buttons2 : "cut,copy,paste,pastetext,pasteword,|,search,replace,|,bullist,numlist,|,outdent,indent,blockquote,|,undo,redo,|,link,unlink,anchor,image,cleanup,help,code,|,insertdate,inserttime,preview,|,forecolor,backcolor",
					theme_advanced_buttons3 : "tablecontrols,|,hr,removeformat,visualaid,|,sub,sup,|,charmap,emotions,iespell,media,advhr,|,print,|,ltr,rtl,|,fullscreen",
					theme_advanced_buttons4 : "insertlayer,moveforward,movebackward,absolute,|,styleprops,spellchecker,|,cite,abbr,acronym,del,ins,attribs,|,visualchars,nonbreaking,template,blockquote,pagebreak,|,insertfile,insertimage",
					theme_advanced_toolbar_location : "top",
					theme_advanced_toolbar_align : "left",
					theme_advanced_statusbar_location : "bottom",
					theme_advanced_resizing : true,
					
					// Skin options
					skin : "o2k7",
					skin_variant : "silver",
					
					// Example content CSS (should be your site CSS)
					content_css : "mainslibres/mainslibres.css",
					       
					// Drop lists for link/image/media/template dialogs
					template_external_list_url : "js/template_list.js",
					external_link_list_url : "js/link_list.js",
					external_image_list_url : "js/image_list.js",
					media_external_list_url : "js/media_list.js",
					});
					
				}
					
				apply_tinymce();
				tinyMCE.execCommand('mceAddControl', false, 'htmlsnippet_edit_description');
		// ]]>
		</script>			
EOD;
			return ;
		}
		
		public function edit_legacy() {
			$reffond = $_REQUEST['reffond'];
			// if reffond undefined, will simply display home page (hopefully ?)
			// FIXME: should ensure that we display same page (but updated)
			$urlfond = urlfond($reffond);
			if ($urfond == '') $urlfond = $fond; // going to index screws up...
			$refdiv = $_REQUEST['refdiv'];
			
			$this->charger_snippet($_REQUEST['refdiv'], $_REQUEST['reffond']);
			
			if ( ($_SESSION['navig']->connecte && $_SESSION['navig']->extclient->privilege < $this->privilege_view) ||
				((! $_SESSION['navig']->connecte) && $this->privilege_view>0) )
				// Void description so that next line returns empty string...
				$this->valtext['description'] = '';
			
			return $this->valtext['description'];
				
		}
		
		public function create(&$input) {
			$this->fillTextFields($input);
			return $this->add();
		}
		
		function getid() {
			if ($this->charger_snippet($_REQUEST['refdiv'], $_REQUEST['reffond']))
				return $this->id;
			else return 0;
		}
	}

?>
