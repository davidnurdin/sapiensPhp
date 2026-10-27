<?php

namespace App\Sapiens;

/**
 * SAPWBRT.C (sprites of the .BRT files, drawing in the 8-bit offscreens), SAPWCVT.C (colours) and the
 * few QuickDraw calls of the game, on Port objects.
 *
 * Rectangles are arrays [left, top, right, bottom] (SetRect order). Coordinates: the offscreens are in
 * real pixels (640x400 for the window = 320x200 "ST" coordinates * ECHELLE); the SapZoom of the
 * window is done by the engine (the view is 640x400).
 */
trait GfxTrait
{
    /** @var array<int, Mob|null> TabMob */
    public array $TabMob = [];
    /** @var array<int, array{n:int, rgb:array<int, array{0:int,1:int,2:int}>, map:string}> TabPalette */
    public array $TabPalette = [];

    public Port $Ecran1;
    public Port $Ecran2;
    public Port $debecr;
    public Port $BuffMouse;
    public Port $mainWindow;
    /** the current port (AcamSetPortOffscreen / CarboniseSetPortWindow) */
    public Port $curPort;

    // QuickDraw state of the current drawing (one for all the ports: the game always sets it)
    public int $qdFore = Mac::BLACK;
    public int $qdBack = Mac::WHITE;
    public int $qdPenW = 1;
    public int $qdPenH = 1;
    public int $qdPenX = 0;
    public int $qdPenY = 0;
    /** 'black' | 'gray' (CarboniseSetPatGray / Black) */
    public string $qdPat = 'black';
    /** PenMode(patOr): only the set pixels of the pattern are drawn */
    public bool $qdPatOr = false;
    /** @var array{0:int,1:int,2:int,3:int}|null ClipRect of the current drawing (text of the message) */
    public ?array $qdClip = null;

    public static function SetRect(int $l, int $t, int $r, int $b): array
    {
        return [$l, $t, $r, $b];
    }

    public static function OffsetRect(array $r, int $dh, int $dv): array
    {
        return [$r[0] + $dh, $r[1] + $dv, $r[2] + $dh, $r[3] + $dv];
    }

    public static function InsetRect(array $r, int $dh, int $dv): array
    {
        return [$r[0] + $dh, $r[1] + $dv, $r[2] - $dh, $r[3] - $dv];
    }

    public static function EmptyRect(array $r): bool
    {
        return $r[2] <= $r[0] || $r[3] <= $r[1];
    }

    public static function PtInRect(array $pt, array $r): bool
    {
        return $pt[0] >= $r[0] && $pt[0] < $r[2] && $pt[1] >= $r[1] && $pt[1] < $r[3];
    }

    /** @return array|null the intersection, null if empty */
    public static function SectRect(array $a, array $b): ?array
    {
        $r = [max($a[0], $b[0]), max($a[1], $b[1]), min($a[2], $b[2]), min($a[3], $b[3])];
        return self::EmptyRect($r) ? null : $r;
    }

    public function AcamSetPortOffscreen(Port $p): void
    {
        $this->curPort = $p;
    }

    public function AcamGetPortOffscreen(): Port
    {
        return $this->curPort;
    }

    public function CarboniseSetPortWindow(): void
    {
        $this->curPort = $this->mainWindow;
    }

    public function initPorts(): void
    {
        Mac::init();
        $this->Ecran1 = new Port(256 * 2, 100 * 2);
        $this->Ecran2 = new Port(256 * 2, 100 * 2);
        $this->debecr = new Port(320 * 2, 200 * 2);
        $this->BuffMouse = new Port(64 * 2, 64 * 2);
        $this->mainWindow = new Port(320 * 2, 200 * 2);
        $this->curPort = $this->mainWindow;
    }

    // ---- mobs --------------------------------------------------------------------------------------

