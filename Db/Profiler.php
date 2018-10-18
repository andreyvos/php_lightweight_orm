<?php

class Db_Profiler {
    protected $queryStart = null;

    protected $is = false;

    protected $queries = [];

    protected $runtime = 0;
    protected $connectedTime = 0;

    /**
     * Запустить профайлер
     */
    public function start(){
        $this->is = true;
    }

    /**
     * Приостановить профайлер
     */
    public function stop(){
        $this->is = false;
    }

    /**
     * Отчистить данные в профайлере
     */
    public function clear(){
        $this->queries = [];
        $this->runtime = 0;
        $this->connectedTime = 0;
    }

    /******************************************************************/

    /**
     * Добавить запрос
     *
     * @param $query
     * @param $params
     * @param $runtime
     */
    public function startQuery($query, $params = null, $runtime = null){
        if($this->is){
            $this->queryStart = microtime(true);

            if($runtime !== null){
                $this->runtime+= $runtime;

                $this->queryStart = microtime(true) - $runtime;
            }

            $this->queries[] = array(
                's' => $this->queryStart - Boot::getStartMicrotime(),
                'q' => $query,
                'p' => $params,
                'r' => $runtime,
            );
        }
    }

    public function endQuery(){
        if($this->is){
            if($this->queryStart){
                $runtime = microtime(true) - $this->queryStart;
                if($runtime){
                    $this->queries[count($this->queries) - 1]['r'] = microtime(true) - $this->queryStart;
                    $this->runtime+= $runtime;
                }
            }

            $this->queryStart = null;
        }
    }

    /**
     * Получить лог запросов
     *
     * @return array
     */
    public function getAllQueries(){
        return $this->queries;
    }

    /**
     * Получить количесво выполненных запросов
     *
     * @return array
     */
    public function getQueriesCount(){
        return count($this->queries);
    }

    public function getLastQuery(){
        if(count($this->queries)){
            return $this->queries[count($this->queries) - 1];
        }
        return null;
    }

    public function getLastQueryTime(){
        $last = $this->getLastQuery();
        if($last){
            return $last['r'];
        }
        return 0;
    }

    /**
     * Получить Время самого долгого запроса
     *
     * @return array
     */
    public function getLongQueryTime(){
        $max = 0;

        if(count($this->queries)){
            foreach($this->queries as $q){
                if($q['r'] > $max){
                    $max = $q['r'];
                }
            }
        }

        return $max;
    }

    /**
     * Получить самый долгий запрос
     *
     * @return string
     */
    public function getLongQuery(){
        $max = 0;
        $query = '';

        if(count($this->queries)){
            foreach($this->queries as $q){
                if($q['r'] > $max){
                    $max    = $q['r'];
                    $query  = $q['q'];
                }
            }
        }

        return $query;
    }


    /**
     * Получить время, потраченное на запросы к базе данных
     *
     * @return float
     */
    public function getRuntime(){
        return $this->runtime;
    }

    /**
     * Получить время, в течении которого было установленно соединение к базе
     *
     * @return float
     */
    public function getConnectedTime(){
        return $this->connectedTime;
    }

    /**
     * Получить запросы в текстовом виде
     *
     * Формат:
     * XXX  YYY  ZZZ  Query1
     * XXX  YYY  ZZZ  Query2
     * ...
     *
     * XXX - Время выполнения запроса
     * YYY - Время от завершения предыдущего, до начала текущего запроса
     * ZZZ - Время от начала выполнения скрипта, до начала выполнения запроса
     *
     * (если в числе нет точки, значит это стотысячные доли секунды)
     *
     * @param bool $html
     * @return string
     */
    public function getAllQueriesText($html = false){
        if($html){
            $r =
                "<div style='margin-bottom: 2px; padding-bottom: 0px; border-bottom: #EEE solid 1px; display: inline'>" .
                "  0.00000   0.00000   0.00000    Query     " .
                "</div>" . "\r\n";
        }
        else {
            $r = "  0.00000   0.00000   0.00000    Query" . "\r\n";
            $r.= "----------------------------------------" . "\r\n";
        }
        $end    = 0;

        foreach($this->queries as $query){
            $query_string = $query['q'];

            if($html && strlen($query_string) > 100){
                $query_string =
                    "<a style='cursor:pointer' onclick=\"jQuery(this).find('.short').toggle(); jQuery(this).find('.full').toggle();\">" .
                        "<span class='short'>" . substr($query_string, 0, 100) . "...</span>" .
                        "<span class='full' style='display:none'>{$query_string}</span>" .
                    "</a>";
            }

            $r.=
                str_pad(ltrim(sprintf("%.5f", $query['r']), '0.'), 9, " ", STR_PAD_LEFT) . " " .
                str_pad(ltrim(sprintf("%.5f", $query['s'] - $end), '0.'), 9, " ", STR_PAD_LEFT) . " " .
                str_pad(ltrim(sprintf("%.5f", $query['s']), '0.'), 9, " ", STR_PAD_LEFT) . "    " .
                str_replace(
                    ["\r\n", "\r", "\n"],
                    [" ", " ", " "],
                    $query_string
                ) . "\r\n";

            $end = $query['s'] + $query['r'];
        }

        return $r;
    }
}