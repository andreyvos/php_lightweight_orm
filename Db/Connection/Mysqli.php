<?php

class Db_Connection_Mysqli extends Db_Connection_Abstract {
    /**
     * @var mysqli
     */
    protected $connect;

    protected $database_name;

    /**
     * @var Db_Profiler
     */
    protected $profiler;

    protected $quotes = '`';
    protected $driver = Conf_Db::DRIVER_MYSQLI;
    protected $engine = Conf_Db::ENGINE_MYSQL;

    protected $conf;
    protected $confMaster;
    protected $market;
    /**
     * @return Db_Connection_Mysqli|Db_Connection_Pdo
     */
    protected function getMasterConnect(){
        return Db::get("m" . $this->name, $this->confMaster, $this->market);
    }

    /**********************************************************************************/

    public function getDriver(){
        return $this->driver;
    }

    public function getEngine(){
        return $this->engine;
    }

    /*************************************************/

    public function __construct(Conf_Db $conf, $market = null, $name = ""){
        $this->conf     = $conf;
        $this->market   = $market;
        $this->name     = $name;

        // проверяеться настройка для процесинг серверов, что бы все select запросы уходили на slave
        if(
            Boot::isModeProcessing() &&
            isset($conf->processing_slaves[Db::getDefaultMarket()]) &&
            is_array($conf->processing_slaves[Db::getDefaultMarket()]) &&
            count($conf->processing_slaves[Db::getDefaultMarket()])
        ){
            $this->slaveSelectMode = true;
            $this->confMaster = clone $conf;
            $this->confMaster->processing_slaves = [];

            foreach($conf->processing_slaves[Db::getDefaultMarket()] as $k => $v){
                $conf->$k = $v;
            }
        }


        $this->profiler = new Db_Profiler();
        $this->getProfiler()->start();

        $start = microtime(1);

        $this->connect = mysqli_init();
        $this->connect->options(MYSQLI_OPT_CONNECT_TIMEOUT, 20);

        $iterations = 3;
        $i = 0;
        while(true) {
            $i++;

            $this->host = $conf->host;
            $this->host_market = Db::getDefaultMarket();

            $this->getProfiler()->startQuery("connect (attempt: {$i}/{$iterations})");
            if((@$this->connect->real_connect(
                $conf->host,
                $conf->user,
                $conf->pass,
                "",
                (int)$conf->port
            ))){
                // good connection
                $this->getProfiler()->endQuery();
                break;
            }
            else {
                $this->getProfiler()->endQuery();

                if($i >= $iterations){
                    throw System_WarningException::create(
                        "Invalid connection to mysql server `{$conf->host}` ({$conf->base}): " . mysqli_connect_error(),
                        [
                            "connect" => [
                                "host" => $conf->host,
                                "user" => $conf->user,
                                "pass" => "***",
                                "port" => (int)$conf->port,
                            ],
                            "env" => [
                                "name"          => $this->name,
                                "market"        => $this->market,
                                "quotes"        => $this->quotes,
                                "driver"        => $this->driver,
                                "engine"        => $this->engine,
                                "database_name" => $this->database_name,
                                "conf"          => $conf->getArray(),
                                "slaveMode"     => $this->slaveSelectMode,
                            ],
                            'runtime' => microtime(1) - $start,
                            'error'   => mysqli_connect_error(),
                            'errno'   => mysqli_connect_errno(),
                        ],
                        mysqli_connect_errno()
                    );
                }
            }
        }

        $this->database_name = $conf->base;

        if(!$this->connect->select_db($conf->base)){
            if($conf->auto_create_base){
                try {
                    $this->query("CREATE DATABASE `{$conf->base}`");
                }
                catch(Exception $e){}

                if(!$this->connect->select_db($conf->base)){
                    throw new Exception($this->connect->error, $this->connect->errno);
                }
            }
            else {
                throw new Exception($this->connect->error . " (connections: {$i}/{$iterations})", $this->connect->errno);
            }
        }

        $this->connect->set_charset('utf8');
        $this->query("SET NAMES utf8");
        $this->query("SET wait_timeout = 1600");

        $timezone = self::getTimezone($market);
        if($timezone){
            $this->query("SET SESSION time_zone = " . $this->escape($timezone));
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
        return $this->database_name . ($this->slaveSelectMode ? " (slave)" : "");
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
        return "'" . $this->connect->real_escape_string($value) . "'";
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
                throw System_WarningException::create(
                    'Error when binding parameters with the string',
                    [
                        'query'  => $string,
                        'params' => $params,
                    ]
                );
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
    }

    /**********************************************************************************/

    /**
     * Выполнить запрос
     *
     * @param $query
     *
     * @return bool|mysqli_result
     *
     * @throws Exception
     */
    public function query($query){
        $query = trim($query);

        if($this->slaveSelectMode){
            $slaveQueryStarts = ["select", "show", "set"];

            $toSlave = false;

            foreach($slaveQueryStarts as $slaveQueryStart){
                if(strtolower(substr($query, 0, strlen($slaveQueryStart))) == strtolower($slaveQueryStart)){
                    $toSlave = true;
                    break;
                }
            }

            if(!$toSlave){
                return $this->getMasterConnect()->query($query);
            }
        }

        $this->getProfiler()->startQuery($query);
        $result = $this->connect->query($query);
        $this->getProfiler()->endQuery();

        if($this->connect->errno){
            throw new Exception($this->connect->error, $this->connect->errno);
        }
        return $result;
    }

    public function multiQuery($query){
        $this->getProfiler()->startQuery($query);
        $result = $this->connect->multi_query($query);
        $this->getProfiler()->endQuery();

        if($this->connect->errno){
            throw new Exception($this->connect->error, $this->connect->errno);
        }
        return $result;
    }

    /**********************************************************************************/

    public function transactionStart(){
        $this->query("START TRANSACTION");
    }

    public function transactionCommit(){
        $this->query("COMMIT");
    }

    public function transactionRollback(){
        $this->query("ROLLBACK");
    }

    /**********************************************************************************/

    /**
     * @param       $table
     * @param array $data
     *
     * @return int
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

                $keys   .=  "`{$k}`";
                $values .=  $this->escape($v);
            }

            $this->query("INSERT INTO `{$table}` ({$keys}) VALUES ({$values})");
	    
            // $action = new Version_DbAction($this->database_name, $table, Versions::QUERY_TYPE_INSERT);
            // $action->data = $data;
            // Versions::addAction($action);
	    
            return $this->lastInsertId();
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

                $keys   .=  "`{$k}`";
                $values .=  $this->escape($v);
            }

            $this->query("REPLACE INTO `{$table}` ({$keys}) VALUES ({$values})");
	    
	        // $action = new Version_DbAction($this->database_name, $table, Versions::QUERY_TYPE_REPLACE);
	        // $action->data = $data;
	        // Versions::addAction($action);
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

            $query_start = "INSERT{$flags} INTO `{$table}`(`" . implode("`,`", $keys) . "`) VALUES ";
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

            return $inserted;
        }

        return null;
    }

    /**
     * @return int
     */
    public function lastInsertId(){
        return $this->connect->insert_id;
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
            $query = "UPDATE `{$table}` SET ";

            $first = true;

            if(is_array($data)){
                foreach ($data as $k => $v) {
                    if(!$first) $query .=  ",";
                    else        $first = false;

                    $query .= "`{$k}`=" . $this->escape($v) . "";
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
                    $add[] = "`{$k}`=" . $this->escape($v);
                }
                $query .= implode(" and ", $add);
            }

            $this->query($query);

            // if($this->connect->affected_rows){
            //     $action = new Version_DbAction($this->database_name, $table, Versions::QUERY_TYPE_UPDATE);
            //     $action->data = $data;
            //     $action->where = $where;
            //     $action->where_params = $whereParams;
            //     Versions::addAction($action);
            // }

            return $this->connect->affected_rows;
        }

        throw new Exception("Invalid update data");
    }