    /**
     * ChargeFichierBrt: palette, then the sprites (big-endian sizes, raw indexes).
     * @return int number of mobs loaded
     */
    public function ChargeFichierBrt(string $file, int $id, int $nbMobs, int $vide): int
    {
        $b = file_get_contents($file);
        if ($b === false || substr($b, 0, 4) !== 'BRUT') {
            throw new \RuntimeException("ChargeFichierBrt: bad file $file");
        }
        $n = ord($b[4]) + 1;
        $rgb = [];
        $map = '';
        for ($i = 0; $i < $n; $i++) {
            $r = ord($b[5 + $i * 3]);
            $g = ord($b[6 + $i * 3]);
            $bl = ord($b[7 + $i * 3]);
            $rgb[$i] = [($r << 8) | $r, ($g << 8) | $g, ($bl << 8) | $bl];
            $map .= chr(Mac::color2Index($rgb[$i][0], $rgb[$i][1], $rgb[$i][2]));
        }
        $map = str_pad($map, 256, "\0");
        $palette = count($this->TabPalette);
        $this->TabPalette[$palette] = ['n' => $n, 'rgb' => $rgb, 'map' => $map];
        $p = 5 + $n * 3;
        $count = unpack('n', $b, $p)[1];
        if ($count !== $nbMobs) {
            throw new \RuntimeException("ChargeFichierBrt: $file has $count mobs, $nbMobs expected");
        }
        $p += 2;
        for ($i = 0; $i < $count; $i++, $id++) {
            [, $w, $h] = unpack('n2', $b, $p);
            $p += 4;
            $mob = new Mob($w, $h, $palette, $vide, substr($b, $p, $w * $h));
            $mob->prepare($map);
            $this->TabMob[$id] = $mob;
            $p += $w * $h;
        }
        return $count;
    }

    /**
     * CalculePaletteAttenuee: the palette of a mob, the $nbCoul first colours multiplied by $coeff.
     * @return string a byte map (tabIndex)
     */
    public function CalculePaletteAttenuee(int $num, float $coeff, int $nbCoul): string
    {
        $coeffl = (int)(256.0 * $coeff);
        $palette = $this->TabPalette[$this->TabMob[$num]->indexPalette];
        $map = '';
        for ($i = 0; $i < $palette['n']; $i++) {
            $c = $palette['rgb'][$i];
            if ($i < $nbCoul) {
                $map .= chr(Mac::color2Index(
                    min(65535, ($coeffl * $c[0]) >> 8),
                    min(65535, ($coeffl * $c[1]) >> 8),
                    min(65535, ($coeffl * $c[2]) >> 8)
                ));
            } else {
                $map .= chr(Mac::color2Index($c[0], $c[1], $c[2]));
            }
        }
        return str_pad($map, 256, "\0");
    }

    /** The default map of the palette of a mob */
    public function mobMap(int $num): string
    {
        return $this->TabPalette[$this->TabMob[$num]->indexPalette]['map'];
    }

    public function CalculeAireMob(int $num, int $x, int $y): array
    {
        $mob = $this->TabMob[$num];
        $x *= 2;
        $y *= 2;
        return [$x, $y, $x + $mob->nbcol, $y + $mob->nbligne];
    }

