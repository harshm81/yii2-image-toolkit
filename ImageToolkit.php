<?php

namespace ImageToolkit;

use yii\base\Widget;
use ImageToolkit\Helpers\Helper;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;
use ImageToolkit\Constants\Constants;
use ImageToolkit\Utils\Transformation;
use yii\base\InvalidArgumentException;

/**
 * This is just an example.
 */
class ImageToolkit extends Widget
{
    /**
     * Custom properties
     */
    public $url;
    private $targetW = null;
    private $targetH = null;
    private $aspectRatio = null;
    private $cropMode = null;
    private $backgroundColor = 'F3F3F3';
    private $isIgnoreAspectRatio = false;
    private $focusForPadding = ''; // Set the desired padding side (either 'left', 'right', 'top', or 'bottom')
    private $cropStrategy = ''; // Set the desired value (either 'force', 'at_max', 'at_max_enlarge' or 'at_least')
    private $quality = 90; // Default quality
    private $validFormats = ['auto', 'jpg', 'png', 'webp', 'avif', 'jpeg'];
    private $format = 'auto';
    private $newTargetW = null;
    private $newTargetH = null;

    public function init()
    {
        parent::init();
        if ($this->url === null) {
            $msg = 'Missing URL during ImageToolkit initialization';
            throw new InvalidArgumentException($msg);
        }

        if (!$this->checkImageExists()) {
            throw new NotFoundHttpException('Image does not exist.');
        }

        $this->parseParameters();
    }

    public function run()
    {
        $this->processImageUrl();
    }

    /**
     * @return bool
     */
    private function checkImageExists()
    {
        // Remove query string
        $newUrl = Helper::removeTransformationStringFromUrl($this->url);

        // Get headers of the URL
        $headers = get_headers($newUrl);

        // Check if the headers contain "200 OK" response
        if ($headers && strpos($headers[0], '200') !== false) {
            $keys = array_filter(array_keys($headers), function ($key) use ($headers) { // Filter the headers array to find keys containing "Content-Type"
                return strpos($headers[$key], 'Content-Type') !== false;
            });
            $keys = array_values($keys); // Reset the keys
            if (!empty($keys) && !empty($keys[0])) {
                if (strpos($headers[$keys[0]], 'Content-Type:') !== false) {
                    $contentType = trim(str_replace('Content-Type:', '', $headers[$keys[0]]));
                    // Check if content type is an image
                    if (!empty($contentType) && preg_match('/^image\/(svg\+xml|svg|gif|jpe?g|png|webp)/i', $contentType)) {
                        return true; // Image exists
                    }
                }
            }
        }

        return false; // Image does not exist or is not accessible
    }

    /**
     * Parse params
     */
    private function parseParameters()
    {
        $trValue = null;
        $urlParams = explode('&', $this->url);
        if (!empty($urlParams)) {
            // Find the element with 'tr' value
            foreach ($urlParams as $param) {
                if (strpos($param, Transformation::TRANSFORMATION_PARAMETER . '=') !== false) {
                    // Extract the 'tr' value
                    parse_str($param, $trParams);
                    $trValue = isset($trParams['tr']) ? $trParams['tr'] : null;
                    break;
                }
            }
        }

        // Parse parameters
        if (!empty($trValue)) {
            $params = explode(Transformation::getTransformDelimiter(), $trValue);
            foreach ($params as $param) {
                $parts = explode(Transformation::getTransformKeyValueDelimiter(), $param);
                $key = Transformation::getTransformKey($parts[0]);

                if (!empty($key)) {
                    switch ($parts[0]) {
                        case 'w':
                            $this->targetW = intval($parts[1]);
                            break;
                        case 'h':
                            $this->targetH = intval($parts[1]);
                            break;
                        case 'ar':
                            array_shift($parts);
                            $this->aspectRatio = implode(Constants::$ASPECT_RATIO_KEY_VALUE_DELIMITER, $parts);
                            break;
                        case 'cm':
                            $this->cropMode = $parts[1];
                            break;
                        case 'bg':
                            $this->backgroundColor = $parts[1];
                            break;
                        case 'fo':
                            $this->focusForPadding = $parts[1];
                            break;
                        case 'c':
                            $this->cropStrategy = $parts[1];
                            break;
                        case 'q':
                            $this->quality = intval($parts[1]);
                            break;
                        case 'f':
                            if (in_array($parts[1], $this->validFormats)) {
                                $this->format = $parts[1];
                            }
                            break;
                    }
                }
            }
        }

        $this->setTargetDimensions();
    }

