# SAPIENS — PHP port

*Il y a cent mille ans… / A hundred thousand years ago…*

**SAPIENS** is the prehistoric adventure game by **Didier & Olivier Guillion** (1986: Thomson, Amstrad
CPC, PC; 1987: Atari ST; 1996: MacOS / Windows; freeware in 2017). In 2018 Myriad published its C
sources under the **GNU GPL v3**. This repository is a faithful port of those sources to **PHP**, on
the [JumPHP](https://github.com/davidnurdin/jumPhp) framework: the same code runs on the desktop
(PHP + SDL2) and in the browser (PHP compiled to WebAssembly), on computers and phones.

Play online: see the game site (link in the GitHub description).

## Le jeu

You are Torgan, a young hunter of the *Pieds-Agiles* tribe. Explore the valley in first-person view,
survive hunger, thirst and fatigue, hunt, knap flint to make weapons, talk and trade with the other
tribes (Yeux-Malins, Bras-de-Roc, Hyènes-Folles), fight in side view, and complete the quests of your
chief Hognor… until the law of strength names the new chief.

## Run it

```bash
git clone https://github.com/davidnurdin/jumPhp
git clone https://github.com/davidnurdin/sapiensPhp jumPhp/games/sapiens
cd jumPhp
bin/jumphp install sapiens
bin/jumphp run sapiens            # desktop window (Docker, X11 / Wayland)
bin/jumphp engine:build           # once: the WebAssembly engine
bin/jumphp build sapiens          # browser build in games/sapiens/dist/web
bin/jumphp serve sapiens          # http://localhost:8080
```

## Controls

| | Computer | Phone |
|---|---|---|
| Menus (bottom of the screen) | mouse | tap |
| Landscape: turn / speed | arrows | joystick |
| Landscape: map | Space | CARTE |
| Side view: walk / crouch | arrows | joystick |
| Side view: strike / change weapon | Space / Return (or right click) | FRAPPER / ARME |
| Leave the view | Escape or click | SORTIR |
| Second player | numeric keypad (8 2 4 6, 1 strike, 3 weapon) | |

## How the port works

See [AGENTS.md](AGENTS.md): one PHP trait per C file with the C function names, an 8-bit 640x400
offscreen like the original Mac version, the original sprites (`.BRT`), sounds and texts read as they
are. The original files of the 2018 archive that were converted are in `assets/` (see `tools/`).

## Credits & license

- SAPIENS © Didier & Olivier Guillion — Myriad, 1986-2017. Music by Gilles Soulet (1986),
  rendered with the instruments of the original `.mux` files.
- Sources and data: GNU GPL v3 (Myriad, 3 April 2018): <https://www.myriad-online.com/fr/products/sapiensopensource.htm>. This port: GNU GPL v3, see [LICENSE](LICENSE).
