<?php

namespace App\Sapiens;

/**
 * SAP3D.C: the objects of the world (shapes "Forme", objects "Mont" in the list aigoual), the world
 * generation (always seeded with 'M','Y','R'), the landscape loop (clav3d), lightning, the final
 * animation (Final).
 *
 * A Mont is an array ['posx', 'posy', 'ign', 'forme' => index in $this->formes, 'distance'] and a Forme an
 * array ['name', 'nbrdisk', 'relief', 'hauteur', 'rayon', 'num_mob', 'numMob3d'] ($this->formeIdx: name =>
 * index): `$this->formes[$this->aigoual[$i]['forme']]['num_mob']` is `(*aigoual)[i].ptrforme->num_mob`.
 * The shapes are shared: changing numMob3d of one changes it for every object of that shape, like in C.
 *
 * Speed: the small static objects (trees, rocks...) are in a grid of cells (gridCell), so that a frame
 * only looks at the cells around the player (ChaineObjet); the counts of a new adventure are capped.
 */
trait World3dTrait
{
    public const NB_NUAGES = 100;
    public const NB_OISEAUX = 20;
    public const NB_ROCHERS = 300;
    public const NB_ARBRES = 800;
    public const NB_HOMMES = 0;
    public const FREQ_ORAGE = 9990;
    public const DIST_MIN_FOUDROIE = 250;
    public const NB_HERBE_AV_PLAN = 20;
    private const NB_MAX_HERBE_AV_PLAN = 100;
    /** caps of the random counts of InitDecor3d (the original goes up to 2000 / 400 / 50000 / 100000) */
    public const CAP_NUAGES = 300;
    public const CAP_OISEAUX = 100;
    public const CAP_ROCHERS = 2000;
    public const CAP_ARBRES = 3000;
    private const VITESSE_DEPLACEMENT_OISEAUX = 10;
    private const MOB_OISEAU = 28;
    private const MOB_HOMME = 35;
    private const NB_OBJ_REF = 223;
    private const GRID = 6144;

    /** name => [nbrdisk, relief, hauteur, rayon, num_mob, numMob3d] */
    private const FORMES = [
        'forme1' => [5, 0, 0, 900, 0, 1],
        'forme2' => [8, 2, 0, 1900, 1, 2],
        'forme4' => [9, 1, 0, 5000, 2, 4],
        'forme6' => [14, 1, 0, 10000, 3, 3],
        'forme7' => [16, 1, 0, 8000, 4, 5],
        'forme8' => [12, 1, 0, 2000, 5, 30],
        'nuage1' => [8, 3, 6000, 3500, -1, 7],
        'nuage2' => [9, 3, 4000, 3500, -1, 8],
        'nuage3' => [5, 3, 3000, 3500, -1, 9],
        'eclair' => [10, 3, 100, 500, -1, 36],
        'toucheEclair' => [10, 4, 2, 60, 6, 37],
        'foudroye' => [10, 4, 1, 60, 6, 38],
        'peupli' => [10, 4, 1, 60, 6, 10],
        'chene' => [10, 4, 1, 40, 6, 11],
        'noiset' => [10, 4, 1, 40, 6, 12],
        'fontaine' => [1, 4, 2, 40, -1, 24],
        'totem' => [10, 4, 2, 40, -1, 6],
        'hutte' => [1, 4, 2, 40, -1, 32],
        'oiseau' => [10, 4, 500, 40, -1, 28],
        'genevr' => [10, 4, 1, 40, 6, 13],
        'hetre' => [10, 4, 1, 40, 6, 14],
        'roc1' => [8, 1, 0, 210, 7, 15],
        'roc2' => [7, 1, 0, 600, 7, 16],
        'roc3' => [8, 1, 0, 600, 7, 17],
        'fumee' => [9, 3, 2, 40, 8, 18],
        'foyer' => [1, 4, 2, 20, -1, 33],
        'homme' => [1, 4, 1, 20, -1, 35],
        'building' => [12, 1, 0, 100, 5, 35],
        'rocher' => [1, 4, 1, 40, -1, 34],
    ];