    /**
     * Affmob3d: a mob scaled into the rectangle $aire of the current port (pixels), with the palette
     * map $tabIndex (null = the palette of the mob).
     */
    public function Affmob3d(int $num, array $aire, ?string $tabIndex = null): void
    {
        $mob = $this->TabMob[$num] ?? null;
        if ($mob === null || $mob->nbcol <= 0 || $mob->nbligne <= 0) {
            return;
        }
        $port = $this->curPort;
        $tx = $mob->nbcol;
        $ty = $mob->nbligne;
        [$l, $t, $r, $b] = $aire;
        $i = $r - $l;
        $j = $b - $t;
        if ($i <= 0 || $j <= 0) {
            return;
        }
        $pasX = intdiv($tx << 16, $i);
        $pasY = intdiv($ty << 16, $j);
        $x0 = 0;
        $y0 = 0;
        if ($l < 0) {
            $x0 = (int)(-$l * $pasX / 65536.0);
            $l = 0;
        }
        if ($t < 0) {
            $y0 = (int)(-$t * $pasY / 65536.0);
            $t = 0;
        }
        $r = min($r, $port->w);
        $b = min($b, $port->h);
        if ($r <= $l || $b <= $t) {
            return;
        }
        $w = $r - $l;
        // columns of the source for each column of the destination
        $cols = [];
        $sx = $x0;
        $rest = 0;
        for ($k = 0; $k < $w; $k++) {
            $cols[$k] = $sx;
            $rest += $pasX;
            $sx += $rest >> 16;
            $rest &= 0xFFFF;
        }
        if ($sx > $tx) {
            foreach ($cols as $k => $c) {
                if ($c >= $tx) {
                    $cols[$k] = $tx - 1;
                }
            }
        }
        $identity = $w === $tx && $x0 === 0;
        $cache = [];
        $sy = $y0;
        $restY = 0;
        for ($y = $t; $y < $b; $y++) {
            if ($sy >= $ty) {
                break;
            }
            if (!isset($cache[$sy])) {
                $src = $tabIndex === null ? $mob->rows[$sy] : $mob->rowWith($sy, $tabIndex);
                $mask = $mob->masks[$sy];
                if ($identity) {
                    $cache[$sy] = [$src, $mask];
                } else {
                    $s = '';
                    $m = '';
                    foreach ($cols as $c) {
                        $s .= $src[$c];
                        $m .= $mask[$c];
                    }
                    $cache[$sy] = [$s, $m];
                }
            }
            [$s, $m] = $cache[$sy];
            $row = $port->rows[$y];
            $port->rows[$y] = substr_replace($row, (substr($row, $l, $w) & $m) | $s, $l, $w);
            $restY += $pasY;
            $sy += $restY >> 16;
            $restY &= 0xFFFF;
        }
        $port->dirty = true;
    }

    /**
     * Affmobbrt: a mob at ($x, $y) in ST coordinates (0-320, 0-200) of the current port, flipped if
     * $sens, with the palette map $tabIndex (null = the palette of the mob).
     */
    public function Affmobbrt(int $num, int $x, int $y, int $sens, ?string $tabIndex = null): void
    {
        $mob = $this->TabMob[$num] ?? null;
        if ($mob === null || $mob->nbcol <= 0 || $mob->nbligne <= 0) {
            return;
        }
        $x *= 2;
        $y *= 2;
        // a flipped mob is drawn from x leftwards: its left edge is x - nbcol + 1
        $left = $sens ? $x - $mob->nbcol + 1 : $x;
        $this->blitRows($this->curPort, $mob, $left, $y, (bool)$sens, $tabIndex);
    }

    /** Draws the rows of a mob at the pixel ($x, $y), clipped. */
    private function blitRows(Port $port, Mob $mob, int $x, int $y, bool $flip, ?string $tabIndex, ?string $color = null): void
    {
        $tx = $mob->nbcol;
        $ty = $mob->nbligne;
        $sx = 0;
        $w = $tx;
        if ($x < 0) {
            $sx = -$x;
            $w += $x;
            $x = 0;
        }
        if ($x + $w > $port->w) {
            $w = $port->w - $x;
        }
        if ($w <= 0) {
            return;
        }
        $y0 = max(0, -$y);
        $y1 = min($ty, $port->h - $y);
        for ($j = $y0; $j < $y1; $j++) {
            if ($color !== null) {
                $mask = $mob->masks[$j];
                $src = ~$mask & str_repeat($color, $tx);
            } else {
                $src = $tabIndex === null ? $mob->rows[$j] : $mob->rowWith($j, $tabIndex);
                $mask = $mob->masks[$j];
            }
            if ($flip) {
                $src = strrev($src);
                $mask = strrev($mask);
            }
            if ($sx || $w !== $tx) {
                $src = substr($src, $sx, $w);
                $mask = substr($mask, $sx, $w);
            }
            $row = $port->rows[$y + $j];
            $port->rows[$y + $j] = substr_replace($row, (substr($row, $x, $w) & $mask) | $src, $x, $w);
        }
        $port->dirty = true;
    }

