<?php
	require_once(realpath(dirname(__FILE__)) . "/../Pluginsthext/PluginsThext.class.php");	
	require_once(realpath(dirname(__FILE__)) . "/serviceusage.php");
	loadPlugin('Paytype');
	loadPlugin('Texte');
	loadPlugin('Wallet');
	loadPlugin('Supplier');
	
	class Servicesupplierpaytype extends BaseobjThext {
		
		const TABLE="servicesupplierpaytype";
		
		function __construct($id = 0){
			parent::__construct(self::TABLE);

			if($id != 0)
 			  $this->charger_id($id);
		}

		public function charger_servicesupplier($servicesupplier, $paytype)
		{
			$query = "select * from $this->table where servicesupplier=$servicesupplier and paytype=$paytype";
			return $this->getVars($query);
		}
		
		public function init()
		{
			// Create table to associate payments types with service
			$query = "
			CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`paytype` int(11) NOT NULL DEFAULT '0',
			`price` float NOT NULL DEFAULT '0',
			`discount` smallint(6) NOT NULL DEFAULT '0',
			`price2` float NOT NULL DEFAULT '0',
			`payperuse` boolean NOT NULL DEFAULT '0',
			`paymentfrequency` int NOT NULL DEFAULT '0',
			`servicesupplier` int(11) NOT NULL DEFAULT '0',
			PRIMARY KEY (`id`)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
			";
				
			$res = $this->query($query);
			
		}
		
		public function purchase($client)
		{
			$w = new Wallet();
			
			// We compute price here (incl discounts)
			// FIXME for production
			$price = $this->price;
			$tva = $this->tva;
			
			// Purchase and update db $client, $type, $id, $htprice, $tva
			if ( $w->purchase_service($client, $this->table, $this->id, $price, $tva) ) {
				// success
			}
			else
				redirige(urlfond("rechargewallet"));
		
		}
		
		
		
	}
	
	class Servicesupplier extends BaseobjThext {
		
		const TABLE="servicesupplier";
				
		function __construct($id = 0){
			parent::__construct(self::TABLE);

			if($id != 0)
 			  $this->charger_id($id);
			
			// Load text fields
			$this->bddvarstext = array( "description");
		}

		public function charger_supplier($supplier, $service)
		{
			$query = "select * from $this->table where supplier=$supplier and service=$service";
			return $this->getVars($query);
		}
		
		public function init() {
			// Create table to associate supplier with service and payment types
			$query = "
			CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`service` int(11) NOT NULL DEFAULT '0',
			`supplier` int(11) NOT NULL DEFAULT '0',
			`datecreation` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			`datemodif` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			`nouveaute` smallint(6) NOT NULL DEFAULT '0',
			`online` smallint(6) NOT NULL DEFAULT '0',
			PRIMARY KEY (`id`)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
			";
			
			$res = $this->query($query);			
		}
		
		// Used by dbbrowser to edit fields of a givenrecord
		// 'p' prefix stands for parent
		// 'o' prefix stands for options
		public function load_join($ptable, $pid, $otable, $oid)
		{
			$query = "select * from $this->table where $otable=$oid and $ptable=$pid";
			return $this->getVars($query);
		}		
	}
	
	
	class Service extends PluginsThext{

		const TABLE="service";
		
		function __construct($id = 0){
			parent::__construct(self::TABLE);

			if($id != 0)
 			  $this->charger_id($id);

			$this->bddvarstext = array ("titre");
			
				
		}

		function charger_titre($category, $titre){
			$sqlstring = mysql_real_escape_string($titre);
			$query = "select service.* from service,texte where texte.parent_id=service.id and
						texte.nomtable='$this->table' and texte.nomchamp='titre' and
						texte.description = '$sqlstring' and category='$category'
						";
			return $this->getVars($query);
		}

		public function init(){
			$query = "
				CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `category` int(11) NOT NULL,
				  `datecreation` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				  `datemodif` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				  PRIMARY KEY (`id`)
				) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
			";
				
			$resul_commentaires = $this->query($query);
			
			$sc = new Servicesupplier();
			$sc->init();
			
			$scp = new Servicesupplierpaytype();
			$scp->init();
			
			$su = new Serviceusage();
			$su->init();
				
		}
		
		
		function supprimer(){

			if ($this->id == 0 || $this->id == "") return;

			// Remove links with paytypes
			$table = self::TABLE.Paytype::TABLE;
			$query = "delete from $table where service='" . $this->id . "'";
			$resul = $this->query($query);

			$this->delete();
			return 1;

		}


		public function boucle($texte, $args){
			$search ="";
			$res=$out="";
			
			// Get arguments arguments and prepare query
			foreach ($this->bddvars as $key => $val){
				$$val = lireTag($args, "$val");
				if ($$val != "") $search .= " and $val=\"". $$val . "\"";
			}
			
			$tablestring = self::TABLE;
			$servicesupplier = lireTag($args,'servicesupplier');
			if ($servicesupplier>0) {
				$tablestring.=','.$this->table.Supplier::TABLE;
				$search.=' and '.$this->table.Supplier::TABLE.'.service=service.id and servicesupplier.id='.$servicesupplier;
				$extrafields = "
						,servicesupplier.supplier,
						servicesupplier.datecreation,
						servicesupplier.datemodif,
						servicesupplier.nouveaute,
						servicesupplier.online,
						servicesupplier.id as sid				
				";
			}
			
			// We include servicesupplier fields separately to avoid clash with id column
			$query = "select service.* $extrafields from ". $tablestring . " where 1 $search";
			
			$result = $this->query($query);
			
			if ($result) {
			
				$nbres = $this->num_rows($result);
			
				if ($nbres > 0) {
			
					while( $row = $this->fetch_object($result)){
			
						$res = $texte;
						
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
							$t->charger(self::TABLE, $val, $row->id, $_SESSION['navig']->lang);
							$htmlTag = '#'.strtoupper($val);
							$res = str_replace($htmlTag, $t->description, $res);
						}
							
						if ($servicesupplier>0){
							$res = str_replace("#SERVICESUPPLIER_SERVICE", $row->service, $res);
							$res = str_replace("#SERVICESUPPLIER_SUPPLIER", $row->service, $res);
							$res = str_replace("#SERVICESUPPLIER_DATECREATION", $row->datecreation, $res);
							$res = str_replace("#SERVICESUPPLIER_DATEMODIF", $row->datemodif, $res);
							$res = str_replace("#SERVICESUPPLIER_NOUVEAUTE", $row->nouveaute, $res);
							$res = str_replace("#SERVICESUPPLIER_ONLINE", $row->online, $res);
							$text = new Texte();
							$text->charger($this->table.Supplier::TABLE,'description',$row->sid, $_SESSION['navig']->lang);
							$res = str_replace("#SERVICESUPPLIER_DESCRIPTION", $text->description, $res);
						}
						$out.=$res;
					}
					
					
				}
			
			}
				
			return $out;
			
				
		}	
				
		public function create(){
			foreach ($this->bddvars as $key => $val){
				if (array_key_exists($val,$_REQUEST))
					$this->$val = $_REQUEST[$val];
			}
			
			if (! $this->charger_titre($this->category, $_REQUEST['titre'])) {
				// service does not exist, we create it so that we can reference it for this supplier
				$s = new Supplier();
				$this->supplier = $s->getIdFromClient($_SESSION['navig']->client->id);
				$this->datecreation = date("Y-m-d H:i:s");
				$this->datemodif = date("Y-m-d H:i:s");
				$this->online = 1;
					
				$serviceid = $this->add();
			}
			else 
				$serviceid = $this->id;

			// Create supplier serivce
			$data['service'] = $serviceid;
			$s = new Supplier();
			$data['supplier'] = $s->getIdFromClient($_SESSION['navig']->client->id);
			$data['datecreation'] = date("Y-m-d H:i:s");
			$data['datemodif'] = date("Y-m-d H:i:s");
			$data['online'] = 1;
			$this->insertSQL(self::TABLE.Supplier::TABLE , $data);
			$id = mysql_insert_id();
				
			// Add description text field
			$t = new Texte();
			$t->nomtable = self::TABLE.Supplier::TABLE;
			$t->parent_id = $id;
			$t->lang = $_SESSION['navig']->lang;
			$a = Array("description");
			$t->ajout($a, $_REQUEST);
			
			// Tous les champs textuels sont ajoutes
			$t = new Texte();
			$data = array();
			$t->nomtable = self::TABLE;
			$t->parent_id = $serviceid;
			$t->lang = $_SESSION['navig']->lang;
			$t->ajout($this->bddvarstext, $_REQUEST);
				
			// Add payment types
			$data = array(); // reset to clean previous values
			$query = "select * from ". Paytype::TABLE;
			$result = $this->query($query);
			if ($result) {
				while( $row = $this->fetch_object($result)){
					$lfield = $lvalues = '';
					$refstring = Paytype::TABLE.'_id_'.$row->id;
					if ($_REQUEST[$refstring] == 1) {
						// payment type selected, fetch price and save
						$refstring = Paytype::TABLE.'_price_'.$row->id;
						if (is_numeric($_REQUEST[$refstring])) $lvalues.= $_REQUEST[$refstring];
							else $lvalues.='0';
						$data[Paytype::TABLE] = $row->id;
						$data[$this->table.Supplier::TABLE] = $id;
						$data['price'] = $lvalues;				
						$this->insertSQL(self::TABLE.Supplier::TABLE.Paytype::TABLE, $data);
					}
					else {
						// payment type not selected
						
					}
				}
			}							
		}

		public function update(){
			
			foreach ($this->bddvars as $key => $val){
				if (array_key_exists($val,$_REQUEST))
					$this->$val = $_REQUEST[$val];
			}
			$this->datemodif = date("Y-m-d H:i:s");

			// We save original id so that if changed we can potentially remove it
			$origid = $this->id;
			
			// Check that service description did not change otherwise need to update, possibly creating a new one
			if (! $this->charger_id($this->id)) {
				// Should never happen...
				ierror('internal error (service does not exist) at '. __FILE__ . " " . __LINE__);
				exit;
			}

			$t = new Texte();
			$t->charger($this->table,'titre',$this->id,$_SESSION['navig']->lang);
			if ($t->description != $_REQUEST['titre']){
				// service does not exist, we create it so that we can reference it for this supplier
				$s = new Supplier();
				$this->supplier = $s->getIdFromClient($_SESSION['navig']->client->id);
				$this->datecreation = date("Y-m-d H:i:s");
				$this->datemodif = date("Y-m-d H:i:s");
				$this->online = 1;
					
				$serviceid = $this->add();

				// Tous les champs textuels sont ajoutes
				$t = new Texte();
				$data = array();
				$t->nomtable = self::TABLE;
				$t->parent_id = $serviceid;
				$t->lang = $_SESSION['navig']->lang;
				$t->ajout($this->bddvarstext, $_REQUEST);
				
				// Check if original id used. If not, delete it
				$query = "select count(id) as res from servicesupplier where servicesupplier.service=$origid";
				$result = mysql_fetch_array($this->query($query),MYSQL_ASSOC);
				if (isset($result['res']) and $result['res'] == 1){
					// Noone using apart from this supplier, remove
					$query = "delete from $this->table where id=$origid";
					if (! $this->query($query))
						ierror('internal error (could not delete service) at '. __FILE__ . " " . __LINE__);
					$query = "delete from texte where nomtable='$this->table' and nomchamp='titre' and parent_id='$origid'";
					if (! $this->query($query))
						ierror('internal error (could not delete titre) at '. __FILE__ . " " . __LINE__);
						
				}
				echo $result[0]->res;
				
			}
			else
				$serviceid = $this->id;
				
			$this->maj();
			
			// Update servicesupplier
			// Create supplier serivce
			$data['service'] = $serviceid;
			$data['datemodif'] = date("Y-m-d H:i:s");
			$data['online'] = 1;
			$cond = "id=".$_REQUEST['servicesupplier'];
			$this->updateSQL(self::TABLE.Supplier::TABLE , $data, $cond);
			// Update description field
			$t = new Texte();
			$t->charger($this->table.Supplier::TABLE,'description',$_REQUEST['servicesupplier'],$_SESSION['navig']->lang);
			$t->description = $_REQUEST['description'];	
			$t->maj();
			
			// Update payment types
			$data = array(); // reset to clean previous values
			$query = "select * from ". Paytype::TABLE;
			$result = $this->query($query);
			if ($result) {
				while( $row = $this->fetch_object($result)){
					$scp = new Servicesupplierpaytype();
					$exists = false;
					if ($scp->charger_servicesupplier($_REQUEST['servicesupplier'], $row->id))
							$exists = true;
					
					// Prepare the values
					$refstring = Paytype::TABLE.'_price_'.$row->id;
					if (is_numeric($_REQUEST[$refstring])) $scp->price= $_REQUEST[$refstring];
						else $scp->price='0';
					$refstring = Paytype::TABLE.'_price2_'.$row->id;
					if (is_numeric($_REQUEST[$refstring])) $price2= $_REQUEST[$refstring];
						else $scp->price2='0';
					$refstring = Paytype::TABLE.'_discount_'.$row->id;
					if (is_numeric($_REQUEST[$refstring])) $price2= $_REQUEST[$refstring];
						else $scp->discount='0';
						
					$scp->servicesupplier = $_REQUEST['servicesupplier'];
					$scp->paytype = $row->id;
					// Update , create or delete according to request
					if ($_REQUEST[Paytype::TABLE.'_id_'.$row->id] == 1) {
						if ($exists) 
							$scp->maj();
						else 
							$scp->add();
					}
					else
						if ($exists) $scp->delete();
				}
			}
				
			
		}
		
		public function action() {
			
			if($_REQUEST['action'] == "service_init"){
				$this->init();
			}
			else if($_REQUEST['action'] == "service_maj") {
				if (! $this->charger_id($_REQUEST['service'])) {
					// Requested updated but record does not exist so we create it
					$this->create();
				}
				else
					$this->update();
			}
			elseif ($_REQUEST['action'] == "service_ajout"){
				$this->create();
			}
			elseif ($_REQUEST['action'] == "service_paiement"){
				$scp = new Servicesupplierpaytype($_REQUEST['scp_id']);
				$scp->purchase($_SESSION['navig']->client->id);
			}
			else { 			
				// By default, if an action request follows the naming convention, call corresponding action():
				// Naming convention <classname>_<restofstring>, then call <classname>->action()
				$temp = explode('_',$_REQUEST['action']);
				$cl = ucfirst($temp[0]);
				if ($cl == ucfirst(self::TABLE) && method_exists($this,$temp[1])) {
					$this->$temp[1];
				}
			}
				
				
		}

		// Search for tags matching text
		function search($text){
		
			if(! $_SESSION["navig"]->connecte)
				return ;
		
			$search = 'description LIKE \'%'.$text. '%\'';
			$query = "select * from texte where texte.nomtable='$this->table' and
						texte.nomchamp='titre' and ($search) LIMIT 10";
			$result = $this->query($query);
			if(empty($result)) die('Requête invalide : ' . mysql_error());
		
			$list = array ();
			while( $row = $this->fetch_object($result)){
				$item['value'] = $row->description;
				$item['id'] = $row->parent_id;
				array_push($list,$item);
			}
		
			echo json_encode($list);
			return ;
			// for test
			echo json_encode(array (
					array ( 'label' => 'test1' , 'value' => 'testvalue'),
					array ('value' => 'value1')));
			//echo json_encode(array ("test", "test2"));
			return ;
		}
		
	
	}

	
?>