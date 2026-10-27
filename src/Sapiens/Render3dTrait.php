<?php

namespace App\Sapiens;

/**
 * SAPW3D.C: the landscape renderer, on Ecran1 (512x200): the sky gradient of the hour, the stars,
 * the sun / moon, the ground in bands, then the objects of the world as scaled sprites, far to near,
 * darker with the distance (TabPaletteDistance), and the rain. The dead visu2 (disks) is not ported.
 *
 * Speed (PHP in the browser): the scaled sprites go through blit3d (Affmob3d with a cache of the
 * scaled rows), the stars are a pre-drawn band, the sky is whole rows.
 */
trait Render3dTrait
{
    private const NB_ETOILES = 10000;
    private const AIRE_X_ETOILE = 2048;
    private const AIRE_Y_ETOILE = 128;
    private const MAX_MAGNITUDE = 16;
    private const NB_INTERV = 10;
    private const LARGEUR_BANDE_SOL = 80;
    private const LARGEUR_MOB = 256;
    private const HAUTEUR_MOB = 32;
    private const HAUTEUR_MIN_BANDE = 5;
    private const LARGEUR_ECRAN = 512;
    private const HAUTEUR_ECRAN = 256;
    private const TAB_LIGN = [0, 41 * 2, 48 * 2, 53 * 2, 57 * 2, 59 * 2, 61 * 2, 64 * 2];

    /** @var string[] the 72 palette maps of the current hour (near to far) */
    public array $TabPaletteDistance = [];
    /** @var int[] the 128 colours of the sky of the current hour */
    public array $TabCouleursCiel = [];
    private int $CouleurOrageJour = 0;
    private int $CouleurOrageNuit = 0;
    private float $IotaPaletteStd = 0.14;
    /** @var array<int, string> CalculePaletteAttenuee(mob3D + 1, coeff, 33) by (int)(256 * coeff) */
    private array $attenuees = [];

    /** @var array<int, array{x:int, y:int, magnitude:int, couleur:int}> */
    private array $TabEtoiles = [];
    private int $CoulPulse = 0;
    private int $meteore = 0;
    private int $xMeteore = 0;
    private int $yMeteore = 0;
    /** @var array<string, array{0:string[],1:string[]}> star bands by magnitude / variant: rows and masks */
    private array $bandesEtoiles = [];

    /** @var array<int, array{y:int, hauteur:int, largeur:int, offsetEnMob:int, offsetEcran:int, dist:int}> */
    private array $TabLigneSol = [];
    private int $lastDzeta = 0;
    private int $SolRandSeed = 0x2548795A;

    /** @var array<string, array{0:string[],1:string[]}> scaled rows of blit3d */
    private array $cache3d = [];
    /** @var array<int, string> rows of the storm pattern (even / odd) and their masks */
    private array $trameOrage = [];

    public function sinshort(int $angle): int
    {
        return (int)(255 * sin($angle * 3.1415925 / 512.0));
    }

    public function cosshort(int $angle): int
    {
        return (int)(255 * cos($angle * 3.1415925 / 512.0));
    }

    /** distance: max + 2/5 min, at most 32700 */
    public function distance(int $dx, int $dy): int
    {
        $dx = abs($dx);
        $dy = abs($dy);
        if ($dx > $dy) {
            $dx = $dx + intdiv($dy * 2, 5);
        } else {
            $dx = $dy + intdiv($dx * 2, 5);
        }
        return $dx > 32700 ? 32700 : $dx;
    }

    /**
     * CalcPoint3D: a point of the world on the screen.
     * @return array{0:int,1:int,2:int}|null x, y, distance; null when not visible
     */
    private function CalcPoint3D(int $xj, int $yj, int $zj, float $ca, float $sa, int $xp, int $yp, int $zp): ?array
    {
        $xp -= $xj;
        $yp -= $yj;
        $zp -= $zj;
        $tmp = (int)($xp * $ca - $yp * $sa);
        $yp = (int)($xp * $sa + $yp * $ca);
        $xp = $tmp;
        if ($xp <= 2) {
            return null;
        }
        $dist = $this->distance($xp, $yp);
        if ($dist === 0) {
            return null;
        }
        $xl = intdiv(self::LARGEUR_ECRAN, 2) - intdiv(intdiv(self::LARGEUR_ECRAN, 2) * $yp, $xp);
        $yl = intdiv(self::HAUTEUR_ECRAN, 2) - intdiv(intdiv(self::HAUTEUR_ECRAN, 2) * $zp, $xp);
        $xl = max(-32760, min(32760, $xl));
        $yl = max(-32760, min(32760, $yl));
        return [$xl, $yl, $dist];
    }