    /** AffmobCar: a mob in one colour at the pixel ($x, $y) of the current port (the font glyphs). */
    public function AffmobCar(int $num, int $x, int $y, int $couleur): void
    {
        $mob = $this->TabMob[$num] ?? null;
        if ($mob === null || $mob->nbcol <= 0 || $mob->nbligne <= 0) {
            return;
        }
        if ($this->qdClip !== null) {
            $this->blitClipped($mob, $x, $y, $couleur);
            return;
        }
        $this->blitRows($this->curPort, $mob, $x, $y, false, null, chr($couleur));
    }

    private function blitClipped(Mob $mob, int $x, int $y, int $couleur): void
    {
        [$cl, $ct, $cr, $cb] = $this->qdClip;
        $port = $this->curPort;
        for ($j = 0; $j < $mob->nbligne; $j++) {
            $py = $y + $j;
            if ($py < $ct || $py >= $cb || $py < 0 || $py >= $port->h) {
                continue;
            }
            $mask = $mob->masks[$j];
            for ($i = 0; $i < $mob->nbcol; $i++) {
                $px = $x + $i;
                if ($mask[$i] === "\0" && $px >= $cl && $px < $cr && $px >= 0 && $px < $port->w) {
                    $port->rows[$py][$px] = chr($couleur);
                }
            }
        }
        $port->dirty = true;
    }

    /**
     * Affmobbrtomb: a mob at ($x, $y) (ST coordinates) with its shadow ($deltah, $deltav ST pixels
     * further): the screen under the shadow is darkened (TabCvtCoulOmbre).
     * @return array the rectangle of the mob and its shadow
     */
    public function Affmobbrtomb(int $num, int $x, int $y, int $deltah, int $deltav): array
    {
        $mob = $this->TabMob[$num] ?? null;
        if ($mob === null) {
            return [0, 0, 0, 0];
        }
        $tx = $mob->nbcol;
        $ty = $mob->nbligne;
        $deltah *= 2;
        $deltav *= 2;
        $x *= 2;
        $y *= 2;
        $r = [$x, $y, $x + $tx + $deltah, $y + $ty + $deltav];
        if ($tx <= 0 || $ty <= 0) {
            return $r;
        }
        $port = $this->curPort;
        // the shadow
        $sx = $x + $deltah;
        $l = max(0, $sx);
        $rr = min($port->w, $sx + $tx);
        if ($rr > $l) {
            for ($j = 0; $j < $ty; $j++) {
                $py = $y + $j + $deltav;
                if ($py < 0 || $py >= $port->h || $py + 1 >= 400) {
                    continue;
                }
                $mask = substr($mob->masks[$j], $l - $sx, $rr - $l);
                $seg = substr($port->rows[$py], $l, $rr - $l);
                $dark = strtr($seg, Mac::IDENT, Mac::$shadow);
                $port->rows[$py] = substr_replace($port->rows[$py], ($seg & $mask) | ($dark & ~$mask), $l, $rr - $l);
            }
        }
        $this->blitRows($port, $mob, $x, $y, false, null);
        return $r;
    }

    public function PlotIndexPixBrt(Port $port, int $x, int $y, int $coul): void
    {
        $port->plot($x, $y, $coul);
    }

    public function GetIndexPixBrt(Port $port, int $x, int $y): int
    {
        return $port->get($x, $y);
    }

    /** SwapCouleur: exchanges two colours in a rectangle of a port */
    public function SwapCouleur(Port $port, array $r, int $coul1, int $coul2): void
    {
        $map = Mac::IDENT;
        $map[$coul1] = chr($coul2);
        $map[$coul2] = chr($coul1);
        $port->mapRect($r[0], $r[1], $r[2], $r[3], $map);
    }

    /** BoxFill: rectangle (pixels) of a port in an ST colour */
    public function BoxFill(Port $port, array $r, int $couleur): void
    {
        $port->fill($r[0], $r[1], $r[2], $r[3], Mac::st($couleur));
    }

    /** RemplitLigneBrut: ST lines y1..y2 of the current port with a system index */
    public function RemplitLigneBrut(int $y1, int $y2, int $index): void
    {
        $this->curPort->fill(0, $y1 * 2, $this->curPort->w, $y2 * 2, $index);
    }

