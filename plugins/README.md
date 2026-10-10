# Plugin Installation Guide

Plugins add custom playback handlers, keywords, and schedule behaviors to the TV station emulator.

You can install plugins in two ways: through the **Station Management Web UI** using packaged plugin bundles, or manually by copying Python source files to your station's **plugins directory**.

---

## Method 1: Web UI Installation (`.tvplugin`)

Packaged plugins use the `.tvplugin` archive format (a zip bundle containing the plugin files and assets).

1. Open the station's web interface in your browser.
2. Navigate to **Station Management**.
3. Locate the **Plugins** card.
4. Click **Upload** (or drag and drop) and select your `.tvplugin` file.
5. The station server will automatically unpack and place the plugin files into the configured plugins directory.
6. The emulator will register the plugin immediately or on the next loop cycle.

---

## Method 2: Manual Installation from Source (`.py`)

If you have individual Python source files (such as `picture-in-picture.py`):

1. Check your `settings.json` file to identify your plugins folder path:
   ```json
   {
       "plugins directory": "/home/pi/tvstation/plugins"
   }
   ```
2. Copy the `.py` plugin file into that directory:
   ```bash
   cp my-plugin.py /path/to/plugins/
   ```
3. Ensure file permissions allow the emulator to read the file:
   ```bash
   chmod 664 /path/to/plugins/my-plugin.py
   ```
4. The station will automatically discover and load any `.py` file containing valid `handle()` and `keywords` definitions. If `settings.json` is modified or saved, the emulator automatically calls `refresh_plugins()`.

---

## Verifying Installation

* Check the terminal output or station logs for:
  ```text
  Loaded plugin: <plugin_name>
  ```
* Once loaded, you can use the plugin's registered keyword as a `"type"` in your `settings.json` schedule blocks.
