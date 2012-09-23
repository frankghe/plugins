<?php
include_once(dirname(__FILE__) . "/../../../classes/Variable.class.php");

function tiny_mce_init($custom_options = array())
{
	$style_chem = new Variable();
	$style_chem->charger("style_chem");

	// Trouver l'URL absolue
	$urlsite = new Variable();
	$urlsite->charger("urlsite");

	$baseurl = rtrim('/' . preg_replace('!http://[^\/]+/?!', '', $urlsite->valeur), '/');

	// Permettre l'upload dans  KCFinder
	$_SESSION['KCFINDER'] = array();
	$_SESSION['KCFINDER']['disabled'] = false;
	$_SESSION['KCFINDER']['readonly'] = false;
	$_SESSION['KCFINDER']['denyZipDownload'] = false;

	$_SESSION['KCFINDER']['types'] = array(
	    'File'   =>  "",
	    'Flash'  =>  "swf flv avi mpg mpeg qt mov wmv asf rm",
	    'Image'  =>  "*img",
	);

	// Les options de tinymce
	$options = array(
			// General options
			// Plus d'infos sur la config: http://tinymce.moxiecode.com/wiki.php/Configuration
			'language' => '"fr"',
	        'mode' => '"exact"',
	        'elements' => '"description"',
			'theme' => '"advanced"',


			// Plus d'infos sur les plugins: http://www.tinymce.com/wiki.php/Plugins
			// 'plugins' => "pagebreak,style,layer,table,save,advhr,advimage,advlink,emotions,iespell,inlinepopups,insertdatetime,preview,media,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,template,wordcount,advlist,autosave",
			'plugins' => '"fullscreen,advlist,advimage,advlink,inlinepopups,style,paste,media,style"',

			// Theme options
			// Plus d'infos sur les boutons: http://www.tinymce.com/wiki.php/Buttons/controls

			'theme_advanced_buttons1' => '"undo,redo,|,bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,|,styleselect,formatselect"',
			'theme_advanced_buttons2' => '"image,media,|,link,unlink,|,bullist,numlist,|,forecolor,|,removeformat,|,pastetext,pasteword,|,outdent,indent,|,code,|,fullscreen"',
			'theme_advanced_buttons3' => '""',
			'theme_advanced_buttons4' => '""',
			'theme_advanced_toolbar_location' => '"top"',
			'theme_advanced_toolbar_align' => '"left"',
			'theme_advanced_statusbar_location' => '"bottom"',

			// Limiter la largeur pour ne pas "perdre" TinyMCE derrière le bloc image.
			'theme_advanced_resizing' => 'true',
			'theme_advanced_resizing_max_width' => '435',

			// CSS personnalisé: indiquer dans 'theme_advanced_styles' les styles définis dans le CSS 'content_css'
			// qui doivent figurer dans le menu 'Styles'
			'content_css' => '"' . $style_chem->valeur . '"',

			'theme_advanced_styles' => '""',
			'theme_advanced_blockformats' => '"p,div,h1,h2,h3,h4,h5,h6"',

			'entity_encoding' => '"raw"',

			'file_browser_callback' => '"openKCFinder"',

			// Utiliser <p></p> au lieu de <br /> pour les sauts de ligne
			'force_p_newlines' => 'true',
			'force_br_newlines' => 'false',
			'forced_root_block' => '"p"',

			// Taille adaptée au B.O. thelia
			'width' => '435',
			'height' => '395',

			'document_base_url' => '"'.rtrim($urlsite->valeur, '/') . '/"'
	);

	// Merger les options custom
	$options = array_merge($options, $custom_options);

	// Corriger les problèmes d'aspect des tables de tiny dûs au CSS du B.O.
	?>
	<style type="text/css">

	#bloc_description .mceEditor table {
		background-color: #F0F0EE;
		border: 0;
		color: #000;
		margin: 0;
	}
	</style>

    <script language="javascript" type="text/javascript" src="<?php echo $baseurl ?>/client/plugins/tinymceplus/tiny_mce.js"></script>

    <script language="javascript" type="text/javascript">
	<!--
	    tinyMCE.init({
		    <?php
		    $str_options = '';

		    foreach ($options as $nom => $valeur)
		    {
		    	$str_options .= "$nom: $valeur,\n";
		    }

		    echo rtrim($str_options, ",\n");
		    ?>
		});

	function openKCFinder(field_name, url, type, win) {

		// Transformer Media en Flash (adaptation à l'existant Thelia)
		if (type == 'media') type = 'flash';

	    type =  type.charAt(0).toUpperCase() + type.substr(1);

	    tinyMCE.activeEditor.windowManager.open({
	        file: '<?php echo $baseurl ?>/client/plugins/tinymceplus/kcfinder/browse.php?lng=fr&opener=tinymce&type=' + type,
	        title: 'KCFinder',
	        width: 700,
	        height: 500,
	        resizable: "yes",
	        inline: true,
	        close_previous: "yes",
	        popup_css: false
	    }, {
	        window: win,
	        input: field_name
	    });
	    return false;
	}
	// -->
	</script>
<?php
}
?>
