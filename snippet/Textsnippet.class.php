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
	Snippet::addSnippetType("text");
	class Textsnippet extends BaseObjThext {

		const TABLE="textsnippet";
		
		function __construct( $snippetptr = null){
			parent::__construct(self::TABLE);
			
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
			PRIMARY KEY (`id`)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
			";
			
			$result = $this->query($query);
		}
		
		
		public function update(&$input) {
			if (! $this->id) {
				// Create entry
				$this->fillFields($input);
				$this->fillTextFields($input);
				$this->add();
			}
			else {
				// Update
				$this->fillTextFields($input);
				$this->maj();
			}				
		}
					
			// Static content, so cacheable
		public function iscacheable() {
			return true;
		}
						
		// Check if snippet is a "main" html page
		public function ismainhtml() {
			if (! $this->id) 
				if (! $this->charger_id($this->snippet->sniptid))
					ierror('snippet not found, should never happen at '.
							__FILE__ . " " . __LINE__);
				
			if (strpos($this->valtext['description'],'<html') !== false)
				return true;
			else
				return false;
		}
		
		public function show() {
			return $this->valtext['description'];
		}
				
		public function edit(&$html, &$js) {
			
			$desc = $this->valtext['description'];
						
			// Edit the template 			
			$html.=  '<p><textarea  id="textsnippet_edit_description" class="notinymce" cols="80" rows="20" name="description">'.
								$desc.'</textarea></p>';
			//
			// Generate html form:
			//
			return ;
		}
				
		public function create(&$input) {
			$this->fillTextFields($input);
			return $this->add();
		}
		
	}

?>
