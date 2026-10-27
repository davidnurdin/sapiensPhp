<?php

namespace App\Sapiens;

/**
 * struct sap: a fighter of the side view (sap1 = the player, sap2 / sap4 = the opponents, sap3 = the
 * friend). hauteur: 0 standing, 1 crouched, 2 kneeling, 3 lying; sens: 0 <=, 1 =>;
 * vitesse 0 walk, 2 stop. Unsigned char fields are kept in 0..255 by the code that changes them.
 */
final class Sap
{
    public int $colonne = 2;
    public int $ligne = 32;
    public int $sens = 0;
    public int $vitesse = 2;
    public int $objet = 0;
    public int $hauteur = 0;
    public int $action = 0;
    public int $vie = 240;
    public int $force = 0;
    public int $agressivite = 128;
    public int $souffle = 255;
    public int $deltah = 0;
    public int $blockimpact = 0;
    public int $ligneimpact = 0;
    public int $colimpact = 0;
    public int $forcehache = 10;
    public int $coeur = 71;
    public int $cptrsouffle = 1;
    public int $nbresag = 5;
    public int $animal = 37;
    public int $linsag = 0;
    public int $colsag = 0;
    public int $snssag = 255;
    public int $fuite = 0;
    public int $flg_mort = 0;
    public int $poslcoeur = 8;
    public int $posccoeur = 5;
    /** @var int[] 15 animation pointers (offsets in Anim::ANIM, 0 = NULL) */
    public array $sapanim = [];
    /** @var int[] 8 bytes: variants of the body parts (body, head, weapon...) */
    public array $cross = [0, 0, 0, 0, 0, 0, 0, 0];
    public Sap $adversaire;
    public Liste $possession;

    public function __construct(int $ligne, int $sens, int $poslcoeur, int $posccoeur, Liste $possession, ?Sap $adversaire = null)
    {
        $this->ligne = $ligne;
        $this->sens = $sens;
        $this->poslcoeur = $poslcoeur;
        $this->posccoeur = $posccoeur;
        $this->possession = $possession;
        $this->adversaire = $adversaire ?? $this;
        $this->sapanim = array_fill(0, 14, Anim::O_ANEUTR);
        $this->sapanim[14] = 0;
    }
}
