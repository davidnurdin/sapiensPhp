<?php

namespace App\Sapiens;

/** A sprite of a .BRT file (struct Mob): palette indexes, one byte per pixel. */
final class Mob
{
    /** @var string[] rows through the palette of the mob (transparent pixels = 0) */
    public array $rows = [];
    /** @var string[] per row: "\xFF" where transparent, "\0" where drawn */
    public array $masks = [];
    /** @var string[] raw rows (indexes of the mob palette) */
    public array $raw = [];

    public function __construct(
        public int $nbcol,
        public int $nbligne,
        public int $indexPalette,
        public int $vide,
        public string $datas,
    ) {
    }

    /** Builds the rows for the default palette map of the mob. */
    public function prepare(string $map): void
    {
        $this->raw = $this->nbcol > 0 ? str_split($this->datas, $this->nbcol) : [];
        $this->raw = array_slice($this->raw, 0, $this->nbligne);
        $maskMap = str_repeat("\0", 256);
        $maskMap[$this->vide] = "\xFF";
        $map[$this->vide] = "\0";
        $this->rows = [];
        $this->masks = [];
        foreach ($this->raw as $row) {
            $this->rows[] = strtr($row, Mac::IDENT, $map);
            $this->masks[] = strtr($row, Mac::IDENT, $maskMap);
        }
    }

    /** One row through another palette map (transparent pixels = 0). */
    public function rowWith(int $y, string $map): string
    {
        $map[$this->vide] = "\0";
        return strtr($this->raw[$y], Mac::IDENT, $map);
    }
}
