<?php


class db {

	var $lastError;					// Holds the last error
	var $lastErrno;					// Holds the last error code number
	var $lastQuery;					// Holds the last query
	var $result;					// Holds the MySQL query result
	var $records;					// Holds the total number of records returned
	var $affected;					// Holds the total number of records affected
	var $rawResults;				// Holds raw 'arrayed' results
	var $arrayedResult;				// Holds an array of the result
	var $key=null;					// Holds the crypt key
	var $database=null;				// Holds the selected database
	var $databaseLink;				// Database Connection Link

	/**
	 * starts a conncetion with a database
	 * @param string $username the username to connect with the database
	 * @param string $password the password to connect with the database
	 * @param string $host     the host where to connect
	 * @param string $db 	   optional-to set at the constuctor the database
	 * @param string $key      optional-to set the crypt key
	 */
	public function __construct($username,$password,$host,$db=null,$key=null){
		$this->connect($username,$password,$host,$db);
		if($key!=null && is_string($key))
			$this->setKey($key);
	}

	/**
	 * set the crypt key
	 * @param string $key the key for the crypt operations
	 */
	public function setKey($key){
		if($key!=null && is_string($key))
			$this->key=$key;
		else
			throw new Exception("Operation Failed: Couldn't set the crypt key!");
			
	}

	/**
	 * starts a connection with a database
	 * @param  string $username the username to connect with the database
	 * @param  string $password the password to connect with the database
	 * @param  string $host     the host where to connect
	 * @param  string $database optional-to set at the constuctor the database
	 * @return bool             returns false when the connection failed or the database couldn't be selected and true if it is working
	 */
	public function connect($username,$password,$host,$db=null){
		$this->CloseConnection();
		$this->databaseLink = mysqli_connect($host,$username,$password,"",3306);
		if(!$this->databaseLink){
			throw new Exception('Could not connect to server: ' . mysqli_connect_errno($this->databaseLink) .mysqli_error($this->databaseLink));
			return false;
		}
		if($db!=null)
			return $this->UseDB($db);
		else 
			return true;
	}

	/**
	 * selects a database
	 * @param  string $db the database to select
	 * @return bool      returns false when the database couldn't be selected and true if it could selected
	 */
	private function useDB($db){
		if(!mysqli_select_db($this->databaseLink,$db)){
			$this->database=null;
			throw new Exception('Cannot select database: ' . mysqli_error($this->databaseLink));
			return false;
		} else {
			$this->database=$db;
			return true;
		}
	}

	/**
	 * execute SQL query
	 * @param stringt $query the sql query
	 * @return mixed         returns true if the query could executed and doesn't return a result
	 *                       returns false if the query couldn't executed
	 *                       returns an array or string if the query could executed and has a result
	 */
	public function ExecuteSQL($query){
		if($this->database==null){
			throw new Exception("Operation Failed: Couldn't execute SQL without selected database!", 1);
		} else {
			$this->lastQuery = $query;
			if($this->result 	= mysqli_query($this->databaseLink,$query)){
				$this->records 	= @mysqli_num_rows($this->result);
				$this->affected	= @mysqli_affected_rows($this->databaseLink);
				if($this->records > 0){
					$this->ArrayResults();
               		return $this->arrayedResult;
        		}else{
        			return true;
           		}
			}else{
				$this->lastErrno = mysqli_errno($this->databaseLink);
				$this->lastError = mysqli_error($this->databaseLink);
				throw new Exception("Operation Failed: [".$this->lastErrno."] ".$this->lastError." ".$query);
				return false;
			}
		}
	}

	/**
	 * convert the result of an sql query to an array
	 * @return array the result as an array
	 */
	protected function ArrayResults(){
		$this->arrayedResult = array();
		while ($data = mysqli_fetch_assoc($this->result)){
			$this->arrayedResult[] = $data;
		}
		return $this->arrayedResult;
	}

	/**
	 * returns the last ID inserted in a table
	 * @return integer the last inserted ID
	 */
 	public function getLastInsertID(){
		return mysqli_insert_id($this->databaseLink);
	}

