<?php

namespace App\Sapiens;

/**
 * SAPLIST.C: lists (the menus of the bottom of the screen, the objects), the main loop of the game
 * (main), the actions take / drop / eat / heal.
 */
trait ListTrait
{
    private int $lastMenuInvers = -1;
    private int $lastn = 0;
    /** @var array{0:int,1:int,2:int,3:int} */
    private array $rInvert = [0, 0, 0, 0];

    /** InverseOption: highlights the option $n of the menu $menu (-1: none) */
    public function InverseOption(int $menu, int $n): void
    {
        $this->eff_sour();
        if ($this->lastMenuInvers >= 0) {
            $this->SwapCouleur($this->debecr, $this->rInvert, Mac::st($this->palbasse[4]), Mac::st($this->palbasse[1]));
            $this->TfrEcr($this->rInvert);
            $this->lastMenuInvers = -1;
        }
        if ($menu >= 0) {
            $this->rInvert = [
                $this->tccol[$menu] * 6 * 2,
                ($this->tclin[$menu] - $this->tlpos[$menu] + 1 + $n) * 7 * 2 - 1,
                ($this->tccol[$menu] + $this->tcncol[$menu]) * 6 * 2,
                ($this->tclin[$menu] - $this->tlpos[$menu] + 1 + $n + 1) * 7 * 2 - 1,
            ];
            $this->SwapCouleur($this->debecr, $this->rInvert, Mac::st($this->palbasse[1]), Mac::st($this->palbasse[4]));
            $this->TfrEcr($this->rInvert);
        }
        $this->lastMenuInvers = $menu;
        $this->lastn = $n;
    }

    private function ListAttendsFinBouton(): \Generator
    {
        do {
            $this->vq_mouse($this->bouton, $this->sposx, $this->sposy);
            if ($this->bouton) {
                yield;
            }
        } while ($this->bouton);
    }

    /** AJOUTL: adds an element to a list; 0 ok, 1 list full */
    public function ajoutl(Liste $debliste, int $element): int
    {
        if ($debliste === $this->listbidon) {
            $debliste->elements[0] = $element;
        } else {
            if ($debliste->taille === $debliste->maxtaille) {
                return 1;
            }
            $debliste->elements[$debliste->taille] = $element;
            $debliste->taille++;
        }
        return 0;
    }

    /**
     * ENLEVL: moves the element $rang of a list to another list.
     * @return int 0 ok, 1 the element was the last one (or the reception list is full), 255 error
     */
    public function enlevl(Liste $orgliste, Liste $receptliste, int $rang): int
    {
        if ($rang < $orgliste->taille) {
            $orgliste->taille--;
            $erreur = $this->ajoutl($receptliste, $orgliste->elements[$rang]);
            if ($erreur) {
                $orgliste->taille++;
                return 1;
            }
            $i = $rang;
            while ($i !== $orgliste->taille) {
                $orgliste->elements[$i] = $orgliste->elements[$i + 1];
                $i++;
            }
            return $i === $rang ? 1 : 0;
        }
        return 255;
    }

    /** MOT: a word of a reference */
    public function mot(string $refer): void
    {
        $n = strlen($refer);
        for ($i = 0; $i < $n; $i++) {
            $this->affcar(ord($refer[$i]));
        }
    }

    private function affmot(Liste $debliste, int $rang): void
    {
        if ($rang < $debliste->taille) {
            $refer = $this->refer[$debliste->typeRef];
            $this->mot($refer[$debliste->elements[$rang] - 1][0] ?? '');
        }
    }

    /** AFLIST: the list in its frame */
    private function aflist(Liste $debliste): void
    {
        $gbak = $this->curPort;
        $this->curPort = $this->debecr;
        if ($this->s_menu === $this->lastMenuInvers) {
            $this->lastMenuInvers = -1;
        }
        $this->Gotoxy($this->ccol, $this->clin);
        $i = $this->cnlin;
        if ($this->lpos === 0) {
            $this->lpos = 1;
        }
        if ($this->lpos > $debliste->taille) {
            $this->lpos = $debliste->taille;
        }
        $travl2 = ($this->lpos - 1) & 0xFF;
        $r = [$this->colaff * 6 * 2, $this->linaff * 7 * 2 - 1, ($this->colaff + $this->cncol) * 6 * 2, ($this->linaff + $this->cnlin) * 7 * 2];
        $this->BoxFill($this->debecr, $r, 0x542);
        if ($debliste->taille) {
            if ($travl2 > 1) {
                $this->Gotoxy($this->ccol + $this->cncol - 1, $this->linaff);
                $this->affcar(ord('<'));
                $this->Gotoxy($this->ccol, $this->clin);
            }
            while ($i--) {
                $this->affmot($debliste, $travl2++);
                $this->Gotoxy($this->ccol, $this->linaff + 1);
            }
            if ($travl2 < $debliste->taille) {
                $this->Gotoxy($this->ccol + $this->cncol - 1, $this->linaff - 1);
                $this->affcar(ord('>'));
            }
        } else {
            $this->mot('...');
        }
        $this->TfrEcr(self::InsetRect($r, -48, -48));
        $this->curPort = $gbak;
    }

