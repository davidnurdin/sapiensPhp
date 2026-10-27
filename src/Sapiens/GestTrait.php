<?php

namespace App\Sapiens;

/**
 * SAPGEST.C: flint knapping (cursil), the character (defsap), the density of the world (DEF3D), the
 * start of an adventure, load / save, the "SAPIENS" menu (trgest), making a weapon (trfab).
 *
 * The knapping game keeps its state in the pixels of Ecran1 (lit_sil / ecrit_sil), like the original.
 * Saves: the original byte format ("GOD" + the soma bytes XOR Random() + checksum + "MAGIC" lines),
 * base64 in the options (key sapiens_save_<slot>, 3 slots). Mac dialogs are in-game screens.
 */
trait GestTrait
{
    private const NB_LIGNE_SILEX = 200;
    private const NB_COL_SILEX = 300;
    private const COULEUR_FOND_NUCLEUS = 0x000;
    private const NB_COUL_SILEX = 8;
    private const PRECISION_SILEX = 300;
    private const CAR_UTILE = 180;
    private const NB_JAUGES_3D = 5;
    private const NB_NUAGES_MAX = 100 * 20;
    private const NB_OISEAUX_MAX = 20 * 20;
    private const NB_ROCHERS_MAX = 50000;
    private const NB_ARBRES_MAX = 100000;
    private const NB_MAX_HERBE_AV_PLAN = 100;
    private const GEST_SLOTS = 3;

    private const FORSY1 = [0, 0, 0, 0, 1, 1, 2, 3, 4, 4,
        6, 6, 7, 7, 8, 8, 9, 9, 10, 10,
        11, 11, 12, 12, 13, 13, 14, 14, 15, 15,
        16, 16, 16, 16, 17, 17, 18, 18, 18, 18,
        19, 19, 20, 20, 20, 20, 21, 21, 22, 22,
        22, 22, 22, 22, 23, 23, 23, 23, 23, 23,
        24, 24, 24, 24, 24, 24, 24, 24, 23, 23,
        23, 23, 23, 23, 22, 22, 22, 22, 21, 21,
        20, 19, 18, 17, 16, 15, 14, 12, 10, 8,
        6, 5, 3, 1, 0, 0, 0, 0, 0, 0];
    private const FORSY2 = [0, 0, 0, 2, 4, 5, 6, 7, 8, 8,
        8, 9, 10, 10, 11, 11, 12, 13, 14, 14,
        15, 15, 16, 16, 17, 17, 18, 18, 19, 19,
        20, 20, 21, 21, 22, 22, 23, 23, 24, 24,
        25, 25, 26, 26, 27, 27, 28, 28, 28, 28,
        29, 29, 29, 29, 30, 30, 30, 30, 30, 30,
        30, 30, 31, 31, 32, 32, 32, 32, 32, 32,
        32, 32, 32, 32, 32, 32, 32, 32, 32, 32,
        32, 32, 31, 31, 30, 29, 28, 26, 24, 22,
        20, 16, 12, 6, 0, 0, 0, 0, 0, 0];
    private const FORMONDE = [50, 50, 50, 50, 49, 49, 49, 49, 48, 48,
        47, 47, 47, 46, 46, 46, 46, 45, 45, 44,
        43, 43, 42, 42, 41, 40, 39, 39, 38, 38,
        37, 37, 36, 35, 34, 34, 33, 33, 32, 32,
        31, 30, 29, 28, 27, 26, 25, 25, 24, 24,
        23, 23, 22, 21, 20, 20, 19, 19, 18, 18,
        17, 17, 16, 16, 15, 15, 15, 15, 14, 14,
        13, 13, 12, 12, 11, 11, 10, 10, 9, 9,
        8, 8, 7, 7, 6, 6, 5, 5, 4, 4,
        3, 3, 2, 2, 1, 1, 0, 0, 0, 0];
    /** Colours of the nucleus, dark to light (16-bit RGB) */
    private const TAB_COUL_SILEX_RGB = [
        [0x0000, 0x0000, 0x0000],
        [0x3b00, 0x1b00, 0x1200],
        [0x6d00, 0x4900, 0x0000],
        [0x9200, 0x6d00, 0x0000],
        [0xB600, 0x9200, 0x0000],
        [0xDB00, 0x9200, 0x2400],
        [0xDB00, 0xB600, 0x0000],
        [0xFD00, 0xE700, 0x0000],
    ];
    /** thickness -> colour */
    private const EPAISS = [
        0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 0, 0, 0, 0,
        1, 1, 1, 1, 1, 2, 2, 2, 2, 3,
        3, 3, 3, 4, 4, 4, 4, 5, 5, 5,
        5, 6, 6, 6, 6, 7, 7, 7, 7, 7];
    /** char * traps[7] (cheat names) */
    private const TRAPS = ['CHAUVET', 'GROTTE', 'MUSICS', 'DETAIL', 'AUROCH', 'MYRIAD', 'CAT   '];

    /** @var int[] forsyl: the shape to obtain (FORSY1 or FORSY2) */
    private array $forsyl = self::FORSY1;
    /** @var array{0:int,1:int,2:int,3:int} the "next" arrow: Rect {top,left,bottom,right} = {80,264,112,296} */
    private array $AireSuite = [320 - 16 - 32 - 8, 100 - 20, 320 - 16 - 8, 100 - 20 + 32];
    /** @var int[] palSilex: system indexes of the colours of the nucleus */
    public array $palSilex = [0, 0, 0, 0, 0, 0, 0, 0];
    /** @var array<int, int> system index -> colour of the nucleus (first match, like the C loop) */
    private array $silexIndex = [];
    /** the slot of the last load / save */
    private int $gestSlot = 0;

    private function DEHORS(): int
    {
        return $this->mobBambou + 20;
    }

    private function FRAPPE(): int
    {
        return $this->mobBambou + 21;
    }

    /** plot_ecr: a coloured point on the current port (ST line, column, ST colour) */
    private function plot_ecr(int $y, int $x, int $couleur): void
    {
        [$r, $g, $b] = $this->stRgb($couleur);
        $this->RGBForeColor($r, $g, $b);
        $this->DoPenSize(1, 1);
        $this->DoMoveTo($x * 2, $y * 2);
        $this->DoLine(1, 0);
        $this->DoPenSize(1, 1);
        $this->qdFore = Mac::BLACK;
    }

    /** CvtCouleurStEnRgb */
    private function stRgb(int $val): array
    {
        return [(($val & 0x700) >> 8) * 9362, (($val & 0x070) >> 4) * 9362, ($val & 0x007) * 9362];
    }

    /** lit_sil: the colour (0..7) of a point of the nucleus, read on Ecran1 */
    private function lit_sil(int $y, int $x): int
    {
        $index = $this->GetIndexPixBrt($this->Ecran1, $x + 106, $y);
        if (isset($this->silexIndex[$index])) {
            return $this->silexIndex[$index];
        }
        return 0;
    }

    /** ecrit_sil: writes a colour (0..7) of the nucleus on Ecran1 */
    private function ecrit_sil(int $y, int $x, int $couleur): void
    {
        $this->PlotIndexPixBrt($this->Ecran1, $x + 106, $y, $this->palSilex[$couleur]);
    }

