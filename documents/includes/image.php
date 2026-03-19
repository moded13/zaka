<?php
/**
 * Image utilities (GD-based). Safe fallbacks.
 */

function orphan_gd_available(): bool {
    return extension_loaded('gd');
}

function orphan_make_thumb_and_optimize(string $srcAbs, string $destOptimAbs, string $destThumbAbs): array
{
    // returns [ok, err]
    if (!orphan_gd_available()) return [false, 'GD غير متوفر'];

    $info = @getimagesize($srcAbs);
    if (!$info) return [false, 'الصورة غير صالحة'];

    [$w, $h] = $info;
    $mime = $info['mime'] ?? '';

    $create = match ($mime) {
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png'  => 'imagecreatefrompng',
        'image/webp' => function_exists('imagecreatefromwebp') ? 'imagecreatefromwebp' : null,
        default => null,
    };
    if (!$create || !function_exists($create)) return [false, 'نوع صورة غير مدعوم'];

    $src = @$create($srcAbs);
    if (!$src) return [false, 'فشل قراءة الصورة'];

    // helper resize
    $resize = function($srcImg, int $maxW, int $maxH) use ($w,$h) {
        $ratio = min($maxW / $w, $maxH / $h, 1);
        $nw = (int)floor($w * $ratio);
        $nh = (int)floor($h * $ratio);

        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $srcImg, 0,0,0,0, $nw,$nh, $w,$h);
        return $dst;
    };

    $opt = $resize($src, 1600, 1600);
    $th  = $resize($src,  360,  360);

    // Prefer webp if supported
    $savedOpt = false;
    $savedTh  = false;

    if (function_exists('imagewebp')) {
        $savedOpt = @imagewebp($opt, $destOptimAbs, 80);
        $savedTh  = @imagewebp($th,  $destThumbAbs, 80);
    }

    // fallback to jpeg
    if (!$savedOpt || !$savedTh) {
        $destOptimAbs = preg_replace('/\.webp$/', '.jpg', $destOptimAbs);
        $destThumbAbs = preg_replace('/\.webp$/', '.jpg', $destThumbAbs);
        $savedOpt = @imagejpeg($opt, $destOptimAbs, 82);
        $savedTh  = @imagejpeg($th,  $destThumbAbs, 82);
    }

    imagedestroy($src);
    imagedestroy($opt);
    imagedestroy($th);

    if (!$savedOpt || !$savedTh) return [false, 'فشل حفظ النسخ المضغوطة'];
    return [true, ''];
}