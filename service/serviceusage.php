<?php
class Serviceusage extends BaseobjThext {

	const TABLE="serviceusage";

	const UNPLANNED 			= 0; // Only booked
	const PLANREQUESTED 		= 1; // Request for mtg sent (fullcalendar exists)
	const PLANNED				= 2; // Planned (fullcalendar exists and confirmed by both client/supplier)
	const ONGOING				= 3; // Session started by at least one of client/supplier	
	const CLOSED				= 4; // Session over
	
	function __construct($id = ""){
		parent::__construct(self::TABLE);

		if($id != "")
			$this->charger_id($id);
	}

	public function charger_wallet($wallet)
	{
		$query = "select * from $this->table where wallet=$wallet";
		return $this->getVars($query);
	}

	public function init()
	{
		// Create table to associate payments types with service
		// Create SQL table to manage wallets
		$query = "
		CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`client` int(11) NOT NULL DEFAULT '0',
		`wallet` int(11) NOT NULL DEFAULT '0',
		`servicesupplierpaytype` int(11) NOT NULL DEFAULT '0',
		`date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		`dateused` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		`fullcalendar` int(11) NOT NULL DEFAULT '0',
		`status` int DEFAULT '0',
		PRIMARY KEY (`id`)
		) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
		";
		
		$res = $this->query($query);
			
	}
	
	function reserve($client,$scp) {
		$this->client = $client;
		$this->servicesupplierpaytype = $scp;
		$this->date = date("Y-m-d H:i:s");
		$this->status = self::UNPLANNED;
		
		$this->add();
	}
	
	function purchase($message = '') {
			
		$this->status = self::CLOSED;
		$this->maj();
		
		$ec = new Extclient();
		if ( ! $ec->charger_client($this->client) ) {
			// Should never happen...
			ierror('internal error (invalid client) at '. __FILE__ . " " . __LINE__);
			exit;
		}
			
		$pt = new Paytype($this->paytype());
		$scp = new Servicesupplierpaytype($this->servicesupplierpaytype);
		// Compute real price
		switch ($pt->category) {
			case Paytype::PERHOUR;
			case Paytype::FORFAIT:
			case Paytype::PERDAY:
				$price = $scp->price;
				$tva = $scp->tva;
				break ;
			case Paytype::PERMIN;
				// Compute meeting duration and related price
				$list = loadItems('Webmtg',"select * from webmtg where serviceusage=$this->id");
				if ($count($list)) {
					$minstarttime = $list[0]->startTime;
					$maxstarttime = $list[0]->endTime;
					foreach ($list as $it) {
						$s = strtotime($it->startTime);
						if ($s < $minstarttime) $minstarttime = $s;
						$e = strtotime($it->endTime);
						if (($e > $maxendtime)) $maxendtime = $e;
					}
					$minutes = round(abs($e - $s) / 60,0).
					$price = $scp->price * $minutes;
				}
				else
		   			ierror('internal error (commande inconsistency) at '. __FILE__ . " " . __LINE__);
				break;
			case Paytype::SUBSCRIPTION:
				break;
			default :
				break;
		}

		$rprice = $price + $tva;
			
		if ($ec->credit >= $rprice) {
					
			// Create wallet transaction
			$w = new Wallet();
			$this->wallet = $w->purchase($this->client,'serviceusage', $this->id, $price, $tva, Wallet::AUTPAID);
			$this->maj();
			
			// Update supplier credit
			$es = new Extclient();
			$es->charger_supplier($this->supplier());
			$es->creditTransaction( + $price);
			$es->maj();
			
			// Send message to supplier
			$m = new Messagerie();
			
			$m->client_src = $ec->client;
			$m->client_dst = $es->client;
			$m->titre = 'Paiement de service '.$this->titre.'(numero '.$this->id.')';
			$m->message = $message;
			$m->date = date ("Y-m-d H:i:s");
			$m->add();
		}		
		else
			// FIXME we're in trouble: service delivered but customer can't pay !!!
			redirige(urlfond('rechargewallet'));
	
	}
	
	public function client() {
		return $this->client;
	}
	
	public function supplier() {
		$scp = new Servicesupplierpaytype($this->servicesupplierpaytype);
		$sc = new Servicesupplier($scp->servicesupplier);
		return $sc->supplier;
	}
	
	public function issupplier() {
		$s = new Supplier();
		return $s->isSupplier();
	}
	
	public function isclient() {
		if ($this->client == $_SESSION['navig']->client->id) return true;
		else return false;
	}
	
	public function ispaid() {
		$ispaid = false;
		if ($this->wallet) {
			$w = new Wallet($this->wallet);
			$ispaid = $w->ispaid();
		}
		
		return $ispaid;
	}
	
	// Get titre of corresponding service
	public function titre() {
		$scp = new Servicesupplierpaytype($this->item);
		$sc = new Servicesupplier($scp->servicesupplier);
		$s = new Service($sc->service);
		return $sc->titre;
	}
	
	public function paytype() {
		$scp = new Servicesupplierpaytype($this->servicesupplierpaytype);
		return $scp->paytype;
	}
	
	
	public function action() {
		switch ($_REQUEST['action']) {
			case 'serviceusage_init': $this->init(true /*bdoor*/);
				break ;
				
			case 'serviceusage_reserve':
				$this->reserve($_SESSION['navig']->client->id, $_REQUEST['scp_id']);
				break;
				
			case 'serviceusage_invitation_create':
				
				$this->charger_id($_REQUEST['wallet']);
				$supplier = $this->supplier();
				// Besides the standard SQL fields, we have custom fields to parse
				$fc = new Fullcalendar();
				$fc->client = $this->client;
				// FIXME: where is $supplier set ??? - in moteur.php ?
				$fc->supplier = $supplier;
				$fc->titre = $this->titre();
				$fc->description = $_REQUEST['message'];
				$fc->start_date = $_REQUEST['startDate'].' '.$_REQUEST['startTime'];
				$fc->date = date ("Y-m-d H:i:s");
					
				$this->fullcalendar = $fc->add();
				$this->status = self::PLANREQUESTED;
					
				// Send message to client
				$m = new Messagerie();
				
				$m->client_src = $_REQUEST['wallet_invitation_src'];
				$m->client_dst = $_REQUEST['wallet_invitation_dst'];
				$m->titre = 'Invitation à un web meeting';
				$m->message = $message;
				$m->date = date ("Y-m-d H:i:s");
				$m->add();
				
				$this->maj();
				break;
					
			case 'serviceusage_invitation_update':

				$this->charger_id($_REQUEST['wallet']);
					
				if ( ($this->status == self::UNPLANNED) || 
					 ($this->status == self::USED) || 
					 ($this->status == self::AUTPAID) || 
					 ($this->status == self::AUTCONFIRMED) || 
					 ($this->status == self::MANPAID) || 
					 ($this->status == self::MANCONFIRMED) )
					return '';
					
				// Besides the standard SQL fields, we have custom fields to parse
				$fc = new Fullcalendar();
				$fc->charger_id($this->fullcalendar);
				$fc->description = $_REQUEST['message'];
				$fc->start_date = $_REQUEST['startDate'].' '.$_REQUEST['startTime'];
				
				$this->fullcalendar = $fc->maj();
				break ;
				
			case 'serviceusage_pay':
				$this->charger_id($_REQUEST['serviceusage']);
				$this->purchase();
				break ;
				
			case 'serviceusage_cancel':
				$this->charger_id($_REQUEST['serviceusage']);
				$w = new Wallet($this->wallet);
				if ($w->status < Wallet::MANPAID && $this->isclient()) {
					
					// FIXME: client only able to cancel if within XX days after service
					
					$price = $w->price;
					// Update client credit
					$ec = new Extclient();
					$ec->charger_client($this->client());
					$ec->creditTransaction( + $price);
					$ec->maj();
					// Update supplier credit
					$es = new Extclient();
					$es->charger_client($this->supplier());
					$es->creditTransaction( - $price);
					$es->maj();
					
					$w->status = Wallet::CANCELED;
					$w->maj();
					
					$this->status = self::UNPLANNED;
					$this->maj();
					
					// Send message to inform supplier
					// Send message to client
					$m = new Messagerie();
						
					$m->client_src = $ec->client;
					$m->client_dst = $es->client;
					$m->titre = 'Annulation du paiement d\'un service';
					$m->message = 'Le client a annule le service numero '.$w->id;
					$m->date = date ("Y-m-d H:i:s");
					$m->add();
					
				}
						
					
				
			default :
		}
	}
}
?>