    /** SapDep: the player walks with the angle and the speed */
    public function sapdep(int $angle, int $vitesse): void
    {
        $tmp = $this->xj + (($this->cosshort($angle) * $vitesse) >> 8);
        $this->xj = max(0, min(65535, $tmp));
        $tmp = $this->yj + (($this->sinshort($angle) * $vitesse) >> 8);
        $this->yj = max(0, min(65535, $tmp));
        $this->AvanceSol($vitesse);
    }

    /** f32: the value as a C float */
    private static function f32(float $v): float
    {
        return unpack('g', pack('g', $v))[1];
    }

    /** CalculeDegradeDuCiel: the 128 colours of the sky from an ST palette of 8 colours */
    private function CalculeDegradeDuCiel(array $palette): array
    {
        $out = [];
        $dep = [0, 0, 0];
        $inc = [0, 0, 0];
        for ($i = $j = 0; $i < self::NB_LIGNES_CIEL; $i++) {
            if ($i === self::TAB_LIGN[$j]) {
                $dep = $this->stRgb3d($palette[$j++]);
                $dest = $this->stRgb3d($palette[$j]);
                $dist = self::TAB_LIGN[$j] - self::TAB_LIGN[$j - 1];
                for ($k = 0; $k < 3; $k++) {
                    $inc[$k] = intdiv($dest[$k] - $dep[$k], $dist);
                }
            }
            for ($k = 0; $k < 3; $k++) {
                $dep[$k] = ($dep[$k] + $inc[$k]) & 0xFFFF;
            }
            $out[$i] = Mac::color2Index($dep[0], $dep[1], $dep[2]);
        }
        return $out;
    }

    /** CvtCouleurStEnRgb */
    private function stRgb3d(int $val): array
    {
        return [(($val & 0x700) >> 8) * 9362, (($val & 0x070) >> 4) * 9362, ($val & 0x007) * 9362];
    }

    /** CalculeDegradeDistance: the palettes of the objects by distance for an hour */
    private function CalculeDegradeDistance(int $heure): array
    {
        if ($heure > 512 - 50 || $heure < 50) {
            $pos = $heure < 50 ? $heure : 512 - $heure;
            $coeff = self::f32(self::f32(0.7 + self::f32($pos / 200.0)) + self::f32($this->IotaPaletteStd));
            $delta = self::f32(0.5 / self::NB_DEGRADE_DISTANCE);
        } else {
            $coeff = self::f32(1.0 + self::f32($this->IotaPaletteStd));
            $delta = self::f32(0.4 / self::NB_DEGRADE_DISTANCE);
        }
        $out = [];
        for ($i = 0; $i < self::NB_DEGRADE_DISTANCE; $i++) {
            $key = (int)(256.0 * $coeff);
            $out[$i] = $this->attenuees[$key] ??= $this->CalculePaletteAttenuee($this->mob3D + 1, $coeff, 33);
            $coeff = self::f32($coeff - $delta);
            if ($coeff < 0) {
                $coeff = 0.0;
            }
        }
        return $out;
    }

    /** CalculeToutDegrades: the sky and distance palettes of every hour */
    public function CalculeToutDegrades(): void
    {
        foreach ($this->tabheur as $i => $h) {
            if ($h['q']) {
                $this->tabheur[$i]['TabCouleursCiel'] = $this->CalculeDegradeDuCiel($h['pal']);
                $this->tabheur[$i]['TabPaletteDistance'] = $this->CalculeDegradeDistance($h['h']);
                if ($this->TabPaletteDistance === []) {
                    $this->FixeDegradesFonctionHeure($this->tabheur[$i]);
                }
            }
        }
        $this->CouleurOrageJour = Mac::color2Index(20000, 20000, 20000);
        $this->CouleurOrageNuit = Mac::color2Index(8000, 8000, 8000);
    }

