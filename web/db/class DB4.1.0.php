<?php

/*
	
	Set up with DB4::ini(PDO_OBJ)
	In MYSQL the primary key should be an int with auto increase named ID
	
	INSTEAD OF USING new Class(id)
	You should use Class::get(id);
	
*/

class DB4{
	protected static $PDO;
	public $id = 0; 								// Each object must always have an ID. There must always be an id in objectfell
	protected $stored_vals = array();				// This contains the data that was loaded in. So that only the data that has changed gets saved.
	public $created = 0;							// These are optional but common enough to warrant auto usage
	public $updated = '';
	public $flags = 0;
	
	static $convert_colons = TRUE;					// Make sure : vars are unique
	/*
		
		Contains:
		{
			<className>:{
				OBJ:(arr)objs,
				SQL:(arr)fields
			}
		}
	
	*/
	static $_CACHE = NULL;							// Contains className : {id:(var)obj}
	public $_PREPARED = false;						// If this is set, the item won't be inserted until PMCharacterAsset::insertPrepared is called
	


/*

		!! YOU HAVE TO COPY THE FOLLOWING OVER TO THE EXTENDING CLASS

*/
// REQUIRED COPY OVER INTO NEW CLASS
	static $table = "";								// Here you set the table to read/save to
	protected static $insertFields = array();		// Fields that should be set on insert, even if they're in disregard_vals
	protected static $disregard_vals = array();		// Fields that should not be set on updates
	protected static $_PREPARE_QUEUE = array();		// Objects that are prepared for a batch insertion, optional and automated.

// OPTIONAL into new class

// Event handlers that you can copy over
	protected function onDataLoad(array $data){		// $data contains the data returned from mysl, so this is where you load that data into your class. Ex: $this->name = $data['name'];
		$this->autoload();
	}
	
	protected function onDataPreSave(){				// Optional, if you want to do actions right before data is saved
		// Raised before data is saved
	}
	protected function onDataPostSave(){			// Optional, if you want to do something after data is saved
		// Raised after save
	}
	protected function onInsertPre(){				// Optional
		//$this->created = time();
		return true;
	}
	protected function onInsertPost(){
		
	}
	protected function onDeletePre(){
		
	}
	protected function onDeletePost(){
		
	}
	
	protected function onConstruct(){return true;} 	// return any value except true in the overwriting function to prevent autoload
// End






// REQUIRED from config
	static function ini($pdo){
		static::$PDO = $pdo;
		static::$_CACHE = new stdClass;
	}
	
	

// CACHING
	// Returns an object containing the object index for the class that called the function, creates it if the class doesn't exist yet
	protected static function getCache(){
		$cname = get_called_class();
		if(!isset(self::$_CACHE->{$cname})){
			$obj = self::$_CACHE->{$cname} = new stdClass;
			$obj->OBJ = array();
			$obj->SQL = array();
		}
		return self::$_CACHE->{$cname};
	}
	
	// Returns fields in the table. Requires new <className> to be run first to be initialized
	protected static function getFields(){
		return static::getCache()->SQL;
	}
	
	// Takes an object and an array to test and returns TRUE if obj has all test keys and they are the same as test vals
	protected static function match($obj, array $test){
		foreach($test as $k=>$v){
			// Mismatch is one of the select
			if(!isset($obj->{$k}) || $obj->{$k} != $v){
				return false;
			}
		}
		return true;
	}
	
	// GET an object from cache. ID can either be an int, or an array like {id:(int)id, someVar:someVal}
	protected static function getFromCache($id){
		$cache = self::getCache()->OBJ;
		foreach($cache as $obj){
			// If ID is an array we make sure all array values are proper
			if(is_array($id)){
				if(self::match($cache, $id))
					return $obj;
			}
			// Otherwise we just check id
			else if((int)$obj->id === (int)$id){
				return $obj;
			}
		}
		return false;
	}
	