    public function delete($table, $where = null, $whereParams = []){
        $query = "DELETE FROM `{$table}` ";

        if(is_string($where)){
            // where params
            if (func_num_args() > 3) {
                $whereParams = func_get_args();
                unset($whereParams[0], $whereParams[1]);
            }
            if (!is_array($whereParams)) $whereParams = array($whereParams);

            $query .= " WHERE " . $this->bind($where, $whereParams);
        }
        else if (is_array($where) && count($where)) {
            $query .= " WHERE ";
            $add = array();
            foreach ($where as $k => $v) {
                $add[] = "`{$k}`=" . $this->escape($v);
            }
            $query .= implode(" and ", $add);
        }

        $this->query($query);

        // $action = new Version_DbAction($this->database_name, $table, Versions::QUERY_TYPE_DELETE);
        // $action->where = $where;
        // $action->where_params = $whereParams;
        // Versions::addAction($action);


        return $this->connect->affected_rows;
    }

    /**
     * @param $query
     * @param array|mixed $params
     *
     * @return mysqli_result
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

        if(!($result instanceof mysqli_result)){
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

        return $res->num_rows ? $res->fetch_row()[0] : false;
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

        return $res->num_rows ? $res->fetch_assoc() : false;
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
    public function fetchCol($query, $params = []){
        // where params
        if (func_num_args() > 2) {
            $params = func_get_args();
            unset($params[0]);
        }
        if (!is_array($params)) $params = array($params);

        $array = [];
        $res = $this->fetch($query, $params);
        while ($el = $res->fetch_row()) {
            $array[] = $el[0];
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
        while($el = $res->fetch_assoc()){
            $array[] = $el;
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
        if($res->field_count == 2){
            while($el = $res->fetch_row()){
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

        while($el = $res->fetch_assoc()){
            if(!isset($array[current($el)])){
                $array[current($el)] = $el;
            }
        }

        return $array;
    }




}