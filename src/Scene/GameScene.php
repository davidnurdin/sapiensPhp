<?php

namespace App\Scene;

use App\Sapiens\Bmp;
use App\Sapiens\Game;
use App\Sapiens\Mac;
use JumPHP\Kernel\Platform;
use JumPHP\Renderer\Renderer;
use JumPHP\Scene\AbstractScene;

/**
 * The whole game: runs the port of the C code (App\Sapiens\Game::main(), a generator), one step per
 * frame, and shows its window (an 8-bit 640x400 offscreen) as a texture.
 *
 * Params: demo: true = a bot clicks and walks (smoke test).
 */
final class GameScene extends AbstractScene
{
    /** action => Mac key code of the original (GetKeys) */
    private const KEYS = [
        'up' => Game::MK_UP,
        'down' => Game::MK_DOWN,
        'left' => Game::MK_LEFT,
        'right' => Game::MK_RIGHT,
        'fire' => Game::MK_SPACE,
        'enter' => Game::MK_RETURN,
        'escape' => Game::MK_ESC,
        'map' => Game::MK_C,
        'shift' => Game::MK_SHIFT,
        'p2_up' => Game::MK_KP8,
        'p2_down' => Game::MK_KP2,
        'p2_left' => Game::MK_KP4,
        'p2_right' => Game::MK_KP6,
        'p2_fire' => Game::MK_KP1,
        'p2_weapon' => Game::MK_KP3,
    ];

    private const CLICK_HOLD = 0.12;

    private ?Game $game = null;
    private ?\Generator $main = null;
    private bool $demo = false;
    private int $demoSeed = 0;
    private int $frame = 0;
    private ?string $error = null;
    private string $bmpHeader = '';
    private string $tmpFile = '';
    private bool $hasScreen = false;
    private float $clickHold = 0;

    public function enter(array $params = []): void
    {
        $this->error = null;
        $this->frame = 0;
        $this->hasScreen = false;
        $this->clickHold = 0;
        $this->demo = (bool)($params['demo'] ?? false);
        $this->demoSeed = (int)($params['seed'] ?? 0);
        $dataDir = $this->engine->config->path('assets/data');
        if (!Game::hasData($dataDir)) {
            $this->error = "THE FILES OF THE GAME ARE MISSING (assets/data/mobs)";
            return;
        }
        $this->loadSounds();
        $this->game = new Game($dataDir, $this->engine->options, $this->sound());
        foreach ([1, 2] as $i) {
            if (is_file($this->engine->config->path("assets/music/sapiens$i.ogg"))) {
                $this->game->musics["sapiens$i"] = true;
            }
        }
        $this->game->langue = match ($this->engine->lang->getLang()) {
            'fr' => Game::FR,
            'es' => Game::ES,
            default => Game::GB,
        };
        foreach (['fr' => Game::FR, 'en' => Game::GB, 'es' => Game::ES] as $code => $l) {
            if (isset($params['lang']) && $params['lang'] === $code) {
                $this->game->langue = $l;
            }
        }
        $this->game->touchMode = Platform::isMobile();
        $this->game->onHelp = function (): void {
            $this->engine->bridge->send('open', ['url' => '/#aide']);
        };
        $this->game->onQuit = function (): void {
            $this->engine->quit();
        };
        Mac::init();
        $this->bmpHeader = Bmp::header(640, 400, Mac::$clut, 640 * 400);
        $this->tmpFile = sys_get_temp_dir() . '/sapiens-screen.bmp';
        $this->main = $this->game->main();
        $this->guard(fn () => $this->main->current());
    }

    private function loadSounds(): void
    {
        $sound = $this->sound();
        for ($i = 0; $i <= 12; $i++) {
            $file = "assets/sfx/bruit$i.wav";
            if (!$sound->hasSfx("bruit$i") && is_file($this->engine->config->path($file))) {
                $sound->loadSfx("bruit$i", $file);
            }
        }
        foreach ([1, 2] as $i) {
            $file = "assets/music/sapiens$i.ogg";
            if (is_file($this->engine->config->path($file))) {
                $sound->loadMusic("sapiens$i", $file);
            }
        }
    }

