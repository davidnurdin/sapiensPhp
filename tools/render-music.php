<?php

/**
 * Offline: renders the two musics of SAPIENS from the score of 1987 (MUSE.C, tools/MuseData.php).
 *
 * The 1996 version played Sapiens1.mux / Sapiens2.mux with a proprietary library (not published);
 * the older sequencer (JoueVoie, SAPWMUS.C, OLD_VERSION) played this score on 3 voices of a MIDI synth
 * at 50 Hz. It is replayed here tick by tick and the notes are synthesised (one simple instrument per
 * General MIDI program of tabins1 / tabins2).
 *
 * Output: assets/music/sapiens<n>.wav (22050 Hz mono 16-bit); encode them in OGG on the host:
 *   ffmpeg -i sapiens1.wav -c:a libvorbis -q:a 3 sapiens1.ogg
 * Usage: bin/jumphp php games/sapiens -d memory_limit=1G tools/render-music.php
 */

const RATE = 22050;
const TICK = RATE / 50;
const MAX_TICKS = 50 * 60 * 20;

$data = require __DIR__ . '/MuseData.php';

// Instr: canal, program (GM, 0-based), coupure, decal_note
$ins = [
    '1' => [1, 33, 0, 0], '2' => [2, 116, 0, 12], '4' => [4, 116, 0, 12], '6' => [6, 12, 0, 0], '8' => [8, 75, 0, 12],
    '21' => [2, 7, 0, 0], '22' => [3, 115, 0, 0], '24' => [4, 37, 0, 0], '25' => [5, 119, 0, 0], '27' => [7, 117, 0, 0],
    '28' => [8, 118, 0, 0], '29' => [9, 87, 0, 0], '2a' => [6, 110, 0, 0], '2b' => [11, 113, 0, 0], '2c' => [12, 32, 0, 0],
    '2d' => [13, 30, 0, 12], '2e' => [14, 28, 0, 0], '2f' => [15, 118, 0, 0],
];
$tabins1 = ['1', '1', '2', '1', '4', '1', '6', '1', '8', '1', '1', '1', '1', '1', '1', '1'];
$tabins2 = ['1', '21', '22', '21', '24', '25', '2c', '27', '28', '29', '2a', '2b', '2c', '2d', '2e', '2f'];