    /**
     * Set target dimensions
     */
    private function setTargetDimensions()
    {
        // Check if width or height is provided
        if ($this->targetW !== null && $this->targetH !== null) {
            $this->newTargetW = $this->targetW;
            $this->newTargetH = $this->targetH;
            $this->isIgnoreAspectRatio = true;
        } elseif ($this->targetW !== null) {
            // If only width is provided, set height same as width
            $this->newTargetW = $this->targetW;
            $this->newTargetH = $this->targetW;
        } elseif ($this->targetH !== null) {
            // If only height is provided, set width same as height
            $this->newTargetW = $this->targetH;
            $this->newTargetH = $this->targetH;
        }

        // Determine target width and height based on aspect ratio
        if ($this->aspectRatio !== null && !$this->isIgnoreAspectRatio) {
            list($aspectW, $aspectH) = $this->calculateDimensions();
            if ($aspectW !== null && $aspectH !== null) {
                $this->newTargetW = $aspectW;
                $this->newTargetH = $aspectH;
            }
        }
    }

    /**
     * @return array|null
     */
    private function calculateDimensions()
    {
        $ratioParts = explode('-', $this->aspectRatio);
        if (count($ratioParts) !== 2) {
            return null;
        }

        $ratio = floatval($ratioParts[0]) / floatval($ratioParts[1]);

        if ($this->targetW !== null) {
            return [intval($this->targetW), intval($this->targetW / $ratio)];
        } elseif ($this->targetH !== null) {
            return [intval($this->targetH * $ratio), intval($this->targetH)];
        }

        return null;
    }

    /**
     * Output svg image
     * @throws NotFoundHttpException
     */
    private function outputSvgImage()
    {
        try {
            // Remove transformation string
            $newUrl = Helper::removeTransformationStringFromUrl($this->url);

            $svgContent = @file_get_contents($newUrl);
            if ($svgContent === false) {
                throw new NotFoundHttpException('Failed to load SVG image.');
            }

            header('Content-Type: image/svg+xml');
            echo $svgContent;
            exit;
        } catch (\Exception $e) {
            throw new NotFoundHttpException('Failed to load SVG image.');
        }
    }

    /**
     * Output gif image
     * @throws NotFoundHttpException
     */
    private function outputGifImage()
    {
        try {
            // Remove transformation string
            $newUrl = Helper::removeTransformationStringFromUrl($this->url);

            header('Content-Type: image/gif');
            readfile($newUrl);
            exit;
        } catch (\Exception $e) {
            throw new NotFoundHttpException('Failed to load GIF image.');
        }
    }

