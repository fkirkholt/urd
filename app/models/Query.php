<?php

namespace URD\models;

class Query {

    function __construct($platform) {
        $this->platform = $platform;
    }

    public function concat($delim, $array) {
        if ($this->platform == 'mysql') {
            $string = "concat_ws('$delim',".implode(',', $array).')';
        } else if (in_array($this->platform, array('oracle', 'sqlite'))) {
            $string = implode(" || '$delim' || ", $array);
        }
        return $string;
    }

}