foreach ([1, 2] as $quelle) {
    $debmus = $quelle === 1 ? $data['muse1'] : $data['muse2'];
    $tabins = $quelle === 1 ? $tabins1 : $tabins2;
    $voies = $quelle === 1 ? ['dvoie1', 'dvoie2', 'dvoie3'] : ['dvoie21', 'dvoie22', 'dvoie23'];
    $events = sequence($debmus, $tabins, $ins, array_map(fn ($v) => $data[$v], $voies), $ticks);
    $buf = synth($events, $ticks);
    $file = __DIR__ . "/../assets/music/sapiens$quelle.wav";
    @mkdir(dirname($file), 0777, true);
    file_put_contents($file, wav($buf));
    $peak = 0.0;
    foreach ($buf as $s) {
        $peak = max($peak, abs($s));
    }
    printf("sapiens%d: %d notes, %.1f s, peak %.2f -> %s\n", $quelle, count($events), $ticks / 50, $peak, basename($file));
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

/** @return float[] */
function synth(array $events, int $ticks): array
{
    $n = (int)($ticks * TICK);
    $buf = array_fill(0, $n, 0.0);
    $seed = 12345;
    foreach ($events as [$start, $len, $note, $prog, $vol]) {
        $s0 = (int)($start * TICK);
        $dur = max(1, (int)($len * TICK));
        $f = 440.0 * 2 ** (($note - 69) / 12);
        $kind = kind($prog);
        // length of the sound: percussions and plucked sounds ring a little after the note
        $total = match ($kind) {
            'drum' => min((int)(0.35 * RATE), $dur + (int)(0.25 * RATE)),
            'pluck', 'mallet', 'bass' => $dur + (int)(0.08 * RATE),
            default => $dur + (int)(0.04 * RATE),
        };
        $amp = 0.22 * $vol;
        $phase = 0.0;
        $inc = $f / RATE;
        for ($i = 0; $i < $total && $s0 + $i < $n; $i++) {
            $t = $i / RATE;
            // release after the end of the note
            $rel = $i < $dur ? 1.0 : max(0.0, 1.0 - ($i - $dur) / (0.04 * RATE));
            $att = min(1.0, $i / (0.004 * RATE));
            switch ($kind) {
                case 'bass':
                    $x = 2.0 * $phase - 1.0;
                    $tri = 1.0 - 4.0 * abs($phase - 0.5);
                    $s = (0.6 * $tri + 0.4 * $x * exp(-$t * 6)) * (0.55 + 0.45 * exp(-$t * 3));
                    break;
                case 'mallet':
                    $s = (sin(2 * M_PI * $phase) + 0.25 * sin(8 * M_PI * $phase) * exp(-$t * 30)) * exp(-$t * 7);
                    break;
                case 'flute':
                    $vib = 1 + 0.004 * sin(2 * M_PI * 5.5 * $t) * min(1.0, $t * 3);
                    $inc = $f * $vib / RATE;
                    $s = (sin(2 * M_PI * $phase) + 0.12 * sin(4 * M_PI * $phase)) * min(1.0, $t / 0.05);
                    break;
                case 'pluck':
                    $s = ($phase < 0.3 ? 1.0 : -0.43) * exp(-$t * 5) * 0.8;
                    break;
                case 'lead':
                    $s = ($phase < 0.5 ? 0.6 : -0.6) * (0.7 + 0.3 * exp(-$t * 4));
                    break;
                case 'string':
                    $vib = 1 + 0.005 * sin(2 * M_PI * 6 * $t) * min(1.0, $t * 2);
                    $inc = $f * $vib / RATE;
                    $s = (2.0 * $phase - 1.0) * 0.6 * min(1.0, $t / 0.08);
                    break;
                default:
                    // drum: a falling tone and a noise burst
                    $seed = ($seed * 1103515245 + 12345) & 0x7FFFFFFF;
                    $noise = $seed / 0x3FFFFFFF - 1.0;
                    $inc = $f * (0.5 + 1.5 * exp(-$t * 25)) / RATE;
                    $s = sin(2 * M_PI * $phase) * exp(-$t * 12) + 0.5 * $noise * exp(-$t * 30);
                    $rel = 1.0;
                    break;
            }
            $phase += $inc;
            $phase -= floor($phase);
            $buf[$s0 + $i] += $s * $amp * $att * $rel;
        }
    }
    // simple low-pass against the aliasing of the square waves
    $prev = 0.0;
    foreach ($buf as $i => $s) {
        $prev += 0.55 * ($s - $prev);
        $buf[$i] = $prev;
    }
    return $buf;
}

function kind(int $prog): string
{
    if ($prog >= 32 && $prog <= 39) {
        return 'bass';
    }
    if ($prog >= 8 && $prog <= 15) {
        return 'mallet';
    }
    if ($prog >= 72 && $prog <= 79) {
        return 'flute';
    }
    if ($prog === 7 || ($prog >= 24 && $prog <= 31)) {
        return 'pluck';
    }
    if ($prog >= 80 && $prog <= 95) {
        return 'lead';
    }
    if ($prog >= 40 && $prog <= 55 || $prog === 110) {
        return 'string';
    }
    return 'drum';
}

/** @param float[] $buf */
function wav(array $buf): string
{
    $pcm = '';
    foreach ($buf as $s) {
        $v = (int)round(max(-1.0, min(1.0, $s)) * 32000);
        $pcm .= pack('v', $v & 0xFFFF);
    }
    $n = strlen($pcm);
    return 'RIFF' . pack('V', 36 + $n) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, RATE, RATE * 2, 2, 16) . 'data' . pack('V', $n) . $pcm;
}
