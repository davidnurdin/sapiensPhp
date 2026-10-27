<?php

namespace App\Sapiens;

/**
 * SAPCOMB.C: the side view (fights between up to 2 against 2): the animation interpreter (mvtsap),
 * the fighters (prm, posvit, pogne, actbra...), the AI (autonhom, bestio, gercop), the spears, the
 * heads / hearts of the top (generique) and the loop of the view (combat / laterb).
 */
trait CombTrait
{
    /** long nbHerbes (defined in SAPCOMB.C): grass of the foreground (world density, saved) */
    public int $nbHerbes = 0;
    private int $NbHerbeAvPlan = 0;
    /** @var array<int, array{type:int, lin:int, col:int, sens:int}> struct brin herbe[] */
    private array $herbe = [];
    private ?string $TabIndexHerbeFond = null;
    /** clav: "while(getkey(0)&16);" after a change of weapon (done by laterb, which can wait) */
    private bool $combPogneWait = false;

    private const LIGNE_BASE_HERBE_AV_PLAN = 63;
    private const LIGNE_MAX_HERBE_AV_PLAN = 70;
    private const LIMITE_GAUCHE = -5;
    private const LIMITE_DROITE = 62;
    private const LIGNE_CHEMIN = 68;
    private const GRANULOSITE = 10;
    private const NB_ARBRES_LATERAL = 3;
    private const NB_ARBRES_LATERAL_MAX = 20;
    private const NB_HERBE_ARR_PLAN = 15;
    private const NB_MAX_HERBE_AR_PLAN = 100;

    /** DECSOU: out of breath */
    public function decsou(Sap $s, int $combien): void
    {
        $tfr = ($s->souffle - $combien) & 0xFF;
        if ($tfr < $s->souffle) {
            $s->souffle = $tfr;
        } else {
            $this->decvie($s, 1);
        }
    }

    /** INCSOU: gets breath back */
    public function incsou(Sap $s, int $combien): void
    {
        $tfr = ($s->souffle + $combien) & 0xFF;
        if ($tfr > $s->souffle) {
            $s->souffle = $tfr;
        } else {
            $s->souffle = 255;
        }
    }

    /** POSAN: sets the animations of a posture (debban[anim - 1]) */
    public function posan(Sap $sapiens, int $anim): void
    {
        $anim--;
        $row = Anim::DEBBAN[$anim] ?? null;
        if ($row === null) {
            return;
        }
        for ($i = 0; $i !== 14; $i++) {
            if ($row[$i]) {
                $sapiens->sapanim[$i] = $row[$i];
            }
        }
    }

    /** ARRET */
    public function arret(Sap $sapiens): void
    {
        if (($sapiens->vitesse & 2) === 0) {
            $sapiens->vitesse = ($sapiens->vitesse ^ 3) & 3;
        }
    }

    /** POSVIT: sets the speed */
    public function posvit(Sap $sapiens): void
    {
        if ($sapiens->hauteur === 0) {
            $sapiens->sens = intdiv(($sapiens->vitesse - 1) & 2, 2);
            if ($sapiens->vitesse & 2) {
                $this->posan($sapiens, 2);
            } else {
                $this->posan($sapiens, 1);
            }
            $this->posan($sapiens, $sapiens->objet + 6);
            return;
        }
        if ($sapiens->hauteur !== 1) {
            $this->arret($sapiens);
            $sapiens->sens = intdiv(($sapiens->vitesse - 1) & 2, 2);
            return;
        }
        if ($sapiens->action < 128) {
            $sapiens->action |= 128;
            if ($sapiens === $this->sap1) {
                $this->decsou($sapiens, 10);
            }
            $this->posan($sapiens, (intdiv($sapiens->vitesse - 1, 2) ^ $sapiens->sens) + 12);
            $sapiens->vitesse = ($sapiens->sens + 2) & 0xFF;
        }
        $this->arret($sapiens);
    }

    /** POGNE: takes the next object in hand (0 nothing, 1 axe, 2 spear) */
    public function pogne(Sap $sapiens): void
    {
        $objet = $sapiens->objet + 1;
        if ($objet > 2) {
            $objet = 0;
        }
        $sapiens->objet = $objet;
        if (($objet === 2 && ($sapiens->nbresag === 0 || ($sapiens->action & 3)))
            || ($objet === 1 && $sapiens->forcehache === 0)) {
            $this->pogne($sapiens);
        } else {
            $this->posan($sapiens, $objet + 6);
        }
    }

    /**
     * JUGE: the opponents of each one.
     * @return int 0: the clan 2 is eliminated, 1: the clan 1 is eliminated, 2: both remain
     */
    public function juge(): int
    {
        $nbre = 0;
        for ($compt = 0; $compt !== 4; $compt++) {
            if ($this->peuple[$compt]->vie) {
                $nbre++;
            }
        }
        if ($nbre === 4) {
            $this->profond[2] = $this->profond[3] = 7;
        } else {
            $this->profond[2] = $this->profond[3] = 10;
        }
        $this->profond[1] = $this->profond[0] = 10;
        if ($nbre === 2 && $this->sap1->vie && $this->sap3->vie) {
            $this->profond[2] = 7;
        }
        $this->sap3->adversaire = $this->sap4->vie ? $this->sap4 : $this->sap2;
        if (($this->sap1->vie + $this->sap3->vie) === 0) {
            return 1;
        }
        if (($this->sap2->vie + $this->sap4->vie) === 0) {
            return 0;
        }
        if ($this->sap1->vie) {
            if ($this->sap3->vie) {
                $this->sap2->adversaire = $this->sap1;
                $this->sap4->adversaire = $this->sap3;
            } else {
                $this->sap2->adversaire = $this->sap1;
                $this->sap4->adversaire = $this->sap1;
            }
        } else {
            $this->sap2->adversaire = $this->sap3;
            $this->sap4->adversaire = $this->sap3;
        }
        return 2;
    }

    /** InitGenerique: the frames of the hearts and heads of the top (debecr) */
    public function InitGenerique(): void
    {
        $gbak = $this->curPort;
        $this->curPort = $this->debecr;
        for ($i = 0; $i < 4; $i++) {
            $l = $this->peuple[$i]->poslcoeur;
            $c = $this->peuple[$i]->posccoeur;
            $this->affomb($this->mobFace + 31, $l - 2, $c - 2, 4, 4, false);
            $this->affomb($this->mobFace + 30, $l - 2 + 22, $c - 2, 4, 4, false);
            $this->curPort = $this->debecr;
            $r = self::InsetRect([($c - 2) * 2, ($l - 2) * 2, ($c + 16 + 2) * 2, ($l + 16 + 2) * 2], -1, -1);
            $this->DoFrameRect($r);
            $this->DoFrameRect(self::OffsetRect($r, 0, 22 * 2));
        }
        $this->curPort = $gbak;
    }

    /** EffaceGenerique (does nothing in the 1996 version) */
    private function EffaceGenerique(): void
    {
    }

