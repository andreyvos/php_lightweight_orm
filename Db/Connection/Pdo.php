<?php

class Db_Connection_Pdo extends Db_Connection_Abstract {
    /**
     * @var PDO
     */
    protected $connect;

    protected $database_name;

    /**
     * @var Db_Profiler
     */
    protected $profiler;

    protected $quotes = '"';
    protected $driver = Conf_Db::DRIVER_PDO;
    protected $engine;

    private $dsn;

    /**********************************************************************************/

    /*************************************************/
    private function make_dsn(Conf_Db $conf){
        $dsn = $conf->engine.':host='.$conf->host
                .';port='.$conf->port
                .';dbname='.$conf->base
                .';user='.$conf->user
                .';password='.$conf->pass;
        $this->dsn = $dsn;
        return true;
    }
    public function getDriver(){
        return $this->driver;
    }
    public function getEngine(){
        return $this->engine;
    }
    /*************************************************/
    public function __construct(Conf_Db $conf, $market = null){
        $this->driver = $conf->driver;
        $this->engine = $conf->engine;

        if($this->engine == Conf_Db::ENGINE_MYSQL){
            $this->quotes = '`';
        }
        $this->profiler = new Db_Profiler();
        $this->getProfiler()->start();

        if($this->make_dsn($conf)){
            $this->getProfiler()->startQuery("connect");
            if(($this->connect = new PDO(
                $this->dsn,
                $conf->user,
                $conf->pass
            )) instanceof PDO){

            }else{
                throw new Exception(implode("|",$this->connect->errorInfo()), $this->connect->errorCode());
            }
            $this->getProfiler()->endQuery();
        }else{
            throw new Exception('Failed assemble dsn for PDO');
        }
        //$this->connect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database_name = $conf->base;

//        if(!$this->connect->select_db($conf->base)){
//            if($conf->auto_create_base){
//                try {
//                    $this->query("CREATE DATABASE `{$conf->base}`");
//                }
//                catch(Exception $e){}
//
//                if(!$this->connect->select_db($conf->base)){
//                    throw new Exception($this->connect->error, $this->connect->errno);
//                }
//            }
//            else {
//                throw new Exception($this->connect->error, $this->connect->errno);
//            }
//        }


//        $this->connect->set_charset('utf8');

        $this->query("SET NAMES 'utf8'");

        $timezone = self::getTimezone($market);
        if($timezone){
            if($this->engine == Conf_Db::ENGINE_PGSQL){
                $this->query("SET SESSION TIME ZONE  " . $this->escape($timezone));
            }
            else{
                $this->query("SET SESSION time_zone = " . $this->escape($timezone));
            }

        }
    }



    /**********************************************************************************/

    /**
     * @return Db_Profiler
     */
    public function getProfiler(){
        return $this->profiler;
    }

    public function getDatabaseName(){
        return $this->database_name;
    }

    public function getDatabaseNameForShow(){
        return $this->database_name;
    }

    /**********************************************************************************/

    /**
     * @param string $from
     * @param array $columns
     * @return Db_Statment_Select
     */
    public function select($from = null, $columns = array()){
        return new Db_Statment_Select($this, $from, $columns);
    }

    /**********************************************************************************/

    /**
     * Экранирование строки
     *
     * @param $value
     *
     * @return string
     */
    public function escape($value){
        if($value instanceof Db_Expr){
            return (string)$value;
        }
        else if(is_null($value)){
            return 'null';
        }
//        return "'" . $this->connect->real_escape_string($value) . "'";
        return $this->connect->quote($value);
    }

