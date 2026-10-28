<?php

/**
 * Offline: renders the two musics of SAPIENS with the instruments of the 1996 version.
 *
 * The game played data/musics/Sapiens1.mux / Sapiens2.mux (Harmony Assistant songs, read by tools/Mux.php):
 * - music 1 is a plain score (5 staves): it is replayed note for note with its sampled instruments;
 * - music 2 is built by the accompaniment engine of Harmony (patterns over a chord grid), which is not
 *   reproduced: its notes come from the score of 1987 it was made from (MUSE.C, tools/MuseData.php,
 *   played by JoueVoie of SAPWMUS.C at 50 Hz), played with the sampled instruments of Sapiens2.mux
 *   (same General MIDI programs).
 *
 * Input: .original/data/musics/Sapiens{1,2}.mux at the root of the jumPhp clone (from sapiens_03_04_2018.zip).
 * Output: assets/music/sapiens<n>.wav (44100 Hz mono 16-bit); encode them in OGG on the host:
 *   ffmpeg -i sapiens1.wav -c:a libvorbis -q:a 4 sapiens1.ogg
 * Usage: bin/jumphp php games/sapiens -d memory_limit=2G tools/render-music.php
 */

require __DIR__ . '/Mux.php';

const RATE = 44100;
const TICK = RATE / 50;
const MAX_TICKS = 50 * 60 * 20;
const RELEASE = 0.12;

$dir = __DIR__ . '/../../../.original/data/musics';
$data = require __DIR__ . '/MuseData.php';

// Instr of SAPWMUS.C: canal, program (GM, 0-based), coupure, decal_note
$ins = [
    '1' => [1, 33, 0, 0], '2' => [2, 116, 0, 12], '4' => [4, 116, 0, 12], '6' => [6, 12, 0, 0], '8' => [8, 75, 0, 12],
    '21' => [2, 7, 0, 0], '22' => [3, 115, 0, 0], '24' => [4, 37, 0, 0], '25' => [5, 119, 0, 0], '27' => [7, 117, 0, 0],
    '28' => [8, 118, 0, 0], '29' => [9, 87, 0, 0], '2a' => [6, 110, 0, 0], '2b' => [11, 113, 0, 0], '2c' => [12, 32, 0, 0],
    '2d' => [13, 30, 0, 12], '2e' => [14, 28, 0, 0], '2f' => [15, 118, 0, 0],
];
$tabins2 = ['1', '21', '22', '21', '24', '25', '2c', '27', '28', '29', '2a', '2b', '2c', '2d', '2e', '2f'];

// music 1: staff => instrument of Sapiens1.mux, transposition (the marimba is written an octave low)
$staves1 = [['C4P117', 0], ['C6P13', 12], ['C1P34', 0], ['C4P117', 0], ['C8P76', 0]];
// music 2: GM program of the 1987 score => instrument of Sapiens2.mux, drum (null = pitched, 0 = unpitched,
// else the note played at the pitch of the sample)
$programs2 = [
    37 => ['C4P38', null], 7 => ['C2P8', null], 32 => ['C12P33', null], 87 => ['C9P88', null],
    110 => ['C6P111', null], 30 => ['C13P31', null], 28 => ['C13P31', null],
    119 => ['kick drum 1', 0], 118 => ['snare drum 1', 0], 117 => ['low bongo', 50], 113 => ['claves', 0],
    115 => ['cabasa', 0],
];
// volumes of the drum tracks of the rhythms of Sapiens2.mux (the samples are full scale)
$drumVolume = ['kick drum 1' => 112, 'cabasa' => 36, 'low bongo' => 80, 'snare drum 1' => 80, 'claves' => 64];
// mix: the melody in front (the samples are not at the same level: rendered raw, the bass and the drums
// cover the lead instruments)
$mix = [
    1 => ['C4P117' => 0.7, 'C6P13' => 1.2, 'C1P34' => 0.6, 'C8P76' => 2.2],
    2 => ['C4P38' => 0.3, 'kick drum 1' => 0.45, 'C2P8' => 1.3, 'C12P33' => 1.0, 'C9P88' => 2.8,
        'C6P111' => 2.2, 'C13P31' => 2.0, 'low bongo' => 1.2, 'snare drum 1' => 2.0, 'claves' => 1.5, 'cabasa' => 2.0],
];

