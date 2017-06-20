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
	var $errorReporting;			// defines the how to display errors
	const ERROR_EXCEPTION=1;		// constant to define the errorReporting with ecxeptions
	const ERROR_TRIGGER=2;			// constant to define the errorReporting with ecxeptions

	/**
	 * starts a conncetion with a database
	 * @param string $username the username to connect with the database
	 * @param string $password the password to connect with the database
	 * @param string $host     the host where to connect
	 * @param string $db 	   optional-to set at the constuctor the database
	 * @param string $key      optional-to set the crypt key
	 */
	public function __construct($host,$username,$password,$db=null,$key=null,$errorReporting=db::ERROR_EXCEPTION){
		$this->connect($host,$username,$password,$db);
		$this->$errorReporting=$errorReporting;
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
			$this->error('Operation Failed: Could not set the crypt key!');
			
	}

	/**
	 * starts a connection with a database
	 * @param  string $username the username to connect with the database
	 * @param  string $password the password to connect with the database
	 * @param  string $host     the host where to connect
	 * @param  string $database optional-to set at the constuctor the database
	 * @return bool             returns false when the connection failed or the database couldn't be selected and true if it is working
	 */
	public function connect($host,$username,$password,$db=null){
		$this->CloseConnection();
		$this->databaseLink = mysqli_connect($host,$username,$password,"",3306);
		if(!$this->databaseLink){
			$this->error('Could not connect to server: '.mysqli_connect_errno($this->databaseLink).mysqli_error($this->databaseLink));
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
			$this->error('Cannot select database: '.mysqli_error($this->databaseLink));
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
			$this->error('Operation Failed: Could not execute SQL without selected database!');
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
				$this->error('Operation Failed: ['.$this->lastErrno.'] '.$this->lastError.' '.$query);
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
	 * returns a list of all columns in a table
	 * @param  string $table the table name
	 * @return array         a list of all columns in a table
	 */
	protected function getColumns($table){
		$tmp=$this->ExecuteSQL("SHOW COLUMNS FROM `".self::SecureData($table)."`;");
		if(!is_array($tmp)) return array();
		$data=array();
		foreach ($tmp as $value) {
			$data[]=$value['Field'];
		}
		return $data;
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
	 * runs a select query
	 * @param string $table   the table name
	 * @param object $objects a list of db objects which are used as record objects
	 */
	public function Select($table,... $objects){
		$cryptedColumn=$this->getCryptedFields($objects);
		$selectColumn=array();
		if(count($cryptedColumn)>0 && $this->key==null)
			$this->error('Operation Failed: No crypt key is set!');
			
		foreach ($objects as $object) {
			if(get_class($object)=="dbSelect"){
				$selectColumn=array_merge($selectColumn,$object->column);
			}
		}
		if(count($cryptedColumn)>0){ //crypted fields
			if(count($selectColumn)==0) //all columns
				$selectColumn=$this->getColumns($table);
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
			$this->error('Operation Failed: Could not Insert Data without a crypted key!');

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


	/**
	 * runs a delete query
	 * @param string $table   the table name
	 * @param object $objects a list of db objects which are used as record objects
	 */
	public function Delete($table,... $objects){
		$query ="DELETE FROM `".self::SecureData($table)."` ";
		$query.=self::buildClauses(... $objects).";";
		return $this->ExecuteSQL($query);
	}


	/**
	 * runs a updatae query
	 * @param string $table   the table name
	 * @param array  $vars    a list of data which should be updated in a table; 
	 *                        organized by column=>value
	 * @param object $objects a list of db objects which are used as record objects
	 */
	public function Update($table,$set=array(),... $objects){
		if(count($set)==0) return false;

		$cryptedColumn=self::getCryptedFields($objects);
		if(count($cryptedColumn)>0 && $this->key==null)
			$this->error('Operation Failed: Could not Insert Data without a crypted key!');

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

	/**
	 * close a active connection
	 */
	public function CloseConnection(){
		if($this->databaseLink){
			mysqli_close($this->databaseLink);
		}
	}

  	/**
  	 * order alss record objects an convert them into a query
  	 * @param  object $objects a list of db objects which are used as record objects
  	 * @return string          the sql query that was builded
  	 */
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

	/**
	 * outputs an error defined by the user
	 * @param string $errorMessage The error message
	 */
	protected function error($errorMessage){
		if($this->errorReporting==db::ERROR_EXCEPTION)
			throw new Exception($errorMessage);
		else if($this->errorReporting==db::ERROR_TRIGGER)
			trigger_error($errorMessage,E_USER_ERROR);
	}
}


//================================================================================
// record classes
//================================================================================

/**
 * record class for a SQL-limit operation
 */
class dbLimit extends dbMain{
	var $start;	//the start point
	var $limit;	//count of rows which should be returned

	/**
	 * method to save all necessary information for the record class
	 * @param string $start set on which row should be start
	 * @param string $limit set how much rows should be returned
	 */
	public function __construct($start,$limit){
		$this->start=$start;
		$this->limit=$limit;
	}

	/**
	 * escapes all used variables to opposite an sql injection
	 * @param        $link    		 the mysqli database link
	 * @param array  $crypted_column a list of crypted columns used in a query 
	 */
	public function save($link,$crypted_column){
		$start=mysqli_real_escape_string($link,$this->start);
		$limit=mysqli_real_escape_string($link,$this->limit);
	}

	/**
	 * creates the where-SQL-string for the complete SQL-query 
	 * @return string where operation string
	 */
	public function build(){
		return "LIMIT ".$this->start.",".$this->limit;
	}
}


/**
 * record class for a SQL-join operation
 */
class dbJoin extends dbMain{
	var $source;		//source table 					  - format: tablename.column
	var $destination;	//table on which should be joined - format: tablename.column

	/**
	 * method to save all necessary information for the record class
	 * @param string $destination table on which should be joined - format: tablename.column
	 * @param string $source	  source table 					  - format: tablename.column
	 */
	public function __construct($destination,$source){
		$this->source=$source;
		$this->destination=$destination;
	}

	/**
	 * escapes all used variables to opposite an sql injection
	 * @param        $link    		 the mysqli database link
	 * @param array  $crypted_column a list of crypted columns used in a query 
	 */
	public function save($link,$crypted_column){
		$this->source=mysqli_real_escape_string($link,$this->source);
		$this->destination=mysqli_real_escape_string($link,$this->destination);
	}

	/**
	 * creates the where-SQL-string for the complete SQL-query 
	 * @return string where operation string
	 */
	public function build(){
		return "JOIN ".strtok($this->destination,'.')." ON ".$this->destination."=".$this->source;
	}
}

/**
 * record class for a single SQL-condition operation
 */
class dbCond extends dbMain{
	var $column;			//the column on which the condition should be run on
	var $cond;				//the statement which should be fulfill
	var $operator;			//the operator which should be used to connect column and operator
	var $connect;			//the connector to connect multiple dbCond
	var $crypted_column;	//a list of crypted columns used in a query 
	var $key;				//the key for the cryption

	/**
	 * method to save all necessary information for the record class
	 * @param string  $column    the column on which the condition should be run on
	 * @param string  $cond      the statement which should be fulfill
	 * @param string  $operator  the operator which should be used to connect column and operator
	 * @param string  $connect   the connector to connect multiple dbCond
	 */
	public function __construct($column,$cond,$operator="=",$connect="AND"){
		$this->column=$column;
		$this->cond=$cond;
		$this->operator=$operator;
		$this->connect=$connect;
	}

	/**
	 * Sets the key
	 * @param string $key the key for the cryption
	 */
	public function setKey($key){
		$this->key=$key;
	}

	/**
	 * escapes all used variables to opposite an sql injection
	 * @param        $link    		 the mysqli database link
	 * @param array  $crypted_column a list of crypted columns used in a query 
	 */
	public function save($link,$crypted_column){
		$this->crypted_column=$crypted_column;
		$this->column=mysqli_real_escape_string($link,$this->column);
		$this->cond=mysqli_real_escape_string($link,$this->cond);
		$this->operator=mysqli_real_escape_string($link,$this->operator);
		$this->connect=mysqli_real_escape_string($link,$this->connect);
	}

	/**
	 * creates the where-SQL-string for the complete SQL-query 
	 * @return string where operation string
	 */
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

/**
 * record class for a multi SQL-condition operation
 */
class dbCondBlock extends dbMain{
	var $cond=array();	//a list of dbCond-objects which should be connected
	var $key;			//the key for the cryption

	/**
	 * method to save all necessary information for the record class
	 * @param dbCond $conditions a list of dbCond-objects which should be connected
	 */
	public function __construct(... $conditions){
		foreach ($conditions as $cond) {
			if($cond instanceof dbCond)
				$this->cond[]=$cond;
		}
	}

	/**
	 * Sets the key
	 * @param string $key the key for the cryption
	 */
	public function setKey($key){
		$this->key=$key;
	}

	/**
	 * escapes all used variables to opposite an sql injection
	 * @param        $link    		 the mysqli database link
	 * @param array  $crypted_column a list of crypted columns used in a query 
	 */
	public function save($link,$crypted_column){
		foreach ($this->cond as $conditions) {
			$conditions->save($link,$crypted_column);
		}
	}

	/**
	 * creates the where-SQL-string for the complete SQL-query 
	 * @return string where operation string
	 */
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
 * record class to create an increased on the SQL-string
 */
class dbInc{
	var $num;	//the number how much should be increased

	/**
	 * method to save all necessary information for the record class
	 * @param int $num the number how much should be increased
	 */
	public function __construct($num){
		$this->num=$num;
	}

	/**
	 * escapes all used variables to opposite an sql injection
	 * @param        $link    		 the mysqli database link
	 * @param array  $crypted_column a list of crypted columns used in a query 
	 */
	public function save($link,$crypted_column){
		$this->num=mysqli_real_escape_string($link,$this->num);
	}
}

/**
 * record class to create an inversion on the SQL-string
 */
class dbNot{

}

/**
 * record class for a SQL operation using SQL-method
 */
class dbFunc{
	var $func;		//SQL method

	/**
	 * method to save all necessary information for the record class
	 * @param string $func SQL method
	 */
	public function __construct($func){
		$this->func=$func;
	}
}


/**
 * record class for a SQL-order operation
 */
class dbOrder extends dbMain{
	var $column;		//the column where should be ordered on
	var $direction;		//the order algorithm

	/**
	 * method to save all necessary information for the record class
	 * @param string $column    the column where should be ordered on
	 * @param string $direction the order algorithm
	 */
	public function __construct($column,$direction){
		$this->column=$column;
		$this->direction=$direction;
	}

	/**
	 * escapes all used variables to opposite an sql injection
	 * @param        $link    		 the mysqli database link
	 * @param array  $crypted_column a list of crypted columns used in a query 
	 */
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

/**
 * record class to save the columns that should be select
 */
class dbSelect extends dbMain{
	var $column;	//a list of column which should be selected

	/**
	 * method to save all necessary information for the record class
	 * @param string $collumn a list of column which should be selected
	 */
	public function __construct(... $collumn){
		$this->column=$column;
	}

	/**
	 * escapes all used variables to opposite an sql injection
	 * @param        $link    		 the mysqli database link
	 * @param array  $crypted_column a list of crypted columns used in a query 
	 */
	public function save($link,$crypted_column){
		foreach ($this->column as $key => $value) {
			$this->column[$key]=mysqli_real_escape_string($link,$value);
		}
	}

	/**
	 * creates the where-SQL-string for the complete SQL-query 
	 * @return string where operation string
	 */
	public function build(){
		$query="";
		foreach ($this->column as $column) {
			$query='`'.$column.'`,';
		}
		return substr($query,0,-1);
	}
}

/**
 * record class to save the crypted columns
 */
class dbCrypt {
	var $columns;	//a list of column which are crypted

	/**
	 * method to save all necessary information for the record class
	 * @param string $collumn a list of column which are crypted
	 */
	public function __construct(... $columns){
		$this->columns=$columns;
	}
}

/**
 * abstract class to create an uniform
 */
abstract class dbMain {
	abstract public function build();
	abstract public function save($link,$crypted_column);
}

?>