    /** GENERIQUE: the hearts (health) and heads of the 4 fighters */
    public function generique(bool $trace): void
    {
        for ($i = 0; $i < 4; $i++) {
            $p = $this->peuple[$i];
            $l = $p->poslcoeur;
            $c = $p->posccoeur;
            $f = intdiv($p->cross[self::FORME_TETE], 2);
            $tr = $trace ? 1 : 0;
            if (!$p->vie) {
                $this->AffDebecr($this->mobFace + 31, $l - 2, $c - 2, $tr);
                $this->affomb($this->DEBCRANE() + 10, $l, $c, 2, 2, $trace);
                $this->affomb($this->DEBCRANE() + 10, $l + 22, $c, 2, 2, $trace);
            } else {
                $this->AffDebecr($this->mobFace + 31, $l - 2, $c - 2, $tr);
                $pres = $this->present[$i];
                if ($pres === 0) {
                    $this->AffDebecr($this->DEBCRANE() + 8, $l, $c, $tr);
                }
                $l += 22;
                $this->AffDebecr($this->mobFace + 30, $l - 2, $c - 2, $tr);
                if ($pres && $pres < 5) {
                    $this->AffDebecr($this->DEBTETE() + $f, $l, $c, $tr);
                } else {
                    $this->AffDebecr($this->DEBBEST() + $pres - 5, $l, $c, $tr);
                }
            }
        }
    }

    /** AffStrVolatile: a little text box at the point clicked, until the button is released */
    private function AffStrVolatile(array $pt, string $chaine): \Generator
    {
        if ($chaine === '') {
            return;
        }
        $gbak = $this->curPort;
        $this->CarboniseSetPortWindow();
        $raff = [$pt[0] - 8, $pt[1] - 8, $pt[0] + 100, $pt[1] + 40];
        if ($raff[0] < 0) {
            $raff = self::OffsetRect($raff, -$raff[0], 0);
        }
        if ($raff[2] > 640) {
            $raff = self::OffsetRect($raff, 640 - $raff[2], 0);
        }
        $this->DoEraseRect($raff);
        $box = self::InsetRect($raff, 4, 4);
        // TextBox (geneva 9, left aligned): word wrap with the small font of the game
        $words = explode(' ', $chaine);
        $line = '';
        $y = $box[1];
        $lines = [];
        foreach ($words as $w) {
            $try = $line === '' ? $w : $line . ' ' . $w;
            if ($line !== '' && $this->TailleChaine($try, 10) > $box[2] - $box[0]) {
                $lines[] = $line;
                $line = $w;
            } else {
                $line = $try;
            }
        }
        $lines[] = $line;
        foreach ($lines as $s) {
            if ($y + 10 > $box[3]) {
                break;
            }
            $this->DrawStringBrt($s, $box[0], $y, 10, Mac::BLACK, Mac::WHITE);
            $y += 11;
        }
        $this->qdFore = Mac::BLACK;
        $this->DoFrameRect($raff);
        $this->curPort = $gbak;
        yield from $this->delai(60);
        yield from $this->AttendsFinBouton();
        $this->TfrEcr($raff);
    }

    /** A string of the resources (STR#) for the fonts of the game */
    private function combStr(int $index, string $arg = ''): string
    {
        $s = $this->GetStr($index);
        if ($arg !== '' || str_contains($s, '%s')) {
            $s = str_replace('%s', $arg, $s);
        }
        return $s;
    }

    /** ClickInGenerique: a click in the top: on a heart or a head, says what it is ($pt: window pixels) */
    public function ClickInGenerique(array $pt): \Generator
    {
        for ($i = 0; $i < 4; $i++) {
            $p = $this->peuple[$i];
            $r = [$p->posccoeur * 2 - 4, $p->poslcoeur * 2 - 4, $p->posccoeur * 2 + 36, $p->poslcoeur * 2 + 36];
            if (self::PtInRect($pt, $r)) {
                if ($p->vie <= 0) {
                    $chaine = $this->combStr(19);
                } elseif ($i === 0) {
                    $chaine = $this->combStr(27);
                } elseif ($i === 2) {
                    $chaine = $this->combStr(29);
                } else {
                    $chaine = $this->combStr(28);
                }
                yield from $this->AffStrVolatile($pt, $chaine);
            }
            $r = self::OffsetRect($r, 0, 44);
            if (self::PtInRect($pt, $r)) {
                if ($p->vie <= 0) {
                    $chaine = $this->combStr(19);
                } elseif ($i === 0) {
                    $chaine = $this->combStr(15, $this->nomjoueur);
                } elseif ($i === 2) {
                    $chaine = $this->combStr(16, $this->nomcopain);
                } elseif ($i === 1) {
                    $chaine = $this->combStr(17);
                } else {
                    $chaine = $this->combStr(18);
                }
                yield from $this->AffStrVolatile($pt, $chaine);
            }
        }
    }

    /** ABANDO: the objects left by a dead one */
    public function abando(Sap $s): void
    {
        if ($s->animal) {
            for ($i = 0; $i < $this->qtviande[$s->animal - 1]; $i++) {
                $this->ajoutl($this->liste4, 37);
            }
            switch ($s->animal) {
                case 1:
                    $this->ajoutl($this->liste4, 33);
                    break;
                case 3:
                    $this->ajoutl($this->liste4, 35);
                    $this->ajoutl($this->liste4, 32);
                    break;
                case 4:
                    $this->ajoutl($this->liste4, 34);
                    $this->ajoutl($this->liste4, 31);
                    break;
            }
        } else {
            if ($this->echan > 2) {
                $this->ajoutl($this->liste4, $this->echan);
            }
            $i = $s->possession->taille;
            while ($i) {
                $this->ajoutl($this->liste4, $s->possession->elements[--$i]);
            }
            $s->possession->taille = 0;
            $this->echan = 0;
        }
    }

    /** DECVIE: loses life ($valeur: unsigned short, a "negative" damage heals like in C) */
    public function decvie(Sap $s, int $valeur): void
    {
        if (!$s->vie) {
            return;
        }
        $valeur &= 0xFFFF;
        $tfr = self::ss($s->vie - $valeur);
        if ($tfr > 0) {
            $s->vie = $tfr & 0xFF;
            $s->coeur = $this->DEBCRANE() + intdiv($s->vie, 32);
            $s->cptrsouffle = 1;
            // hitting our friend
            if ($s === $this->sap3 && !$this->presence && $valeur) {
                $this->presence = -1;
                $this->sap3clav = 0;
                $s->adversaire = $this->sap1;
                $this->viecopain = 0;
            }
        } else {
            $s->vie = 0;
            if (!$s->animal) {
                $s->flg_mort = 16;
                $this->bruit($s->cross[self::FORME_BUSTE] < 8 ? 11 : 12);
            } else {
                $s->flg_mort = 1;
            }
        }
    }

