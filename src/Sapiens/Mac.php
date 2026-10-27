<?php

namespace App\Sapiens;

/**
 * The Mac OS 8-bit system palette (the original game draws every pixel as an index in it: Color2Index)
 * and the colour tables built from it (SAPWCVT.C).
 */
final class Mac
{
    /** "\x00\x01...\xFF": with strtr($s, IDENT, $map) a string of indexes goes through a byte map */
    /** @var array<int, array{0:int,1:int,2:int}> */
    public static array $clut = [];
    /** @var array<int, int> Atari ST colour (0x0RGB, 3 bits each) => system index (TabCvtCoul) */
    public static array $stToIndex = [];
    /** index => index of the same colour at half brightness (TabCvtCoulOmbre), as a byte map */
    public static string $shadow = '';
    /** @var array<int, int> cache of color2Index */
    private static array $cache = [];

    public const IDENT = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F\x20\x21\x22\x23\x24\x25\x26\x27\x28\x29\x2A\x2B\x2C\x2D\x2E\x2F\x30\x31\x32\x33\x34\x35\x36\x37\x38\x39\x3A\x3B\x3C\x3D\x3E\x3F\x40\x41\x42\x43\x44\x45\x46\x47\x48\x49\x4A\x4B\x4C\x4D\x4E\x4F\x50\x51\x52\x53\x54\x55\x56\x57\x58\x59\x5A\x5B\x5C\x5D\x5E\x5F\x60\x61\x62\x63\x64\x65\x66\x67\x68\x69\x6A\x6B\x6C\x6D\x6E\x6F\x70\x71\x72\x73\x74\x75\x76\x77\x78\x79\x7A\x7B\x7C\x7D\x7E\x7F\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8A\x8B\x8C\x8D\x8E\x8F\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9A\x9B\x9C\x9D\x9E\x9F\xA0\xA1\xA2\xA3\xA4\xA5\xA6\xA7\xA8\xA9\xAA\xAB\xAC\xAD\xAE\xAF\xB0\xB1\xB2\xB3\xB4\xB5\xB6\xB7\xB8\xB9\xBA\xBB\xBC\xBD\xBE\xBF\xC0\xC1\xC2\xC3\xC4\xC5\xC6\xC7\xC8\xC9\xCA\xCB\xCC\xCD\xCE\xCF\xD0\xD1\xD2\xD3\xD4\xD5\xD6\xD7\xD8\xD9\xDA\xDB\xDC\xDD\xDE\xDF\xE0\xE1\xE2\xE3\xE4\xE5\xE6\xE7\xE8\xE9\xEA\xEB\xEC\xED\xEE\xEF\xF0\xF1\xF2\xF3\xF4\xF5\xF6\xF7\xF8\xF9\xFA\xFB\xFC\xFD\xFE\xFF";

    public const WHITE = 0;
    public const BLACK = 255;

    public static function init(): void
    {
        if (self::$clut !== []) {
            return;
        }
        // 0..214: the 6x6x6 cube from white (FF) down, without black; then red, green, blue and grey
        // ramps of 10 (EE, DD, BB, AA, 88, 77, 55, 44, 22, 11); 255 = black
        $cube = [0xFF, 0xCC, 0x99, 0x66, 0x33, 0x00];
        $ramp = [0xEE, 0xDD, 0xBB, 0xAA, 0x88, 0x77, 0x55, 0x44, 0x22, 0x11];
        $clut = [];
        foreach ($cube as $r) {
            foreach ($cube as $g) {
                foreach ($cube as $b) {
                    if ($r || $g || $b) {
                        $clut[] = [$r, $g, $b];
                    }
                }
            }
        }
        foreach ($ramp as $v) {
            $clut[] = [$v, 0, 0];
        }
        foreach ($ramp as $v) {
            $clut[] = [0, $v, 0];
        }
        foreach ($ramp as $v) {
            $clut[] = [0, 0, $v];
        }
        foreach ($ramp as $v) {
            $clut[] = [$v, $v, $v];
        }
        $clut[] = [0, 0, 0];
        self::$clut = $clut;

        // FabriqueLuts
        for ($r = 0; $r < 8; $r++) {
            for ($v = 0; $v < 8; $v++) {
                for ($b = 0; $b < 8; $b++) {
                    self::$stToIndex[($r << 8) + ($v << 4) + $b] = self::color2Index($r * 9362, $v * 9362, $b * 9362);
                }
            }
        }
        $shadow = '';
        for ($i = 0; $i < 256; $i++) {
            [$r, $g, $b] = $clut[$i];
            $shadow .= chr(self::color2Index((($r << 8) | $r) >> 1, (($g << 8) | $g) >> 1, (($b << 8) | $b) >> 1));
        }
        self::$shadow = $shadow;
    }

    /** Color2Index / SapColor2Index: nearest system colour (16-bit components) */
    public static function color2Index(int $r, int $g, int $b): int
    {
        $key = (($r >> 8) << 16) | (($g >> 8) << 8) | ($b >> 8);
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        $best = 0;
        $min = -1;
        foreach (self::$clut as $i => $c) {
            $d = abs($r - (($c[0] << 8) | $c[0])) + abs($g - (($c[1] << 8) | $c[1])) + abs($b - (($c[2] << 8) | $c[2]));
            if ($min < 0 || $d < $min) {
                $min = $d;
                $best = $i;
            }
        }
        return self::$cache[$key] = $best;
    }

    /** TabCvtCoul[st colour] */
    public static function st(int $color): int
    {
        return self::$stToIndex[$color & 0x777] ?? 0;
    }

    /** Index2Color: 16-bit RGB */
    public static function rgb16(int $index): array
    {
        $c = self::$clut[$index & 255];
        return [($c[0] << 8) | $c[0], ($c[1] << 8) | $c[1], ($c[2] << 8) | $c[2]];
    }
}
