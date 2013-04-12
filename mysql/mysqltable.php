<?php
include(dirname(__FILE__).'/../databasetable.php');
//import('lib.oopdb.mysql.mysqlrow');
## represents a Mysql Table in a Mysql Database

class MysqlTable implements DatabaseTable {
    public $fields = array();
    private $connection;
    private $database;
    private $name;
    
    public $lasterror = false;

    public function __construct($database, $name) {
       $this->database = $database;
       $this->connection = $this->database->connection();
       $this->name = $name;

       $fields = array();
       $sql_query = "DESCRIBE {$name}";

        if (!$me = $this->connection->prepare($sql_query)) {
            die(mysqli_error($this->connection));
        }
        
        $me->execute();
        $me->bind_result($field_name, $info['type'], $info['null'], $info['key'], $info['default'], $info['extra']);
        $me->store_result();
            
        if($me->num_rows === 0) {
            $me->close();
            die("The requested table {$name} does not exist, or there is a mysqli error: '".mysqli_error($this->connection)."'");
        }
            
        while($me->fetch()) {
            $newinfo = array();
            foreach($info as $key => $value) {
                $newinfo[$key] = $value;
            }
            $fields[strtolower($field_name)] = $newinfo;
        }
        $me->close();
        $this->fields = $fields;
    }

    public function info() {
        return $this->fields;
    }
    public function database() {
        return $this->database;
    }

    public function find($infoarray=array(), $sql = "") {
        if($sql !== "") $sql = " ".$sql;
        
        $bind_result_params = array();
        $isfirst = true;

        $where = $this->database->createWhereClausule($infoarray, $this);
        $bind_param_args = $where['bind_param'];
        $whereclausule = $where['where_clausule'];

        foreach($this->fields as $key => $value) {
            $bind_result_params[] = &$results[strtolower($key)];
        }
        
        $sql_query = "SELECT * FROM `".$this->name."`".$whereclausule.$sql;

        if (!$mysqli_exec = $this->connection->prepare($sql_query)) {
            die(mysqli_error($this->connection));
        }
        if(count($infoarray) != 0)
            call_user_func_array(array($mysqli_exec, 'bind_param'), makeValuesReferenced($bind_param_args));
        $mysqli_exec->execute();
        call_user_func_array(array($mysqli_exec, 'bind_result'), $bind_result_params); 

        $roll = array();
        $i = 0;
        while($mysqli_exec->fetch()) {
            foreach($results as $key => $result) {
                $roll[$i][$key] = $result;
            }
            $i++;
        }
        $mysqli_exec->close();
        return $roll;
    }
    
    public function findOne($infoarray=array(), $sql="") {
        $results = $this->find($infoarray, $sql." LIMIT 1");
        if( count($results) !== 1 ) {
            return null;
        }
        return $results[0];
    }

     //################################
    //## Insert a value into the database
    //################################
    public function insert($info_placeholder) {
      foreach(func_get_args() as $key => $info) {
        if(gettype($info) != "array") {
            die("Expected array, but got '{$info}' as argument {$key} of '.insert()'");
        }

        $insert_string="";
        $values_string = "";

        $types = "";
        $params = array();
        $isfirst = true;

        foreach($info as $field => $value) {
           if(!array_key_exists(strtolower($field),$this->fields)) {
               die("wrong row used in '.insert()'; row {$name} does not exist in table {$this->name}.");
           }

           if($this->fields[$field]['extra'] === "auto_increment") {
               continue;
           }

            if(gettype($value) == "array") {
                die("not supported yet: ".xdebug($value));
            }

            if($isfirst) {
                $isfirst = false;
                $insert_string .= "?";
                $values_string .= "`{$field}`";
            } else {
                $insert_string .= ",?";
                $values_string .= ", `{$field}`";
            }
            
            $type = gettype($value);
            $types .= substr($type, 0, 1);
            $params[] = &$info[$field];
        }
        
        $sql_query = "INSERT INTO `{$this->name}` ({$values_string}) VALUES ({$insert_string})";

        $func_args = array_merge(array($types), $params);
        if (!$mysqli_exec = $this->connection->prepare($sql_query)) {
            $this->lasterror = mysqli_error($this->connection);
            return false;
        }
        
        call_user_func_array(array($mysqli_exec, 'bind_param'), $func_args);
        $mysqli_exec->execute();

        $id = $this->connection->insert_id;
        if($id === 0) {
            $this->lasterror = mysqli_error($this->connection);
            return false;
        }
      
        return $id;
      }
    }

    public function update($where, $info) {
        $insert_string="";
        $isfirst = true;
        $types = "";
        
        foreach($info as $field => $value) {
           if(!array_key_exists(strtolower($field),$this->fields)) {
               die("wrong row used in '.insert()'; row {$name} does not exist in table {$this->name}.");
           }

            if(gettype($value) == "array") {
                die("not supported yet: ".xdebug($value));
            }

            if($isfirst) {
                $isfirst = false;
                $insert_string .= "`{$field}` = ?";
            } else {
                $insert_string .= ", `{$field}` = ?";
            }
            
            $type = gettype($value);
            $types .= substr($type, 0, 1);
            $params[] = &$info[$field];
        }

        $where = $this->database->createWhereClausule($where, $this);
        $bind_param_args = $where['bind_param'];
        $whereclausule = $where['where_clausule'];
        
        $bind_param_args = array_merge(array($types . $bind_param_args[0]), $params, array_slice($bind_param_args, 1));
        
        $sql_query = "UPDATE `{$this->name}` SET {$insert_string}{$whereclausule}";
        $func_args = array_merge(array($types), $params);

        if (!$mysqli_exec = $this->connection->prepare($sql_query)) {
            die(mysqli_error($this->connection));
        }
        
        call_user_func_array(array($mysqli_exec, 'bind_param'), makeValuesReferenced($bind_param_args));
        $mysqli_exec->execute();
        $id = $this->connection->insert_id;
        return $id;
    }
    
    public function delete($infoarray) {    
        $where = $this->database->createWhereClausule($info, $this);
        $bind_param_args = $where['bind_param'];
        $whereclausule = $where['where_clausule'];
        
        $sql_query = "DELETE FROM `".$this->name."` ".$whereclausule;

        if (!$mysqli_exec = $this->connection->prepare($sql_query)) {
            die(mysqli_error($this->connection));
        }
        
        call_user_func_array(array($mysqli_exec, 'bind_param'), $bind_param_args);
        $mysqli_exec->execute();

        $id = $this->connection->insert_id;
        return $id;
    }

}

function makeValuesReferenced($arr){
    $refs = array();
    foreach($arr as $key => $value)
        $refs[$key] = &$arr[$key];
    return $refs;

}

?>