    /** ObjetsRef: the fixed objects [posx, posy, ign, shape] */
    private const OBJETS_REF = [
        [20000, 1590, 20, 'forme1'],
        [5120, 53760, 20, 'forme2'],
        [26000, 16000, 100, 'forme4'],
        [9984, 16860, 140, 'forme6'],
        [36133, 53402, 200, 'forme7'],
        [50435, 17637, 80, 'forme8'],
        [22656, 21632, 80, 'fumee'],
        [22656, 21632, 15, 'foyer'],
        [22704, 21632, 100, 'hutte'],
        [22640, 21632, 100, 'hutte'],
        [39680, 14336, 60, 'fumee'],
        [39680, 14336, 15, 'foyer'],
        [39744, 14400, 140, 'hutte'],
        [39808, 14432, 100, 'hutte'],
        [39872, 14432, 100, 'hutte'],
        [39808, 14592, 100, 'hutte'],
        [39552, 14592, 100, 'hutte'],
        [39552, 14080, 100, 'hutte'],
        [39424, 14400, 100, 'hutte'],
        [39424, 14848, 100, 'hutte'],
        [12032, 52992, 60, 'fumee'],
        [12032, 52992, 15, 'foyer'],
        [11892, 53082, 100, 'hutte'],
        [11992, 53162, 100, 'hutte'],
        [11792, 52992, 100, 'hutte'],
        [49920, 43520, 70, 'fumee'],
        [49920, 43520, 15, 'foyer'],
        [50120, 43720, 100, 'hutte'],
        [50220, 43720, 100, 'hutte'],
        [50220, 43820, 100, 'hutte'],
        [49952, 43520, 15, 'totem'],
        [4000, 4000, 20, 'forme2'],
        [9000, 5000, 33, 'forme2'],
        [9000, 5000, 30, 'forme2'],
        [13000, 3500, 16, 'forme2'],
        [8000, 1800, 26, 'forme2'],
        [40000, 5000, 33, 'forme2'],
        [25000, 52000, 30, 'forme2'],
        [9000, 5000, 30, 'forme2'],
        [37000, 30000, 16, 'forme2'],
        [32000, 32000, 33, 'forme2'],
        [43000, 42000, 25, 'forme2'],
        [4000, 50000, 46, 'forme2'],
        [33000, 8000, 120, 'forme4'],
        [55000, 5000, 100, 'forme4'],
        [38000, 23500, 140, 'forme7'],
        [28000, 26000, 100, 'forme4'],
        [13000, 37000, 120, 'forme7'],
        [37000, 37000, 100, 'forme4'],
        [47000, 35000, 100, 'forme4'],
        [33000, 8000, 120, 'forme4'],
        [55000, 42000, 120, 'forme4'],
        [5000, 32000, 140, 'forme4'],
        [20000, 30000, 120, 'forme4'],
        [25000, 40000, 120, 'forme7'],
        [22656, 21280, 8, 'chene'],
        [22272, 20992, 8, 'genevr'],
        [22784, 20992, 8, 'peupli'],
        [44000, 45800, 20, 'totem'],
        [44000, 45900, 20, 'totem'],
        [43900, 45800, 20, 'totem'],
        [25000, 22500, 10, 'chene'],
        [19000, 24000, 8, 'chene'],
        [13000, 42500, 10, 'chene'],
        [3000, 30000, 12, 'chene'],
        [4500, 46000, 4, 'chene'],
        [17000, 44000, 15, 'chene'],
        [17500, 49000, 9, 'chene'],
        [32000, 45000, 10, 'chene'],
        [19200, 47200, 12, 'chene'],
        [9000, 45000, 7, 'chene'],
        [32100, 15050, 15, 'chene'],
        [32200, 15200, 12, 'chene'],
        [32300, 15400, 10, 'chene'],
        [32400, 15410, 10, 'chene'],
        [32000, 15600, 10, 'chene'],
        [32050, 15700, 7, 'chene'],
        [12000, 50000, 7, 'chene'],
        [32000, 15800, 10, 'chene'],
        [31900, 15900, 15, 'chene'],
        [32050, 15700, 7, 'chene'],
        [33000, 16000, 17, 'chene'],
        [33500, 16000, 10, 'chene'],
        [31900, 15900, 15, 'chene'],
        [33000, 13500, 10, 'chene'],
        [38000, 12000, 7, 'chene'],
        [32000, 15000, 7, 'chene'],
        [32800, 18100, 10, 'chene'],
        [33050, 16500, 9, 'chene'],
        [32800, 18200, 10, 'chene'],
        [35000, 16000, 7, 'chene'],
        [25000, 21500, 10, 'chene'],
        [10900, 28000, 12, 'chene'],
        [13000, 31000, 10, 'chene'],
        [15000, 26000, 10, 'chene'],
        [29000, 20500, 9, 'chene'],
        [21700, 8500, 12, 'chene'],
        [23800, 6000, 9, 'chene'],
        [20000, 5500, 9, 'chene'],
        [24000, 5000, 9, 'chene'],
        [26000, 10000, 9, 'chene'],
        [20000, 9000, 9, 'chene'],
        [15000, 6000, 9, 'chene'],
        [15500, 47000, 15, 'peupli'],
        [24000, 48500, 20, 'peupli'],
        [20500, 18000, 12, 'peupli'],
        [40000, 200, 18, 'peupli'],
        [34000, 2000, 16, 'peupli'],
        [32000, 1000, 12, 'peupli'],
        [100, 41000, 12, 'peupli'],
        [22000, 25000, 18, 'peupli'],
        [21000, 21000, 20, 'peupli'],
        [13500, 44000, 12, 'peupli'],
        [33500, 19000, 12, 'peupli'],
        [42000, 17000, 20, 'peupli'],
        [34000, 17000, 12, 'peupli'],
        [38000, 16000, 9, 'peupli'],
        [39050, 14050, 12, 'peupli'],
        [21000, 23000, 14, 'peupli'],
        [12100, 29050, 12, 'peupli'],
        [11000, 30000, 20, 'peupli'],
        [13000, 46000, 10, 'hetre'],
        [17500, 45000, 15, 'hetre'],
        [19000, 47000, 10, 'hetre'],
        [19000, 48500, 18, 'hetre'],
        [22000, 23000, 10, 'hetre'],
        [13000, 42500, 15, 'hetre'],
        [3500, 47000, 10, 'hetre'],
        [7000, 41000, 11, 'hetre'],
        [32900, 17000, 15, 'hetre'],
        [40000, 14000, 11, 'hetre'],
        [40000, 15000, 8, 'hetre'],
        [39000, 12200, 11, 'hetre'],
        [32800, 18000, 15, 'hetre'],
        [42000, 15000, 18, 'hetre'],
        [41500, 14000, 15, 'hetre'],
        [42500, 16000, 15, 'hetre'],
        [31500, 22000, 15, 'hetre'],
        [12000, 29000, 17, 'hetre'],
        [11900, 29100, 15, 'hetre'],
        [12500, 30000, 15, 'hetre'],
        [5000, 39000, 10, 'genevr'],
        [19250, 49200, 15, 'genevr'],
        [35000, 43000, 4, 'genevr'],
        [41000, 2000, 2, 'genevr'],
        [4000, 40000, 6, 'genevr'],
        [14000, 45000, 2, 'genevr'],
        [14000, 45000, 12, 'genevr'],
        [19100, 47050, 10, 'genevr'],
        [31500, 14500, 10, 'genevr'],
        [34000, 15000, 10, 'genevr'],
        [36000, 17000, 10, 'genevr'],
        [40000, 18000, 10, 'genevr'],
        [31000, 19000, 12, 'genevr'],
        [14000, 30000, 10, 'genevr'],
        [10000, 29500, 10, 'genevr'],
        [13000, 27000, 10, 'genevr'],
        [40000, 42000, 10, 'genevr'],
        [41000, 44000, 10, 'genevr'],
        [47000, 45000, 10, 'genevr'],
        [48000, 44000, 10, 'genevr'],
        [48000, 48000, 10, 'genevr'],
        [21000, 20000, 10, 'noiset'],
        [21000, 20000, 10, 'fontaine'],
        [12400, 28500, 10, 'noiset'],
        [12400, 28500, 10, 'fontaine'],
        [48000, 42000, 10, 'noiset'],
        [48000, 42000, 10, 'fontaine'],
        [2000, 2000, 10, 'noiset'],
        [2000, 2000, 10, 'fontaine'],
        [39000, 800, 10, 'noiset'],
        [39000, 800, 10, 'fontaine'],
        [16000, 42000, 10, 'noiset'],
        [16000, 42000, 10, 'fontaine'],
        [32000, 16000, 10, 'noiset'],
        [32000, 16000, 10, 'fontaine'],
        [45000, 25500, 10, 'noiset'],
        [45000, 25500, 10, 'fontaine'],
        [6000, 45000, 10, 'noiset'],
        [6000, 45000, 10, 'fontaine'],
        [50000, 50000, 10, 'noiset'],
        [50000, 50000, 5, 'fontaine'],
        [24320, 18944, 10, 'noiset'],
        [24320, 18944, 10, 'fontaine'],
        [32768, 17408, 10, 'noiset'],
        [32768, 17408, 5, 'fontaine'],
        [40192, 14336, 10, 'noiset'],
        [40192, 14336, 5, 'fontaine'],
        [43776, 16896, 10, 'noiset'],
        [43776, 16896, 5, 'fontaine'],
        [100, 39000, 100, 'roc1'],
        [15000, 46000, 45, 'roc2'],
        [42000, 16000, 75, 'roc3'],
        [33050, 17000, 95, 'roc1'],
        [5000, 38000, 100, 'roc2'],
        [7000, 39000, 150, 'roc3'],
        [4000, 43000, 110, 'roc1'],
        [7000, 44000, 100, 'roc2'],
        [10000, 42000, 150, 'roc1'],
        [15000, 43000, 100, 'roc2'],
        [15000, 45000, 140, 'roc1'],
        [18000, 47000, 98, 'roc2'],
        [19000, 50000, 100, 'roc1'],
        [42000, 46000, 100, 'roc1'],
        [44000, 45000, 200, 'roc2'],
        [46000, 42000, 100, 'roc2'],
        [38000, 44000, 90, 'roc1'],
        [35000, 1000, 110, 'roc2'],
        [37000, 3000, 100, 'roc2'],
        [36000, 15000, 110, 'roc2'],
        [37000, 3000, 100, 'roc2'],
        [39000, 13000, 100, 'roc2'],
        [41000, 13000, 100, 'roc2'],
        [36000, 18000, 100, 'roc2'],
        [39000, 17000, 100, 'roc2'],
        [38000, 12000, 132, 'roc2'],
        [42000, 15000, 100, 'roc2'],
        [20000, 22000, 100, 'roc2'],
        [13000, 28000, 100, 'roc2'],
        [11000, 32000, 100, 'roc2'],
        [46000, 47500, 100, 'roc2'],
        [9500, 50000, 100, 'roc3'],
        [1, 1, 10, 'chene'],
    ];

