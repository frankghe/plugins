<?php

include_once(realpath(dirname(__FILE__)) . "/../Pluginsthext/PluginsThext.class.php");
include_once(realpath(dirname(__FILE__)) . "/../../../classes/Ventedeclidisp.class.php");
include_once(realpath(dirname(__FILE__)) . "/../../../classes/Venteprod.class.php");
include_once(realpath(dirname(__FILE__)) . "/../../../classes/Client.class.php");

include_once(realpath(dirname(__FILE__)) . "/../fullcalendar/Fullcalendar.class.php");

include_once(realpath(dirname(__FILE__)) . "/config.php");
include_once(realpath(dirname(__FILE__)) . "/bbb-api.php");

	
	class Bbb extends PluginsThext{		
				
		private $bbb;
		
		// Liste des clients, extraite de la 2e table
		public $clients;
		
		const TABLE = 'bbb_mtg';
		const STATUS_UNPLANNED = 'pas planifié';
		const STATUS_PLANNED = 'pas demarré';
		const STATUS_OPEN = 'ouvert'; // mtg started by moderator, but no participant connected
		const STATUS_ONGOING = 'en cours'; // mtg open and at least 1 participant connected
		const STATUS_COMPLETED = 'terminé';
		
		
		public function __construct(){
			parent::__construct(self::TABLE);	
			$this->bbb = new BigBlueButton();
			$this->status = self::STATUS_UNPLANNED;
		}

		public function charger($id){		
			return $this->charger_id($id);
		}

		public function charger_venteprod($venteprod){
			return $this->getVars("select * from $this->table where venteprod=\"$venteprod\"");
		}
		
		public function init(){									
			$query = "
					CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
					  `id` int(11) NOT NULL AUTO_INCREMENT,
					  `venteprod` int(11) NOT NULL DEFAULT '0',
					  `status` varchar(128) DEFAULT NULL,
					  `meetingID` varchar(128) DEFAULT NULL,
					  `createTime` varchar(128) DEFAULT NULL,
					  `voiceBridge` varchar(128) DEFAULT NULL,
					  `attendeePW` varchar(128) DEFAULT NULL,
					  `moderatorPW` varchar(128) DEFAULT NULL,
					  `running` tinyint(1) NOT NULL DEFAULT '0',
					  `recording` tinyint(1) NOT NULL DEFAULT '0',
					  `hasBeenForciblyEnded` tinyint(1) NOT NULL DEFAULT '0',
					  `startTime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
					  `endTime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
					  `participantCount` int(11) NOT NULL DEFAULT '0',
					  `maxUsers` int(11) NOT NULL DEFAULT '0',
					  `moderatorCount` int(11) NOT NULL DEFAULT '0',
					  `fullcalendar` int(11) NOT NULL DEFAULT '0',
					  `date` datetime DEFAULT '0000-00-00 00:00:00',
					  PRIMARY KEY (`id`)
					) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
					";
						
			$result = $this->query($query);				
				
		}

		public function destroy(){
		}		

		public function boucle($texte, $args){
			
			// récupération des arguments
			$search  = $this->loadTags($args);
			$search .= " and bbb_mtg.venteprod=venteprod.id and ventedeclidisp.venteprod=venteprod.id and ".
					"venteprod.commande=commande.id";
				
			$res="";
							
			$query = "select bbb_mtg.id as bbb_mtg_id,client,supplier from bbb_mtg,venteprod,ventedeclidisp,commande ".
						"where 1 $search";
			
			$result = $this->query($query);
			
			if ($result) {
			
				$nbres = $this->num_rows($result);
			
				if ($nbres > 0) {
			
					while( $row = $this->fetch_object($result)){
			
						$temp = $texte;
						
						$temp = str_replace("#ID", $row->bbb_mtg_id, $texte);
						$temp = str_replace("#STATUS", $row->status, $texte);
						$temp = str_replace("#SUPPLIER", $row->supplier, $temp);
						$temp = str_replace("#CLIENT", $row->client, $temp);
						$temp = str_replace("#MESSAGE", $row-message, $temp);
						$temp = str_replace("#VENTEPROD", $row->venteprod, $temp);
						$temp = str_replace("#MEETINGID", $row->meetingID, $temp);
						$temp = str_replace("#CREATETIME", $row->createTime, $temp);
						$temp = str_replace("#VOICEBRIDGE", $row->voiceBridge, $temp);
						$temp = str_replace("#ATTENDEEPW", $row->attendeePW, $temp);
						$temp = str_replace("#MODERATORPW", $row->moderatorPW, $temp);
						if ($row->running) $checked = 'checked';
							else $checked = '';
						$temp = str_replace("#RUNNING", $checked, $temp);
						if ($row->recording) $checked = 'checked';
							else $checked = '';
						$temp = str_replace("#RECORDING", $checked, $temp);
						if ($row->hasBeenForciblyEnded) $checked = 'checked';
						else $checked = '';
						$temp = str_replace("#HASBEENFORCIBLYENDED", $checked, $temp);
						$temp = str_replace("#STARTTIME", $row->startTime, $temp);
						$temp = str_replace("#ENDTIME", $row->endTime, $temp);
						$temp = str_replace("#PARTICIPANTCOUNT", $row->participantCount, $temp);
						$temp = str_replace("#MAXUSERS", $row->maxUsers, $temp);
						$temp = str_replace("#MODERATORCOUNT", $row->moderatorCount, $temp);
						$temp = str_replace("#ENDTIME", $row->endTime, $temp);
						$temp = str_replace("#DATE", substr($row->date, 0, 10), $temp);
						$res .= $temp;
					}
				}
			
			}
			
			return $res;
			
				
		}	

			public function action() 
		{
			switch ($_REQUEST['action']) {
				
				case "createBbbMtg":
					foreach ($this->bddvars as $key => $val){
						$this->$val = $_REQUEST[$val];
					}
					$d = $_REQUEST['startDate'];
					$t = $_REQUEST['startTime'];
					
					if ($this->charger_venteprod($_REQUEST['venteprod'])) 
						ierror('internal error (webmtg already created) at '. __FILE__ . " " . __LINE__);
					
					$this->createWebMtg($d.' '.$t, $_REQUEST['message']);
					break;
					
				case "updateBbbMtg":
					foreach ($this->bddvars as $key => $val){
						$this->$val = $_REQUEST[$val];
					}
					$d = $_REQUEST['startDate'];
					$t = $_REQUEST['startTime'];
					
					$this->charger_venteprod($this->venteprod);
					$this->updateWebMtg($d.' '.$t, $_REQUEST['message']);
					break;
					
				case "joinBbbMtg":
					$this->charger_venteprod($_REQUEST['venteprod']);
					if ($this->status == self::STATUS_COMPLETED)
						// should never happen
						return redirige(urlfond("moncompte"));
					
					$vdd = new Ventedeclidisp();
					$vdd->charger_vdec($this->venteprod, $_SESSION['navig']->client->id);				
					if ($_SESSION['navig']->client->id == $vdd->supplier) {
						// supplier is connecting
						$this->status = self::STATUS_OPEN;
						$this->maj();
					}
					else if ($this->status == self::STATUS_OPEN) {
						// client is connecting
						$this->status = self::STATUS_ONGOING;
						$this->startTime = date("Y-m-d H:i:s");
						$this->maj();
					}
					else if ($this->status == self::STATUS_ONGOING) {
						// client is connecting to an ongoing mtg (or reconnecting)
					}
					else
						redirige(urlfond("moncompte"));
						
					$url = $this->getServerWebMtgUrl();
					redirige($url);
					break;
					
				case "closeBbbMtg":
					$this->charger_venteprod($_REQUEST['venteprod']);
					// Only supplier can close mtg
					// This code should really be called out of context of normal connection
					// therefore not relying on _SESSION info
					// So uid should be passed as parameter
					// FIXME: need to encrypt uid
					// FIXME: anyway single returnurl provided so this part needs revisiting !!!
					$vdd = new Ventedeclidisp();
					if ($vdd->charger_vdec($this->venteprod, $_SESSION['navig']->client->id))
						$this->closeWebMtg();
					break;
						
				default:
					break;
			}
		}
		
		public function closeWebMtg()
		{
			if ($this->status == self::STATUS_ONGOING) {
				// Webmtg completed
				$this->status = self::STATUS_COMPLETED;
				$this->endTime = date("Y-m-d H:i:s");
				$this->maj();
				$this->closeServerWebMtg();
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
		
		public function closeServerWebMtg()
		{
			/* ___________ END A MEETING ______ */
			/* Determine the meeting to end via meetingId and end it.
			 */
			
			$endParams = array(
					'meetingId' => $this->meetingID, // REQUIRED - We have to know which meeting to end.
					'password' => $_SESSION['navig']->client->email, // REQUIRED - Must match moderator pass for meeting.
			
			);
			
			// Get the URL to end a meeting:
			$itsAllGood = true;
			try {$result = $this->bbb->endMeetingWithXmlResponseArray($endParams);
			}
			catch (Exception $e) {
				echo 'Caught exception: ', $e->getMessage(), "\n";
				$itsAllGood = false;
			}
			
			if ($itsAllGood == true) {
				// If it's all good, then we've interfaced with our BBB php api OK:
				if ($result == null) {
					// If we get a null response, then we're not getting any XML back from BBB.
					return ierror('internal error (no connection to bbb server ?t) at '. __FILE__ . " " . __LINE__);
				}
				else {
					// We got an XML response, so let's see what it says:
					if ($result['returncode'] == 'SUCCESS') {
					}
					else {
						return ierror('internal error (failed to end mtg) at '. __FILE__ . " " . __LINE__);;
					}
				}
			}
				
		}
		
		
		
		public function getWebMtgUrl()
		{
			return "?action=joinWebMtg&venteprod=$this->venteprod";
		}
		
		// Retrieve url from bbb server for web mtg
		public function getServerWebMtgUrl()
		{
			
			if ( ($this->status == self::STATUS_UNPLANNED) || ($this->status == self::STATUS_UNPLANNED)  )
				return '';
			
			/* ___________ JOIN MEETING w/ OPTIONS ______ */
			/* Determine the meeting to join via meetingId and join it.
			 */
			
			$joinParams = array(
					'meetingId' => $this->meetingID, // REQUIRED - We have to know which meeting to join.
					'username' => $_SESSION['navig']->client->prenom. ' '.$_SESSION['navig']->client->nom, // REQUIRED - The user display name that will show in the BBB meeting.
					'password' => $_SESSION['navig']->client->email, // REQUIRED - Must match either attendee or moderator pass for meeting.
					'createTime' => '', // OPTIONAL - string
					'userId' => '', // OPTIONAL - string
					'webVoiceConf' => '' // OPTIONAL - string
			);
			
			// Get the URL to join meeting:
			$itsAllGood = true;
			try {$result = $this->bbb->getJoinMeetingURL($joinParams);
			}
			catch (Exception $e) {
				echo 'Caught exception: ', $e->getMessage(), "\n";
				$itsAllGood = false;
			}
			
			if ($itsAllGood == true) {
				//Output results to see what we're getting:
				return $result;
			}			
		}
		
		// Update an existing Webmtg
		// We assume that the registered session in BBB does not need any modification
		// So update is mostly about changing the fullcalendar entry
		public function updateWebMtg($time, $message)
		
		{ 
			
			if ( ($this->status == self::STATUS_UNPLANNED) || ($this->status == self::STATUS_UNPLANNED)  )
				return '';
				
			$v = new Venteprod();
			$v->charger($venteprod);
			$vdd = new Ventedeclidisp();			
			
			// Besides the standard SQL fields, we have custom fields to parse
			$fc = new Fullcalendar();
			$fc->charger($this->fullcalendar);
			$fc->titre = $v->titre;
			$fc->description = $message;
			$fc->start_date = $time;
			$fc->date = $this->date;
				
			$this->fullcalendar = $fc->maj();
			
		}
		
		/* ___________ CREATE MEETING w/ OPTIONS ______ */
		/*
		 */
		public function createWebMtg($time, $message)
		{ 

			if ( ($this->status === self::STATUS_PLANNED) || ($this->status === self::STATUS_ONGOING)  || 
					($this->status === self::STATUS_OPEN)  || ($this->status === self::STATUS_COMPLETED))
				return '';
				
			$v = new Venteprod();
			$v->charger($this->venteprod);
			$vdd = new Ventedeclidisp();
			$cmd = new Commande();
			$cmd->charger($v->commande);
			$vdd->charger($v->id);
			$s = new Client();
			$s->charger_id($vdd->supplier);
				
			$client = new Client();
			$client->charger_id($cmd->client);

			if ($this->meetingID == '') $this->meetingID = $this->venteprod;
			$this->date = date("Y-m-d H:i:s");
				
						
			// when webmtg completes, return url to update db
			$returl = urlfond('moncompte','action=closeWebMtg&venteprod='.$this->venteprod);
			
			// Fill in bbb record and save
						
			$creationParams = array(
					'meetingId' => $this->venteprod, // REQUIRED
					'meetingName' => 'Test Meeting Name', // REQUIRED
					'attendeePw' => $client->email, // Match this value in getJoinMeetingURL() to join as attendee.
					'moderatorPw' => $s->email, // Match this value in getJoinMeetingURL() to join as moderator.
					'welcomeMsg' => '', // ''= use default. Change to customize.
					'dialNumber' => '', // The main number to call into. Optional.
					'voiceBridge' => '', // PIN to join voice. Optional.
					'webVoice' => '', // Alphanumeric to join voice. Optional.
					'logoutUrl' => $returl, // Default in bigbluebutton.properties. Optional.
					'maxParticipants' => '-1', // Optional. -1 = unlimitted. Not supported in BBB. [number]
					'record' => 'false', // New. 'true' will tell BBB to record the meeting.
					'duration' => '0', // Default = 0 which means no set duration in minutes. [number]
					//'meta_category' => '', // Use to pass additional info to BBB server. See API docs.
			);
			
			// Create the meeting and get back a response:
			$itsAllGood = true;
			try {$result = $this->bbb->createMeetingWithXmlResponseArray($creationParams);
			}
			catch (Exception $e) {
				echo 'Caught exception: ', $e->getMessage(), "\n";
				$itsAllGood = false;
			}
			
			if ($itsAllGood == true) {
				// If it's all good, then we've interfaced with our BBB php api OK:
				if ($result == null) {
					// If we get a null response, then we're not getting any XML back from BBB.
					echo "Failed to get any response. Maybe we can't contact the BBB server.";
				}
				else {
					// We got an XML response, so let's see what it says:
					if ($result['returncode'] == 'SUCCESS') {
						// Besides the standard SQL fields, we have custom fields to parse
						$fc = new Fullcalendar();
						$fc->client = $client->id;
						$fc->supplier = $s->id;
						$fc->titre = $v->titre;
						$fc->description = $message;
						$fc->start_date = $time;
						$fc->date = $this->date;
							
						$this->fullcalendar = $fc->add();

						// Send message to client
						$m = new Messagerie();
						
						$m->client_src = $s->id;
						$m->client_dst = $client->id;
						$m->titre = 'Invitation à un web meeting';
						$m->message = $message;
						$m->date = date ("Y-m-d H:i:s");
						$m->add();
						
						$this->attendeePw = $client->email;
						$this->moderatorPw = $s->email;
						$this->status = self::STATUS_PLANNED;
						$this->add();
					}
					else {
						print_r($result);
						redirige(urlfond("formulerr", "errform=1"));
					}
				}
			}
		
		}
		
		public function joinIfRunning()
		{
			
			if ( ($this->status == self::STATUS_UNPLANNED) || ($this->status == self::STATUS_COMPLETED))
				return '';
				
			$v = new Venteprod();
			$v->charger($this->venteprod);
			$vdd = new Ventedeclidisp();
			$cmd = new Commande();
			$cmd->charger($v->commande);
			$vdd->charger($v->id);
			$s = new Client();
			$s->charger_id($vdd->supplier);
				
			$client = new Client();
			$client->charger_id($cmd->client);
				
			
			// Si le client essaie de se connecter, on verifie si la session est ouverte
			if ($_SESSION['navig']->client->id == $client->id)
				$this->joinWebMtgParticipant($this->venteprod,
						$_SESSION['navig']->client->prenom. ' '.$_SESSION['navig']->client->nom, 
						$_SESSION['navig']->client->email);
			else
				$this->joinWebMtgModerator($this->venteprod, 
						$_SESSION['navig']->client->prenom. ' '.$_SESSION['navig']->client->nom, 
						$_SESSION['navig']->client->email);
		}
		
		function joinWebMtgParticipant($venteprod, $username, $password)
		{
			
			$meetingId = $venteprod;
			$itsAllGood = true;
				
			try {$result = $this->bbb->isMeetingRunningWithXmlResponseArray($meetingId);
			}
			catch (Exception $e) {
				echo 'Caught exception: ', $e->getMessage(), "\n";
				$itsAllGood = false;
			}
			if ($itsAllGood == true) {
				//Output results to see what we're getting:
				//print_r($result);
				$status = $result['running'];
				//echo "<p style='color:red;'>".$status."</p>";
			
				$holdMessage = '';
			
				// The meeting is not running yet so hold your horses:
				if ($status == 'false') {
					return $holdMessage;
				}
				else {
					//Here we redirect the user to the joinUrl.
					// For now we output this:
			
					$joinParams = array(
							'meetingId' => $venteprod, // REQUIRED - We have to know which meeting to join.
							'username' => $_SESSION['navig']->client->prenom. ' '.$_SESSION['navig']->client->nom, // REQUIRED - The user display name that will show in the BBB meeting.
							'password' => $_SESSION['navig']->client->email, // REQUIRED - Must match either attendee or moderator pass for meeting.
							'createTime' => '', // OPTIONAL - string
							'userId' => '', // OPTIONAL - string
							'webVoiceConf' => '' // OPTIONAL - string
					);
						
					// Get the URL to join meeting:
					$allGood = true;
					try {$result = $this->bbb->getJoinMeetingURL($joinParams);
					}
					catch (Exception $e) {
						echo 'Caught exception: ', $e->getMessage(), "\n";
						$allGood = false;
					}
						
					if ($allGood == true) {
						//Output resulting URL. Send user there...
						//return $result;
						echo "<a href=\"$result\">webconf</a>";
					}
				}
			}		
		}
		function joinWebMtgModerator($venteprod, $username, $password)
		{
			// Moderator starts a session
			
			$joinParams = array(
					'meetingId' => $venteprod, // REQUIRED - We have to know which meeting to join.
					'username' => $username, // REQUIRED - The user display name that will show in the BBB meeting.
					'password' => $password, // REQUIRED - Must match either attendee or moderator pass for meeting.
					'createTime' => '', // OPTIONAL - string
					'userId' => '', // OPTIONAL - string
					'webVoiceConf' => '' // OPTIONAL - string
			);
			
			// Get the URL to join meeting:
			$itsAllGood = true;
			try {$result = $this->bbb->getJoinMeetingURL($joinParams);
			}
			catch (Exception $e) {
				echo 'Caught exception: ', $e->getMessage(), "\n";
				$itsAllGood = false;
			}
			
			if ($itsAllGood == true) {
				//Output results to see what we're getting:
				return $result;
				echo "<a href=\"$result\">webconf</a>";
			
			}
				
		}
		
		public function getWebMtgInfo()
		{
			/* ___________ GET MEETING INFO ______ */
			/* Get meeting info based on meeting id.
			 */
			
			$infoParams = array(
					'meetingId' => $this->venteprod, // REQUIRED - We have to know which meeting.
					'password' => $_SESSION['navig']->client->email, // REQUIRED - Must match moderator pass for meeting.
			
			);
			
			// Now get meeting info and display it:
			$itsAllGood = true;
			try {$result = $bbb->getMeetingInfoWithXmlResponseArray($infoParams);
			}
			catch (Exception $e) {
				echo 'Caught exception: ', $e->getMessage(), "\n";
				$itsAllGood = false;
			}
			
			if ($itsAllGood == true) {
				// If it's all good, then we've interfaced with our BBB php api OK:
				if ($result == null) {
					// If we get a null response, then we're not getting any XML back from BBB.
					echo "Failed to get any response. Maybe we can't contact the BBB server.";
				}
				else {
					// We got an XML response, so let's see what it says:
					var_dump($result);
					if (!isset($result['messageKey'])) {
						// Then do stuff ...
						echo "<p>Meeting info was found on the server.</p>";
					}
					else {
						echo "<p>Failed to get meeting info.</p>";
					}
				}
			}
				
		}
		
		public function listWebMtgs()
		{
			$itsAllGood = true;
			try {$result = $this->bbb->getMeetingsWithXmlResponseArray();
			}
			catch (Exception $e) {
				echo 'Caught exception: ', $e->getMessage(), "\n";
				$itsAllGood = false;
			}
			
			if ($itsAllGood == true) {
				// If it's all good, then we've interfaced with our BBB php api OK:
				if ($result == null) {
					// If we get a null response, then we're not getting any XML back from BBB.
					echo "Failed to get any response. Maybe we can't contact the BBB server.";
				}
				else {
					// We got an XML response, so let's see what it says:
					if ($result['returncode'] == 'SUCCESS') {
						// Then do stuff ...
						echo "<p>We got some meeting info from BBB:</p>";
						// You can parse this array how you like. For now we just do this:
						print_r($result);
					}
					else {
						echo "<p>We didn't get a success response. Instead we got this:</p>";
						print_r($result);
					}
				}
			}
			
				
		}
		
	}
?>