    /** ENCADRE: the bamboo frame of a menu */
    private function encadre(int $ligne, int $colon, int $haut, int $larg): void
    {
        $ligne *= 7;
        $colon *= 6;
        $haut = $ligne + 7 * $haut;
        $larg = $colon + 6 * $larg;
        // small delta for variety
        $ligne -= ($colon & 3);
        $colon = $colon - 2 - ($haut & 3);
        $haut += $larg & 3;
        $larg += $ligne & 3;
        $c = $this->MOB_CADRE();
        $this->affomb($c + 0, $ligne - 24, $colon - 8, 8, 8, false);
        $this->affomb($c + 1, $ligne - 24, $larg - 8, 8, 8, false);
        $this->affomb($c + 2, $ligne - 16, $colon - 16, 8, 8, false);
        $this->affomb($c + 3, $ligne - 8, $colon - 8, 8, 8, false);
        $tailleh = intdiv($larg - $colon - 1, 16);
        if ($tailleh < 2000) {
            for ($i = 0; $i < $tailleh; $i++) {
                $this->affomb($c + 4, $ligne - 8, $colon + 8 + $i * 16, 8, 8, false);
            }
        }
        $this->affomb($c + 5, $ligne - 8, $larg - 8, 8, 8, false);
        $this->affomb($c + 6, $ligne - 16, $larg + 8, 8, 8, false);
        $taillev = intdiv($haut - $ligne - 1, 16);
        if ($taillev < 2000) {
            for ($i = 0; $i < $taillev; $i++) {
                $this->affomb($c + 7, $ligne + 8 + 16 * $i, $colon - 8, 8, 8, false);
                if ($tailleh < 2000) {
                    for ($j = 0; $j < $tailleh; $j++) {
                        $this->affomb($c + 16, $ligne + 8 + 16 * $i, $colon + 8 + 16 * $j, 8, 8, false);
                    }
                }
                $this->affomb($c + 8, $ligne + 8 + 16 * $i, $larg - 8, 8, 8, false);
            }
        }
        $this->affomb($c + 9, $haut - 8, $colon - 16, 8, 8, false);
        $this->affomb($c + 10, $haut - 8, $colon - 8, 8, 8, false);
        if ($tailleh < 2000) {
            for ($i = 0; $i < $tailleh; $i++) {
                $this->affomb($c + 11, $haut - 8, $colon + 8 + $i * 16, 8, 8, false);
            }
        }
        $this->affomb($c + 12, $haut - 8, $larg - 8, 8, 8, false);
        $this->affomb($c + 13, $haut - 8, $larg + 8, 8, 8, false);
        $this->affomb($c + 14, $haut + 7, $colon - 16, 8, 8, false);
        $this->affomb($c + 15, $haut + 7, $larg, 8, 8, false);
    }

    /** GEMOUVRE: the opening of a frame (dotted frames: drawn by nothing in the 1996 version) */
    public function gemouvre(int $ligncad1, int $colcad1, int $hautcad1, int $largcad1, int $ligncad2, int $colcad2, int $hautcad2, int $largcad2): void
    {
    }

