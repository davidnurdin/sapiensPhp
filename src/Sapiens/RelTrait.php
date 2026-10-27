<?php

namespace App\Sapiens;

/**
 * SAPREL.C: the relational module (1986): dialogues and relations with the tribes, the clock of the
 * day (chrono), the encounters by position (zonevt / zoninf), sleep, the observation views, the
 * speech bubbles and the messages of the scenario.
 *
 * scenar: 1 meat for the tribe, 2 potion of life (#42) of the Yeux-Malins, 3 ivory axe (#43) of the
 * chief of the Bras-de-Roc, 4 sacred stick (#44) of the Hyenes-Folles, 5 last fight, 6 won, 7 lost.
 */
trait RelTrait
{
    public int $LastObsv = 0;
    /** @var array{0:int,1:int,2:int,3:int} static Rect rBulleJoueur */
    private array $relBulleJoueur = [0, 0, 0, 0];
    /** @var array{0:int,1:int,2:int,3:int} static Rect rBulleAdversaire */
    private array $relBulleAdversaire = [0, 0, 0, 0];

    /** MOTLST: a string on the message line */
    private function motlst(string $debut): void
    {
        $n = strlen($debut);
        for ($i = 0; $i < $n; $i++) {
            $this->affcarmess(ord($debut[$i]));
        }
        $this->TfrBadAss();
    }

    /**
     * TailleChaine (static of SAPREL.C): width in pixels (font 10) and number of line breaks.
     * @return array{0:int,1:int} [tx, ty]
     */
    private function relTailleChaine(string $debut): array
    {
        $x = 0;
        $taillex = 0;
        $y = 0;
        $ty = 0;
        $n = strlen($debut);
        $i = 0;
        while ($i < $n) {
            $car = ord($debut[$i]);
            if ($car === 13) {
                $ty++;
                $x = 0;
                $i++;
            } else {
                // TailleCarMess
                $i++;
                if ($car > 128) {
                    [$tx2, $ty2] = $this->relTailleChaine($this->GetStrBkMot($car));
                    $x += $tx2;
                    $y += $ty2;
                } else {
                    $x += $this->TailleCar($car, 10);
                }
                if ($x > $taillex) {
                    $taillex = $x;
                }
                $ty += $y;
            }
        }
        return [$taillex, $ty];
    }

    /** Sappause: a pause, ended by the mouse (moved or clicked) */
    private function Sappause(int $duree): \Generator
    {
        while ($this->bouton) {
            yield;
            $this->vq_mouse($this->bouton, $this->sposx, $this->sposy);
        }
        $this->dsposx = $this->sposx;
        $this->dsposy = $this->sposy;
        while ($duree && !$this->bouton && $this->sposx === $this->dsposx && $this->sposy === $this->dsposy) {
            yield from $this->delai(1);
            $duree--;
            $this->vq_mouse($this->bouton, $this->sposx, $this->sposy);
        }
    }

    public function efbadi(): void
    {
        $this->Gotoxy(3, 15);
        $this->efbadass();
    }

    public function scrollbadi(): void
    {
        $this->Gotoxy(3, 15);
        $this->scrollbadass();
    }

    /** CLRBADI: clears the message line if it is used */
    public function clrbadi(): void
    {
        if ($this->flagbadi) {
            $this->efbadi();
            $this->flagbadi = 0;
        }
    }

    /** SCEVEU: the message of a phase of the scenario, in the 3D area (6: won -> Final) */
    public function sceveu(int $quoi): \Generator
    {
        $scenme = $this->GetScenme();
        $this->dervue = self::VUE_AUCUNE;
        $this->effvirt(0x007);
        $this->Gotoxy(12, 1);
        $p = (string)($scenme[$quoi - 1] ?? '');
        $n = strlen($p);
        $k = 0;
        $car = $k < $n ? ord($p[$k++]) : 0;
        while ($car) {
            $this->car2($car);
            do {
                $car = $k < $n ? ord($p[$k++]) : 0;
                if ($car === 13) {
                    $this->Gotoxy(12, $this->linaff + 1);
                }
            } while ($car === 13);
        }
        if ($quoi === 6) {
            for ($i = 0; $i < 3; $i++) {
                $this->bruit(6);
                // bruit(6) waited 30 ticks in SAPWMUS.C
                yield from $this->delai(30);
            }
        }
        $this->TfrEcr(null);
        if ($quoi === 6) {
            // you won
            $this->CopyOffscreen($this->debecr, $this->Ecran1, [32 * 2, 1 * 2, (32 + 255) * 2, (1 + 100) * 2], [0, 0, 255 * 2, 100 * 2]);
            yield from $this->delai(15 * 60);
            yield from $this->Final();
        }
    }

    /** SceveuBulle: a message of the scenario in a bubble (index 0-based) */
    public function SceveuBulle(int $quoi): void
    {
        $scenme = $this->GetScenme();
        $this->effaceBulles();
        $this->bulle((string)($scenme[$quoi] ?? ''), 1);
    }

    /** GAINSBOUR: physical decay */
    public function gainsbour(): void
    {
        $this->bless = ($this->bless - (6 - intdiv(6 * $this->resistance, 256))) & 0xFF;
        if ($this->bless > 251) {
            if ($this->mort) {
                $this->mort--;
                $this->bless = 20;
            } else {
                $this->presence = 0;
                $this->fdialo = 0;
            }
        }
    }