	// PUT to cache if not already cached
	protected function addToCache(){
		if($this->id === 0 || self::getFromCache($this->id) !== false)
			return;
		$cache = self::getCache();
		$cache->OBJ[] = $this;
	}
	

	
// SQL methods

	protected static function prepareParamtersForMultipleBindings($pstrSql, array $paBindings = array())
    {
        foreach($paBindings as $lstrBinding => $lmValue)
        {
            // $lnTermCount= substr_count($pstrSql, ':'.$lstrBinding);
            preg_match_all("/:".$lstrBinding."\b/", $pstrSql, $laMatches);

            $lnTermCount= (isset($laMatches[0])) ? count($laMatches[0]) : 0;

            if($lnTermCount > 1)
            {
                for($lnIndex = 1; $lnIndex <= $lnTermCount; $lnIndex++)
                {
                    $paBindings[$lstrBinding.'_'.$lnIndex] = $lmValue;
                }

                unset($paBindings[$lstrBinding]);
            }
        }
        return $paBindings;
    }
	protected static function prepareSqlForMultipleBindings($pstrSql, array $paBindings = array())
    {
        foreach($paBindings as $lstrBinding => $lmValue)
        {
            // $lnTermCount= substr_count($pstrSql, ':'.$lstrBinding);
            preg_match_all("/:".$lstrBinding."\b/", $pstrSql, $laMatches);

            $lnTermCount= (isset($laMatches[0])) ? count($laMatches[0]) : 0;

            if($lnTermCount > 1)
            {
                $lnCount= 0;
                $pstrSql= preg_replace_callback('(:'.$lstrBinding.'\b)', function($paMatches) use (&$lnCount) {
                    $lnCount++;
                    return sprintf("%s_%d", $paMatches[0], $lnCount);
                } , $pstrSql, $lnLimit = -1, $lnCount);
            }
        }

        return $pstrSql;
    }
	
	static function MQ($query, $vars = array(), $pdo = NULL){
		if($pdo === NULL)$pdo = static::$PDO;
		$vars = (array)$vars;
		$original = $query;
		
		// PDO doesn't allow multiple vars with the same :name label, so this is a workaround that renames them in sequence like :name, :name1, :name2...
		if(self::$convert_colons){
			$query = self::prepareSqlForMultipleBindings($query, $vars);
			$vars = self::prepareParamtersForMultipleBindings($query, $vars);
		}
		
		// Debug backtrace
		$backtrace = array();
		$bt = debug_backtrace();
		foreach($bt as $key=>$val){
			if(isset($val['file']) && isset($val['line']))
				$backtrace[] = $val['file'].'.ln'.$val['line'];
		}
		$debug = ' @ '.implode($backtrace, ' &lt;- ');
		
		// MYSQL missing
		if($pdo ===  NULL)
			die("MYSQL PDO undefined <br />".$debug);
		
		// Prepare the query
		try{
			$call = $pdo->prepare($query);
		}catch(Exception $e){ 
			die("PDO prepare error ".$e); 
		}
		if(!is_object($call))die("PDO prepare failed: ".$debug);
		// Execute the query
		$call->execute($vars)
			or die("MYSQL ERROR <br />".print_r($call->errorInfo(), true).'<br />'.$debug);
		return $call;
	}
	
	// MysqlInsertID Last insert id
	static function MII($pdo = NULL){
		if($pdo === NULL)$pdo = static::$PDO;
		return $pdo->lastInsertId();
	}
	
	// Mysql Affected Rows
	static function MAR($query){
		return $query->rowCount();
	}
	
	// MysqlNumRows
	static function MNR($query){
		return count($query->fetchAll());
	}
	
	// query -> get assoc - Returns an array of all results
	static function QGA($query, $vars = array(), $pdo = NULL){
		$vars = (array)$vars;
		$call = static::MQ($query, $vars, $pdo);
		return $call->fetchAll(PDO::FETCH_ASSOC);
	}
	