    /** INIMEN: shows the menu $entmenu (lastmen: the last menu other than 0) */
    public function inimen(int $entmenu): void
    {
        $refer1 = $this->refer[self::REFER1];
        $this->menu = $entmenu;
        $menu = $this->menu;
        $this->clin = $this->tclin[$menu];
        $this->ccol = $this->tccol[$menu];
        $this->colcdr = $this->tcolcdr[$menu];
        $this->cnlin = $this->tcnlin[$menu];
        $this->cncol = $this->tcncol[$menu];
        $this->poscur = $this->tposcur[$menu];
        $this->stdlist = $this->tstdlist[$menu];
        $this->eff_sour();
        if ($menu !== 0) {
            $this->smenu[1] = $menu;
            if ($menu !== $this->lastmen) {
                $this->encadre($this->clin, $this->ccol, $this->cnlin, $this->cncol);
                $this->lastmen = $menu;
            }
        } else {
            $this->criter = 0;
            if ($this->presence && $this->presence < 5) {
                $this->liste0->taille = 6;
            } else {
                $this->liste0->taille = 11;
            }
            if ($this->mort === 0) {
                $this->tlpos[0] = 1;
                $this->liste0->taille = 2;
                $this->clrbadi();
                $this->comment(136);
            }
        }
        $this->lpos = $this->tlpos[$menu];
        if ($this->criter) {
            $i = $j = 0;
            $this->lpos = 111;
            while ($i !== $this->stdlist->taille) {
                $rang = $this->stdlist->elements[$i];
                $valeur = ($refer1[$rang - 1][1] ?? 0) & 0xF0;
                if (($this->criter === 1 && $valeur === 0x20) || ($this->criter === 2 && $valeur === 0x40)) {
                    $this->zoneu->elements[$j] = $rang;
                    $this->cross->elements[$j] = $i;
                    $j++;
                    if ($i >= $this->tlpos[$menu] && $this->lpos === 111) {
                        $this->lpos = $j;
                    }
                }
                $i++;
            }
            if ($this->lpos === 111) {
                $this->lpos = 1;
            }
            $this->zoneu->taille = $j;
            $this->stdlist = $this->zoneu;
            $this->lpos = $this->tlpos[$menu];
        }
        if ($menu) {
            $this->sliste[1] = $this->stdlist;
            $this->smenu[1] = $menu;
        }
        $this->s_menu = $menu;
        $this->aflist($this->stdlist);
    }

    /** AFSTAT: the status menu */
    public function afstat(): void
    {
        $r = &$this->refer[self::REFER3];
        $r[7][0][0] = chr((intdiv($this->faim, 64) + 0x91) & 0xFF);
        $r[8][0][0] = chr((intdiv($this->soif, 64) + 0x91) & 0xFF);
        $r[9][0][1] = chr((intdiv($this->fatig, 64) + 0x95) & 0xFF);
        $r[10][0][1] = chr((intdiv($this->bless, 64) + 0x9A) & 0xFF);
        $r[11][0][1] = chr(($this->mort + 0xA7) & 0xFF);
        unset($r);
        $this->inimen(7);
        $this->s_action = 2;
    }

    public function pos0_mouse(): void
    {
        $this->reset_mouse();
    }

    /** GERE_SOURIS: the icon of the mouse over the menus */
    private function gere_souris(): void
    {
        $this->bouge_souris();
        if ($this->sposy < 105) {
            $this->icone = 0;
            $this->eff_sour();
        } else {
            $x = intdiv($this->sposx + 8, 6);
            $y = intdiv($this->sposy + 8, 7);
            $this->icone = $this->INCONNU();
            $this->s_menu = -1;
            for ($i = 0; $i < 2; $i++) {
                $men = $this->smenu[$i];
                if ($men !== 111 && $men >= 0 && $x >= $this->tccol[$men] && $x <= $this->tccol[$men] + $this->tcncol[$men]) {
                    $this->s_menu = $men;
                    $this->stdlist = $this->sliste[$i];
                    if ($y < $this->tclin[$men]) {
                        $this->icone = $this->UP();
                    } elseif ($y > $this->tclin[$men] + $this->tcnlin[$men]) {
                        $this->icone = $this->DOWN();
                    } else {
                        $this->icone = $this->SELECT();
                    }
                }
            }
        }
    }