    /** PASTMP: time passes (the meat rots) */
    public function pastmp(): void
    {
        $i = $this->liste1->taille;
        while ($i) {
            $i--;
            if ($this->liste1->elements[$i] >= 37 && $this->liste1->elements[$i] < 41) {
                $this->liste1->elements[$i]++;
            }
        }
    }

    /** TFRENE: keeps the anger of the tribe against you */
    public function tfrene(): void
    {
        if ($this->presence < 5 && $this->presence) {
            if ($this->fpress) {
                $this->enetoi[$this->tribu - 1] = $this->enerv;
                if ($this->chef || $this->tribu !== 1) {
                    $this->tagress[$this->tribu - 1] = $this->agress;
                }
            }
        }
    }

    /** COMMENT: a comment (mescon, 1-based) on the message line */
    public function comment(int $rang): void
    {
        $mescon = $this->GetMesCon();
        if (!$this->flefba) {
            $this->flefba++;
            $this->scrollbadi();
        }
        $this->flagbadi = 1;
        $p = (string)($mescon[$rang - 1] ?? '');
        $n = strlen($p);
        for ($i = 0; $i < $n; $i++) {
            $this->affcarmess(ord($p[$i]));
        }
        $this->TfrBadAss();
    }

    public function effaceBulles(): void
    {
        $this->tfrvirt();
        if (!self::EmptyRect($this->relBulleJoueur)) {
            $this->CopyOffscreen($this->debecr, $this->mainWindow, $this->relBulleJoueur, $this->relBulleJoueur);
        }
        $this->relBulleJoueur = [0, 0, 0, 0];
        if (!self::EmptyRect($this->relBulleAdversaire)) {
            $this->CopyOffscreen($this->debecr, $this->mainWindow, $this->relBulleAdversaire, $this->relBulleAdversaire);
        }
        $this->relBulleAdversaire = [0, 0, 0, 0];
    }

    /** BULLE: a speech bubble with a text; $qui 0 = the player, 1 = the opponent */
    public function bulle(string $debut, int $qui): void
    {
        $xDroite = 640 - 64 - 16;
        $xGauche = 80;
        $yBulle = 58;
        $queue = 20;
        $gbak = $this->curPort;
        $this->curPort = $this->debecr;
        [$tx, $ty] = $this->relTailleChaine($debut);
        if ($tx < 64) {
            $tx = 64;
        }
        $gauche = ($qui ^ $this->sap1->sens) !== 0;
        if ($gauche) {
            $r = [$xGauche, $yBulle - ($ty + 2) * 12, $xGauche + $tx + 16, $yBulle];
        } else {
            $r = [$xDroite - $tx - 16, $yBulle - ($ty + 2) * 12, $xDroite, $yBulle];
        }
        $r[3] += $queue;
        $rbase = $r;
        if ($qui === 0) {
            $this->effaceBulles();
            $this->relBulleJoueur = $r;
        } else {
            if (!self::EmptyRect($this->relBulleAdversaire)) {
                $b = $this->relBulleAdversaire;
                $r = self::OffsetRect($r, 0, $b[3] - $b[1] - $queue - 4);
                $this->relBulleAdversaire = [min($b[0], $r[0]), min($b[1], $r[1]), max($b[2], $r[2]), max($b[3], $r[3])];
                $rbase = $this->relBulleAdversaire;
            } else {
                $this->relBulleAdversaire = $r;
            }
        }
        $r[3] -= $queue;
        // the region: a round rectangle (16x16 corners) and its tail
        $x = $gauche ? $r[0] + 48 : $r[2] - 48;
        $y = $r[3];
        $tail = $gauche ? [[$x, $y], [$x - $queue, $y + $queue], [$x - 8, $y]] : [[$x, $y], [$x + $queue, $y + $queue], [$x + 8, $y]];
        $this->relPaintBubble($this->debecr, $r, $tail);
        $this->qdClip = $r;
        $x = $r[0] + 8;
        $y = $r[1] + 6;
        $this->GotoxyPixel($x, $y);
        $n = strlen($debut);
        $k = 0;
        while ($k < $n && $debut[$k] === ' ') {
            $k++;
        }
        while ($k < $n) {
            if ($debut[$k] === "\r") {
                $y += 14;
                $this->GotoxyPixel($x, $y);
                do {
                    $k++;
                } while ($k < $n && $debut[$k] === ' ');
            } else {
                $this->affcar10(ord($debut[$k++]));
            }
        }
        $this->qdClip = null;
        $this->curPort = $gbak;
        $this->TfrEcr($rbase);
        $this->DoPenSize(1, 1);
    }