    /** @var array<int, array<string, mixed>> the shapes (struct Forme) */
    public array $formes = [];
    /** @var array<string, int> name of a shape => index in $formes */
    public array $formeIdx = [];
    /** @var array<int, array{posx:int, posy:int, ign:int, forme:int, distance:int}> MontHandle aigoual: the objects of the world */
    public array $aigoual = [];
    public int $nbreobj = 0;
    public int $nbNuages = 0;
    public int $nbEclairs = 0;
    public int $nbOiseaux = 0;
    public int $nbRochers = 0;
    public int $nbHommes = 0;
    public int $nbArbres = 0;
    public int $FreqOrage = 0;
    public int $FreqEclair = 0;
    public int $DistMinFoudroie = 0;
    public int $VitesseDeplacementNuages = 0;
    private int $PremEclair = 0;
    private int $PremArbre = 0;
    private int $PremRocher = 0;
    /** index of the tree struck (-1: none) */
    private int $ArbreFoudroye = -1;
    private int $okcarte = 0;
    /** @var int[] indexes of the objects of height 0 (mountains: altitude, EstDansMontagne) */
    private array $montagnes = [];
    /** @var array<int, int[]> grid cell => indexes of small static objects */
    private array $grid = [];
    /** @var int[] the other objects (looked at every frame) */
    private array $horsGrille = [];
    private bool $gridDirty = true;