foreach ([1, 2] as $quelle) {
    $mux = new Mux("$dir/sapiens$quelle.mux");
    $instruments = $mux->instruments();
    $notes = [];   // start (s), length (s), instrument, midi note (null = unpitched), velocity 0..1
    if ($quelle === 1) {
        $beat = 60 / $mux->tempo();
        foreach ($mux->staves() as $i => $staff) {
            [$name, $transpose] = $staves1[$i];
            foreach ($staff['notes'] as [$start, $len, $pitch, $vel]) {
                $notes[] = [$start * $beat, $len * $beat, $name, $pitch + $transpose, $vel / 127 * $mix[1][$name]];
            }
        }
    } else {
        $events = sequence($data['muse2'], $tabins2, $ins, [$data['dvoie21'], $data['dvoie22'], $data['dvoie23']], $ticks);
        foreach ($events as [$start, $len, $note, $prog, $vol]) {
            [$name, $drum] = $programs2[$prog] ?? throw new RuntimeException("no instrument for program $prog");
            if ($prog === 115 && $note < 70) {
                // the low notes of this percussion line are hand drums
                [$name, $drum] = ['low bongo', 64];
            }
            // the drums of the 1987 score are notes of melodic programs: only the toms keep a pitch
            $pitch = $drum === null ? $note : ($drum > 0 ? $note - $drum : null);
            $notes[] = [$start / 50, $len / 50, $name, $pitch, ($drumVolume[$name] ?? 127) / 127 * $vol * ($mix[2][$name] ?? 1.0)];
        }
    }
    $buf = master(render($notes, $instruments));
    $file = __DIR__ . "/../assets/music/sapiens$quelle.wav";
    @mkdir(dirname($file), 0777, true);
    file_put_contents($file, wav($buf));
    printf("sapiens%d: %d notes, %.1f s -> %s\n", $quelle, count($notes), count($buf) / RATE, basename($file));
}

/**
 * JoueVoie for the 3 voices until they are all finished.
 * @return array<int, array{0:int,1:int,2:int,3:int,4:float}> notes: start tick, length (ticks), midi note, program, volume
 */
function sequence(array $debmus, array $tabins, array $ins, array $dvoies, ?int &$ticks): array
{
    $voices = [];
    foreach ($dvoies as $dv) {
        // init_voie: the sequence 'seqnul' (0xFF) makes the first tick read the track list
        $voices[] = ['dv' => $dv, 'pv' => 0, 'seq' => [0xFF, 0], 'p' => 0, 'deb' => -1, 'nbrseq' => 1, 'base' => 0,
            'insdef' => 0, 'instr' => '1', 'time' => 0, 'maxtime' => 0, 'fini' => false, 'note' => null];
    }
    $volume = 100 / 127;
    $events = [];
    for ($t = 0; $t < MAX_TICKS; $t++) {
        $fini = true;
        foreach ($voices as &$v) {
            if ($v['fini']) {
                continue;
            }
            $v['time']++;
            if ($v['time'] >= $v['maxtime']) {
                // the note ends (arretvoie)
                if ($v['note'] !== null) {
                    $events[$v['note']][1] = $t - $events[$v['note']][0];
                    $v['note'] = null;
                }
                $p = $v['p'];
                $cont = true;
                do {
                    $seq = $v['deb'] < 0 ? $v['seq'] : $debmus;
                    $car = $seq[$p++] ?? 255;
                    if ($car >= 128) {
                        if ($car === 255) {
                            if (--$v['nbrseq'] <= 0) {
                                $car = $v['dv'][$v['pv']++] ?? 0;
                                if ($car) {
                                    $v['nbrseq'] = $car;
                                    $v['deb'] = $p = $v['dv'][$v['pv']++];
                                    $v['base'] = $v['dv'][$v['pv']++];
                                    $v['insdef'] = $v['dv'][$v['pv']++];
                                } else {
                                    $v['fini'] = true;
                                    continue 2;
                                }
                            } else {
                                $p = $v['deb'] < 0 ? 0 : $v['deb'];
                            }
                        } else {
                            $car &= 127;
                            if ($car < 16) {
                                if ($car === 0) {
                                    $car = $v['insdef'];
                                }
                                $v['instr'] = $tabins[$car & 15];
                            } else {
                                $car &= 15;
                                $param = $debmus[$p++] ?? 0;
                                if ($car === 9) {
                                    $volume = min(127, $param * 8) / 127;
                                } elseif ($car === 10) {
                                    $vol = (int)round($volume * 127 / 8) + ($param >= 128 ? $param - 256 : $param);
                                    $volume = max(0, min(127, $vol * 8)) / 127;
                                }
                            }
                        }
                    } else {
                        $instr = $ins[$v['instr']];
                        if ($car > 0) {
                            $car += $v['base'] + $instr[3];
                        }
                        $v['time'] = 0;
                        $v['maxtime'] = $debmus[$p++] ?? 0;
                        if ($car > 0) {
                            $v['note'] = count($events);
                            $events[] = [$t, $v['maxtime'], $car & 127, $instr[1], $volume];
                        }
                        $cont = false;
                    }
                } while ($cont);
                $v['p'] = $p;
            }
            if (!$v['fini']) {
                $fini = false;
            }
        }
        unset($v);
        if ($fini) {
            break;
        }
    }
    $ticks = $t + 25;
    return $events;
}

