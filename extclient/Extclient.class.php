<?php
	require_once(realpath(dirname(__FILE__)) . "/../Pluginsthext/PluginsThext.class.php");
	require_once(realpath(dirname(__FILE__)) . "/../../../classes/Variable.class.php");
	
	/* 
	 * Extclient plugin extends the Thelia client table
	 * client table is managed within the engine in Thelia
	 * extending its features requires to modify the engine itself, 
	 * which is not desirable to ease migration to latest versions
	 * Default fields are listed in the ini() function
	 * But it should be adapted according to the needs of a specific
	 * site that relies on this plugin
	 * On main feature added in the plugin is a 'privilege' level associated
	 * with each user.
	 * Other plugins can then rely on this value to define which users have access
	 * to some features with restricted access
	 * A site variable 'superadminlevel' is also created to store
	 * a pre-defined privilege level that should grant all rights, whatever the feature
	 * A credit value is available to manage an ewallet
	 * 
	 */
	
	class Extclient extends PluginsThext{
		const TABLE="extclient";

		function __construct($id = ""){
			parent::__construct(self::TABLE);
		
			if($id != "")
				$this->charger_id($id);
			
		}
		
		public function init(){
			$query = "
			CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`client` int(11) NOT NULL DEFAULT '0',
			`credit` int NOT NULL DEFAULT '0',
			`privilege` int(4) NOT NULL DEFAULT '0',
			PRIMARY KEY (`id`)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
			";
		
			$resul_commentaires = $this->query($query);
			
			$v = new Variable();
			if(! $v->charger('superadminlevel')){
				$v->nom = superadminlevel;
				$v->valeur = 5;
				$v->add();
			}
				
		}

		public function charger_client($client) {
			return $this->getVars("select * from $this->table where client=\"$client\"");
		}
		
		public function apresclient($client){
			$this->client = $client->id;
			$this->add();
		}

		public function apresconnexion(){
			$ec = new Extclient();
			$ec->charger_client($_SESSION['navig']->client->id);
			$_SESSION['navig']->extclient = $ec;
		}
		
		public function apresdeconnexion($extclient){
				
			unset($_SESSION['navig']->extclient);
		}
		
		public function action() {

			switch ($_REQUEST['action']) {
				case 'extclient_init': $this->init();
					break ;
				case 'extclient_config': 			
					if (! $this->charger_client($_SESSION['navig']->client->id))
						// should never happen
						return ;
				
					$this->loadParams($_REQUEST);
								
					$this->maj();
					break;
				default :
			}			
		}
		
		// Increment client credit with $amount
		// For new credit to be committed, maj() must be called
		public function creditTransaction($amount) {
			$this->credit += $amount;
		}
					
	}

?>