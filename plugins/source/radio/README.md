# Raspberry Pi Radio Automation Plugin (v0.1)

A flexible, keyword-driven radio automation plugin designed for Python 2 and `omxplayer`. This system allows for complex radio programming schedules, including DJ "shifts," automated commercials, background audio "beds," and seasonal content ramping.

> **Note:** The included settings file is provided as a reference example. Users are expected to create their own `settings.json` tailored to their specific directory structures, media paths, and programming needs.

## Features

* **Keyword Handling**: Supports `audio` (simple folder playback) and `radio` (complex format playback).
* **Playback Modes**:
    * `random`: Standard shuffle.
    * `balanced`: Uses backend logic to ensure a varied rotation.
    * `ordered`: Plays files in a specific sequence.
    * `ordered-show`: Designed for episodic content (e.g., Casey Kasem, Rick Dees).
    * `commercial`: Specialized logic for advertisement breaks.
* **Dynamic Background "Beds"**: Automatically plays background music/ambience under banter or news clips.
* **Weighted Rotation**: Assign probabilities to specific folders (e.g., 50% Pop, 20% Rock).
* **Advanced Scheduling**: Support for "shifts," date ranges (Halloween/Christmas), and "chance" equations for gradual content introduction.

## Prerequisites

* **Hardware**: Raspberry Pi (designed for `omxplayer` hardware acceleration).
* **OS**: Legacy Raspberry Pi OS (supporting Python 2.7 and OMXPlayer).
* **Dependencies**:
    * `omxplayer-wrapper` (Python library)
    * `dbus`
    * A local backend server (listening on `127.0.0.1`) to handle `balanced` and `ordered` logic.

## Customization (settings.json)

The system is highly data-driven. To set up your station:

1. **Define Paths**: Map your drives (e.g., `%D[1]%`, `%D[2]%`) to your local mount points.
2. **Configure Shifts**: Define your DJ or programming blocks in the `vars` section.
3. **Build Formats**: Create `radio` format arrays to chain elements together:
   * **Commercials** (Looping)
   * **Jingles** (Random)
   * **Banter** (Balanced with a "Bed" track)
   * **Music** (Weighted selection)

## Plugin Integration

The plugin registers itself with the main engine and requires access to specific shared functions provided by the host application, such as `eval_equation` for processing logic and `replace_all_special_words` for path parsing.

## License
This project is provided "as-is" for radio enthusiasts and hobbyists.
