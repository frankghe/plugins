<?php

include_once(realpath(dirname(__FILE__)) . "/../Pluginsthext/PluginsThext.class.php");
loadPlugin('dbbrowser');
	
	class Contact extends PluginsThext{

		const TABLE = 'contact';
		
		public function __construct( $id=0 ){
			parent::__construct("contact");	

			if($id > 0)
				$this->charger_id($id);
			
		}

		public function charger($id){		
			return $this->getVars("select * from $this->table where id=\"$id\"");
		}


		public function init(){									
			$query = "
				CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `email` varchar(128) NOT NULL DEFAULT '' COMMENT 'global=>label=Votre Email',
				  `message` text COMMENT 'global=>label=Votre Email',
				  `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT 'edit=>display=false',
				  PRIMARY KEY (`id`)
				) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
						";
			
			$resul_commentaires = $this->query($query);
				
		}

		public function destroy(){
		}		


		public function action(){
			switch ($_REQUEST['action']) {
				case 'contact_update':
					$this->fillFields($_REQUEST);
					$this->add();
					break;
				default:
			}
		}
		
		// Support for dbbroswer - add current time
		public function dbb_date($mode = 'edit'){
			$out = '';
			switch ($mode) {
				case 'edit':
					break;
				case 'update':
					$out = date ("Y-m-d H:i:s");
			}
			return $out;
		}
			
	}
?>
