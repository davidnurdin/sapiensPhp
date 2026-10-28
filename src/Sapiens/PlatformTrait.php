<?php

namespace App\Sapiens;

/**
 * What the original took from MacOS (SAPWCLAV.C, SAPWIND.C, SAPWPREF.C): timers, keyboard and mouse,
 * QuickDraw random numbers, preferences. The engine feeds the input state each frame (GameScene).
 */
trait PlatformTrait
{
    public const ECHELLE = 2;

    /** the 11 ms timer of the game */
    public int $sapiensTimer = 0;
    /** TickCount (1/60 s) */
    public int $ticks = 0;
    private float $msTimer = 0.0;
    private float $msTicks = 0.0;

    // ---- input state (set by GameScene) ----
    /** @var array<int, bool> Mac key codes held (GetKeys) */
    public array $keysHeld = [];
    /** @var int[] typed characters (keyDown char codes) waiting for GereInterface */
    public array $typedQueue = [];
    /** mouse in pixels of the window (640x400) */
    public int $mouseX = 0;
    public int $mouseY = 0;
    public bool $mouseButton = false;
    /** the game read the mouse (vq_mouse) since the last click: GameScene keeps short clicks down until then */
    public bool $mouseRead = true;
    /** the pointer is a finger (no mouse steering in the views) */
    public bool $touchMode = false;

    // ---- SAPWCLAV.C globals ----
    public int $toucheClav = 0;
    public int $sposx = 0;
    public int $sposy = 0;
    public int $dsposx = 0;
    public int $dsposy = 0;
    public int $dicone = 0;
    public int $s_menu = 0;
    public int $icone = 0;
    public int $bouton = 0;
    public int $handle = 0;
    public int $s_action = 0;
    public bool $isScrollMouse = false;
    private int $lastposx = 0;
    private int $lastposy = 0;
    /** @var array{0:int,1:int,2:int,3:int} */
    private array $rsouris = [0, 0, 0, 0];

    // ---- preferences (Sapiens.cfg) ----
    public int $IsBruitages = 1;
    public int $ModeJoueur2 = 0;
    public int $okmuse = 1;

    /** QuickDraw randSeed */
    public int $randSeed = 1;

    /** Mac key codes used by the game */
    public const MK_UP = 0x7E;
    public const MK_DOWN = 0x7D;
    public const MK_LEFT = 0x7B;
    public const MK_RIGHT = 0x7C;
    public const MK_SPACE = 0x31;
    public const MK_RETURN = 0x24;
    public const MK_ESC = 0x35;
    public const MK_C = 0x08;
    public const MK_M = 0x2E;
    public const MK_SHIFT = 0x38;
    public const MK_KP8 = 0x5B;
    public const MK_KP2 = 0x54;
    public const MK_KP4 = 0x56;
    public const MK_KP6 = 0x58;
    public const MK_KP1 = 0x53;
    public const MK_KP3 = 0x55;
    public const MK_KPENTER = 0x4C;
    public const MK_KPDOT = 0x41;
    public const MK_KPMINUS = 0x4E;
    public const MK_KP0 = 0x52;
    public const MK_KPPLUS = 0x45;
    public const MK_KPSTAR = 0x43;
    public const MK_KPSLASH = 0x4B;
    public const MK_KPCLEAR = 0x47;
    public const MK_KP7 = 0x59;
    public const MK_KP9 = 0x5C;
    public const MK_KP5 = 0x57;
    public const MK_ESC2 = 0x42;

    /** Time passing (called by the scene before each step of the game). */
    public function advanceTime(float $ms): void
    {
        $this->msTimer += $ms;
        $this->msTicks += $ms;
        if ($this->msTimer >= 11) {
            $n = (int)($this->msTimer / 11);
            $this->sapiensTimer += $n;
            $this->msTimer -= $n * 11;
        }
        if ($this->msTicks >= 1000 / 60) {
            $n = (int)($this->msTicks / (1000 / 60));
            $this->ticks += $n;
            $this->msTicks -= $n * (1000 / 60);
        }
    }

    public function TickCount(): int
    {
        return $this->ticks;
    }