    public function ChangeIotaPalette(float $combien): void
    {
        $this->IotaPaletteStd += $combien;
        $this->TabPaletteDistance = [];
        $this->CalculeToutDegrades();
        if ($this->dervue === self::VUE_PAYSAGE) {
            $this->affich($this->dzeta);
            $this->tfrima();
        }
    }

    /** FixeDegradesFonctionHeure: the colours of an entry of tabheur become the current ones */
    public function FixeDegradesFonctionHeure(array $chron): void
    {
        if (isset($chron['TabCouleursCiel'])) {
            $this->TabCouleursCiel = $chron['TabCouleursCiel'];
            $this->TabPaletteDistance = $chron['TabPaletteDistance'];
        }
    }

    /** CalculeEtoiles: 10000 stars in a band of 2048 x 128 */
    public function CalculeEtoiles(): void
    {
        $this->TabEtoiles = [];
        $this->bandesEtoiles = [];
        $this->CoulPulse = Mac::color2Index(65535, 65535, 65535);
        for ($i = 0; $i < self::NB_ETOILES; $i++) {
            $x = $this->myRandom(0, self::AIRE_X_ETOILE);
            $y = $this->myRandom(0, self::AIRE_Y_ETOILE);
            $m = $this->myRandom(0, self::MAX_MAGNITUDE);
            $c = 55000 - $y * intdiv(32768, self::AIRE_Y_ETOILE);
            $this->TabEtoiles[$i] = ['x' => $x, 'y' => $y, 'magnitude' => $m, 'couleur' => Mac::color2Index($c, $c, 0)];
        }
    }

    /**
     * The stars of magnitude <= $magnitude drawn in a band (mirrored: screen x shows the band at
     * (x + 768 - 2 dzeta) & 2047), twinkling variant $variant (half the stars white, at random).
     * @return array{0:string[],1:string[]} rows (2048 + 512 wide) and masks
     */
    private function bandeEtoiles(int $magnitude, int $variant): array
    {
        $key = $magnitude . '-' . $variant;
        if (isset($this->bandesEtoiles[$key])) {
            return $this->bandesEtoiles[$key];
        }
        if (count($this->bandesEtoiles) >= 8) {
            $this->bandesEtoiles = [];
        }
        $rows = array_fill(0, self::AIRE_Y_ETOILE, str_repeat("\0", 2048));
        $masks = array_fill(0, self::AIRE_Y_ETOILE, str_repeat("\xFF", 2048));
        mt_srand(1234 + $variant);
        foreach ($this->TabEtoiles as $p) {
            if ($p['magnitude'] <= $magnitude) {
                $k = (2048 - $p['x']) & 2047;
                $rows[$p['y']][$k] = chr(mt_rand(0, 255) > 127 ? $this->CoulPulse : $p['couleur']);
                $masks[$p['y']][$k] = "\0";
            }
        }
        mt_srand();
        foreach ($rows as $y => $r) {
            $rows[$y] = $r . substr($r, 0, 512);
            $masks[$y] = $masks[$y] . substr($masks[$y], 0, 512);
        }
        return $this->bandesEtoiles[$key] = [$rows, $masks];
    }