    /**
     * @return bool
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     */
    private function processImageUrl()
    {
        // Remove query string
        $newUrl = Helper::removeQueryStringFromUrl($this->url);
        $imageExtension = pathinfo(basename($newUrl), PATHINFO_EXTENSION);

        switch (true) {
            case(!empty($imageExtension) && strtolower($imageExtension) === 'svg'): // Check if the image is an SVG by checking the file extension
                // Output SVG directly
                $this->outputSvgImage();
                break;
            case(!empty($imageExtension) && strtolower($imageExtension) === 'gif'): // Check if the image is a GIF by checking the file extension
                // Output the GIF image directly
                $this->outputGifImage();
                break;
            default:
                // Remove query string
                $newUrl = Helper::removeTransformationStringFromUrl($this->url);
                // Determine the correct function based on the image type
                $imageType = exif_imagetype($newUrl);
                if (empty($imageType)) {
                    throw new NotFoundHttpException('Image does not exist.');
                }

                switch ($imageType) {
                    case IMAGETYPE_JPEG:
                        // Get image.
                        $in = imagecreatefromjpeg($newUrl);
                        break;
                    case IMAGETYPE_PNG:
                        // Get image.
                        $in = imagecreatefrompng($newUrl);
                        break;
                }

                // Get image dimensions.
                $w = imagesx($in);
                $h = imagesy($in);

                if ($this->newTargetW === null && $this->newTargetH === null) {
                    // Output original image if dimensions are not provided
                    header('Content-Type: image/jpeg');
                    imagejpeg($in);

                    // Free up memory.
                    imagedestroy($in);
                    exit;
                }

                if (!($w >= $this->newTargetW && $h >= $this->newTargetH)) {
                    throw new BadRequestHttpException('Image too small.');

                    // Free up memory.
                    imagedestroy($in);
                    exit;
                }

                switch (true) {
                    case(!empty($this->cropStrategy) && $this->cropStrategy == 'force' && $this->targetW !== null && $this->targetH !== null):
                        $out = $this->processImageByForceCropStrategy($in, $w, $h, $this->targetW, $this->targetH);
                        break;
                    case(!empty($this->cropStrategy) && $this->cropStrategy == 'at_max' && $this->targetW !== null && $this->targetH !== null):
                        $out = $this->processImageByAtMaxCropStrategy($in, $w, $h);
                        break;
                    case(!empty($this->cropStrategy) && $this->cropStrategy == 'at_max_enlarge' && $this->targetW !== null && $this->targetH !== null):
                        $out = $this->processImageByAtMaxEnlargeCropStrategy($in, $w, $h, $this->targetW, $this->targetH);
                        break;
                    case(!empty($this->cropStrategy) && $this->cropStrategy == 'at_least' && $this->targetW !== null && $this->targetH !== null):
                        $out = $this->processImageByAtLeastCropStrategy($in, $w, $h, $this->targetW, $this->targetH);
                        break;
                    case(!empty($this->cropMode) && $this->newTargetW !== null && $this->newTargetH !== null):
                        $out = $this->processImageByCropMode($in, $w, $h, $this->newTargetW, $this->newTargetH);
                        break;
                    default:
                        // Get scales.
                        $xScale = $w / $this->newTargetW;
                        $yScale = $h / $this->newTargetH;

                        // Create a new canvas with desired dimensions
                        $destinationImage = $this->createCanvas($this->newTargetW, $this->newTargetH);
                        if (!$destinationImage) {
                            return false;
                        }

                        $newW = $this->newTargetW;
                        $newH = $this->newTargetH;
                        $srcX = 0;
                        $srcY = 0;

                        // Compare scales to ensure we crop whichever is smaller: top/bottom or left/right.
                        if ($xScale > $yScale) {
                            $newW = $w / $yScale;

                            // See description of $srcY, below.
                            $srcX = (($newW - $this->newTargetW) / 2) * $yScale;
                        } else {
                            $newH = $h / $xScale;

                            // A bit tricky. Crop is done by specifying coordinates to copy from in
                            // source image. So calculate how much to remove from new image and
                            // then scale that up to original. Result is out by ~1px but good enough.
                            $srcY = (($newH - $this->newTargetH) / 2) * $xScale;
                        }

                        // Resize the original image to fit within the output dimensions
                        $this->resizeAndCopyImage($in, $destinationImage, $w, $h, $newW, $newH, $srcX, $srcY);

                        $out = $destinationImage;
                }

                // Output the resulting image
                if (!$out) {
                    throw new NotFoundHttpException('Image does not exist.');
                }

                $fileExtension = $this->determineFileExtension();

                header('Content-Type: image/' . $fileExtension);

                $this->outputImage($out);

                // Free up memory.
                imagedestroy($out);
                imagedestroy($in);
                exit;
        }
    }

    /**
     * Create a canvas with specified dimensions.
     *
     * @param int $width The width of the canvas.
     * @param int $height The height of the canvas.
     * @return resource|bool Returns the created image resource or false on failure.
     */
    private function createCanvas($width, $height)
    {
        return imagecreatetruecolor($width, $height);
    }

    /**
     * Resize and copy the source image onto the canvas.
     *
     * @param resource $sourceImage The source image resource.
     * @param resource $destinationImage The destination canvas resource.
     * @param int $sourceWidth The width of the source image.
     * @param int $sourceHeight The height of the source image.
     * @param int $destinationWidth The width of the destination canvas.
     * @param int $destinationHeight The height of the destination canvas.
     * @param int $sourceX The starting X coordinate in the source image.
     * @param int $sourceY The starting Y coordinate in the source image.
     * @param int $destinationX The starting X coordinate in the destination image.
     * @param int $destinationY The starting Y coordinate in the destination image.
     */
    private function resizeAndCopyImage($sourceImage, $destinationImage, $sourceWidth, $sourceHeight, $destinationWidth, $destinationHeight, $sourceX, $sourceY, $destinationX = 0, $destinationY = 0)
    {
        imagecopyresampled($destinationImage, $sourceImage, $destinationX, $destinationY, $sourceX, $sourceY, $destinationWidth, $destinationHeight, $sourceWidth, $sourceHeight);
    }

