<?php

/*
 * (c) Studio107 <mail@studio107.ru> http://studio107.ru
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * Author: Maxim Falaleev <max@studio107.ru>
 */

namespace Mindy\Thumb;

use Exception;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\ManipulatorInterface;
use Imagine\Image\Metadata\DefaultMetadataReader;
use Imagine\Image\Point;

/**
 * Class ImageProcess.
 */
trait ImageProcess
{
    /**
     * GD2 driver definition for Imagine implementation using the GD library.
     */
    public static $DRIVER_GD2 = 'gd2';
    /**
     * imagick driver definition.
     */
    public static $DRIVER_IMAGICK = 'imagick';
    /**
     * gmagick driver definition.
     */
    public static $DRIVER_GMAGICK = 'gmagick';

    /**
     * @var ImagineInterface instance
     */
    private static $_imagine;

    /**
     * Returns the `Imagine` object that supports various image manipulations.
     *
     * @return ImagineInterface the `Imagine` object
     */
    public static function getImagine()
    {
        if (self::$_imagine === null) {
            self::$_imagine = static::createImagine();
        }

        return self::$_imagine;
    }

    /**
     * Creates an `Imagine` object based on the specified [[driver]].
     *
     * @throws Exception if [[driver]] is unknown or the system doesn't support any [[driver]]
     *
     * @return ImagineInterface the new `Imagine` object
     */
    protected static function createImagine()
    {
        $drivers = [
            self::$DRIVER_GMAGICK,
            self::$DRIVER_IMAGICK,
            self::$DRIVER_GD2,
        ];

        foreach ((array) $drivers as $driver) {
            switch ($driver) {
                case self::$DRIVER_GMAGICK:
                    if (class_exists('Gmagick', false)) {
                        $imagine = new \Imagine\Gmagick\Imagine();
                        $imagine->setMetadataReader(new DefaultMetadataReader());

                        return $imagine;
                    }
                    break;
                case self::$DRIVER_IMAGICK:
                    if (class_exists('Imagick', false)) {
                        $imagine = new \Imagine\Imagick\Imagine();
                        $imagine->setMetadataReader(new DefaultMetadataReader());

                        return $imagine;
                    }
                    break;
                case self::$DRIVER_GD2:
                    if (function_exists('gd_info')) {
                        $imagine = new \Imagine\Gd\Imagine();
                        $imagine->setMetadataReader(new DefaultMetadataReader());

                        return $imagine;
                    }
                    break;
                default:
                    throw new Exception("Unknown driver: $driver");
            }
        }
        throw new Exception('Your system does not support any of these drivers: '.implode(',', $drivers));
    }

    /**
     * @param ImageInterface $img
     * @param int $width
     * @param int $height
     * @param string $method
     *
     * @return ImageInterface|static
     */
    public function resize(ImageInterface $img, $width, $height, $method)
    {
        $box = new Box($width, $height);

        $imgBox = $img->getSize();

        if (($imgBox->getWidth() <= $box->getWidth() && $imgBox->getHeight() <= $box->getHeight()) || (!$box->getWidth() && !$box->getHeight())) {
            return $img;
        }

        if ($method == 'resize') {
            $img = $img->thumbnail($box, ManipulatorInterface::THUMBNAIL_INSET);
        } elseif ($method == 'adaptiveResize') {
            $img = $img->thumbnail($box, ManipulatorInterface::THUMBNAIL_OUTBOUND);
        } elseif ($method == 'adaptiveResizeFromTop') {
            $fromWidth = $imgBox->getWidth();
            $fromHeight = $imgBox->getHeight();

            $toWidth = $box->getWidth();
            $toHeight = $box->getHeight();

            $fromPercent = $fromWidth / $fromHeight;
            $toPercent = $toWidth / $toHeight;

            if ($toPercent >= $fromPercent) {
                $resizeWidth = $toWidth;
                $resizeHeight = round($toWidth / $fromWidth * $fromHeight);
                $img = $img
                    ->resize(new Box($resizeWidth, $resizeHeight))
                    ->crop(new Point(0, 0), new Box($toWidth, $toHeight));
            } else {
                $img = $img->thumbnail($box, ManipulatorInterface::THUMBNAIL_OUTBOUND);
            }
        }

        return $img;
    }