    /** GER_MORT: the death (falls to the ground) */
    private function ger_mort(Sap $s): void
    {
        if (!$s->flg_mort) {
            return;
        }
        $s->flg_mort--;
        if (!($s->flg_mort & 2) && !$s->animal) {
            $this->baisse($s);
            if ($s->hauteur >= 3) {
                $s->flg_mort = 0;
            }
        }
        if ($s->flg_mort) {
            return;
        }
        if ($this->presence === -1) {
            $this->sap2->vie = $this->sap3->vie;
            $this->sap3->vie = 0;
        }
        if ($s === $this->sap3) {
            if ($this->presence === -1) {
                $this->viecopain = $this->chef = 0;
                if ($this->charisme > 10) {
                    $this->charisme = ($this->charisme - 10) & 0xFF;
                    $this->param[2] = ($this->param[2] - 10) & 0xFF;
                }
            } else {
                $this->viecopain = ($this->viecopain - 1) & 0xFF;
            }
            $this->flefba = 0;
            if (!$this->viecopain) {
                $this->present[2] = 0;
                $this->comment(112);
            } else {
                $this->comment(111);
            }
        }
        if ($this->presence !== $this->juge()) {
            $this->fin = $this->juge() + 1;
        }
        if ($s !== $this->sap1) {
            $this->abando($s);
        }
        if (!$s->animal) {
            if ($s->colonne > self::LIMITE_DROITE) {
                $s->colonne = 1;
            }
            if ($s->sens) {
                $s->colonne += 8;
            }
            $this->AffmobConst($this->MOB_MORT() + intdiv($s->cross[self::FORME_TETE], 2), 100 - 34 + $this->profond[$this->who],
                4 * ($s->colonne + 8 * ($s->colonne < 8 ? 1 : 0)), $s->sens);
        } else {
            $this->AffmobConst($this->MOB_ANIMAL_MORT() + $s->animal - 1, 100 - 20 + $this->profond[4] - 8 * ($s->animal > 2 ? 1 : 0),
                4 * $s->colonne, $s->sens);
        }
        $s->colonne = 200;
        $this->generique(true);
    }

    /** TOUCHESAG: does the spear of s1 hit s2 */
    public function touchesag(Sap $s1, Sap $s2): void
    {
        if ($s2->colonne < 200 && $s1->snssag < 128) {
            $test = self::sc($s1->colsag - $s2->colonne);
            if ($test < 0) {
                $test = self::sc(-$test);
            }
            if ($test < 3 && $s1->linsag > $this->hautou[$s2->hauteur]) {
                $s2->blockimpact = 4;
                $this->bruit(1);
                $tfr = self::sc($s1->linsag);
                if ($tfr > 30) {
                    $tfr = 30;
                }
                $s2->ligneimpact = $tfr & 0xFF;
                $s2->colimpact = ($s1->colsag + 6 * ($s1->snssag === 0 ? 1 : 0)) & 0xFF;
                $this->decvie($s2, 30);
                $this->AffmobConst($this->MOB_FLECHE(), 100 - 23 + $this->profond[$this->who], 4 * $s1->colsag, $s1->snssag);
                $this->ajoutl($this->liste4, 45);
                $s1->snssag = 255;
            }
        }
    }

    /** GERSAG: the flying spear */
    public function gersag(Sap $s1): void
    {
        if ($s1->snssag < 128) {
            $tfr = self::sc($s1->snssag ? $s1->colsag + 3 : $s1->colsag - 3);
            if ($tfr < 0 || $tfr > 63) {
                $s1->snssag = 255;
            } else {
                $s1->colsag = $tfr;
                $s1->linsag++;
                if ($s1->linsag > 45) {
                    $this->AffmobConst($this->MOB_FLECHE(), 100 - 23 + $this->profond[$this->who], 4 * $s1->colsag, $s1->snssag);
                    if ($this->Rnd() > 200) {
                        $this->ajoutl($this->liste4, self::SAGAIE);
                    } else {
                        $this->ajoutl($this->liste4, 45);
                    }
                    $s1->snssag = 255;
                } else {
                    $this->Affmob($this->MOB_FLECHE(), $s1->linsag + $this->profond[$this->who] + 32, 4 * $s1->colsag, $s1->snssag);
                }
            }
            if ($this->who === 0) {
                $this->touchesag($s1, $this->sap2);
                $this->touchesag($s1, $this->sap3);
                $this->touchesag($s1, $this->sap4);
            } else {
                $this->touchesag($s1, $s1->adversaire);
            }
        }
    }

    /** SAUT: gets up */
    public function saut(Sap $sapiens): void
    {
        if ($sapiens->hauteur === 0 || ($sapiens->action & 128)) {
            return;
        }
        $sapiens->hauteur--;
        $sapiens->action = 0;
        if ($sapiens->hauteur === 0) {
            $this->posvit($sapiens);
            return;
        }
        $this->posan($sapiens, $sapiens->hauteur + 8);
        $this->arret($sapiens);
        $this->posan($sapiens, $sapiens->objet + 6);
    }
    /** TOUCHE: s strikes s2 (the distance of the strike is the byte at ptranim) */
    public function touche(Sap $s, Sap $s2): void
    {
        $d = self::sc(Anim::ANIM[$this->ptranim] ?? 0);
        $coltest = $s->sens ? $s->colonne + $d : $s->colonne - $d;
        $tfr = abs($coltest - $s2->colonne);
        if ($tfr > 3 || $s2->hauteur > $s->hauteur + 1) {
            return;
        }
        $s->blockimpact = 4;
        $s->colimpact = ($s2->colonne + ($s2->sens ? -3 : 2) + 6 * $s->sens) & 0xFF;
        if ($s->colimpact > 128) {
            $s->colimpact = 0;
        } elseif ($s->colimpact > self::LIMITE_DROITE + 3) {
            $s->colimpact = self::LIMITE_DROITE + 3;
        }
        $s->ligneimpact = $this->tabinp[$s->hauteur];
        if (intdiv($s->hauteur, 2) === intdiv($s2->hauteur, 2) && $s->sens !== $s2->sens && ($s2->action & 3)) {
            // same height, face to face, both strike: the weapons meet
            $s->blockimpact = 2;
            if ($this->Rnd() < 10) {
                $this->bruit(4);
                if ($s2->objet === 1) {
                    $s2->forcehache = 0;
                    $this->pogne($s2);
                }
                if ($s->objet === 1) {
                    $s->forcehache = 0;
                    $this->pogne($s);
                }
            } else {
                $this->bruit(2);
            }
        } else {
            if ($s->sens) {
                if ($s2->colonne < self::LIMITE_DROITE - 3) {
                    $s2->colonne += 3;
                }
            } else {
                if ($s2->colonne > self::LIMITE_GAUCHE + 3) {
                    $s2->colonne -= 3;
                }
            }
            $dommage = 5;
            if ($s->objet === 1) {
                $dommage = $s->forcehache;
            }
            if ($s->hauteur === 0) {
                $dommage *= 2;
            }
            if ($s2 === $this->sap1) {
                $dommage -= intdiv($this->resistance, 50);
            }
            $this->decvie($s2, $dommage);
            if ($s->animal) {
                if ($this->Rnd() > 200) {
                    $this->bruit(8);
                }
            } else {
                $this->bruit(0);
            }
        }
    }