    /** Wraps a value to a signed 32-bit integer (C long), the same on 64-bit and 32-bit PHP. */
    public static function l32(int|float $v): int
    {
        $v = fmod((float)$v, 4294967296.0);
        if ($v < 0) {
            $v += 4294967296.0;
        }
        if ($v >= 2147483648.0) {
            $v -= 4294967296.0;
        }
        return (int)$v;
    }

    /** (char) cast: signed 8-bit */
    public static function sc(int $v): int
    {
        $v &= 0xFF;
        return $v >= 128 ? $v - 256 : $v;
    }

    /** (short) cast: signed 16-bit */
    public static function ss(int $v): int
    {
        $v &= 0xFFFF;
        return $v >= 32768 ? $v - 65536 : $v;
    }

    // ---- random numbers -------------------------------------------------------------------------

    /** QuickDraw Random(): Park-Miller on randSeed, low 16 bits signed, -32768 -> 0 */
    public function Random(): int
    {
        $seed = $this->randSeed & 0x7FFFFFFF;
        if ($seed === 0) {
            $seed = 1;
        }
        // Schrage: seed * 16807 mod (2^31 - 1) without overflow
        $hi = intdiv($seed, 127773);
        $lo = $seed % 127773;
        $t = 16807 * $lo - 2836 * $hi;
        if ($t <= 0) {
            $t += 0x7FFFFFFF;
        }
        $this->randSeed = $t;
        $r = $t & 0xFFFF;
        if ($r >= 32768) {
            $r -= 65536;
        }
        return $r === -32768 ? 0 : $r;
    }

    public function initrnd(int $sem1, int $sem2, int $sem3): void
    {
        $this->randSeed = self::l32(($sem1 << 16) + ($sem2 << 8) + $sem3);
    }

    public function rnd0(): int
    {
        return $this->Random() & 0xFF;
    }

    public function Rnd(): int
    {
        return $this->Random() & 0xFF;
    }

    /** myRandom: min <= v < max */
    public function myRandom(int $min, int $max): int
    {
        $rdm = $this->Rnd();
        $rdm = ($rdm + $this->Rnd() * 256) & 0xFFFF;
        $range = $max - $min;
        // C: (long)rdm * (long)range / 65536 (truncation toward zero)
        $t = (int)(($rdm * (float)$range) / 65536.0);
        return $min + $t;
    }

    // ---- interface ------------------------------------------------------------------------------

    /** GereInterface: the events (here: the typed keys) and the music */
    public function GereInterface(): void
    {
        $this->CheckChangeMusique();
        while ($this->typedQueue !== []) {
            $this->toucheClav = array_shift($this->typedQueue);
            // F1 (help): open the manual on the web page
            if ($this->toucheClav === 0x10) {
                $this->DoAide();
            }
        }
    }

    /** delai: waits $duree 1/60 s (generator) */
    public function delai(int $duree): \Generator
    {
        $t = $this->TickCount() + $duree;
        do {
            $this->GereInterface();
            yield;
        } while ($t > $this->TickCount());
    }

    public function litclav(): int
    {
        $this->GereInterface();
        $n = $this->toucheClav;
        $this->toucheClav = 0;
        return $n;
    }

    public function reset_mouse(): void
    {
        $b = 0;
        $this->vq_mouse($b, $this->dsposx, $this->dsposy);
    }

    public function reset_clav(): void
    {
        $this->toucheClav = 0;
        $this->typedQueue = [];
    }

    public function CacheSouris(): void
    {
    }

    public function MontreSouris(): void
    {
    }

    /** vq_mouse: button (1 = click, |2 with shift) and position in ST coordinates (0-320, 0-200) */
    public function vq_mouse(int &$bouton, int &$x, int &$y): void
    {
        $this->GereInterface();
        $x = intdiv($this->mouseX, 2);
        $y = intdiv($this->mouseY, 2);
        $bouton = $this->mouseButton ? 1 : 0;
        $this->mouseRead = true;
        if ($bouton && ($this->keysHeld[self::MK_SHIFT] ?? false)) {
            $bouton |= 2;
        }
    }

    public function FixeAireNoMouse(array $r): void
    {
    }

    public function FixeAireNoMouseAireBasse(): void
    {
    }

    /** wait_mouse: until the mouse moves or its button changes (generator) */
    public function wait_mouse(int &$bouton, int &$x, int &$y): \Generator
    {
        $orgx = $x;
        $orgy = $y;
        $orgbouton = $bouton;
        do {
            yield;
            $this->vq_mouse($bouton, $x, $y);
        } while ($x === $orgx && $y === $orgy && $bouton === $orgbouton);
    }

