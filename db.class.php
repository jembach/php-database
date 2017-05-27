<?php

class db {

	var $lastError;					// Holds the last error
	var $lastQuery;					// Holds the last query
	var $result;						// Holds the MySQL query result
	var $records;						// Holds the total number of records returned
	var $affected;					// Holds the total number of records affected
	var $rawResults;				// Holds raw 'arrayed' results
	var $arrayedResult;			// Holds an array of the result
	var $databaseLink;		// Database Connection Link
	var $errorCode;
	var $crypt;

	public function __construct($username,$password,$host,$database){
		$this->crypt=new encryption();
		self::connect($username,$password,$host,$database);
	}

	private function connect($username,$password,$host,$database){
		$this->CloseConnection();
		$this->databaseLink = mysqli_connect($username,$password,$host,"",3306);
		if(!$this->databaseLink){
   			$this->lastError = 'Could not connect to server: ' . mysqli_connect_errno($this->databaseLink) .mysqli_error($this->databaseLink);
   			die($this->lastError);
			return false;
		}
		if(!$this->UseDB($database)){
			$this->lastError = 'Could not connect to database: ' . mysqli_error($this->databaseLink);
			die();
			return false;
		}
		$this->crypt->setKey($connectionData[4]);
		return true;
	}

	private function UseDB($db){
		if(!mysqli_select_db($this->databaseLink,$db)){
			$this->lastError = 'Cannot select database: ' . mysqli_error($this->databaseLink);
			return false;
		}else{
			return true;
		}
	}


	public function ExecuteSQL($query,$multi=false){
		$this->lastQuery = $query;
		if($multi){
			 if(mysqli_multi_query($this->databaseLink,$query)){
				 $this->Connect();
				 return true;
			 } else {
				 $this->lastErrno = mysqli_errno($this->databaseLink);
				 $this->lastError = mysqli_error($this->databaseLink);
				throw new Exception("Operation Failed: [".$this->lastErrno."] ".$this->lastError." ".$query);
				 return false;
			 }
		} else {
			if($this->result 	= mysqli_query($this->databaseLink,$query)){
				$this->records 	= @mysqli_num_rows($this->result);
				$this->affected	= @mysqli_affected_rows($this->databaseLink);
				if($this->records > 0){
					$this->ArrayResults();
                   	return $this->arrayedResult;
        		}else{
        			return (bool)true;
               	}
			}else{
				$this->lastErrno = mysqli_errno($this->databaseLink);
				$this->lastError = mysqli_error($this->databaseLink);
				throw new Exception("Operation Failed: [".$this->lastErrno."] ".$this->lastError." ".$query);
				return false;
			}
		}
	}

	public function ArrayResult(){
		$this->arrayedResult = mysqli_fetch_assoc($this->result) or die (mysqli_error());
		return $this->arrayedResult;
	}

	public function ArrayResults(){
		if($this->records == 1){
			return $this->ArrayResult();
		}
		$this->arrayedResult = array();
		while ($data = mysqli_fetch_assoc($this->result)){
			$this->arrayedResult[] = $data;
		}
		return $this->arrayedResult;
	}

	public function ArrayResultsWithKey($key='id'){
		if(isset($this->arrayedResult)){
			unset($this->arrayedResult);
		}
		$this->arrayedResult = array();
		while($row = mysqli_fetch_assoc($this->result)){
			foreach($row as $theKey => $theValue){
				$this->arrayedResult[$row[$key]][$theKey] = $theValue;
			}
		}
		return $this->arrayedResult;
	}

	/************************************************************************************************\
	# Funktion:                                                                                      #
	#   - gibt den letzten Auto-Increment Wert zurück					 							 #
	#														  										 #
	\************************************************************************************************/

 	public function getLastInsertID(){
		return mysqli_insert_id($this->databaseLink);
	}

 	/************************************************************************************************\
	# Funktion:                                                                                      #
	#   - gibt zurück, wie viele Datensätze gefunden wurden				 							 #
	#														  										 #
	\************************************************************************************************/

 	public function CountRows($from,... $object){
		$result = $this->Select($from, 'count(*)',$object);
		return $result[0]["count(*)"];
	}

	/************************************************************************************************\
	# Funktion:                                                                                      #
	#   - gibt alle Spalten in einer Tabelle zurück						 							 #
	#														  										 #
	\************************************************************************************************/

 	public function getColumns($from){
 		$from=self::SecureData($from);
 		$data=self::ExecuteSQL("SHOW COLUMNS FROM ".$from.";");
 		$columns=array();
 		foreach ($data as $value) {
 			$columns[]=$value['Field'];
 		}
 		return $columns;
	}
	

