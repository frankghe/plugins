<?php
	include_once(realpath(dirname(__FILE__)) . "/../Pluginsthext/PluginsThext.class.php");
	require_once(realpath(dirname(__FILE__)) . "/../../../classes/Variable.class.php");
	require_once(realpath(dirname(__FILE__)) . "/../../../classes/Client.class.php");
	loadPlugin('Extclient');
	

	class Connexion extends PluginsThext {

		const TABLE="connexion";

		function __construct($id = 0){
			parent::__construct(self::TABLE);

			if($id > 0)
 			  $this->charger($id);
		}

		
		public function init(){
			$query = "CREATE TABLE `".self::TABLE."` (
			`id` int(11) NOT NULL auto_increment,
			`extclient` int(11) NOT NULL,
			`conn_login` datetime NOT NULL,
			`conn_last` datetime NOT NULL,
			`conn_logout` datetime NOT NULL,
			`ip` varchar(128) NOT NULL,
			PRIMARY KEY  (`id`)
			) AUTO_INCREMENT=1 ;";
				
			$resul_commentaires = $this->query($query);
		
		}
		
		function charger($id){
			return $this->getVars("select * from $this->table where id=\"$id\"");

		}

		// Charge le dernier enregistrement pour $extclient 
		function charger_extclient_enligne($extclient){
			$query = "select * from $this->table where extclient=\"$extclient\" and conn_logout=\"0000-00-00 00:00:00\" ".
						" order by $this->table.conn_login desc limit 1";
			
			return $this->getVars($query);
		}

		// Charge le dernier enregistrement pour $client 
		function charger_client_enligne($client){
			$c = new Client();
			$ec = new Extclient();
			$query = "select $this->table.* from $this->table,$c->table, $ec->table where $c->table.id=\"$client\" and conn_logout=\"0000-00-00 00:00:00\" ".
					" and extclient.client=client.id order by $this->table.conn_login desc limit 1";
				
			return $this->getVars($query);
		}
		
		
		function delete($requete){
				$resul = $this->query($requete, $this->link);
				CacheBase::getCache()->reset_cache();
		}
		
		function isenligne(){
			if (! $this->extclient)
				return 0;
			
			$duree = new Variable();
			$duree->charger('conn_window');
			$conn_longest = date('Y-m-d H:i:s', strtotime('-'.$duree->valeur.' minutes'));
			$query = "select count(id) from connexion where conn_last>\"$conn_longest\" and extclient=\"$this->extclient\"".
						 "and conn_logout=\"0000-00-00 00:00:00\" ";
			$result = mysql_query($query, $this->link);
			if ($result){
				$row = mysql_fetch_row($result);
				return $row[0];
			}
			else
				return 0;
		}
		
		function totalenligne(){
			$duree = new Variable();
			$duree->charger('conn_window');
			$conn_longest = date('Y-m-d H:i:s', strtotime('-'.$duree->valeur.' minutes'));
			$query = "select count(id) from connexion where conn_last>\"$conn_longest\"".
						 "and conn_logout=\"0000-00-00 00:00:00\" ";
			
			$result = mysql_query($query, $this->link);
			if ($result){
				$row = mysql_fetch_row($result);
				return $row[0];
			}
			else
				return 0;
		}
		
		function totalsupplierenligne($service = ''){
			$duree = new Variable();
			$ec = new Extclient();
			$duree->charger('conn_window');
			$conn_longest = date('Y-m-d H:i:s', strtotime('-'.$duree->valeur.' minutes'));
			$t = $this->table;
			$query = "select count($t.id) from $t,$ec->table where conn_last>\"$conn_longest\" and ".
						"$t.extclient=$ec->table.id and $ec->table.issupplier=\"1\"".
						"and conn_logout=\"0000-00-00 00:00:00\" ";
				
			$result = mysql_query($query, $this->link);
			if ($result){
				$row = mysql_fetch_row($result);
				return $row[0];
			}
			else
				return 0;
		}
		

		function supprimer(){
			$this->delete("delete from $this->table where id=\"$this->id\"");

			return 1;

		}

		public function apresconnexion(){

			if ( ! isset($_SESSION['navig']->extclient)) {
				$ec = new Extclient();
				$ec->charger_client($_SESSION['navig']->client->id);
				$_SESSION['navig']->extclient = $ec;
			}
			$this->extclient=$_SESSION['navig']->extclient->id;
			$this->conn_login = date ("Y-m-d H:i:s");
			$this->conn_last = $this->conn_login;
			$this->ip = $_SERVER['REMOTE_ADDR'];
			$this->add();
			
		}
		
		public function apresdeconnexion($client){
			
			if (!isset($client)) return ; // Strange, should not happen...
			if (! $this->charger_client_enligne($client))
				return ierror('internal error (deconnexion but client ('.$client.') does not exist...) at '. __FILE__ . " " . __LINE__);
			
			$this->conn_logout = date ("Y-m-d H:i:s");			
			$this->maj();
				
		}
		
		public function action(){
				
		}
		
	}

?>