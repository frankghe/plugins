<?php

	include_once(realpath(dirname(__FILE__)) . "/../../../classes/Variable.class.php");
	include_once(realpath(dirname(__FILE__)) . "/../../../classes/Commande.class.php");
	include_once(realpath(dirname(__FILE__)) . "/../../../classes/Produit.class.php");
	include_once(realpath(dirname(__FILE__)) . "/../../../classes/Venteprod.class.php");
	require_once(realpath(dirname(__FILE__)) . "/../Pluginsthext/PluginsThext.class.php");
	loadPlugin("Extclient");
	loadPlugin("Paytype");
	
	class Wallet extends PluginsPaiementsThext{

		const TABLE="wallet";
		const VARWALLET = "rechargement wallet";
		
		const AUTPAID				= 1; // Automatically paid during session monitoring
		const AUTCONFIRMED			= 2; // Automatically confirmed during session monitoring -
										 // Set this state when both client and supplier have closed their session
		const MANPAID				= 3; // Manually paid
		const MANCONFIRMED			= 4; // Manually confirmed by user
		const CANCELED 				= 5;
		
		function __construct( $id = 0){
			parent::__construct("wallet");
			
			if ($id > 0)
				$this->charger_id($id);
		}

		function init($bdoor = false){
			
			if ( ! $bdoor )
				$this->ajout_desc("Wallet", "Porte-monnaie électronique", "", 1, 1);
			
			// Create SQL table to manage wallets
			$query = "
			CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`itemtype` varchar(128) DEFAULT NULL,
			`item` int(11) NOT NULL DEFAULT '0',
			`client` int(11) NOT NULL DEFAULT '0',
			`price` int NOT NULL DEFAULT '0',
			`tva` int NOT NULL DEFAULT '0',
			`status` int NOT NULL DEFAULT '0',
			`date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (`id`)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
			";
			
			$result = $this->query($query);

			$v = new Variable();
			if(! $v->charger(self::VARWALLET)){
				$v->nom = self::VARWALLET;
				$v->valeur = "";
				$v->add();
			}
				
			// FIXME : workaround to avoid MySQL error (devise can't be null)
			$this->modulesdesc->devise = 1;
		}

		public function est_module_de_paiement_pour($commande) {
			// This module can not be used to credit wallet (-:
	
			$this->load_walletprods();

			$p = new Produit();
			$p->charger($commande->ref);
			if (in_array($p->id, $lwalletprod))
				return false;
			
			// OK, standard produit, we can use wallet
			$module = new Modules();
			return $module->charger_id($commande->paiement) && $module->nom == $this->getNom();
		}
		
		public function load_walletprods() {
			$v = new Variable();
			$v->charger(self::VARWALLET);
			if ($v->valeur == '') {
				// Strange, we could pay with wallet but no value
				// to avoid self re-crediting wallet
				// We remove it from options by default
				return false;
			}
			$this->lwalletprod = explode(',',$v->valeur);
		}

		// This module can not be used to credit wallet (-:
		public function est_paiement_propose_pour(&$panier, &$exclusion) {
			
			if ( !isset($panier) || ! $panier->nbart)
				return false;
			
			$this->load_walletprods();
			$est_propose = true;
			
			foreach ($panier->tabarticle as $prod) {
				if (in_array($prod->produit->id, $this->lwalletprod)) $est_propose = false;
			}
				
			if ( ! $est_propose) {
				if ($exclusion != '') $exclusion.=',';
				$exclusion.= self::TABLE;
			}
		}
		
		function paiement($commande){

				if ($_SESSION['navig']->commande->id != $commande->id) {
		   			// Should never happen
		   			ierror('internal error (commande inconsistency) at '. __FILE__ . " " . __LINE__);
		   			exit;
		   			}
			
		   		ActionsModules::instance()->appel_module("confirmation", $commande);
		   		
		   		// Compute total price
		   		$total = $_SESSION['navig']->commande->total;
		   		$tva = 0;

		   		// Purchase and update db $client, $type, $id, $htprice, $tva
				if ( $this->purchase($commande->client, $commande->table, $commande->id, $total, $tva) ) {
					// success
				    $commande->status = 2;
				    $commande->genfact();
					$commande->maj();
				
					// Reset panier
					$_SESSION["navig"]->commande = new Commande();
					$_SESSION["navig"]->panier = new Panier();
					$_SESSION['navig']->promo = new Promo();
					$_SESSION['navig']->commande->transport = '';
						
					modules_fonction("confirmation", $commande);
				}
				else
					redirige(urlfond("rechargewallet"));
		   				   		
				redirige(urlfond('aprescommandewallet', "commande_ref=$commande->ref"));
				exit;
		}
		
		public function confirmation($commande) {
			$this->load_walletprods();
			$prods = $this->products_of($commande);
			$walletprod_trouve = false;
			// We iterate over all products of commande and specifically don't stop
			// when we find one, just in case multiple wallet products were included
			// unlikely but we never know...
			foreach ($prods as $prod) {
				if (in_array($prod, $this->lwalletprod)) {
					$walletprod_trouve = true;
					$ec = new Extclient();
					$ec->charger_client($commande->client);
					$ec->credit += $commande->total();
					
				}
			}
			if ($walletprod_trouve) $ec->maj();
			
		}
		
		// Return array of product ids linked to $commande
		public function products_of($commande) {
			
			$lprod = array();
			
			$venteprod = new Venteprod();
			$produit = new Produit();
			
			$query = "select produit.id from $venteprod->table,$produit->table where commande='$commande->id' and 
						produit.ref=venteprod.ref";
			$resul = $venteprod->query($query);
			
			$total = 0;
			
			while($row = mysql_fetch_object($resul))
				array_push($lprod,$row->id);
			
			return $lprod;
				
		}

		
		function ispaid() {
			$ispaid = false;
			if ($this->id && (
					$this->status == self::AUTPAID || 
					$this->status == self::AUTCONFIRMED ||
					$this->status == self::MANPAID ||
					$this->status == self::MANCONFIRMED ) )
				$ispaid = true;

			return $ispaid;
		}
		
		function purchase($client, $itemtype, $id, $price, $tva, $paymode, $message = ''){
			
			$ec = new Extclient();
			if ( ! $ec->charger_client($client) ) {
				// Should never happen...
				ierror('internal error (invalid client) at '. __FILE__ . " " . __LINE__);
				exit;
			}
			
			$rprice = $price + $tva;
			
			if ($ec->credit >= $rprice) {
				$this->client = $client;
				$this->itemtype = $itemtype;
				$this->item = $id;
				$this->price = $price;
				$this->tva = $tva;
				$this->date = date("Y-m-d H:i:s");
				$this->status = $paymode;
				
				$id = $this->add();
				
				$ec->credit -= ($price+$tva);
				$ec->maj();
									
				// Send message to client
				$m = new Messagerie();
				
				$m->client_src = $client;
				$m->client_dst = $supplier;
				$m->titre = 'Paiement du service numero '.$this->item;
				$m->message = $message;
				$m->date = date ("Y-m-d H:i:s");
				$m->add();
								
				return $id;
			}			
			else return false;
			
		}
		
		public function action() {
			switch ($_REQUEST['action']) {
				case 'wallet_init': $this->init(true /*bdoor*/);
					break ;
				default :
			}
		}

	}

?>