    /**
     * RemplitZoneLignes: from the pixel line $y, $taille lines of the current port, one colour each.
     * @param int[] $tabCouleur system indexes
     */
    public function RemplitZoneLignes(int $y, int $taille, array $tabCouleur, int $offset = 0): void
    {
        $port = $this->curPort;
        for ($i = 0; $i < $taille; $i++) {
            if ($y + $i >= 0 && $y + $i < $port->h) {
                $port->rows[$y + $i] = str_repeat(chr($tabCouleur[$offset + $i]), $port->w);
            }
        }
        $port->dirty = true;
    }

    /** DebDeg: dither patterns of the disks, 32 bits each (as strings: the browser has 32-bit ints) */
    private const DEB_DEG = [
        '10000000000000000000000000000000', '11000000000000000000000000000000', '10100000000000000000000000000000', '11010000000000000000000000000000',
        '11001000000000000000000000000000', '11011000000000000000000000000000', '10101100000000000000000000000000', '11010100000000000000000000000000',
        '11110101000000000000000000000000', '11101100100000000000000000000000', '11101100100000000000000000000000', '11110110100000000000000000000000',
        '11110101011010000000000000000000', '11111101110100000000000000000000', '11111010101101000000000000000000', '11111000110100000000000000000000',
        '11111101101111001000000000000000', '11111110111011001000000000000000', '11111011101010010000000000000000', '11101110111010100000000000000000',
        '11110110101100110000000000000000', '11111101101010110100000000000000', '11110110101101011010000000000000', '11111101110110010100101000000000',
        '11111011011001010010010000000000', '11111101111001100001100000000000', '11110111011110011011001000000000', '11111101101011110010101010000000',
        '11111111011101011110110001101000', '11111111011111010110110110010100', '11111101110101101011011011010000', '11111101110010110100101000010100',
        '11110110111011000110010101100000', '11101111101110101001001100000000', '11110111011101011011010101000000',
    ];
    private const TABPOURC = [3276, 9830, 19660, 32767, 45874, 55704, 62258, 65535];

    /**
     * TraceDisque: a disk of the trees of the side view (8 colours of the palette palhaute/tabcoul,
     * dithered), from the edges ($dg, $dd at line $dy) to ($fg, $fd), $epaisseur lines up.
     * $dg, $dd, $dy are updated (by reference) like the C pointers.
     */
    public function TraceDisque(int &$dg, int &$dd, int &$dy, int $fg, int $fd, int $epaisseur): void
    {
        $fy = 0;
        if ($dy > 0 && $epaisseur > 0) {
            $port = $this->curPort;
            $fy = $dy * 2;
            $epaisseur *= 2;
            $x1 = ($dg << 16) * 2;
            $x2 = ($dd << 16) * 2;
            $dx1 = intdiv(2 * (($fg - $dg) << 16), $epaisseur);
            $dx2 = intdiv(2 * (($fd - $dd) << 16), $epaisseur);
            $index = [];
            for ($i = 0; $i < 9; $i++) {
                $index[$i] = chr(Mac::st($this->palhaute[$this->tabcoul[$i]] ?? 0));
            }
            while ($fy && $epaisseur--) {
                if ($fy < 200) {
                    $delta = ($x2 - $x1) >> 16;
                    $sx1 = $x1 >> 16;
                    for ($i = 0; $i < 8; $i++) {
                        $sx2 = ($x1 >> 16) + (($delta * self::TABPOURC[$i]) >> 16);
                        if ($sx1 < 0) {
                            $sx1 = 0;
                        }
                        if ($sx2 > 511) {
                            $sx2 = 511;
                        }
                        if ($sx1 < 512 && $sx2 > 0 && $sx1 !== $sx2 && $sx2 > $sx1 && $fy >= 0 && $fy < $port->h) {
                            $tsx = $sx2 - $sx1;
                            $bits = self::DEB_DEG[($tsx > 32 ? 31 : $tsx - 1) + ($fy & 3)];
                            $seg = strtr(str_pad(substr($bits, 0, min($tsx, 32)), $tsx, '0'), ['1' => $index[$i], '0' => $index[$i + 1]]);
                            $port->rows[$fy] = substr_replace($port->rows[$fy], $seg, $sx1, $tsx);
                        }
                        $sx1 = $sx2;
                    }
                }
                $x1 += $dx1;
                $x2 += $dx2;
                $fy--;
            }
            $port->dirty = true;
        }
        $dg = $fg;
        $dd = $fd;
        if ($fy <= 0) {
            $dy = 0;
        } else {
            $dy = intdiv($fy, 2);
        }
    }

