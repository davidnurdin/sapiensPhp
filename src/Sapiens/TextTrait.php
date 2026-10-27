<?php

namespace App\Sapiens;

use App\Sapiens\Text\Es;
use App\Sapiens\Text\Fr;
use App\Sapiens\Text\Gb;

/**
 * SAPTXT.C (texts by language), SAPWCAR.C (characters drawn with the font mobs GENEVA10/14 of the
 * original game: the "not smoothed" mode of the original) and SAPWMESS.C (the message line).
 *
 * Strings are byte strings like in the C code: bytes >= 129 are macros of the word bank (GetBkMot),
 * '&', '%', '$', '#' are accented letters (CvtCar).
 */
trait TextTrait
{
    public const FR = 0;
    public const GB = 1;
    public const ES = 2;
    public const REFER0 = 0;
    public const REFER1 = 1;
    public const REFER2 = 2;
    public const REFER3 = 3;

    public string $maced = 'HARGIONGIKRUNHOGROKHOULANIRKHUGOTHORNIUPAISNIPHYGLO';
    /** 0 French, 1 English, 2 Spanish */
    public int $langue = 0;
    public string $nomjoueur = 'Torgan';
    public string $nomcopain = 'GABU..';
    public string $nomadversaire = '';
    public string $nomtribu = '';
    public string $nomsecondaire = '';
    public string $poschef = '. .';
    public int $HOMME = 72;
    public int $FEMME = 70;
    public int $OUI = 79;

    /** @var array<int, array<int, array{0:string,1:int,2:int}>> the references of each type (copies: afstat changes them) */
    public array $refer = [];

    /** Point ptEcrCar (h, v) in pixels of the port */
    public int $ptEcrH = 0;
    public int $ptEcrV = 0;
    public int $colaff = 1;
    public int $linaff = 1;
    public int $indexWhite = 0;
    public int $indexBlack = 255;
    public int $indexBrown = 0;
    public int $indexGray = 0;
    /** @var array{0:int,1:int,2:int,3:int} message line (pixels) */
    public array $rMess = [16 * 2, 104 * 2, 303 * 2, 112 * 2];
    /** @var array<string, mixed> strings of the resource fork (strings.json) */
    public array $rsrcStrings = [];

    /** @return class-string */
    private function textClass(): string
    {
        return match ($this->langue) {
            self::FR => Fr::class,
            self::ES => Es::class,
            default => Gb::class,
        };
    }

    /** InitAndCheckTexts (+ the references, which afstat modifies) */
    public function InitAndCheckTexts(): void
    {
        $c = $this->textClass();
        $this->refer = [$c::REFER0, $c::REFER1, $c::REFER2, $c::REFER3];
        switch ($this->langue) {
            case self::FR:
                $this->HOMME = ord('H');
                $this->FEMME = ord('F');
                $this->OUI = ord('O');
                break;
            case self::ES:
                $this->HOMME = ord('H');
                $this->FEMME = ord('M');
                $this->OUI = ord('S');
                break;
            default:
                $this->HOMME = ord('M');
                $this->FEMME = ord('F');
                $this->OUI = ord('Y');
        }
    }

    /** @return array<int, array{0:string,1:int,2:int}> GetRefer(type): [name, param1, param2] */
    public function GetRefer(int $type): array
    {
        return $this->refer[$type];
    }

    /** @return array<int, string|null> */
    public function GetMesCon(): array
    {
        return $this->textClass()::MESCON;
    }

    /** @return array<int, string|null> */
    public function GetApp(): array
    {
        return $this->textClass()::APP;
    }

    /** @return array<int, string|null> */
    public function GetBkMot(): array
    {
        return $this->textClass()::BKMOTS;
    }

    /** @return array<int, string|null> */
    public function GetScenme(): array
    {
        return $this->textClass()::SCENME;
    }

    /** GetStr: string of STR# 0 (+1000 per language), 1-based */
    public function GetStr(int $index): string
    {
        return $this->utf8ToFont((string)($this->rsrcStrings[$this->langue]['vrac'][$index - 1] ?? ''));
    }

