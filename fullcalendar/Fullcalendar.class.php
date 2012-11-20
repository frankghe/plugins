<?php

include_once(realpath(dirname(__FILE__)) . "/../Pluginsthext/PluginsThext.class.php");
	
	
	class Fullcalendar extends PluginsThext{
		
		const TABLE = 'fullcalendar';

		public function __construct(  $id = 0 ){
			parent::__construct("fullcalendar");	

			if($id > 0)
				$this->charger_id($id);
				
		}

		public function init(){									
			$query = "
				CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `client` int(11) NOT NULL DEFAULT '0',
				  `supplier` int(11) NOT NULL DEFAULT '0',
				  `titre` text,
				  `description` text,
				  `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				  `url` text,
				  `start_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				  `end_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				  `category` varchar(128) DEFAULT NULL,
				  PRIMARY KEY (`id`)
				) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
						";
			
			$result = $this->query($query);
				
		}

		public function destroy(){
		}		


		public function action(){
			if($_REQUEST['action'] == "fullcalendar_newentry"){
			
				$fc = new Fullcalendar();
				$fc->client = $_POST['fullcalendar_client'];
				$fc->supplier = $_POST['fullcalendar_supplier'];
				$fc->category = $_POST['fullcalendar_category'];
				$fc->titre = $_POST['fullcalendar_titre'];
				$fc->description = $_POST['fullcalendar_description'];
				$fc->start_date = $_POST['fullcalendar_start_date'];
				$fc->end_date = $_POST[  'fullcalendar_end_date'];
				$fc->date = date("Y-m-d H:i:s");
			
				$fc->add();
			
			}
					
		}
		
		
		//
		// Support functions for dbbrowser plugin
		//
		
		public function dbbrowser_fieldlookup($field)
		{
			switch ($field) {
				case 'supplier':
					$out = 'client';
					break;
				default:
					$out = '';
			}
			return $out;
		}
		
	}
?>
