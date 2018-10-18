<?php

class Db_Expr {
    protected $v;

    public function __construct($string){
        $this->v = $string;
    }

    public function __toString(){
        return (string)$this->v;
    }
}