    public function cat(): void
    {
    }

    /** TRAPSURE: reserved for the initiated (names typed in getnom) */
    public function trapsure(int $quoi): \Generator
    {
        $this->flefba = 0;
        if ($quoi > 0) {
            $this->comment(114 + $quoi);
        }
        switch ($quoi) {
            case 0:
                yield from $this->DEF3D();
                break;
            case 1:
                $this->xj = 22528;
                $this->yj = 21504;
                break;
            case 2:
                yield from $this->sceveu(11);
                break;
            case 3:
                yield from $this->sceveu(13);
                break;
            case 4:
                $this->ajoutl($this->liste1, 22);
                $this->ajoutl($this->liste1, 25);
                $this->ajoutl($this->liste1, 46);
                break;
            case 5:
                yield from $this->sceveu(12);
                break;
            case 6:
                $this->cat();
                break;
        }
    }

    /** TABDRT: right edge of the nucleus on a line */
    private function tabdrt(int $pos): int
    {
        $i = self::NB_COL_SILEX + 1;
        while (!$this->lit_sil($pos, --$i) && $i > self::NB_COL_SILEX / 2) {
        }
        return $i;
    }

    /** TABGAU: left edge of the nucleus on a line */
    private function tabgau(int $pos): int
    {
        $i = 0;
        while (!$this->lit_sil($pos, $i++) && $i < self::NB_COL_SILEX / 2) {
        }
        return $i;
    }

    /** VALSYL: the error between the nucleus and the shape to obtain */
    private function valsyl(): int
    {
        $ecart = 0;
        for ($i = 0; $i < self::NB_LIGNE_SILEX; $i += 2) {
            $f = $this->forsyl[intdiv($i, 2)];
            $trav1 = abs(self::ss(intdiv(self::NB_COL_SILEX, 2) - 2 * $f - $this->tabgau($i)));
            $trav2 = abs(self::ss(intdiv(self::NB_COL_SILEX, 2) + 2 * $f - $this->tabdrt($i)));
            $ecart += $trav1 + $trav2;
        }
        return intdiv($ecart, 3) & 0xFFFF;
    }

    private function AfficheSuite(): void
    {
        $this->affomb($this->mobFleches + 19, $this->AireSuite[1], $this->AireSuite[0], 2, 2, true);
    }

    private function MouseDansSuite(int $posx, int $posy): bool
    {
        return self::PtInRect([$posx, $posy], $this->AireSuite);
    }

    /** SILEX: the nucleus and the ideal shape over it */
    private function TraceSilex(): void
    {
        $gbak = $this->curPort;
        $this->curPort = $this->debecr;
        $this->eff_sour();
        $this->tfrvirt();
        for ($i = 0; $i < self::NB_LIGNE_SILEX; $i += 4) {
            $f = $this->forsyl[intdiv($i, 2)];
            if ($f) {
                $this->plot_ecr(intdiv($i, 2) + 1, 160 + $f, 0x007);
                $this->plot_ecr(intdiv($i, 2) + 1, 160 - $f, 0x007);
            }
        }
        $this->AfficheSuite();
        $this->TfrEcr(null);
        $this->curPort = $gbak;
    }

    /** FabriqueNucleus: a raw nucleus on Ecran1 */
    private function FabriqueNucleus(): void
    {
        $this->effima(self::COULEUR_FOND_NUCLEUS);
        $half = intdiv(self::NB_COL_SILEX, 2);
        $delta1 = 60;
        $kk = $this->myRandom(3, 16);
        for ($y = 0; $y < self::NB_LIGNE_SILEX; $y++) {
            $delta1 += $this->myRandom(-1, 2);
            if ($delta1 < 0) {
                $delta1 = 0;
            }
            $posTheo = intdiv($this->forsyl[intdiv($y, 2)] + ($this->forsyl[intdiv($y, 2) + 1] ?? 0), 2);
            $pos1 = $half - $posTheo - $delta1;
            $delta1 += $this->myRandom(-1, 2);
            if ($delta1 < 0) {
                $delta1 = 0;
            }
            $pos2 = $half + $posTheo + $delta1;
            if ($pos1 < 0) {
                $pos1 = 0;
            }
            if ($pos2 > self::NB_COL_SILEX) {
                $pos2 = self::NB_COL_SILEX;
            }
            $yy = $y < intdiv(self::NB_LIGNE_SILEX, 2) ? $y : self::NB_LIGNE_SILEX - $y;
            for ($x = $pos1; $x <= $half; $x++) {
                $couleur = 1 + intdiv(($x - $pos1) * $yy + $this->Rnd() * $kk, 1000);
                if ($couleur >= self::NB_COUL_SILEX) {
                    $couleur = self::NB_COUL_SILEX - 1;
                }
                $this->ecrit_sil($y, $x, $couleur);
            }
            for ($x = $pos2; $x > $half; $x--) {
                $couleur = 1 + intdiv(($pos2 - $x) * $yy + $this->Rnd() * $kk, 1000);
                if ($couleur >= self::NB_COUL_SILEX) {
                    $couleur = self::NB_COUL_SILEX - 1;
                }
                $this->ecrit_sil($y, $x, $couleur);
            }
        }
    }

    /** AppliqueNlleEpaiss: a new thickness if thinner than the old one */
    private function AppliqueNlleEpaiss(int $y, int $x, int $epaiss): void
    {
        $anc = $this->lit_sil($y, $x);
        if ($anc < $epaiss || $epaiss === 0) {
            $this->ecrit_sil($y, $x, $epaiss);
        }
    }

    /** One line of the flake (left or right) */
    private function eclatLigne(int $ligne, int $onde, int $forcef, int $posimp, bool $senson): void
    {
        if ($senson) {
            $a = self::ss(intdiv(self::FORMONDE[$onde] * $forcef, 256) + $posimp);
            $distact = self::ss($a - $this->tabgau($ligne));
            if ($distact > 0) {
                $pas = intdiv(7400, $distact + 1);
                if ($pas === 0) {
                    $pas = 1;
                }
                for ($i = 7400; $i >= 0; $i -= $pas, $a--) {
                    $this->AppliqueNlleEpaiss($ligne, $a, self::EPAISS[intdiv($i, 100)]);
                }
            }
        } else {
            $a = self::ss($posimp - intdiv(self::FORMONDE[$onde] * $forcef, 256));
            $distact = self::ss($this->tabdrt($ligne) - $a);
            if ($distact > 0) {
                $pas = intdiv(7400, $distact);
                if ($pas === 0) {
                    $pas = 1;
                }
                for ($i = 7400; $i >= 0; $i -= $pas, $a++) {
                    $this->AppliqueNlleEpaiss($ligne, $a, self::EPAISS[intdiv($i, 100)]);
                }
            }
        }
    }