    public function update(float $dt): void
    {
        if ($this->main === null || $this->game === null || $this->error !== null) {
            return;
        }
        $this->frame++;
        $this->readInput($dt);
        $this->game->advanceTime(min($dt, 0.1) * 1000);
        $this->guard(function (): void {
            if ($this->main->valid()) {
                $this->main->next();
            }
        });
    }

    private function guard(callable $f): void
    {
        try {
            $f();
        } catch (\Throwable $e) {
            $this->error = get_class($e) . ': ' . $e->getMessage() . "\n" . basename($e->getFile()) . ':' . $e->getLine();
            error_log('[sapiens] ' . $e);
        }
    }

    private function readInput(float $dt): void
    {
        $g = $this->game;
        if ($this->demo) {
            $this->demoInput();
            return;
        }
        $actions = $this->actions();
        foreach (self::KEYS as $action => $code) {
            $g->keysHeld[$code] = $actions->isHeld($action) || $actions->isPressed($action);
        }
        $kb = $this->engine->input->getKeyboard();
        $shift = $actions->isHeld('shift');
        foreach ($kb->getTyped() as $code) {
            if ($code >= 97 && $code <= 122 && $shift) {
                $code -= 32;
            }
            if ($code > 0 && $code < 256) {
                $g->typedQueue[] = $code;
            }
        }
        $mouse = $this->engine->input->getMouse();
        $g->mouseX = (int)$mouse->x;
        $g->mouseY = (int)$mouse->y;
        // a tap is down and up in the same frame, and the game reads the button only between its waits:
        // keep a click down until the game has seen it, and at least CLICK_HOLD seconds
        if ($mouse->isPressed(1)) {
            $this->clickHold = self::CLICK_HOLD;
            $g->mouseRead = false;
        } elseif ($this->clickHold > 0 && $g->mouseRead) {
            $this->clickHold -= $dt;
        }
        $g->mouseButton = $mouse->isHeld(1) || $this->clickHold > 0;
        if ($mouse->isHeld(3) || $mouse->isPressed(3)) {
            // right button = shift + click (change of weapon in the side view)
            $g->mouseButton = true;
            $g->keysHeld[Game::MK_SHIFT] = true;
        }
    }

    /** A bot for the smoke test: random clicks in the menus and the picture, random keys. */
    private function demoInput(): void
    {
        $g = $this->game;
        $f = $this->frame;
        if ($f === 1) {
            mt_srand((int)($this->demoSeed ?: 1987));
        }
        if ($f % 20 === 0) {
            $g->keysHeld = [];
            $r = mt_rand(0, 9);
            if ($r < 6) {
                // a click in the menus (bottom) or in the picture
                $g->mouseX = mt_rand(40, 600);
                $g->mouseY = $r < 5 ? mt_rand(240, 360) : mt_rand(10, 200);
            }
            foreach ([Game::MK_UP, Game::MK_DOWN, Game::MK_LEFT, Game::MK_RIGHT, Game::MK_SPACE, Game::MK_RETURN] as $k) {
                $g->keysHeld[$k] = mt_rand(0, 4) === 0;
            }
            $g->keysHeld[Game::MK_ESC] = mt_rand(0, 12) === 0;
            if (mt_rand(0, 8) === 0) {
                $g->typedQueue[] = [13, 27, 49, 50, 51, 111, 104][mt_rand(0, 6)];
            }
        }
        $g->mouseButton = $f % 20 >= 2 && $f % 20 < 5;
    }

    public function render(Renderer $r): void
    {
        if ($this->error !== null) {
            $r->clear([0, 0, 0]);
            $this->font()->draw($r, strtoupper($this->error), 10, 20, 1, [255, 220, 120]);
            return;
        }
        if ($this->game === null) {
            return;
        }
        $win = $this->game->mainWindow;
        if ($win->dirty || !$this->hasScreen) {
            $win->dirty = false;
            file_put_contents($this->tmpFile, $this->bmpHeader . implode('', array_reverse($win->rows)));
            $this->sdl()->loadTexture('screen', $this->tmpFile);
            $this->hasScreen = true;
        }
        $r->copy($this->sdl()->getTexture('screen')['texture'], null, 0, 0, 640, 400);
    }
}
