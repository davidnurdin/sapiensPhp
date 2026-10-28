<?php

/**
 * Reads the music files of the 1996 version (data/musics/Sapiens1.mux, Sapiens2.mux: Harmony Assistant
 * songs, Myriad). Used only by the offline tool tools/render-music.php.
 *
 * Format (found by reading the files, no documentation is published):
 * - "EXPO" + 13 bytes, then LZW: codes MSB first, 9 to 12 bits (the width grows one code early),
 *   no clear code, the dictionary restarts when it holds 4095 entries;
 * - staves: Pascal name ("Portée n"), 0x1b bytes, u16 note count, notes of 28 bytes:
 *   0 pitch (MIDI, 0xff = rest), 7..10 u32 duration (quarter = 40320), 14 velocity;
 * - digital instruments ("DGIT"): u32 size, u16 type, u16 bits, Fixed rate, u16 base note,
 *   u16 volume, u32 loop start, loop end, length (in 16-bit samples at `rate`);
 *   type 3 = one signed byte per 2 samples, value = ±table[|byte|] (table: 128 u16 just before the data);
 *   type 0x4000 = raw PCM (8-bit unsigned or 16-bit big endian).
 */
final class Mux
{
    public const QUARTER = 40320;

    public string $data;

    public function __construct(string $file)
    {
        $this->data = self::unexpo(file_get_contents($file));
    }

    public static function unexpo(string $b): string
    {
        if (substr($b, 0, 4) !== 'EXPO') {
            throw new RuntimeException('not an EXPO file');
        }
        $pos = 17 * 8;
        $nbits = strlen($b) * 8;
        $dict = [];
        $reset = function () use (&$dict): void {
            $dict = [];
            for ($i = 0; $i < 256; $i++) {
                $dict[] = chr($i);
            }
        };
        $reset();
        $width = 9;
        $prev = null;
        $out = [];
        while ($pos + $width <= $nbits) {
            $code = 0;
            for ($i = 0; $i < $width; $i++, $pos++) {
                $code = ($code << 1) | ((ord($b[$pos >> 3]) >> (7 - ($pos & 7))) & 1);
            }
            $n = count($dict);
            if ($code < $n) {
                $e = $dict[$code];
            } elseif ($code === $n && $prev !== null) {
                $e = $prev . $prev[0];
            } else {
                throw new RuntimeException("bad LZW code $code");
            }
            $out[] = $e;
            if ($prev !== null) {
                $dict[] = $prev . $e[0];
            }
            $prev = $e;
            if (count($dict) + 1 >= (1 << $width) && $width < 12) {
                $width++;
            }
            if (count($dict) >= 4095) {
                $reset();
                $width = 9;
                $prev = null;
            }
        }
        return implode('', $out);
    }

    /** @return int beats per minute of the song header */
    public function tempo(): int
    {
        // header: ... u32 bar length (0x27600), u16 tempo, u16 beats, u16 beat unit
        $p = strpos($this->data, "\x00\x02\x76\x00", 0x80);
        return unpack('n', $this->data, $p + 4)[1];
    }

    /** @return array<int, array{name:string, notes:array<int, array{0:float,1:float,2:int,3:int}>}> notes: start, length (quarters), pitch, velocity */
    public function staves(): array
    {
        $d = $this->data;
        $staves = [];
        $i = 0;
        while (($i = strpos($d, "Port\x8ee ", $i + 1)) !== false) {
            $h = $i + ord($d[$i - 1]);
            $count = unpack('n', $d, $h + 0x1b)[1];
            $base = $h + 0x1d;
            if ($count === 0 || $base + 28 * $count > strlen($d)) {
                continue;
            }
            $t = 0;
            $notes = [];
            for ($k = 0; $k < $count; $k++) {
                $r = $base + 28 * $k;
                $len = unpack('N', $d, $r + 7)[1];
                if ($len > 64 * self::QUARTER) {
                    // not a staff of notes (the drum loops have other records)
                    continue 2;
                }
                $pitch = ord($d[$r]);
                if ($pitch < 128) {
                    $notes[] = [$t / self::QUARTER, $len / self::QUARTER, $pitch, ord($d[$r + 14])];
                }
                $t += $len;
            }
            $staves[] = ['name' => substr($d, $i, ord($d[$i - 1])), 'notes' => $notes];
        }
        return $staves;
    }

    /**
     * @return array<string, array{rate:float, base:int, volume:int, loopStart:int, loopEnd:int, samples:float[]}>
     *   name => instrument, samples in -1..1 at `rate`
     */
    public function instruments(): array
    {
        $d = $this->data;
        $list = [];
        $i = 0;
        while (($i = strpos($d, 'DGIT', $i + 1)) !== false) {
            // name: the Pascal string after the 0x0001 that starts the instrument chunk
            $name = $this->instrumentName($i);
            $k = $i + 4;
            while (!in_array(substr($d, $k + 4, 4), ["\x00\x03\x00\x10", "\x40\x00\x00\x08", "\x40\x00\x00\x10"], true)) {
                $k++;
            }
            ['type' => $type, 'bits' => $bits, 'rate' => $rate, 'base' => $base, 'volume' => $volume, 'ls' => $ls, 'le' => $le, 'len' => $len]
                = unpack('Nsize/ntype/nbits/Nrate/nbase/nvolume/Nls/Nle/Nlen', $d, $k);
            $rate /= 65536;
            $samples = [];
            if ($type === 3) {
                $t = $k + 0x2c;
                $table = array_values(unpack('n128', $d, $t));
                $p = $t + 256;
                $n = intdiv($len, 2);
                for ($j = 0; $j < $n; $j++) {
                    $v = ord($d[$p + $j]);
                    $samples[] = ($v < 128 ? $table[$v] : -$table[min(127, 256 - $v)]) / 32768;
                }
                $rate /= 2;
                $ls = intdiv($ls, 2);
                $le = intdiv($le, 2);
            } else {
                $p = $k + 0x2c;
                if ($bits === 16) {
                    foreach (unpack("n$len", $d, $p) as $v) {
                        $samples[] = ($v >= 32768 ? $v - 65536 : $v) / 32768;
                    }
                } else {
                    for ($j = 0; $j < $len; $j++) {
                        $samples[] = (ord($d[$p + $j]) - 128) / 128;
                    }
                }
            }
            $list[$name] = ['rate' => $rate, 'base' => $base, 'volume' => $volume, 'loopStart' => $ls,
                'loopEnd' => $le > $ls + 16 ? $le : 0, 'samples' => $samples];
        }
        return $list;
    }

    private function instrumentName(int $dgit): string
    {
        // the chunk starts with ... 00 01 <len> <name> 00 ..., about 0x40 bytes before "MIDI"
        $d = $this->data;
        for ($p = $dgit - 0x30; $p > $dgit - 0x80; $p--) {
            if ($d[$p] === "\x01" && $d[$p - 1] === "\x00") {
                $n = ord($d[$p + 1]);
                $name = substr($d, $p + 2, $n);
                if ($n >= 3 && $n < 24 && preg_match('/^[A-Za-z0-9 \-]+$/', $name)) {
                    return $name;
                }
            }
        }
        return 'dgit' . $dgit;
    }
}