    /**
     * Сформировать строку запроса из подготовленной строки и параметров
     *
     * @param $string
     * @param $params
     *
     * @return string
     *
     * @throws Exception
     */
    public function bind($string, $params){
        $string = (string)$string;
        if(!is_array($params)) $params = [$params];

        if(strlen($string)){
            /***************
             * TYPES:
             * 1 - поиск
             * 2 - внутри ""
             * 3 - внутри ''
             */

            $positions = [];
            $type = 1;

            for($i = 0; $i < strlen($string); $i++){
                $s = substr($string, $i, 1);

                if($type == 1){
                    if($s == "?")       $positions[] = $i;
                    else if($s == '"')  $type = 2;
                    else if($s == "'")  $type = 3;
                    else if($s == "\\") $i++;
                }
                else if($type == 2){
                    if($s == '"') $type = 1;
                    else if($s == "\\") $i++;
                }
                else if($type == 3){
                    if($s == "'") $type = 1;
                    else if($s == "\\") $i++;
                }
            }

            if(count($positions) != count($params)){
                throw new Exception('Error when binding parameters with the string');
            }

            // подстановка значений в строку
            if(count($params)){
                $add = 0;
                $i = 0;
                foreach($params as $el){
                    $value = $this->escape($el);
                    $string = substr($string, 0, $positions[$i] + $add) . $value . substr($string, $positions[$i] + 1 + $add);
                    $add += strlen($value) - 1;
                    $i++;
                }
            }
        }
        return $string;
//        $stmt = $this->connect->prepare($string);
//        foreach($params as $k => $v){
//            $stmt->bindValue($k,$v);
//        }
//        return $stmt->queryString;

    }

    /**********************************************************************************/

    /**
     * Выполнить запрос
     *
     * @param $query
     *
     * @return bool|PDOStatement
     *
     * @throws Exception
     */
    public function query($query){
        //quoting replacement for PGSQL
        if($this->engine == Conf_Db::ENGINE_PGSQL){
            $query = str_replace('`','"',$query);
        }
        $this->getProfiler()->startQuery($query);
        //$result = $this->connect->query($query);
        $result = $this->connect->prepare($query);
        $result->execute();
        $this->getProfiler()->endQuery();

        if($this->connect->errorCode() !== NULL && $this->connect->errorCode() !== '00000'){
            throw new Exception(implode("|",$this->connect->errorInfo()), $this->connect->errorCode());
        }
        if($result->errorCode() !== NULL && $result->errorCode() !== '00000'){
            throw new Exception(implode("|",$result->errorInfo()), $result->errorCode());
        }
        return $result;
    }

    /**********************************************************************************/

    /**
     * Выполнить запрос
     *
     * @param $query
     *
     * @return bool|PDOStatement
     *
     * @throws Exception
     */
    public function multiQuery($query){
        return $this->query($query);
    }

    /**********************************************************************************/

    public function transactionStart(){
//        $this->query("START TRANSACTION");
        $this->getProfiler()->startQuery("START TRANSACTION");
        $this->connect->beginTransaction();
        $this->getProfiler()->endQuery();
    }

    public function transactionCommit(){
//        $this->query("COMMIT");
        $this->getProfiler()->startQuery("COMMIT");
        $this->connect->commit();
        $this->getProfiler()->endQuery();
    }

    public function transactionRollback(){
//        $this->query("ROLLBACK");
        $this->getProfiler()->startQuery("ROLLBACK");
        $this->connect->rollBack();
        $this->getProfiler()->endQuery();
    }

    /**********************************************************************************/

    /**
     * @param $table
     * @param $data
     * @throws Exception
     */
    public function insert($table, array $data){
        if(is_array($data) && count($data)){
            $table  = (string)$table;

            $keys   = '';
            $values = '';

            $first  = true;

            foreach($data as $k => $v){
                if(!$first){
                    $keys   .=  ",";
                    $values .=  ",";
                }
                else {
                    $first = false;
                }

                $keys   .=  $this->quotes."{$k}".$this->quotes;
                $values .=  $this->escape($v);
            }

            /**TODO
             * Проверить Exception-ы..+какой стиль вывода ошибок лучше silent/exception
             * Также проверить совместимость PDOStatment с mysqli_result
             */
            if($this->engine == Conf_Db::ENGINE_PGSQL && $this->connect->inTransaction()){
                $this->query("SAVEPOINT pdo_insert_sp");
                try{
                    $this->query("INSERT INTO {$this->quotes}{$table}{$this->quotes} ({$keys}) VALUES ({$values})");
                }catch (Exception $e){
                    $this->query("ROLLBACK TO SAVEPOINT pdo_insert_sp");
                    throw new Exception($e->getMessage(), $e->getCode());
                }

                $this->query("RELEASE SAVEPOINT pdo_insert_sp");
            }else{
                $this->query("INSERT INTO {$this->quotes}{$table}{$this->quotes} ({$keys}) VALUES ({$values})");
            }
            $this->insert_tbl = $table;
        }
    }

