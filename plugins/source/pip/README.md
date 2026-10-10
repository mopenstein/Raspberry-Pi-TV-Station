# Picture-in-Picture Plugin

A video playback plugin for the TV station emulator that handles the `"pip"` schedule keyword. 

It uses Raspberry Pi DispmanX hardware layering in OMXPlayer to run a primary full-screen video on **Layer 1** while displaying an animated video overlay, bug, or promo window on **Layer 2**.

---

## What It Does

- **Hardware Layering**: Runs the main broadcast program on Layer 1 and renders a floating overlay window on Layer 2 via DBus control.
- **Audio Ducking**: Automatically lowers the main program's volume while an overlay clip plays, restoring full volume when it ends.
- **Flexible Layouts**: Position the overlay using named corner presets (`top-right`, `bottom-left`, `center`, etc.), pixel bounding boxes (`[x1, y1, x2, y2]`), or coordinate dictionaries (`{"left", "top", "width", "height"}`).
- **Timing & Repetition**: Supports start delays, per-clip cut durations, intermission pauses (`wait`) between plays, and loop limits or timeouts.
- **Alpha Transparency**: Set overlay opacity from `0` (invisible) to `255` (opaque) for watermarks and semi-transparent bugs.
- **Synced Lifecycle**: The primary program dictates the schedule block—when the main video finishes, all active overlays terminate cleanly.

---

## Installation

1. Copy `picture-in-picture.py` into the plugin directory configured in your emulator's `settings.json`:
2. The emulator will automatically discover and load the plugin on startup or when settings reload.

---

## Schedule Example (`settings.json`)

Add an entry with `"type": "pip"` to your `times` schedule array:

```json
{
  "name": ["%D[1]%/broadcast/features"],
  "type": "pip",
  "primary": {
    "source": ["movies/action"],
    "type": "random",
    "volume": 10
  },
  "overlay": {
    "source": ["promos/bumpers"],
    "position": "bottom-right",
    "margin": 20,
    "volume": 8,
    "duck-primary": 2,
    "delay": 10,
    "duration": 15,
    "wait": 60,
    "loop": 3
  }
}
```

---

## Configuration Keys

### `primary` Block
| Key | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `source` | `str` / `list` | *Required* | Folder path containing base video files. Supports `@` for absolute paths. |
| `type` | `str` | `"random"` | Selection mode (`"random"` or `"first"`). |
| `volume` | `float` | `10.0` | Base playback volume (`0.0` to `10.0`, unity gain is 10). |

### `overlay` Block
| Key | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `source` | `str` / `list` | *Required* | Folder path containing overlay clips. |
| `position` | `str` / `list` / `dict` | `"bottom-right"` | Corner presets, `[x1, y1, x2, y2]` coords, or `{"left", "top", "width", "height"}`. |
| `margin` | `int` | `20` | Offset margin from the screen edge when using presets. |
| `alpha` | `int` | `255` | Window opacity (`0`–`255`). |
| `volume` | `float` | `0` | Overlay volume (`0.0` to `10.0`). Default is muted. |
| `duck-primary` | `float` | `None` | Volume level to duck the main program down to during overlay playback. |
| `delay` | `float` | `0` | Seconds to wait after main video starts before first spawning the overlay. |
| `duration` | `float` | `None` | Max duration (in seconds) to let an overlay clip run before cutting it off. |
| `wait` | `float` | `0` | Seconds to pause between repeating overlay clips. |
| `loop` | `int` | `-1` | Number of times to cycle the overlay (`-1` for infinite until main video ends). |
| `max-duration` | `float` | `None` | Overall cutoff limit (seconds) for overlay activity across all loops. |
