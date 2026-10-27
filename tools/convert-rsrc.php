<?php

/**
 * Offline conversion of the resource fork of the original game (GPL v3, Myriad 2018):
 *   PICT 128 (window background tile) and 134 (logo) -> assets/data/pict<id>.bmp (8-bit)
 *   snd 1000-1012 (sound effects)                     -> assets/sfx/bruit<n>.wav
 *   STR# (misc strings, per language)                 -> assets/data/strings.json
 *
 * Usage: bin/jumphp php games/sapiens tools/convert-rsrc.php [../../.original/__MACOSX/Rsrc/._sapiens.rsrc]
 */

require __DIR__ . '/Rsrc.php';
require __DIR__ . '/../src/Sapiens/Bmp.php';

$file = $argv[1] ?? __DIR__ . '/../../../.original/__MACOSX/Rsrc/._sapiens.rsrc';
$r = new Rsrc($file);
$out = __DIR__ . '/../assets';

foreach ([128, 134] as $id) {
    [$w, $h, $pal, $pix] = pict($r->res['PICT'][$id]);
    // the picture frame can be narrower than the pixmap
    [, $ft, $fl, $fb, $fr] = unpack('n4', $r->res['PICT'][$id], 2);
    if ($fr - $fl < $w) {
        $rows = str_split($pix, $w);
        $w = $fr - $fl;
        $pix = implode('', array_map(fn ($row) => substr($row, 0, $w), $rows));
    }
    file_put_contents("$out/data/pict$id.bmp", App\Sapiens\Bmp::encode($w, $h, $pix, $pal));
    echo "PICT $id: {$w}x{$h}\n";
}

@mkdir("$out/sfx", 0777, true);
foreach ($r->res['snd '] as $id => $d) {
    [$rate, $samples] = snd($d);
    $n = $id - 1000;
    file_put_contents("$out/sfx/bruit$n.wav", wav($rate, $samples));
    echo "snd $id: $rate Hz, " . strlen($samples) . " samples\n";
}

$strings = [];
foreach ([0, 1000, 2000] as $lang => $base) {
    $strings[$lang]['vrac'] = array_map(fn ($s) => iconv('MACINTOSH', 'UTF-8', $s), $r->strList($base));
    $strings[$lang]['splash'] = array_map(fn ($s) => iconv('MACINTOSH', 'UTF-8', $s), $r->strList($base + 1));
}
$strings['languages'] = array_map(fn ($s) => iconv('MACINTOSH', 'UTF-8', $s), $r->strList(128));
file_put_contents("$out/data/strings.json", json_encode($strings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

/** @return array{0:int,1:int,2:array,3:string} PICT v2 with one PackBitsRect (indexed pixmap) */
function pict(string $d): array
{
    $p = 10;
    while ($p < strlen($d)) {
        if ($p & 1) {
            $p++;
        }
        $op = unpack('n', $d, $p)[1];
        $p += 2;
        switch ($op) {
            case 0x0000: case 0x001E:
                break;
            case 0x0011:
                $p += 2;
                break;
            case 0x0C00:
                $p += 24;
                break;
            case 0x0001:
                $p += unpack('n', $d, $p)[1];
                break;
            case 0x00A0:
                $p += 2;
                break;
            case 0x00A1:
                $p += 2;
                $p += 2 + unpack('n', $d, $p)[1];
                break;
            case 0x0098:
                $rowBytes = unpack('n', $d, $p)[1] & 0x3FFF;
                [, $top, $left, $bottom, $right] = unpack('n4', $d, $p + 2);
                $p += 2 + 8 + 36;
                $p += 4 + 2;
                $n = unpack('n', $d, $p)[1] + 1;
                $p += 2;
                $pal = [];
                for ($i = 0; $i < $n; $i++) {
                    [, $v, $cr, $cg, $cb] = unpack('n4', $d, $p);
                    $pal[$i] = [$cr >> 8, $cg >> 8, $cb >> 8];
                    $p += 8;
                }
                $p += 18;
                $w = $right - $left;
                $h = $bottom - $top;
                $pix = '';
                for ($y = 0; $y < $h; $y++) {
                    if ($rowBytes < 8) {
                        $row = substr($d, $p, $rowBytes);
                        $p += $rowBytes;
                    } else {
                        $len = $rowBytes > 250 ? unpack('n', $d, $p)[1] : ord($d[$p]);
                        $p += $rowBytes > 250 ? 2 : 1;
                        $row = unpackBits(substr($d, $p, $len));
                        $p += $len;
                    }
                    $pix .= str_pad(substr($row, 0, $w), $w, "\0");
                }
                return [$w, $h, $pal, $pix];
            default:
                throw new RuntimeException(sprintf('PICT opcode %04x not handled', $op));
        }
    }
    throw new RuntimeException('PICT without pixmap');
}

function unpackBits(string $s): string
{
    $out = '';
    $i = 0;
    $n = strlen($s);
    while ($i < $n) {
        $c = ord($s[$i++]);
        if ($c < 128) {
            $out .= substr($s, $i, $c + 1);
            $i += $c + 1;
        } elseif ($c > 128) {
            $out .= str_repeat($s[$i++], 257 - $c);
        }
    }
    return $out;
}

/** @return array{0:int,1:string} format-1 'snd ' with one sampled sound (8-bit unsigned) */
function snd(string $d): array
{
    $format = unpack('n', $d)[1];
    $p = 2;
    if ($format === 1) {
        $nMod = unpack('n', $d, $p)[1];
        $p += 2 + $nMod * 6;
    } else {
        $p += 2;
    }
    $nCmd = unpack('n', $d, $p)[1];
    $p += 2;
    $offset = 0;
    for ($i = 0; $i < $nCmd; $i++) {
        $cmd = unpack('n', $d, $p)[1];
        $param2 = unpack('N', $d, $p + 4)[1];
        if (($cmd & 0x7FFF) === 80 || ($cmd & 0x7FFF) === 81) {
            $offset = $param2;
        }
        $p += 8;
    }
    $len = unpack('N', $d, $offset + 4)[1];
    $rate = (int)round(unpack('N', $d, $offset + 8)[1] / 65536);
    return [$rate, substr($d, $offset + 22, $len)];
}

function wav(int $rate, string $samples): string
{
    $n = strlen($samples);
    return 'RIFF' . pack('V', 36 + $n) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, $rate, $rate, 1, 8)
        . 'data' . pack('V', $n) . $samples;
}
