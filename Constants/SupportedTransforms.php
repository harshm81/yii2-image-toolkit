<?php

namespace ImageToolkit\Constants;

/**
 *
 */
class SupportedTransforms
{
    private static $transforms = [
        'height' => 'h',
        'width' => 'w',
        'aspectRatio' => 'ar',
        'quality' => 'q',
        'crop' => 'c',
        'cropMode' => 'cm',
        'focus' => 'fo',
        'format' => 'f',
        'background' => 'bg',
    ];

    /**
     * @return array<string, string>
     */
    public static function get()
    {
        return self::$transforms;
    }
}
