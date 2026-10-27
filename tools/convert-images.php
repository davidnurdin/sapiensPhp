<?php

/**
 * Offline: the help pictures of the original game (data/images/image_<id>.png, RGB) -> 8-bit BMP in
 * the Mac system palette (the screen of the port is 8-bit like the original one).
 * Usage: bin/jumphp php games/sapiens tools/convert-images.php
 */

require __DIR__ . '/../src/Sapiens/Mac.php';
require __DIR__ . '/../src/Sapiens/Bmp.php';

use App\Sapiens\Bmp;
use App\Sapiens\Mac;

Mac::init();
foreach (glob(__DIR__ . '/../../../.original/data/images/image_*.png') as $file) {
    $img = imagecreatefrompng($file);
    $w = imagesx($img);
    $h = imagesy($img);
    $pix = '';
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $c = imagecolorsforindex($img, imagecolorat($img, $x, $y));
            $pix .= chr(Mac::color2Index($c['red'] * 257, $c['green'] * 257, $c['blue'] * 257));
        }
    }
    $out = __DIR__ . '/../assets/data/' . basename($file, '.png') . '.bmp';
    file_put_contents($out, Bmp::encode($w, $h, $pix, Mac::$clut));
    echo basename($out), "\n";
}
