# MetaData
#
# name: Picture-in-Picture Plugin
# version: 2.0
# version date: 2026.10.10
#
# description: A plugin to handle PiP playback ("pip") keyword
#	Spawns a primary video and an optional overlay with defined
#	coordinates/presets/dict layout, audio ducking, alpha, delay, duration,
#	interval wait time between loops, and loop controls.
#	Renders explicit user geometry without artificial canvas clipping.
#   Synchronizes telemetry with the station state_manager API.
#
# EndMetaData
#
# Must be placed in the plugins directory specified in settings.json

import os
import math
import time
import random
import traceback
from omxplayer import OMXPlayer

VIDEO_EXTENSIONS = ('mp4', 'avi', 'webm', 'mpeg', 'm4v', 'mkv', 'mov', 'flv', 'wmv')

keywords = ["pip"]

requested_functions = [
    "printd", "report_error", "get_setting", "get_files_from_dir",
    "get_length_from_file", "replace_all_special_words",
    "kill_omxplayer", "report_video_playback",
    "api_update_main", "api_set_submedia", "api_clear_submedia"
]

functions = {}
global_settings = {}

def register(name, func):
	global functions
	functions[name] = func

def refresh(settings):
	global global_settings
	global_settings = settings

def load(settings):
	global global_settings
	global_settings = settings

def validate_param(value, min_val, max_val, default_val, param_name):
	if value is None:
		return default_val

	try:
		val = float(value)
	except (ValueError, TypeError):
		functions["report_error"]("PIP_CONFIG_WARNING", [
			"Non-numeric value for {}: '{}'".format(param_name, str(value)),
			"Reverting to default: {}".format(default_val)
		])
		return default_val

	if min_val is not None and val < min_val:
		functions["report_error"]("PIP_CONFIG_WARNING", [
			"Value for {} ({}) below minimum ({})".format(param_name, val, min_val),
			"Clamping to minimum: {}".format(min_val)
		])
		return min_val

	if max_val is not None and val > max_val:
		functions["report_error"]("PIP_CONFIG_WARNING", [
			"Value for {} ({}) above maximum ({})".format(param_name, val, max_val),
			"Clamping to maximum: {}".format(max_val)
		])
		return max_val

	return val

def resolve_target_file(base_dir, node_def):
	if not node_def or not isinstance(node_def, dict):
		functions["report_error"]("PIP_CONFIG_ERROR", ["Invalid node definition", str(node_def)])
		return None

	source_list = node_def.get("source")
	if not source_list:
		functions["report_error"]("PIP_SOURCE_MISSING", ["No source path provided in node", str(node_def)])
		return None

	if isinstance(source_list, basestring):
		source_list = [source_list]

	raw_dir = random.choice(source_list)
	raw_dir = functions["replace_all_special_words"](raw_dir)

	if raw_dir.startswith("@"):
		target_dir = raw_dir[1:]
	else:
		target_dir = os.path.join(base_dir, raw_dir)

	if not os.path.isdir(target_dir):
		functions["report_error"]("PIP_DIR_NOT_FOUND", ["Directory does not exist:", target_dir])
		return None

	files = functions["get_files_from_dir"](target_dir, VIDEO_EXTENSIONS)
	if not files:
		functions["report_error"]("PIP_EMPTY_DIR", ["No video files found in:", target_dir])
		return None

	sel_type = node_def.get("type", "random")
	if sel_type == "random":
		return random.choice(files)
	else:
		return files[0]

def get_audio_args(volume):
	if volume is None:
		return []

	try:
		vol = float(volume)
	except (ValueError, TypeError):
		return []

	if vol <= 0:
		return ["-n", "-1"]

	vol = min(vol, 10.0)
	millibels = int(round(2000.0 * math.log10(vol / 10.0)))
	return ["--vol", str(millibels)]

