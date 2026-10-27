<?php

namespace App\Sapiens;

/**
 * SAPWCART.C: the map (bottom right of the screen): the objects of the world seen from above around
 * the player, sorted by their bottom edge, and the arrow of the direction.
 *
 * The objects: $this->aigoual (World3dTrait, struct Mont) = arrays ['posx', 'posy', 'ign', 'forme']
 * where 'forme' is an index in $this->formes (struct Forme as arrays: 'rayon', 'num_mob', 'numMob3d'...).
 */
trait MapTrait
{
    private ?Port $EcrCarte = null;
    /** @var array<int, array{aire: array{0:int,1:int,2:int,3:int}, mob: int}> TabEltCar */
    private array $TabEltCar = [];
    /** @var array{0:int,1:int,2:int,3:int} */
    private array $AireCarte = [0, 0, 144 * 2, 80 * 2];

    private const TAILLE_X_CARTE = 144;
    private const TAILLE_Y_CARTE = 80;
    private const NB_OBJETS_MAX = 200;

    /** tfrcar: the map to debecr and to the window */
    private function tfrcar(): void
    {
        $rdest = [160 * 2, 117 * 2, (160 + self::TAILLE_X_CARTE) * 2, (117 + self::TAILLE_Y_CARTE) * 2];
        $this->CopyOffscreen($this->EcrCarte, $this->debecr, $this->AireCarte, $rdest);
        $this->CopyOffscreen($this->EcrCarte, $this->mainWindow, $this->AireCarte, $rdest);
    }

    /**
     * calc_cart: the rectangle of an object on the map.
     * @return array{aire: array{0:int,1:int,2:int,3:int}, mob: int}|null null if not on the map
     */
    private function calc_cart(array $mont): ?array
    {
        $forme = $this->formes[$mont['forme']];
        $x = intdiv($this->yj - $mont['posy'], $this->zoom) + self::TAILLE_X_CARTE;
        $y = intdiv($this->xj - $mont['posx'], $this->zoom) + self::TAILLE_Y_CARTE;
        $r = intdiv($forme['rayon'], intdiv($this->zoom, 2));
        if ($forme['num_mob'] === 8) {
            // smoke
            $aire = [$x - 8, $y - 32, $x + 8, $y + 32];
        } elseif ($r < 16) {
            $aire = [$x - 8, $y - 8, $x + 8, $y + 8];
        } else {
            $aire = [$x - intdiv($r, 2), $y - intdiv($r, 8), $x + intdiv($r, 2), $y + intdiv($r, 8)];
        }
        if (self::SectRect($aire, $this->AireCarte) !== null) {
            return ['aire' => $aire, 'mob' => $this->mob3D + $forme['numMob3d']];
        }
        return null;
    }

    /** aff_mont: the objects of the map, from the top to the bottom (sorted on their bottom edge) */
    private function aff_mont(int $nb): \Generator
    {
        yield from [];
        for (;;) {
            $minyc = 32767;
            $jmin = 0;
            for ($j = 0; $j < $nb; $j++) {
                if ($this->TabEltCar[$j]['aire'][3] < $minyc) {
                    $jmin = $j;
                    $minyc = $this->TabEltCar[$j]['aire'][3];
                }
            }
            if ($minyc === 32767) {
                return;
            }
            $this->Affmob3d($this->TabEltCar[$jmin]['mob'], $this->TabEltCar[$jmin]['aire'], null);
            $this->TabEltCar[$jmin]['aire'][3] = 32767;
        }
    }

    /** EffCarte: the background of the map */
    private function EffCarte(): void
    {
        $this->BoxFill($this->EcrCarte, [0, 0, self::TAILLE_X_CARTE * 2, self::TAILLE_Y_CARTE * 2], 0x050);
    }

    /** CARTE: draws the map (generator for the callers; it does not wait) */
    public function carte(): \Generator
    {
        if ($this->EcrCarte === null) {
            $this->EcrCarte = new Port(self::TAILLE_X_CARTE * 2, self::TAILLE_Y_CARTE * 2);
            $this->AireCarte = [0, 0, self::TAILLE_X_CARTE * 2, self::TAILLE_Y_CARTE * 2];
        }
        $gbak = $this->curPort;
        $this->curPort = $this->EcrCarte;
        $this->smenu[1] = 111;
        $this->EffCarte();
        $this->TabEltCar = [];
        $j = 0;
        $i = $this->nbreobj;
        $k = 0;
        while ($i-- > 0 && $j < self::NB_OBJETS_MAX) {
            $mont = $this->aigoual[$k++];
            if ($this->formes[$mont['forme']]['num_mob'] >= 0) {
                $elt = $this->calc_cart($mont);
                if ($elt !== null) {
                    $this->TabEltCar[$j++] = $elt;
                }
            }
        }
        yield from $this->aff_mont($j);
        $angle = intdiv($this->dzeta + 32, 64) & 15;
        $this->Affmobbrtomb($this->DEBFLECH() + $angle, intdiv(self::TAILLE_X_CARTE, 2) - 8, intdiv(self::TAILLE_Y_CARTE, 2) - 8, 5, 5);
        $this->tfrcar();
        $this->curPort = $gbak;
    }

    /** ChangeZoomCarte (generator for the callers; it does not wait) */
    public function ChangeZoomCarte(int $delta): \Generator
    {
        $this->zoom = ($this->zoom + $delta) & 0xFF;
        if ($this->zoom > 160) {
            $this->zoom = 160;
        }
        if ($this->zoom < 16) {
            $this->zoom = 16;
        }
        yield from $this->carte();
    }

    /** ZOOCAR: the map alone; up / down (or the mouse) zoom, escape or a click leaves */
    public function zoocar(): \Generator
    {
        $gbak = $this->curPort;
        $this->CarboniseSetPortWindow();
        do {
            $this->vq_mouse($this->bouton, $this->sposx, $this->sposy);
            if ($this->bouton) {
                yield;
            }
        } while ($this->bouton);
        $r = self::InsetRect([160 * 2, 117 * 2, (160 + self::TAILLE_X_CARTE) * 2, (117 + self::TAILLE_Y_CARTE) * 2], -1, -1);
        $this->qdFore = Mac::WHITE;
        $this->DoFrameRect($r);
        $this->qdFore = Mac::BLACK;
        yield from $this->carte();
        do {
            yield;
            $this->GereInterface();
            $bitmap = 0;
            if ($this->GetKeyMap($bitmap)) {
                if ($bitmap & 1) {
                    yield from $this->ChangeZoomCarte(-2);
                }
                if ($bitmap & 2) {
                    yield from $this->ChangeZoomCarte(2);
                }
                if ($bitmap & 64) {
                    $this->bouton = 1;
                }
            } else {
                $this->vq_mouse($this->bouton, $this->sposx, $this->sposy);
                if ($this->sposy !== $this->dsposy) {
                    $this->zoom = intdiv($this->sposy, 2) + 30;
                    if ($this->zoom < 16) {
                        $this->zoom = 16;
                    }
                    if ($this->zoom > 160) {
                        $this->zoom = 160;
                    }
                    $this->dsposy = $this->sposy;
                    yield from $this->carte();
                }
            }
        } while (!$this->bouton);
        // the original draws the frame again in black (the pen colour was set back to black)
        $this->DoFrameRect($r);
        $this->curPort = $gbak;
    }
}
