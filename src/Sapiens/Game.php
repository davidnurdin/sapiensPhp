<?php

namespace App\Sapiens;

use JumPHP\Save\Options;

/**
 * SAPIENS (Didier & Olivier Guillion, 1986-2017, GPL v3) ported to PHP from the C sources of 2018.
 *
 * One trait per C file, with the C names: this class holds the globals of SAPGLOB.C / SAPIENS.H.
 * The C code waits in loops (mouse, timer...): those functions are generators here, called with
 * `yield from`; each `yield` ends a frame (the scene shows mainWindow, then resumes the game).
 * main() is the whole game.
 */
final class Game
{
    use PlatformTrait;
    use GfxTrait;
    use TextTrait;
    use ListTrait;
    use RelTrait;
    use CombTrait;
    use GestTrait;
    use World3dTrait;
    use Render3dTrait;
    use TreeTrait;
    use MapTrait;
    use MusicTrait;

    public const VUE_AUCUNE = 0;
    public const VUE_PAYSAGE = 1;
    public const VUE_LATERALE = 2;
    public const FORME_BUSTE = 3;
    public const FORME_TETE = 4;
    public const FORME_ARME = 5;
    public const JOUEUR2_AUTONOME = 0;
    public const JOUEUR2_CLAVIER = 1;
    public const SAGAIE = 23;
    public const DSTSAG = 10;
    public const DSTFRA = 4;
    public const NBREVTHEURE = 37;
    public const NB_LIGNES_CIEL = 128;
    public const NB_LIGNES_TERRE = 72;
    public const NB_DEGRADE_DISTANCE = 72;
    public const NBREVTDESTINE = 40;
    public const GEMNBRECAD = 4;
    public const GEMINTERV = 4;
    /** gemaction: 19,1,1,14 */
    public const GEMACTION = [19, 1, 1, 14];
    public const NB_MOTS_BKMOTS = 46;

    /** callbacks to the scene: open the manual, quit */
    public ?\Closure $onHelp = null;
    public ?\Closure $onQuit = null;

    // ---- mob bases (SAPWIND.C InitMobs) ----
    public int $mobBambou = 0;
    public int $mobFace = 0;
    public int $mobFeuille = 0;
    public int $mobFleches = 0;
    public int $mobSapmob = 0;
    public int $mobAvplan = 0;
    public int $mobAvplanSpec = 0;
    public int $mobSilex = 0;
    public int $mob3D = 0;
    public int $Fonte14 = 0;
    public int $Fonte10 = 0;
    public int $nbMobHerbes = 0;
    public int $nbAvplanSpec = 0;

    // ---- SAPGLOB.C ----
    public int $lpos = 1;
    public int $stock1 = 0;
    public int $stock2 = 0;
    public int $criter = 0;
    public int $lastmen = 255;
    public int $rega = 0;
    public int $who = 0;
    public int $dzeta = 0;
    public int $ldzeta = 0;
    public int $type_synthe = 0;
    /** @var int[] */
    public array $profond = [10, 8, 6, 4, 2];
    /** objet prefere */
    public array $tabprf = [25, 33, 34, 35];
    /** objets de la tribu */
    public array $tobje = [[23, 26, 28, 38], [21, 25, 29, 39], [36, 27, 3, 23], [25, 24, 26, 23]];
    /** enervement tribu - etranger (unsigned short) */
    public array $tenerv = [256, 4864, 25600, 40960];
    /** enerv contre toi (unsigned short) */
    public array $enetoi = [0, 0, 0, 0];
    public array $listdep = [21, 23, 23, 27, 37, 36, 19, 19, 25];
    public array $maxparam = [[240, 180, 254, 200, 130, 254], [200, 254, 130, 200, 200, 254]];
    public array $agrdep = [110, 150, 200, 201];
    /** agressivite (unsigned char) */
    public array $tagress = [110, 150, 200, 201];