    /** GCOEUR: the heart beats */
    private function gcoeur(Sap $s): void
    {
        if ($s->cptrsouffle < 32) {
            $s->cptrsouffle = $s->souffle;
            $quoi = self::sc($s->coeur);
            if ($quoi < 0) {
                $quoi = 1 - $quoi;
            }
            $this->affomb($quoi, $s->poslcoeur, $s->posccoeur, 0, 0, true);
            $s->coeur = -$s->coeur;
        } else {
            $s->cptrsouffle -= 32;
        }
    }

    /** IMPAK: the impacts */
    private function impak(Sap $s): void
    {
        if ($s->blockimpact) {
            $this->Affmob($s->blockimpact + $this->MOB_IMPACT(), $s->ligneimpact + $this->profond[$this->who] + 32, 4 * $s->colimpact, $s->sens);
            if (!(--$s->blockimpact)) {
                $this->AffmobConst($this->MOB_SANG_A_TERRE(), 100 + $this->profond[$this->who] - 19 - $this->myRandom(0, 4), 4 * $s->colimpact, $s->sens);
            }
        }
    }

    private function avasap(Sap $s): void
    {
        if ($s->sens === 0) {
            $this->decvit($s);
        } else {
            $this->incvit($s);
        }
    }

    private function recusap(Sap $s): void
    {
        if ($s->hauteur === 1 && !($s->action & 128)) {
            if ($s->sens === 0) {
                $this->incvit($s);
            } else {
                $this->decvit($s);
            }
        }
    }

    /** FIXCOLON: the column of arrival in the view */
    private function fixcolon(Sap $s): void
    {
        if ($s === $this->sap3) {
            $s->colonne = $this->sap1->colonne;
            $s->sens = $this->sap1->sens;
            return;
        }
        if ($s === $this->sap1 && $s->colonne > 64) {
            $s->colonne = 2;
            $s->sens = 1;
            return;
        }
        if ($s->colonne > 32) {
            if ($s !== $this->sap1) {
                $s->sens = 0;
                $s->colonne = 53 + $this->who;
            } else {
                $s->colonne = 55;
            }
        } else {
            if ($s !== $this->sap1) {
                $s->sens = 1;
                $s->colonne = 5 - $this->who;
            } else {
                $s->colonne = 1;
            }
        }
    }

    /** INIUNE: first initialisations of the side view */
    public function iniune(Sap $s): void
    {
        $s->sapanim[10] = Anim::O_ANEUTR;
        $s->sapanim[11] = Anim::O_ANEUTR;
        $s->action = 0;
        $s->ligne = 2;
        $s->hauteur = 0;
        $s->objet = 0;
        $this->posan($s, 2);
        $s->sens = $s->colonne > 32 ? 0 : 1;
        $this->fixcolon($s);
        $s->vitesse = $s->sens | 2;
        $s->animal = $s->deltah = 0;
        $s->souffle = 200;
        $s->cptrsouffle = 1;
    }

    /**
     * PRM: initialises an encounter. type: 0 nobody, 1 Pieds Agiles, 2 Yeux Malins, 3 Bras de Roc,
     * 4 Hyenes Folles, 5 wolf, 6 bear, 7 rabbit, 8 ptarmigan, -1 the player
     */
    public function prm(Sap $s, int $type): void
    {
        $s->snssag = 255;
        $s->fuite = 0;
        $s->blockimpact = 0;
        $s->flg_mort = 0;
        if ($s === $this->sap1) {
            $this->who = 0;
        }
        if ($s === $this->sap2) {
            $this->who = 1;
        }
        if ($s === $this->sap3) {
            $this->who = 2;
        }
        if ($s === $this->sap4) {
            $this->who = 3;
        }
        $this->present[$this->who] = $type;
        if ($type === 0) {
            $s->colonne = 200;
            $s->vie = 0;
            return;
        }
        if ($type < 0 || ($this->who === 2 && $type < 5)) {
            if ($type === -1) {
                $this->sap1->vie = (intdiv($this->vie * 50, 256) + intdiv($this->bless * 93, 256)
                    + intdiv($this->faim * 46, 256) + intdiv($this->soif * 46, 256)) & 0xFF;
            } else {
                $s->vie = ($this->viecopain * 60 + 20) & 0xFF;
            }
            // tribforce[-2] for the player: the C reads hautou[2] (the array before)
            $s->forcehache = $type - 1 >= 0 ? $this->tribforce[$type - 1] : $this->hautou[4 + $type - 1];
            $this->iniune($s);
            $this->decvie($s, 0);
            return;
        }
        for ($i = 0; $i !== 7; $i++) {
            $s->cross[$i] = 0;
        }
        if ($type < 5) {
            do {
                $s->cross[self::FORME_BUSTE] = 2 * $this->myRandom(0, 4);
                if ($this->chef && $s === $this->sap2) {
                    $i = 8;
                    $s->cross[self::FORME_BUSTE] = 0;
                } else {
                    if ($this->Rnd() < 100) {
                        // females
                        $i = 12 + intdiv($this->Rnd(), 43) * 2;
                        $s->cross[self::FORME_BUSTE] += 8;
                    } else {
                        $i = 2 * ($this->Rnd() & 3);
                    }
                }
                if ($type === 4) {
                    $i = 10;
                }
            } while ($i === $this->sap1->cross[self::FORME_TETE]);
            $s->cross[self::FORME_TETE] = $i;
            $s->cross[self::FORME_ARME] = $this->Rnd() & 1;
        }
        $s->colonne = $this->sap1->colonne > 32 ? 2 : 54;
        $this->iniune($s);
        if ($type <= 4) {
            $s->nbresag = 4;
            $s->agressivite = intdiv($this->agress, 256);
            $s->forcehache = $this->tribforce[$type - 1];
            $s->vie = $this->tribvie[$type - 1] & 0xFF;
            if ($this->chef && $this->tribu === 1) {
                $s->agressivite = 255;
                $s->forcehache = 40;
                $s->vie = 200;
            }
            $this->posvit($s);
        } else {
            $s->sapanim[1] = $type >= 7 ? Anim::O_ANLAP1 : Anim::O_ANLOU1;
            $s->sapanim[0] = $s->sapanim[1];
            $s->sapanim[2] = 0;
            $s->sapanim[3] = 0;
            $s->cross[0] = (($type & 1) ^ 1) * 4;
            $s->animal = (($type - 5) ^ 2) + 1;
            $s->vie = $this->bestvie[$type - 5] & 0xFF;
            $s->ligne = $this->bestlin[$type - 5] & 0xFF;
            $s->hauteur = $this->besthaut[$type - 5] & 0xFF;
            $s->agressivite = $this->bestagress[$type - 5] & 0xFF;
            $s->vitesse = 0;
            $s->objet = 1;
            $s->forcehache = 10;
        }
        $this->decvie($s, 0);
        $this->generique(false);
    }