    /**
     * EraseRgn + FrameRgn of the bubble: white inside, a black outline of one pixel.
     * @param array<int, array{0:int,1:int}> $tail triangle
     */
    private function relPaintBubble(Port $port, array $r, array $tail): void
    {
        $l = min($r[0], $tail[0][0], $tail[1][0], $tail[2][0]);
        $rr = max($r[2], $tail[0][0], $tail[1][0], $tail[2][0]) + 1;
        $t = $r[1];
        $b = max($r[3], $tail[1][1]) + 1;
        $inside = function (int $px, int $py) use ($r, $tail): bool {
            if ($px >= $r[0] && $px < $r[2] && $py >= $r[1] && $py < $r[3]) {
                // corners: ovals of 16x16
                $cx = $px < $r[0] + 8 ? $r[0] + 8 : ($px >= $r[2] - 8 ? $r[2] - 8 : $px);
                $cy = $py < $r[1] + 8 ? $r[1] + 8 : ($py >= $r[3] - 8 ? $r[3] - 8 : $py);
                $dx = $px + 0.5 - $cx;
                $dy = $py + 0.5 - $cy;
                if (($px < $r[0] + 8 || $px >= $r[2] - 8) && ($py < $r[1] + 8 || $py >= $r[3] - 8)) {
                    return $dx * $dx + $dy * $dy <= 64;
                }
                return true;
            }
            // the tail (triangle)
            [$a, $bb, $c] = $tail;
            $s1 = ($bb[0] - $a[0]) * ($py - $a[1]) - ($bb[1] - $a[1]) * ($px - $a[0]);
            $s2 = ($c[0] - $bb[0]) * ($py - $bb[1]) - ($c[1] - $bb[1]) * ($px - $bb[0]);
            $s3 = ($a[0] - $c[0]) * ($py - $c[1]) - ($a[1] - $c[1]) * ($px - $c[0]);
            return ($s1 >= 0 && $s2 >= 0 && $s3 >= 0) || ($s1 <= 0 && $s2 <= 0 && $s3 <= 0);
        };
        $in = [];
        for ($py = $t - 1; $py <= $b; $py++) {
            for ($px = $l - 1; $px <= $rr; $px++) {
                $in[$py][$px] = $inside($px, $py);
            }
        }
        $white = chr(Mac::WHITE);
        $black = chr(Mac::BLACK);
        for ($py = max(0, $t); $py < min($port->h, $b); $py++) {
            for ($px = max(0, $l); $px < min($port->w, $rr); $px++) {
                if (!$in[$py][$px]) {
                    continue;
                }
                $edge = !$in[$py - 1][$px] || !$in[$py + 1][$px] || !$in[$py][$px - 1] || !$in[$py][$px + 1];
                $port->rows[$py][$px] = $edge ? $black : $white;
            }
        }
        $port->dirty = true;
    }

    /**
     * CHRONO: the clock. heure 0..1023 (morning 0-90, afternoon 426-512, night 512-602, end of
     * night 938-1023). quoi 0: normal, 1: no decrease nor message, 2: decrease but no message.
     */
    public function chrono(int $quoi): void
    {
        if ($this->minute) {
            $this->minute--;
            return;
        }
        $this->minute = 5;
        $this->heure = ($this->heure + 1) & 0xFFFF;
        if (($this->heure & 255) === 90) {
            $this->heure += 336;
        }
        $this->heure &= 0x03FF;
        if (!($this->heure & 0xFF)) {
            $this->pastmp();
        }
        while ($this->heure >= $this->tabheur[$this->pos_evt]['h']) {
            if ($this->tabheur[$this->pos_evt]['q']) {
                // the gradient of the sky with its 8 ST colours
                $this->ciel_degr = $this->tabheur[$this->pos_evt]['pal'];
                $this->FixeDegradesFonctionHeure($this->tabheur[$this->pos_evt]);
            } else {
                $this->palhaute = $this->tabheur[$this->pos_evt]['pal'];
            }
            if (++$this->pos_evt >= self::NBREVTHEURE) {
                $this->pos_evt = 0;
                $this->heure = 0;
            }
        }
        if ($this->heure === 0) {
            $this->flefba = 0;
            $this->comment(114);
            $this->jour_act = ($this->jour_act + 1) & 0xFFFF;
        }
        // position of the sun / moon
        if ($this->heure & 0x100) {
            $this->metpos = (($this->heure & 0xFF) - 180) & 0xFFFF;
            $this->angsol = 256;
        } else {
            $this->metpos = (75 - ($this->heure & 0xFF)) & 0xFFFF;
            $this->angsol = 768;
        }
        if ($quoi === 1) {
            return;
        }
        $this->finjou = 1;
        $this->faim = ($this->faim - 2) & 0xFF;
        $avemess = 0;
        if ($this->faim > 253) {
            $this->faim = 5;
            $this->gainsbour();
            $avemess = 93;
        }
        $this->soif = ($this->soif - 4) & 0xFF;
        if ($this->soif > 251) {
            $this->soif = 5;
            $this->gainsbour();
            $avemess = 94;
        }
        $this->fatig = ($this->fatig - 2) & 0xFF;
        if ($this->fatig > 253) {
            $this->fatig = 5;
            $this->gainsbour();
            $avemess = 95;
        }
        if ($avemess && !$quoi) {
            $this->efbadi();
            $this->comment($avemess);
        }
    }

    /** CAUSE: one of the 4 sentences of a context (1..), in a bubble, if one is talking */
    private function cause(int $context, ?string $messAdd): void
    {
        $mescon = $this->GetMesCon();
        if ($this->fdialo) {
            $rang = ($context - 1) * 4 + ($this->Rnd() & 3);
            $chaine = (string)($mescon[$rang] ?? '');
            if ($messAdd !== null) {
                $chaine .= $messAdd;
            }
            $this->bulle($chaine, 1);
        }
    }

    /** FUITE: he runs away */
    public function fuite(): \Generator
    {
        $this->flefba = 0;
        if ($this->agress < 245) {
            $this->agress += 10;
        }
        $this->comment(80);
        yield from $this->quitte();
    }

