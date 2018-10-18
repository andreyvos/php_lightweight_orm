<?php

class Db_Model_Update_Plus implements Db_Model_Update_Interface {
    protected $value;
    protected $precision;

    public function __construct($value, $precision = 0){
        $this->value        = (double)$value;
        $this->precision    = max(0, (int)$precision);
    }

    public function getQuery($currentValue, $valueName){
        return new Db_Expr(
            "ROUND(`{$valueName}`" . (($this->value >= 0) ? "+" : "") . "{$this->value}, {$this->precision})"
        );
    }

    public function getNewValue($currentValue){
        return round($currentValue + $this->value, $this->precision);
    }
}