    /** BAISSE: goes down (crouched, kneeling, lying) */
    public function baisse(Sap $s): void
    {
        if ($s->hauteur < 3) {
            $s->hauteur++;
            $s->action = 0;
            $this->posan($s, $s->hauteur + 8);
            $this->arret($s);
            $this->posan($s, $s->objet + 6);
        }
    }

    /** ACTBRA: strikes / throws */
    public function actbra(Sap $s): void
    {
        if (!($s->action & 3) && ($s->souffle > 32 || $this->who)) {
            if ($s === $this->sap1) {
                $this->decsou($s, 20);
            }
            $s->action = ($s->action | ($s->objet + 1)) & 0xFF;
            if ($s->objet === 2) {
                if ($s->snssag > 128 && $s->nbresag) {
                    $s->colsag = $s->colonne + 2;
                    $s->nbresag--;
                    $s->linsag = $this->hautlin[$s->hauteur];
                    $s->snssag = $s->sens;
                } else {
                    return;
                }
            }
            $this->posan($s, $s->objet + 3);
        }
    }

    /** DEPLAT: walking out of the side view moves in the world */
    public function deplat(): void
    {
        if ($this->sap1->colonne < 32) {
            $this->ldzeta = ($this->dzeta + 512) & 0x03FF;
            $this->sap1->colonne = self::LIMITE_DROITE - 1;
        } else {
            $this->ldzeta = $this->dzeta;
            $this->sap1->colonne = self::LIMITE_GAUCHE + 1;
        }
        $this->sapdep($this->ldzeta, 46);
        $this->minute = 0;
        $this->chrono(0);
    }

    /**
     * MVTSAP: runs the animations of a fighter and draws its parts.
     * @return int 1 if it must be called again at once
     */
    public function mvtsap(Sap $sapiens): int
    {
        $anim = Anim::ANIM;
        $this->sensmob = $sapiens->sens;
        $registerb = 0;
        $ptrcross = 0;
        $nummob = 0;
        while (true) {
            if ($registerb > 14) {
                return 0;
            }
            $this->ptranim = $sapiens->sapanim[$registerb] ?? 0;
            if (!$this->ptranim) {
                return 0;
            }
            $ok = 0;
            while (!$ok) {
                $tfr = self::sc($anim[$this->ptranim++] ?? 0xFF);
                if ($tfr >= 0) {
                    $ok++;
                    $nummob = $tfr;
                    continue;
                }
                if ($tfr === -1) {
                    // end of the animation: back to the standard one
                    $this->ptranim = $sapiens->sapanim[$registerb + 1] ?? 0;
                    if (!$this->ptranim) {
                        return 0;
                    }
                    continue;
                }
                $tfr &= 127;
                if ($tfr === 0) {
                    // vertical move
                    $sapiens->deltah = $anim[$this->ptranim++] & 0xFF;
                    continue;
                }
                if ($tfr === 1) {
                    // a step
                    if ($registerb === 0) {
                        $lastcol = $sapiens->colonne;
                        if ($this->sensmob) {
                            $sapiens->colonne++;
                        } else {
                            $sapiens->colonne--;
                        }
                        if (($this->sensmob === 0 && $sapiens->colonne <= self::LIMITE_GAUCHE)
                            || ($this->sensmob === 1 && $sapiens->colonne > self::LIMITE_DROITE)) {
                            if ($this->presence) {
                                $sapiens->colonne = $lastcol;
                                if ($this->present[$this->who] < 5) {
                                    $this->arret($sapiens);
                                    if ($sapiens->hauteur) {
                                        $this->posan($sapiens, $sapiens->hauteur + 8);
                                    } else {
                                        $this->posan($sapiens, 2);
                                    }
                                    $this->posan($sapiens, $sapiens->objet + 6);
                                    $sapiens->action &= 127;
                                    return 1;
                                }
                            } else {
                                $this->fin = 128;
                                if ($sapiens === $this->sap3) {
                                    if (!$this->sap1->vie) {
                                        $this->fin = 129;
                                    }
                                    $this->sap1->colonne = $this->sap3->colonne;
                                    $this->sap1->sens = $this->sap3->sens;
                                }
                                return 0;
                            }
                        }
                    }
                    continue;
                }
                if ($tfr === 2) {
                    // footprint
                    $this->AffmobConst($this->MOB_EMPREINTE(), 100 + $this->profond[$this->who] - 19,
                        4 * ($sapiens->colonne + $sapiens->sens * 5), $sapiens->sens);
                    continue;
                }
                if ($tfr === 3) {
                    // neutral: next part
                    $ptrcross++;
                    $ok = 2;
                    break;
                }
                if ($tfr === 4) {
                    $sapiens->hauteur = ($sapiens->hauteur - 1) & 0xFF;
                    $this->arret($sapiens);
                    $this->posan($sapiens, 10);
                    return 1;
                }
                if ($tfr === 5) {
                    // step back
                    if ($registerb === 0) {
                        $lastcol = $sapiens->colonne;
                        if ($this->sensmob) {
                            $sapiens->colonne--;
                        } else {
                            $sapiens->colonne++;
                        }
                        if (($this->sensmob === 1 && $sapiens->colonne < self::LIMITE_GAUCHE + 3)
                            || ($this->sensmob === 0 && $sapiens->colonne > self::LIMITE_DROITE + 3)) {
                            if ($this->presence) {
                                $sapiens->colonne = $lastcol;
                            } else {
                                $this->fin = 128;
                                if ($sapiens === $this->sap3) {
                                    if (!$this->sap1->vie) {
                                        $this->fin = 129;
                                    }
                                    $this->sap1->colonne = $this->sap3->colonne;
                                    $this->sap1->sens = $this->sap3->sens;
                                }
                                return 0;
                            }
                        }
                    }
                    continue;
                }
                if ($tfr === 6) {
                    // end of the weapon
                    $sapiens->action = $sapiens->action & ($anim[$this->ptranim++] & 0xFF);
                    continue;
                }
                if ($tfr === 7) {
                    // hit test
                    if ($this->who === 0) {
                        if ($this->presence < 1) {
                            $this->touche($sapiens, $this->sap3);
                        } else {
                            $this->touche($sapiens, $this->sap2);
                            $this->touche($sapiens, $this->sap4);
                        }
                    } else {
                        $this->touche($sapiens, $sapiens->adversaire);
                    }
                    $this->ptranim++;
                    continue;
                }
                if ($tfr === 8) {
                    // end of the spear
                    $this->pogne($sapiens);
                    continue;
                }
            }
            if ($ok === 1) {
                $linmob = $sapiens->ligne + self::sc($anim[$this->ptranim++] ?? 0);
                $tfr = self::sc($anim[$this->ptranim++] ?? 0);
                if ($sapiens->sens) {
                    $tfr = self::sc(-$tfr + 6);
                    if ($sapiens->animal === 4) {
                        $tfr = self::sc($tfr + 8);
                    }
                }
                $sapiens->sapanim[$registerb] = $this->ptranim;
                $colmob = $tfr + $sapiens->colonne;
                if ($colmob > 127) {
                    return 0;
                }
                if ($nummob > 10) {
                    $linmob += $sapiens->deltah;
                }
                $this->Affmob($nummob + $sapiens->cross[$ptrcross++ & 7] + $this->MOB_CORPS(),
                    self::sc($linmob) + self::sc($this->profond[$this->who]) + 32, 4 * $colmob, $sapiens->sens);
            }
            $registerb += 2;
        }
    }