 	/**
 	 * returns the counted rows that returned by a select
 	 * @param string $from   the table where the select is on to perform
 	 * @param object $object a list of db objects which are used as record objects
 	 * @return integer 		 the number of counted rows
 	 */
 	public function CountRows($from,... $object){
 		$object[]=new dbSelect("count(*)");
		$result = $this->Select($from,$object);
		return $result[0]["count(*)"];
	}

	/**
	 * returns all columns in a table
	 * @param  string $from the table
	 * @return array        columns in a table
	 */
 	public function getColumns($from){
 		$from=self::SecureData($from);
 		$data=self::ExecuteSQL("SHOW COLUMNS FROM ".$from.";");
 		$columns=array();
 		foreach ($data as $value) {
 			$columns[]=$value['Field'];
 		}
 		return $columns;
	}
	
	/**
	 * secureData escapes a string or object to opposite an sql injection
	 * @param  mixed $data an array,string or object to 'save'
	 * @return mixed returns the $data element 'saved'
	 */
	protected function SecureData($data){
		if(is_array($data)){	//prove if $data is an array -> if array function runs recursive to get a string or an object to 'save'
			foreach ($data as $index => $value) {
				$data[$index]=self::SecureData($value); //runs function recursive
			}
			return $data;
		} else if(is_object($data) && method_exists($data,'save')){ // prove if $data is an object and has the save method -> not all db objects have a save method
			$data->save($this->databaseLink,self::getCryptedFields($data));
			return $data;
		} else if(!is_object($data)) //prove if $data is a string
			return mysqli_real_escape_string($this->databaseLink,$data); //escape the string
		  else // $data is something other
			return $data;
	}

	/**
	 * returns all crypted column set by objects
	 * @param  object $objects a list of db objects which are used as record objects
	 * @return array           a list of all crypted columns
	 */
	protected function getCryptedFields($objects){
		$cryptedColumn=array();
		foreach ($objects as $object) {
			if($object instanceof dbCrypt)
				$cryptedColumn=array_merge($object->columns,$cryptedColumn);
		}
		return $cryptedColumn;
	}

	/**
	 * returns a list of all columns in a table
	 * @param  string $table the table name
	 * @return array         a list of all columns in a table
	 */
	protected function getColumnsFromTable($table){
		$tmp=$this->ExecuteSQL("SHOW COLUMNS FROM `".$table."`;");
		if(!is_array($tmp)) return array();
		$data=array();
		foreach ($tmp as $value) {
			$data[]=$value['Field'];
		}
		return $data;
	}

	/**
	 * runs a select query
	 * @param string $table   the table name
	 * @param object $objects a list of db objects which are used as record objects
	 */
	public function Select($table,... $objects){
		$cryptedColumn=$this->getCryptedFields($objects);
		$selectColumn=array();
		if(count($cryptedColumn)>0 && $this->key==null)
			throw new Exception("Operation Failed: No crypt key is set!");
			
		foreach ($objects as $object) {
			if(get_class($object)=="dbSelect"){
				$selectColumn=array_merge($selectColumn,$object->column);
			}
		}
		if(count($cryptedColumn)>0){ //crypted fields
			if(count($selectColumn)==0) //all columns
				$selectColumn=$this->getColumnsFromTable($table);
			foreach ($selectColumn as $key => $column) {
				if(in_array($column,$cryptedColumn))
					$selectColumn[$key]="AES_DECRYPT(`".$column."`,'".$this->key."') AS ".$column;
				else
					$selectColumn[$key]="`$column`";
			}
		} else if(count($cryptedColumn)==0 && count($selectColumn)==0){ //no crypted fields and all column selected
			$selectColumn[]="*";
		}
		$query ="SELECT ";
		foreach ($selectColumn as $column) {
			$query.=$column.", ";
		}
		$query=substr($query,0,-2);
		$query.=" FROM `".self::SecureData($table)."` ";
		$query.=self::buildClauses(... $objects).";";
		$data=$this->ExecuteSQL($query);

		return $data;
	}