def resolve_position_coords(pos_def, margin_def=20, canvas_w=640, canvas_h=480, default_w=200, default_h=150):
	margin = int(validate_param(margin_def, 0, 200, 20, "margin"))

	if isinstance(pos_def, dict):
		try:
			x1 = int(pos_def.get("left", pos_def.get("x", 0)))
			y1 = int(pos_def.get("top", pos_def.get("y", 0)))
			w = int(pos_def.get("width", pos_def.get("w", default_w)))
			h = int(pos_def.get("height", pos_def.get("h", default_h)))

			x2 = x1 + w
			y2 = y1 + h
			return "{} {} {} {}".format(x1, y1, x2, y2)
		except (ValueError, TypeError):
			functions["report_error"]("PIP_CONFIG_WARNING", [
				"Invalid dictionary coordinates: {}".format(str(pos_def)),
				"Falling back to default preset 'bottom-right'"
			])

	if isinstance(pos_def, list):
		if len(pos_def) == 4:
			try:
				coords = [int(p) for p in pos_def]
				return "{} {} {} {}".format(coords[0], coords[1], coords[2], coords[3])
			except (ValueError, TypeError):
				functions["report_error"]("PIP_CONFIG_WARNING", [
					"Non-integer coordinate list: {}".format(str(pos_def)),
					"Falling back to default preset 'bottom-right'"
				])
		else:
			functions["report_error"]("PIP_CONFIG_WARNING", [
				"Coordinate list must contain exactly 4 values, got {}".format(len(pos_def)),
				"Falling back to default preset 'bottom-right'"
			])

	valid_presets = ["top-right", "top-left", "bottom-right", "bottom-left", "center"]
	preset = str(pos_def).lower().strip() if pos_def else "bottom-right"

	if preset not in valid_presets:
		functions["report_error"]("PIP_CONFIG_WARNING", [
			"Unknown position preset '{}'".format(preset),
			"Falling back to 'bottom-right'"
		])
		preset = "bottom-right"

	if preset == "top-right":
		x1 = canvas_w - default_w - margin
		y1 = margin
	elif preset == "top-left":
		x1 = margin
		y1 = margin
	elif preset == "bottom-left":
		x1 = margin
		y1 = canvas_h - default_h - margin
	elif preset == "center":
		x1 = (canvas_w - default_w) // 2
		y1 = (canvas_h - default_h) // 2
	else:
		x1 = canvas_w - default_w - margin
		y1 = canvas_h - default_h - margin

	x2 = x1 + default_w
	y2 = y1 + default_h
	return "{} {} {} {}".format(x1, y1, x2, y2)

