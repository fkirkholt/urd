<?php

namespace URD\Template;

interface Renderer
{
    public function render($template, $data = []);
}