	/**
	 * runs a insert query
	 * @param string $table   the table name
	 * @param array  $vars    a list of data which should be insert in a table; 
	 *                        organized by column=>value
	 * @param object $objects a list of db objects which are used as record objects
	 */
	public function Insert($table,$vars,... $objects){
		if(isset($vars[0]) && is_array($vars[0])){
			foreach ($vars as $value) {
				$this->Insert($table,$value,... $objects);
			}
			return true;
		}
		$cryptedColumn=self::getCryptedFields($objects);
		if(count($cryptedColumn)>0 && $this->key==null)
			throw new Exception("Operation Failed: Couldn't Insert Data without a crypted key!");

		$vars = $this->SecureData($vars);
		$query = "INSERT INTO `{$table}` SET ";

		foreach($vars as $key=>$value){
			if($value instanceof dbFunc)
				$query .= "`{$key}` = {$value->func}, ";
			else if(in_array($key,$cryptedColumn))
				$query .= "`{$key}` = AES_ENCRYPT('{$value}','".$this->key."'), ";
			else
				$query .= "`{$key}` = '{$value}', ";

		}
		$query = substr($query, 0, -2);
		return $this->ExecuteSQL($query);
	}


	/************************************************************************************************\
	# Funktion:                                                                                      #
	#   - führt einen delete befehl durch 							                                 #
	#																								 #
	\************************************************************************************************/

	public function Delete($table,... $objects){
		$query ="DELETE FROM `".self::SecureData($table)."` ";
		$query.=self::buildClauses(... $objects).";";
		return $this->ExecuteSQL($query);
	}


	/************************************************************************************************\
	# Funktion:                                                                                      #
	#   - führt einen update befehl durch 							                                 #
	#																								 #
	\************************************************************************************************/

	public function Update($table,$set=array(),... $objects){
		if(count($set)==0) return false;

		$cryptedColumn=self::getCryptedFields($objects);
		if(count($cryptedColumn)>0 && $this->key==null)
			throw new Exception("Operation Failed: Couldn't Insert Data without a crypted key!");

		$set   = self::SecureData($set);
		$query = "UPDATE `".self::SecureData($table)."` SET ";
		foreach($set as $key=>$value){
			if($value instanceof dbNot)
				$query .= "`{$key}` = !".$key.", ";
			else if($value instanceof dbInc)
				$query .= "`{$key}` = `{$key}` + '{$value->num}', ";
			else if($value instanceof dbFunc)
				$query .= "`{$key}` = '{$value->func}', ";
			else if(in_array($key,$cryptedColumn))
				$query .= "`{$key}` = AES_ENCRYPT('{$value}','".$this->key."'), ";
			else
				$query .= "`{$key}` = '{$value}', ";

		}
		$query =substr($query, 0, -2)." ";
		$query.=self::buildClauses(... $objects).";";
		return $this->ExecuteSQL($query);
	}


	/************************************************************************************************\
	# Funktion:                                                                                      #
	#   - gibt die letzte Auto Increment Zahl einer Tabelle zurück	                                 #
	#																								 #
	\************************************************************************************************/

	public function LastInsertID(){
		return mysqli_insert_id($this->databaseLink);
	}

	/************************************************************************************************\
	# Funktion:                                                                                      #
	#   - schließt eine Verbindung				 					                                 #
	#																								 #
	\************************************************************************************************/

	public function CloseConnection(){
		if($this->databaseLink){
			mysqli_close($this->databaseLink);
		}
	}
	

	/************************************************************************************************\
	# Funktion:                                                                                      #
	#   - wandelt einen string in einen gesichtern string um -> get manipulation vorbeugen           #
	#																								 #
	\************************************************************************************************/

	public function save($save){
	    return mysqli_real_escape_string($this->databaseLink,$save);
	}


	/************************************************************************************************\
	# Funktion:                                                                                      #
	#   - wandelt die db Objekt um in einen string 					                                 #
	#	- bringt die db Objekte in die richtige reihenfolge											 #
	#																								 #
	\************************************************************************************************/
  