/**
 * Plays the samples: linear interpolation, sustain loop while the note is held, short release.
 * @return SplFixedArray<float>
 */
function render(array $notes, array $instruments): SplFixedArray
{
    $end = 0.0;
    foreach ($notes as [$start, $len]) {
        $end = max($end, $start + $len);
    }
    $n = (int)(($end + 2) * RATE);
    $buf = new SplFixedArray($n);
    for ($i = 0; $i < $n; $i++) {
        $buf[$i] = 0.0;
    }
    foreach ($notes as [$start, $len, $name, $pitch, $vel]) {
        $ins = $instruments[$name] ?? throw new RuntimeException("unknown instrument $name");
        $smp = $ins['samples'];
        $count = count($smp);
        $step = $ins['rate'] / RATE * ($pitch === null ? 1.0 : 2 ** (($pitch - $ins['base']) / 12));
        $loopStart = $ins['loopStart'];
        $loopEnd = $ins['loopEnd'];
        $gain = $vel * $ins['volume'] / 127;
        $s0 = (int)($start * RATE);
        $held = (int)($len * RATE);
        $total = $held + (int)(RELEASE * RATE);
        if ($pitch === null) {
            // drums ring to the end of their sample
            $total = max($total, (int)($count / $step));
            $held = $total;
        }
        $pos = 0.0;
        for ($i = 0; $i < $total && $s0 + $i < $n; $i++) {
            if ($loopEnd > 0) {
                while ($pos >= $loopEnd) {
                    $pos -= $loopEnd - $loopStart;
                }
            } elseif ($pos >= $count - 1) {
                break;
            }
            $k = (int)$pos;
            $f = $pos - $k;
            $v = $smp[$k] + ($smp[$k + 1] ?? $smp[$k]) * $f - $smp[$k] * $f;
            if ($i >= $held) {
                $v *= 1 - ($i - $held) / ($total - $held);
            } elseif ($i < 64) {
                $v *= $i / 64;
            }
            $buf[$s0 + $i] += $v * $gain;
            $pos += $step;
        }
    }
    return $buf;
}

/**
 * Brightness (the samples are 22 kHz, linear interpolation dulls them): high shelf +5 dB above ~2.5 kHz,
 * then a soft limiter so the quiet lead parts are not lost under the peaks of the drums, normalised to 0.9.
 * @param SplFixedArray<float> $buf
 * @return SplFixedArray<float>
 */
function master(SplFixedArray $buf): SplFixedArray
{
    // RBJ high shelf, S = 1
    $a = 10 ** (5 / 40);
    $w = 2 * M_PI * 2500 / RATE;
    $cs = cos($w);
    $alpha = sin($w) / 2 * sqrt(2);
    $sq = 2 * sqrt($a) * $alpha;
    $b0 = $a * (($a + 1) + ($a - 1) * $cs + $sq);
    $b1 = -2 * $a * (($a - 1) + ($a + 1) * $cs);
    $b2 = $a * (($a + 1) + ($a - 1) * $cs - $sq);
    $a0 = ($a + 1) - ($a - 1) * $cs + $sq;
    $a1 = 2 * (($a - 1) - ($a + 1) * $cs);
    $a2 = ($a + 1) - ($a - 1) * $cs - $sq;
    $x1 = $x2 = $y1 = $y2 = 0.0;
    $sum = 0.0;
    $n = count($buf);
    foreach ($buf as $i => $x) {
        $y = ($b0 * $x + $b1 * $x1 + $b2 * $x2 - $a1 * $y1 - $a2 * $y2) / $a0;
        $x2 = $x1;
        $x1 = $x;
        $y2 = $y1;
        $y1 = $y;
        $buf[$i] = $y;
        $sum += $y * $y;
    }
    // drive: the RMS at 0.2 before the limiter
    $drive = 0.2 / max(sqrt($sum / max(1, $n)), 1e-6);
    $peak = 0.0;
    foreach ($buf as $i => $v) {
        $v = tanh($v * $drive);
        $buf[$i] = $v;
        $peak = max($peak, abs($v));
    }
    $scale = 0.9 / max($peak, 0.01);
    foreach ($buf as $i => $v) {
        $buf[$i] = $v * $scale;
    }
    return $buf;
}

/** @param SplFixedArray<float> $buf */
function wav(SplFixedArray $buf): string
{
    $pcm = '';
    foreach ($buf as $s) {
        $v = (int)round(max(-1.0, min(1.0, $s)) * 32000);
        $pcm .= pack('v', $v & 0xFFFF);
    }
    $n = strlen($pcm);
    return 'RIFF' . pack('V', 36 + $n) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, RATE, RATE * 2, 2, 16) . 'data' . pack('V', $n) . $pcm;
}
