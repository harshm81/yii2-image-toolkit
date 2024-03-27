<?php

namespace ImageToolkit\Utils;

use ImageToolkit\Constants\SupportedTransforms;

/**
 *
 */
class Transformation
{
    const TRANSFORMATION_PARAMETER = 'tr';
    const TRANSFORM_DELIMITER = ',';
    const TRANSFORM_KEY_VALUE_DELIMITER = '-';

    /**
     * @param $transformation
     * @return mixed
     */
    public static function getTransformKey($transformation)
    {
        $supportedTransforms = SupportedTransforms::get();
        // Reverse the transforms array
        $reversedTransforms = array_flip($supportedTransforms);

        if (isset($reversedTransforms[$transformation])) {
            return $reversedTransforms[$transformation];
        }

        return $transformation;
    }

    /**
     * @return string
     */
    public static function getTransformKeyValueDelimiter()
    {
        return self::TRANSFORM_KEY_VALUE_DELIMITER;
    }

    /**
     * @return string
     */
    public static function getTransformDelimiter()
    {
        return self::TRANSFORM_DELIMITER;
    }
}