    private function initFormes(): void
    {
        if ($this->formes !== []) {
            return;
        }
        foreach (self::FORMES as $name => [$nb, $relief, $hauteur, $rayon, $numMob, $mob3d]) {
            $this->formeIdx[$name] = count($this->formes);
            $this->formes[] = [
                'name' => $name, 'nbrdisk' => $nb, 'relief' => $relief, 'hauteur' => $hauteur,
                'rayon' => $rayon, 'num_mob' => $numMob, 'numMob3d' => $mob3d,
            ];
        }
    }

    private function newMont(int $x, int $y, int $ign, int $forme): array
    {
        return ['posx' => $x & 0xFFFF, 'posy' => $y & 0xFFFF, 'ign' => $ign & 0xFFFF, 'forme' => $forme, 'distance' => 0];
    }

    /** AjouteObjet3d: adds $nbObj objects, returns the index of the first one */
    public function AjouteObjet3d(int $nbObj): int
    {
        $this->nbreobj += $nbObj;
        $this->gridDirty = true;
        return $this->nbreobj - $nbObj;
    }

    /** Sets the object $index (and keeps the list of the mountains) */
    private function poseObjet(int $index, array $mont): void
    {
        $this->aigoual[$index] = $mont;
        if ($this->formes[$mont['forme']]['hauteur'] === 0) {
            $this->montagnes[] = $index;
        }
    }

    /** EstDansMontagne: the position is inside a mountain */
    private function EstDansMontagne(int $x, int $y): bool
    {
        foreach ($this->montagnes as $i) {
            $m = $this->aigoual[$i];
            if ($this->formes[$m['forme']]['hauteur'] === 0) {
                $dist = $this->distance(abs($m['posx'] - $x), abs($m['posy'] - $y));
                if ($dist < $this->formes[$m['forme']]['rayon']) {
                    return true;
                }
            }
        }
        return false;
    }

    /** DefautDecor3d: the world of the first adventure */
    public function DefautDecor3d(): void
    {
        $this->FreqOrage = self::FREQ_ORAGE;
        $this->DistMinFoudroie = self::DIST_MIN_FOUDROIE;
        $this->nbNuages = self::NB_NUAGES;
        $this->nbOiseaux = self::NB_OISEAUX;
        $this->nbRochers = self::NB_ROCHERS;
        $this->nbHommes = self::NB_HOMMES;
        $this->nbArbres = self::NB_ARBRES;
        $this->nbHerbes = self::NB_HERBE_AV_PLAN;
        $this->FreqEclair = 1000;
        $this->VitesseDeplacementNuages = 20;
    }

    /** InitDecor3d: random counts for each new adventure (capped, see CAP_*) */
    public function InitDecor3d(): void
    {
        $this->FreqOrage = $this->myRandom(9900, 10000);
        $this->DistMinFoudroie = $this->myRandom(20, 500);
        $this->nbNuages = min(self::CAP_NUAGES, $this->myRandom(self::NB_NUAGES, self::NB_NUAGES * 20));
        $this->nbOiseaux = min(self::CAP_OISEAUX, $this->myRandom(self::NB_OISEAUX, self::NB_OISEAUX * 20));
        $this->nbRochers = min(self::CAP_ROCHERS, $this->myRandom(self::NB_ROCHERS, 50000));
        $this->nbArbres = min(self::CAP_ARBRES, $this->myRandom(self::NB_ARBRES, 100000));
        $this->nbHerbes = $this->myRandom(self::NB_HERBE_AV_PLAN, self::NB_MAX_HERBE_AV_PLAN);
        $this->nbHommes = 0;
        $this->FreqEclair = 1000;
        $this->VitesseDeplacementNuages = $this->myRandom(1, 120);
    }

    /** Applies the caps to counts read from a saved game */
    public function capDecor3d(): void
    {
        $this->nbNuages = max(0, min(self::CAP_NUAGES, $this->nbNuages));
        $this->nbOiseaux = max(0, min(self::CAP_OISEAUX, $this->nbOiseaux));
        $this->nbRochers = max(0, min(self::CAP_ROCHERS, $this->nbRochers));
        $this->nbArbres = max(0, min(self::CAP_ARBRES, $this->nbArbres));
        $this->nbHommes = 0;
    }

