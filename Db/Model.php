<?php

abstract class Db_Model {
    /**
     * @var array
     */
    static protected $_objects = [];

    /**
     * Получить IDS загруженных объектов
     *
     * @return array
     */
    static public function getLoadedObjectsProfile(){
        $res = [];
        if(count(static::$_objects)){
            foreach(static::$_objects as $class => $objects){
                $res[$class] = [
                    'count' => count($objects),
                ];
            }
        }

        return $res;
    }

    /**
     * Загрузить объект из хранилища
     *
     * @param $id
     * @return static
     */
    static public function getObject($id){
        $id = (int)$id;
        if(!isset(static::$_objects[get_called_class()])) static::$_objects[get_called_class()] = [];
        if(!isset(static::$_objects[get_called_class()][$id])){
            static::$_objects[get_called_class()][$id] = new static();
            /** @noinspection PhpUndefinedMethodInspection */
            static::$_objects[get_called_class()][$id]->load($id);
        }
        return static::$_objects[get_called_class()][$id];
    }

    /**
     * Проверить загружен ли объект
     *
     * @param $id
     * @return bool
     */
    static public function isLoadObject($id){
        return isset(static::$_objects[get_called_class()][(int)$id]);
    }

    static public function bulk($ids){
        $newIds = [];

        if(is_array($ids) && count($ids)){
            foreach($ids as $id){
                $id = (int)$id;

                if($id && !static::isLoadObject($id)){
                    $newIds[] = $id;
                }
            }

            if(count($newIds)){
                $newIds = array_unique($newIds);

                $all = static::getDatabase()->fetchAll(
                    "select id, `" . implode('`,`', static::getFields()) . "` from `" . static::$_table . "` where id in (" . implode(",", $newIds) . ")"
                );

                if(count($all)){
                    foreach($all as $el) {
                        $obj = new static();
                        $obj->id = $el['id'];

                        unset($el['id']);

                        $obj->loadFromArray($el);
                        static::setObject($obj);
                    }

                }
            }

        }
    }

    /**
     * Сохранить объект в хранилище
     *
     * @param Db_Model $object
     */
    static public function setObject(Db_Model $object){
        if(!isset(static::$_objects[get_called_class()])) static::$_objects[get_called_class()] = [];
        if($object->id){
            static::$_objects[get_called_class()][(int)$object->id] = $object;
        }
    }

    static public function getTableName()
    {
        return static::$_table;
    }

    static public function getTableStructure()
    {
        return static::$_structure;
    }

    static public function getDatabaseStatic(){
        return static::getDatabase();
    }
    /***************************************************************************/

    /**
    * Имя таблицы, которое будет использоваться в запросах
    *
    * @var string
    */
    static protected $_table;

    /**
     * Структура полей
     *
     * @var array
     */
    static protected $_structure = [];

    /**
     * Поля, которые должны сохъраняться
     *
     * @var array
     */
    static protected $_array_values = [];

    /**
    * @return Db_Connection_Abstract
    */
    abstract protected function getDatabase();

    public $id;

    /***************************************************************************/

    /**
     * Подбор полей в соответсвии с требованиями
     *
     * @param array $only   только указанные поля (если они выбранны, то переменная update не учитываеться)
     * @param bool $update  если не нужны определенные поля, то поля для апдейта, откуда могут быть исключены некоторые поля
     *
     * @return array
     *
     * @throws Exception
     */
    protected function getFields($only = [], $update = false){
        $all = [];

        if(count(static::$_structure)){
            // Если в only переденно только 1 строковое значение
            if(is_string($only) && strlen($only)) $only = [$only];

            if(is_array($only) && count($only)){
                // только выбранные поля
                foreach(static::$_structure as $el){
                    if(is_array($el)) $el = $el[0];
                    if(in_array($el, $only))$all[] = $el;
                }

                if(count($all) != count($only)){
                    $not = [];
                    foreach($only as $el){
                        if(!in_array($el, $all)) $not[] = $el;
                    }

                    throw new Exception("Model `" . get_called_class() . "` not included fields: `" . implode("`, `", $not) . "`");
                }
            }
            else if($update){
                foreach(static::$_structure as $el){
                    if(!is_array($el)) $all[] = $el;
                }

                if(!count($all)){
                    throw new Exception("Model `" . get_called_class() . "` not included fields for default update");
                }
            }
            else {
                foreach(static::$_structure as $el){
                    if(is_array($el)) $el = $el[0];
                    $all[] = $el;
                }

                if(!count($all)){
                    throw new Exception("Model `" . get_called_class() . "` not included fields");
                }
            }
        }

        return $all;
    }

