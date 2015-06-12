<?php
	
	class AjaxCore{
		static $task = '';
		static $data = array();
		static $vals = array();
		static $redir = false;
		
		// Optional methods
		protected static function preInit(){
			return true;
		}
		protected static function postInit(){
			
		}
		

		
		public static function init(){
			if(!isset($_GET['t'])){
				Tools::addError("Unable to load JSON. Task missing.");
				return false;
			}
			if(isset($_GET['csrf']) && $_GET['csrf'] != $_SESSION['CSRF_TOKEN']){
				Tools::addError("Invalid CSRF");
				return false;
			}
			self::$task = $_GET['t'];
			
			if(isset($_GET['d']))self::$data = (array)json_decode($_GET['d'], true);
			
			if(!static::preInit())continue;
			
			if(!method_exists(get_called_class(), 'pub'.self::$task)){
				Tools::addError("Method does not exist: pub".htmlspecialchars(self::$task));
				self::finish();
				continue;
			}
				
			$r = new ReflectionMethod(get_called_class(), 'pub'.self::$task);
			if(count(self::$data)<$r->getNumberOfRequiredParameters()){
				Tools::addError("Invalid amount of parameters for ".htmlspecialchars(self::$task));
				self::finish();
				continue;
			}
				
			if(!call_user_func_array(get_called_class().'::'.'pub'.self::$task, self::$data))self::finish();
			
			static::postInit();
			
			self::finish(true);			
			return true;
		}
		
		public static function setVals($vals){
			self::$vals = $vals;
		}
		
		public static function setRedir($redir){
			self::$redir = $redir;
		}
		
		public static function finish($success = false){
			header('Content-Type: application/json');
			$output = array(
				"vars" => self::$vals,
				"err" => Tools::$ERRORS,
				"note" => Tools::$NOTICES,
				"succ" => $success,
				"redir"=>self::$redir
			);
			die(json_encode($output));
		}
		

	}