	protected function buildClauses(... $objects){
		if(count($objects)==0) return;
		$clauses=array();
		//sql abschnitte erzeugen 
		foreach ($objects as $object) {
			if(method_exists($object,'save'))
				$object->save($this->databaseLink,self::getCryptedFields($objects));
			if(method_exists($object,'setKey'))
				$object->setKey($this->key);
			if(method_exists($object,'build'))
				$clauses[get_class($object)][]=$object->build();
		}
		//sql string zusammensetzen
		$return="";
		foreach (array("dbJoin","dbCondBlock","dbCond","dbOrder","dbLimit") as $classes) {
			if(isset($clauses[$classes])){
				foreach ($clauses[$classes] as $subclauses) {
					$return.=$subclauses." ";
				}
			}
		}
		return $return;
	}
}


/************************************************************************************************\
# Klasse:                                                                                      	 #
#   - Record-Klasse: Informationsspeicher, keine Funktionalität im eigentlichen Sinne            #
#   - Informationen über zusätzliche Joins						                                 #
#																								 #
\************************************************************************************************/

class dbLimit extends dbMain{
	var $start;
	var $limit;

	public function __construct($start,$limit){
		$this->start=$start;
		$this->limit=$limit;
	}

	public function save($link,$crypted_column){
		$start=mysqli_real_escape_string($link,$this->start);
		$limit=mysqli_real_escape_string($link,$this->limit);
	}

	public function build(){
		return "LIMIT ".$this->start.",".$this->limit;
	}
}

/************************************************************************************************\
# Klasse:                                                                                      	 #
#   - Record-Klasse: Informationsspeicher, keine Funktionalität im eigentlichen Sinne            #
#   - Informationen über zusätzliche Joins						                                 #
#																								 #
\************************************************************************************************/

class dbJoin extends dbMain{
	var $source;
	var $destination;

	public function __construct($source,$destination){
		$this->source=$source;
		$this->destination=$destination;
	}

	public function save($link,$crypted_column){
		$this->source=mysqli_real_escape_string($link,$this->source);
		$this->destination=mysqli_real_escape_string($link,$this->destination);
	}

	public function build(){
		return "JOIN ".strtok($this->source,'.')." ON ".$this->source."=".$this->destination;
	}
}

/************************************************************************************************\
# Klasse:                                                                                      	 #
#   - Record-Klasse: Informationsspeicher, keine Funktionalität im eigentlichen Sinne            #
#   - Informationen über Einbindungsklausel in einem MySQL-String                                #
#																								 #
\************************************************************************************************/
class dbCond extends dbMain{
	var $column;
	var $cond;
	var $operator;
	var $connect;
	var $crypted_column;
	var $key;

	public function __construct($column,$cond,$operator="=",$connect="AND"){
		$this->column=$column;
		$this->cond=$cond;
		$this->operator=$operator;
		$this->connect=$connect;
	}

	public function setKey($key){
		$this->key=$key;
	}

	public function save($link,$crypted_column){
		$this->crypted_column=$crypted_column;

		$this->column=mysqli_real_escape_string($link,$this->column);
		$this->cond=mysqli_real_escape_string($link,$this->cond);
		$this->operator=mysqli_real_escape_string($link,$this->operator);
		$this->connect=mysqli_real_escape_string($link,$this->connect);
	}