    /** AfficheEtoiles: the stars on the sky at night, a meteor sometimes, the pole star */
    public function AfficheEtoiles(): void
    {
        if (!($this->heure > 480 || $this->heure < 20)) {
            return;
        }
        if ($this->heure < self::MAX_MAGNITUDE) {
            $magnitude = self::MAX_MAGNITUDE - $this->heure;
        } elseif ($this->heure < 480 + self::MAX_MAGNITUDE) {
            $magnitude = $this->heure - (480 + self::MAX_MAGNITUDE);
        } else {
            $magnitude = self::MAX_MAGNITUDE;
        }
        if ($magnitude >= 0 && $this->TabEtoiles !== []) {
            [$rows, $masks] = $this->bandeEtoiles($magnitude, $this->Rnd() & 3);
            $port = $this->curPort;
            $s = (768 - $this->dzeta * 2) & 2047;
            for ($y = 0; $y < self::AIRE_Y_ETOILE; $y++) {
                $port->rows[$y] = (substr($port->rows[$y], 0, 512) & substr($masks[$y], $s, 512)) | substr($rows[$y], $s, 512);
            }
            $port->dirty = true;
        }
        if ($this->meteore) {
            $x = (($this->dzeta * 2 - $this->xMeteore - 512) & 2047) - 512 + 256;
            $this->Affmobbrt($this->mob3D + 21, intdiv($x, 2), intdiv($this->yMeteore, 2), 0);
            $this->meteore--;
            $this->xMeteore += 5;
            $this->yMeteore += 5;
        } elseif ($this->myRandom(0, 1000) > 990) {
            $this->xMeteore = $this->myRandom(0, self::AIRE_X_ETOILE);
            $this->yMeteore = $this->myRandom(0, intdiv(self::AIRE_Y_ETOILE, 2));
            $this->meteore = $this->myRandom(3, 6);
        }
        // the pole star
        $x = (($this->dzeta * 2 + 512) % 2048) - 512 + 256;
        $this->Affmobbrt($this->mob3D + $this->myRandom(22, 24), intdiv($x, 2), 20, 0);
    }

    // ---- the ground --------------------------------------------------------------------------------

    private function CalculeDimensionsLignes(): void
    {
        $t = &$this->TabLigneSol;
        for ($i = 1; $i < self::NB_INTERV; $i++) {
            $t[$i]['hauteur'] = self::ss($t[$i]['y'] - $t[$i - 1]['y']);
            $t[$i]['largeur'] = self::ss(intdiv(self::LARGEUR_MOB * $t[$i]['hauteur'], self::HAUTEUR_MOB));
        }
        $t[0]['hauteur'] = $t[0]['y'];
        $t[0]['largeur'] = self::ss(intdiv(self::LARGEUR_MOB * $t[0]['hauteur'], self::HAUTEUR_MOB));
    }

    /** TourneSol: the player turns his head, the bands of the ground move */
    private function TourneSol(): void
    {
        if ($this->dzeta !== $this->lastDzeta) {
            $delta = self::ss($this->dzeta - $this->lastDzeta);
            $t = &$this->TabLigneSol;
            for ($i = 1; $i < self::NB_INTERV; $i++) {
                $h = $t[$i]['hauteur'];
                if ($h) {
                    $o = self::ss($t[$i]['offsetEnMob'] + intdiv(($delta * 2) * self::HAUTEUR_MOB, $h));
                    $o %= self::LARGEUR_MOB;
                    $t[$i]['offsetEnMob'] = $o;
                    $t[$i]['offsetEcran'] = self::ss(intdiv(-$o * $h, self::HAUTEUR_MOB));
                }
            }
            $this->lastDzeta = $this->dzeta;
        }
    }

    private function CalcY(int $idx): int
    {
        $dist = $this->TabLigneSol[$idx]['dist'];
        if ($dist === 0) {
            return 0;
        }
        return self::ss(intdiv(256 * (38 + intdiv($this->zj, 20)), $dist));
    }

    /** AvanceSol: the player walks, the bands of the ground come nearer */
    public function AvanceSol(int $dist): void
    {
        if ($dist) {
            $this->SolRandSeed = self::l32($this->TickCount() + $this->xj + $this->yj);
        }
        if ($this->TabLigneSol === []) {
            for ($i = 0; $i < self::NB_INTERV; $i++) {
                $this->TabLigneSol[$i] = ['y' => 0, 'hauteur' => 0, 'largeur' => 0, 'offsetEnMob' => 0, 'offsetEcran' => 0, 'dist' => 0];
            }
        }
        $t = &$this->TabLigneSol;
        if ($t[0]['dist'] === 0) {
            $d = 70;
            for ($i = 0; $i < self::NB_INTERV; $i++) {
                $t[self::NB_INTERV - 1 - $i]['dist'] = $d;
                $t[self::NB_INTERV - 1 - $i]['y'] = $this->CalcY(self::NB_INTERV - 1 - $i);
                $d += self::LARGEUR_BANDE_SOL;
            }
        } else {
            for ($i = 0; $i < self::NB_INTERV; $i++) {
                $t[$i]['dist'] -= $dist;
                $t[$i]['y'] = $this->CalcY($i);
            }
            // the nearest band went away?
            while ($t[self::NB_INTERV - 1]['dist'] < 70) {
                for ($i = self::NB_INTERV - 1; $i > 0; $i--) {
                    $t[$i] = $t[$i - 1];
                }
                $t[0]['dist'] = $t[1]['dist'] + self::LARGEUR_BANDE_SOL;
                $t[0]['y'] = $this->CalcY(0);
            }
        }
    }

