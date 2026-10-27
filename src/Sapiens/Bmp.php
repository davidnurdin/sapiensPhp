<?php

namespace App\Sapiens;

/** 8-bit palettised BMP files: the textures of the screen and the pictures of the original game. */
final class Bmp
{
    /**
     * @param array<int, array{0:int,1:int,2:int}> $palette
     * @param string $pixels one palette index per byte, rows top to bottom
     */
    public static function encode(int $w, int $h, string $pixels, array $palette): string
    {
        $stride = ($w + 3) & ~3;
        $pad = str_repeat("\0", $stride - $w);
        $rows = [];
        for ($y = $h - 1; $y >= 0; $y--) {
            $rows[] = substr($pixels, $y * $w, $w) . $pad;
        }
        return self::header($w, $h, $palette, $stride * $h) . implode('', $rows);
    }

    /** @param array<int, array{0:int,1:int,2:int}> $palette */
    public static function header(int $w, int $h, array $palette, int $size): string
    {
        $pal = '';
        for ($i = 0; $i < 256; $i++) {
            $c = $palette[$i] ?? [0, 0, 0];
            $pal .= chr($c[2]) . chr($c[1]) . chr($c[0]) . "\0";
        }
        $offset = 14 + 40 + 1024;
        return 'BM' . pack('VvvV', $offset + $size, 0, 0, $offset)
            . pack('VVVvvVVVVVV', 40, $w, $h, 1, 8, 0, $size, 2835, 2835, 256, 0)
            . $pal;
    }

    /** @return array{0:int,1:int,2:array<int, array{0:int,1:int,2:int}>,3:string} width, height, palette, pixels (top to bottom) */
    public static function decode(string $b): array
    {
        $offset = unpack('V', $b, 10)[1];
        [, $w, $h] = unpack('V2', $b, 18);
        $colors = unpack('V', $b, 46)[1] ?: 256;
        $pal = [];
        for ($i = 0; $i < $colors; $i++) {
            $pal[$i] = [ord($b[54 + $i * 4 + 2]), ord($b[54 + $i * 4 + 1]), ord($b[54 + $i * 4])];
        }
        $stride = ($w + 3) & ~3;
        $pix = '';
        for ($y = $h - 1; $y >= 0; $y--) {
            $pix .= substr($b, $offset + $y * $stride, $w);
        }
        return [$w, $h, $pal, $pix];
    }
}
