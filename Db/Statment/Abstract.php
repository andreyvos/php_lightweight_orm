<?php


abstract class Db_Statment_Abstract {
    /**
     * @var Db_Connection_Abstract
     */
    protected $adapter;

    public function __construct(Db_Connection_Abstract $adapter){
        $this->adapter = $adapter;
    }

    /**
     * Полуить строку запроса, без Params
     *
     * @param bool $count
     * @return mixed
     */
    abstract public function getQuery($count = false);

    /**
     * Получить параметры запроса
     * @return array
     */
    abstract public function getParams();

    public function __toString(){
        return (string)$this->getQuery();
    }

    /**
     * Добавление кавычек к название таблиц, полей и т.д.
     * Если название содержит только A-z 0-9 или _ или - оно обромляется амперсантами
     * Если передать массив, то он добавит ковычки ко всем его элементам в отдельности
     *
     * @param $name
     * @return array|string
     */
    protected function quoteName($name){
        if(is_array($name)){
            foreach($name as $k => $v){
                $name[$k] = $this->quoteName($v);
            }
            return $name;
        }

        $name = (string)$name;

        if(!preg_match('/^[a-z0-9_\-]*$/i', $name)) return $name;

        return "`{$name}`";
    }
} 