    /** INTRSQ: value of an object (doubled if it is the favourite one) */
    public function intrsq(int $objet): int
    {
        $refer1 = $this->refer[self::REFER1];
        $valeur = ($refer1[$objet - 1][1] ?? 0) & 0xF;
        if ($objet === $this->predil) {
            $valeur = ($valeur * 2) & 0xFF;
        }
        return $valeur;
    }

    /** RAPIAS: the most interesting object you carry */
    public function rapias(): int
    {
        $max = 0;
        $objet = 0;
        for ($i = 0; $i < $this->liste1->taille; $i++) {
            $valeur = $this->intrsq($this->liste1->elements[$i]);
            if ($valeur > $max) {
                $max = $valeur;
                $objet = $this->liste1->elements[$i];
            }
        }
        return $objet;
    }

    /** EXCITE: the anger increases */
    public function excite(int $valeur): void
    {
        $valeur &= 0xFFFF;
        $tfr = ($this->enerv + $valeur) & 0xFFFF;
        if ($this->enerv <= $tfr) {
            $this->enerv = $tfr;
        } else {
            $this->enerv = 65535;
        }
        if ($this->enerv < 51 * 256) {
            if ($this->fpress) {
                $this->cause(7, null);
            } else {
                $this->cause(2, null);
            }
            return;
        }
        if ($this->enerv < 102 * 256) {
            $this->cause(8, null);
            return;
        }
        if ($this->Rnd() < intdiv($this->enerv, 512)) {
            $this->inimen(0);
            $this->flcomb = 1;
            return;
        }
        if ($this->enerv < 153 * 256) {
            $this->cause(9, null);
            return;
        }
        if ($this->enerv < 204 * 256) {
            $this->cause(10, null);
            return;
        }
        $this->cause(11, null);
    }

    /** MODREL: another action than SAY / GIVE while someone is there: he gets angry */
    public function modrel(): void
    {
        if ($this->tlpos[0] === 5 || $this->tlpos[0] === 6 || !$this->fdialo) {
            return;
        }
        $tfr = ($this->enerv + 100) & 0xFFFF;
        if ($this->enerv < $tfr) {
            $this->enerv = $tfr;
        }
        if ($this->enerv < 51 * 256) {
            if ($this->fpress) {
                $this->cause(7, null);
            } else {
                $this->cause(2, null);
            }
            return;
        }
        if ($this->enerv < 102 * 256) {
            $this->cause(8, null);
        }
        if ($this->Rnd() > intdiv($this->enerv, 512)) {
            if ($this->enerv < 153 * 256) {
                $this->cause(9, null);
            } elseif ($this->enerv < 204 * 256) {
                $this->cause(10, null);
            } else {
                $this->cause(11, null);
            }
        }
    }

    /** PRENDS: he takes the object rega of your list */
    public function prends(): void
    {
        $erreur = $this->enlevl($this->liste1, $this->list10, $this->rega);
        if ($erreur === 1 && $this->lpos > 1) {
            $this->lpos--;
        }
        $this->inimen($this->s_menu);
    }

    /** TRDONN: give the object $rangb */
    public function trdonn(int $rangb): \Generator
    {
        $mescon = $this->GetMesCon();
        if (!$this->fdialo) {
            return;
        }
        $objet = $this->liste1->elements[$rangb];
        $this->flefba = 0;
        if ($this->chef && $this->tribu === 1) {
            // to your chief
            if ($this->scenar !== 1) {
                if ($objet !== $this->scenar + 40) {
                    $this->SceveuBulle($this->scenar - 1);
                    return;
                }
            } else {
                // meat
                if ($objet < 35 || $objet > 39) {
                    $this->SceveuBulle($this->scenar - 1);
                    return;
                }
                $this->scenpr = self::sc($this->scenpr - (40 - $objet));
                if ($this->scenpr > 0) {
                    $this->prends();
                    $this->list10->taille = 0;
                    $this->effaceBulles();
                    $this->cause(6, null);
                    return;
                }
            }
            // (IsEnreg() is always true: the game is free)
            $this->prends();
            $this->effaceBulles();
            switch ($this->scenar) {
                case 1:
                    $this->bulle((string)$mescon[82 - 1], 1);
                    break;
                case 2:
                    $this->bulle((string)$mescon[83 - 1], 1);
                    break;
                case 3:
                case 4:
                    $this->bulle((string)$mescon[84 - 1], 1);
                    break;
            }
            $this->scenar = ($this->scenar + 1) & 0xFF;
            if ($this->scenar === 5) {
                // the last fight
                $this->flcomb = 1;
                $this->SceveuBulle($this->scenar - 1);
                yield from $this->delai(5 * 60);
                return;
            }
            if ($this->scenar === 3) {
                // the Bras-de-Roc are very angry: you must steal the axe
                $this->enetoi[2] = 60000;
            }
            return;
        }
        // a gift out of the scenario
        $this->effaceBulles();
        $valeur = ($this->enerv - 512 * $this->intrsq($this->liste1->elements[$this->rega])) & 0xFFFF;
        if ($valeur > $this->enerv) {
            $valeur = 0;
        }
        if (!$this->fpress) {
            // not introduced: a present
            if ($this->list10->taille <= $this->list10->maxtaille) {
                $this->prends();
                if ($this->enerv < 102 * 256) {
                    $this->cause(1, null);
                    $this->cause(12, null);
                }
                if ($this->agress > 0) {
                    $this->agress--;
                }
                $this->enerv = $valeur;
                $this->excite(0);
            }
            return;
        }
        // exchange
        if ($this->enerv < 102 * 256) {
            $valeur = $this->intrsq($this->liste1->elements[$this->rega]);
            if ($this->chef && $this->scenar === $this->tribu) {
                $valeur = $this->liste1->elements[$this->rega] !== $this->predil ? 0 : 200;
            }
            if ($valeur > $this->vlchan) {
                $this->vlchan = $valeur & 0xFF;
                $this->cause(4, null);
                if ($this->echan === 1) {
                    // protection
                    $this->enetoi[$this->tribu - 1] = 0;
                    $this->echan = 25;
                } else {
                    $tfr = $this->liste1->elements[$rangb];
                    $this->liste1->elements[$rangb] = $this->echan;
                    $this->echan = $tfr;
                    $this->inimen($this->s_menu);
                }
            } else {
                $this->cause(3, null);
                $this->excite(50);
            }
        } else {
            if ($this->list10->taille < $this->list10->maxtaille) {
                $this->prends();
                $this->enerv = $valeur;
                $this->cause(5, null);
            }
        }
    }