    /** CLAVM: the menus with the mouse, until an action is chosen */
    private function clavm(): \Generator
    {
        $this->aff_sour($this->icone, $this->sposy, $this->sposx);
        while (true) {
            yield;
            $this->gere_souris();
            if (!$this->bouton) {
                continue;
            }
            if ($this->icone !== $this->INCONNU() && $this->icone !== 0) {
                if ($this->icone === $this->UP() && $this->tlpos[$this->s_menu] > 1) {
                    $this->eff_sour();
                    $this->tlpos[$this->s_menu]--;
                    $this->inimen($this->s_menu);
                    $this->aff_sour($this->icone, $this->sposy, $this->sposx);
                    yield from $this->waitTicks(6);
                }
                if ($this->icone === $this->DOWN()
                    && $this->tlpos[$this->s_menu] <= self::sc($this->stdlist->taille - $this->tcnlin[$this->s_menu])) {
                    $this->eff_sour();
                    $this->tlpos[$this->s_menu]++;
                    $this->inimen($this->s_menu);
                    $this->aff_sour($this->icone, $this->sposy, $this->sposx);
                    yield from $this->waitTicks(6);
                }
                if ($this->icone === $this->SELECT() && $this->stdlist->taille) {
                    $this->eff_sour();
                    $y = intdiv($this->sposy, 7);
                    $this->rega = ($y - $this->tclin[$this->s_menu] + $this->tlpos[$this->s_menu] - 1) & 0xFF;
                    if ($this->rega < $this->stdlist->taille) {
                        $refer = $this->refer[$this->stdlist->typeRef];
                        $ref = $refer[$this->stdlist->elements[$this->rega] - 1];
                        $this->stock1 = $ref[1];
                        $this->stock2 = $ref[2];
                        $this->efbadi();
                        if (!$this->s_menu) {
                            $this->s_action = $this->rega + 1;
                        }
                        $this->comment(120 + $this->s_action);
                        $this->InverseOption($this->s_menu, $this->rega);
                        if ($this->s_menu) {
                            $refer = $this->refer[$this->sliste[1]->typeRef];
                            $this->affcar(ord(' '));
                            $ref = $refer[$this->sliste[1]->elements[$this->rega] - 1];
                            $this->mot($ref[0]);
                            $this->TfrBadAss();
                            if ($this->criter) {
                                $this->rega = $this->cross->elements[$this->rega];
                            }
                            return;
                        }
                        $this->criter = $this->stock2;
                        if ($this->criter) {
                            $this->tlpos[1] = 1;
                        }
                        if ($this->stock1 === 7) {
                            if ($this->dervue !== self::VUE_LATERALE) {
                                $this->primjauge(0);
                            }
                            $this->afstat();
                        } else {
                            $this->inimen($this->stock1);
                        }
                        $this->sposx++;
                        $this->vq_mouse($this->bouton, $this->sposx, $this->sposy);
                    }
                    $this->aff_sour($this->icone, $this->sposy, $this->sposx);
                    // the original waits in inimen (6 ticks); and one click = one choice
                    yield from $this->ListAttendsFinBouton();
                }
            } elseif ($this->icone === 0) {
                // the mouse is in the top of the screen
                $pt = [$this->mouseX, $this->mouseY];
                $r = [32 * 2, 1 * 2, (32 + 255) * 2, (1 + 100) * 2];
                if (!$this->presence && self::PtInRect($pt, $r)) {
                    // a click in the picture: the menu 'observe'
                    $this->InverseOption(0, 6);
                    $this->s_action = 7;
                    $this->inimen(3);
                    $this->rega = 0;
                    yield from $this->ListAttendsFinBouton();
                    return;
                }
                yield from $this->ClickInGenerique($pt);
            }
        }
    }

    /** Waits $n ticks (1/60 s) */
    public function waitTicks(int $n): \Generator
    {
        $t = $this->TickCount() + $n;
        while ($this->TickCount() < $t) {
            yield;
        }
    }

    /** TRPOSE: drop an object */
    public function trpose(): void
    {
        $erreur = $this->enlevl($this->liste1, $this->liste4, $this->rega);
        if ($erreur === 1 && $this->lpos > 1) {
            $this->lpos--;
        }
        $this->inimen($this->s_menu);
    }

    /** TRPREN: take an object */
    public function trpren(): void
    {
        $quoi = 0;
        if ($this->liste4->elements[$this->rega] === 2) {
            // water
            $this->flefba = 0;
            if ($this->soif === 255) {
                $this->comment(98);
            } else {
                $this->comment(96);
            }
            $this->soif = 255;
            for ($i = 0; $i !== $this->liste1->taille; $i++) {
                if ($this->liste1->elements[$i] === 26) {
                    $this->liste1->elements[$i] = 27;
                    $quoi++;
                }
            }
            if ($quoi) {
                $this->comment(97);
            }
            $this->flefba = 0;
            return;
        }
        $old = $this->liste1->taille;
        $this->enlevl($this->liste4, $this->liste1, $this->rega);
        if ($this->liste1->taille === $old) {
            $this->flefba = 0;
            $this->comment(101);
        } elseif ($this->liste4->elements[$this->rega] === 44) {
            // the sacred stick
            $this->scenpr = 1;
        }
        $this->inimen($this->s_menu);
    }

    /** TRABSO: eat / drink */
    public function trabso(): void
    {
        if ($this->faim > 192 && $this->soif > 192) {
            $this->flefba = 0;
            $this->comment(107);
            return;
        }
        $calcul = ($this->faim + intdiv($this->stock2 & 0xF0, 4)) & 0xFF;
        $this->faim = $calcul >= $this->faim ? $calcul : 255;
        $calcul = ($this->soif + ($this->stock2 & 0x0F) * 4) & 0xFF;
        $this->soif = $calcul >= $this->soif ? $calcul : 255;
        $objet = $this->liste1->elements[$this->rega];
        if ($objet === 27) {
            $this->liste1->elements[$this->rega] = 26;
        } else {
            $this->enlevl($this->liste1, $this->listbidon, $this->rega);
        }
        $this->inimen(1);
    }