    /***************************************************************************/

    /**
     * Иницилизироать массив данных в объект
     *
     * @param array $array
     */
    protected function baseToObject($array){
        if(is_array($array) && count($array)){
            foreach($array as $k => $v){
                $this->$k = $v;
            }
        }

        // декодирование JSON в Array
        if(count(static::$_array_values)){
            foreach(static::$_array_values as $k){
                $this->$k = Json::decode($this->$k);
                if(!is_array($this->$k)){
                    $this->$k = [];
                }
            }
        }
    }

    /**
     * Формирует из объекта массив для базы данных
     *
     * @param array $only
     * @param bool $update
     * @return array
     */
    protected function objectToBase($only = [], $update = false){
        $result = array();

        foreach($this->getFields($only, $update) as $el){
            $result[$el] = isset($this->$el) ? $this->$el : null;
        }

        // преобразование Array в JSON для записи в базу
        if(count(static::$_array_values)){
            foreach(static::$_array_values as $k){
                if(isset($result[$k])){
                    $result[$k] = Json::encode(
                        is_array($result[$k]) ? $result[$k] : []
                    );
                }
            }
        }

        return $result;
    }

    /***************************************************************************/

    /**
     * @param $array
     * @return $this
     */
    public function loadFromArray($array){
        $this->baseToObject($array);
        return $this;
    }

    /**
     * Загрузить объект по id
     *
     * @param $id
     * @return $this
     */
    public function load($id){
        $id = (int)$id;

        if($id != 0){
            $all = $this->getDatabase()->fetchRow(
                "select `" . implode('`,`', $this->getFields()) . "` from `" . static::$_table . "` where id=?", $id
            );

            if(is_array($all) && count($all)){
                $this->id = $id;
                $this->baseToObject($all);
            }
            else {
                $this->id = null;
            }
        }
        else {
            $this->id = null;
        }

        return $this;
    }

    /**
     * Записать объект в базу данных
     */
    public function insert(){
        $this->getDatabase()->insert(
            static::$_table,
            $this->objectToBase()
        );

        if(isset($this->objectToBase()['id'])){
            $this->id = (string)$this->id;
        }
        else {
            $this->id = $this->getDatabase()->lastInsertId();
        }
    }

    /**
     * Обночить базу данных из объекта
     *
     * @param array $fields
     * @return $this
     */
    public function update($fields = []){
        if($this->id){
            $this->getDatabase()->update(
                static::$_table,
                $this->objectToBase($fields, true),
                "id=?", (int)$this->id
            );
        }

        return $this;
    }

    /**
     * Обновить таблицу из массива.
     * Не обязательно присутствие данных в модели
     *
     * @param array $array
     */
    public function updateArray(array $array){
        if(is_array($array) && count($array) && $this->id){
            $update = [];
            foreach($array as $k => $v){
                if($v instanceof Db_Model_Update_Interface){
                    $this->$k = $v->getNewValue(
                        isset($this->$k) ? $this->$k : null
                    );

                    $update[$k] = $v->getQuery(
                        isset($this->$k) ? $this->$k : null,
                        $k
                    );
                }
                else {
                    $this->$k = $v;
                    $update[$k] = $v;
                }
            }

            $this->getDatabase()->update(
                static::$_table,
                $update,
                "id=?", (int)$this->id
            );
        }
    }

    public function delete(){
        if(isset($this->id) && $this->id){
            $this->getDatabase()->delete(static::$_table, "id=?", $this->id);
            $this->id = null;
        }
    }

    /***************************************************************************/

    /**
     * Получить массив, созданный из элементов объекта, которые загружаются из базы данных
     */
    public function getParams(){
        $result = [
            'id' => $this->id,
        ];
        foreach($this->getFields() as $el){
            $result[$el] = isset($this->$el) ? $this->$el : null;
        }
        return $result;
    }

}
