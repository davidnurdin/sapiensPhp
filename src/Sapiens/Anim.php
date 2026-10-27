<?php

namespace App\Sapiens;

/**
 * The animation bytecode of the side view (SAPGLOB.C: anjm1, anjm2...) in one flat table: a C pointer
 * into an animation is an offset in ANIM (0 = NULL). Values are the C initialisers: read them as
 * signed chars (Game::sc()).
 */
final class Anim
{
    public const O_ANJM1 = 1;
    public const O_ANJM2 = 49;
    public const O_ANJM3 = 55;
    public const O_ANACR1 = 59;
    public const O_ANACR2 = 65;
    public const O_ANGEN1 = 69;
    public const O_ANGEN2 = 75;
    public const O_ANAGA1 = 79;
    public const O_ANAGA2 = 105;
    public const O_ANRGA1 = 119;
    public const O_ANRGA2 = 145;
    public const O_ANBRAS = 159;
    public const O_ANBRA1 = 190;
    public const O_ANBRA2 = 194;
    public const O_ANHAC1 = 198;
    public const O_ANSAG1 = 202;
    public const O_ANPOIN = 206;
    public const O_ANHACH = 220;
    public const O_ANSAG = 238;
    public const O_ANBUST = 248;
    public const O_ANBST1 = 252;
    public const O_ANTETE = 268;
    public const O_ANTET1 = 281;
    public const O_ANTET2 = 288;
    public const O_ANARM1 = 292;
    public const O_ANARM3 = 312;
    public const O_ANARM4 = 316;
    public const O_ANEUTR = 320;
    public const O_ANLOU1 = 322;
    public const O_ANLOU2 = 326;
    public const O_ANLAP1 = 345;
    public const O_ANLAP2 = 352;
    public const O_ANLAP3 = 364;

    public const ANIM = [
        0, 130, 129, 0, 26, 0, 128, -2, 129, 1, 26, 0, 129, 2, 26, 0,
        128, -1, 129, 3, 26, 0, 129, 4, 26, 0, 128, 0, 129, 5, 26, 0,
        129, 6, 26, 0, 129, 7, 26, 0, 129, 8, 26, 0, 129, 9, 26, 0,
        255, 128, 0, 0, 26, 0, 255, 4, 26, 0, 255, 128, 2, 15, 27, 1,
        255, 16, 27, 2, 255, 128, 13, 17, 19, 0, 255, 18, 24, 0, 255, 15,
        27, 1, 128, -1, 8, 26, 0, 129, 128, -2, 9, 26, 0, 129, 128, -1,
        0, 26, 0, 128, 0, 130, 134, 127, 255, 16, 27, 2, 5, 26, 0, 6,
        26, 0, 6, 26, 0, 130, 255, 15, 27, 1, 128, -1, 0, 26, 0, 133,
        128, -2, 9, 26, 0, 128, -1, 133, 8, 26, 0, 128, 0, 130, 134, 127,
        255, 16, 27, 2, 6, 26, 0, 6, 26, 0, 5, 26, 0, 130, 255, 31,
        12, 0, 32, 12, 0, 33, 12, 0, 34, 12, 0, 35, 12, 0, 35, 12,
        0, 34, 12, 0, 33, 12, 0, 32, 12, 0, 31, 12, 0, 255, 34, 12,
        0, 255, 33, 12, 0, 255, 38, 12, 0, 255, 36, 9, 2, 255, 34, 12,
        0, 107, 12, -1, 135, 3, 37, 12, 1, 134, 252, 255, 38, 12, 0, 39,
        12, -1, 40, 12, -1, 41, 14, 0, 39, 12, -1, 134, 252, 255, 36, 9,
        2, 37, 9, 1, 134, 252, 136, 255, 120, 12, 1, 255, 128, 18, 121, 12,
        1, 121, 12, 1, 121, 12, 1, 121, 12, 1, 132, 255, 53, 0, 2, 53,
        0, 2, 54, 0, 2, 54, 0, 2, 255, 53, 0, 2, 54, 0, 2, 255,
        53, 0, 2, 255, 45, 7, -2, 47, 8, -3, 135, 3, 49, 6, -4, 51,
        10, -1, 45, 8, -2, 134, 252, 255, 45, 7, -2, 255, 43, 11, -1, 255,
        131, 255, 96, 26, 0, 255, 95, 26, 0, 129, 96, 26, 0, 129, 97, 26,
        0, 129, 98, 26, 0, 129, 135, 1, 255, 87, 35, 0, 87, 35, 0, 255,
        87, 35, 0, 129, 88, 35, 0, 89, 35, 0, 129, 255, 90, 28, 2, 90,
        28, 2, 90, 28, 2, 90, 28, 2, 255,
    ];

    /** debban[13][14]: the animation of each body part by posture */
    public const DEBBAN = [
        [self::O_ANJM1, self::O_ANJM1, self::O_ANBRAS, self::O_ANBRAS, self::O_ANJM1+27, self::O_ANJM1, self::O_ANBUST, self::O_ANBUST, self::O_ANTETE, self::O_ANTETE, 0, 0, self::O_ANBRAS+15, self::O_ANBRAS],
        [self::O_ANJM2, self::O_ANJM2, self::O_ANBRA1, self::O_ANBRA1, self::O_ANJM3, self::O_ANJM3, self::O_ANBUST, self::O_ANBUST, self::O_ANTET2, self::O_ANTET2, 0, 0, self::O_ANBRA2, self::O_ANBRA2],
        [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, self::O_ANEUTR, 0, self::O_ANPOIN, 0],
        [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, self::O_ANARM1, 0, self::O_ANHACH, 0],
        [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, self::O_ANEUTR, self::O_ANEUTR, self::O_ANSAG, self::O_ANBRA2],
        [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, self::O_ANEUTR, self::O_ANEUTR, 0, 0],
        [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, self::O_ANARM3, self::O_ANARM3, self::O_ANHAC1, self::O_ANHAC1],
        [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, self::O_ANARM4, self::O_ANARM4, self::O_ANSAG1, self::O_ANSAG1],
        [self::O_ANACR1, self::O_ANACR1, self::O_ANBRA1, self::O_ANBRA1, self::O_ANACR2, self::O_ANACR2, 0, 0, self::O_ANTET2, self::O_ANTET2, 0, 0, self::O_ANBRA2, self::O_ANBRA2],
        [self::O_ANGEN1, self::O_ANGEN1, self::O_ANBRA1, self::O_ANBRA1, self::O_ANGEN2, self::O_ANGEN2, self::O_ANBUST, self::O_ANBUST, 0, 0, 0, 0, 0, 0],
        [self::O_ANEUTR, self::O_ANEUTR, self::O_ANEUTR, self::O_ANEUTR, self::O_ANEUTR, self::O_ANEUTR, self::O_ANBST1, self::O_ANBUST, 0, 0, 0, 0, 0, 0],
        [self::O_ANAGA1, 0, 0, 0, self::O_ANAGA2, 0, 0, 0, self::O_ANTET1, 0, 0, 0, 0, 0],
        [self::O_ANRGA1, 0, 0, 0, self::O_ANRGA2, 0, 0, 0, self::O_ANTET1, 0, 0, 0, 0, 0],
    ];
}
