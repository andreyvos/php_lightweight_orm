<?php

class Db_Statment_Select extends Db_Statment_Select_Abstract {
    protected $isDistinct = false;
    
    protected $colums = array();
    
    protected $from = '';
    protected $join = [];

    protected $where = array();
    protected $whereParams = array();
    
    protected $order = array();
    
    protected $group = array();
    
    protected $having = array();
    protected $havingParams = array();
    
    protected $limitStart = 0;
    protected $limitRows = 0;
    
    public function __construct(Db_Connection_Abstract $adapter, $from = null, $columns = array()){
        parent::__construct($adapter); 
        $this->from($from, $columns); 
    }
    
    /**
    * Отображать или нет дублирующиеся записи по запрошенным полям
    * 
    * @param mixed $isDistinct
    * 
    * @return self
    */
    public function distinct($isDistinct = true){
        $this->isDistinct = (bool)$isDistinct;
        return $this;
    }
    
    /**
    * Установить массив получаемых полей
    * 
    * @param mixed $colums
    * @return Db_Statment_Select
    */
    public function setColums($colums){
        if(!is_array($colums)) $colums = array($colums);
        $this->colums = $colums;
        return $this;
    }
    
    /**
    * Указать таблицу и поля по которым делать выборку
    * 
    * @param mixed $table 
    * @param array $columns OR $column1, [$column2, $column3, ...]
    * 
    * @return self
    */
    public function from($table, $columns = array()){
        if(func_num_args() > 2){
            $columns = func_get_args();
            unset($columns[0]);
            $columns = array_values($columns);   
        }
        
        if(!is_array($columns)) $columns = array($columns);
        if(count($columns))     $this->colums = array_merge($this->colums, $columns);

        if(is_string($table)){
            $explode_from = explode(",", $table);

            if($explode_from > 1){
                $table = [];

                foreach($explode_from as $el){
                    $el = trim($el);
                    if(strlen($el)){
                        $table[] = $el;
                    }
                }
            }
        }

        $this->from = $table; 
        
        return $this;
    }

    /**
     * @param        $table
     * @param string $on
     * @param string $type
     *
     * @return $this
     */
    public function join($table, $on = "", $type = 'LEFT'){
        $this->join[] = [
            'table' => $table,
            'on'    => $on,
            'type'  => $type,
        ];

        return $this;
    }

    /**
    * Добавить условие
    *
    * @param mixed $query
    * @param array|mixed $params OR $param1, [$param2, $param3, ...]
    *
    * @return self
    */
    public function where($query, $params = array()){
        $query = trim($query);

        if(strlen($query)){
            if(func_num_args() > 2){
                $params = func_get_args();
                unset($params[0]);
            }

            if(!is_array($params)) $params = array($params);

            if(count($params)){
                foreach($params as $k => $v){
                    if(is_numeric($k)) $this->whereParams[] = $v;
                    else               $this->whereParams[$k] = $v;
                }
            }

            $this->where[] = $query;
        }

        return $this;
    }
    
    /**
    * Добавить условие типа in
    * 
    * @param mixed $query
    * @param array|mixed $params OR $param1, [$param2, $param3, ...]
    * 
    * @return self
    */
    public function where_in($query, $params = array()){
        if(func_num_args() > 2){
            $params = func_get_args();
            unset($params[0]);   
        }
        
        return $this->where(str_replace("?", "(?" . str_repeat(",?", count($params) - 1) . ")", $query), $params);
    }
    
    /**
    * Добавить условие которое накладывается на уже полученные из базы данные
    * 
    * @param mixed $query
    * @param array $params OR $param1, [$param2, $param3, ...]
    * 
    * @return self
    */
    public function having($query, $params = array()){
        if(func_num_args() > 2){
            $params = func_get_args();
            unset($params[0]);   
        }
        
        if(!is_array($params)) $params = array($params);
            
        if(count($params)){
            foreach($params as $k => $v){
                if(is_numeric($k)) $this->havingParams[] = $v;
                else               $this->havingParams[$k] = $v;
            }
        } 
        
        $this->having[] = $query;
        return $this;
    }