    /**
     * AppliqueImpact: a strike at (posxf, posyf) with the force forcef.
     * @return int the sound to play
     */
    private function AppliqueImpact(int $posxf, int $posyf, int $forcef): int
    {
        $okbruit = 0;
        $cass = 0;
        $half = intdiv(self::NB_COL_SILEX, 2);
        $posxf *= 2;
        $posyf *= 2;
        if ($posxf > $this->tabdrt($posyf) || $posxf < $this->tabgau($posyf)) {
            $this->TraceSilex();
            return $okbruit;
        }
        $senson = $posxf < $half;
        $tfr = intdiv($forcef, 16) & 0xFFFF;
        $penet = intdiv($forcef * 50, 256);
        if ($senson) {
            $tfr = ($tfr + $posxf) & 0xFFFF;
            if ($tfr < $penet) {
                $tfr = $penet & 0xFFFF;
            }
            if ($tfr > $half) {
                $cass++;
            }
            $posimp = self::ss($tfr - $penet);
            $distcb = ($penet + ($posimp - $this->tabgau($posyf))) & 0xFFFF;
        } else {
            $tfr = ($posxf - $tfr) & 0xFFFF;
            if ($tfr < $half) {
                $cass++;
            }
            $posimp = self::ss($tfr + $penet);
            $distcb = ($penet - ($posimp - $this->tabdrt($posyf))) & 0xFFFF;
        }
        if (intdiv(($forcef - 1) * 48, 256) > $distcb) {
            if ($cass) {
                // broken: the top or the bottom of the flint goes away
                $okbruit = 3;
                if ($posyf < intdiv(self::NB_LIGNE_SILEX, 2)) {
                    for ($i = $posyf + 4; $i >= 0 && $i < self::NB_LIGNE_SILEX; $i--) {
                        for ($j = 0; $j < self::NB_COL_SILEX; $j++) {
                            $this->AppliqueNlleEpaiss($i, $j, 0);
                        }
                    }
                } else {
                    for ($i = $posyf - 5; $i >= 0 && $i < self::NB_LIGNE_SILEX; $i++) {
                        for ($j = 0; $j < self::NB_COL_SILEX; $j++) {
                            $this->AppliqueNlleEpaiss($i, $j, 0);
                        }
                    }
                }
            } else {
                // a flake
                $paslec = (($forcef ^ 255) * 8 + 128) & 0xFFFF;
                $yact = $posyf & 0xFFFF;
                $calc = ($posyf + 1) & 0xFFFF;
                $onde = 0;
                $trav1 = 0;
                while ($onde < 100) {
                    if ($yact < self::NB_LIGNE_SILEX) {
                        $this->eclatLigne($yact, $onde, $forcef, $posimp, $senson);
                    }
                    if ($calc < self::NB_LIGNE_SILEX) {
                        $this->eclatLigne($calc, $onde, $forcef, $posimp, $senson);
                    }
                    $calc = ($calc + 1) & 0xFFFF;
                    $yact = ($yact - 1) & 0xFFFF;
                    $trav1 += $paslec & 255;
                    $onde = ($onde + ($trav1 > 255 ? 1 : 0) + ($paslec >> 8)) & 0xFFFF;
                    $trav1 &= 255;
                }
                $okbruit = 4;
            }
        } else {
            // not strong enough
            $okbruit = 5;
        }
        $this->TraceSilex();
        return $okbruit;
    }

    /**
     * CURSIL: knapping (regb 0 = spear shape, 1 = axe shape).
     * @return \Generator<int, null, mixed, int> the error (valsyl)
     */
    private function cursil(int $regb): \Generator
    {
        $gbak = $this->curPort;
        $this->curPort = $this->debecr;
        $this->silexIndex = [];
        for ($i = 0; $i < self::NB_COUL_SILEX; $i++) {
            $c = self::TAB_COUL_SILEX_RGB[$i];
            $this->palSilex[$i] = Mac::color2Index($c[0], $c[1], $c[2]);
            if (!isset($this->silexIndex[$this->palSilex[$i]])) {
                $this->silexIndex[$this->palSilex[$i]] = $i;
            }
        }
        $this->curPort = $gbak;
        $this->forsyl = $regb === 0 ? self::FORSY1 : self::FORSY2;
        $this->FabriqueNucleus();
        $this->flefba = 0;
        $this->bande(1);
        $this->TraceSilex();
        while (true) {
            do {
                $this->vq_mouse($this->bouton, $this->posxf, $this->posyf);
                if ($this->bouton) {
                    yield;
                }
            } while ($this->bouton);
            do {
                yield;
                $this->vq_mouse($this->bouton, $this->posxf, $this->posyf);
                if ($this->posxf < 32 || $this->posxf > 320 || $this->posyf > 100 || $this->posyf < 2) {
                    if ($this->posyf > 100) {
                        $this->posyf = 100;
                    }
                    if ($this->posxf > 320 - 16) {
                        $this->posxf = 320 - 16;
                    }
                    $this->aff_sour($this->DEHORS(), $this->posyf, $this->posxf);
                    $this->bouton &= 2;
                } elseif ($this->MouseDansSuite($this->posxf, $this->posyf)) {
                    $this->aff_sour($this->FRAPPE(), $this->posyf, $this->posxf);
                } else {
                    $this->aff_sour($this->mobBambou + 22, $this->posyf - 25, $this->posxf);
                }
            } while (!$this->bouton);
            // the flint is accepted
            if ($this->MouseDansSuite($this->posxf, $this->posyf)) {
                $this->affomb($this->mobFleches + 20, $this->AireSuite[1], $this->AireSuite[0], 2, 2, true);
                return $this->valsyl();
            }
            $gbak = $this->curPort;
            $this->CarboniseSetPortWindow();
            $this->plot_ecr($this->posyf, $this->posxf, 0x700);
            $this->curPort = $gbak;
            if ($this->bouton & 1) {
                // the force of the strike: how long the button is held
                $t = $this->TickCount();
                $bidon = 0;
                do {
                    yield;
                    $this->vq_mouse($this->bouton, $bidon, $bidon);
                    $forcef = ($this->TickCount() - $t) * 4;
                    $this->aff_sour($this->mobBambou + 22, $this->posyf - 25 - intdiv($forcef, 8) - 2, $this->posxf + intdiv($forcef, 8) + 2);
                } while ($this->bouton && $forcef < 254);
                if ($forcef > 254) {
                    $forcef = 254;
                }
                for ($i = $forcef; $i >= 0; $i -= 32) {
                    $this->aff_sour($this->mobBambou + 22, $this->posyf - 25 - intdiv($i, 8), $this->posxf + intdiv($i, 8));
                    yield;
                }
                $okbruit = $this->AppliqueImpact($this->posxf - 85, $this->posyf - 1, $forcef) & 0xFF;
                $this->aff_sour($this->mobBambou + 22, $this->posyf - 25, $this->posxf);
                if ($okbruit) {
                    $this->bruit($okbruit);
                }
            }
        }
    }
    /**
     * GETNOM: types a name of $taille characters in the message line.
     * @return \Generator<int, null, mixed, int> the number of characters typed (0 = cancelled)
     */
    private function getnom(int $mess, string &$position, int $taille): \Generator
    {
        $pos = 0;
        while ($this->litclav()) {
        }
        for ($i = 0; $i < $taille; $i++) {
            $this->zoneu->elements[$i] = 32;
        }
        while (true) {
            $this->efbadi();
            $this->comment($mess);
            for ($i = 0; $i < $taille && $this->zoneu->elements[$i] !== 32; $i++) {
                $this->affcarmess($this->zoneu->elements[$i]);
            }
            $this->affcarmess(2);
            $this->TfrBadAss();
            do {
                yield;
                $car = $this->litclav();
            } while (!$car);
            if ($car === 13) {
                // the names of the initiated
                for ($i = 0; $i < 7; $i++) {
                    $oktrap = 1;
                    for ($j = 0; $j < 6; $j++) {
                        if ((ord(self::TRAPS[$i][$j]) & 223) !== ($this->zoneu->elements[$j] & 223)) {
                            $oktrap = 0;
                        }
                    }
                    if ($oktrap) {
                        yield from $this->trapsure($i);
                        $pos = 0;
                    }
                }
                if ($pos) {
                    $position = '';
                    for ($i = 0; $i < $taille; $i++) {
                        $position .= chr($this->zoneu->elements[$i]);
                    }
                }
                $this->efbadass();
                $this->TfrBadAss();
                return $pos;
            }
            if ($car > 32 && $car <= ord('z') && !($car >= ord('0') && $car <= ord('9'))) {
                if ($pos < $taille) {
                    $this->zoneu->elements[$pos++] = $car;
                }
            } elseif ($car === 8 && $pos) {
                $this->zoneu->elements[--$pos] = 32;
            }
        }
    }

