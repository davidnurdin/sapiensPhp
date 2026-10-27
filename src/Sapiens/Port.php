<?php

namespace App\Sapiens;

/**
 * An 8-bit offscreen of the original game (CGrafPtr of AcamCreatePortOffscreen): one byte per pixel,
 * indexes in the Mac system palette (Mac::CLUT). Rows are separate strings so that a line or a
 * rectangle is changed without copying the whole buffer.
 */
final class Port
{
    /** @var string[] */
    public array $rows = [];
    /** Something was drawn since the last presentation (the window) */
    public bool $dirty = true;

    public function __construct(public int $w, public int $h, int $color = 0)
    {
        $this->rows = array_fill(0, $h, str_repeat(chr($color), $w));
    }

    /** Fills [l, t, r, b[ (clipped) with a palette index. */
    public function fill(int $l, int $t, int $r, int $b, int $color): void
    {
        $l = max(0, $l);
        $t = max(0, $t);
        $r = min($this->w, $r);
        $b = min($this->h, $b);
        if ($r <= $l || $b <= $t) {
            return;
        }
        $line = str_repeat(chr($color), $r - $l);
        for ($y = $t; $y < $b; $y++) {
            $this->rows[$y] = substr_replace($this->rows[$y], $line, $l, $r - $l);
        }
        $this->dirty = true;
    }

    public function get(int $x, int $y): int
    {
        if ($x < 0 || $y < 0 || $x >= $this->w || $y >= $this->h) {
            return 0;
        }
        return ord($this->rows[$y][$x]);
    }

    public function plot(int $x, int $y, int $color): void
    {
        if ($x < 0 || $y < 0 || $x >= $this->w || $y >= $this->h) {
            return;
        }
        $this->rows[$y][$x] = chr($color);
        $this->dirty = true;
    }

    /** CopyOffscreen: rectangle [l, t, r, b[ of $src to ($dx, $dy) of this port, clipped on both. */
    public function copyFrom(Port $src, int $l, int $t, int $r, int $b, int $dx, int $dy): void
    {
        // clip to the source
        if ($l < 0) {
            $dx -= $l;
            $l = 0;
        }
        if ($t < 0) {
            $dy -= $t;
            $t = 0;
        }
        $r = min($r, $src->w);
        $b = min($b, $src->h);
        // clip to the destination
        if ($dx < 0) {
            $l -= $dx;
            $dx = 0;
        }
        if ($dy < 0) {
            $t -= $dy;
            $dy = 0;
        }
        $r = min($r, $l + $this->w - $dx);
        $b = min($b, $t + $this->h - $dy);
        $w = $r - $l;
        if ($w <= 0 || $b <= $t) {
            return;
        }
        $full = $l === 0 && $w === $src->w && $dx === 0 && $w === $this->w;
        for ($y = $t; $y < $b; $y++) {
            $this->rows[$dy + $y - $t] = $full ? $src->rows[$y]
                : substr_replace($this->rows[$dy + $y - $t], substr($src->rows[$y], $l, $w), $dx, $w);
        }
        $this->dirty = true;
    }

    /** One row through a byte map (strtr from Mac::IDENT): SwapCouleur, shadows... */
    public function mapRect(int $l, int $t, int $r, int $b, string $map): void
    {
        $l = max(0, $l);
        $t = max(0, $t);
        $r = min($this->w, $r);
        $b = min($this->h, $b);
        if ($r <= $l || $b <= $t) {
            return;
        }
        for ($y = $t; $y < $b; $y++) {
            $this->rows[$y] = substr_replace($this->rows[$y], strtr(substr($this->rows[$y], $l, $r - $l), Mac::IDENT, $map), $l, $r - $l);
        }
        $this->dirty = true;
    }

    public function pixels(): string
    {
        return implode('', $this->rows);
    }
}
