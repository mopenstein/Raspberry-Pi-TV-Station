# MetaData
#
# name: Picture-in-Picture Plugin
# version: 1.6
# version date: 2026.10.04
#
# description: A plugin to handle PiP playback ("pip") keyword
#	Spawns a primary video and an optional overlay with defined
#	coordinates/presets, audio ducking, alpha, delay, duration,
#	interval wait time between loops, and loop controls.
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
    "kill_omxplayer", "report_video_playback"
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

def resolve_target_file(base_dir, node_def):
	"""
	Resolves a video file from a source definition node.
	Handles absolute paths starting with '@' or paths relative to the schedule root.
	"""
	source_list = node_def.get("source")
	if not source_list:
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
	"""
	Converts volume into OMXPlayer command line arguments (clamped 0 to 10).
	0 completely disables audio hardware (-n -1).
	10 represents 0mB (unity gain / maximum volume).
	"""
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
	"""
	Resolves position into a 4-coordinate bounding box string "x1 y1 x2 y2".
	Supports [x1, y1, x2, y2] arrays or presets:
	'top-right', 'top-left', 'bottom-right', 'bottom-left', 'center'.
	"""
	if isinstance(pos_def, list) and len(pos_def) == 4:
		return "{} {} {} {}".format(pos_def[0], pos_def[1], pos_def[2], pos_def[3])

	try:
		margin = int(margin_def)
	except (ValueError, TypeError):
		margin = 20

	preset = str(pos_def).lower().strip() if pos_def else "bottom-right"

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

	# 1. Overlay loop evaluation
	overlay_enabled = False
	remaining_loops = -1

	if overlay_meta:
		raw_loop = overlay_meta.get("loop", -1)
		if raw_loop is None:
			remaining_loops = -1
		else:
			try:
				remaining_loops = int(raw_loop)
			except (ValueError, TypeError):
				remaining_loops = -1

		if remaining_loops != 0:
			overlay_enabled = True

	# 2. Resolve primary source file
	primary_source = resolve_target_file(base_folder, primary_meta)
	if not primary_source:
		functions["report_error"]("PIP_FILE_ERROR", ["Failed to resolve primary video source", primary_meta])
		return [True, None]

	# 3. Audio & timing parameters
	primary_vol = min(float(primary_meta.get("volume", 10)), 10.0)
	duck_vol = None
	overlay_vol = 0
	overlay_delay = 0
	overlay_wait = 0
	overlay_per_play_duration = None
	overlay_max_total_duration = None
	pip_args = []

	if overlay_enabled:
		if "duck-primary" in overlay_meta:
			try:
				duck_vol = min(float(overlay_meta["duck-primary"]), 10.0)
			except (ValueError, TypeError):
				duck_vol = None

		raw_delay = overlay_meta.get("delay", overlay_meta.get("start-offset", 0))
		try:
			overlay_delay = max(0.0, float(raw_delay))
		except (ValueError, TypeError):
			overlay_delay = 0.0

		raw_wait = overlay_meta.get("wait", overlay_meta.get("sleep", 0))
		try:
			overlay_wait = max(0.0, float(raw_wait))
		except (ValueError, TypeError):
			overlay_wait = 0.0

		raw_duration = overlay_meta.get("duration")
		if raw_duration is not None:
			try:
				overlay_per_play_duration = max(0.1, float(raw_duration))
			except (ValueError, TypeError):
				overlay_per_play_duration = None

		raw_max_duration = overlay_meta.get("max-duration", overlay_meta.get("max-runtime", None))
		if raw_max_duration is not None:
			try:
				overlay_max_total_duration = max(0.1, float(raw_max_duration))
			except (ValueError, TypeError):
				overlay_max_total_duration = None

		pos_val = overlay_meta.get("position", "bottom-right")
		margin_val = overlay_meta.get("margin", 20)
		coord_str = resolve_position_coords(pos_val, margin_val)

		alpha_val = overlay_meta.get("alpha")
		alpha_args = []
		if alpha_val is not None:
			try:
				clamped_alpha = max(0, min(255, int(alpha_val)))
				alpha_args = ["--alpha", str(clamped_alpha)]
			except (ValueError, TypeError):
				pass

		overlay_vol = min(float(overlay_meta.get("volume", 0)), 10.0)
		pip_args = ["--no-osd", "--layer", "2", "--win", coord_str] + alpha_args + get_audio_args(overlay_vol)

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

		# Schedule initial spawn
		if overlay_enabled:
			overlay_next_spawn_time = start_time + overlay_delay

		primary_len = functions["get_length_from_file"](primary_source)

		while True:
			now = time.time()
			current_elapsed = now - start_time

			if functions["get_setting"](["debug"], False):
				dbg_limit = int(functions["get_setting"](["debug positon"], 999999))
				if current_elapsed > dbg_limit:
					break

			try:
				curr_pos = main_player.position()
			except Exception:
				break

			if primary_len and current_elapsed >= (primary_len + 1):
				break

			# Check total max-duration ceiling
			if overlay_enabled and overlay_max_total_duration and overlay_spawn_time:
				if (now - overlay_spawn_time) >= overlay_max_total_duration:
					overlay_enabled = False
					if overlay_player is not None:
						try:
							overlay_player.quit()
						except Exception:
							pass
						overlay_player = None
					if is_ducked:
						try:
							clamped_restore = min(max(0.1, primary_vol), 10.0)
							millibels = int(round(2000.0 * math.log10(clamped_restore / 10.0)))
							main_player.set_volume(pow(10, millibels / 2000.0))
							is_ducked = False
						except Exception:
							pass

			# Spawn overlay when idle and the wait/delay timer has passed
			if overlay_enabled and overlay_player is None and (remaining_loops == -1 or remaining_loops > 0):
				if now >= overlay_next_spawn_time:
					overlay_source = resolve_target_file(base_folder, overlay_meta)
					if overlay_source:
						if duck_vol is not None and not is_ducked:
							try:
								clamped_duck = min(max(0.1, duck_vol), 10.0)
								millibels = int(round(2000.0 * math.log10(clamped_duck / 10.0)))
								main_player.set_volume(pow(10, millibels / 2000.0))
								is_ducked = True
							except Exception:
								pass

						pip_id = random.randint(1000, 9999)
						overlay_player = OMXPlayer(overlay_source, args=pip_args, dbus_name="omxplayer.pip_{}".format(pip_id))
						if not overlay_spawned:
							overlay_spawned = True
							overlay_spawn_time = now
						overlay_loop_start_time = now

						if remaining_loops > 0:
							remaining_loops -= 1
					else:
						overlay_enabled = False

			# Supervise active overlay playback
			if overlay_player is not None:
				cycle_next_loop = False
				try:
					overlay_pos = overlay_player.position()
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

					# Restore primary audio during inter-loop intervals
					if is_ducked:
						try:
							clamped_restore = min(max(0.1, primary_vol), 10.0)
							millibels = int(round(2000.0 * math.log10(clamped_restore / 10.0)))
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
		for p in [overlay_player, main_player]:
			if p is not None:
				try:
					p.quit()
				except Exception:
					pass

	return [True, primary_source]