    /** CreeDecor3d: builds the list of the objects of the world */
    public function CreeDecor3d(bool $display): void
    {
        $this->initFormes();
        if ($display) {
            $this->flefba = 0;
            $this->comment(137);
        }
        $this->capDecor3d();
        $this->initrnd(ord('M'), ord('Y'), ord('R'));
        $this->nbEclairs = $this->nbNuages;
        $this->aigoual = [];
        $this->montagnes = [];
        $this->PremEclair = 0;
        $this->PremArbre = 0;
        $this->PremRocher = 0;
        $this->ArbreFoudroye = -1;
        $this->nbreobj = 0;
        $f = $this->formeIdx;
        for ($i = 0; $i < $this->nbNuages; $i++) {
            $x = $this->myRandom(0, 32767) * 2;
            $y = $this->myRandom(0, 32767) * 2;
            $ign = $this->myRandom(100, 200);
            $forme = match ($this->myRandom(0, 3)) {
                0 => $f['nuage1'],
                1 => $f['nuage2'],
                default => $f['nuage3'],
            };
            $this->poseObjet($this->AjouteObjet3d(1), $this->newMont($x, $y, $ign, $forme));
        }
        // a lightning for each cloud
        for ($i = 0; $i < $this->nbEclairs; $i++) {
            $n = $this->aigoual[$i];
            $half = intdiv($this->formes[$n['forme']]['rayon'], 2);
            $index = $this->AjouteObjet3d(1);
            $this->poseObjet($index, $this->newMont($n['posx'] + $half, $n['posy'] + $half, 0, $f['eclair']));
            if ($this->PremEclair === 0) {
                $this->PremEclair = $index;
            }
        }
        // the birds
        for ($i = 0; $i < $this->nbOiseaux; $i++) {
            $x = $this->myRandom(0, 32767) * 2;
            $y = $this->myRandom(0, 32767) * 2;
            $this->poseObjet($this->AjouteObjet3d(1), $this->newMont($x, $y, 10, $f['oiseau']));
        }
        $index = $this->AjouteObjet3d(self::NB_OBJ_REF);
        foreach (self::OBJETS_REF as $k => [$x, $y, $ign, $name]) {
            $this->poseObjet($index + $k, $this->newMont($x, $y, $ign, $f[$name]));
        }
        // the trees
        for ($i = 0; $i < $this->nbArbres; $i++) {
            do {
                $x = $this->myRandom(0, 32767) * 2;
                $y = $this->myRandom(0, 32767) * 2;
            } while ($this->EstDansMontagne($x, $y));
            $ign = $this->myRandom(5, 20);
            $forme = match ($this->myRandom(0, 3)) {
                0 => $f['genevr'],
                1 => $f['chene'],
                default => $f['peupli'],
            };
            $index = $this->AjouteObjet3d(1);
            if ($this->PremArbre === 0) {
                $this->PremArbre = $index;
            }
            $this->poseObjet($index, $this->newMont($x, $y, $ign, $forme));
        }
        // the rocks
        for ($i = 0; $i < $this->nbRochers; $i++) {
            do {
                $x = $this->myRandom(0, 32767) * 2;
                $y = $this->myRandom(0, 32767) * 2;
            } while ($this->EstDansMontagne($x, $y));
            $ign = $this->myRandom(15, 60);
            $index = $this->AjouteObjet3d(1);
            if ($this->PremRocher === 0) {
                $this->PremRocher = $index;
            }
            $this->poseObjet($index, $this->newMont($x, $y, $ign, $f['rocher']));
        }
        $this->gridDirty = true;
    }

    /** The grid of the small static objects (rebuilt when objects were moved / added) */
    private function construitGrille(): void
    {
        $this->grid = [];
        $this->horsGrille = [];
        $mobiles = $this->nbNuages + $this->nbEclairs + $this->nbOiseaux;
        for ($i = 1; $i < $this->nbreobj; $i++) {
            $m = $this->aigoual[$i];
            $fo = $this->formes[$m['forme']];
            // farthest distance at which ChaineObjet keeps it
            $max = min(intdiv(256 * $fo['nbrdisk'] * 65535, 4), intdiv(256 * $fo['rayon'], 3));
            if ($i < $mobiles || $max > self::GRID) {
                $this->horsGrille[] = $i;
            } else {
                $this->grid[intdiv($m['posx'], self::GRID) * 16 + intdiv($m['posy'], self::GRID)][] = $i;
            }
        }
        $this->gridDirty = false;
    }

    /**
     * The objects that can be within $r of (x, y): all those outside the grid and those of the
     * cells around, in increasing index order.
     * @return int[]
     */
    private function objetsProches(int $x, int $y, int $r): array
    {
        if ($this->gridDirty) {
            $this->construitGrille();
        }
        $list = $this->horsGrille;
        $cx0 = intdiv(max(0, $x - $r), self::GRID);
        $cx1 = intdiv(min(65535, $x + $r), self::GRID);
        $cy0 = intdiv(max(0, $y - $r), self::GRID);
        $cy1 = intdiv(min(65535, $y + $r), self::GRID);
        for ($cx = $cx0; $cx <= $cx1; $cx++) {
            for ($cy = $cy0; $cy <= $cy1; $cy++) {
                foreach ($this->grid[$cx * 16 + $cy] ?? [] as $i) {
                    $list[] = $i;
                }
            }
        }
        sort($list);
        return $list;
    }

    /** WaitTick: waits $nb 1/60 s */
    public function WaitTick(int $nb): \Generator
    {
        $t = $this->TickCount();
        while ($this->TickCount() < $t + $nb) {
            yield;
        }
    }

    /** One frame of the final animation: the landscape, then the time passes */
    private function finalImage(int $wait, bool $chrono): \Generator
    {
        $this->affich($this->dzeta);
        $this->tfrima();
        yield from $this->delai(0);
        if ($chrono) {
            $this->chrono(1);
        }
        yield from $this->WaitTick($wait);
    }

