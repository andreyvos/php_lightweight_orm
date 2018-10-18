<?php

class Db {

    /**************************************************************************/

    static protected $default_market;

    /**
     * Задание маркета, к которому будет подключаться база
     *
     * @param $market
     */
    static public function setDefaultMarket($market){
        self::$default_market = $market;
    }


    /**
     * Определения маркета по умолчанию, к которому подключаеться база
     *
     * @return int
     */
    static public function getDefaultMarket(){

        if(is_null(self::$default_market)){
            self::$default_market = Lead_Market::USA;
        }

        return self::$default_market;
    }

    /**************************************************************************/

    /**
     * @var Db_Connection_Abstract[][]
     */
    static protected $connections = [];

    /**
     * @param $name
     * @param null $config
     * @param int $market
     * @return Db_Connection_Mysqli|Db_Connection_Pdo
     */
    static public function get($name, $config = null, $market = 0){
        $marketID = (int)$market;

        if(!isset(self::$connections[$name][$marketID])){
            /**
             * @var $config Conf_Db
             */
            self::$connections[$name][$marketID] = Db_Connection::create($config, $market, $name);
        }

        return self::$connections[$name][$marketID];
    }

    /**************************************************************************/

    static protected $site                  = 1;
    static protected $processing            = 2;
    static protected $reports               = 3;
    static protected $logs                  = 4;
    static protected $scheduler             = 5;
    static protected $migration_logs        = 6;
    static protected $v2_t3api_replica      = 7;

    static protected $test          = 5432;

    /**
     * Получить данные доступа ко всем слейвам
     *
     * @return array
     * @throws System_WarningException
     */
    static public function getAllSlavesInfo(){
        $res = [];
        $slaves = [];

        // processing_slaves
        if(count(Conf::mysql_processing()->processing_slaves)){
            $processing = Conf::mysql_processing()->getArray();
            unset($processing['processing_slaves']);

            foreach(Conf::mysql_processing()->processing_slaves as $market => $ps){
                $slaves[] = [
                    'market' => $market,
                    'config' => new Conf_Mysql(array_merge($processing, $ps)),
                ];
            }
        }

        if(count($slaves)){
            foreach($slaves as $slave) {
                try{
                    $connection = Db_Connection::create($slave['config']);
                }
                catch(Exception $e){

                    $doc = [
                        'market' => $slave['market'],
                        'config' => $slave['config']->getArray(),
                    ];

                    $doc['config']['pass'] = "***";

                    throw System_WarningException::create(
                        "Invalid connection to slave: " . $e->getMessage(),
                        $doc
                    )
                        ->setLevel(System_WarningsLevel::Standard);
                }

                $slaveRes = $connection->fetchAll("show slave status");
                $slaveRes = isset($slaveRes[0]) ? $slaveRes[0] : [];

                $add = [
                    'market'                => $slave['market'],
                    'base'                  => $slave['config']->base,
                    'host'                  => $slave['config']->host . ":" . $slave['config']->port,
                    'status'                => 0,
                    'Slave_IO_Running'      => isset($slaveRes['Slave_IO_Running']) ? $slaveRes['Slave_IO_Running'] : "",
                    'Slave_SQL_Running'     => isset($slaveRes['Slave_SQL_Running']) ? $slaveRes['Slave_SQL_Running'] : "",
                    'Seconds_Behind_Master' => isset($slaveRes['Seconds_Behind_Master']) ? $slaveRes['Seconds_Behind_Master'] : "",
                    'Last_Error'            => isset($slaveRes['Last_Error']) ? $slaveRes['Last_Error'] : "",
                    'full'                  => Json::encode($slaveRes),
                ];

                if(
                    $add['Slave_IO_Running']        == 'Yes' &&
                    $add['Slave_SQL_Running']       == 'Yes' &&
                    $add['Seconds_Behind_Master']   <   60
                ){
                    $add['status'] = 1;
                }

                $res[] = $add;
            }
        }

        return $res;
    }