    /**
     * Fill the canvas with the specified background color.
     *
     * @param resource $canvas The canvas resource to fill.
     * @param string $hexColor The hexadecimal color code (e.g., "#RRGGBB") of the background color.
     * @return bool True on success, false on failure.
     */
    private function fillBackgroundColor($canvas, $hexColor)
    {
        // Convert hexadecimal color code to RGB components
        $red = hexdec(substr($hexColor, 1, 2));
        $green = hexdec(substr($hexColor, 3, 2));
        $blue = hexdec(substr($hexColor, 5, 2));

        // Allocate the background color on the canvas
        $bgColor = imagecolorallocate($canvas, $red, $green, $blue);
        if ($bgColor === false) {
            return false; // Return false on failure
        }

        // Fill the canvas with the background color
        imagefill($canvas, 0, 0, $bgColor);
    }

    /**
     * @param $sourceImage
     * @param $sourceWidth
     * @param $sourceHeight
     * @param $targetWidth
     * @param $targetHeight
     * @return bool|resource
     */
    private function processImageByForceCropStrategy($sourceImage, $sourceWidth, $sourceHeight, $targetWidth, $targetHeight)
    {
        // Create a new canvas with desired dimensions
        $destinationImage = $this->createCanvas($targetWidth, $targetHeight);
        if (!$destinationImage) {
            return false;
        }

        // Resize the original image to fit within the output dimensions
        $this->resizeAndCopyImage($sourceImage, $destinationImage, $sourceWidth, $sourceHeight, $targetWidth, $targetHeight, 0, 0);

        return $destinationImage;
    }

    /**
     * @param $sourceImage
     * @param $sourceWidth
     * @param $sourceHeight
     * @return bool|resource
     */
    private function processImageByAtMaxCropStrategy($sourceImage, $sourceWidth, $sourceHeight)
    {
        // Calculate aspect ratio
        $aspectRatioForCrop = $sourceWidth / $sourceHeight;

        // Calculate new dimensions while preserving aspect ratio
        if ($this->targetW !== null && $this->targetH !== null) {
            // Both width and height are specified, adjust the dimension that exceeds the target
            if ($sourceWidth / $this->targetW > $sourceHeight / $this->targetH) {
                $newW = $this->targetW;
                $newH = $this->targetW / $aspectRatioForCrop;
            } else {
                $newW = $this->targetH * $aspectRatioForCrop;
                $newH = $this->targetH;
            }
        } elseif ($this->targetW !== null) {
            // Only width is specified, adjust height
            $newW = $this->targetW;
            $newH = $this->targetW / $aspectRatioForCrop;
        } elseif ($this->targetH !== null) {
            // Only height is specified, adjust width
            $newH = $this->targetH;
            $newW = $this->targetH * $aspectRatioForCrop;
        } else {
            // No target dimensions specified, use original dimensions
            $newW = $sourceWidth;
            $newH = $sourceHeight;
        }

        // Create a new canvas with desired dimensions
        $destinationImage = $this->createCanvas($newW, $newH);
        if (!$destinationImage) {
            return false;
        }

        // Resize the original image to fit within the output dimensions
        $this->resizeAndCopyImage($sourceImage, $destinationImage, $sourceWidth, $sourceHeight, $newW, $newH, 0, 0);

        return $destinationImage;
    }