    /** IDIALO: the opponent, from the presence */
    public function idialo(): void
    {
        $this->fdialo = $this->flcomb = $this->fpress = $this->flgcharism = $this->dquest = 0;
        if ($this->presence === 0) {
            return;
        }
        if ($this->presence > 4) {
            // an animal
            $this->echan = 0;
            $this->flcomb++;
            return;
        }
        $this->fdialo++;
        $t = $this->tribu - 1;
        $this->enerv = $this->tenerv[$t];
        $this->agress = $this->tagress[$t];
        $this->predil = $this->tabprf[$t];
        $this->echan = $this->tobje[$t][$this->Rnd() & 3];
        for ($i = 0; $i < 2; $i++) {
            $this->list10->elements[$i] = $this->tobje[$t][$this->Rnd() & 3];
        }
        $this->list10->taille = 2;
        $tribuCar = chr(($this->tribu + 0x85) & 0xFF);
        if ($this->chef) {
            $this->nomtribu = chr(0x85) . $tribuCar;
            $this->nomadversaire = chr(($this->tribu + 0x89) & 0xFF);
            if ($this->tribu === 1) {
                $this->fpress = 1;
                $this->enerv = $this->enetoi[$t];
            }
            if ($this->scenar === $this->tribu && $this->tribu !== 4) {
                $this->echan = $this->tribu + 40;
            } else {
                $this->echan = 1;
            }
        } else {
            $this->nomtribu = ' ' . $tribuCar;
            $this->nomadversaire = $this->relNom();
        }
        $this->nomsecondaire = $this->relNom();
        $this->vlchan = $this->intrsq($this->echan);
        $this->smenu[1] = 111;
        $this->inimen(0);
    }

    /** A name of 3 syllables of 3 letters (maced) */
    private function relNom(): string
    {
        $nom = '';
        for ($i = 0; $i < 6;) {
            $pos = intdiv(48 * $this->Rnd(), 256);
            $nom .= substr($this->maced, $pos, 3);
            $i += 3;
        }
        return $nom;
    }

    /** QUITTE: he is frightened: he runs away */
    public function quitte(): \Generator
    {
        $this->tfrene();
        $this->flcomb = 0;
        $this->presence = 0;
        $this->idialo();
        $this->present[1] = 0;
        $this->present[3] = 0;
        $this->prm($this->sap2, 0);
        $this->prm($this->sap4, 0);
        $this->dsposx = $this->sposx;
        $this->dsposy = $this->sposy;
        $this->reset_mouse();
        if ($this->dervue === self::VUE_LATERALE) {
            yield from $this->laterb(false);
        } else {
            yield from $this->latera();
        }
        $this->generique(true);
        $this->inimen(0);
    }

    /** CREECOP: makes a friend (sap2 becomes sap3) */
    public function creecop(int $qui): \Generator
    {
        $this->sap3->cross = $this->sap2->cross;
        if ($this->presence < 5) {
            $this->nomcopain = $this->nomadversaire;
        } else {
            // your friend
            $this->nomcopain = chr(128 + 45);
        }
        $this->prm($this->sap3, $qui);
        $this->sap3->colonne = $this->sap2->colonne;
        $this->sap3->sens = $this->sap2->sens;
        $this->sap3->coeur = $this->sap2->coeur;
        $this->sap3->cptrsouffle = 1;
        $this->prm($this->sap2, 0);
        if ($this->dervue !== self::VUE_PAYSAGE) {
            $this->presence = 0;
            yield from $this->trobsv(1);
        }
        $this->echan = 0;
        $this->peuple[2]->possession->taille = 0;
        $this->generique(true);
    }

    /** BONNECHASS: "good hunting" */
    public function bonnechass(): \Generator
    {
        $mescon = $this->GetMesCon();
        if ($this->enerv > 102 * 256) {
            $this->excite(300);
        } else {
            if (!$this->fpress) {
                $this->cause(2, null);
            } else {
                $this->bulle((string)$mescon[73 - 1], 1);
                yield from $this->quitte();
                $this->inimen(0);
                $this->effaceBulles();
            }
        }
    }

