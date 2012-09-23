<?php
include_once(dirname(__FILE__) . "/../../../fonctions/authplugins.php");

autorisation("tinymceplus");

// Les pages du back office ou TinyMce sera activé
$tm_pages_autorisees = array(
    'produit_modifier',
    'rubrique_modifier',
    'contenu_modifier',
    'dossier_modifier'
);

if ( preg_match("`([^\/]*).php`", $_SERVER['PHP_SELF'], $page) && in_array($page[1], $tm_pages_autorisees)) {

	include_once(dirname(__FILE__) . "/../../../client/plugins/tinymceplus/tiny_mce_init.php");

	tiny_mce_init();
}
?>