    /**
     * @return Db_Connection_Mysqli|Db_Connection_Pdo
     */
    static public function site(){
        return self::get(self::$site, Conf::mysql_site());
    }

    /**
     * @return Db_Connection_Mysqli|Db_Connection_Pdo
     */
    static public function processing(){
        return self::get(self::$processing, Conf::mysql_processing());
    }


    /**
     * @param int $market
     *
     * @return Db_Connection_Mysqli|Db_Connection_Pdo
     */
    static public function reports($market = 0){
        if($market == 0){
            $market = self::getDefaultMarket();
        }

        return self::get(self::$reports, Conf::mysql_reports($market), $market);
    }


    /**
     * @param int $market
     *
     * @return Db_Connection_Mysqli|Db_Connection_Pdo
     */
    static public function logs($market = 0){
        if($market == 0){
            $market = self::getDefaultMarket();
        }

        return self::get(self::$logs, Conf::mysql_logs($market), $market);
    }


    /**
     * @param int $market
     *
     * @return Db_Connection_Mysqli|Db_Connection_Pdo
     */
    static public function scheduler($market = 0){
        if($market == 0){
            $market = self::getDefaultMarket();
        }

        return self::get(self::$scheduler, Conf::mysql_scheduler($market), $market);
    }

    /**
     * @return Db_Connection_Pdo
     */
    static public function test(){
        return self::get(self::$test, Conf::pgsql_test());
    }

    static public function migration_logs(){
        return self::get(self::$migration_logs, Conf::mysql_migration_logs());
    }

    static public function v2_t3api_replica(){
        return self::get(self::$v2_t3api_replica, Conf::mysql_v2_t3api_replica());
    }

    /**************************************************************************/

    /**
     * @return Db_Connection_Abstract[][]
     */
    static public function getAllCurrentConnections(){
        return self::$connections;
    }

    /**
     * Получить общую информацию из профайлера
     *
     * @return array
     */
    static public function getProfilerSummaryInfo(){
        $all = [];
        if(count($connections = self::getAllCurrentConnections())){
            foreach($connections as $id => $connectionsPool) {
                foreach($connectionsPool as $market_id => $connect) {
                    $p = $connect->getProfiler();
                    $all[$id . "." . $market_id] = array(
                        'id'                     => $id,
                        'market'                 => $market_id,
                        'slave_mode'             => $connect->isSlaveMode(),
                        'database_name'          => $connect->getDatabaseNameForShow(),
                        'database_host'          => $connect->getHost(),
                        'database_host_market'   => $connect->getHostMarket(),
                        'database_engine_driver' => strtoupper($connect->getEngine()) . '/' . strtoupper($connect->getDriver()),
                        'querys_count'           => $p->getQueriesCount(),
                        'runtime'                => $p->getRuntime(),
                        'connected_time'         => $p->getConnectedTime(),
                        'lond_query_time'        => $p->getLongQueryTime(),
                        'lond_query'             => $p->getLongQuery()
                    );
                }
            }
        }

        return $all;
    }

    static public function getProfilerAllQueriesText(){
        $all = [];
        if(count($connections = self::getAllCurrentConnections())){
            foreach($connections as $id => $connectionsPool) {
                foreach($connectionsPool as $market_id => $connect) {
                    $p = $connect->getProfiler();
                    $all[$connect->getDatabaseNameForShow()] = $connect->getProfiler()->getAllQueriesText();
                }
            }
        }

        return $all;
    }

    static public function getDatabaseRuntime(){
        $runtime = 0;

        if(count($connections = self::getAllCurrentConnections())){
            foreach($connections as $id => $connectionsPool) {
                foreach($connectionsPool as $market_id => $connect) {
                    $runtime += $connect->getProfiler()->getRuntime();
                }
            }
        }

        return $runtime;
    }
}