	/************************************************************************************************\
	# Funktion:                                                                                      #
	#   - wandelt einen string/array/object in einen gesichtern string um 							 #
	#     -> get manipulation vorbeugen							                                 	 #
	#														  										 #
	\************************************************************************************************/

	protected function SecureData($data){
		if(is_array($data)){
			foreach ($data as $index => $value) {
				$data[$index]=self::SecureData($value);
			}
			return $data;
		} else if(is_object($data) && method_exists($data,'save')){
			$data->save($this->databaseLink,$this->crypt,self::getCryptedFields($data));
			return $data;
		} else if(!is_object($data))
			return mysqli_real_escape_string($this->databaseLink,$data);
		  else 
			return $data;
	}

	/************************************************************************************************\
	# Funktion:                                                                                      #
	#   - ent- bzw. verschlüsselt einen string/array 												 #
	#														  										 #
	\************************************************************************************************/

	protected function crypt($data,$fields=array(),$mode="encrypt"){
		if(is_string($data) && $mode=="encrypt"){ //string entschlüsseln
			return (string)$this->crypt->encrypt((string)$data);
		} else if(is_string($data)){	//string verschlüsseln
			return (string)$this->crypt->decrypt((string)$data);
		} else if(is_array($data)){ //array umwandelen
			$newData=array();
			foreach ($data as $key => $value) {
				if(is_array($value))
					$newData[$key]=self::crypt($value,$fields,$mode);
				else if(in_array($key, $fields))
					$newData[$key]=self::crypt($value,$fields,$mode);
				else 
					$newData[$key]=$value;
			}
			return $newData;
		} else {
			return $data;
		}
	}

	/************************************************************************************************\
	# Funktion:                                                                                      #
	#   - gibt den Inhalt des dbCrypt Objekts zurück												 #
	#														  										 #
	\************************************************************************************************/

	protected function getCryptedFields($objects){
		foreach ($objects as $object) {
			if($object instanceof dbCrypt)
				return $object->columns;
		}
		return array();
	}

	/************************************************************************************************\
	# Funktion:                                                                                      #
	#   - gibt ein entschlüsselten String zurück													 #
	#														  										 #
	\************************************************************************************************/

	public function getEncryptedString($string){
		return $this->crypt($string,array(),"encrypt");
	}

	/************************************************************************************************\
	# Funktion:                                                                                      #
	#   - gibt ein verschüsselten String zurück														 #
	#														  										 #
	\************************************************************************************************/

	public function getDecryptedString($string){
		return $this->crypt($string,array(),"decrypt");
	}

	/************************************************************************************************\
	# Funktion:                                                                                      #
	#   - führt einen Select befehl durch 		 					                                 #
	#																								 #
	\************************************************************************************************/

	public function Select($table,... $objects){
		//create columns arguement
		$query ="SELECT * FROM `".self::SecureData($table)."` ";
		$query.=self::buildClauses($objects).";";
		$data=$this->ExecuteSQL($query);
		if(!is_null($data) && $data!==true && $data!==false && !array_key_exists(0,$data)){ $data=array($data); }
		if(!is_null($data) && $data!==true && $data!==false ){ $data=self::crypt($data,self::getCryptedFields($objects),"decrypt"); }
		return $data;
	}

	/************************************************************************************************\
	# Funktion:                                                                                      #
	#   - führt einen delete befehl durch 							                                 #
	#	TODO: make compatible with mutliple inserts; -> keys  										 #
	\************************************************************************************************/

