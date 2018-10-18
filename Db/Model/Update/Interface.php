<?php

interface Db_Model_Update_Interface {
    public function getQuery($currentValue, $valueName);
    public function getNewValue($currentValue);
}