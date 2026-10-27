<?php

namespace App\Sapiens;

/**
 * SAPWARB.C: the procedural trees of the side view (recursive branches drawn with TraceDisque, tufts
 * of leaves at their ends), drawn on Ecran1.
 */
trait TreeTrait
{
    /** the colours (indexes in palhaute) of the 9 bands of a branch (TraceDisque) */
    public array $tabcoul = [3, 4, 5, 6, 6, 5, 4, 3, 3];

    /** niveau de recursivite */
    private int $arbNiveau = 0;
    /** largeur du tronc */
    private int $arbLargtronc = 0;
    /** type de la feuille */
    private int $arbNumfeu = 0;
    private int $arbAmini = 0;
    private int $arbQutlgn = 0;
    private int $arbDensif = 0;
    /** @var int[] */
    private array $arbFortou = [0, 0, 0, 0];
    private ?string $TabIndexFeuilleNuit = null;

    private const TABRND = [20, 30, 8, 1, 6, 12, 20, 6, 10, 4];
    private const TRAM_ARBRE = [1, 3, 4, 5, 6, 5, 4, 3, 1];

    /** calpar: angle, width and length of a sub-branch */
    private function calpar(int $largeur, int &$ang, int &$lr, int &$lo): void
    {
        $n = $this->arbLargtronc - (($this->rnd0() * $this->arbQutlgn * $this->arbNiveau) >> 8);
        if ($n < 0) {
            $n = ($this->arbLargtronc * $this->rnd0()) >> 8;
        }
        $n++;
        $lo = $n;
        $largeur = (($this->rnd0() * ($largeur >> 1)) >> 8) + ($largeur >> 2) + 1;
        if ($this->rnd0() <= 40) {
            $largeur++;
        }
        $ang = (($this->rnd0() * 70) >> 8) + $this->arbAmini;
        $lr = $largeur;
    }

    /** touffe: a tuft of leaves around (colonne, ligne) */
    private function touffe(int $colonne, int $ligne): void
    {
        $angle = 0;
        $forme = 0;
        for ($i = 0; $i < $this->arbDensif; $i++) {
            $r = $this->rnd0();
            $y = ($this->arbFortou[1] * $this->sinshort($angle & 0xFF) * $r) >> 16;
            $x = ($this->arbFortou[0] * $this->cosshort($angle & 0xFF) * $r) >> 16;
            if ($angle & 0x100) {
                $x += $colonne;
            } else {
                $x = $colonne - $x;
            }
            if ($angle & 0x200) {
                $y += $ligne;
            } else {
                $y = $ligne - $y;
            }
            $x += 16;
            if ($x < 256) {
                $this->Affmobbrt($this->MOB_FEUILLE() + $this->arbNumfeu + $forme, $x, $y, $this->rnd0() & 1, $this->TabIndexFeuilleNuit);
            }
            if (++$forme > 3) {
                $forme = 0;
            }
            $angle += $this->rnd0() + 256;
            $angle &= 1023;
        }
    }

    /** tracebranche: a branch, then its two sub-branches (or a tuft at the end) */
    private function tracebranche(int $angle, int $colonne, int $ligne, int $longueur, int $largeur): void
    {
        if (++$this->arbNiveau < 15) {
            $ancXG = $colonne;
            $ancXD = $colonne + $largeur;
            $ancy = $ligne;
            $epaisseur = (abs($this->sinshort($angle)) * $longueur) >> 8;
            if ($epaisseur < 1) {
                $epaisseur = 1;
            }
            $posg = $ancXG + (($this->cosshort($angle) * $longueur) >> 8);
            if ($posg < 0) {
                $posg = 0;
            }
            $posd = $posg + $largeur;
            if ($posd > 255) {
                $posg = 255 - $largeur;
                $posd = 255;
            }
            if ($epaisseur + $ancy < 0) {
                $epaisseur = $ancy;
            }
            $this->TraceDisque($ancXG, $ancXD, $ancy, $posg, $posd, $epaisseur);
            if ($largeur < 2) {
                $this->touffe(intdiv($ancXG + $ancXD, 2), $ancy);
            } elseif ($ancy > 0) {
                $ang = $lr = $lo = $bidon = 0;
                // left branch
                $this->calpar($largeur, $ang, $lr, $lo);
                $ang = $angle + $ang;
                if ($ang > 450) {
                    $ang = 900 - $ang;
                }
                $this->tracebranche($ang, $ancXG, $ancy, $lo, $lr);
                $col = $ancXG + $lr;
                $lr = $largeur - $lr;
                if ($lr < 1) {
                    $lr = 1;
                }
                $this->calpar($largeur, $ang, $bidon, $lo);
                $ang = $angle - $ang;
                if ($ang < 10) {
                    $ang = 124 - $ang;
                }
                $this->tracebranche($ang, $col, $ancy, $lo, $lr);
            }
        }
        $this->arbNiveau--;
    }

    /** arbre: a tree whose trunk starts at (ligne, colonne) of Ecran1 (ST coordinates of the view) */
    public function arbre(int $ligne, int $colonne): void
    {
        $gbak = $this->curPort;
        $this->curPort = $this->Ecran1;
        if ($this->heure > 512) {
            $this->TabIndexFeuilleNuit = $this->CalculePaletteAttenuee($this->MOB_FEUILLE(), 0.50 + (float)$this->metpos / 150.0, 999);
        } else {
            $this->TabIndexFeuilleNuit = null;
        }
        $this->arbNiveau = 0;
        for ($i = 0; $i < 9; $i++) {
            $this->tabcoul[$i] = self::TRAM_ARBRE[$i];
        }
        $this->arbNumfeu = (($this->rnd0() * 9) >> 8) * 4;
        $this->arbAmini = (($this->rnd0() * self::TABRND[0]) >> 8) + self::TABRND[1];
        $this->arbQutlgn = (($this->rnd0() * self::TABRND[2]) >> 8) + self::TABRND[3];
        $this->arbDensif = (($this->rnd0() * self::TABRND[4]) >> 8) + self::TABRND[5];
        $this->arbFortou[0] = (($this->rnd0() * self::TABRND[6]) >> 8) + self::TABRND[7];
        $this->arbFortou[1] = (($this->rnd0() * self::TABRND[8]) >> 8) + self::TABRND[9];
        $longueur = (($this->rnd0() * 6) >> 8) + 10;
        $largeur = (($this->rnd0() * 10) >> 8) + 10;
        $this->arbLargtronc = $largeur;
        $this->tracebranche(256, $colonne, $ligne, $longueur, $largeur);
        $this->TabIndexFeuilleNuit = null;
        $this->curPort = $gbak;
    }
}