    /**
     * @param $sourceImage
     * @param $sourceWidth
     * @param $sourceHeight
     * @param $targetWidth
     * @param $targetHeight
     * @return bool|resource
     */
    private function processImageByAtMaxEnlargeCropStrategy($sourceImage, $sourceWidth, $sourceHeight, $targetWidth, $targetHeight)
    {
        // Calculate aspect ratio
        $aspectRatioForCrop = $sourceWidth / $sourceHeight;

        // Calculate new dimensions while preserving aspect ratio
        if ($targetWidth !== null && $targetHeight !== null) {
            // Both width and height are specified, adjust the dimension that exceeds the target
            if ($sourceWidth / $targetWidth > $sourceHeight / $targetHeight) {
                $newW = min($sourceWidth, $targetWidth);
                $newH = $newW / $aspectRatioForCrop;
            } else {
                $newH = min($sourceHeight, $targetHeight);
                $newW = $newH * $aspectRatioForCrop;
            }
        } elseif ($targetWidth !== null) {
            // Only width is specified, adjust height
            $newW = min($sourceWidth, $targetWidth);
            $newH = $newW / $aspectRatioForCrop;
        } elseif ($targetHeight !== null) {
            // Only height is specified, adjust width
            $newH = min($sourceHeight, $targetHeight);
            $newW = $newH * $aspectRatioForCrop;
        } else {
            // No target dimensions specified, use original dimensions
            $newW = $sourceWidth;
            $newH = $sourceHeight;
        }

        // Create a new canvas with desired dimensions
        $destinationImage = $this->createCanvas($newW, $newH);
        if (!$destinationImage) {
            return false;
        }

        // Resize the original image to fit within the output dimensions
        $this->resizeAndCopyImage($sourceImage, $destinationImage, $sourceWidth, $sourceHeight, $newW, $newH, 0, 0);

        return $destinationImage;
    }

    /**
     * @param $sourceImage
     * @param $sourceWidth
     * @param $sourceHeight
     * @param $targetWidth
     * @param $targetHeight
     * @return bool|resource
     */
    private function processImageByAtLeastCropStrategy($sourceImage, $sourceWidth, $sourceHeight, $targetWidth, $targetHeight)
    {
        // Calculate aspect ratio
        $aspectRatioForCrop = $sourceWidth / $sourceHeight;

        // Calculate new dimensions while preserving aspect ratio
        if ($targetWidth !== null && $targetHeight !== null) {
            // Both width and height are specified
            $newW = $targetWidth;
            $newH = $newW / $aspectRatioForCrop;

            // If calculated height is less than target height, adjust dimensions
            if ($newH < $targetHeight) {
                $newH = $targetHeight;
                $newW = $newH * $aspectRatioForCrop;
            }
        } elseif ($targetWidth !== null) {
            // Only width is specified
            $newW = $targetWidth;
            $newH = $newW / $aspectRatioForCrop;
        } elseif ($targetHeight !== null) {
            // Only height is specified
            $newH = $targetHeight;
            $newW = $newH * $aspectRatioForCrop;
        } else {
            // No target dimensions specified, use original dimensions
            $newW = $sourceWidth;
            $newH = $sourceHeight;
        }

        // Create a new canvas with desired dimensions
        $destinationImage = $this->createCanvas($newW, $newH);
        if (!$destinationImage) {
            return false;
        }

        // Resize the original image to fit within the output dimensions
        $this->resizeAndCopyImage($sourceImage, $destinationImage, $sourceWidth, $sourceHeight, $newW, $newH, 0, 0);

        return $destinationImage;
    }

    /**
     * @param $sourceImage
     * @param $sourceWidth
     * @param $sourceHeight
     * @param $targetWidth
     * @param $targetHeight
     * @return bool|resource
     */
    private function processImageByCropMode($sourceImage, $sourceWidth, $sourceHeight, $targetWidth, $targetHeight)
    {
        // Calculate aspect ratio
        $aspectRatioForPadding = $sourceWidth / $sourceHeight;

        // Calculate scales
        $xScale = $targetWidth / $sourceWidth;
        $yScale = $targetHeight / $sourceHeight;

        // Determine which scale to use for resizing while maintaining aspect ratio
        if ($xScale < $yScale) {
            // Resize using xScale
            $newW = $targetWidth;
            $newH = $targetWidth / $aspectRatioForPadding;
        } else {
            // Resize using yScale
            $newW = $targetHeight * $aspectRatioForPadding;
            $newH = $targetHeight;
        }

        // Create a new canvas with desired dimensions
        $destinationImage = $this->createCanvas($targetWidth, $targetHeight);
        if (!$destinationImage) {
            return false;
        }

        // Fill the background color to the entire canvas
        $this->fillBackgroundColor($destinationImage, '#' . $this->backgroundColor);

        // Calculate padding based on the specified side
        if ($this->focusForPadding === 'left') {
            $paddingX = 0;  // No padding on the left side
            $paddingY = ($targetHeight - $newH) / 2; // Center vertically
        } elseif ($this->focusForPadding === 'right') {
            $paddingX = $targetWidth - $newW; // Padding on the right side
            $paddingY = ($targetHeight - $newH) / 2; // Center vertically
        } elseif ($this->focusForPadding === 'top') {
            $paddingX = ($targetWidth - $newW) / 2; // Center horizontally
            $paddingY = 0;  // No padding on the top side
        } elseif ($this->focusForPadding === 'bottom') {
            $paddingX = ($targetWidth - $newW) / 2; // Center horizontally
            $paddingY = $targetHeight - $newH; // Padding on the bottom side
        } else {
            // Default to padding on the left/right side if the specified side is invalid
            $paddingX = ($targetWidth - $newW) / 2;
            $paddingY = ($targetHeight - $newH) / 2;
        }

        // Resize the original image to fit within the output dimensions
        $this->resizeAndCopyImage($sourceImage, $destinationImage, $sourceWidth, $sourceHeight, $newW, $newH, 0, 0, $paddingX, $paddingY);

        return $destinationImage;
    }