    /** eff_sour: hides the mouse of the menus (debecr copied over it) */
    public function eff_sour(): void
    {
        $this->CopyOffscreen($this->debecr, $this->mainWindow, $this->rsouris, $this->rsouris);
        $this->rsouris = [0, 0, 0, 0];
    }

    /** aff_sour: the mouse of the menus (an icon mob with its shadow) at (ligne, colonne) */
    public function aff_sour(int $mob, int $ligne, int $colonne): void
    {
        if ($mob <= 0 || !isset($this->TabMob[$mob])) {
            $this->eff_sour();
            return;
        }
        $old = $this->rsouris;
        $this->rsouris = $this->CalculeAireMob($mob, $colonne, $ligne);
        $rsource = $this->CalculeAireMob($mob, 0, 0);
        $this->rsouris[2] += 8;
        $this->rsouris[3] += 8;
        $rsource[2] += 8;
        $rsource[3] += 8;
        $this->CopyOffscreen($this->debecr, $this->BuffMouse, $this->rsouris, $rsource);
        $gbak = $this->curPort;
        $this->curPort = $this->BuffMouse;
        $this->Affmobbrtomb($mob, 0, 0, 4, 4);
        $this->curPort = $gbak;
        // the old place first, then the mouse
        $this->CopyOffscreen($this->debecr, $this->mainWindow, $old, $old);
        $this->CopyOffscreen($this->BuffMouse, $this->mainWindow, $rsource, $this->rsouris);
    }

    public function KeyPressed(int $code): bool
    {
        return $this->keysHeld[$code] ?? false;
    }

    public function IsKey(int $code): bool
    {
        return $this->KeyPressed($code);
    }

    /** GetKeyMap: 1 up, 2 down, 4 left, 8 right, 16 return, 32 space (or C / M: map), 64 escape */
    public function GetKeyMap(int &$bitmap): int
    {
        $bitmap = 0;
        if ($this->KeyPressed(self::MK_UP)) {
            $bitmap |= 1;
        }
        if ($this->KeyPressed(self::MK_DOWN)) {
            $bitmap |= 2;
        }
        if ($this->KeyPressed(self::MK_LEFT)) {
            $bitmap |= 4;
        }
        if ($this->KeyPressed(self::MK_RIGHT)) {
            $bitmap |= 8;
        }
        if ($this->KeyPressed(self::MK_SPACE)) {
            $bitmap |= 32;
        }
        if ($this->KeyPressed(self::MK_RETURN)) {
            $bitmap |= 16;
        }
        if ($this->KeyPressed(self::MK_ESC)) {
            $bitmap |= 64;
        }
        if ($this->KeyPressed(self::MK_C) || $this->KeyPressed(self::MK_M)) {
            $bitmap |= 32;
        }
        return $bitmap;
    }

    private function GetKeyMapJoueur2(int &$bitmap): int
    {
        $bitmap = 0;
        if ($this->KeyPressed(self::MK_KP8)) {
            $bitmap |= 1;
        }
        if ($this->KeyPressed(self::MK_KP2)) {
            $bitmap |= 2;
        }
        if ($this->KeyPressed(self::MK_KP4)) {
            $bitmap |= 4;
        }
        if ($this->KeyPressed(self::MK_KP6)) {
            $bitmap |= 8;
        }
        if ($this->KeyPressed(self::MK_KP1)) {
            $bitmap |= 32;
        }
        if ($this->KeyPressed(self::MK_KP3)) {
            $bitmap |= 16;
        }
        if ($this->KeyPressed(self::MK_ESC2)) {
            $bitmap |= 64;
        }
        return $bitmap;
    }