    /** DECVIT: slower (or towards the left) */
    public function decvit(Sap $sapiens): void
    {
        $tfrvit = ($sapiens->vitesse - 1) & 0xFF;
        if ($tfrvit) {
            $sapiens->vitesse = $tfrvit;
            $this->posvit($sapiens);
        }
    }

    /** INCVIT: faster (or towards the right) */
    public function incvit(Sap $sapiens): void
    {
        $tfrvit = ($sapiens->vitesse + 1) & 0xFF;
        if ($tfrvit < 5) {
            $sapiens->vitesse = $tfrvit;
            $this->posvit($sapiens);
        }
    }

    /** CLAV: the keys during a fight (bitmap of getkey) */
    public function clav(Sap $s, int $caract): void
    {
        if (!$caract) {
            return;
        }
        if ($caract & 4) {
            $this->decvit($s);
        } elseif ($caract & 8) {
            $this->incvit($s);
        }
        if ($caract & 1) {
            $this->saut($s);
        } elseif ($caract & 2) {
            $this->baisse($s);
        }
        if ((($caract & 18) === 18 || ($caract & 64)) && $this->presence !== -1) {
            $this->fin = 129;
        }
        if ($caract & 16) {
            $this->pogne($s);
            // the C waits here for the key to be released: laterb does it
            $this->combPogneWait = true;
        }
        if ($caract & 32) {
            $this->actbra($s);
        }
    }

    /** GERCOP: the friend (human or animal) follows the player */
    public function gercop(Sap $scop, int $type): void
    {
        $this->distsp = $this->sap1->colonne - $scop->colonne;
        if ($this->distsp < 0) {
            $this->distsp = -$this->distsp;
            $sens = 0;
        } else {
            $sens = 1;
        }
        if ($this->distsp > 3) {
            $scop->sens = $sens;
            if ($type) {
                if ($scop->hauteur) {
                    $this->saut($scop);
                } else {
                    $this->avasap($scop);
                }
            } else {
                $scop->sapanim[1] = $this->present[$this->who] > 6 ? Anim::O_ANLAP2 : Anim::O_ANLOU2;
            }
        } else {
            if (!$type) {
                $scop->sens = $this->sap1->sens;
                $a = $this->present[$this->who] > 6 ? Anim::O_ANLAP1 : Anim::O_ANLOU1;
                $scop->sapanim[1] = $scop->sapanim[0] = $a;
                $scop->coeur = $this->DEBCRANE() + 8;
                $scop->cptrsouffle = 1;
                return;
            }
            if ($scop->objet) {
                $this->pogne($scop);
            }
            if ($scop->hauteur) {
                $this->saut($scop);
            } else {
                $scop->vitesse = $this->sap1->vitesse;
                $this->arret($scop);
                $this->posvit($scop);
            }
        }
    }

    /** AUTONHOM: the AI of a human fighter (s2) against s1 */
    public function autonhom(Sap $s2, Sap $s1): void
    {
        // a moral game: my friend may refuse to strike the same opponent as me
        if ($s2 === $this->sap3) {
            if ($this->sap1->vie && !$this->sap4->vie && $this->Rnd() > 60) {
                return;
            }
        }
        $this->distsp = $s1->colonne - $s2->colonne;
        if ($this->distsp < 0) {
            $this->distsp = -$this->distsp;
            $sens = 1;
        } else {
            $sens = 0;
        }
        $distsp = $this->distsp;
        if ($s2->vie < 20 || $this->Rnd() > 160) {
            if ($s1->hauteur > 1 && $this->Rnd() > 128) {
                $s2->fuite = 4;
            } elseif ($s2->fuite && $this->Rnd() > 200) {
                $s2->fuite = intdiv($this->Rnd(), 13);
            }
        }
        if ($sens === $s2->sens) {
            if ($s2->hauteur) {
                $this->saut($s2);
            }
            $s2->sens ^= 1;
            return;
        }
        if ($distsp > 8) {
            if ($s2->objet === 2) {
                if ($this->Rnd() < $s2->agressivite) {
                    $this->actbra($s2);
                }
            } else {
                if ($this->Rnd() < 20 && $s2->nbresag) {
                    $this->pogne($s2);
                    return;
                }
            }
            if ($this->Rnd() < 60) {
                if ($s2 !== $this->sap3 || $this->present[1] < 7) {
                    $this->avasap($s2);
                }
            } else {
                $this->saut($s2);
            }
            return;
        }
        if ($distsp < 2 || $s2->fuite) {
            if ($s2->hauteur > 1) {
                $this->saut($s2);
                return;
            }
            if ($s2->hauteur === 0) {
                $this->baisse($s2);
                return;
            }
            if ($s2->fuite) {
                $s2->fuite--;
            }
            $this->recusap($s2);
            return;
        }
        if ($s2->hauteur < 2 && $this->Rnd() > 180 && ($s2->action & 128) === 0) {
            $this->baisse($s2);
        }
        if ($distsp < 6) {
            if ($this->Rnd() < intdiv($s2->souffle * $s2->agressivite, 512) + 128) {
                $this->actbra($s2);
                return;
            } elseif (($s1->action || $distsp < 4) && ($s2->action & 128) === 0) {
                if ($s2->hauteur < 2) {
                    $this->recusap($s2);
                } elseif ($this->Rnd() > 160) {
                    $this->baisse($s2);
                } else {
                    $this->saut($s2);
                }
            } else {
                $this->baisse($s2);
            }
        } else {
            if ($s2->hauteur >= 2) {
                $this->saut($s2);
                return;
            }
            if ($s2->objet !== 1 && $this->Rnd() > 70) {
                $this->pogne($s2);
            }
            if ($this->Rnd() > 80) {
                $this->avasap($s2);
            }
            return;
        }
        if ($s1->action & 3) {
            if ($s1->hauteur >= 2 && $this->Rnd() < 10) {
                $this->baisse($s2);
            } else {
                $this->saut($s2);
            }
            return;
        }
        if ($this->Rnd() > $s2->agressivite) {
            if ($s2->hauteur >= 2) {
                $this->recusap($s2);
            }
            return;
        }
    }

