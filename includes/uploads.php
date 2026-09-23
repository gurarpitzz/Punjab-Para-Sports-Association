<?php
// includes/uploads.php - Secure Document & Image Optimization Engine for PPSA

/**
 * Optimizes and safely saves an uploaded image or document.
 * - Automatically downsamples massive mobile camera resolutions (e.g. 4000px down to max 1600px / 800px)
 * - Auto-corrects EXIF phone orientation (no rotated photos)
 * - Strips unnecessary EXIF/GPS bloat
 * - Re-encodes JPEGs at high-efficiency 82% quality
 * - Leaves PDFs untouched
 *
 * @param string $tmpPath   Source temporary file path
 * @param string $mime      Mime type
 * @param string $destPath  Target storage path
 * @param int    $maxDim    Max width or height in pixels (e.g. 800 for photo, 1600 for docs)
 * @param int    $quality   JPEG compression quality (1-100)
 * @return bool
 */
function optimizeAndSaveUploadedFile(string $tmpPath, string $mime, string $destPath, int $maxDim = 1600, int $quality = 82): bool {
    // If it's a PDF or GD is not available, move directly
    if ($mime === 'application/pdf' || !function_exists('imagecreatetruecolor')) {
        return @move_uploaded_file($tmpPath, $destPath) || @copy($tmpPath, $destPath);
    }

    // Attempt GD image loading
    $srcImg = null;
    switch ($mime) {
        case 'image/jpeg':
            if (function_exists('imagecreatefromjpeg')) {
                $srcImg = @imagecreatefromjpeg($tmpPath);
            }
            break;
        case 'image/png':
            if (function_exists('imagecreatefrompng')) {
                $srcImg = @imagecreatefrompng($tmpPath);
            }
            break;
        case 'image/webp':
            if (function_exists('imagecreatefromwebp')) {
                $srcImg = @imagecreatefromwebp($tmpPath);
            }
            break;
    }

    if (!$srcImg && function_exists('imagecreatefromstring')) {
        $raw = @file_get_contents($tmpPath);
        if ($raw) {
            $srcImg = @imagecreatefromstring($raw);
        }
    }

    // Fallback if image could not be parsed by GD
    if (!$srcImg) {
        return @move_uploaded_file($tmpPath, $destPath) || @copy($tmpPath, $destPath);
    }

    // Handle EXIF orientation if available (JPEG)
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        try {
            $exif = @exif_read_data($tmpPath);
            if (!empty($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 3:
                        $srcImg = imagerotate($srcImg, 180, 0);
                        break;
                    case 6:
                        $srcImg = imagerotate($srcImg, -90, 0);
                        break;
                    case 8:
                        $srcImg = imagerotate($srcImg, 90, 0);
                        break;
                }
            }
        } catch (\Throwable $e) {}
    }

    $origW = imagesx($srcImg);
    $origH = imagesy($srcImg);

    // Calculate proportional downsampled dimensions
    $newW = $origW;
    $newH = $origH;

    if ($origW > $maxDim || $origH > $maxDim) {
        if ($origW >= $origH) {
            $newW = $maxDim;
            $newH = (int)round(($origH * $maxDim) / $origW);
        } else {
            $newH = $maxDim;
            $newW = (int)round(($origW * $maxDim) / $origH);
        }
    }

    // Create high-quality true-color canvas
    $dstImg = imagecreatetruecolor($newW, $newH);

    // If source is PNG/WebP with transparency, fill white background for clean JPEG export
    $white = imagecolorallocate($dstImg, 255, 255, 255);
    imagefilledrectangle($dstImg, 0, 0, $newW, $newH, $white);

    // Resample with anti-aliasing
    imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

    // Save as optimized JPEG
    $success = false;
    if (function_exists('imagejpeg')) {
        $success = @imagejpeg($dstImg, $destPath, $quality);
    }

    // Free memory
    imagedestroy($srcImg);
    imagedestroy($dstImg);

    if (!$success) {
        return @move_uploaded_file($tmpPath, $destPath) || @copy($tmpPath, $destPath);
    }

    return true;
}
