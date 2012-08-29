<?php

include_once(realpath(dirname(__FILE__)) . "/../../../classes/PluginsClassiques.class.php");
	
	
	class Bouclegen extends PluginsClassiques{

		public $id;
		
		const TABLE = 'bouclegen';

		public $table = self::TABLE;

		
		public function __construct(){
			parent::__construct("bouclegen");	
		}

		public function charger($id){		
		}


		public function init(){									
		}

		public function destroy(){
		}		

		public function boucle($texte, $args){
			
			$out = '';
			// récupération des arguments
			$query = lireTag($args, "query");				

			$result = $this->query($query);				
				
			if ($result) {
			
				$nbres = $this->num_rows($result);
			
				if ($nbres > 0) {
					$columns = mysql_num_fields($result);
					while( $row = $this->fetch_object($result)){
			
						$temp = $texte;
						// For each field of the request, generate corresponding html tag and 
						// replace in text			
						foreach ($row as $field => $value) {
							$fname_up = strtoupper($field);
							$temp = str_replace("#$fname_up", $value, $temp);
						}
						$out.=$temp;
					}
				}
			
			}
			
			return $out;
			
				
		}	

		public function action(){
		}
		
	}
?>