    /** CopyOffscreen: rectangle $rs of $source to the rectangle $rd of $dest (same size). */
    public function CopyOffscreen(Port $source, Port $dest, array $rs, array $rd): void
    {
        if (self::EmptyRect($rs)) {
            return;
        }
        $dest->copyFrom($source, $rs[0], $rs[1], $rs[2], $rs[3], $rd[0], $rd[1]);
    }

    public function effdemi(): void
    {
        $r = [0, 32 * 2, 255 * 2, (32 + 66) * 2];
        $this->CopyOffscreen($this->Ecran2, $this->Ecran1, $r, $r);
    }

    public function tfrdemi(): void
    {
        $this->CopyOffscreen($this->Ecran1, $this->mainWindow, [0, 32 * 2, 255 * 2, (32 + 66) * 2], [32 * 2, 33 * 2, (32 + 255) * 2, (33 + 66) * 2]);
    }

    public function effima(int $couleur): void
    {
        $this->BoxFill($this->Ecran1, [0, 0, 255 * 2, 100 * 2], $couleur);
    }

    public function effvirt(int $couleur): void
    {
        $this->BoxFill($this->debecr, [32 * 2, 1 * 2, (32 + 255) * 2, 101 * 2], $couleur);
    }

    public function tfrima(): void
    {
        $this->CopyOffscreen($this->Ecran1, $this->mainWindow, [0, 0, 255 * 2, 100 * 2], [32 * 2, 1 * 2, (32 + 255) * 2, (1 + 100) * 2]);
    }

    public function tfrvirt(): void
    {
        $this->CopyOffscreen($this->Ecran1, $this->debecr, [0, 0, 255 * 2, 100 * 2], [32 * 2, 1 * 2, (32 + 255) * 2, (1 + 100) * 2]);
    }

    /** tfrmenufenetre: the bottom of the window active (copied from debecr) or greyed */
    public function tfrmenufenetre(int $etat): void
    {
        $r = [0, 113 * 2, 320 * 2, 200 * 2];
        if ($etat) {
            $this->CopyOffscreen($this->debecr, $this->mainWindow, $r, $r);
        } else {
            $gbak = $this->curPort;
            $this->CarboniseSetPortWindow();
            $this->qdPat = 'gray';
            $this->RGBForeColor(15000, 6000, 2000);
            $this->qdPatOr = true;
            $this->DoPaintRect($r);
            $this->qdFore = Mac::BLACK;
            $this->qdPat = 'black';
            $this->qdPatOr = false;
            $this->curPort = $gbak;
        }
    }

    public function CopyEcran1Sur2(): void
    {
        $r = [0, 0, 255 * 2, 100 * 2];
        $this->CopyOffscreen($this->Ecran1, $this->Ecran2, $r, $r);
    }

    /** TfrEcr: a rectangle of debecr (null = all) to the window */
    public function TfrEcr(?array $r = null): void
    {
        $r ??= [0, 0, 640, 400];
        $this->CopyOffscreen($this->debecr, $this->mainWindow, $r, $r);
    }

    public function Affmob(int $mob, int $y, int $x, int $inverse): void
    {
        $gbak = $this->curPort;
        $this->curPort = $this->Ecran1;
        $this->Affmobbrt($mob, $x, $y, $inverse);
        $this->curPort = $gbak;
    }