    /** getkey3d: keys, or the mouse moved from its reference point (dsposx, dsposy) */
    public function getkey3d(): int
    {
        $bitmap = 0;
        if (!$this->GetKeyMap($bitmap)) {
            $this->vq_mouse($this->bouton, $this->sposx, $this->sposy);
            if ($this->touchMode) {
                // a touch screen has no resting pointer: no steering with it (the pad does it)
                $this->dsposx = $this->sposx;
                $this->dsposy = $this->sposy;
            }
            $delt = $this->sposx - $this->dsposx;
            if ($delt > 16) {
                $bitmap |= 8;
            }
            if ($delt < -16) {
                $bitmap |= 4;
            }
            $delt = $this->sposy - $this->dsposy;
            if ($delt > 8) {
                $bitmap |= 2;
            }
            if ($delt < -8) {
                $bitmap |= 1;
            }
            if ($this->bouton & 3) {
                $bitmap |= 64;
            }
        } else {
            $this->reset_mouse();
        }
        return $bitmap;
    }

    /** getkey: 0 main player, 1 second player (numeric keypad) */
    public function getkey(int $quoi): int
    {
        $bitmap = 0;
        if ($quoi) {
            $this->GetKeyMapJoueur2($bitmap);
        } else {
            if (!$this->GetKeyMap($bitmap)) {
                $this->vq_mouse($this->bouton, $this->sposx, $this->sposy);
                if (!$this->touchMode) {
                    $delt = $this->sposx - $this->dsposx;
                    if ($delt > 10) {
                        $bitmap |= 8;
                    }
                    if ($delt < -10) {
                        $bitmap |= 4;
                    }
                    $delt = $this->sposy - $this->dsposy;
                    if ($delt > 5) {
                        $bitmap |= 2;
                    }
                    if ($delt < -5) {
                        $bitmap |= 1;
                    }
                }
                if ($this->bouton & 1) {
                    $bitmap |= 32;
                }
                if ($this->bouton & 2) {
                    $bitmap |= 16;
                }
            } else {
                $this->reset_mouse();
            }
            $this->dsposx = $this->sposx;
            $this->dsposy = $this->sposy;
        }
        return $bitmap;
    }

    /** bouge_souris: reads the mouse and draws the icon of the menus */
    public function bouge_souris(): void
    {
        $this->GereInterface();
        $this->vq_mouse($this->bouton, $this->sposx, $this->sposy);
        if ($this->sposy > 184) {
            $this->sposy = 184;
        }
        if ($this->sposx > 303) {
            $this->sposx = 303;
        }
        if ((($this->sposx !== $this->lastposx) || ($this->sposy !== $this->lastposy) || ($this->icone !== $this->dicone)) && $this->icone) {
            $this->dicone = $this->icone;
            $this->lastposx = $this->sposx;
            $this->lastposy = $this->sposy;
            $this->aff_sour($this->icone, $this->sposy, $this->sposx);
        }
    }

    /** AttendsFinBouton: until the button is released (generator) */
    public function AttendsFinBouton(): \Generator
    {
        while ($this->bouton) {
            yield;
            $this->vq_mouse($this->bouton, $this->sposx, $this->sposy);
        }
    }

    public function joystick(): int
    {
        return 0;
    }

    // ---- SAPWIND.C ----------------------------------------------------------------------------

    public function Collapse(string $format, mixed ...$args): void
    {
        error_log('[sapiens] ' . vsprintf($format, $args));
    }

    public function boxf(int $lignedep, int $coldep, int $lignefin, int $colfin, int $index): void
    {
        $this->BoxFill($this->curPort, [$coldep * 2, $lignedep * 2, $colfin * 2, $lignefin * 2], $this->palhaute[$index] ?? 0);
    }

    public function bande(int $quelle): void
    {
    }

    public function irqon(): void
    {
    }

    public function cadreg(int $ligne, int $col, int $nligne, int $ncol): void
    {
    }

    public function hires(): int
    {
        return 1;
    }

    public function ValideUpdate(): void
    {
    }

    /** MenuFenetres: the menus of the bottom active or not (during the views) */
    public function MenuFenetres(int $etat): void
    {
        $this->Frame3d($etat);
        $this->tfrmenufenetre($etat);
        $this->dicone = -1;
    }

    /** DoAide: the manual (web page) */
    public function DoAide(): void
    {
        if ($this->onHelp !== null) {
            ($this->onHelp)();
        }
    }

    // ---- preferences ----

    public function ChargePref(): bool
    {
        $p = $this->prefs;
        if ($p === null) {
            return true;
        }
        $this->IsBruitages = (int)$p->get('sapiens_bruitages', 1);
        $this->ModeJoueur2 = (int)$p->get('sapiens_joueur2', 0);
        $this->okmuse = (int)$p->get('sapiens_musique', 1);
        $this->langue = (int)$p->get('sapiens_langue', $this->langue);
        return false;
    }