	public function Insert($table,$vars,... $objects){
		$vars = $this->SecureData($vars);
		$query = "INSERT INTO `{$table}` SET ";
		$vars=self::crypt($vars,self::getCryptedFields($objects),"encrypt");
		foreach($vars as $key=>$value){
			if($value instanceof dbFunc)
				$query .= "`{$key}` = {$value->func}, ";
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
		$query.=self::buildClauses($objects).";";
		return $this->ExecuteSQL($query);
	}


	/************************************************************************************************\
	# Funktion:                                                                                      #
	#   - führt einen update befehl durch 							                                 #
	#																								 #
	\************************************************************************************************/

	public function Update($table,$set=array(),... $objects){
		if(count($set)==0) return false;
		$set   = self::SecureData($set);
		$set=self::crypt($set,self::getCryptedFields($objects),"encrypt");
		$query = "UPDATE `".self::SecureData($table)."` SET ";
		foreach($set as $key=>$value){
			if($value instanceof dbNot)
				$query .= "`{$key}` = !".$key.", ";
			else if($value instanceof dbInc)
				$query .= "`{$key}` = `{$key}` + '{$value->num}', ";
			else if($value instanceof dbFunc)
				$query .= "`{$key}` = '{$value->func}', ";
			else
				$query .= "`{$key}` = '{$value}', ";

		}
		$query =substr($query, 0, -2)." ";
		$query.=self::buildClauses($objects).";";
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
  
	protected function buildClauses($objects){
		if(!is_array($objects) && is_object($objects)) $objects=array($objects);
		if(count($objects)==0) return;
		$clauses=array();
		//sql abschnitte erzeugen 
		foreach ($objects as $object) {
			if(method_exists($object,'save'))
				$object->save($this->databaseLink,$this->crypt,self::getCryptedFields($objects));
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

	public function save($link,$crypt,$crypted_column){
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

	public function save($link,$crypt,$crypted_column){
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
	var $cond_updated=false;
	var $operator;
	var $connect;
	var $crypted_column;

	public function __construct($column,$cond,$operator="=",$connect="AND"){
		$this->column=$column;
		$this->cond=$cond;
		$this->operator=$operator;
		$this->connect=$connect;
	}


	public function save($link,$crypt,$crypted_column){
		if($this->cond_updated==false && in_array($this->column, $crypted_column)){
			$this->cond=$crypt->encrypt($this->cond);
			$this->cond_updated=true;
		}
		$this->crypted_column=$crypted_column;

		$this->column=mysqli_real_escape_string($link,$this->column);
		$this->cond=mysqli_real_escape_string($link,$this->cond);
		$this->operator=mysqli_real_escape_string($link,$this->operator);
		$this->connect=mysqli_real_escape_string($link,$this->connect);
	}

	public function build($operatian="dbCond"){
		$query="";
		if($operatian=="dbCond") $query.="WHERE ";
		else 				 	 $query.=$this->connect." ";
		if(in_array($this->column, $this->crypted_column) && ($this->cond instanceof dbNot || 
				$this->cond instanceof dbFun || $this->operator=="LIKE")){
			trigger_error("Auf verschlüsselte Datensätze können nicht alle Operationen angewandt werden.",E_USER_ERROR);
			return "";
		}
		if($this->cond instanceof dbNot) 
			$query.=$this->column."= !".$this->$column;
		else if($this->cond instanceof dbFun) 
			$query.="`".$this->column."` ".$this->operator." ".$this->cond->func;
		else if($this->operator=="LIKE") 
			$query.="`".$this->column."` ".$this->operator." '%".$this->cond."%'";
		else 					   		 
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

	public function __construct(... $conditions){
		foreach ($conditions as $cond) {
			if($cond instanceof dbCond)
				$this->cond[]=$cond;
		}
	}

	public function save($link,$crypt,$crypted_column){
		foreach ($this->cond as $conditions) {
			$conditions->save($link,$crypt,$crypted_column);
		}
	}

	public function build(){
		$query="";
		foreach ($this->cond as $key => $condition) {
			$query.=$condition->build("dbCondBlock").' ';
		}
		$query="WHERE ".substr($query, strlen($this->cond[0]->connect));
		return $query;
	}
}

/************************************************************************************************\
# Klasse:                                                                                      	 #
#   - Record-Klasse: Informationsspeicher, keine Funktionalität im eigentlichen Sinne            #
#   - Addiert einen wert auf ein Feld 							                                 #
#																								 #
\************************************************************************************************/

class dbInc{
	var $num;

	public function __construct($num){
		$this->num=$num;
	}

	public function save($link,$crypt,$crypted_column){
		$this->num=mysqli_real_escape_string($link,$this->num);
	}
}

/************************************************************************************************\
# Klasse:                                                                                      	 #
#   - Record-Klasse: Informationsspeicher, keine Funktionalität im eigentlichen Sinne            #
#   - Invertiert einen Wert 									                                 #
#																								 #
\************************************************************************************************/

class dbNot{

}

/************************************************************************************************\
# Klasse:                                                                                      	 #
#   - Record-Klasse: Informationsspeicher, keine Funktionalität im eigentlichen Sinne            #
#   - bietet die möglichkeit, bei einen befehl, eine Mysql funktion auszuführen                  #
#																								 #
\************************************************************************************************/

class dbFunc{
	var $func;

	public function __construct($func){
		$this->func=$func;
	}
}

/************************************************************************************************\
# Klasse:                                                                                      	 #
#   - Record-Klasse: Informationsspeicher, keine Funktionalität im eigentlichen Sinne            #
#   - Informationen über die Anzeigereihenfolge 				                                 #
#																								 #
\************************************************************************************************/

class dbOrder extends dbMain{
	var $column;
	var $direction;

	public function __construct($column,$direction){
		$this->column=$column;
		$this->direction=$direction;
	}

	public function save($link,$crypt,$crypted_column){
		$this->column=mysqli_real_escape_string($link,$this->column);
		$this->direction=mysqli_real_escape_string($link,$this->direction);
	}

	public function build(){
		return "ORDER BY ".$this->column." ".$this->direction;
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
	abstract public function save($link,$crypt,$crypted_column);
}

?>