    /** BESTIO: the animals (1 rabbit, 2 ptarmigan, 3 wolf, 4 bear) */
    public function bestio(Sap $s2, Sap $s1): void
    {
        $this->distsp = $s1->colonne - $s2->colonne;
        if ($this->distsp < 0) {
            $this->distsp = -$this->distsp;
            $sens = 1;
        } else {
            $sens = 0;
        }
        $distsp = $this->distsp;
        $crane = $this->DEBCRANE() + 8;
        if ($s2->animal >= 3) {
            // wolf, bear
            if ($s2->colonne < self::LIMITE_GAUCHE - 3) {
                $s2->sens = 1;
                $s2->colsag = 10 + intdiv($this->Rnd(), 20);
                $s2->fuite = 2;
            }
            if ($s2->colonne > self::LIMITE_DROITE + 3) {
                $s2->sens = 0;
                $s2->colsag = 10 + intdiv($this->Rnd(), 20);
                $s2->fuite = 2;
            }
            if (($s2->vie < 40 && $this->Rnd() > 180) || $this->Rnd() < 10) {
                if (!$s2->fuite) {
                    $s2->sapanim[1] = Anim::O_ANLOU2;
                    $s2->fuite = 1;
                    $s2->colsag = intdiv($this->Rnd(), 12);
                }
            }
            if ($s2->colsag) {
                if ($s2->fuite === 1) {
                    $s2->sens = $sens;
                }
                $s2->colsag--;
            } elseif ($distsp > 4 && $sens === $s2->sens) {
                $s2->sens ^= 1;
            }
            if ($this->Rnd() < $s2->agressivite) {
                $s2->sapanim[1] = $this->Rnd() > 100 ? Anim::O_ANLOU1 : Anim::O_ANLOU2;
            }
            $s2->souffle = 128;
            $s2->coeur = $s2->coeur < 0 ? -$crane : $crane;
        } else {
            // the little rabbit
            $s2->souffle = 1;
            $s2->coeur = $s2->coeur < 0 ? -$crane : $crane;
            if (!$s2->fuite && $this->Rnd() < 60) {
                if (intdiv($this->Rnd(), 8) > $distsp) {
                    $s2->sapanim[0] = Anim::O_ANLAP3;
                    if (($distsp < 10 && $this->Rnd() > 60) || !($s1->vitesse & 2)
                        || $s1->snssag !== 255 || $s1->action) {
                        $s2->sapanim[1] = Anim::O_ANLAP2;
                        $s2->sens = $s1->sens;
                        $s2->fuite = 1;
                    }
                } else {
                    if ($this->Rnd() < $s2->agressivite) {
                        $s2->sapanim[0] = Anim::O_ANLAP2;
                        if ($this->Rnd() < 25 && $distsp < 45) {
                            $s2->sens = $this->Rnd() & 1;
                        }
                    }
                }
            }
            if ($s2->colonne < self::LIMITE_GAUCHE + 3 || $s2->colonne > self::LIMITE_DROITE - 3) {
                // out of the screen: gone
                $s2->sapanim[0] = 0;
                $s2->colonne = 200;
                $s2->vie = 0;
                $this->present[$this->who] = 0;
                $this->generique(true);
                if ($this->presence !== $this->juge()) {
                    $this->fin = $this->juge() + 1;
                }
            }
        }
    }

    /** MOBS: moves and draws the 4 fighters */
    private function mobs(bool $TesteClavier): void
    {
        for ($this->who = 3; $this->who >= 0; $this->who--) {
            $surqui = $this->peuple[$this->who];
            if ($surqui->flg_mort) {
                $this->ger_mort($surqui);
                while ($this->mvtsap($surqui)) {
                }
            } elseif ($surqui->vie) {
                $this->incsou($surqui, 1);
                while ($this->mvtsap($surqui)) {
                }
                if ($this->who === 0) {
                    $tfragil = ($this->cptagil + $this->agilite) & 0xFF;
                    if ($tfragil < $this->cptagil) {
                        if ($TesteClavier) {
                            $this->clav($surqui, $this->getkey(0));
                        }
                    }
                    $this->cptagil = $tfragil;
                } elseif ($this->present[$this->who] < 5) {
                    if ($this->sap3clav && $this->who === 2) {
                        if ($TesteClavier) {
                            $this->clav($surqui, $this->getkey(1));
                        }
                    } elseif (!$this->presence) {
                        $this->gercop($surqui, 1);
                    } else {
                        $this->autonhom($surqui, $surqui->adversaire);
                    }
                } else {
                    if (!$this->presence) {
                        $this->gercop($surqui, 0);
                    } else {
                        $this->bestio($surqui, $surqui->adversaire);
                        // a rabbit also reacts to my friend
                        if ($this->present[$this->who] > 6 && $this->present[2]) {
                            $this->bestio($surqui, $this->sap3);
                        }
                    }
                }
                $this->gcoeur($surqui);
            }
            $this->gersag($surqui);
        }
        for ($this->who = 0; $this->who < 4; $this->who++) {
            $this->impak($this->peuple[$this->who]);
        }
        // the grass of the foreground
        for ($i = 0; $i < $this->NbHerbeAvPlan; $i++) {
            $h = $this->herbe[$i];
            if ($h['type']) {
                $this->Affmob($h['type'], $h['lin'], $h['col'], $h['sens']);
            }
        }
    }

    /** CalculeMobHerbe: a mob of grass */
    private function CalculeMobHerbe(): int
    {
        if ($this->myRandom(0, 1000) > 950) {
            return $this->mobAvplanSpec + 1 + $this->myRandom(0, $this->nbAvplanSpec);
        }
        return $this->MOB_HERBE() + $this->myRandom(0, $this->nbMobHerbes);
    }

    /** AVPLAN: the path, trees and grass of the side view (the same for a place) */
    private function avplan(): void
    {
        if ($this->TabIndexHerbeFond === null) {
            $this->TabIndexHerbeFond = $this->CalculePaletteAttenuee($this->MOB_HERBE(), 0.75, 999);
        }
        $this->initrnd(intdiv($this->xj, 256), intdiv($this->yj, 256), ($this->xj & 224) | intdiv($this->yj & 224, 8));
        if ($this->nbHerbes <= 20) {
            $this->NbHerbeAvPlan = $this->myRandom(0, $this->nbHerbes);
        } else {
            $this->NbHerbeAvPlan = $this->myRandom(0, intdiv($this->nbHerbes, self::GRANULOSITE)) * self::GRANULOSITE;
        }
        $this->NbHerbeAvPlan = min($this->NbHerbeAvPlan, 100);
        for ($i = 0; $i < 8; $i++) {
            $this->Affmob($this->MOB_CHEMIN() + ($this->rnd0() & 3), self::LIGNE_CHEMIN, $i * 8 * 4, 0);
        }
        $nb = $this->myRandom(self::NB_ARBRES_LATERAL, self::NB_ARBRES_LATERAL_MAX);
        for ($i = 0; $i < $nb; $i++) {
            $y = 68.0 + 10.0 * $i / $nb;
            $this->arbre((int)$y, $this->myRandom(0, 250));
        }
        $nb = $this->myRandom(intdiv(self::NB_HERBE_ARR_PLAN, self::GRANULOSITE), intdiv(self::NB_MAX_HERBE_AR_PLAN, self::GRANULOSITE)) * self::GRANULOSITE;
        for ($i = 0; $i < $nb; $i++) {
            $num = $this->CalculeMobHerbe();
            $r = $this->CalculeAireMob($num, 0, 0);
            $this->AffmobPalette($num, self::LIGNE_CHEMIN + 12 - intdiv($r[3], 2) + $this->myRandom(0, 3), $this->myRandom(0, 256),
                $this->rnd0() & 1, $this->TabIndexHerbeFond);
        }
        $this->herbe = [];
        for ($i = 0; $i < $this->NbHerbeAvPlan; $i++) {
            $num = $this->CalculeMobHerbe();
            $r = $this->CalculeAireMob($num, 0, 0);
            $lin = self::LIGNE_MAX_HERBE_AV_PLAN - intdiv(($this->NbHerbeAvPlan - $i) * (self::LIGNE_MAX_HERBE_AV_PLAN - self::LIGNE_BASE_HERBE_AV_PLAN), $this->NbHerbeAvPlan)
                - intdiv($r[3], 2) + 32;
            $h = ['type' => $num, 'lin' => $lin, 'col' => $this->myRandom(0, 240), 'sens' => $this->rnd0() & 1];
            $this->herbe[$i] = $h;
            if ($h['type']) {
                $this->Affmob($h['type'], $h['lin'], $h['col'], $h['sens']);
            }
        }
        $this->CopyEcran1Sur2();
    }

