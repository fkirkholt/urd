<?php

namespace URD\models;

use BrightNucleus\MimeTypes\MimeTypes;

class File {

    function __construct($path) {
        $this->path = $path;
    }

    public function get() {
        $ext = pathinfo($this->path, PATHINFO_EXTENSION);
        $filename = pathinfo($this->path, PATHINFO_FILENAME);

        $mime = MimeTypes::getTypesForExtension($ext)[0];

        header("Cache-Control: ");
        header("Content-type: $mime");
        header('Content-Disposition: inline; filename="' . $filename . '.' . $ext . '"');

        flush(); // Flush system output buffer

        readfile($this->path);    
    }
}