    /** donnees du menu en cours */
    public int $menu = 0;
    public int $clin = 1;
    public int $ccol = 1;
    public int $colcdr = 10;
    public int $cnlin = 10;
    public int $cncol = 10;
    public int $coulcur = 1;
    public int $poscur = 0;
    public Liste $stdlist;
    public int $curpos = 0;
    public int $pcadre = 0;
    public int $coulfd = 0;
    /** @var array<int, array{ligne:int, colon:int, haut:int, larg:int}> */
    public array $gemzoom = [];

    public Liste $liste0;
    public Liste $liste1;
    public Liste $liste2;
    public Liste $liste3;
    public Liste $liste4;
    public Liste $liste5;
    public Liste $liste6;
    public Liste $liste7;
    public Liste $liste8;
    public Liste $list10;
    public Liste $list11;
    public Liste $list12;
    public Liste $listbidon;
    public Liste $zoneu;
    public Liste $cross;

    /** Inits des tableaux des menus (signed chars) */
    public array $tclin = [18, 19, 18, 18, 19, 18, 19, 20, 18];
    public array $tccol = [4, 22, 23, 28, 26, 31, 33, 23, 26];
    public array $tcolcdr = [11, 17, 17, 18, 12, 12, 12, 17, 13];
    public array $tcnlin = [7, 7, 8, 6, 6, 3, 2, 5, 6];
    public array $tcncol = [13, 16, 28, 12, 16, 16, 9, 26, 17];
    public array $tlpos = [1, 1, 1, 1, 1, 1, 1, 1, 1];
    public array $tcoulcur = [9, 9, 9, 9, 9, 9, 9, 9, 9];
    public array $tposcur = [6082, 5787, 6121, 5795, 6432, 6118, 6441, 6445, 5792];
    /** @var Liste[] */
    public array $tstdlist = [];

    public int $distsp = 0;
    public int $presence = 0;
    public int $fin = 0;
    public int $sensmob = 0;
    public int $nummob = 0;
    /** char * ptranim: offset in Anim::ANIM */
    public int $ptranim = 0;

    /** tableaux: char arrays, values as written in C (sc() where the C code reads a negative char) */
    public array $hautlin = [10, 15, 25, 30];
    public array $tabinp = [3, 5, 20, 30];
    public array $hautou = [10, 20, 27, 35];
    public array $tribforce = [15, 15, 25, 20];
    public array $tribvie = [160, 150, 200, 180];
    public array $bestlin = [2, -35, 2, 2];
    public array $bestvie = [70, 200, 1, 1];
    public array $bestagress = [200, 180, 200, 180];
    public array $besthaut = [2, 1, 3, 3];
    public array $present = [0, 0, 0, 0];
    public array $qtviande = [1, 2, 4, 8];

    public Sap $sap1;
    public Sap $sap2;
    public Sap $sap3;
    public Sap $sap4;
    /** @var Sap[] */
    public array $peuple = [];

    /** destine[]: type, posx, posy, distmax, rnd, evt, message */
    public array $destine = [
        [1, 0x58, 0x54, 1, 245, 0x81, 80],
        [1, 155, 56, 1, 245, 0x82, 80],
        [1, 47, 207, 1, 245, 0x83, 80],
        [1, 195, 170, 1, 245, 0x84, 80],
        [0, 88, 81, 20, 10, 1, 79],
        [0, 155, 56, 30, 10, 2, 79],
        [0, 47, 207, 60, 20, 3, 79],
        [0, 195, 170, 40, 25, 4, 79],
        [0, 82, 78, 1, 255, 0, 76],
        [0, 48, 111, 1, 255, 0, 76],
        [0, 187, 164, 1, 255, 0, 76],
        [0, 8, 8, 1, 255, 0, 76],
        [0, 152, 3, 1, 255, 0, 76],
        [0, 63, 164, 1, 255, 0, 76],
        [0, 125, 63, 1, 255, 0, 76],
        [0, 176, 99, 1, 255, 0, 76],
        [0, 24, 176, 1, 255, 0, 76],
        [0, 195, 196, 1, 255, 0, 76],
        [0, 95, 74, 1, 255, 0, 76],
        [0, 128, 68, 1, 255, 0, 76],
        [0, 157, 56, 1, 255, 0, 76],
        [0, 171, 66, 1, 255, 0, 76],
        [0, 9, 20, 40, 20, 7, 77],
        [0, 20, 194, 20, 10, 8, 77],
        [0, 80, 84, 10, 10, 7, 77],
        [1, 110, 181, 10, 128, 7, 77],
        [1, 110, 33, 10, 128, 8, 77],
        [1, 179, 234, 10, 128, 7, 77],
        [1, 195, 147, 10, 128, 8, 77],
        [1, 202, 23, 5, 128, 8, 77],
        [1, 236, 154, 5, 128, 8, 77],
        [0, 50, 134, 20, 30, 5, 74],
        [0, 76, 214, 20, 30, 5, 74],
        [0, 135, 60, 20, 50, 5, 74],
        [0, 236, 134, 20, 30, 5, 74],
        [0, 195, 170, 10, 40, 5, 74],
        [0, 30, 54, 10, 10, 6, 75],
        [0, 97, 140, 10, 10, 6, 75],
        [0, 139, 221, 10, 15, 6, 75],
        [0, 185, 87, 5, 20, 6, 75],
    ];