    /**
     * Добавить правила сортировки
     *
     * @param array $order $order1, [$order2, $order3, ...]
     * @return $this
     */
    public function order($order){
        if(func_num_args() > 1) $order = func_get_args();
        if(!is_array($order)) $order = array($order);
        
        foreach($order as $ord){
            $o = explode(" ", $ord, 2);
            if(count($o) == 2 && in_array(strtoupper($o[1]), array('ASC', 'DESC'))){
                $this->order[] = $this->quoteName($o[0]) . " " . strtoupper($o[1]);      
            }                                         
            else {
                $this->order[] = $this->quoteName($ord);    
            }     
        }
        
        return $this;
    }
    
    /**
    * Добавить правила групировки
    * 
    * @param array $group OR $group1, [$group2, $group3, ...]
    * 
    * @return $this
    */
    public function group($group){
        if(func_num_args() > 1) $group = func_get_args();
        if(!is_array($group)) $group = array($group);
        
        foreach($group as $gr){
            $g = explode(" ", $gr, 2);
            if(count($g) == 2 && in_array(strtoupper($g[1]), array('ASC', 'DESC'))){
                $this->group[] = $this->quoteName($g[0]) . " " . strtoupper($g[1]);      
            }                                         
            else {
                $this->group[] = $this->quoteName($gr);    
            }     
        }
        
        return $this;
    }
    
    /**
    * Установить ограничение строк получаемых с базы
    * 
    * @param int $rows   Сколько строк
    * @param int $start  С какой строки начинать
    * 
    * @return $this
    */
    public function limit($rows = 0, $start = 0){
        $this->limitRows = (int)$rows;
        $this->limitStart = (int)$start; 
        
        return $this;
    }
    
    /**
    * Формирование массива параметров
    */
    public function getParams(){
        if(count($this->havingParams)){
            $params = $this->whereParams;
            foreach($this->havingParams as $k => $v){
                if(is_numeric($k)) $params[] = $v;
                else               $params[$k] = $v;
            } 
            return $params;  
        }
        return $this->whereParams;
    }

    public function getBindQuery(){
        return $this->adapter->bind($this->getQuery(), $this->getParams());
    }

    /**
     * Создание SELECT запроса
     *
     * @param bool $count
     * @return mixed|string
     */
    public function getQuery($count = false){
        // colums
        $colums = ($this->isDistinct) ? " DISTINCT " : " ";
        if(is_array($this->colums) && count($this->colums)){
            $columsArr = array();
            foreach($this->colums as $k => $v){
                if(is_numeric($k)) $columsArr[] = $this->quoteName($v);
                else               $columsArr[] = $this->quoteName($v) . " AS " . $this->quoteName($k);
            }
            $colums.= implode(",", $columsArr);
        }
        else {
            if(is_string($this->from)){
                $colums.= $this->quoteName($this->from) . ".*";
            }
            else if(is_array($this->from) && count($this->from) == 1 && isset($this->from[0])){
                $colums.= $this->quoteName($this->from[0]) . ".*";
            }
            else {
                $colums.= "*";
            }
        }

        if($count){
            if($this->isDistinct){
                $colums = " count({$colums})";
            }
            else {
                $colums = " count(*) ";
            }
        }
        
        // from
        if(is_array($this->from)){
            $temp = [];

            foreach($this->from as $el){
                $temp[] = $this->quoteName($el);
            }

            $from = " FROM " . implode(", ", $temp);
        }
        else {
            $from = " FROM " . $this->quoteName($this->from);
        }



        // join
        $join = "";
        if(count($this->join)){
            $join = [];
            foreach($this->join as $el){
                $join[] = " {$el['type']} JOIN " . $this->quoteName($el['table']) .
                    (strlen($el['on']) ?
                        " ON (" . $el['on'] . ")" : ""
                    );
            }

            $join = implode(" ", $join);
        }
        
        // where 
        $where = count($this->where) ? " WHERE (" . implode(") AND (", $this->where) . ")" : "";
        
        // group
        $group = count($this->group) ? " GROUP BY " . implode(",", $this->group) : "";
        
        // where 
        $having = count($this->having) ? " HAVING " . implode(" AND ", $this->having) : "";
        
        // order
        $order = count($this->order) ? " ORDER BY " . implode(",", $this->order) : "";
        
        // limit
        $limit = "";
        if($this->limitRows){
            $limit = " LIMIT ";
            $limit.= ($this->limitStart) ? "{$this->limitStart},{$this->limitRows}" : $this->limitRows; 
        } 
            
        return "SELECT" . $colums . $from . $join . $where . $group . $having . $order . $limit;
    }     
}