    /** DebutAventure: the 3D world, the title of the adventure (generator: about 60 frames) */
    public function DebutAventure(bool $isFirst): \Generator
    {
        $this->primsap();
        $this->generique(false);
        if ($isFirst) {
            $this->DefautDecor3d();
        } else {
            $this->InitDecor3d();
        }
        $this->CreeDecor3d(false);
        $this->effvirt(0x000);
        $this->effima(0x000);
        $this->heure = 10;
        $this->chrono(0);
        $this->affich($this->dzeta);
        $this->tfrvirt();
        $this->flefba = 1;
        $this->InitGenerique();
        $this->generique(false);
        $this->TfrEcr(null);
        $this->Frame3d(0);
        $this->efbadi();
        $this->comment(67);
        for ($i = 0; $i < 60; $i++) {
            $posTimer = $this->sapiensTimer;
            $this->chrono(1);
            $this->chrono(0);
            $this->chrono(0);
            $this->affich($this->dzeta);
            $this->tfrima();
            if ($this->getkey(0) & (32 + 16)) {
                $i = 111;
            }
            $this->flefba = 0;
            if ($i === 25) {
                $this->comment(68);
            }
            if ($i === 50) {
                $this->comment(69);
            }
            do {
                yield;
            } while ($posTimer + 30 >= $this->sapiensTimer);
        }
        $this->comment(70);
        $this->MenuFenetres(1);
        $this->efbadi();
        $this->comment(132);
    }

    /** PRIMSAP: the first definitions of the adventure */
    public function primsap(): void
    {
        $this->scenar = $this->jour_act = 1;
        $this->scenpr = 20;
        $this->fdialo = $this->pos_evt = $this->presence = $this->viecopain = $this->jour_dsk = 0;
        $this->dervue = self::VUE_AUCUNE;
        $this->prm($this->sap2, 0);
        $this->prm($this->sap3, 0);
        $this->prm($this->sap4, 0);
        $this->mort = 4;
        $this->faim = $this->soif = $this->fatig = $this->bless = 255;
        $this->xj = 0x5800;
        $this->yj = 0x5400;
        $this->dzeta = 768;
        $this->zoom = 120;
        $this->heure = 1;
        $this->zj = 40;
        $this->liste1->taille = 9;
        for ($i = 0; $i < 9; $i++) {
            $this->liste1->elements[$i] = $this->listdep[$i];
        }
        // IsEnreg(): a club instead of the axe
        $this->liste1->elements[0] = 22;
        for ($i = 0; $i < 4; $i++) {
            $this->enetoi[$i] = $this->tenerv[$i];
            $this->tagress[$i] = $this->agrdep[$i];
        }
        // the 4 chiefs come back
        for ($i = 0; $i < 4; $i++) {
            $this->destine[$i][0] = 1;
        }
        $this->liste4->taille = 0;
    }

    /** AireJauge: the whole gauge (ST coordinates) */
    private function AireJauge(int $i): array
    {
        return [114, 6 + 14 * $i, 114 + intdiv($this->maxparam[$this->sexe][$i], 2) + 2, 16 + 14 * $i];
    }

    /** AireValJauge: the value of a gauge (ST coordinates) */
    private function AireValJauge(int $i): array
    {
        return [115, 7 + 14 * $i, 115 + intdiv($this->param[$i], 2), 15 + 14 * $i];
    }

    private function AfficheCadreJauge(int $i): void
    {
        $r = $this->AireJauge($i);
        $this->qdFore = Mac::BLACK;
        $this->DoFrameRect([$r[0] * 2, $r[1] * 2, $r[2] * 2, $r[3] * 2]);
    }

    /** JAUGE: a gauge in debecr, copied to the window if $tfr */
    private function jauge(int $i, bool $tfr): void
    {
        $gbak = $this->curPort;
        $this->curPort = $this->debecr;
        $rval = $this->AireValJauge($i);
        $this->AfficheCadreJauge($i);
        $this->boxf($rval[1], $rval[0], $rval[3], $rval[2], 2);
        $r = $this->AireJauge($i);
        if (intdiv($this->param[$i], 2) < intdiv($this->maxparam[$this->sexe][$i], 2)) {
            $this->boxf($r[1] + 1, $rval[2], $r[3] - 1, $r[2] - 1, 14);
            if ($this->param[$i]) {
                $this->boxf($r[3] - 2, $r[0] + 1, $r[3] - 1, $rval[2] - 1, 9);
                $this->boxf($r[1] + 1, $rval[2] - 1, $r[3] - 1, $rval[2], 9);
            }
        }
        $this->curPort = $gbak;
        if ($tfr) {
            $this->TfrEcr([$r[0] * 2, $r[1] * 2, $r[2] * 2, $r[3] * 2]);
        }
    }

    /** PRIMJAUGE: the gauges of the character */
    public function primjauge(int $jaugeCredit): void
    {
        $app = $this->GetApp();
        $this->dervue = self::VUE_AUCUNE;
        $this->effima(0x050);
        $this->tfrvirt();
        for ($k = 0; $k < 5; $k++) {
            $this->Gotoxy(6, 1 + 2 * $k);
            $this->motlst2((string)$app[$k]);
        }
        if ($jaugeCredit) {
            $this->Gotoxy(6, 11);
            $this->motlst2((string)$app[5]);
        }
        $gbak = $this->curPort;
        $this->curPort = $this->debecr;
        for ($i = 0; $i < 5 + $jaugeCredit; $i++) {
            $this->jauge($i, false);
        }
        $this->curPort = $gbak;
        $this->TfrEcr(null);
    }

    /** Waits for a click, the mouse icon following the pointer */
    private function gestAttendsClic(): \Generator
    {
        do {
            yield;
            $this->vq_mouse($this->bouton, $this->sposx, $this->sposy);
            $this->aff_sour($this->FRAPPE(), $this->sposy, $this->sposx);
        } while (!$this->bouton);
    }

