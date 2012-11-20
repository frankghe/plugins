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
	class Htmlsnippet extends Textsnippet {

		const TABLE="textsnippet";
		
		function __construct( $snippetptr = null){
			parent::__construct($snippetptr);
			
		}
		
		public function edit(&$html, &$js) {
			
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
		
	}

?>
