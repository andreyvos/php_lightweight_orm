<?php


abstract class Db_Statment_Select_Abstract extends Db_Statment_Abstract {
    /**
     * Получить все данные из базы данных
     *
     * @return array
     */
    public function fetchAll(){
        return $this->adapter->fetchAll($this->getQuery(), $this->getParams());
    }

    /**
     * Получить первую колонку
     *
     * @return array
     */
    public function fetchCol(){
        return $this->adapter->fetchCol($this->getQuery(), $this->getParams());
    }

    /**
     * Получить первое значение, первой строки
     * Желательно использовать в запросе limit 1, и искать только 1 поле, что бы не получать лишне даннеы
     *
     * @return array
     */
    public function fetchOne(){
        return $this->adapter->fetchOne($this->getQuery(), $this->getParams());
    }

    public function fetchCount(){
        return $this->adapter->fetchOne($this->getQuery(true), $this->getParams());
    }

    /**
     * Получить первую колонку в ключ, второе значение в значение
     *
     * @return array
     */
    public function fetchPairs(){
        return $this->adapter->fetchPairs($this->getQuery(), $this->getParams());
    }

    /**
     * Получить первую строчку в виде массива, где имена полей это ключи, а их данные это значения
     * Желательно в запрос добавлять limit 1
     *
     * @return array
     */
    public function fetchRow(){
        //Value::export($this->adapter->getDatabaseName());

        return $this->adapter->fetchRow($this->getQuery(), $this->getParams());
    }

    /**
     * Первое поле ставиться в ключ массива
     * При дублирующихся ключах, текущее значение заменяется на новое
     *
     * @return array
     */
    public function fetchUnique(){
        return $this->adapter->fetchUnique($this->getQuery(), $this->getParams());
    }
}