    /** Final: the end of the game (the time passes over the land of the tribe) */
    public function Final(): \Generator
    {
        $this->DefautDecor3d();
        $this->heure = 0;
        while ($this->heure < 40) {
            $this->chrono(1);
        }
        $this->jour_dsk = 0;
        $this->xj = 0x5800;
        $this->yj = 0x5400;
        $this->dzeta = 768;
        $this->zj = 40;
        $this->clrbadi();
        $this->comment(133);
        $a = &$this->aigoual;
        for ($n = 0; $n < 5; $n++) {
            // the trees grow in number
            for ($t = 0; $t < 15; $t++) {
                for ($i = 0; $i < $this->nbNuages; $i++) {
                    $a[$i]['posx'] = ($this->xj + $this->myRandom(-5000, 5000)) & 0xFFFF;
                    $a[$i]['posy'] = ($this->yj + $this->myRandom(-5000, 5000)) & 0xFFFF;
                }
                $this->GereInterface();
                for ($i = $this->PremArbre; $i < $this->PremArbre + $this->nbArbres; $i++) {
                    $dist = $this->distance(abs($a[$i]['posx'] - $this->xj), abs($a[$i]['posy'] - $this->yj));
                    if ($dist > 2000 && $this->myRandom(0, 1000) > 900) {
                        $a[$i]['posx'] = ($this->xj + $this->myRandom(-750, 750)) & 0xFFFF;
                        $a[$i]['posy'] = ($this->yj + $this->myRandom(-5000, -500)) & 0xFFFF;
                        $dist = $this->distance(abs($a[$i]['posx'] - $this->xj), abs($a[$i]['posy'] - $this->yj));
                        if ($dist < 50) {
                            $a[$i]['posx'] = 65000;
                            $a[$i]['posy'] = 65000;
                        }
                    }
                }
                $this->gridDirty = true;
                $this->AvanceSol(1);
                $this->heure = $this->myRandom(0, 90);
                yield from $this->finalImage(5, false);
            }
            // erosion of the mountains
            for ($i = 0; $i < $this->nbreobj; $i++) {
                if ($this->formes[$a[$i]['forme']]['hauteur'] === 0 && $a[$i]['ign'] > 10) {
                    $a[$i]['ign'] -= 10;
                }
            }
            for ($i = $this->PremRocher; $i < $this->PremRocher + $this->nbRochers; $i++) {
                $dist = $this->distance(abs($a[$i]['posx'] - $this->xj), abs($a[$i]['posy'] - $this->yj));
                if ($dist > 2000 && $this->myRandom(0, 1000) > 900) {
                    $a[$i]['posx'] = ($this->xj + $this->myRandom(-1000, 1000)) & 0xFFFF;
                    $a[$i]['posy'] = ($this->yj + $this->myRandom(-1000, -100)) & 0xFFFF;
                    $dist = $this->distance(abs($a[$i]['posx'] - $this->xj), abs($a[$i]['posy'] - $this->yj));
                    if ($dist < 50) {
                        $a[$i]['posx'] = 65000;
                        $a[$i]['posy'] = 65000;
                    }
                }
            }
            $this->gridDirty = true;
            // the trees decrease in number
            for ($t = 0; $t < 15; $t++) {
                for ($i = 0; $i < $this->nbNuages; $i++) {
                    $a[$i]['posx'] = ($this->xj + $this->myRandom(-5000, 5000)) & 0xFFFF;
                    $a[$i]['posy'] = ($this->yj + $this->myRandom(-5000, 5000)) & 0xFFFF;
                }
                $this->GereInterface();
                for ($i = $this->PremArbre; $i < $this->PremArbre + $this->nbArbres; $i++) {
                    $dist = $this->distance(abs($a[$i]['posx'] - $this->xj), abs($a[$i]['posy'] - $this->yj));
                    if ($dist < 3000 && $this->myRandom(0, 1000) > 900) {
                        $a[$i]['posx'] = 65000;
                        $a[$i]['posy'] = 65000;
                    }
                }
                $this->gridDirty = true;
                $this->heure = $this->myRandom(0, 90);
                $this->AvanceSol(1);
                yield from $this->finalImage(5, false);
            }
        }
        for ($i = 0; $i < $this->nbNuages; $i++) {
            $a[$i]['posx'] = ($this->xj + $this->myRandom(-2000, 2000)) & 0xFFFF;
            $a[$i]['posy'] = ($this->yj + $this->myRandom(-2000, 2000)) & 0xFFFF;
        }
        for ($n = 0; $n < 100; $n++) {
            $this->FreqEclair = 900;
            for ($i = $this->PremRocher; $i < $this->PremRocher + $this->nbRochers; $i++) {
                if ($this->myRandom(0, 1000) > 900) {
                    $a[$i]['posx'] = 65000;
                    $a[$i]['posy'] = 65000;
                }
            }
            for ($i = $this->PremArbre; $i < $this->PremArbre + $this->nbArbres; $i++) {
                if ($this->myRandom(0, 1000) > 900) {
                    $a[$i]['posx'] = 65000;
                    $a[$i]['posy'] = 65000;
                }
            }
            // buildings appear
            $ign = $this->myRandom(15, 60);
            $x = $this->xj + $this->myRandom(-750, 750);
            $y = $this->yj + $this->myRandom(-10000, -500);
            $this->poseObjet($this->AjouteObjet3d(1), $this->newMont($x, $y, $ign, $this->formeIdx['building']));
            $this->heure = $this->myRandom(0, 90);
            yield from $this->finalImage(5, true);
        }
        unset($a);
        $this->heure = 512;
        for ($n = 0; $n < 400; $n++) {
            $this->yj = ($this->yj - 30) & 0xFFFF;
            yield from $this->finalImage(2, true);
        }
        // THE END, in the font of the game four times bigger, from black to white
        $chaine = 'THE END';
        $w = 0;
        for ($k = 0; $k < strlen($chaine); $k++) {
            $w += $this->TailleCar(ord($chaine[$k]), 14) * 4;
        }
        $dx = intdiv(512 - $w, 2);
        for ($n = 0; $n < 65535; $n += 256) {
            $this->chrono(1);
            $this->chrono(1);
            $color = chr(Mac::color2Index($n, $n, $n));
            $this->affich($this->dzeta);
            yield from $this->delai(0);
            $gbak = $this->curPort;
            $this->curPort = $this->Ecran1;
            $x = $dx;
            for ($k = 0; $k < strlen($chaine); $k++) {
                $c = ord($chaine[$k]);
                $num = $this->Fonte14 + $c - 31;
                $mob = $this->TabMob[$num] ?? null;
                if ($mob !== null && $c !== 32) {
                    $this->Affmob3d($num, [$x, 64 - 4 * $mob->nbligne, $x + 4 * $mob->nbcol, 64], str_repeat($color, 256));
                }
                $x += $this->TailleCar($c, 14) * 4;
            }
            $this->curPort = $gbak;
            $this->tfrima();
            yield from $this->WaitTick(1);
        }
        $this->CarboniseSetPortWindow();
    }