    /** LATERB: one frame of the side view (~6 ticks of 11 ms) */
    public function laterb(bool $TesteClavier): \Generator
    {
        $t = $this->sapiensTimer;
        $this->effdemi();
        $this->mobs($TesteClavier);
        $this->tfrdemi();
        $this->GereInterface();
        do {
            yield;
        } while ($t > $this->sapiensTimer - 6);
        if ($this->combPogneWait) {
            // clav: while(getkey(0)&16);
            while ($this->getkey(0) & 16) {
                yield;
            }
            $this->combPogneWait = false;
        }
    }

    /** LATERA: sets the side view (landscape seen sideways, path, trees, grass) */
    public function latera(): \Generator
    {
        $this->EffaceGenerique();
        $this->generique(true);
        $this->dismin = 50;
        $this->affich(($this->dzeta + 256) & 0x3ff);
        $this->avplan();
        $this->tfrima();
        $this->vq_mouse($this->bouton, $this->dsposx, $this->dsposy);
        $this->reset_clav();
        yield from $this->laterb(false);
        $this->tfrvirt();
    }

    /** INARME: the weapons of the player from its objects */
    public function inarme(): void
    {
        $this->entvie = $this->sap1->vie;
        $this->sap1->forcehache = 0;
        $this->sap1->cross[self::FORME_ARME] = 0;
        $this->sap1->nbresag = 0;
        $this->cptagil = 1;
        $i = $this->liste1->taille;
        while ($i) {
            $i--;
            if ($this->sap1->forcehache === 0) {
                if ($this->liste1->elements[$i] === 22) {
                    // club
                    $this->sap1->forcehache = 10 + intdiv($this->force * 10, 256);
                    $this->sap1->cross[self::FORME_ARME] = 1;
                }
                if ($this->liste1->elements[$i] === 21) {
                    // axe
                    $this->sap1->forcehache = 6 + intdiv($this->force * 6, 256);
                }
            }
            if ($this->liste1->elements[$i] === 23) {
                $this->sap1->nbresag++;
            }
        }
    }

    /** COMBAT: the side view, until it is left (fights, walking) */
    public function combat(): \Generator
    {
        $quelqun = false;
        $this->reset_mouse();
        if ($this->dervue !== self::VUE_LATERALE) {
            $this->prm($this->sap1, -1);
            $this->prm($this->sap3, $this->present[2]);
            yield from $this->latera();
        }
        $this->vq_mouse($this->bouton, $this->dsposx, $this->dsposy);
        $this->dervue = self::VUE_LATERALE;
        $this->clrbadi();
        while (true) {
            $this->sap3clav = ($this->ModeJoueur2 && $this->present[2] < 5) ? 1 : 0;
            $this->inarme();
            $this->presence = $this->juge();
            if ($this->sap2->vie === 0 && $this->sap4->vie === 0) {
                if ($quelqun) {
                    $this->presence = 0;
                    $this->inimen(0);
                    yield from $this->laterb(true);
                    // the menu 'take' by default
                    $this->InverseOption(0, 2);
                    $this->s_action = 3;
                    $this->inimen(4);
                    return;
                }
            } else {
                $quelqun = true;
            }
            $this->fin = 0;
            while (!$this->fin) {
                yield from $this->laterb(true);
            }
            $this->tfrene();
            $this->outarm();
            $this->flcomb = 0;
            $this->flefba = 0;
            $this->fdialo = 0;
            if ($this->presence === -1) {
                $this->present[2] = 0;
            }
            if ($this->fin === 2) {
                // the clan 1 lost
                $this->CopyEcran1Sur2();
                $this->dervue = self::VUE_AUCUNE;
                if ($this->scenar === 5) {
                    $this->mort = 0;
                } else {
                    $tfr = ($this->bless - 100) & 0xFF;
                    if ($tfr > $this->bless) {
                        $this->mort = ($this->mort - 1) & 0xFF;
                        $tfr = 20;
                    }
                    $this->bless = $tfr;
                }
                $this->afstat();
                if ($this->mort === 0) {
                    $this->scenar = 7;
                    yield from $this->sceveu(7);
                    $this->presence = 0;
                    $this->inimen(0);
                    return;
                }
                $this->comment(81);
                if ($this->present[1] < 5 && $this->present[1]) {
                    $this->vole();
                }
                $this->prm($this->sap2, 0);
                $this->prm($this->sap4, 0);
                $this->presence = 0;
                $this->attends_bruit();
                $this->inimen(0);
                return;
            }
            if ($this->fin === 1) {
                // the clan 2 lost
                $this->presence = 0;
                $this->prm($this->sap2, 0);
                $this->prm($this->sap4, 0);
                if ($this->scenar === 4 && $this->chef && $this->tribu === 4) {
                    $this->scenpr = 0;
                    yield from $this->sceveu(9);
                }
                if ($this->scenar === 5) {
                    $this->mort = 0;
                    $this->scenar = 6;
                    $this->jour_dsk = ($this->jour_dsk + 1) & 0xFFFF;
                    yield from $this->sceveu(6);
                    $this->presence = 0;
                    $this->inimen(0);
                    return;
                }
            }
            if (($this->fin === 129 && !$this->presence) || !$this->mort) {
                if (!$this->sap1->vie) {
                    $this->dervue = 255;
                }
                $this->presence = 0;
                $this->inimen(0);
                return;
            }
            if ($this->fin === 128) {
                // out of the screen (we are alone): we move in the world
                $this->clrbadi();
                $this->deplat();
                yield from $this->zoninf();
                if ($this->presence && $this->presence < 5) {
                    $this->prm($this->sap1, $this->present[0]);
                    $this->prm($this->sap3, $this->present[2]);
                    yield from $this->latera();
                    $this->dervue = self::VUE_LATERALE;
                    $this->inimen(0);
                    return;
                }
                $this->fixcolon($this->sap1);
                $this->fixcolon($this->sap3);
                $this->prm($this->sap3, $this->present[2]);
                yield from $this->latera();
            }
        }
    }
}