    private static function numPaletteSol(int $bottom): int
    {
        if ($bottom > self::NB_LIGNES_CIEL) {
            $n = (self::NB_LIGNES_CIEL + self::NB_LIGNES_TERRE) - $bottom;
        } else {
            $n = self::NB_LIGNES_CIEL - self::NB_LIGNES_TERRE - $bottom;
        }
        return max(0, min(self::NB_DEGRADE_DISTANCE - 1, $n));
    }

    /** AfficheSol: the bands of the ground (far: thin bands of mob 25, near: mob 20) */
    private function AfficheSol(): void
    {
        $this->AvanceSol(0);
        $this->CalculeDimensionsLignes();
        $this->TourneSol();
        $t = $this->TabLigneSol;
        for ($i = 1; $i < self::NB_INTERV && $t[$i]['hauteur'] < 4; $i++) {
        }
        if ($i < self::NB_INTERV) {
            $ymax = $t[$i]['y'] + self::NB_LIGNES_CIEL;
        } else {
            $ymax = self::NB_LIGNES_CIEL + self::NB_LIGNES_TERRE;
        }
        $largeur = intdiv(self::HAUTEUR_MIN_BANDE * self::LARGEUR_MOB * 4, self::HAUTEUR_MOB);
        $randSeedBak = $this->randSeed;
        $this->randSeed = $this->SolRandSeed;
        $map = $this->TabPaletteDistance;
        for ($j = $ymax; $j > self::NB_LIGNES_CIEL; $j -= self::HAUTEUR_MIN_BANDE) {
            $offset = (2048 - $this->dzeta * 2 + $this->Rnd()) % $largeur;
            if ($j < self::NB_LIGNES_CIEL + self::HAUTEUR_MIN_BANDE) {
                $r = [256 - $offset, self::NB_LIGNES_CIEL, 256 + $largeur - $offset, $j];
            } else {
                $r = [256 - $offset, $j - self::HAUTEUR_MIN_BANDE, 256 + $largeur - $offset, $j];
            }
            $pal = $map[self::numPaletteSol($r[3])] ?? null;
            $this->bandeSol($this->mob3D + 25, $r, $largeur, 256 - $offset, $pal);
        }
        for (; $i < self::NB_INTERV; $i++) {
            $hauteur = $t[$i]['hauteur'];
            $largeur = $t[$i]['largeur'];
            if ($hauteur > 0 && $largeur > 0) {
                $offset = $t[$i]['offsetEcran'];
                if ($i) {
                    $r = [256 - $offset, $t[$i - 1]['y'] + self::NB_LIGNES_CIEL, 256 + $largeur - $offset, $t[$i]['y'] + self::NB_LIGNES_CIEL];
                } else {
                    $r = [256 - $offset, self::NB_LIGNES_CIEL, 256 + $largeur - $offset, $t[$i]['y'] + self::NB_LIGNES_CIEL];
                }
                $pal = $map[self::numPaletteSol($r[3])] ?? null;
                $this->bandeSol($this->mob3D + 20, $r, $largeur, 256 - $offset, $pal);
            }
        }
        $this->randSeed = $randSeedBak;
    }