    /**
     * @param $table
     * @param $data
     * @throws Exception
     */
    public function replace($table, array $data){
        if(is_array($data) && count($data)){
            $table  = (string)$table;

            $keys   = '';
            $values = '';

            $first  = true;

            foreach($data as $k => $v){
                if(!$first){
                    $keys   .=  ",";
                    $values .=  ",";
                }
                else {
                    $first = false;
                }

                $keys   .=  $this->quotes."{$k}".$this->quotes;
                $values .=  $this->escape($v);
            }

            $this->query("REPLACE INTO {$this->quotes}{$table}{$this->quotes} ({$keys}) VALUES ({$values})");
            $this->insert_tbl = $table;
        }
    }

    public function insertMulti($table, array $data, $count = 500, $ignore = false){
        if (is_array($data) && count($data)) {
            reset($data);
            $keys = array_keys($data[key($data)]);

            $num      = 0;
            $blockNum = 0;
            $all      = count($data);
            $inserted = 0;

            // Flags
            $flags = "";
            if($ignore) $flags.= " IGNORE";

            $query_start = "INSERT{$flags} INTO {$this->quotes}{$table}{$this->quotes}({$this->quotes}" . implode("{$this->quotes},{$this->quotes}", $keys) . "{$this->quotes}) VALUES ";
            $query_values = '';

            foreach ($data as $el) {
                $blockNum++;
                $num++;

                if(strlen($query_values)){
                    $query_values .= ",";
                }

                $query_values .= "(";
                $first_v = true;
                foreach($el as $v){
                    if($first_v){
                        $first_v = false;
                    }
                    else {
                        $query_values.= ",";
                    }
                    $query_values.= $this->escape($v);
                }
                $query_values .= ")";

                if ($blockNum == $count || $num == $all) {
                    $inserted += $this->query($query_start . $query_values);

                    $query_values = '';
                    $blockNum = 0;
                }
            }
            $this->insert_tbl = $table;
            return $inserted;
        }

        return null;
    }

    /**
     * @param string $pkey
     * @param null $table
     * @param null $full_seq_name Full sequence name (example pgsql default: 'tablename_columnname_seq')
     * @return int|string
     */
    public function lastInsertId($pkey = 'id',$table = null,$full_seq_name = null){
        //return $this->connect->insert_id;
        if($full_seq_name !== null){
            return $this->connect->lastInsertId($full_seq_name);
        }elseif($table !== null){
            return $this->connect->lastInsertId($table.'_'.$pkey.'_seq');
        }elseif(isset($this->insert_tbl)){
            return $this->connect->lastInsertId($this->insert_tbl.'_'.$pkey.'_seq');
        }
        return $this->connect->lastInsertId();
    }

    /**
     * @param $table
     * @param array $data
     * @param null $where
     * @param array|mixed $whereParams
     *
     * @return int Affected Rows
     *
     * @throws Exception
     */
    public function update($table, array $data, $where = null, $whereParams = []){
        if ((is_array($data) && count($data)) || is_string($data)) {
            $query = "UPDATE {$this->quotes}{$table}{$this->quotes} SET ";

            $first = true;

            if(is_array($data)){
                foreach ($data as $k => $v) {
                    if(!$first) $query .=  ",";
                    else        $first = false;

                    $query .= "{$this->quotes}{$k}{$this->quotes}=" . $this->escape($v) . "";
                }
            }
            else if (is_string($data)) {
                $query .= $data;
            }


            if(is_string($where)){
                // where params
                if (func_num_args() > 4) {
                    $whereParams = func_get_args();
                    unset($whereParams[0], $whereParams[1], $whereParams[2]);
                }
                if (!is_array($whereParams)) $whereParams = array($whereParams);

                $query .= " WHERE " . $this->bind($where, $whereParams);
            }
            else if (is_array($where) && count($where)) {
                $query .= " WHERE ";
                $add = array();
                foreach ($where as $k => $v) {
                    $add[] = "{$this->quotes}{$k}{$this->quotes}=" . $this->escape($v);
                }
                $query .= implode(" and ", $add);
            }

//            $this->query($query);
//            return $this->connect->affected_rows;
            return $this->query($query)->rowCount();
        }


        throw new Exception("Invalid update data");
    }