    /** DEFSAP: a new character (name, sex, gauges, look), then a new adventure */
    public function defsap(): \Generator
    {
        $this->sapvit = 0;
        $nom = '';
        $taille = yield from $this->getnom(88, $nom, 8);
        if (!$taille) {
            return;
        }
        $this->flefba = 0;
        $this->nomjoueur = substr($nom, 0, $taille);
        for ($i = 0; $i < 5; $i++) {
            $this->param[$i] = 130;
        }
        $this->param[5] = 20;
        $this->generique(true);
        $this->comment(89);
        $car = 0;
        $this->reset_clav();
        while ($car !== $this->HOMME && $car !== $this->FEMME) {
            yield;
            $car = $this->litclav() & 223;
        }
        $this->affcarmess($car);
        $this->TfrBadAss();
        if ($car === $this->HOMME) {
            $this->sexe = 1;
            $this->sap1->cross[self::FORME_BUSTE] = 0;
            $this->sap1->cross[self::FORME_TETE] = 0;
        } else {
            $this->sexe = 0;
            $this->sap1->cross[self::FORME_BUSTE] = 8;
            $this->sap1->cross[self::FORME_TETE] = 12;
        }
        $this->pal_chron = $this->palhaute;
        $this->palhaute = $this->pal2;
        $this->primjauge(1);
        $this->AfficheSuite();
        $cont = 1;
        $pos = 0;
        $this->sposx = $this->dsposx = 160;
        $this->sposy = $this->dsposy = 50;
        // the gauges
        while ($cont) {
            yield from $this->gestAttendsClic();
            if ($this->MouseDansSuite($this->sposx, $this->sposy)) {
                $this->affomb($this->mobFleches + 20, $this->AireSuite[1], $this->AireSuite[0], 2, 2, true);
                $cont = 0;
                $this->agilite = $this->param[0] < 223 ? $this->param[0] + 32 : 255;
                $this->force = $this->param[1];
                $this->charisme = $this->param[2];
                $this->vie = $this->param[3];
                $this->resistance = $this->param[4];
                while ($this->getkey(0)) {
                    yield;
                }
            } else {
                $pt = [$this->sposx, $this->sposy];
                for ($i = 0; $i < 5; $i++) {
                    $r = $this->AireJauge($i);
                    if (self::PtInRect($pt, $r)) {
                        $pos = $i;
                        $this->affomb($this->MOB_DESIGNE(), $r[1] - 4, 86, 0, 0, true);
                        do {
                            $rval = $this->AireValJauge($pos);
                            if ($this->sposx > $rval[2] && $this->param[5] && $this->param[$pos] < $this->maxparam[$this->sexe][$pos]) {
                                $this->param[5]--;
                                $this->param[$pos]++;
                            }
                            if ($this->sposx < $rval[2] && $this->param[5] < 254 && $this->param[$pos]) {
                                $this->param[5]++;
                                $this->param[$pos]--;
                            }
                            $this->vq_mouse($this->bouton, $this->sposx, $this->sposy);
                            $this->jauge($pos, true);
                            $this->jauge(5, true);
                            $this->aff_sour($this->FRAPPE(), $this->sposy, $this->sposx);
                            yield;
                        } while ($this->bouton);
                        $this->primjauge(1);
                        $this->AfficheSuite();
                        break;
                    }
                }
            }
        }
        // the look
        $this->eff_sour();
        $cont = 1;
        $pos = 0;
        $this->prm($this->sap1, -1);
        $this->effima(0x050);
        $this->tfrvirt();
        $this->tfrima();
        $this->flefba = 0;
        $this->eff_sour();
        while ($cont) {
            $this->sap1->colonne = 28;
            $this->sap1->sens = 1;
            $this->effima(0x050);
            $this->mvtsap($this->sap1);
            $this->tfrdemi();
            $this->tfrvirt();
            $this->AfficheSuite();
            $this->affomb($this->MOB_DESIGNE(), ($pos * 2 + 6) * 7 + 1, 100, 4, 4, true);
            yield from $this->gestAttendsClic();
            if ($this->MouseDansSuite($this->sposx, $this->sposy)) {
                $this->affomb($this->mobFleches + 20, $this->AireSuite[1], $this->AireSuite[0], 2, 2, true);
                $cont = 0;
            } else {
                $pos = $this->sposy > 53 ? 1 : 0;
                $this->dsposy = $this->sposy;
                $this->dsposx = $this->sposx;
                if ($this->bouton & 1) {
                    if ($pos) {
                        $b = $this->sap1->cross[self::FORME_BUSTE] + 2;
                        if ($this->sexe) {
                            if ($b >= 8) {
                                $b = 0;
                            }
                        } elseif ($b >= 16) {
                            $b = 8;
                        }
                        $this->sap1->cross[self::FORME_BUSTE] = $b & 0xFF;
                    } else {
                        $t = $this->sap1->cross[self::FORME_TETE] + 2;
                        if ($this->sexe) {
                            if ($t > 6) {
                                $t = 0;
                            }
                        } elseif ($t > 22) {
                            $t = 12;
                        }
                        $this->sap1->cross[self::FORME_TETE] = $t & 0xFF;
                    }
                    do {
                        yield;
                        $this->vq_mouse($this->bouton, $this->sposx, $this->sposy);
                    } while ($this->bouton & 1);
                }
            }
        }
        $this->eff_sour();
        $this->palhaute = $this->pal_chron;
        yield from $this->DebutAventure(false);
    }

    /** AIREJAUGE3d (ST coordinates) */
    private function AIREJAUGE3d(int $i): array
    {
        return [75, 7 + 14 * $i, 75 + self::CAR_UTILE, 15 + 14 * $i];
    }

    private function JAUGE3d(int $i, float $val): void
    {
        $r = $this->AIREJAUGE3d($i);
        $v = (int)($r[0] + self::CAR_UTILE * $val);
        $this->boxf($r[1] - 1, $r[0] - 1, $r[3] + 1, $r[2] + 1, 16);
        $this->boxf($r[1], $r[0], $r[3], $v, 14);
        $this->boxf($r[1], $v, $r[3], $r[2], 4);
    }

    /** JAUGES3D: the gauges of the density of the world */
    private function JAUGES3D(): void
    {
        $gbak = $this->curPort;
        $this->curPort = $this->debecr;
        $this->boxf(0, 32, 101, 287, 5);
        // icons: cloud, bird, rock, tree, grass
        $icons = [[$this->mob3D + 7, 80, 64, 0], [$this->mob3D + 28, 120, 16, 0], [$this->mob3D + 34, 110, 26, -2], [$this->mob3D + 10, 110, 32, -4], [$this->MOB_HERBE(), 110, 32, -4]];
        foreach ($icons as $i => [$mob, $left, $w, $inset]) {
            $r = $this->AIREJAUGE3d($i);
            $r = [$left, $r[1] * 2 + $inset, $left + $w, $r[3] * 2 - $inset];
            $this->Affmob3d($mob, $r, null);
        }
        $this->AfficheSuite();
        $this->JAUGE3d(0, $this->nbNuages / self::NB_NUAGES_MAX);
        $this->JAUGE3d(1, $this->nbOiseaux / self::NB_OISEAUX_MAX);
        $this->JAUGE3d(2, $this->nbRochers / self::NB_ROCHERS_MAX);
        $this->JAUGE3d(3, $this->nbArbres / self::NB_ARBRES_MAX);
        $this->JAUGE3d(4, $this->nbHerbes / self::NB_MAX_HERBE_AV_PLAN);
        $this->TfrEcr([0, 0, 600, 400]);
        $this->curPort = $gbak;
    }

