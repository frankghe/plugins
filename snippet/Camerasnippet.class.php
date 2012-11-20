<?php

	
	class Cameraimage extends BaseobjThext {
	
		const TABLE="cameraimage";
	
		function __construct($id = ""){
			parent::__construct(self::TABLE);
	
			if($id != "")
				$this->charger_id($id);
			
			// Ce tableau liste les champs a rechercher dans la table de gestions des champs "linguistiques"
			$this->bddvarstext = array ("caption");
	
		}
	
		public function init()
		{
			// Create table to associate payments types with service
			// Create SQL table to manage wallets
			$query = "
			CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`filename` text,
			`order` smallint(6),
			`camerasnippet` int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY (`id`)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
			";
	
			$res = $this->query($query);
	
		}
		
		public function charger_image($parent, $filename) {
			$query = "select * from ".self::TABLE." where camerasnippet='".$parent."' and filename='".$filename."'";
			$loaded = $this->getVars($query);
			if ($loaded) $this->loadValtext();
			
			return $loaded;
		}
	
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
	Snippet::addSnippetType("camera");
	class Camerasnippet extends BaseObjThext {

		const TABLE="camerasnippet";
		
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
			
			$ci = new Cameraimage();
			$ci->init();
		}
		
		
		public function update(&$input) {
			if (! $this->id) {
				// Create entry
				$this->id = $this->add();	
			}			
			
			// Add images and captions
			$dir = $this->getImageDir();
			if ($handle = opendir($dir)) {
				$i = 1;
				while (false !== ($entry = readdir($handle))) {
					if ($entry != "." && $entry != "..") {
						// FIXME: ugly, find a clean solution
						$cleanentry = str_replace(".","_","$entry");
						$ci = new Cameraimage();
						if ($ci->charger_image($this->id,$entry)) {
							// Image in db, if removed, remove, otherwise update caption
							if (isset($input[$cleanentry])) {
								$ci->order = $input['order_'.$cleanentry];
								$cap = array('caption' => $input['caption_'.$cleanentry]);
								$ci->fillTextFields( $cap);
								$ci->maj();
							}
							else {
								$ci->delete();
							}
						}
						else {
							if (isset($input[$cleanentry])) {
								$ci->camerasnippet = $this->id;
								$ci->filename = $entry;
								$ci->order = $input['order_'.$cleanentry];
								$cap = array('caption' => $input['caption_'.$cleanentry]);
								$ci->fillTextFields( $cap);
								$ci->add();
							}
						}
					}
				}
				closedir($handle);
			}
				
			
		}
		
		// Static content, so cacheable
		public function iscacheable() {
			return true;
		}
						
		// Check if snippet is a "main" html page
		public function ismainhtml() {
			return false;
		}
		
		public function show() {
			$id = $this->id;
			$html.=  <<<EOD
			    <link rel='stylesheet' id='camera-css'  href='lib/camera/css/camera.css' type='text/css' media='all'> 
			    <script type='text/javascript' src='lib/camera/scripts/camera.min.js'></script> 
			    
			    <script>
					jQuery(function(){
						
						jQuery('#slider_$id').camera({
							thumbnails: true,
							time:3000
						});	
					});
				</script>
EOD;
			$html.= '<div class="camera_wrap camera_azure_skin" id="slider_'.$id.'">';
			
			$query = "select * from cameraimage where camerasnippet='".$this->id."' ORDER BY `order` ASC";
			$result = $this->query($query);
			if ($result) {
				$nbres = $this->num_rows($result);
				if ($nbres > 0) {
					while( $row = $this->fetch_object($result)){
						$html.='<div data-src="'.$this->getImageDir().$row->filename.'" data-link="'.$row->link.'">';
						$t = new Texte();
						if ( $t->charger('cameraimage','caption',$row->id) && isset($t->description) 
								&& $t->description != '')
							$html.='<div class="camera_caption fadeFromBottom">'.$t->description.'</div>';
						$html.='</div>';
					}	
				}
			}
			$html.='</div><!-- #camera_wrap --><p>&nbsp;</p>';
			return $html;
		}
				
		function getImageDir() {
			global $reptpl;
			
			return $reptpl."upload/";
		}
		
		public function edit(&$html, &$js) {
			
			$html.='<div style="clear:both;"></div>';
			$html.='<div class="imagelist">';
			$dir = $this->getImageDir();
			if ($handle = opendir($dir)) {
				$i = 1;
				while (false !== ($entry = readdir($handle))) {
					if ($entry != "." && $entry != "..") {
						$ci = new Cameraimage();
						if ($ci->charger_image($this->id,$entry)) $checked = 'checked';
							else $checked = '';
						$fullimagepath = $this->getImageDir().$entry;
						$html.='<div class="imageitem">';
						$html.='<div class="imageview">';
						$html.='<img src="'.$fullimagepath.'" width="50px">';
						$html.='</div>';
						$html.='<div class="imagedesc">';
						$html.=$entry.'<br/>';
						$html.='<input type="checkbox" name="'.$entry.'" value="1" '.$checked.'>';
						$html.='&nbsp;ordre:<input type="text" name="order_'.$entry.'" value="'.$ci->order.'" '.$ci->order.'><br/>';
						$html.='<input type="text" name="caption_'.$entry.'" value="'.$ci->valtext['caption'].'" size="60" >';
						$html.='</div>';
						$html.='<div style="clear:both;"><hr></div>';
						$html.='</div>';
						$i++;
					}
				}
				closedir($handle);
			}
			$html.='</div>';
			$html.='<div style="clear:both;"></div>';
			return $html;
				
		}
		
		
		public function create(&$input) {
			return $this->add();
			
		}
		
		
	}

?>
