<?php

namespace App\Sapiens;

/**
 * SAPWMUS.C: sound effects (the 'snd ' resources 1000-1012 -> assets/sfx/bruit<n>.wav) and music.
 *
 * The music: the score of 1987 (MUSE.C) rendered by tools/render-music.php (assets/music/sapiens1.ogg
 * and sapiens2.ogg). Like Kmuse, when a music ends the other one starts (quelle_musique ^ 3).
 */
trait MusicTrait
{
    public int $quelle_musique = 0;
    public bool $MusiqueEnCours = false;
    /** @var array<string, bool> the musics loaded by the scene */
    public array $musics = [];

    /** bruit: plays the sound effect n */
    public function bruit(int $num): void
    {
        if (!$this->IsBruitages || $this->sound === null) {
            return;
        }
        $name = 'bruit' . $num;
        if (method_exists($this->sound, 'hasSfx') && $this->sound->hasSfx($name)) {
            $this->sound->playSfx($name);
        }
    }

    /** MusicInit: starts the music 1 or 2 (played once: CheckChangeMusique chains the other one) */
    public function MusicInit(int $quelle): void
    {
        $this->quelle_musique = $quelle;
        if ($this->sound === null) {
            return;
        }
        if (!$this->okmuse) {
            if ($this->MusiqueEnCours) {
                $this->sound->stopMusic();
            }
            $this->MusiqueEnCours = false;
            return;
        }
        $name = 'sapiens' . $quelle;
        if (isset($this->musics[$name])) {
            $this->sound->playMusic($name, false);
            $this->MusiqueEnCours = $this->sound->getCurrentMusic() === $name;
        }
    }

    /** CheckChangeMusique: the end of a music starts the other one (Kmuse) */
    public function CheckChangeMusique(): void
    {
        if (!$this->MusiqueEnCours || $this->sound === null || !function_exists('Mix_PlayingMusic')) {
            return;
        }
        if (!\Mix_PlayingMusic()) {
            $this->MusicInit($this->quelle_musique ^ 3);
        }
    }

    /** attends_bruit, quel_synthe, reset_synthe: empty in the original too */
    public function attends_bruit(): void
    {
    }

    public function quel_synthe(int $n): void
    {
    }

    public function reset_synthe(): void
    {
    }
}