    /** DEF3D: the density of the world (clouds, birds, rocks, trees, grass) */
    public function DEF3D(): \Generator
    {
        $this->pal_chron = $this->palhaute;
        $this->palhaute = $this->pal2;
        $cont = 1;
        $this->sposx = $this->dsposx = 160;
        $this->sposy = $this->dsposy = 50;
        while ($cont) {
            $this->JAUGES3D();
            yield from $this->gestAttendsClic();
            if ($this->MouseDansSuite($this->sposx, $this->sposy)) {
                $cont = 0;
            } else {
                for ($i = 0; $i < self::NB_JAUGES_3D; $i++) {
                    $r = $this->AIREJAUGE3d($i);
                    if ($this->sposy >= $r[1] && $this->sposy <= $r[3]) {
                        do {
                            $val = ($this->sposx - $r[0]) / ($r[2] - $r[0]);
                            switch ($i) {
                                case 0: $this->nbNuages = (int)($val * self::NB_NUAGES_MAX); break;
                                case 1: $this->nbOiseaux = (int)($val * self::NB_OISEAUX_MAX); break;
                                case 2: $this->nbRochers = (int)($val * self::NB_ROCHERS_MAX); break;
                                case 3: $this->nbArbres = (int)($val * self::NB_ARBRES_MAX); break;
                                case 4: $this->nbHerbes = (int)($val * self::NB_MAX_HERBE_AV_PLAN); break;
                            }
                            $this->nbNuages = max(0, min(self::NB_NUAGES_MAX, $this->nbNuages));
                            $this->nbOiseaux = max(0, min(self::NB_OISEAUX_MAX, $this->nbOiseaux));
                            $this->nbRochers = max(0, min(self::NB_ROCHERS_MAX, $this->nbRochers));
                            $this->nbArbres = max(0, min(self::NB_ARBRES_MAX, $this->nbArbres));
                            $this->nbHerbes = max(0, min(self::NB_MAX_HERBE_AV_PLAN, $this->nbHerbes));
                            $this->vq_mouse($this->bouton, $this->sposx, $this->sposy);
                            $this->JAUGES3D();
                            $this->aff_sour($this->FRAPPE(), $this->sposy, $this->sposx);
                            yield;
                        } while ($this->bouton);
                        break;
                    }
                }
            }
        }
        $this->CreeDecor3d(true);
    }
    // ---- in-game dialogs (replace the Mac alerts, dialogs and file pickers) ----------------------

    /** Texts of the dialogs, by language, with the accent codes of the game fonts */
    private function gestTexte(string $key): string
    {
        $t = [
            'perte' => ['Abandonner l\'aventure en cours ?', 'Abandon the current adventure?', '[Abandonar la aventura?'],
            'oui' => ['Oui', 'Yes', 'Si'],
            'non' => ['Non', 'No', 'No'],
            'retour' => ['Retour', 'Back', 'Volver'],
            'charger' => ['Charger quelle aventure ?', 'Load which adventure?', '[Cargar que aventura?'],
            'sauver' => ['Sauver dans quel emplacement ?', 'Save in which slot?', '[Guardar en que lugar?'],
            'vide' => ['- vide -', '- empty -', '- vacio -'],
            'jour' => ['jour', 'day', 'dia'],
            'prefs' => ['Pr&f&rences', 'Preferences', 'Preferencias'],
            'langue' => ['Langue', 'Language', 'Idioma'],
            'bruitages' => ['Bruitages', 'Sound effects', 'Efectos'],
            'musique' => ['Musique', 'Music', 'Musica'],
            'joueur2' => ['Second joueur', 'Second player', 'Segundo jugador'],
            'autonome' => ['autonome', 'automatic', 'automatico'],
            'clavier' => ['clavier', 'keyboard', 'teclado'],
            'info' => ['SAPIENS 3.0.14', 'SAPIENS 3.0.14', 'SAPIENS 3.0.14'],
        ];
        return $t[$key][$this->langue] ?? $t[$key][1];
    }

    /**
     * A choice drawn in the view (white on blue), chosen with the mouse / a tap or the keys 1-9.
     * @param string[] $options
     * @return \Generator<int, null, mixed, int> the option chosen, -1 = Escape
     */
    private function gestChoix(string $titre, array $options): \Generator
    {
        $saveDeb = new Port(640, 400);
        $saveDeb->copyFrom($this->debecr, 0, 0, 640, 400, 0, 0);
        $saveWin = new Port(640, 400);
        $saveWin->copyFrom($this->mainWindow, 0, 0, 640, 400, 0, 0);
        $this->eff_sour();
        $this->effvirt(0x007);
        $gbak = $this->curPort;
        $this->curPort = $this->debecr;
        $this->Gotoxy(7, 1);
        $this->motlst2($titre);
        foreach ($options as $k => $o) {
            $this->Gotoxy(9, 3 + 2 * $k);
            $this->motlst2(($k + 1) . '  ' . $o);
        }
        $this->curPort = $gbak;
        $this->TfrEcr([64, 2, 574, 202]);
        $this->reset_clav();
        $choix = -2;
        // the button of the click that opened the dialog
        do {
            yield;
            $this->vq_mouse($this->bouton, $this->sposx, $this->sposy);
        } while ($this->bouton);
        while ($choix === -2) {
            yield;
            $car = $this->litclav();
            if ($car === 27) {
                $choix = -1;
            } elseif ($car >= ord('1') && $car < ord('1') + count($options)) {
                $choix = $car - ord('1');
            }
            $this->vq_mouse($this->bouton, $this->sposx, $this->sposy);
            if ($this->bouton && $this->sposx >= 32 && $this->sposx < 288 && $this->sposy < 101) {
                $ligne = intdiv($this->sposy, 7);
                foreach ($options as $k => $o) {
                    if ($ligne >= 3 + 2 * $k && $ligne <= 4 + 2 * $k) {
                        $choix = $k;
                    }
                }
            }
        }
        do {
            yield;
            $this->vq_mouse($this->bouton, $this->sposx, $this->sposy);
        } while ($this->bouton);
        $this->debecr->copyFrom($saveDeb, 0, 0, 640, 400, 0, 0);
        $this->mainWindow->copyFrom($saveWin, 0, 0, 640, 400, 0, 0);
        return $choix;
    }

    /** ConfirmePerteAventure (SAPWIND.C): yes / no */
    private function gestConfirmePerte(): \Generator
    {
        $c = yield from $this->gestChoix($this->gestTexte('perte'), [$this->gestTexte('oui'), $this->gestTexte('non')]);
        return $c === 0;
    }