    /** FoudroieJoueur: the player is struck if the lightning falls near him */
    private function FoudroieJoueur(int $x, int $y): void
    {
        if ($this->distance($this->xj - $x, $this->yj - $y) < $this->DistMinFoudroie) {
            $this->clrbadi();
            $this->comment(134);
            // the original flashes 100 times without waiting: the last image stays
            $this->effima(0x077);
            $this->tfrima();
            $this->effima(0x000);
            $this->tfrima();
            $this->mort = 0;
        }
    }

    /** FoudroieArbre: the lightning falls at (x, y): on the first tree near, and maybe on the player */
    private function FoudroieArbre(int $x, int $y): int
    {
        $this->FoudroieJoueur($x, $y);
        // object 0 is not in the lists of objetsProches
        $liste = $this->objetsProches($x, $y, max(0, $this->DistMinFoudroie));
        if ($this->nbreobj > 0) {
            array_unshift($liste, 0);
        }
        foreach ($liste as $i) {
            $mont = $this->aigoual[$i];
            if ($this->formes[$mont['forme']]['hauteur'] === 1 && $this->distance($mont['posx'] - $x, $mont['posy'] - $y) < $this->DistMinFoudroie) {
                $this->aigoual[$i]['forme'] = $this->formeIdx['toucheEclair'];
                $this->FoudroieJoueur($mont['posx'], $mont['posy']);
                return $i;
            }
        }
        return -1;
    }

