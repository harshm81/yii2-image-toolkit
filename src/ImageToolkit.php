<?php

namespace ImageToolkit;

use yii\base\InvalidArgumentException;
use yii\base\Widget;

/**
 * This is just an example.
 */
class ImageToolkit extends Widget
{
    public $url; // Custom property

    public function init()
    {
        parent::init();
        if ($this->url === null) {
            $msg = 'Missing URL during ImageToolkit initialization';
            throw new InvalidArgumentException($msg);
        }
    }

    public function run()
    {
        p($this->url);
    }
}