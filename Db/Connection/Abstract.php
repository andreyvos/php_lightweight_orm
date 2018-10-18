<?php


abstract class Db_Connection_Abstract {

    protected $host;
    protected $host_market;
    protected $connect;

    protected $database_name;

    /**
     * @var Db_Profiler
     */
    protected $profiler;

    protected $quotes = '"';

    protected $driver = "pdo";

    protected $engine;
    /**********************************************************************************/

    public function getDriver(){
        return $this->driver;
    }
    public function getEngine(){
        return $this->engine;
    }

    public function getHost(){
        return $this->host;
    }

    public function getHostMarket(){
        return $this->host_market;
    }
    /*************************************************/

    /**
     * Режим при котором основное подключение - это слей и на него идут все set, show и select запросы
     *
     * @var bool
     */
    protected $slaveSelectMode = false;

    public function isSlaveMode(){
        return $this->slaveSelectMode;
    }

    /**********************************************************************************/

    abstract public function __construct(Conf_Db $conf);


    /**********************************************************************************/

    /**
     * @return Db_Profiler
     */
    abstract public function getProfiler();

    abstract public function getDatabaseName();

    /**
     * Может не совпадать с реальным именем базы данных и содержать какие то поментки и комментарии
     *
     * @return mixed
     */
    abstract public function getDatabaseNameForShow();

    /**********************************************************************************/

    /**
     * @param string $from
     * @param array $columns
     * @return Db_Statment_Select_Abstract
     */
    abstract public function select($from = null, $columns = array());

    /**********************************************************************************/

    /**
     * Экранирование строки
     *
     * @param $value
     *
     * @return string
     */
    abstract public function escape($value);

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
    abstract public function bind($string, $params);

    /**********************************************************************************/

    /**
     * Выполнить запрос
     *
     * @param $query
     *
     * @return bool|mysqli_result|PDOStatement
     *
     * @throws Exception
     */
    abstract public function query($query);

    /**********************************************************************************/

    /**
     * Выполнить multi запрос
     *
     * @param $query
     *
     * @return bool|mysqli_result|PDOStatement
     *
     * @throws Exception
     */
    abstract public function multiQuery($query);

    /**********************************************************************************/

    abstract public function transactionStart();

    abstract public function transactionCommit();

    abstract public function transactionRollback();

    /**********************************************************************************/

    /**
     * @param $table
     * @param $data
     * @throws Exception
     */
    abstract public function insert($table, array $data);

    /**
     * @param $table
     * @param $data
     * @throws Exception
     */
    abstract public function replace($table, array $data);

    abstract public function insertMulti($table, array $data, $count = 500, $ignore = false);

    /**
     * @return int
     */
    abstract public function lastInsertId();

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
    abstract public function update($table, array $data, $where = null, $whereParams = []);

    abstract public function delete($table, $where = null, $whereParams = []);

    /**
     * @param $query
     * @param array|mixed $params
     *
     * @return mysqli_result|PDOStatement
     *
     * @throws Exception
     */
    abstract public function fetch($query, $params = []);

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
    abstract public function fetchOne($query, $params = []);

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
    abstract public function fetchRow($query, $params = []);

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
    abstract public function fetchCol($query, $params = []);

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
    abstract public function fetchAll($query, $params = []);

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
    abstract public function fetchPairs($query, $params = []);

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
    abstract public function fetchUnique($query, $params = []);


    static protected function getTimezone($market){
        return Conf::main()->timezone;
    }
} 