    /**
     * ChargeEltsDecorsMobiles: the variable parts of the world from the end of a saved game
     * ("MAGIC" then "Foudroye x y" lines): the trees struck by lightning.
     */
    public function ChargeEltsDecorsMobiles(string $text): void
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        if (($words[0] ?? '') !== 'MAGIC') {
            return;
        }
        $n = count($words);
        for ($k = 1; $k < $n; $k++) {
            if ($words[$k] === 'Foudroye' && $k + 2 < $n) {
                $x = (int)$words[$k + 1];
                $y = (int)$words[$k + 2];
                $k += 2;
                foreach ($this->aigoual as $i => $mont) {
                    if ($this->formes[$mont['forme']]['hauteur'] === 1 && $mont['posx'] === $x && $mont['posy'] === $y) {
                        $this->aigoual[$i]['forme'] = $this->formeIdx['foudroye'];
                    }
                }
            }
        }
        $this->gridDirty = true;
    }

    /** SauveEltsDecorsMobiles: the text written at the end of a saved game */
    public function SauveEltsDecorsMobiles(): string
    {
        $s = "\nMAGIC\n";
        $foudroye = $this->formeIdx['foudroye'] ?? -1;
        foreach ($this->aigoual as $mont) {
            if ($mont['forme'] === $foudroye) {
                $s .= 'Foudroye ' . $mont['posx'] . ' ' . $mont['posy'] . "\n";
            }
        }
        return $s;
    }

    /** AFFICH: the landscape seen with the angle $tfr, drawn on Ecran1 */
    public function affich(int $tfr): void
    {
        $this->initFormes();
        $dzetabak = $this->dzeta;
        $this->dzeta = $tfr;
        $this->eff3d($this->ciel, $this->terre, $this->metpos, ($tfr + 128 - $this->angsol) & 0xFFFF, ($this->heure & 0x200) === 0x200 ? 1 : 0);
        // smoke, flames, birds
        $f = $this->formes;
        $fi = $this->formeIdx;
        $f = &$this->formes;
        $f[$fi['fumee']]['numMob3d'] = $this->myRandom(18, 20);
        if (++$f[$fi['forme8']]['numMob3d'] === 32) {
            $f[$fi['forme8']]['numMob3d'] = 30;
        }
        if (++$f[$fi['oiseau']]['numMob3d'] === self::MOB_OISEAU + 2) {
            $f[$fi['oiseau']]['numMob3d'] = self::MOB_OISEAU;
        }
        if (++$f[$fi['homme']]['numMob3d'] === self::MOB_HOMME + 2) {
            $f[$fi['homme']]['numMob3d'] = self::MOB_HOMME;
        }
        unset($f);
        $a = &$this->aigoual;
        // the clouds go west
        for ($i = 0; $i < $this->nbNuages; $i++) {
            $a[$i]['posx'] = ($a[$i]['posx'] + $this->VitesseDeplacementNuages) & 0xFFFF;
        }
        if ($this->FreqEclair > 1000) {
            // no storm: a new one?
            if ($this->myRandom(0, 10000) > $this->FreqOrage) {
                $this->FreqEclair = 0;
            }
        } else {
            $this->FreqEclair += 4;
        }
        if ($this->ArbreFoudroye >= 0) {
            $this->aigoual[$this->ArbreFoudroye]['forme'] = $fi['foudroye'];
            $this->ArbreFoudroye = -1;
        }
        for ($i = 0; $i < $this->nbEclairs; $i++) {
            $s = &$a[$this->PremEclair + $i];
            if ($s['ign']) {
                // end of the lightning
                if ($this->ArbreFoudroye < 0) {
                    $this->ArbreFoudroye = $this->FoudroieArbre($s['posx'], $s['posy']);
                }
                $s['ign'] = 0;
            } elseif ($this->myRandom(0, 1000) > $this->FreqEclair && $this->myRandom(0, 1000) > 910) {
                $s['ign'] = 300;
            }
            // the lightning stays under its cloud
            $s['posx'] = ($s['posx'] + $this->VitesseDeplacementNuages) & 0xFFFF;
            unset($s);
        }
        // the birds go south
        for ($i = 0; $i < $this->nbOiseaux; $i++) {
            $a[$this->nbNuages + $i]['posy'] = ($a[$this->nbNuages + $i]['posy'] + self::VITESSE_DEPLACEMENT_OISEAUX) & 0xFFFF;
        }
        unset($a);
        // sort the visible objects (far to near) and draw them
        $this->visu($this->ChaineObjets());
        $this->Pluie();
        $this->dzeta = $dzetabak;
    }

    /** CLAV3D: the landscape view, until a presence, the death or the exit (generator) */
    public function clav3d(): \Generator
    {
        $cptemps = 1;
        $cpt_cart = 0;
        $cont = true;
        $this->dismin = 0;
        // only the column of the mouse is reset
        do {
            $this->vq_mouse($this->bouton, $this->dsposx, $this->sposy);
            if ($this->bouton) {
                yield;
            }
        } while ($this->bouton);
        do {
            $posTimer = $this->sapiensTimer;
            $this->affich($this->dzeta);
            $this->tfrima();
            if ($this->okcarte) {
                if (!($cpt_cart--)) {
                    $cpt_cart = 5;
                    yield from $this->carte();
                }
            }
            $this->sapdep($this->dzeta, $this->sapvit);
            $cptemps = ($cptemps - 1) & 0xFF;
            if (!$cptemps) {
                $cptemps = 5;
                $this->chrono(0);
            } else {
                $this->chrono(1);
            }
            yield from $this->zoninf();
            $this->PosePluie();
            $car = $this->getkey3d();
            if ($car) {
                if (($car & 1) && $this->sapvit < 20) {
                    $this->icone = $this->mobBambou + 23;
                    $this->sapvit += 2;
                }
                if (($car & 2) && $this->sapvit) {
                    $this->icone = $this->mobBambou + 24;
                    if ($this->sapvit < 4) {
                        $this->sapvit = 0;
                    } else {
                        $this->sapvit -= 4;
                    }
                }
                if ($car & 8) {
                    $this->icone = $this->mobBambou + 26;
                    $this->dzeta = ($this->dzeta - 8) & 0x3FF;
                }
                if ($car & 4) {
                    $this->icone = $this->mobBambou + 25;
                    $this->dzeta = ($this->dzeta + 8) & 0x3FF;
                }
                if ($car & 32) {
                    // anti repeat
                    while ($this->getkey3d() & 32) {
                        yield;
                    }
                    $this->okcarte ^= 1;
                    if ($this->okcarte) {
                        $cpt_cart = 0;
                    } else {
                        $this->affomb($this->DEBFLECH() + 17, 180, 280, 5, 5, true);
                    }
                }
                if ($car & 64) {
                    $cont = false;
                }
            } else {
                $this->icone = $this->mobBambou + 20;
            }
            // frame synchronisation (at least 9 ticks of 11 ms)
            do {
                yield;
            } while ($posTimer > $this->sapiensTimer - 9);
        } while (!$this->presence && $this->mort && $cont);
        $this->tfrvirt();
        if ($this->presence <= 4 && $this->presence) {
            // the menu 'say' by default
            $this->InverseOption(0, 4);
            $this->s_action = 5;
            $this->inimen(2);
        }
    }
}