	public function build($operatian="dbCond"){
		$query="";
		//prove if object stands alone or in an condition block
		if($operatian=="dbCond") $query.="WHERE ";
		else 				 	 $query.=$this->connect." ";

		if(in_array($this->column, $this->crypted_column) && ($this->cond instanceof dbNot || 
				$this->cond instanceof dbFun)){
			throw new Exception("Auf verschlüsselte Datensätze können nicht alle Operationen angewandt werden.");
			return "";
		}

		if($this->cond instanceof dbNot) 
			$query.=$this->column."= !".$this->$column;
		else if($this->cond instanceof dbFun) 
			$query.="`".$this->column."` ".$this->operator." ".$this->cond->func;
		else if($this->operator=="LIKE" && !in_array($this->column, $this->crypted_column)) //LIKE and not crypted
			$query.="`".$this->column."` ".$this->operator." '%".$this->cond."%'";
		else if($this->operator=="LIKE" && in_array($this->column, $this->crypted_column))  //LIKE and crypted
			$query.="CONVERT(AES_DECRYPT(`".$this->column."`,'".$this->key."') USING utf8) ".$this->operator." '%".$this->cond."%'";
		else if(in_array($this->column, $this->crypted_column))	   		 					//crypted
			$query.="CONVERT(AES_DECRYPT(`".$this->column."`,'".$this->key."') USING utf8) ".$this->operator." '".$this->cond."'";
		else																				//not crypted
			$query.="`".$this->column."` ".$this->operator." '".$this->cond."'";
		return $query;
	}
}

/************************************************************************************************\
# Klasse:                                                                                      	 #
#   - Record-Klasse: Informationsspeicher, keine Funktionalität im eigentlichen Sinne            #
#   - Informationen über Einbindungsklausel in einem MySQL-String                                #
#																								 #
\************************************************************************************************/

class dbCondBlock extends dbMain{
	var $cond=array();
	var $key;

	public function __construct(... $conditions){
		foreach ($conditions as $cond) {
			if($cond instanceof dbCond)
				$this->cond[]=$cond;
		}
	}

	public function setKey($key){
		$this->key=$key;
	}

	public function save($link,$crypted_column){
		foreach ($this->cond as $conditions) {
			$conditions->save($link,$crypted_column);
		}
	}

	public function build(){
		$query="";
		foreach ($this->cond as $key => $condition) {
			$condition->setKey($this->key);
			$query.=$condition->build("dbCondBlock").' ';
		}
		$query="WHERE ".substr($query, strlen($this->cond[0]->connect));
		return $query;
	}
}

/**
 * record class to create an incremation at the SQL-string
 */
class dbInc{
	var $num;

	public function __construct($num){
		$this->num=$num;
	}

	public function save($link,$crypted_column){
		$this->num=mysqli_real_escape_string($link,$this->num);
	}
}

/**
 * record class to create an inversion at the SQL-string
 */

class dbNot{

}

/**
 * record class to save data for an function based on SQL
 */
class dbFunc{
	var $func;		//SQL function

	public function __construct($func){
		$this->func=$func;
	}
}


/**
 * record class to save data for an SQL-order operation
 */
class dbOrder extends dbMain{
	var $column;		//the column where should be ordered on
	var $direction;		//the order algorithm

	/**
	 * sets the data for an SQL-order operation
	 * @param string $column    the column where should be ordered on
	 * @param string $direction the order algorithm
	 */
	public function __construct($column,$direction){
		$this->column=$column;
		$this->direction=$direction;
	}

	public function save($link,$crypted_column){
		$this->column=mysqli_real_escape_string($link,$this->column);
		$this->direction=mysqli_real_escape_string($link,$this->direction);
	}

	/**
	 * creates the where-SQL-string for the complete SQL-query 
	 * @return string where operation string
	 */
	public function build(){
		return "ORDER BY ".$this->column." ".$this->direction;
	}
}

class dbSelect extends dbMain{
	var $column;

	public function __construct(... $collumn){
		$this->column=$column;
	}

	public function save($link,$crypted_column){

	}

	public function build(){
		$query="";
		foreach ($this->column as $column) {
			$query='`'.$column.'`,';
		}
		return substr($query,0,-1);
	}
}
/************************************************************************************************\
# Klasse:                                                                                      	 #
#   - Record-Klasse: Informationsspeicher, keine Funktionalität im eigentlichen Sinne            #
#   - Informationen über die Felder, die verschlüsselt sind		                                 #
#																								 #
\************************************************************************************************/

class dbCrypt {
	var $columns;

	public function __construct(... $columns){

		$this->columns=$columns;
	}
}

abstract class dbMain {
	abstract public function build();
	abstract public function save($link,$crypted_column);
}

?>