    public int $posxf = 0;
    public int $posyf = 0;
    public int $ralent = 0;
    // unsigned char
    public int $soif = 0;
    public int $fatig = 0;
    public int $faim = 0;
    public int $mort = 0;
    public int $bless = 0;
    public int $echan = 0;
    public int $vlchan = 0;
    public int $flefba = 0;
    public int $predil = 0;
    public int $fdialo = 0;
    public int $fpress = 0;
    public int $chef = 0;
    public int $tribu = 0;
    public int $scenar = 0;
    public int $flcomb = 0;
    public int $agress = 0;
    public int $entvie = 0;
    public int $sexe = 1;
    public int $dquest = 0;
    public int $dervue = 0;
    public int $valsoi = 0;
    public int $source = 0;
    public int $minute = 0;
    public int $sapvit = 0;
    public int $viecopain = 0;
    public int $sap3clav = 0;
    public int $cptsoi = 0;
    public int $cptagil = 0;
    public int $finjou = 0;
    public int $agilite = 128;
    public int $force = 128;
    public int $resistance = 128;
    public int $charisme = 128;
    public int $vie = 128;
    public int $flagbadi = 0;
    public int $ciel = 221;
    public int $terre = 136;
    public int $freq = 10;
    public int $flgcharism = 0;
    /** char (signed) */
    public int $scenpr = 0;
    // unsigned short
    public int $enerv = 0;
    public int $xj = 0;
    public int $yj = 0;
    public int $zj = 0;
    public int $derxj = 0;
    public int $deryj = 0;
    public int $bidon = 0;
    public int $kk = 0;
    public int $dismin = 1;
    public int $dist = 1;
    public int $heure = 1;
    public int $metpos = 70;
    public int $angsol = 0;
    public int $jour_act = 0;
    public int $jour_dsk = 0;
    // unsigned char
    public int $zoom = 64;
    public array $tabclav1 = [72, 80, 75, 77, 28, 57, 1];
    public array $tabclav2 = [18, 45, 31, 32, 34, 33, 1];
    public array $param = [128, 128, 128, 128, 128, 0];

    /**
     * tabheur: per hour, h, q (0 = palette of the ground, 1 = palette of the sky), pal (ST colours);
     * SAPW3D adds 'ciel' (TabCouleursCiel) and 'distance' (TabPaletteDistance).
     * @var array<int, array<string, mixed>>
     */
    public array $tabheur = [];