    /** VIENSAVEC: "come with me" */
    public function viensavec(): \Generator
    {
        $mescon = $this->GetMesCon();
        if ($this->fpress && $this->presence && $this->presence < 5
            && $this->present[2] === 0 && $this->present[3] === 0 && !$this->chef && !$this->flgcharism) {
            if (($this->charisme * $this->Rnd() > $this->enerv) && $this->Rnd() < 70) {
                yield from $this->creecop(1);
                $this->viecopain = 3;
                $this->sap3->vie = 200;
                $this->bulle((string)$mescon[100 - 1], 1);
                $this->flagbadi = 0;
            } else {
                $this->excite(1500);
            }
        } else {
            $this->bulle((string)$mescon[99 - 1], 1);
            $this->excite(2000);
        }
        $this->flgcharism = 1;
    }

    /** TUVEUQUOI: "what do you want?" */
    public function tuveuquoi(): void
    {
        $refer1 = $this->refer[self::REFER1];
        $mescon = $this->GetMesCon();
        $scenme = $this->GetScenme();
        if ($this->enerv > 102 * 256) {
            $this->cause(13, null);
            return;
        }
        if ($this->chef && $this->tribu === 1) {
            $this->dquest = 0;
            $this->bulle((string)($scenme[$this->scenar - 1] ?? ''), 1);
            return;
        }
        if ($this->tribu === $this->scenar && $this->chef) {
            $this->cause(14, $refer1[$this->predil - 1][0] ?? '');
            return;
        }
        $valeur = $this->intrsq($this->rapias());
        if ($valeur > $this->vlchan) {
            $this->cause(14, $refer1[$this->rapias() - 1][0] ?? '');
        } elseif ($this->chef) {
            $this->cause(14, $refer1[$this->predil - 1][0] ?? '');
        } else {
            $this->bulle((string)$mescon[66 - 1], 1);
            $this->excite(0);
        }
    }

    /** TRDIRE: say the sentence $diquoi of the list of sentences */
    public function trdire(int $diquoi): \Generator
    {
        $refer1 = $this->refer[self::REFER1];
        $mescon = $this->GetMesCon();
        if (!$this->fdialo) {
            return;
        }
        $this->flefba = 1;
        if ($diquoi === $this->dquest) {
            $this->excite(1000);
            return;
        }
        $this->dquest = $diquoi;
        if ($diquoi === 5) {
            // I am ...
            if (!$this->fpress) {
                $this->fpress++;
                $tfr = self::ss($this->enerv - $this->tenerv[$this->tribu - 1]);
                $this->enerv = $this->enetoi[$this->tribu - 1];
                $enerv2 = ($this->enerv + $tfr) & 0xFFFF;
                if ($tfr > 0 && $enerv2 < $this->enerv) {
                    $enerv2 = 65535;
                }
                if ($tfr < 0 && $enerv2 > $this->enerv) {
                    $enerv2 = 0;
                }
                $this->enerv = $enerv2;
                if ($this->enerv > 0x3400) {
                    // so it is you
                    $this->bulle((string)$mescon[72 - 1], 1);
                    $this->excite(0);
                } else {
                    $this->bulle((string)$mescon[71 - 1], 1);
                }
            } else {
                $this->excite(600);
            }
            return;
        }
        if ($diquoi === 6) {
            yield from $this->bonnechass();
            return;
        }
        if ($diquoi === 7 || $diquoi === 8) {
            yield from $this->viensavec();
            return;
        }
        if ($this->stock1 === 0) {
            if ($diquoi === 1) {
                // who are you?
                if ($this->enerv > 102 * 256) {
                    $this->excite(100);
                } else {
                    $this->cause(12, $this->GetStrBkMot(129 + 3));
                    if (!$this->fpress && !$this->chef) {
                        $this->bulle((string)$mescon[65 - 1], 1);
                        $this->excite(200);
                    }
                }
                return;
            }
            if ($diquoi === 2) {
                $this->tuveuquoi();
                return;
            }
            if ($diquoi === 3) {
                // what do you offer?
                if ($this->enerv > 256 * 102) {
                    $this->bulle((string)$mescon[66 - 1], 1);
                    $tfr = self::ss($this->enerv + 200);
                    if ($this->enerv < $tfr) {
                        $this->enerv = $tfr & 0xFFFF;
                    }
                } else {
                    if (!$this->fpress) {
                        $this->cause(2, null);
                    } else {
                        $this->cause(15, $refer1[$this->echan - 1][0] ?? '');
                    }
                }
                return;
            }
            // where is your chief?
            if ($this->chef) {
                $this->excite(32700);
            } else {
                if ($this->enerv > 51 * 256) {
                    $this->cause(16, null);
                    $tfr = self::ss($this->enerv + 256);
                    if ($this->enerv < $tfr) {
                        $this->enerv = $tfr & 0xFFFF;
                    }
                } else {
                    if (!$this->fpress) {
                        $this->cause(2, null);
                    } else {
                        $p = $this->poschef;
                        $p[0] = ' ';
                        $p[2] = ' ';
                        $d = $this->destine[$this->tribu - 1];
                        if (intdiv($this->xj, 256) < $d[1]) {
                            $p[0] = chr(128 + 31);
                        }
                        if (intdiv($this->xj, 256) > $d[1]) {
                            $p[0] = chr(128 + 32);
                        }
                        if (intdiv($this->yj, 256) > $d[2]) {
                            $p[2] = chr(128 + 33);
                        }
                        if (intdiv($this->yj, 256) < $d[2]) {
                            $p[2] = chr(128 + 34);
                        }
                        $this->poschef = $p;
                        $this->bulle($p, 1);
                    }
                }
            }
        } else {
            // flattery or insult
            switch ($this->stock1) {
                case 1:
                    // insult
                    $this->excite(200 * $this->stock2);
                    break;
                case 2:
                    // flattery
                    if ($this->agress < 200) {
                        $this->agress++;
                        $tfr = self::ss($this->enerv - $this->stock2 * 100);
                        if ($tfr < $this->enerv) {
                            $this->enerv = $tfr & 0xFFFF;
                        }
                        $this->excite(0);
                        break;
                    }
                    // no break (as in the C code)
                default:
                    // boasting
                    if ($this->agress < 200 && !$this->chef) {
                        $this->agress++;
                        if ($this->Rnd() > intdiv($this->enerv, 200)) {
                            yield from $this->fuite();
                        } else {
                            $tfr = self::ss($this->enerv - $this->stock2 * 100);
                            if ($tfr < $this->enerv) {
                                $this->enerv = $tfr & 0xFFFF;
                            }
                            $this->excite(0);
                        }
                    } else {
                        // he takes it as an insult
                        $this->excite(200 * $this->stock2);
                    }
                    break;
            }
        }
    }