	// query -> get single assoc
	static function QGS($query, $vars = array(), $pdo = NULL){
		$vars = (array)$vars;
		$call = static::MQ($query, $vars, $pdo);
		return $call->fetch(PDO::FETCH_ASSOC);
	}
	
	// query -> get objects / all
	static function QGOA($query, $vars = array(), $pdo = NULL){
		$vars = (array)$vars;
		$q = self::QGA($query, $vars, $pdo);
		if($q === false)return array();
		$out = array();
		foreach($q as $val){
			// See if this object is cached and use that instead
			$o = self::getFromCache($val['id']);
			if($o !== false){
				$out[] = $o;
				continue;
			}
			
			
			$o = new static;
			$o->load($val);
			$out[] = $o;
		}
		return $out;
	}
	
	// query -> get objects / single
	static function QGOS($query, $vars = array(), $pdo = NULL){
		$vars = (array)$vars;
		$arr = self::QGS($query, $vars, $pdo);
		if($arr === false)return new static;
		
		// See if this object already exists
		$o = self::getFromCache($arr['id']);
		if($o !== false)
			return $o;
		
		$obj = new static;
		$obj->load($arr);
		return $obj;
	}
	
	
	// For simple queries you should use this over QGOS as it can fetch directly from cache without having to do a query first
	// $id can either be an ID or an associated array of multiple parameters have have to match exactly
	static function getSingle($id = 0){
		#echo 'Querying... ';
		static::verifySqlCache();			// Make sure we have the cols
		$cc = get_called_class();
		
		if($id == 0){
			return new static;		// Id is unset so just return a new object
		}

		$o = self::getFromCache($id);
		if($o !== false){
			#echo 'Dumping from cache';
			return $o;
		}
		
		// The object was not cached
		// Try to load it
		$fields = static::getFields();
		if(!is_array($id)){$id = array("id"=>$id);} 		// prepare the array
		
		$labels = array();		// Contains strings of `col`=?
		$vals = array();		// Contains the values for above
		foreach($id as $key=>$val){
			if(!in_array(strtolower($key),$fields)){
				die("Error, key ".htmlspecialchars($key)." does not exist in ".$cc);
			}
			$labels[] = '`'.$key.'`=?';
			$vals[] = $val;
		}
		
		#echo 'for class'.$cc." : SELECT * FROM ".static::$table." WHERE ".implode(' AND ', $labels)." LIMIT 1"." with vals being: ".json_encode($vals);
		$q = static::QGOS("SELECT * FROM ".static::$table." WHERE ".implode(' AND ', $labels)." LIMIT 1", $vals);;
		#echo "\n".'Q id is now '.$q->id;
		return $q;
	}
	
	// quickly Check if an ID exists
	static function idExists($id, $pdo = NULL){
		$call = static::MQ("SELECT id FROM ".static::$table." WHERE id=? LIMIT 1", array((int)$id), $pdo);
		return static::MNR($call);
	}
	
	// Use this instead of new
	static function get($id = 0){
		$obj = new static;
		if((int)$id && call_user_func_array(array($obj, "onConstruct"), func_get_args()) === true){
			$obj = static::getSingle($id);
		}
		return $obj;
	}
	
	// Clears object cache
	static function clearCache(){
		foreach(self::$_CACHE as $val){
			$val->OBJ = array();
		}
	}

	static function verifySqlCache(){
		// Make sure the table has it's colums cached if they aren't already
		$cache = static::getCache();
		if(empty($cache->SQL)){
			// We have to pull down the index
			$db = DB4::QGS('select database() AS db');
			$fields = DB4::QGA("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?", array($db['db'], static::$table));
			$cache->SQL = array_map(function($val){return $val['COLUMN_NAME'];}, $fields);
			
			if(empty($cache->SQL)){
				Tools::addNotice("No column info found for table ".htmlspecialchars(static::$table));
			}
		}
	}

// Methods you'll probably use
	function __construct(){					// Obvious construct is obvious
		$args = func_get_args();
		if(empty($args))$args = array(0);
		$id = $args[0];
		
		static::verifySqlCache();
		if((int)$id && call_user_func_array(array($this, "onConstruct"), $args) === true){
			$this->loadById($id);
		}
	}
	
