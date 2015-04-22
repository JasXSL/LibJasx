<?php
	
	class AjaxCore{
		static $task = '';
		static $data = array();
		static $vals = array();
		static $redir = false;
		
		// Optional methods
		protected static function preInit(){
			
		}
		protected static function postInit(){
			
		}
		
		
		public static function init(){
			if(!isset($_POST['call'])){
				Tools::addError("Unable to load JSON. Task or data missing.");
				return false;
			}
			
			$data = (array)json_decode($_POST['call'], true);
			self::$task = array_shift($data);
			self::$data = $data;
			static::preInit();
			
			if(!method_exists(__CLASS__, 'pub'.self::$task)){
				Tools::addError("Method does not exist: ".htmlspecialchars(self::$task));
				self::finish();
				continue;
			}
				
			$r = new ReflectionMethod(__CLASS__, 'pub'.self::$task);
			if(count(self::$in_data)<$r->getNumberOfRequiredParameters()){
				Tools::addError("Invalid amount of parameters for ".htmlspecialchars(self::$task));
				self::finish();
				continue;
			}
				
			if(!call_user_func_array(__CLASS__.'::'.'pub'.self::$task, self::$data))self::finish();
			
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



