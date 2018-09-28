<?php

namespace URD\lib;

use PDO;

class Oci8 extends \Yajra\Pdo\Oci8
{
    /**
     * Retrieve a database connection attribute
     *
     * @param int $attribute
     * @return mixed A successful call returns the value of the requested PDO
     *   attribute. An unsuccessful call returns null.
     */
    public function getAttribute($attribute)
    {
        if ($attribute == PDO::ATTR_DRIVER_NAME) {
            return "oci";
        }

        if (isset($this->options[$attribute])) {
            return $this->options[$attribute];
        }

        return null;
    }
}