	// Load an object from DB by id (which should be your primary key and an integer)
	public function loadByID($id){
		$q = self::QGS("SELECT * FROM ".static::$table." WHERE id=?", $id);
		if($q)$this->load($q);
	}
	
	// This is where you load data returned from mysql
	public function load(array $data){
		$this->stored_vals = $data;
		$this->id = (int)$data['id'];
		$this->addToCache();				// Cache this
		$this->onDataLoad($data);
	}
	
	public function exists(){
		return $this->id > 0;
	}
	
	// Delete this object from mysql
	public function delete(){
		if($this->onDeletePre() === false)
			return false;
		
		$out = static::MAR(static::MQ("DELETE FROM ".static::$table." WHERE id=?", array((int)$this->id)));
		$this->onDeletePost();
		
		return $out;
	}
	
	public function insert($debug = false){
		$this->created = time();
		return $this->save(true, false, $debug);
	}
	
		
	protected function autoload($data = NULL){
		if($data === NULL)$data = $this->stored_vals;
		$set = $this->typecast($data);
		foreach($set as $key=>$val){
			$this->{$key} = $val;
		}
	}
	
	private function typecast(array $data){
		$vars = get_class_vars(get_class($this));
		foreach($vars as $key=>$val){
			if(isset($data[$key]) || array_key_exists($key, $data)){
				if(is_array($val))$data[$key] = (array)json_decode($data[$key], true);
				else{
					$type = gettype($val);
					if($type === "boolean")$type = "integer";
					else if($type === "NULL" && $data[$key] !== NULL)$type = "string";
					settype($data[$key], $type);
				}
			}
		}
		return $data;
	}
	
	
	// Returns array of question marks with the same length as input
	static function a2q(array $input){
		foreach($input as $key=>$val)$input[$key] = "?";
		return $input;
	}
	static function q(array $input){
		return self::a2q($input);
	}
	// Does the same as the above functions except instead of ? it uses a question mark
	static function qn($prefix, array $input){
		$out = array();
		foreach($qn as $key=>$val)
			$out[] = $prefix;
		return $out;
	}
	
	
// BATCH INSERTS
	// Prepares an object for a batch insert
	// Keep in mind that no IDs will be assigned for these assets, so make sure to reload these from DB if you need them again after
	public function prepare(){
		$this->_PREPARED = true;
		$this->id = 0;
		static::$_PREPARE_QUEUE[] = $this;
	}

