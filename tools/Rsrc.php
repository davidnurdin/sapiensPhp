<?php

/**
 * Reads the classic Mac resource fork of the original game (stored in an AppleDouble file).
 * Used only by the offline conversion tools (tools/convert-rsrc.php).
 */
final class Rsrc
{
    /** @var array<string, array<int, string>> type => id => data */
    public array $res = [];

    public function __construct(string $file)
    {
        $b = file_get_contents($file);
        if (substr($b, 0, 4) === "\x00\x05\x16\x07") {
            // AppleDouble: find entry 2 (resource fork)
            $n = unpack('n', $b, 24)[1];
            for ($i = 0; $i < $n; $i++) {
                [, $id, $off, $len] = unpack('N3', $b, 26 + $i * 12);
                if ($id === 2) {
                    $b = substr($b, $off, $len);
                    break;
                }
            }
        }
        [, $dataOff, $mapOff] = unpack('N2', $b, 0);
        $typeList = $mapOff + unpack('n', $b, $mapOff + 24)[1];
        $nTypes = unpack('n', $b, $typeList)[1] + 1;
        for ($t = 0; $t < $nTypes; $t++) {
            $p = $typeList + 2 + $t * 8;
            $type = substr($b, $p, 4);
            [, $count, $refOff] = unpack('n2', $b, $p + 4);
            for ($r = 0; $r <= $count; $r++) {
                $q = $typeList + $refOff + $r * 12;
                $id = unpack('n', $b, $q)[1];
                if ($id >= 0x8000) {
                    $id -= 0x10000;
                }
                $off = unpack('N', $b, $q + 4)[1] & 0xFFFFFF;
                $len = unpack('N', $b, $dataOff + $off)[1];
                $this->res[$type][$id] = substr($b, $dataOff + $off + 4, $len);
            }
        }
    }

    /** @return string[] the strings of a STR# resource */
    public function strList(int $id): array
    {
        $d = $this->res['STR#'][$id] ?? '';
        if ($d === '') {
            return [];
        }
        $n = unpack('n', $d)[1];
        $p = 2;
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $len = ord($d[$p]);
            $out[] = substr($d, $p + 1, $len);
            $p += 1 + $len;
        }
        return $out;
    }
}
