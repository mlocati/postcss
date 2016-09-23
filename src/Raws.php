<?php

namespace PostCSS;

class Raws
{
    public function __construct(array $defaults = [])
    {
        foreach ($defaults as $name => $value) {
            $this->$name = $value;
        }
    }

    public function __clone()
    {
        unset($this->before);
        unset($this->after);
        unset($this->between);
        unset($this->semicolon);
    }

    public function __get($name)
    {
        return null;
    }
}