    /** One band of the ground: the tile repeated right then left of $start */
    private function bandeSol(int $num, array $r, int $largeur, int $start, ?string $pal): void
    {
        $rdep = $r;
        for ($k = $start; $k < 512; $k += $largeur) {
            $this->blit3d($num, $r, $pal);
            $r[0] += $largeur;
            $r[2] += $largeur;
        }
        $r = $rdep;
        for ($k = $start; $k > 0; $k -= $largeur) {
            $r[0] -= $largeur;
            $r[2] -= $largeur;
            $this->blit3d($num, $r, $pal);
        }
    }

    /**
     * Affmob3d with a cache: the rows of the mob scaled to the size of $aire (same sampling as
     * Affmob3d), clipped to the current port.
     */
    private function blit3d(int $num, array $aire, ?string $tabIndex): void
    {
        $mob = $this->TabMob[$num] ?? null;
        if ($mob === null || $mob->nbcol <= 0 || $mob->nbligne <= 0) {
            return;
        }
        [$l, $t, $r, $b] = $aire;
        $i = $r - $l;
        $j = $b - $t;
        if ($i <= 0 || $j <= 0) {
            return;
        }
        $port = $this->curPort;
        if ($l >= $port->w || $t >= $port->h || $r <= 0 || $b <= 0) {
            return;
        }
        $key = $num . '|' . $i . '|' . $j . '|' . ($tabIndex ?? '');
        if (!isset($this->cache3d[$key])) {
            if (count($this->cache3d) > 300) {
                $this->cache3d = [];
            }
            $tx = $mob->nbcol;
            $ty = $mob->nbligne;
            $pasX = intdiv($tx << 16, $i);
            $pasY = intdiv($ty << 16, $j);
            // columns: runs of the same source column
            $runs = [];
            $sx = 0;
            $rest = 0;
            for ($k = 0; $k < $i; $k++) {
                $c = min($sx, $tx - 1);
                $n = count($runs);
                if ($n && $runs[$n - 1][0] === $c) {
                    $runs[$n - 1][1]++;
                } else {
                    $runs[] = [$c, 1];
                }
                $rest += $pasX;
                $sx += $rest >> 16;
                $rest &= 0xFFFF;
            }
            $identity = $i === $tx;
            $srcRows = [];
            $rows = [];
            $masks = [];
            $sy = 0;
            $restY = 0;
            for ($y = 0; $y < $j && $sy < $ty; $y++) {
                if (!isset($srcRows[$sy])) {
                    $src = $tabIndex === null ? $mob->rows[$sy] : $mob->rowWith($sy, $tabIndex);
                    $mask = $mob->masks[$sy];
                    if (!$identity) {
                        $s = '';
                        $m = '';
                        foreach ($runs as [$c, $n]) {
                            $s .= str_repeat($src[$c], $n);
                            $m .= str_repeat($mask[$c], $n);
                        }
                        $src = $s;
                        $mask = $m;
                    }
                    $srcRows[$sy] = [$src, $mask];
                }
                $rows[$y] = $srcRows[$sy][0];
                $masks[$y] = $srcRows[$sy][1];
                $restY += $pasY;
                $sy += $restY >> 16;
                $restY &= 0xFFFF;
            }
            $this->cache3d[$key] = [$rows, $masks];
        }
        [$rows, $masks] = $this->cache3d[$key];
        $x0 = $l < 0 ? -$l : 0;
        $dl = max(0, $l);
        $w = min($port->w, $r) - $dl;
        if ($w <= 0) {
            return;
        }
        $whole = $x0 === 0 && $w === $i;
        $y1 = min($b, $port->h);
        for ($y = max(0, $t); $y < $y1; $y++) {
            $k = $y - $t;
            if (!isset($rows[$k])) {
                break;
            }
            $s = $whole ? $rows[$k] : substr($rows[$k], $x0, $w);
            $m = $whole ? $masks[$k] : substr($masks[$k], $x0, $w);
            $row = $port->rows[$y];
            $port->rows[$y] = substr_replace($row, (substr($row, $dl, $w) & $m) | $s, $dl, $w);
        }
        $port->dirty = true;
    }

    // ---- storm, rain ------------------------------------------------------------------------------

