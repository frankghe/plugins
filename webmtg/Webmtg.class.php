<?php

include_once(realpath(dirname(__FILE__)) . "/../Pluginsthext/PluginsThext.class.php");
include_once(realpath(dirname(__FILE__)) . "/../../../classes/Client.class.php");
loadPLugin("Messagerie");
loadPlugin('Service');
loadPlugin('Paytype');
	
	class Webmtg extends PluginsThext{		
				
		const TABLE = 'webmtg';
		
		const VIDEOCHAT = 'videochat';
		const REMOTE = 'remote';
		
		const STATUS_UNPLANNED 		= 'pas planifié';
		const STATUS_PLANNED 		= 'pas demarré';
		const STATUS_OPEN 			= 'ouvert'; // mtg started by moderator, but no participant connected
		const STATUS_ONGOING 		= 'en cours'; // mtg open and at least 1 participant connected
		const STATUS_COMPLETED 		= 'terminé';
		
		public function __construct(){
			parent::__construct(self::TABLE);	
			$this->status = self::STATUS_UNPLANNED;
		}

		// Triplet (wallet,type,id) is what we need for unique identification
		public function charger($serviceusage, $id, $webmtgtype){
			$this->serviceusage = $serviceusage;
			$query = "select * from $this->table where serviceusage='$serviceusage' and client='$id'  and webmtgtype='$webmtgtype' order by date desc limit 1";
			
			return $this->getVars($query);
		}
		
		public function init(){									
			$query = "
					CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
					  `id` int(11) NOT NULL AUTO_INCREMENT,
					  `serviceusage` int(11) NOT NULL DEFAULT '0',
					  `client` int(11) NOT NULL DEFAULT '0',
					  `status` varchar(128) DEFAULT NULL,
					  `webmtgtype` varchar(128) DEFAULT NULL,
					  `startTime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
					  `endTime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
					  `date` datetime DEFAULT '0000-00-00 00:00:00',
					  PRIMARY KEY (`id`)
					) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
					";
						
			$result = $this->query($query);				
				
		}

		public function action(){
			
			if (! $_SESSION['navig']->connecte)
				return ;
			
			foreach ($this->bddvars as $key => $val){
				if (array_key_exists($val, $_REQUEST)) $this->$val = $_REQUEST[$val];
			}
				
			switch ($_REQUEST['action']) {
				
				case "webmtg_download":
				case "webmtg_downloaded":
					$serviceusage = $_REQUEST['serviceusage'];
					$su = new Serviceusage();
					if (! $su->charger_id($serviceusage)) {
						// Should never happen...
						ierror('internal error (serviceusage does not exist) at '. __FILE__ . " " . __LINE__);
						exit;
					}
					
					$wmtg = new Webmtg();
					$wmtg->serviceusage = $serviceusage;
					$wmtg->client = $_SESSION['navig']->client->id;
					$wmtg->webmtgtype = $_REQUEST['webmtgtype'];
					$wmtg->create(date("Y-m-d H:i:s"), '');
					
					// In case of videochat, download and start are merged (to avoid sending 2 sequential ajax requests)
					if ($wmtg->webmtgtype == self::VIDEOCHAT) {
						$wmtg->start();
					}
					break ;
				
				case "webmtg_create":
					$d = $_REQUEST['startDate'];
					$t = $_REQUEST['startTime'];
					
					if ($this->charger_serviceusage($_REQUEST['serviceusage']))
						ierror('internal error (webmtg already created) at '. __FILE__ . " " . __LINE__);
					
					$this->create($d.' '.$t, $_REQUEST['message']);						
					break;

				case "webmtg_start":
				case "webmtg_started":
					$wmtg = new Webmtg();
					if (! $wmtg->charger($_REQUEST['serviceusage'], $_SESSION['navig']->client->id, $_REQUEST['webmtgtype']) ) {
						ierror('internal error (webmtg does not exist) at '. __FILE__ . " " . __LINE__);
						exit ;
					}
					$wmtg->start();
					break ;
						
					
				case "webmtg_update":
					$d = $_REQUEST['startDate'];
					$t = $_REQUEST['startTime'];
					
					$this->charger_serviceusage($this->serviceusage);
					$this->update($d.' '.$t, $_REQUEST['message']);
					break;
					
				case "webmtg_close":
				case "webmtg_closed":
					$wmtg = new Webmtg();
					$wmtg->charger($_REQUEST['serviceusage'], $_SESSION['navig']->client->id, $_REQUEST['webmtgtype']);
					$wmtg->close();
					
					// FIXME: If both sides ended the meeting, update the related wallet
					// Need smarter processing here...
					break;
					
				case "webmtg_init":
					$this->init();
					break;
					
				default :
			}
		}

		public function create($time, $message = '')
		{ 

			if ( ($this->status === self::STATUS_ONGOING)  || ($this->status === self::STATUS_COMPLETED)) {
						// Should never happen...
				ierror('internal error (webmtg state is invalid) at '. __FILE__ . " " . __LINE__);
				return ;
			}
			
			if ($this->status == self::STATUS_PLANNED)
				// nothing to do: mtg was created already...
				// this can happen when a mtg is created but not started and then closed
				return ;
				
			$su = new Serviceusage($this->serviceusage);
			$s = new Client();
			$s->charger_id($su->supplier());
				
			// FIXME
			// Check that client has enough credit to start the webmtg
			//
			
			
			$client = new Client();
			$client->charger_id($su->client);

			if ($this->meetingID == '') $this->meetingID = $this->venteprod;
			$this->date = date("Y-m-d H:i:s");
				
			
			$this->status = self::STATUS_PLANNED;
			$this->id = $this->add();
		
		}
		
		public function start()
		{
			
			$su = new Serviceusage();
			if (! $su->charger_id($this->serviceusage)) {
				ierror('internal error (serviceusage does not exist) at '. __FILE__ . " " . __LINE__);
				// Should never happen...
				exit;
			}
				
			// Webmtg started
			$this->status = self::STATUS_ONGOING;
			$this->startTime = date("Y-m-d H:i:s");
			$this->maj();
			
			$su->status = Serviceusage::ONGOING;
			$su->dateused = date("Y-m-d H:i:s");
			$su->maj();
				
			return ;
				
		}
		public function close()
		{
			
			$su = new Serviceusage();
			if (! $su->charger_id($this->serviceusage)) {
				ierror('internal error (serviceusage does not exist) at '. __FILE__ . " " . __LINE__);
				// Should never happen...
				exit;
			}
			
				
			if ($this->status == self::STATUS_ONGOING) {
				// Webmtg completed
				$this->status = self::STATUS_COMPLETED;
				$this->endTime = date("Y-m-d H:i:s");
				$this->maj();

				// Mtg successfully completed, proceed to payment
				// Update serviceusage (and pay with wallet)
				if ($su->client == $_SESSION['navig']->client->id) {
					$su->purchase();
				}
			}
			else if ($this->status == self::STATUS_OPEN) {
				// Supplier connected but not moderator, so we "reset" status
				$this->status = self::STATUS_PLANNED;
				$this->endTime = '0000-00-00 00:00:00';
				$this->maj();
			} else 
				ierror('internal error (illegal action for this mtg state) at '. __FILE__ . " " . __LINE__);
			
			return ;
		}
		
		
		// Update an existing Webmtg
		// We assume that the registered session in BBB does not need any modification
		// So update is mostly about changing the fullcalendar entry
		public function update($time, $message)		
		{ 
			
			
		}
		
		
		
		public function supplier() {
			$su = new Serviceusage($this->serviceusage);
			return $su->supplier();
		}
		
	}
?>
