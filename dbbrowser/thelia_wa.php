<?php

require_once(realpath(dirname(__FILE__)) . "/../Pluginsthext/PluginsThext.class.php");

// Workaround to add features required to browse database with dbbrowser without 
// actually patching Thelia code

class Statut extends BaseObjThext {
	const TABLE="statut";
	
	function __construct($id = 0){
		parent::__construct(self::TABLE);
	
		if($id != 0)
			$this->charger_id($id);
	}
	
}

class wa_functions extends Cnx {
	
	function __construct($dbbinst){
		parent::__construct();
		
		$this->dbbinst = $dbbinst;
	}
	//
	// Custom functions for standard Thelia classes, to avoid patching official code in actual classes...
	//
	
	function raison_dropListTable($current){
		return $this->dbbinst->dropListTable('raisondesc','court','raison', $current);
	}
	
	function zone_dropListTable($current){
		return $this->dbbinst->dropListTable('zone','nom','id', $current);
	}
	
	function lang_dropListTable($current){
		return $this->dbbinst->dropListTable('lang','description','id', $current);
	}
	
	function raison_name($id){
		return $this->dbbinst->getField('raisondesc','court','raison',$id);
	}
	
	function lang_name($id){
		return $this->dbbinst->getField('lang','description','id',$id);
	}
	
	function adresse_name($id){
		return $this->dbbinst->getField('adresse','ville','id',$id);
	}
	
	function client_name($id){
		$c = new Client($id);
		return $c->prenom.' '.$c->nom;
	}
	
	// Called by editRecord to overwrite edition of field motdepasse from class client
	function client_dbb_motdepasse($rec, $action)
	{
		switch ($action) {
			case 'edit':
				$out='<input  type="text" name="motdepasse" value=""/>';
				break;
			case 'update':
				// mdp must be at least 4 chars
				if (strlen($_REQUEST['motdepasse']) > 3)
				{
					$mdp = strip_tags($_REQUEST['motdepasse']);
					$query = "select PASSWORD('$mdp') as resultat";
					$resul = $this->query($query);
					$rec->motdepasse = mysql_result($resul, 0, "resultat");
				}
				else {
					// Do nothing so that we keep old password
				}
				break;
			default:
				break;
		}
		return $out;
	}
	
	function commande_fieldlookup($field)
	{
		ierror('obsolete function commande_fieldlookup');
		die();
		switch ($field) {
			case 'adrlivr':
			case 'adrfact':
				$out = 'adresse';
				break;
			default:
				$out = '';
		}
		return $out;
	}
	
}

?>