    /** TrameCielOrage: a grey checkerboard over the sky */
    private function TrameCielOrage(int $index): void
    {
        $port = $this->curPort;
        $w = $port->w;
        $c = chr($index);
        $even = str_repeat($c . "\0", intdiv($w, 2));
        $evenMask = str_repeat("\0\xFF", intdiv($w, 2));
        $odd = str_repeat("\0" . $c, intdiv($w, 2));
        $oddMask = str_repeat("\xFF\0", intdiv($w, 2));
        for ($y = 0; $y < self::NB_LIGNES_CIEL; $y += 2) {
            $port->rows[$y] = ($port->rows[$y] & $evenMask) | $even;
            $port->rows[$y + 1] = ($port->rows[$y + 1] & $oddMask) | $odd;
        }
        $port->dirty = true;
    }

    /** Flou: every third pixel takes the colour of the next one */
    private function Flou(): void
    {
        $port = $this->curPort;
        $w = $port->w;
        for ($y = 0; $y < 200 && $y < $port->h; $y++) {
            $row = $port->rows[$y];
            for ($x = 0; $x + 1 < $w; $x += 3) {
                $row[$x] = $row[$x + 1];
            }
            $port->rows[$y] = $row;
        }
        $port->dirty = true;
    }

    /** TracePluie: $n drops of rain (diagonal lines of 50 pixels) */
    private function TracePluie(int $index, int $n): void
    {
        if ($this->myRandom(0, 1000) > 500) {
            $this->Flou();
        }
        $port = $this->curPort;
        $w = $port->w;
        $size = $w * $port->h;
        $c = chr($index);
        while ($n-- > 0) {
            $x = $this->myRandom(0, $w);
            $y = $this->myRandom(0, 150);
            $off = $y * $w + $x;
            for ($j = 0; $j < 50; $j++) {
                if ($off >= 0 && $off < $size) {
                    $port->rows[intdiv($off, $w)][$off % $w] = $c;
                }
                $off += $w - 1;
            }
        }
        $port->dirty = true;
    }

    /** eff3d: the background of the view: sky, stars, sun or moon, storm, ground */
    public function eff3d(int $ciel, int $terre, int $ymeteore, int $xmeteore, int $isLune): void
    {
        $gbak = $this->curPort;
        $this->curPort = $this->Ecran1;
        if ($this->TabCouleursCiel !== []) {
            $this->RemplitZoneLignes(0, self::NB_LIGNES_CIEL, $this->TabCouleursCiel);
        }
        $this->AfficheEtoiles();
        $this->Affmobbrt($this->MOB_SOLEIL() + $isLune, self::ss($xmeteore), self::ss($ymeteore), 0);
        if ($this->FreqEclair < 1000) {
            $this->TrameCielOrage($isLune ? $this->CouleurOrageNuit : $this->CouleurOrageJour);
        }
        $this->AfficheSol();
        $this->curPort = $gbak;
    }

    /** Pluie: the rain during a storm, and water on the ground */
    public function Pluie(): void
    {
        $gbak = $this->curPort;
        $this->curPort = $this->Ecran1;
        if ($this->FreqEclair < 1000 && $this->FreqEclair > 500) {
            $n = $this->FreqEclair > 750 ? 1000 - $this->FreqEclair : $this->FreqEclair - 500;
            $this->TracePluie(($this->heure & 0x200) ? $this->CouleurOrageNuit : $this->CouleurOrageJour, $n);
            $this->PosePluie();
        }
        $this->curPort = $gbak;
    }

    /** PosePluie: when it rains there is water on the ground */
    public function PosePluie(): void
    {
        if ($this->FreqEclair < 1000 && $this->FreqEclair > 500) {
            $l = $this->liste4;
            if (($l->taille === 0 || $l->elements[$l->taille - 1] !== 2) && $l->taille < $l->maxtaille) {
                $l->taille++;
                $this->tlpos[4] = $l->taille;
                $l->elements[$l->taille - 1] = 2;
            }
        }
    }