    /** UTF-8 -> the bytes of the texts of the game (accents as '&', '%', '$', '#', see CvtCar) */
    public function utf8ToFont(string $s): string
    {
        return strtr($s, [
            'é' => '&', 'è' => '%', 'ê' => '%', 'à' => '$', 'ç' => '#', 'É' => 'E', 'È' => 'E', 'À' => 'A',
            'â' => 'a', 'ä' => 'a', 'á' => 'a', 'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o',
            'ó' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u', 'ñ' => 'n', 'Ñ' => 'N', '¿' => '?',
            '¡' => '!', '’' => "'", 'œ' => 'oe',
        ]);
    }

    /** CvtCar: accents of the texts -> characters of the font (CP437 like: 0x82 = é...) */
    public function CvtCar(int $car): int
    {
        switch ($car) {
            case 0x25: return 0x8A;
            case 0x26: return 0x82;
            case 0x24: return 0x85;
            case 0x23: return 0x87;
        }
        if ($this->langue === self::ES) {
            // the Spanish letters of the smoothed font do not exist in the bitmap font
            switch ($car) {
                case 0x5B: return 0x3F; // ¿
                case 0x5D: return 0x21; // ¡
                case 0x5E: return 0x6E; // ñ
                case 0x7C: return 0x8D; // í
                case 0x5F: return 0x97; // ú
            }
        }
        return $car;
    }

    public function InitCouleurFontes(): void
    {
        $this->indexWhite = Mac::color2Index(0xFFFF, 0xFFFF, 0xFFFF);
        $this->indexBlack = Mac::color2Index(0, 0, 0);
        $this->indexBrown = Mac::color2Index(34368, 27840, 7008);
        $this->indexGray = Mac::color2Index(50000, 50000, 50000);
    }

    public function Gotoxy(int $x, int $y): void
    {
        $this->ptEcrH = $x * 2 * 6;
        $this->ptEcrV = $y * 2 * 7 - 1;
        $this->colaff = $x;
        $this->linaff = $y;
    }

    public function GotoxyPixel(int $x, int $y): void
    {
        $this->ptEcrH = $x;
        $this->ptEcrV = $y;
    }

    /** TailleCar: width of a character in pixels */
    public function TailleCar(int $car, int $taille): int
    {
        $car = $this->CvtCar($car);
        $num = ($taille === 10 ? $this->Fonte10 : $this->Fonte14) + $car - 31;
        $mob = $this->TabMob[$num] ?? null;
        if ($mob === null) {
            return 0;
        }
        return $mob->nbcol + ($taille === 10 ? 2 : 3);
    }

    /** Width of a string (macros expanded) */
    public function TailleChaine(string $s, int $taille = 14): int
    {
        $w = 0;
        $n = strlen($s);
        for ($i = 0; $i < $n; $i++) {
            $c = ord($s[$i]);
            if ($c > 128) {
                $w += $this->TailleChaine($this->GetStrBkMot($c), $taille);
            } else {
                $w += $this->TailleCar($c, $taille);
            }
        }
        return $w;
    }

    /** AffCarBrt: one character at ptEcrCar of the current port, with its shadow */
    public function AffCarBrt(int $car, int $couleur, int $couleurOmbre, int $taille): void
    {
        $car = $this->CvtCar($car);
        if ($car < 32) {
            return;
        }
        if ($taille === 10) {
            $num = $this->Fonte10 + $car - 31;
            if (!isset($this->TabMob[$num])) {
                return;
            }
            $this->AffmobCar($num, $this->ptEcrH + 1, $this->ptEcrV + 1, $couleurOmbre);
            $this->AffmobCar($num, $this->ptEcrH, $this->ptEcrV, $couleur);
            $this->ptEcrH += $this->TabMob[$num]->nbcol + 2;
        } else {
            $num = $this->Fonte14 + $car - 31;
            if (!isset($this->TabMob[$num])) {
                return;
            }
            $this->AffmobCar($num, $this->ptEcrH + 2, $this->ptEcrV + 1, $couleurOmbre);
            $this->AffmobCar($num, $this->ptEcrH + 1, $this->ptEcrV, $couleur);
            $this->AffmobCar($num, $this->ptEcrH, $this->ptEcrV, $couleur);
            $this->ptEcrH += $this->TabMob[$num]->nbcol + 3;
        }
    }

