<?php

namespace URD\models;

class Expression {

    function __construct($platform, $expr) {
        $this->platform = $platform;
        $this->expr = $expr;
    }

    /**
     * Concatenates expressions and treats nulls as ''
     */
    public function concat($expressions)
    {
        switch ($this->platform) {
        case 'mysql':
            return "concat_ws('', " . implode(',', $expressions) . ')';
        case 'oracle':
        case 'sqlite':
            return implode(' || ', $expressions);
        }
    }

    /**
     * Concatenates expressions with separator and treats nulls as ''
     */
    public function concat_ws($sep, $expressions)
    {
        switch ($this->platform) {
        case 'mysql':
            return "concat_ws('$sep'," . implode(',', $expressions) . ")";
        case 'oracle':
        case 'sqlite':
        case 'pgsql':
            return implode(" || '$sep' || ", $expressions);
        }
    }

    public function autoincrement() {
        switch ($this->platform) {
        case 'mysql':
            return "AUTO_INCREMENT";
        case 'oracle':
            return "GENERATED BY DEFAULT ON NULL AS IDENTITY";
        default:
            throw new \Exception("Auto increment not implemented for this database");
        }

    }

    public function to_native_type($size)
    {
        if ($this->platform == 'mysql') {
            switch ($this->expr) {
            case 'string':
                return ($size) ? "varchar($size)" : "longtext";
            case 'integer':
                return "int($size)";
            case 'decimal':
                return "decimal($size)";
            case 'float':
                return "float($size)";
            case 'date':
                return 'date';
            case 'boolean':
                return 'tinyint(1)';
            case 'binary':
                return 'blob';
            default:
                throw new \Exception("type $this->expr not recognized");
            }
        } else if ($this->platform == 'sqlite') {
            switch ($this->expr) {
            case "string":
            case "date":
                return "text";
            case "integer":
            case "boolean":
                return "integer";
            case "decimal":
                return "decimal";
            case "float":
                return "real";
            case "binary":
                return "blob";
            }
        } else {
            throw new \Exception("type conversion for $this->platform not implemented");
        }
    }

    public function to_urd_type($nativetype)
    {
        $nativetype = strtolower($nativetype);
        if ($this->platform == 'mysql') {
            if (preg_match("/char|text/", $nativetype)) {
                return "string";
            } else if (preg_match("/int/", $nativetype)) {
                return "integer";
            } else if (preg_match("/double|decimal/", $nativetype)) {
                return "decimal";
            } else if (preg_match("/float|double|decimal/", $nativetype)) {
                return "float";
            } else if (preg_match("/date|time/", $nativetype)) {
                return "date";
            } else if (preg_match("/blob/", $nativetype)) {
                return "binary";
            } else {
                throw new \Exception("type $nativetype not recognized");
            }
        } else if ($this->platform == 'oracle') {
            switch (strtolower($nativetype)) {
            case 'char':
            case 'varchar2':
                return 'string';
            case 'number':
                return 'integer';
            case 'date':
            case 'timestamp':
                return 'date';
            default:
                throw new \Exception("type $this->expr not recognized");
            }
        } else {
            switch (strtolower($nativetype)) {
            case 'varchar':
            case 'text':
                return 'string';
            case 'integer':
            case 'int4':
                return 'integer';
            case 'numeric':
            case 'decimal':
                return 'decimal';
            case 'float8':
                return 'float';
            case 'blob':
                return 'binary';
            case 'date':
            case 'timestamp':
                return 'date';
            default:
                throw new \Exception("type $nativetype not recognized");
            }
        }
    }

    public function replace_vars($sql) {
        $sql = str_replace('$user_id', $_SESSION['user_id'], $sql);
        $sql = str_replace('$user_name', $_SESSION['user_name'], $sql);
        $sql = str_replace('$date', date('Y-m-d'), $sql);
        $sql = str_replace('current_date', date('Y-m-d'), $sql);
        $sql = str_replace('curdate()', date('Y-m-d'), $sql);
        $sql = str_replace('$timestamp', date('Y-m-d H:i:s'), $sql);
        $sql = str_replace('current_timestamp', date('Y-m-d H:i:s'), $sql);
        return $sql;
    }

    public function __toString() {
        return $this->expr;
    }

}