    /** CalculeAltitude: the altitude of the player (the mountains around him) */
    private function CalculeAltitude(int $x, int $y): int
    {
        $z = 40;
        foreach ($this->montagnes as $i) {
            $p = $this->aigoual[$i];
            $f = $this->formes[$p['forme']];
            if (!$f['hauteur']) {
                $d = $this->distance($this->xj - $p['posx'], $this->yj - $p['posy']);
                $r = $f['rayon'];
                if ($d < $r) {
                    $z = self::ss($z + intdiv(($r - $d) * $f['nbrdisk'] * $p['ign'], $r));
                }
            }
        }
        return $z;
    }

    /**
     * Visu: draws the objects (indexes, far to near) on Ecran1.
     * @param int[] $liste
     */
    public function visu(array $liste): void
    {
        $gbak = $this->curPort;
        $this->curPort = $this->Ecran1;
        $this->zj = $this->CalculeAltitude($this->xj, $this->yj);
        $angle = $this->dzeta * 3.1415927 / 512.0;
        $ca = cos(-$angle);
        $sa = sin(-$angle);
        $base = $this->mob3D;
        $map = $this->TabPaletteDistance;
        foreach ($liste as $i) {
            $mont = $this->aigoual[$i];
            $forme = $this->formes[$mont['forme']];
            $p = $this->CalcPoint3D($this->xj, $this->yj, $this->zj, $ca, $sa, $mont['posx'], $mont['posy'], $forme['hauteur']);
            if ($p === null) {
                continue;
            }
            [$x, $y, $dist] = $p;
            $rd = self::ss(intdiv($forme['rayon'] * 2 * 256, $dist));
            $hauteurDisque = self::ss(intdiv($mont['ign'] * $forme['nbrdisk'] * 256, $dist));
            $r = [self::ss($x - intdiv($rd, 2)), self::ss($y - $hauteurDisque), self::ss($x + intdiv($rd, 2)), $y];
            if (!self::EmptyRect($r) && $r[0] < self::LARGEUR_ECRAN && $r[2] > 0 && $r[3] > 0 && $r[1] < self::HAUTEUR_ECRAN) {
                if ($r[3] > self::NB_LIGNES_CIEL) {
                    $n = (self::NB_LIGNES_CIEL + self::NB_LIGNES_TERRE) - $r[3];
                } else {
                    $n = $r[3] - (self::NB_LIGNES_CIEL - self::NB_LIGNES_TERRE);
                }
                $n = max(0, min(self::NB_DEGRADE_DISTANCE - 1, $n));
                $this->blit3d($base + $forme['numMob3d'], $r, $map[$n] ?? null);
            }
        }
        $this->curPort = $gbak;
    }

    /**
     * ChaineObjet for all the objects: the visible ones (near enough, not behind), sorted far to near
     * like the C chain (equal distances: the later object first). Object 0 is never drawn (C quirk).
     * @return int[]
     */
    public function ChaineObjets(): array
    {
        if ($this->nbreobj === 0) {
            return [];
        }
        $xj = $this->xj;
        $yj = $this->yj;
        $quart = ($this->dzeta >> 8) & 3;
        $order = [];
        $cand = $this->objetsProches($xj, $yj, self::GRID);
        for ($k = count($cand) - 1; $k >= 0; $k--) {
            $i = $cand[$k];
            if ($i === 0) {
                continue;
            }
            $mont = $this->aigoual[$i];
            if ($mont['ign'] === 0) {
                continue;
            }
            $forme = $this->formes[$mont['forme']];
            $this->aigoual[$i]['distance'] = $dist =$this->distance($mont['posx'] - $xj, $mont['posy'] - $yj);
            if ($dist > intdiv(256 * $forme['nbrdisk'] * $mont['ign'], 4) || $dist > intdiv(256 * $forme['rayon'], 3)) {
                continue;
            }
            $px = $mont['posx'];
            $py = $mont['posy'];
            if (($quart === 0 && $px < $xj && $py < $yj) || ($quart === 1 && $px > $xj && $py < $yj)
                || ($quart === 2 && $px > $xj && $py > $yj) || ($quart === 3 && $px < $xj && $py > $yj)) {
                continue;
            }
            $order[$i] = $dist;
        }
        arsort($order);
        return array_keys($order);
    }
}
