<?php

namespace App\Services\JobCards;

use RuntimeException;

/**
 * Shrinks a phone photo to at most $max px on the long side, turns it upright (EXIF orientation),
 * and re-encodes it as JPEG. Re-encoding also drops EXIF, so no GPS location is kept.
 * Uses Imagick when it is loaded (reads HEIC too), else GD.
 */
class PhotoResizer
{
    /** @return array{0: string, 1: int, 2: int} JPEG bytes, width, height */
    public function toJpeg(string $sourcePath, int $max = 1600, int $quality = 82): array
    {
        if (extension_loaded('imagick')) {
            try {
                return $this->withImagick($sourcePath, $max, $quality);
            } catch (\Throwable) {
                // Fall through to GD (e.g. Imagick built without a delegate for this format).
            }
        }
        if (extension_loaded('gd')) {
            return $this->withGd($sourcePath, $max, $quality);
        }

        throw new RuntimeException('No image library (Imagick or GD) is available.');
    }

    private function withImagick(string $path, int $max, int $quality): array
    {
        // Read only photo formats, never PDF/SVG/PS etc. by sniffing (audit L18), and cap memory.
        $magic = (string) @file_get_contents($path, false, null, 0, 16);
        if (! self::looksLikePhoto($magic)) {
            throw new RuntimeException('This file is not a photo we can read.');
        }
        $img = new \Imagick;
        $img->setResourceLimit(\Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024);
        $img->setResourceLimit(\Imagick::RESOURCETYPE_AREA, 128 * 1024 * 1024);
        $img->readImage($path);
        if ($img->getNumberImages() > 1) {
            $img = $img->coalesceImages();
            $img->setIteratorIndex(0);
            $img = $img->getImage();
        }
        $this->orientImagick($img);
        $img->setImageBackgroundColor('white');
        $img = $img->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

        $w = $img->getImageWidth();
        $h = $img->getImageHeight();
        if (max($w, $h) > $max) {
            $img->resizeImage($w >= $h ? $max : 0, $h > $w ? $max : 0, \Imagick::FILTER_LANCZOS, 1);
        }
        $img->setImageFormat('jpeg');
        $img->setImageCompressionQuality($quality);
        $img->stripImage();
        $blob = $img->getImageBlob();
        $out = [$blob, $img->getImageWidth(), $img->getImageHeight()];
        $img->clear();

        return $out;
    }

    /** JPEG, PNG, WebP, GIF or HEIC/HEIF, by the first bytes of the file. */
    public static function looksLikePhoto(string $head): bool
    {
        return str_starts_with($head, "\xFF\xD8\xFF")
            || str_starts_with($head, "\x89PNG")
            || str_starts_with($head, 'GIF8')
            || (str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP')
            || (substr($head, 4, 4) === 'ftyp' && in_array(substr($head, 8, 4), ['heic', 'heix', 'hevc', 'heim', 'heis', 'mif1', 'msf1', 'avif'], true));
    }

    private function orientImagick(\Imagick $img): void
    {
        switch ($img->getImageOrientation()) {
            case \Imagick::ORIENTATION_BOTTOMRIGHT: $img->rotateImage('#000', 180);
                break;
            case \Imagick::ORIENTATION_RIGHTTOP: $img->rotateImage('#000', 90);
                break;
            case \Imagick::ORIENTATION_LEFTBOTTOM: $img->rotateImage('#000', -90);
                break;
        }
        $img->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
    }

    private function withGd(string $path, int $max, int $quality): array
    {
        $data = @file_get_contents($path);
        $src = $data === false ? false : @imagecreatefromstring($data);
        if (! $src) {
            throw new RuntimeException('This file is not a photo we can read.');
        }

        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $src = match ((int) ($exif['Orientation'] ?? 1)) {
                3 => imagerotate($src, 180, 0),
                6 => imagerotate($src, -90, 0),
                8 => imagerotate($src, 90, 0),
                default => $src,
            };
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $max / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // transparent PNG → white
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagejpeg($dst, null, $quality);
        $blob = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);

        return [$blob, $nw, $nh];
    }
}