    /** VOLE: you lost: he takes everything */
    public function vole(): void
    {
        $this->liste4->taille = 0;
        $i = $this->liste1->taille;
        while ($i) {
            $i--;
            if ($this->intrsq($this->liste1->elements[$i]) > 5 && $this->liste1->elements[$i] < 40 && $this->Rnd() > 128) {
                $this->enlevl($this->liste1, $this->listbidon, $i);
            }
        }
        $this->tlpos[1] = 1;
        $this->tlpos[4] = 1;
    }

    /** OUTARM: the weapons after a fight */
    public function outarm(): void
    {
        $this->tlpos[1] = 1;
        $this->tlpos[4] = 1;
        $i = $this->liste1->taille;
        // the spears thrown are lost
        while ($i) {
            $i--;
            if ($this->liste1->elements[$i] === 23) {
                if ($this->sap1->nbresag) {
                    $this->sap1->nbresag--;
                } else {
                    $this->enlevl($this->liste1, $this->listbidon, $i);
                }
            }
        }
        if ($this->sap1->forcehache === 0) {
            // no more axe
            $i = $this->liste1->taille;
            while ($i) {
                $i--;
                if ($this->liste1->elements[$i] === 21 || $this->liste1->elements[$i] === 22) {
                    $this->enlevl($this->liste1, $this->listbidon, $i);
                    $i = 0;
                }
            }
        }
        if ($this->sap1->vie !== $this->entvie) {
            $tfr = ($this->fatig - intdiv($this->entvie - $this->sap1->vie, 2)) & 0xFF;
            if ($tfr < $this->fatig) {
                $this->fatig = $tfr;
            } else {
                $this->fatig = 0;
            }
        }
    }

    /** TRDORS: sleep */
    public function trdors(): \Generator
    {
        if ($this->fatig > 192) {
            $this->flefba = 0;
            $this->comment(108);
            return;
        }
        $this->liste4->taille = 0;
        $this->tlpos[4] = 1;
        $tfr = ($this->fatig + $this->stock2) & 0xFF;
        if ($tfr < $this->fatig) {
            $tfr = 255;
        }
        $this->fatig = 255;
        $this->dervue = self::VUE_PAYSAGE;
        $this->stock2 = intdiv($this->stock2, 7);
        while ($this->stock2) {
            $this->GereInterface();
            for ($i = 0; $i < 9; $i++) {
                $this->minute = 0;
                $this->chrono(1);
            }
            $t = $this->TickCount();
            while ($this->TickCount() < $t + 10) {
                yield;
            }
            $this->affich($this->dzeta);
            $this->tfrima();
            $this->minute = 0;
            $this->chrono(2);
            $this->stock2--;
        }
        $this->fatig = $tfr;
        $this->chrono(0);
        $this->afstat();
        $this->inimen(0);
    }

    private function InitVueLaterale(): \Generator
    {
        if ($this->dervue !== self::VUE_LATERALE) {
            $this->ldzeta = $this->dzeta;
        }
        $this->prm($this->sap2, $this->present[1]);
        $this->prm($this->sap4, $this->present[3]);
        $this->flcomb = 1;
        if ($this->presence) {
            if ($this->presence < 5) {
                $this->flcomb = 0;
            }
            // bit 7: friendly animal
            $this->presence &= 127;
            $this->prm($this->sap1, $this->present[0]);
            $this->prm($this->sap3, $this->present[2]);
            if ($this->dervue !== self::VUE_LATERALE) {
                yield from $this->latera();
            }
            $this->dervue = self::VUE_LATERALE;
        }
        $this->pos0_mouse();
    }