    public function AffmobConst(int $mob, int $y, int $x, int $inverse): void
    {
        $gbak = $this->curPort;
        $this->curPort = $this->Ecran1;
        $this->Affmobbrt($mob, $x, $y, $inverse);
        $this->curPort = $this->Ecran2;
        $this->Affmobbrt($mob, $x, $y, $inverse);
        $this->curPort = $gbak;
    }

    public function AffDebecr(int $mob, int $y, int $x, int $transfere): void
    {
        $gbak = $this->curPort;
        $this->curPort = $this->debecr;
        $this->Affmobbrt($mob, $x, $y, 0);
        if ($transfere) {
            $this->TfrEcr($this->CalculeAireMob($mob, $x, $y));
        }
        $this->curPort = $gbak;
    }

    /** affomb: a mob and its shadow on debecr, copied to the window if $recopie */
    public function affomb(int $mob, int $ligne, int $colonne, int $deltav, int $deltah, bool $recopie): void
    {
        $gbak = $this->curPort;
        $this->curPort = $this->debecr;
        $r = $this->Affmobbrtomb($mob, $colonne, $ligne, $deltah, $deltav);
        if ($recopie) {
            $this->CopyOffscreen($this->debecr, $this->mainWindow, $r, $r);
        }
        $this->curPort = $gbak;
    }

    public function AffmobPalette(int $mob, int $y, int $x, int $inverse, ?string $tabIndex): void
    {
        $gbak = $this->curPort;
        $this->curPort = $this->Ecran1;
        $this->Affmobbrt($mob, $x, $y, $inverse, $tabIndex);
        $this->curPort = $gbak;
    }

    /** AffichePictDansAire: help picture image_<num + 1000 * langue> in the rectangle of the current port */
    public function AffichePictDansAire(int $num, array $r): void
    {
        $file = $this->dataDir . '/image_' . ($num + 1000 * $this->langue) . '.bmp';
        if (!is_file($file)) {
            $file = $this->dataDir . '/image_' . ($num + 1000) . '.bmp';
        }
        if (!is_file($file)) {
            return;
        }
        $this->drawBmp($file, $r[0], $r[1]);
    }

    /** Draws an 8-bit BMP of the Mac palette (converted pictures) at ($x, $y) of the current port. */
    public function drawBmp(string $file, int $x, int $y, bool $remap = false): void
    {
        [$w, $h, $pal, $pix] = Bmp::decode((string)file_get_contents($file));
        if ($remap) {
            $map = '';
            for ($i = 0; $i < 256; $i++) {
                $c = $pal[$i] ?? [0, 0, 0];
                $map .= chr(Mac::color2Index($c[0] * 257, $c[1] * 257, $c[2] * 257));
            }
            $pix = strtr($pix, Mac::IDENT, $map);
        }
        $src = new Port($w, $h);
        $src->rows = str_split($pix, $w);
        $this->curPort->copyFrom($src, 0, 0, $w, $h, $x, $y);
    }

    // ---- QuickDraw -------------------------------------------------------------------------------

    public function RGBForeColor(int $r, int $g, int $b): void
    {
        $this->qdFore = Mac::color2Index($r, $g, $b);
    }

    public function ForeColorBlack(): void
    {
        $this->qdFore = Mac::BLACK;
    }

    public function ForeColorWhite(): void
    {
        $this->qdFore = Mac::WHITE;
    }

    public function DoPenSize(int $x, int $y): void
    {
        $this->qdPenW = $x;
        $this->qdPenH = $y;
    }

    public function PenNormal(): void
    {
        $this->qdPenW = $this->qdPenH = 1;
        $this->qdPat = 'black';
        $this->qdPatOr = false;
    }

    /** PaintRect with the pen colour and pattern */
    public function DoPaintRect(array $r): void
    {
        $this->paint($r, $this->qdFore);
    }

    /** EraseRect: background colour (white) */
    public function DoEraseRect(array $r): void
    {
        $this->curPort->fill($r[0], $r[1], $r[2], $r[3], $this->qdBack);
    }

