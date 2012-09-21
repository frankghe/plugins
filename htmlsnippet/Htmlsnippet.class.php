<?php

	include_once(realpath(dirname(__FILE__)) . "/../../../classes/Variable.class.php");
	include_once(realpath(dirname(__FILE__)) . "/../../../classes/Commande.class.php");
	include_once(realpath(dirname(__FILE__)) . "/../../../classes/Produit.class.php");
	include_once(realpath(dirname(__FILE__)) . "/../../../classes/Venteprod.class.php");
	require_once(realpath(dirname(__FILE__)) . "/../Pluginsthext/PluginsThext.class.php");
	loadPlugin('texte');
		
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
	class Htmlsnippet extends PluginsThext{

		const TABLE="htmlsnippet";
		
		function __construct( $id = 0){
			parent::__construct("htmlsnippet");
			
			// Ce tableau liste les champs a rechercher dans la table de gestions des champs "linguistiques"
			$this->bddvarstext = array ("description");
			if ($id > 0)
				$this->charger_id($id);
		}
		
		function charger_snippet($refdiv, $reference, $reffond) {
			$query = 'select * from '.$this->table.' where reference=\''.$reference.
					'\' and reffond=\''.$reffond.'\' and refdiv=\''.$refdiv.'\'';
			$loaded = $this->getVars($query);
			if ($loaded) $this->loadValtext();
			return $loaded;
				
		}

		function init($bdoor = false){
			
			if ( ! $bdoor )
				$this->ajout_desc("Htmlsnippet", "Manage html cde snippets", "", 1, 1);
			
			// Create SQL table to manage htmlsnippets
			$query = "
			CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`refdiv` varchar(128) DEFAULT NULL,
			`reference` varchar(128) DEFAULT NULL,
			`reffond` varchar(128) DEFAULT NULL,
			`privilege_view` int(4) DEFAULT 0,
			`privilege_edit` int(4) DEFAULT 1,
			`datecreation` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			`dateupdate` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (`id`)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
			";
			
			$result = $this->query($query);
		}
		
		public function update() {
			if (! $this->charger_snippet($_REQUEST['refdiv'], $_REQUEST['reference'], 
												$_REQUEST['reffond'])) {
				// create new entry
				$this->fillFields($_REQUEST);
				$this->datecreation = date("Y-m-d H:i:s");
				$this->dateupdate = $this->datecreation;
				$this->id = $this->add();
				$this->updateTextFields($_REQUEST, $this->id);
			}
			else {
				// Update snippet
				$this->fillFields($_REQUEST);
				$this->dateupdate = date("Y-m-d H:i:s");
				$this->maj();
				$this->updateTextFields($_REQUEST, $this->id);
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

					// Check for privilege
					if ($_SESSION['navig']->connecte && $_SESSION['navig']->extclient->privilege < $row->privilege_view)
						return '';
						
					
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
						if ($t->nomchamp == 'description')
							$this->thelia_parse($t->description);
						$res = str_replace($htmlTag, $t->description, $res);				
					}
					$out.=$res;
				}
			}			
		}
		
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
		
		public function edit() {
			
			$id = $_REQUEST['id'];
			$reference = $_REQUEST['reference'];
			$reffond = $_REQUEST['reffond'];
			// if reffond undefined, will simply display home page (hopefully ?)
			// FIXME: should ensure that we display same page (but updated)
			$urlfond = urlfond($reffond);
			if ($urfond == '') $urlfond = $fond; // going to index screws up...
			$refdiv = $_REQUEST['refdiv'];
				
			$this->charger_snippet($_REQUEST['refdiv'], $_REQUEST['reference'], $_REQUEST['reffond']);
			
			if ( $_SESSION['navig']->connecte && $_SESSION['navig']->extclient->privilege < $this->privilege_view)
				// Void description so that next line returns empty string...
				$this->valtext['description']->description = '';
			
			return $this->valtext['description']->description;
		}
		
		public function action() {				
			switch ($_REQUEST['action']) {
				case 'htmlsnippet_init': $this->init(true /*bdoor*/);
					break ;
				case 'htmlsnippet_update': $this->update();
					break;
				case 'htmlsnippet_edit': echo $this->edit();
					break ;
				default :
			}
		}

	}

?>
