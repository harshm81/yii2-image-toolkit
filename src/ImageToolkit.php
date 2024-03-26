<?php

<<<<<<< HEAD
namespace app\widgets\ImageToolkit;

use yii\base\Widget;
use yii\base\InvalidArgumentException;
=======
namespace ImageToolkit;
>>>>>>> 13c2969 (Directory Structure change)

/**
 * This is just an example.
 */
<<<<<<< HEAD
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
=======
class ImageToolkit extends \yii\base\Widget
{
    public function run()
    {
        return "Hello!";
>>>>>>> 13c2969 (Directory Structure change)
    }
}
