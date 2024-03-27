<?php

namespace ImageToolkit\Helpers;

use ImageToolkit\Constants\Constants;

/**
 *
 */
class Helper
{
    /**
     * @param $url
     * @return string
     */
    public static function removeQueryStringFromUrl($url)
    {
        $newUrl = '';
        if (!empty($url)) {
            $urlParts = parse_url($url);
            $newUrl = $urlParts['scheme'] . '://' . $urlParts['host'] . $urlParts['path'];
        }

        return $newUrl;
    }

    /**
     * @param $url
     * @return string
     */
    public static function removeTransformationStringFromUrl($url)
    {
        $newUrl = '';
        if (!empty($url)) {
            $urlParts = explode(Constants::$REMOVE_TRANSFORMATION_STRING_DELIMITER, $url);
            $newUrl = !empty($urlParts) && !empty($urlParts[0]) ? $urlParts[0] : '';
        }

        return $newUrl;
    }
}