    /** GetStrBkMot: the word of the bank for a macro character (>= 129) */
    public function GetStrBkMot(int $car): string
    {
        $bk = $this->GetBkMot();
        $car -= 129;
        if ($car < 0 || $car >= count($bk)) {
            return '';
        }
        $p = (string)$bk[$car];
        if ($p !== '' && $p[0] === '$') {
            switch ($p[1] ?? '') {
                case '0': return $this->nomsecondaire;
                case '1': return $this->nomjoueur;
                case '2': return $this->nomadversaire;
                case '3': return $this->nomtribu;
                case '4': return $this->nomcopain;
            }
        }
        return $p;
    }

    public function AffCarBank(Port $port, int $car, int $couleur1, int $couleur2, int $taille): void
    {
        $gbak = $this->curPort;
        $this->curPort = $port;
        if ($car > 128) {
            $p = $this->GetStrBkMot($car);
            $n = strlen($p);
            for ($i = 0; $i < $n; $i++) {
                $this->AffCarBank($port, ord($p[$i]), $couleur1, $couleur2, $taille);
            }
        } else {
            $this->AffCarBrt($car, $couleur1, $couleur2, $taille);
            $this->colaff++;
        }
        $this->curPort = $gbak;
    }

    public function affcar(int $car): void
    {
        $this->AffCarBank($this->debecr, $car, $this->indexBlack, $this->indexBrown, 14);
    }

    public function affcar10(int $car): void
    {
        $this->AffCarBank($this->debecr, $car, $this->indexBlack, $this->indexGray, 10);
    }

    public function car2(int $car): void
    {
        $this->AffCarBank($this->debecr, $car, $this->indexWhite, $this->indexBlack, 14);
    }

    /** affcarmess: a character of the message line (on debecr, clipped to rMess); 1/2 = cursor */
    public function affcarmess(int $car): void
    {
        $gbak = $this->curPort;
        $this->curPort = $this->debecr;
        $this->qdClip = $this->rMess;
        if ($car < 32) {
            $r = [$this->ptEcrH, $this->ptEcrV, $this->ptEcrH + 8, $this->ptEcrV + 11];
            $r = self::SectRect($r, $this->rMess);
            if ($r !== null) {
                if ($car === 2) {
                    $this->debecr->fill($r[0], $r[1], $r[2], $r[3], Mac::BLACK);
                }
                if ($car === 1) {
                    $this->DoEraseRect($r);
                }
            }
        } else {
            $this->AffCarBank($this->debecr, $car, $this->indexBlack, $this->indexGray, 14);
        }
        $this->qdClip = null;
        $this->curPort = $gbak;
    }

    /** motlst2: a string in white on black (gauges) */
    public function motlst2(string $debut): void
    {
        $n = strlen($debut);
        for ($i = 0; $i < $n; $i++) {
            $this->car2(ord($debut[$i]));
        }
    }

    /** A string in a font of the game at a pixel position of the current port (replaces DrawString) */
    public function DrawStringBrt(string $s, int $x, int $y, int $taille, int $couleur, int $ombre): void
    {
        $this->ptEcrH = $x;
        $this->ptEcrV = $y;
        $port = $this->curPort;
        $n = strlen($s);
        for ($i = 0; $i < $n; $i++) {
            $this->AffCarBank($port, ord($s[$i]), $couleur, $ombre, $taille);
        }
    }

    // ---- SAPWMESS.C ----------------------------------------------------------------------------

    public function TfrBadAss(): void
    {
        $this->eff_sour();
        $this->CopyOffscreen($this->debecr, $this->mainWindow, $this->rMess, $this->rMess);
    }

    public function efbadass(): void
    {
        $gbak = $this->curPort;
        $this->curPort = $this->debecr;
        $this->DoEraseRect($this->rMess);
        $this->curPort = $gbak;
    }

    public function scrollbadass(): void
    {
        $this->eff_sour();
        // the original scrolls the window by steps of 8 pixels without waiting: it is invisible
        $this->efbadass();
        $this->CopyOffscreen($this->debecr, $this->mainWindow, $this->rMess, $this->rMess);
    }

    public function framebadass(): void
    {
        $gbak = $this->curPort;
        $this->curPort = $this->debecr;
        $r = self::InsetRect($this->rMess, -1, -1);
        $this->DoEraseRect($r);
        $this->qdFore = Mac::BLACK;
        $this->DoFrameRect($r);
        $this->DoPenSize(1, 1);
        $this->curPort = $gbak;
    }
}