    /** TROBSV: observe: 0 landscape (3D), 1 side view, 2 panorama, 3 map */
    public function trobsv(int $regb): \Generator
    {
        if ($this->presence) {
            return;
        }
        if ($regb === 0) {
            $this->LastObsv = 0;
            $this->dismin = 0;
            $this->lastmen = 255;
            $this->clrbadi();
            $this->dervue = self::VUE_PAYSAGE;
            $this->MenuFenetres(0);
            $this->AffichePictAide(1);
            yield from $this->clav3d();
            $this->MenuFenetres(1);
            $this->pos0_mouse();
            if ($this->presence) {
                $regb = 1;
            }
        }
        if ($regb === 1) {
            $this->LastObsv = 1;
            yield from $this->InitVueLaterale();
            return;
        }
        if ($regb === 2) {
            // panorama
            while ($this->getkey(0)) {
                yield;
            }
            $this->dervue = self::VUE_PAYSAGE;
            $dzetdep = $this->dzeta;
            do {
                $posTimer = $this->sapiensTimer;
                $this->affich($this->dzeta);
                $this->tfrima();
                $this->dzeta = ($this->dzeta + 16) & 0x3FF;
                do {
                    yield;
                } while ($posTimer > $this->sapiensTimer - 8);
            } while ($dzetdep !== $this->dzeta && !$this->getkey(0));
        }
        if ($regb === 3) {
            $this->dervue = self::VUE_PAYSAGE;
            yield from $this->zoocar();
            $this->lastmen = 255;
        }
        $this->inimen(0);
        $this->pos0_mouse();
        $this->efbadass();
        $this->TfrBadAss();
    }

    /** ZONEVT: an event from the position (0: none, else the presence 1..8) */
    public function zonevt(int $okmess): int
    {
        for ($i = 0; $i < self::NBREVTDESTINE; $i++) {
            $dieu = $this->destine[$i];
            $dist = $this->distance($dieu[1] * 256 - $this->xj, $dieu[2] * 256 - $this->yj);
            $dist = intdiv($dist, 256);
            if ($dist > $dieu[3]) {
                // out of the zone: it is valid again
                $this->destine[$i][0] &= 127;
            } else {
                if (!($dieu[0] & 128) && ($dist * 12 + 10) < $this->Rnd()) {
                    if ($this->Rnd() >= $dieu[4]) {
                        if ($this->Rnd() > 240) {
                            if ($okmess) {
                                $this->efbadi();
                                $this->comment($dieu[6]);
                                return 0;
                            }
                        }
                    } else {
                        if ($dieu[5] === 0) {
                            // water
                            $this->source = ($this->source + 1) & 0xFF;
                            $this->liste4->taille = $this->tlpos[4] = 1;
                            $this->liste4->elements[0] = 2;
                            $this->efbadi();
                            $this->comment(76);
                            return 0;
                        }
                        $this->chef = $dieu[5] > 127 ? 1 : 0;
                        $qui = $dieu[5] & 127;
                        // one shot
                        if ($dieu[0]) {
                            $this->destine[$i][0] |= 128;
                        }
                        $nuit = $this->heure > 512;
                        if ($this->chef
                            || ($nuit && $qui !== 5 && $this->Rnd() > 222)
                            || (!$nuit && $qui === 5 && $this->Rnd() > 222)
                            || ($nuit && $qui === 5)
                            || (!$nuit && $qui !== 5)) {
                            return $qui;
                        }
                    }
                }
            }
        }
        return 0;
    }

    /** ZONINF: the events of the position (sets the presences) */
    public function zoninf(): \Generator
    {
        if ($this->presence) {
            return;
        }
        if ($this->derxj === ($this->xj & 0xFFE0) && $this->deryj === ($this->yj & 0xFFE0)) {
            return;
        }
        $this->derxj = $this->xj & 0xFFE0;
        $this->deryj = $this->yj & 0xFFE0;
        $this->chef = 0;
        $this->flcomb = 0;
        $this->source = 0;
        $this->present[1] = $this->zonevt(1);
        if ($this->present[1]) {
            // a chief comes with another one
            if (!$this->chef) {
                $this->present[3] = $this->zonevt(0);
            } else {
                $this->present[3] = $this->present[1];
            }
            // a man first
            if ($this->present[1] > 4 && $this->present[3] < 5 && $this->present[3] !== 0) {
                $tfr = $this->present[1];
                $this->present[1] = $this->present[3];
                $this->present[3] = $tfr;
            }
            $this->presence = $this->present[1];
            $this->tribu = $this->presence;
            if ($this->dervue !== self::VUE_LATERALE) {
                $this->sap1->colonne = 3;
            }
            $this->prm($this->sap2, $this->present[1]);
            $this->prm($this->sap4, $this->present[3]);
            $this->idialo();
            // the charisma on an animal
            if ($this->presence > 4 && $this->charisme > $this->Rnd() && $this->Rnd() < 40 && !$this->present[2]) {
                $this->flefba = 0;
                $this->comment(97 + $this->presence);
                yield from $this->creecop($this->presence);
                $this->viecopain = 1;
                $this->flefba = 0;
                if ($this->dervue === self::VUE_LATERALE) {
                    $this->flagbadi = 0;
                }
                $this->presence = 128;
                return;
            }
        }
        if (!$this->source) {
            if ($this->scenar === 4 && !$this->scenpr) {
                // the sacred stick of the Hyenes-Folles, under the three totems
                if (abs($this->derxj - 0xABE0) < 500 && abs($this->deryj - 0xB2E0) < 500) {
                    $this->liste4->taille = $this->tlpos[4] = 1;
                    $this->liste4->elements[0] = 44;
                    return;
                }
            }
            $this->liste4->taille = $this->Rnd() & 7;
            $i = $this->liste4->taille;
            $this->tlpos[4] = 1;
            while ($i) {
                $this->liste4->elements[--$i] = 3 + intdiv(17 * $this->Rnd(), 256);
            }
        }
    }
}