    /**
     * Determine the file extension based on the selected format or the current image format.
     * @return string The file extension determined based on the selected format or the current image format.
     */
    private function determineFileExtension()
    {
        $fileExtension = 'jpg'; // Default to JPEG if image type is unknown
        if (!empty($this->format)) {
            switch ($this->format) {
                case 'jpg':
                case 'jpeg':
                    $fileExtension = 'jpg';
                    break;
                case 'png':
                    $fileExtension = 'png';
                    break;
                case 'webp':
                    $fileExtension = 'webp';
                    break;
            }
        } else {
            // Remove query string
            $newUrl = Helper::removeTransformationStringFromUrl($this->url);
            // Determine file extension based on the current image format
            $imageType = exif_imagetype($newUrl);
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    $fileExtension = 'jpg';
                    break;
                case IMAGETYPE_PNG:
                    $fileExtension = 'png';
                    break;
                case IMAGETYPE_WEBP:
                    $fileExtension = 'webp';
                    break;
            }
        }

        return $fileExtension;
    }

    /**
     * Output image to browser based on the specified format or auto-select based on browser support and image content.
     * @param resource $out The image resource.
     */
    private function outputImage($out)
    {
        // Remove query string
        $newUrl = Helper::removeTransformationStringFromUrl($this->url);

        switch ($this->format) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($out, null, $this->quality);
                break;
            case 'png':
                if (!empty($this->quality)) {
                    $this->quality = (9 - round($this->quality / 9 * 9));
                }
                imagepng($out, null, $this->quality);
                break;
            case 'webp':
                imagewebp($out, null, $this->quality);
                break;
            default:
                // Auto format selection
                // Determine the best format based on browser support and image content
                if (function_exists('imageavif') && function_exists('imagewebp') && in_array('avif', $this->validFormats) && in_array('webp', $this->validFormats)) {
                    // Browser supports AVIF and WebP
                    // Choose AVIF for images with transparency, otherwise choose WebP
                    $imageType = exif_imagetype($newUrl);
                    if ($imageType === IMAGETYPE_PNG && imagecolortransparent($out) >= 0) {
                        if (function_exists('imageavif')) {
                            imageavif($out, null, $this->quality);
                        } else {
                            imagewebp($out, null, $this->quality);
                        }
                    } else {
                        imagewebp($out, null, $this->quality);
                    }
                } elseif (function_exists('imagewebp') && in_array('webp', $this->validFormats)) {
                    // Browser supports WebP, but not AVIF
                    imagewebp($out, null, $this->quality);
                } elseif (function_exists('imageavif') && in_array('avif', $this->validFormats)) {
                    // Browser supports AVIF, but not WebP
                    imageavif($out, null, $this->quality);
                } else {
                    // Browser supports neither AVIF nor WebP
                    // Choose JPEG or PNG based on image content
                    $imageType = exif_imagetype($newUrl);
                    if ($imageType === IMAGETYPE_PNG && imagecolortransparent($out) >= 0) {
                        imagepng($out, null, $this->quality);
                    } else {
                        imagejpeg($out, null, $this->quality);
                    }
                }
        }
    }
}
