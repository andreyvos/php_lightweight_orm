<?php

class Db_Connection {
//    /**
//     * @param $config Conf_Db
//     * @return Db_Abstract_Connection
//     */
//    public function __construct($config){
//        if(isset($config->driver)){
//            if(isset($config->engine)){
//                if($config->engine != Conf_Db::ENGINE_MYSQL){
//                    return new Db_PdoConnection($config);
//                }else{
//                    if($config->driver == Conf_Db::DRIVER_MYSQLI){
//                        return new Db_MysqliConnection($config);
//                    }elseif($config->driver == Conf_Db::DRIVER_PDO){
//                        return new Db_PdoConnection($config);
//                    }
//                }
//            }
//        }
//        //default
//        return new Db_MysqliConnection($config);
//    }

    /**
     * @param        $config
     * @param null   $market
     * @param string $name
     *
     * @return Db_Connection_Mysqli|Db_Connection_Pdo
     */
    static public function create($config, $market = null, $name = ""){
        if(isset($config->driver)){
            if(isset($config->engine)){
                if($config->engine == Conf_Db::ENGINE_MYSQL){
                    if($config->driver == Conf_Db::DRIVER_PDO){
                        return new Db_Connection_Pdo($config, $market);
                    }
                    else {
                        return new Db_Connection_Mysqli($config, $market, $name);
                    }
                }
                else {
                    return new Db_Connection_Pdo($config, $market);
                }
            }
        }

        return new Db_Connection_Mysqli($config, $market, $name);
    }
} 