    /** @return string[] the label of each save slot */
    private function gestLibellesSlots(): array
    {
        $l = [];
        for ($i = 0; $i < self::GEST_SLOTS; $i++) {
            $info = $this->prefs?->get('sapiens_save_' . $i . '_info');
            $l[] = is_string($info) && $info !== '' ? $info : $this->gestTexte('vide');
        }
        return $l;
    }

    /** DoPreferences (SAPWPREF.C): language, sounds, music, second player */
    private function gestPreferences(): \Generator
    {
        $langues = ['Fran#ais', 'English', 'Castellano'];
        while (true) {
            $options = [
                $this->gestTexte('langue') . ' : ' . $langues[$this->langue],
                $this->gestTexte('bruitages') . ' : ' . $this->gestTexte($this->IsBruitages ? 'oui' : 'non'),
                $this->gestTexte('musique') . ' : ' . $this->gestTexte($this->okmuse ? 'oui' : 'non'),
                $this->gestTexte('joueur2') . ' : ' . $this->gestTexte($this->ModeJoueur2 === self::JOUEUR2_CLAVIER ? 'clavier' : 'autonome'),
                $this->gestTexte('retour'),
            ];
            $c = yield from $this->gestChoix($this->gestTexte('prefs'), $options);
            switch ($c) {
                case 0:
                    $this->langue = ($this->langue + 1) % 3;
                    $this->InitAndCheckTexts();
                    break;
                case 1:
                    $this->IsBruitages = $this->IsBruitages ? 0 : 1;
                    break;
                case 2:
                    $this->okmuse = $this->okmuse ? 0 : 1;
                    if ($this->okmuse) {
                        $this->MusicInit($this->quelle_musique ?: 1);
                    } elseif ($this->sound !== null && method_exists($this->sound, 'stopMusic')) {
                        $this->sound->stopMusic();
                    }
                    break;
                case 3:
                    $this->ModeJoueur2 = $this->ModeJoueur2 === self::JOUEUR2_CLAVIER ? self::JOUEUR2_AUTONOME : self::JOUEUR2_CLAVIER;
                    break;
                default:
                    $this->SauvePref();
                    // the menus in the new language
                    $this->lastmen = 255;
                    $this->encadre($this->tclin[0], $this->tccol[0], $this->tcnlin[0], $this->tcncol[0]);
                    return;
            }
        }
    }

    /** Informations: version and credits in the view, and the manual (DoAide) */
    private function gestInfo(): \Generator
    {
        yield from $this->gestChoix($this->gestTexte('info'), [
            'Didier et Olivier Guillion 1986-2017',
            'Musique : Gilles Soulet',
            'Port PHP / JumPHP - GPL v3',
            $this->gestTexte('retour'),
        ]);
    }

    // ---- saves -----------------------------------------------------------------------------------

    /** The soma[] cells (SAPGLOB.C) of the save: 161 bytes, multi-byte values little-endian */
    private function gestSomaBytes(): string
    {
        $b = '';
        foreach (['soif', 'fatig', 'faim', 'mort', 'bless', 'zoom', 'agilite', 'force', 'resistance', 'charisme', 'vie', 'sexe', 'viecopain'] as $v) {
            $b .= chr($this->$v & 0xFF);
        }
        foreach ($this->sap1->cross as $c) {
            $b .= chr($c & 0xFF);
        }
        foreach ($this->sap3->cross as $c) {
            $b .= chr($c & 0xFF);
        }
        $b .= chr($this->scenar & 0xFF) . chr($this->scenpr & 0xFF) . chr($this->ciel & 0xFF) . chr($this->terre & 0xFF);
        $b .= chr($this->liste1->taille & 0xFF);
        for ($i = 0; $i < 30; $i++) {
            $b .= chr($this->liste1->elements[$i] & 0xFF);
        }
        for ($i = 0; $i < 4; $i++) {
            $b .= chr($this->tagress[$i] & 0xFF) . pack('v', $this->enetoi[$i] & 0xFFFF);
        }
        $b .= pack('v4', $this->xj & 0xFFFF, $this->yj & 0xFFFF, $this->dzeta & 0xFFFF, $this->heure & 0xFFFF);
        $b .= chr($this->sap3->vie & 0xFF);
        $b .= str_pad(substr($this->nomjoueur, 0, 8), 8, "\0");
        $b .= str_pad(substr($this->nomcopain, 0, 6), 6, "\0");
        $b .= chr($this->present[2] & 0xFF);
        $b .= pack('v', $this->jour_act & 0xFFFF);
        for ($i = 0; $i < 6; $i++) {
            $b .= chr($this->param[$i] & 0xFF);
        }
        for ($i = 0; $i < 7; $i++) {
            $b .= chr($this->tabclav1[$i] & 0xFF);
        }
        for ($i = 0; $i < 7; $i++) {
            $b .= chr($this->tabclav2[$i] & 0xFF);
        }
        $b .= chr($this->liste8->elements[4] & 0xFF) . chr($this->liste8->elements[5] & 0xFF);
        $b .= chr($this->bidon & 0xFF);
        foreach (['FreqOrage', 'DistMinFoudroie', 'nbNuages', 'nbOiseaux', 'nbRochers', 'nbHommes', 'nbArbres', 'nbHerbes', 'VitesseDeplacementNuages'] as $v) {
            $b .= pack('V', self::l32($this->$v));
        }
        return $b;
    }

    private function gestSomaLoad(string $b): void
    {
        $p = 0;
        $u8 = function () use ($b, &$p): int {
            return ord($b[$p++]);
        };
        $u16 = function () use ($b, &$p): int {
            $v = unpack('v', $b, $p)[1];
            $p += 2;
            return $v;
        };
        foreach (['soif', 'fatig', 'faim', 'mort', 'bless', 'zoom', 'agilite', 'force', 'resistance', 'charisme', 'vie', 'sexe', 'viecopain'] as $v) {
            $this->$v = $u8();
        }
        for ($i = 0; $i < 8; $i++) {
            $this->sap1->cross[$i] = $u8();
        }
        for ($i = 0; $i < 8; $i++) {
            $this->sap3->cross[$i] = $u8();
        }
        $this->scenar = $u8();
        $this->scenpr = self::sc($u8());
        $this->ciel = $u8();
        $this->terre = $u8();
        $this->liste1->taille = $u8();
        for ($i = 0; $i < 30; $i++) {
            $this->liste1->elements[$i] = $u8();
        }
        for ($i = 0; $i < 4; $i++) {
            $this->tagress[$i] = $u8();
            $this->enetoi[$i] = $u16();
        }
        $this->xj = $u16();
        $this->yj = $u16();
        $this->dzeta = self::ss($u16());
        $this->heure = $u16();
        $this->sap3->vie = $u8();
        $n = substr($b, $p, 8);
        $p += 8;
        $this->nomjoueur = explode("\0", $n)[0];
        $n = substr($b, $p, 6);
        $p += 6;
        $this->nomcopain = explode("\0", $n)[0];
        $this->present[2] = self::sc($u8());
        $this->jour_act = $u16();
        for ($i = 0; $i < 6; $i++) {
            $this->param[$i] = $u8();
        }
        for ($i = 0; $i < 7; $i++) {
            $this->tabclav1[$i] = $u8();
        }
        for ($i = 0; $i < 7; $i++) {
            $this->tabclav2[$i] = $u8();
        }
        $this->liste8->elements[4] = $u8();
        $this->liste8->elements[5] = $u8();
        $this->bidon = ($this->bidon & 0xFF00) | $u8();
        foreach (['FreqOrage', 'DistMinFoudroie', 'nbNuages', 'nbOiseaux', 'nbRochers', 'nbHommes', 'nbArbres', 'nbHerbes', 'VitesseDeplacementNuages'] as $v) {
            $this->$v = self::l32(unpack('V', $b, $p)[1]);
            $p += 4;
        }
    }

