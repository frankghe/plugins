<?php

require_once(realpath(dirname(__FILE__)) . "/../Pluginsthext/PluginsThext.class.php");

	class Map extends PluginsThext{

		const TABLE = 'maps_ville';
		
		public function __construct( $id = 0 ){
			parent::__construct("maps_ville");	

			if($id > 0)
				$this->charger_id($id);
			
		}
		
		public function charger_nom($nom){
			return $this->getVars("select * from $this->table where nom=\"$nom\"");
		}
		
		public function charger_cp($cp){
			return $this->getVars("select * from $this->table where cp=\"$cp\"");
		}
		
		public function init(){		

			// FIXME We manually export the table...
			return ;
			
			$query = "
				CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
				  `nom` varchar(255) DEFAULT NULL,
				  `cp` varchar(255) DEFAULT NULL,
				  `latitude` float(7,6) NOT NULL DEFAULT '0.000000',
				  `longitude` float(7,6) NOT NULL DEFAULT '0.000000',
				  `eloignement` varchar(255) DEFAULT NULL,
				  `url` varchar(255) DEFAULT NULL
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;
						";
			$result = $this->query($query);
				
		}

		public function destroy(){
		}		

		public function boucle($texte, $args){			
		}	

		public function action(){
		}
		
		public function distance($lat1, $lng1, $lat2, $lng2, $miles = false)
		{
			$pi80 = M_PI / 180;
			$lat1 *= $pi80;
			$lng1 *= $pi80;
			$lat2 *= $pi80;
			$lng2 *= $pi80;
		
			$r = 6372.797; // mean radius of Earth in km
			$dlat = $lat2 - $lat1;
			$dlng = $lng2 - $lng1;
			$a = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlng / 2) * sin($dlng / 2);
			$c = 2 * atan2(sqrt($a), sqrt(1 - $a));
			$km = $r * $c;
		
			return ($miles ? ($km * 0.621371192) : $km);
		}		
		
		public function dist($lat1, $lon1, $lat2, $lon2){
			$distance = (3958*3.1415926*sqrt(($lat2-$lat1)*($lat2-$lat1) + cos($lat2/57.29578)*cos($lat1/57.29578)*($lon2-$lon1)*($lon2-$lon1))/180);			
			return $distance;
		}
		
		public function getDistanceBetweenPointsNew($latitude1, $longitude1, $latitude2, $longitude2, $unit = 'Km') {
			$theta = $longitude1 - $longitude2; 
			$distance = (sin(deg2rad($latitude1)) * sin(deg2rad($latitude2))) + 
						(cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * cos(deg2rad($theta))); 
			$distance = acos($distance); $distance = rad2deg($distance); 
			$distance = $distance * 60 * 1.1515; 
			switch($unit) {
				case 'Mi': break; 
				case 'Km' : $distance = $distance * 1.609344;
			} return (round($distance,2));
		}
				
		public function distance_ville($ville){
			if ( $this->nom != '' &&  ($ville->nom != ''))
				$out = $this->getDistanceBetweenPointsNew($this->latitude, $this->longitude,
									$ville->latitude, $ville->longitude);
			else
				$out = "- ";
			return $out;
		}
		
	}
?>
		