    public function delete($table, $where = null, $whereParams = []){
        $query = "DELETE FROM {$this->quotes}{$table}{$this->quotes} ";

        if(is_string($where)){
            // where params
            if (func_num_args() > 3) {
                $whereParams = func_get_args();
                unset($whereParams[0], $whereParams[1]);
            }
            if (!is_array($whereParams)) $whereParams = array($whereParams);

            $query .= " WHERE " . $this->bind($where, $whereParams);
        }

//        $this->query($query);
//        return $this->connect->affected_rows;
        return $this->query($query)->rowCount();
    }

    /**
     * @param $query
     * @param array|mixed $params
     *
     * @return PDOStatement
     *
     * @throws Exception
     */
    public function fetch($query, $params = []){

        // where params
        if (func_num_args() > 2) {
            $params = func_get_args();
            unset($params[0]);
        }
        if (!is_array($params)) $params = array($params);

        $query = $this->bind($query, $params);

        $result = $this->query($query);

        if(!($result instanceof PDOStatement)){
            throw new Exception("Invalid select query");
        }

        return $result;
    }

    /**
     * Получить одно значение
     *
     * @param $query
     * @param array|mixed $params
     *
     * @return string|bool
     *
     * @throws Exception
     */
    public function fetchOne($query, $params = []){
        // where params
        if (func_num_args() > 2) {
            $params = func_get_args();
            unset($params[0]);
        }
        if (!is_array($params)) $params = array($params);

        $res = $this->fetch($query, $params);

        return $res->rowCount() ? $res->fetchColumn() : false;
    }

    /**
     * Получить строку
     *
     * @param $query
     * @param array|mixed $params
     *
     * @return array|bool
     *
     * @throws Exception
     */
    public function fetchRow($query, $params = []){
        // where params
        if (func_num_args() > 2) {
            $params = func_get_args();
            unset($params[0]);
        }
        if (!is_array($params)) $params = array($params);

        $res = $this->fetch($query, $params);

        return $res->rowCount() ? (array)$res->fetchObject() : false;
    }

    /**
     * Получить первую колонку
     *
     * @param $query
     * @param array|mixed $params
     *
     * @return array
     *
     * @throws Exception
     */
    public function fetchCol($query, $params = [],$colnum = 0){
        // where params
        if (func_num_args() > 2) {
            $params = func_get_args();
            unset($params[0]);
        }
        if (!is_array($params)) $params = array($params);

        $array = [];
        $res = $this->fetch($query, $params);
        while($el = $res->fetchColumn($colnum)){
            $array[] = $el;
        }

        return $array;
    }

    /**
     * Получить все данные в массиве
     *
     * @param $query
     * @param array|mixed $params
     *
     * @return array
     *
     * @throws Exception
     */
    public function fetchAll($query, $params = []){
        // where params
        if (func_num_args() > 2) {
            $params = func_get_args();
            unset($params[0]);
        }
        if (!is_array($params)) $params = array($params);

        $array = [];
        $res = $this->fetch($query, $params);
        if($el = $res->fetchAll(PDO::FETCH_ASSOC)){
            $array = $el;
        }

        return $array;
    }

    /**
     * Получить первую колонку в ключ, второе значение в значение
     *
     * @param $query
     * @param array|mixed $params
     *
     * @return array
     *
     * @throws Exception
     */
    public function fetchPairs($query, $params = []){
        // where params
        if (func_num_args() > 2) {
            $params = func_get_args();
            unset($params[0]);
        }
        if (!is_array($params)) $params = array($params);

        $array = [];
        $res = $this->fetch($query, $params);
        if($res->columnCount() == 2){
            while($el = $res->fetchObject()){
                $el = array_values((array)$el);
                $array[$el[0]] = $el[1];
            }
        }
        else {
            throw new Exception('Invalid Query for fetchPairs, use only 2 fields');
        }

        return $array;
    }

    /**
     * Первое поле ставиться в ключ массива
     * При дублирующихся ключах, значение идущее позднее не учитываеться
     *
     * @param $query
     * @param array|mixed $params
     *
     * @return array
     *
     * @throws Exception
     */
    public function fetchUnique($query, $params = []){
        // where params
        if (func_num_args() > 2) {
            $params = func_get_args();
            unset($params[0]);
        }
        if (!is_array($params)) $params = array($params);

        $array = [];
        $res = $this->fetch($query, $params);
        //$array = $res->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);//without first column in results
        while($el = $res->fetchObject()){
            $el = (array)$el;
            if(!isset($array[current($el)])){
                $array[current($el)] = $el;
            }
        }

        return $array;
    }




}