    public function DoFrameRect(array $r): void
    {
        [$l, $t, $rr, $b] = $r;
        $pw = $this->qdPenW;
        $ph = $this->qdPenH;
        $this->paint([$l, $t, $rr, $t + $ph], $this->qdFore);
        $this->paint([$l, $b - $ph, $rr, $b], $this->qdFore);
        $this->paint([$l, $t, $l + $pw, $b], $this->qdFore);
        $this->paint([$rr - $pw, $t, $rr, $b], $this->qdFore);
    }

    private function paint(array $r, int $color): void
    {
        $port = $this->curPort;
        if ($this->qdPat === 'gray') {
            // 50% pattern; with patOr only its set pixels are drawn, else the others get the background
            $l = max(0, $r[0]);
            $rr = min($port->w, $r[2]);
            for ($y = max(0, $r[1]); $y < min($port->h, $r[3]); $y++) {
                for ($x = $l; $x < $rr; $x++) {
                    if ((($x + $y) & 1) === 0) {
                        $port->rows[$y][$x] = chr($color);
                    } elseif (!$this->qdPatOr) {
                        $port->rows[$y][$x] = chr($this->qdBack);
                    }
                }
            }
            $port->dirty = true;
            return;
        }
        $port->fill($r[0], $r[1], $r[2], $r[3], $color);
    }

    public function DoMoveTo(int $x, int $y): void
    {
        $this->qdPenX = $x;
        $this->qdPenY = $y;
    }

    public function DoLineTo(int $x, int $y): void
    {
        $this->drawLine($this->qdPenX, $this->qdPenY, $x, $y);
        $this->qdPenX = $x;
        $this->qdPenY = $y;
    }

    public function DoLine(int $dx, int $dy): void
    {
        $this->DoLineTo($this->qdPenX + $dx, $this->qdPenY + $dy);
    }

    /** A line with the pen (QuickDraw: the pen hangs below and right of the path) */
    private function drawLine(int $x0, int $y0, int $x1, int $y1): void
    {
        $dx = abs($x1 - $x0);
        $dy = -abs($y1 - $y0);
        $sx = $x0 < $x1 ? 1 : -1;
        $sy = $y0 < $y1 ? 1 : -1;
        $err = $dx + $dy;
        $port = $this->curPort;
        $c = chr($this->qdFore);
        $pw = $this->qdPenW;
        $ph = $this->qdPenH;
        while (true) {
            for ($j = 0; $j < $ph; $j++) {
                $yy = $y0 + $j;
                if ($yy < 0 || $yy >= $port->h) {
                    continue;
                }
                for ($i = 0; $i < $pw; $i++) {
                    $xx = $x0 + $i;
                    if ($xx >= 0 && $xx < $port->w) {
                        $port->rows[$yy][$xx] = $c;
                    }
                }
            }
            if ($x0 === $x1 && $y0 === $y1) {
                break;
            }
            $e2 = 2 * $err;
            if ($e2 >= $dy) {
                $err += $dy;
                $x0 += $sx;
            }
            if ($e2 <= $dx) {
                $err += $dx;
                $y0 += $sy;
            }
        }
        $port->dirty = true;
    }

    /** ScrollRect: moves the content of a rectangle of the current port */
    public function DoScrollRect(array $r, int $dh, int $dv): void
    {
        $port = $this->curPort;
        $copy = new Port($r[2] - $r[0], $r[3] - $r[1]);
        $copy->copyFrom($port, $r[0], $r[1], $r[2], $r[3], 0, 0);
        $clip = $this->qdClip;
        $port->fill($r[0], $r[1], $r[2], $r[3], $this->qdBack);
        $dest = new Port($port->w, $port->h);
        $dest->rows = $port->rows;
        $dest->copyFrom($copy, 0, 0, $copy->w, $copy->h, $r[0] + $dh, $r[1] + $dv);
        // only inside the rectangle
        for ($y = max(0, $r[1]); $y < min($port->h, $r[3]); $y++) {
            $port->rows[$y] = substr_replace($port->rows[$y], substr($dest->rows[$y], $r[0], $r[2] - $r[0]), $r[0], $r[2] - $r[0]);
        }
        $this->qdClip = $clip;
        $port->dirty = true;
    }
}