    public function SauvePref(): void
    {
        $p = $this->prefs;
        if ($p === null) {
            return;
        }
        $p->set('sapiens_bruitages', $this->IsBruitages);
        $p->set('sapiens_joueur2', $this->ModeJoueur2);
        $p->set('sapiens_musique', $this->okmuse);
        $p->set('sapiens_langue', $this->langue);
    }

    // ---- window (SAPWIND.C) -----------------------------------------------------------------------

    /** Frame3d: the frame of the 3D view (black = active) */
    public function Frame3d(int $etat): void
    {
        $gbak = $this->curPort;
        $r = [62, 0, 640 - 64, 204];
        foreach ([$this->debecr, $this->mainWindow] as $port) {
            $this->curPort = $port;
            $this->qdFore = $etat ? Mac::BLACK : Mac::WHITE;
            $this->DoPenSize(2, 2);
            $this->DoFrameRect($r);
            $this->PenNormal();
        }
        $this->qdFore = Mac::BLACK;
        $this->curPort = $gbak;
    }

    /** AfficheFond: the background pattern of the window (PICT 128) */
    public function AfficheFond(Port $port): void
    {
        $gbak = $this->curPort;
        $this->curPort = $port;
        for ($y = 0; $y < $port->h; $y += 64) {
            for ($x = 0; $x < $port->w + 16; $x += 64) {
                $this->drawBmp($this->dataDir . '/pict128.bmp', $x, $y, true);
            }
        }
        $this->curPort = $gbak;
    }

    /** AffStr: a string in white with a black shadow, centred if $x < 0 ($y = base line) */
    private function AffStr(string $chaine, int $x, int $y): void
    {
        if ($x < 0) {
            $x = intdiv(640 - $this->TailleChaine($chaine), 2);
        }
        $y -= 12;
        $this->DrawStringBrt($chaine, $x, $y, 14, Mac::WHITE, Mac::BLACK);
    }

    /** init_graph: the window, the title screen, the offscreens, the mobs, the colours (generator: 5 s of title) */
    public function init_graph(): \Generator
    {
        $this->ChargePref();
        $this->MusicInit(1);
        $this->InitAndCheckTexts();
        Mac::init();
        $this->InitMobs();
        $this->InitCouleurFontes();
        $this->CarboniseSetPortWindow();
        $this->AfficheFond($this->mainWindow);
        // the logo, centred
        $r = [intdiv(640 - 220, 2), 60, intdiv(640 - 220, 2) + 220, 60 + 56];
        $this->drawBmp($this->dataDir . '/pict134.bmp', $r[0], $r[1], true);
        $this->AffStr('3.0.14', -1, $r[3] + 16);
        $this->AffStr('Copyright Didier et Olivier Guillion, 1986,1996,2005,2017', -1, $r[3] + 32);
        $y = 60 + 120 + 18 + 32;
        foreach ($this->rsrcStrings[$this->langue]['splash'] ?? [] as $line) {
            if ($line !== '') {
                $this->AffStr($this->utf8ToFont($line), -1, $y);
            }
            $y += 18;
        }
        $this->AffStr('Port PHP - JumPHP - GPL v3', -1, $y + 18);
        $timeBase = $this->TickCount() + 5 * 60;
        $this->CalcIndexPalette($this->pal1);
        $this->CalcIndexPalette($this->pal2);
        $this->curPort = $this->Ecran1;
        $this->AfficheFond($this->debecr);
        $this->CalculeToutDegrades();
        $this->CalculeEtoiles();
        // the title stays 5 s (a click or a key shortens it)
        $b = 0;
        $x = 0;
        $y = 0;
        while ($timeBase > $this->TickCount()) {
            yield;
            $this->vq_mouse($b, $x, $y);
            if ($b || $this->litclav()) {
                break;
            }
        }
        yield from $this->AttendsFinBoutonLocal();
    }

    private function AttendsFinBoutonLocal(): \Generator
    {
        $b = 1;
        $x = 0;
        $y = 0;
        while ($b) {
            $this->vq_mouse($b, $x, $y);
            if ($b) {
                yield;
            }
        }
    }
}