	// insert all prepared assets
	static function insertPrepared(){
		if(!count(static::$_PREPARE_QUEUE))return true;
		$qs = array();		// Question marks
		$vals = array(); 	// Question mark values
		
		// Sanitize the fields
		$cols = static::getFields();
		$c = array();
		
		// Convert to lower case
		$if = static::$insertFields;
		$dv = static::$disregard_vals;
		foreach($if as $key=>$val)$if[$key] = strtolower($val);
		foreach($dv as $key=>$val)$dv[$key] = strtolower($val);
		
		foreach($cols as $val){
			if($val === "id")continue;									// ID should not be present in insert
			if(in_array($val, $dv) && !in_array($val, $if))continue;	// This value should never be saved, likely a value with ON_UPDATE
			$c[] = $val;	
		}
		$qblock = '('.implode(',', self::q($c)).')';
		
		foreach(static::$_PREPARE_QUEUE as $val){
			$qs[] = $qblock;
			
			foreach($c as $field){
				$v = $val->{$field};
				if(is_object($v) || is_array($v))$v = json_encode($v);
				$vals[] = $v;
			}
		}
		
		static::MQ("INSERT INTO ".static::$table." (`".implode('`,`',$c)."`) VALUES ".join(',', $qs), $vals);
		
		// Finish up
		static::$_PREPARE_QUEUE = array();
		return true;
	}


	
// SAVE/LOAD	
	// Save will fail if insert is false and id is 0
	// If insert is set, ID will be auto set to 0
	public function save($insert = false, $ignore_errors = false, $debug = false){
		static::verifySqlCache();
		
		// This object does not exist and we're trying to save without inserting, so ignore it.
		if(!$insert && !$this->id){
			Tools::addError("Unable to save a zero ID");
			return false;
		}
		
		if($this->_PREPARED){
			Tools::addError("Unable to save an object that is prepared for a batch insert. Use insertPrepared instead");
			return false;
		}
		
		if($insert === true){
			$this->stored_vals = array();
			$this->id = 0;
			$this->onInsertPre();
		}
		else $this->onDataPreSave();
		
		$f = self::getFields();
		$fields = array();				// Fields to add/update
		$vals = array();				// Data to add/update with
		
		if($debug){
			Tools::addNotice("Scanning ".json_encode($f));
		}
		
		// Check viable fields
		foreach($f as $val){
			// Allow this field IF
			if(
				$val === 'id' ||												// Always include ID because it's needed on UPDATE
				(
					(
						!in_array($val, static::$disregard_vals) ||				// Not in disregard vals
						(in_array($val, static::$insertFields) && $insert)		// OR this is an insert and it's in insert fields
					) &&														// And
					isset($this->{$val}) &&								// Value exists in the first place
					(
						empty($this->stored_vals) ||							// No values are present (probable cause is a new insert)
						$this->{$val} != $this->stored_vals[$val] 				// OR the value has changed
					)
				)
			){
				if($insert && $val === 'id')continue;							// ID should be disregarded on insert, and value should be ignored if not set in the extending class
				$fields[] = '`'.$val.'`';
				$val = $this->{$val};
				if($val === '')$val = NULL;
				if(is_object($val) || is_array($val))$val = json_encode($val);
				$vals[] = $val;
			}
		}
		
		if(empty($fields)){
			Tools::addError("No data in object");
			return false;										// no data to save
		}
		
		// Question marks
		$qs = self::a2q($fields);
		
		
		/*
		echo 'Q: '.'INSERT INTO '.static::$table." (".implode(',', $fields).") VALUES (".implode(',',$qs).") ON DUPLICATE KEY UPDATE ".implode(',',$update)."\n";
		
		echo 'V: '.json_encode($vals)."\n";
		return;
		*/
		
		$q = "";
		
		// INSERT
		if($insert){
			
			$q = 'INSERT '.($ignore_errors ? 'IGNORE' : '').' INTO '.static::$table." (".implode(',', $fields).") VALUES (".implode(',',$qs).") ON DUPLICATE KEY UPDATE id=id";
		}
		
		// UPDATE
		else{
			$update = array();
			foreach($fields as $val)
				$update[] = $val.'=?';
				
			$q = "UPDATE ".static::$table." SET ".implode(',',$update)." WHERE id=?";
			$vals[] = $this->id;
		}
		
		$query = static::MQ($q, $vals);
		
		if($this->id === 0){
			$this->id = static::MII();
		}
		
		if($insert)$this->onInsertPost();
		else $this->onDataPostSave();
		
		return true;
	} 
	
	// Generic function
	static function getConsts($prefix = false){
		$refl = new ReflectionClass(get_called_class());
		$consts = $refl->getConstants();
		if($prefix === false)return $consts;
		$out = array();
		foreach($consts as $key=>$val){
			if(strrpos($key, $prefix, -strlen($key)) !== FALSE)$out[$key] = $val;
		}
		return $out;
	}		

	

}