    /** short * palhaute, palbasse, pal_chron, ciel_degr: ST palettes (16 colours + 16 indexes) */
    public array $palhaute = [];
    public array $palbasse = [];
    public array $pal_chron = [];
    public array $ciel_degr = [];
    public int $pos_evt = 0;
    public array $pal1 = [0x006, 0x542, 0x650, 0x777, 0x430, 0x310, 0x320, 0x210, 0x005, 0x432, 0x443, 0x444, 0x322, 0x332, 0x211, 0x000,
        0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    public array $pal2 = [0x777, 0x000, 0x700, 0x320, 0x430, 0x540, 0x650, 0x531, 0x631, 0x641, 0x444, 0x050, 0x070, 0x770, 0x450, 0x005,
        0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    public array $pal_zero = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    /** smenu[]: the menus shown (0 and the sub-menu) */
    public array $smenu = [0, -1];
    /** sliste[]: their lists */
    public array $sliste = [];

    public function __construct(public string $dataDir, public ?Options $prefs = null, public ?object $sound = null)
    {
        $this->initPorts();
        $this->liste0 = new Liste(self::REFER0, 11, 20, [11, 10, 5, 4, 3, 6, 1, 2, 7, 8, 9]);
        $this->liste1 = new Liste(self::REFER1, 16, 30, [21, 23, 23, 27, 19, 19, 19, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20]);
        $this->liste2 = new Liste(self::REFER2, 28, 0, range(1, 28));
        $this->liste3 = new Liste(self::REFER3, 4, 0, [1, 2, 3, 4]);
        $this->liste4 = new Liste(self::REFER1, 2, 30, [2, 3, 4]);
        $this->liste5 = new Liste(self::REFER3, 3, 0, [5, 6, 7]);
        $this->liste6 = new Liste(self::REFER1, 2, 0, [23, 21]);
        $this->liste7 = new Liste(self::REFER3, 5, 0, [8, 9, 10, 11, 12]);
        $this->liste8 = new Liste(self::REFER3, 5, 0, [19, 13, 14, 15, 26]);
        $this->list10 = new Liste(self::REFER3, 0, 20);
        $this->list11 = new Liste(self::REFER3, 0, 20);
        $this->list12 = new Liste(self::REFER3, 0, 20);
        $this->listbidon = new Liste(self::REFER0, 0, 11);
        $this->zoneu = new Liste(self::REFER1, 0, 30);
        $this->cross = new Liste(self::REFER1, 0, 30);
        $this->stdlist = $this->liste0;
        $this->tstdlist = [$this->liste0, $this->liste1, $this->liste2, $this->liste3, $this->liste4, $this->liste5, $this->liste6, $this->liste7, $this->liste8];
        $this->sliste = [$this->liste0, $this->liste1];
        for ($i = 0; $i < self::GEMNBRECAD; $i++) {
            $this->gemzoom[$i] = ['ligne' => 0, 'colon' => 0, 'haut' => 0, 'larg' => 0];
        }

        $this->sap1 = new Sap(32, 0, 8, 5, $this->liste1);
        $this->sap2 = new Sap(54, 1, 8, 296, $this->list10);
        $this->sap3 = new Sap(54, 1, 56, 5, $this->list11, $this->sap2);
        $this->sap4 = new Sap(54, 1, 56, 296, $this->list12, $this->sap2);
        $this->peuple = [$this->sap1, $this->sap2, $this->sap3, $this->sap4];

        $this->tabheur = GlobData::tabheur();
        $this->palhaute = $this->pal_chron = $this->palbasse = $this->ciel_degr = $this->pal_zero;

        $strings = @file_get_contents($this->dataDir . '/strings.json');
        $this->rsrcStrings = $strings ? (array)json_decode($strings, true) : [];
    }

    /** The original files are there (data of the original game, assets/data). */
    public static function hasData(string $dataDir): bool
    {
        return is_file($dataDir . '/mobs/W3D.BRT');
    }

    /** InitMobs: the 11 .BRT files */
    public function InitMobs(): void
    {
        $this->TabMob = [];
        $this->TabPalette = [];
        $dir = $this->dataDir . '/mobs/';
        $this->mobBambou = 0;
        $nb = $this->ChargeFichierBrt($dir . 'WBAMBOU.BRT', $this->mobBambou + 1, 26, 0);
        $this->mobFace = $this->mobBambou + $nb;
        $nb = $this->ChargeFichierBrt($dir . 'WFACE.BRT', $this->mobFace + 1, 31, 0);
        $this->mobFeuille = $this->mobFace + $nb;
        $nb = $this->ChargeFichierBrt($dir . 'WFEUILLE.BRT', $this->mobFeuille + 1, 36, 11);
        $this->mobFleches = $this->mobFeuille + $nb;
        $nb = $this->ChargeFichierBrt($dir . 'WFLECHES.BRT', $this->mobFleches + 1, 20, 0);
        $this->mobSapmob = $this->mobFleches + $nb;
        $nb = $this->ChargeFichierBrt($dir . 'WSAPMOB.BRT', $this->mobSapmob + 1, 141, 0);
        $this->mobAvplan = $this->mobSapmob + $nb;
        $nb = $this->nbMobHerbes = $this->ChargeFichierBrt($dir . 'WAVPLAN.BRT', $this->mobAvplan + 1, 9, 13);
        $this->mobAvplanSpec = $this->mobAvplan + $nb;
        $nb = $this->nbAvplanSpec = $this->ChargeFichierBrt($dir . 'WAVSPEC.BRT', $this->mobAvplanSpec + 1, 4, 0);
        $this->mobSilex = $this->mobAvplanSpec + $nb;
        $nb = $this->ChargeFichierBrt($dir . 'WSILEX.BRT', $this->mobSilex + 1, 15, 0);
        $this->mob3D = $this->mobSilex + $nb;
        $nb = $this->ChargeFichierBrt($dir . 'W3D.BRT', $this->mob3D + 1, 38, 0);
        $this->Fonte14 = $this->mob3D + $nb;
        $nb = $this->ChargeFichierBrt($dir . 'GENEVA14.BRT', $this->Fonte14 + 1, 120, 0);
        $this->Fonte10 = $this->Fonte14 + $nb;
        $this->ChargeFichierBrt($dir . 'GENEVA10.BRT', $this->Fonte10 + 1, 120, 0);
    }

    // ---- the mob numbers of SAPIENS.H ----
    public function MOB_FLECHE(): int { return $this->mobSapmob + 49; }
    public function MOB_IMPACT(): int { return $this->mobSapmob + 32; }
    public function MOB_SANG_A_TERRE(): int { return $this->mobSapmob + 87; }
    public function MOB_EMPREINTE(): int { return $this->mobSapmob + 20; }
    public function MOB_CORPS(): int { return $this->mobSapmob + 6; }
    public function MOB_CHEMIN(): int { return $this->mobSapmob + 83; }
    public function MOB_ANIMAL_MORT(): int { return $this->mobSapmob + 109; }
    public function MOB_PICT(): int { return $this->mobSilex + 1; }
    public function MOB_GUEPARD(): int { return $this->mobSilex + 12; }
    public function MOB_SILEX_SAGAIE(): int { return $this->mobSilex + 13; }
    public function MOB_SILEX_HACHE(): int { return $this->mobSilex + 14; }
    public function MOB_SILEX_CASSE(): int { return $this->mobSilex + 15; }
    public function MOB_MORT(): int { return $this->mobSapmob + 114; }
    public function MOB_SOLEIL(): int { return $this->mobFace + 28; }
    public function MOB_FEUILLE(): int { return $this->mobFeuille + 1; }
    public function MOB_HERBE(): int { return $this->mobAvplan + 1; }
    public function MOB_CADRE(): int { return $this->mobBambou + 1; }
    public function UP(): int { return $this->mobBambou + 18; }
    public function DOWN(): int { return $this->mobBambou + 19; }
    public function INCONNU(): int { return $this->mobBambou + 20; }
    public function SELECT(): int { return $this->mobBambou + 21; }
    public function DEBFLECH(): int { return $this->mobFleches + 1; }
    public function DEBCRANE(): int { return $this->mobFace + 13; }
    public function DEBTETE(): int { return $this->mobFace + 1; }
    public function DEBBEST(): int { return $this->mobFace + 24; }
    public function MOB_DESIGNE(): int { return $this->mobFleches + 17; }
    public function MOB_AIDE(): int { return $this->mobBambou + 27; }

    /** CalcIndexPalette (SAPWIND.C): indexes 16..31 of an ST palette = system indexes of 0..15 */
    public function CalcIndexPalette(array &$palette): void
    {
        for ($i = 0; $i < 16; $i++) {
            $palette[16 + $i] = Mac::st($palette[$i]);
        }
    }
}