def handle(keyword, programming_schedule):
	global functions, global_settings

	if keyword != "pip":
		return [False, None]

	meta = programming_schedule[4]
	folders = programming_schedule[0]
	base_folder = functions["replace_all_special_words"](folders[0])

	primary_meta = meta.get("primary")
	overlay_meta = meta.get("overlay")

	if not primary_meta:
		functions["report_error"]("PIP_CONFIG_ERROR", ["Missing primary config", meta])
		return [True, None]

	overlay_enabled = False
	remaining_loops = -1

	if overlay_meta:
		raw_loop = overlay_meta.get("loop", -1)
		if raw_loop is None:
			remaining_loops = -1
		else:
			try:
				remaining_loops = int(raw_loop)
				if remaining_loops < -1:
					remaining_loops = -1
			except (ValueError, TypeError):
				remaining_loops = -1

		if remaining_loops != 0:
			overlay_enabled = True

	primary_source = resolve_target_file(base_folder, primary_meta)
	if not primary_source:
		functions["report_error"]("PIP_PLAYBACK_ABORTED", ["Primary video resolution failed; playback canceled"])
		return [True, None]

	raw_prim_vol = primary_meta.get("volume", 10)
	primary_vol = validate_param(raw_prim_vol, 0.0, 10.0, 10.0, "primary volume")

	duck_vol = None
	overlay_vol = 0
	overlay_delay = 0
	overlay_wait = 0
	overlay_per_play_duration = None
	overlay_max_total_duration = None
	coord_str = None
	pip_args = []

	if overlay_enabled:
		if "duck-primary" in overlay_meta:
			raw_duck = overlay_meta.get("duck-primary")
			duck_vol = validate_param(raw_duck, 0.0, 10.0, None, "duck-primary")

		raw_delay = overlay_meta.get("delay", overlay_meta.get("start-offset", 0))
		overlay_delay = validate_param(raw_delay, 0.0, 86400.0, 0.0, "delay")

		raw_wait = overlay_meta.get("wait", overlay_meta.get("sleep", 0))
		overlay_wait = validate_param(raw_wait, 0.0, 86400.0, 0.0, "wait")

		raw_dur = overlay_meta.get("duration")
		if raw_dur is not None:
			overlay_per_play_duration = validate_param(raw_dur, 0.1, 86400.0, None, "duration")

		raw_max_dur = overlay_meta.get("max-duration", overlay_meta.get("max-runtime", None))
		if raw_max_dur is not None:
			overlay_max_total_duration = validate_param(raw_max_dur, 0.1, 86400.0, None, "max-duration")

		pos_val = overlay_meta.get("position", "bottom-right")
		margin_val = overlay_meta.get("margin", 20)
		coord_str = resolve_position_coords(pos_val, margin_val)

		alpha_args = []
		if "alpha" in overlay_meta:
			raw_alpha = overlay_meta.get("alpha")
			checked_alpha = int(validate_param(raw_alpha, 0, 255, 255, "alpha"))
			alpha_args = ["--alpha", str(checked_alpha)]

		raw_overlay_vol = overlay_meta.get("volume", 0)
		overlay_vol = validate_param(raw_overlay_vol, 0.0, 10.0, 0.0, "overlay volume")

		pip_args = [
			"--no-osd",
			"--layer", "2",
			"--aspect-mode", "stretch",
			"--win", coord_str
		] + alpha_args + get_audio_args(overlay_vol)

	initial_primary_vol = duck_vol if (overlay_enabled and overlay_delay == 0 and duck_vol is not None) else primary_vol
	main_args = list(functions["get_setting"](["player_settings"], ["--no-osd"])) + ["--layer", "1"] + get_audio_args(initial_primary_vol)

	main_player = None
	overlay_player = None
	overlay_spawned = False
	overlay_spawn_time = None
	overlay_loop_start_time = None
	overlay_next_spawn_time = 0
	is_ducked = (initial_primary_vol == duck_vol and duck_vol is not None)

	try:
		rnd_id = random.randint(1000, 9999)
		functions["report_video_playback"](primary_source, "video")

		main_player = OMXPlayer(primary_source, args=main_args, dbus_name="omxplayer.main_{}".format(rnd_id))
		start_time = time.time()

		if overlay_enabled:
			overlay_next_spawn_time = start_time + overlay_delay

		primary_len = functions["get_length_from_file"](primary_source)

		if "api_update_main" in functions:
			functions["api_update_main"](
				state="playing", media_type="video", file_path=primary_source, 
				position=0.0, volume=initial_primary_vol / 10.0, visible=True
			)

		while True:
			now = time.time()
			current_elapsed = now - start_time

			if functions["get_setting"](["debug"], False):
				dbg_limit = int(functions["get_setting"](["debug positon"], 999999))
				if current_elapsed > dbg_limit:
					break

			try:
				curr_pos = main_player.position()
				if "api_update_main" in functions:
					curr_vol = duck_vol if (is_ducked and duck_vol is not None) else primary_vol
					functions["api_update_main"](
						state="playing", media_type="video", file_path=primary_source, 
						position=curr_pos, volume=curr_vol / 10.0, visible=True
					)
			except Exception:
				break

			if primary_len and current_elapsed >= (primary_len + 1):
				break

			if overlay_enabled and overlay_max_total_duration and overlay_spawn_time:
				if (now - overlay_spawn_time) >= overlay_max_total_duration:
					overlay_enabled = False
					if overlay_player is not None:
						try:
							overlay_player.quit()
						except Exception:
							pass
						overlay_player = None
						if "api_clear_submedia" in functions:
							functions["api_clear_submedia"]("pip_plugin")
					if is_ducked:
						try:
							millibels = int(round(2000.0 * math.log10(max(0.1, primary_vol) / 10.0)))
							main_player.set_volume(pow(10, millibels / 2000.0))
							is_ducked = False
						except Exception:
							pass

			if overlay_enabled and overlay_player is None and (remaining_loops == -1 or remaining_loops > 0):
				if now >= overlay_next_spawn_time:
					overlay_source = resolve_target_file(base_folder, overlay_meta)
					if overlay_source:
						if duck_vol is not None and not is_ducked:
							try:
								millibels = int(round(2000.0 * math.log10(max(0.1, duck_vol) / 10.0)))
								main_player.set_volume(pow(10, millibels / 2000.0))
								is_ducked = True
							except Exception:
								pass

						pip_id = random.randint(1000, 9999)
						try:
							overlay_player = OMXPlayer(overlay_source, args=pip_args, dbus_name="omxplayer.pip_{}".format(pip_id))
							if not overlay_spawned:
								overlay_spawned = True
								overlay_spawn_time = now
							overlay_loop_start_time = now

							if "api_set_submedia" in functions:
								functions["api_set_submedia"](
									layer_id="pip_plugin", media_type="video", file_path=overlay_source, 
									position=0.0, volume=overlay_vol / 10.0, visible=True, coords=coord_str
								)

							if remaining_loops > 0:
								remaining_loops -= 1
						except Exception as oe:
							functions["report_error"]("PIP_OVERLAY_LAUNCH_ERROR", [str(oe), "Falling back: skipping overlay spawn"])
							overlay_player = None
							overlay_next_spawn_time = now + overlay_wait
					else:
						overlay_enabled = False

			if overlay_player is not None:
				cycle_next_loop = False
				try:
					overlay_pos = overlay_player.position()
					if "api_set_submedia" in functions:
						functions["api_set_submedia"](
							layer_id="pip_plugin", media_type="video", file_path=overlay_source, 
							position=overlay_pos, volume=overlay_vol / 10.0, visible=True, coords=coord_str
						)
					if overlay_per_play_duration and overlay_loop_start_time and (now - overlay_loop_start_time) >= overlay_per_play_duration:
						cycle_next_loop = True
				except Exception:
					cycle_next_loop = True

				if cycle_next_loop:
					try:
						overlay_player.quit()
					except Exception:
						pass
					overlay_player = None
					if "api_clear_submedia" in functions:
						functions["api_clear_submedia"]("pip_plugin")

					if is_ducked:
						try:
							millibels = int(round(2000.0 * math.log10(max(0.1, primary_vol) / 10.0)))
							main_player.set_volume(pow(10, millibels / 2000.0))
							is_ducked = False
						except Exception:
							pass

					if remaining_loops == -1 or remaining_loops > 0:
						overlay_next_spawn_time = time.time() + overlay_wait
					else:
						overlay_enabled = False

	except Exception as e:
		functions["report_error"]("PIP_PLAY_ERROR", [str(e), traceback.format_exc()])
		functions["kill_omxplayer"]()
		return [True, primary_source]

	finally:
		if "api_clear_submedia" in functions:
			functions["api_clear_submedia"]("pip_plugin")
		if "api_update_main" in functions:
			functions["api_update_main"](
				state="offline", media_type="video", file_path="", 
				position=0.0, visible=False
			)
		for p in [overlay_player, main_player]:
			if p is not None:
				try:
					p.quit()
				except Exception:
					pass

	return [True, primary_source]