    /** TRSOIN: heal */
    public function trsoin(): void
    {
        if ($this->bless > 192) {
            $this->flefba = 0;
            $this->comment(109);
            return;
        }
        $calcul = ($this->bless + ($this->stock2 & 0x0F) * 10) & 0xFF;
        $this->bless = $calcul > $this->bless ? $calcul : 255;
        $this->enlevl($this->liste1, $this->listbidon, $this->rega);
        $this->inimen(1);
    }

    /** INITPRIM: the first initialisations */
    private function initprim(): \Generator
    {
        $this->dsposx = $this->sposx = 60;
        $this->dsposy = $this->sposy = 160;
        $this->palhaute = $this->pal_chron = $this->palbasse = $this->ciel_degr = $this->pal_zero;
        yield from $this->init_graph();
        $this->xj = 0x5800;
        $this->yj = 0x5400;
        $this->dzeta = 768;
        $this->flefba = 0;
        $this->primsap();
        $this->sap1->colonne = 200;
        $this->present[0] = -1;
        $this->prm($this->sap1, -1);
        $this->prm($this->sap2, 0);
        $this->prm($this->sap3, 0);
        $this->prm($this->sap4, 0);
        $this->framebadass();
        $this->palhaute = $this->pal_chron = $this->pal2;
        $this->palbasse = $this->pal1;
        yield from $this->DebutAventure(true);
        $this->afstat();
        $this->encadre($this->tclin[0], $this->tccol[0], $this->tcnlin[0], $this->tcncol[0]);
        $this->inimen(0);
    }

    /** ACTION: does the chosen action */
    private function action(): \Generator
    {
        switch ($this->s_action) {
            case 1:
                yield from $this->trgest($this->rega);
                $this->inimen(0);
                break;
            case 8:
                $this->trabso();
                yield from $this->ListAttendsFinBouton();
                break;
            case 4:
                $this->trpose();
                yield from $this->ListAttendsFinBouton();
                break;
            case 3:
                $this->trpren();
                yield from $this->ListAttendsFinBouton();
                break;
            case 9:
                $this->trsoin();
                yield from $this->ListAttendsFinBouton();
                break;
            case 5:
                if ($this->presence) {
                    $refer = $this->refer[$this->sliste[1]->typeRef];
                    if ($this->dervue !== self::VUE_LATERALE) {
                        $this->tfrima();
                    }
                    $this->bulle($refer[$this->sliste[1]->elements[$this->rega] - 1][0], 0);
                    yield from $this->trdire($this->liste2->elements[$this->rega]);
                    yield from $this->ListAttendsFinBouton();
                } else {
                    // you are talking to yourself...
                    $this->clrbadi();
                    $this->comment(135);
                }
                break;
            case 6:
                yield from $this->trdonn($this->rega);
                yield from $this->ListAttendsFinBouton();
                break;
            case 7:
                yield from $this->trobsv($this->rega);
                break;
            case 10:
                $this->MenuFenetres(0);
                yield from $this->trdors();
                $this->MenuFenetres(1);
                break;
            case 11:
                $this->MenuFenetres(0);
                yield from $this->trfab($this->rega);
                $this->MenuFenetres(1);
                break;
        }
    }

    /** AffichePictAide: the help picture of the controls (0 side view, 1 landscape) */
    public function AffichePictAide(int $mode): void
    {
        $gbak = $this->curPort;
        $this->CarboniseSetPortWindow();
        if ($mode === 0) {
            $r = [320 - 125, 230, 320 + 125, 230 + 160];
            $num = 131;
        } else {
            $r = [32, 230, 32 + 250, 230 + 160];
            $num = 132;
        }
        $this->AffichePictDansAire($num, $r);
        $this->curPort = $gbak;
    }

    public function TraiteVueLaterale(): \Generator
    {
        $this->MenuFenetres(0);
        $this->efbadass();
        $this->TfrBadAss();
        $this->bruit(9);
        $this->AffichePictAide(0);
        $this->effaceBulles();
        yield from $this->combat();
        $this->pos0_mouse();
        $this->MenuFenetres(1);
    }

    /** main: the whole game */
    public function main(): \Generator
    {
        yield from $this->initprim();
        while (true) {
            yield from $this->clavm();
            yield from $this->action();
            if ($this->flcomb) {
                yield from $this->TraiteVueLaterale();
            }
        }
    }
}