    /**
     * @param ImageInterface $source
     * @param ImageInterface $watermark
     * @param string         $position
     *
     * @return ImageInterface
     */
    public function applyWatermark(ImageInterface $source, ImageInterface $watermark, $position = 'center')
    {
        $x = 0;
        $y = 0;

        $wSize = $watermark->getSize();
        $sSize = $source->getSize();

        $wWidth = $wSize->getWidth();
        $wHeight = $wSize->getHeight();

        $sWidth = $sSize->getWidth();
        $sHeight = $sSize->getHeight();

        $repeat = false;

        // If watermark bigger of original image, resize watermark.
        // https://github.com/MindyPHP/Mindy_Orm/pull/140
        if ($sWidth < $wWidth || $sHeight < $wHeight) {
            $watermark = $this->resize($watermark, $sWidth * 0.9, $sHeight * 0.9, 'resize');
            $wSize = $watermark->getSize();
            $wWidth = $wSize->getWidth();
            $wHeight = $wSize->getHeight();
        }

        if (is_array($position)) {
            list($x, $y) = $position;
        } else {
            switch ($position) {
                case 'top':
                    $x = $sWidth / 2 - $wWidth / 2;
                    $y = 0;
                    break;
                case 'bottom':
                    $x = $sWidth / 2 - $wWidth / 2;
                    $y = $sHeight - $wHeight;
                    break;
                case 'center':
                    $x = $sWidth / 2 - $wWidth / 2;
                    $y = $sHeight / 2 - $wHeight / 2;
                    break;
                case 'left':
                    $x = 0;
                    $y = $sHeight / 2 - $wHeight / 2;
                    break;
                case 'right':
                    $x = $sWidth - $wWidth;
                    $y = $sHeight / 2 - $wHeight / 2;
                    break;
                case 'top-left':
                    $x = 0;
                    $y = 0;
                    break;
                case 'top-right':
                    $x = $sWidth - $wWidth;
                    $y = 0;
                    break;
                case 'bottom-left':
                    $x = 0;
                    $y = $sHeight - $wHeight;
                    break;
                case 'bottom-right':
                    $x = $sWidth - $wWidth;
                    $y = $sHeight - $wHeight;
                    break;
                case 'repeat':
                    $repeat = true;
                    break;
            }
            if ($x < 0) {
                $x = 0;
            }
            if ($y < 0) {
                $y = 0;
            }
        }

        if ($repeat) {
            while ($y < $sHeight) {
                $appendY = $wHeight;
                if ($y + $appendY > $sHeight) {
                    $appendY = $sHeight - $y;
                }
                $x = 0;
                while ($x < $sWidth) {
                    $appendX = $wWidth;
                    if ($x + $appendX > $sWidth) {
                        $appendX = $sWidth - $x;
                    }

                    if ($appendY != $wHeight || $appendX != $wWidth) {
                        $source->paste($watermark->copy()->crop(new Point(0, 0), new Box($appendX, $appendY)),
                            new Point($x, $y));
                    } else {
                        $source->paste($watermark, new Point($x, $y));
                    }

                    $x += $appendX;
                }
                $y += $appendY;
            }
        } else {
            if (($x + $wWidth <= $sWidth) && ($y + $wHeight <= $sHeight)) {
                return $source->paste($watermark, new Point($x, $y));
            }
        }

        return $source;
    }

    /**
     * @param $source
     * @param null $width
     * @param null $height
     *
     * @return array
     */
    protected function imageScale(ImageInterface $source, $width = null, $height = null)
    {
        $size = $source->getSize();
        $ratio = $size->getWidth() / $size->getHeight();
        if ($width && !$height) {
            $height = $width / $ratio;
        } elseif (!$width && $height) {
            $width = $height * $ratio;
        }

        return [
            (int) $width,
            (int) $height,
        ];
    }
}