    /** SAUVE: the adventure in a slot (one save per day of game) */
    public function sauve(): \Generator
    {
        $this->flefba = 0;
        if ($this->jour_act === $this->jour_dsk) {
            // one adventure per day
            $this->comment(113);
            return;
        }
        $slot = yield from $this->gestChoix($this->gestTexte('sauver'), [...$this->gestLibellesSlots(), $this->gestTexte('retour')]);
        if ($slot < 0 || $slot >= self::GEST_SLOTS || $this->prefs === null) {
            return;
        }
        $this->gestSlot = $slot;
        $clair = $this->gestSomaBytes();
        $data = 'GOD';
        $checksum = 111;
        $this->initrnd(ord('S'), ord('A'), ord('P'));
        $n = strlen($clair);
        for ($i = 0; $i < $n; $i++) {
            $car = ord($clair[$i]) ^ $this->rnd0();
            $checksum += $car;
            $data .= chr($car);
        }
        $data .= chr($checksum & 255);
        $data .= $this->SauveEltsDecorsMobiles();
        $this->prefs->set('sapiens_save_' . $slot, base64_encode($data));
        $this->prefs->set('sapiens_save_' . $slot . '_info', $this->nomjoueur . ', ' . $this->gestTexte('jour') . ' ' . $this->jour_act);
        $this->jour_dsk = $this->jour_act;
    }

    /** CHARGE: an adventure from a slot */
    public function charge(): \Generator
    {
        $this->flefba = 0;
        $slot = yield from $this->gestChoix($this->gestTexte('charger'), [...$this->gestLibellesSlots(), $this->gestTexte('retour')]);
        if ($slot < 0 || $slot >= self::GEST_SLOTS) {
            return;
        }
        $raw = $this->prefs?->get('sapiens_save_' . $slot);
        $data = is_string($raw) ? base64_decode($raw, true) : false;
        $this->flefba = 0;
        if ($data === false || $data === '') {
            $this->comment(92);
            return;
        }
        $this->gestSlot = $slot;
        $this->primsap();
        $checksum = 111;
        $taille = strlen($this->gestSomaBytes());
        $rest = '';
        $this->initrnd(ord('S'), ord('A'), ord('P'));
        if (substr($data, 0, 3) === 'GOD') {
            if (strlen($data) < 3 + $taille) {
                $this->flefba = 0;
                $this->comment(92);
                return;
            }
            $clair = '';
            for ($i = 0; $i < $taille; $i++) {
                $car = ord($data[3 + $i]);
                $checksum += $car;
                $clair .= chr($car ^ $this->rnd0());
            }
            $this->gestSomaLoad($clair);
            $checksum &= 255;
            if ($checksum !== ord($data[3 + $taille] ?? "\0")) {
                $this->flefba = 0;
                $this->comment(92);
                $this->primsap();
                $this->mort = 0;
            }
            $rest = (string)substr($data, 4 + $taille);
        } else {
            $this->comment(92);
        }
        $this->CreeDecor3d(true);
        $this->ChargeEltsDecorsMobiles($rest);
        $this->minute = $this->zj = 0;
        $this->chrono(0);
        $this->jour_dsk = $this->jour_act;
        $this->affich($this->dzeta);
        $this->tfrima();
        $this->generique(true);
        if ($this->scenar === 6) {
            yield from $this->sceveu(6);
        }
    }

    /** TRGEST: the menu SAPIENS */
    public function trgest(int $regb): \Generator
    {
        $this->MontreSouris();
        switch ($regb) {
            case 0:
                // informations
                yield from $this->gestInfo();
                $this->DoAide();
                break;
            case 1:
                if (yield from $this->gestConfirmePerte()) {
                    yield from $this->charge();
                    $this->inimen(8);
                }
                break;
            case 2:
                yield from $this->sauve();
                break;
            case 3:
                // a new character: a new adventure
                if (yield from $this->gestConfirmePerte()) {
                    yield from $this->defsap();
                }
                break;
            case 4:
                yield from $this->gestPreferences();
                break;
        }
    }

    /** AJUSTE: MIDI settings (OLD_VERSION only in the original) */
    public function ajuste(): \Generator
    {
        $car = 0;
        yield from $this->sceveu(10);
        $this->reset_clav();
        while (!$car) {
            yield;
            $car = $this->litclav() & 223;
        }
    }

    /** BRAVO: the result of the knapping fades in (a frame per step: the original was that slow) */
    private function bravo(int $quoi): \Generator
    {
        $gbak = $this->curPort;
        $this->curPort = $this->Ecran1;
        $this->eff_sour();
        $this->effima(self::COULEUR_FOND_NUCLEUS);
        for ($k = 0; $k < 20; $k++) {
            $coeff = $k * 0.05;
            $this->GereInterface();
            $map = $this->CalculePaletteAttenuee($quoi, $coeff, 999);
            $this->Affmobbrt($quoi, 96, 1, 0, $map);
            $this->tfrima();
            yield;
        }
        $this->curPort = $gbak;
    }

    /** TRFAB: make a weapon from a flint (regb 0 spear, 1 axe) */
    public function trfab(int $regb): \Generator
    {
        $this->flefba = 0;
        $this->clrbadi();
        $i = $this->liste1->taille - 1;
        while ($i >= 0 && $this->liste1->elements[$i] !== 19) {
            $i--;
        }
        if ($i < 0) {
            // you have no flint
            $this->comment(87);
            return;
        }
        $this->dervue = self::VUE_AUCUNE;
        $tfr = $this->ciel_degr;
        $this->bruit(10);
        $trace_syl = yield from $this->cursil($regb);
        $this->palhaute = $this->pal2;
        $this->flefba = 0;
        if ($trace_syl > self::PRECISION_SILEX) {
            $this->bruit(7);
            yield from $this->bravo($this->MOB_SILEX_CASSE());
            $this->liste1->elements[$i] = 20;
            $this->comment(86);
        } else {
            $this->bruit(6);
            if ($trace_syl < 150) {
                $this->comment(110);
            }
            if ($regb !== 1) {
                $this->liste1->elements[$i] = 23;
                yield from $this->bravo($this->MOB_SILEX_SAGAIE());
            } else {
                $this->liste1->elements[$i] = 21;
                yield from $this->bravo($this->MOB_SILEX_HACHE());
            }
            $this->comment(85);
        }
        $this->bande(0);
        $this->generique(true);
        $this->ciel_degr = $tfr;
    }
}
