# MetaData
#
# name: Radio Station Plugin
# version: 0.1
# version date: 2026.02.09
#
# description: A plugin to handle radio playback keywords \
#	"audio" - plays a random audio file from a specified folder \
#	"radio" - plays a radio program based on a specified format with support for random, balanced, and ordered playback
#
# EndMetaData
#
#
# Must be placed in the plugins directory specified in settings.json




from dbus.exceptions import DBusException
from omxplayer import OMXPlayer # the video player
from time import sleep			# used to give time for the player to load the video file
from datetime import datetime	# used for timestamping
import urllib					# used for URL encoding
import time						# used for timing
import threading				# used for delayed cleanup
import random					# choosing random stuff
import traceback				# for error reporting
import os
import random

AUDIO_EXTENSIONS = ('mp3', 'm4a', 'aac', 'wav', 'flac', 'ogg', 'wma')	# audio file extensions that are considered valid audio files

# registered keywords that this plugin can handle
keywords = ["audio", "radio"]

# functions that the main program must provide
requested_functions = ["printd", "open_url", "get_setting", "report_error", "eval_equation", "report_file_not_found", "report_debug", "report_video_playback", "get_length_from_file", "kill_omxplayer", "ensure_string", "get_folders_from_dir", "get_files_from_dir", "weighted_random_choice", "replace_all_special_words", "get_videos_from_dir_cached", "is_special_time"]

functions = {} # functions passed from main program to plugin 

global_settings = {} # settings passed from main program

def register(name, func):
	global functions
	functions[name] = func

def refresh(settings):
	global global_settings
	global_settings = settings

def load(settings):
	global global_settings
	global_settings = settings

def check_paths(format_paths):
	"""
	Checks format_paths and returns:
	- string unchanged if input is a string
	- list unchanged if input is a list of strings
	- 'name' (string or list) if input is a list of dicts and conditions pass

	:param format_paths: string, list of strings, or list of dicts
	:return: string, list of strings, or None
	"""

	# case 1: single string
	if isinstance(format_paths, basestring):  # Python 2 safe
		return functions["replace_all_special_words"](format_paths) # return path with special words replaced

	# case 2: list
	if isinstance(format_paths, list):
		# if all entries are strings return list unchanged
		if all(isinstance(entry, basestring) for entry in format_paths):
			#return format_paths
			return [functions["replace_all_special_words"](entry) for entry in format_paths] # return list with special words replaced

		# if entries are dicts check each until one passes
		for entry in format_paths:
			if isinstance(entry, dict):
				name = entry.get("name")
				if not name:
					continue

				# special condition
				if "special" in entry and not functions["is_special_time"](entry["special"]):
					continue

				if "chance" in entry:
					chance_eval = functions["eval_equation"](entry['chance'])
					chance_rnd = random.random()
					functions["printd"]("Chance Eval", chance_eval, "rnd", chance_rnd, "allow_chance", "uneval", entry['chance'])
					if chance_rnd > float(chance_eval):
						continue

				# all conditions passed return name (string or list)
				return functions["replace_all_special_words"](name)

		# no dict passed
		return None

	# fallback
	return None

def handle(keyword, programming_schedule):
	global keywords
	global functions
	global global_settings
	global AUDIO_EXTENSIONS
	if keyword not in keywords:
		functions["printd"]("Keyword", keyword, "not registered in plugin.")
		return [False, None]

	if keyword == "audio":
		functions["printd"]("Handling keyword", keyword, "in plugin.")
		folders = programming_schedule[0] # returns all subfolders of a directory
		functions["printd"]("Selected folder for audio playback:", folders)
		selfolder = functions["replace_all_special_words"](random.choice(folders)) # choose a random one
		functions["printd"]("Selected folder for audio playback:", selfolder)
		files = functions["get_files_from_dir"](selfolder, AUDIO_EXTENSIONS) # preload all the files in that directory
		source = random.choice(files) # choose a random file from that directory

		if not files:
			functions["printd"]("No audio files found in folder:", selfolder)
			functions["report_error"]("RADIO PLAYBACK ERROR", ["No audio files found in folder:", selfolder])
			return [True, None]

		play_file(source) # play the audio file
		return [True, source]
	elif keyword == "radio":
		functions["printd"]("Handling keyword", keyword, "in plugin.")

		if 'format' in programming_schedule[4]:
			functions["printd"]("Formats:", programming_schedule[4]["format"])
			raw_format = programming_schedule[4]["format"]

			if isinstance(raw_format, list) and all(isinstance(item, list) for item in raw_format):
				format = random.choice(raw_format)
			else:
				format = raw_format
		else:
			functions["printd"]("No format specified in schedule")
			functions["report_error"]("RADIO PLAYBACK ERROR", ["No format specified in schedule"])
			return [True, None]

		folder = programming_schedule[0]
		base_folder = functions["replace_all_special_words"](folder[0])
		functions["printd"]("Base folder:", base_folder, "programming schedule folder:", programming_schedule)

		filter_string = ','.join(AUDIO_EXTENSIONS)
		dircontents = {}

		for entry in format:
			functions["printd"]("Processing format entry:", entry)

			if isinstance(entry, list):
				functions["printd"]("Standalone list detected - skipping:", entry)
				continue

			if isinstance(entry, dict):
				format_paths = entry.get("path")
				format_type = entry.get("type", "random")
				bed_folder_name = entry.get("bed")
				loop_count = entry.get("loop", 1)
				weights = entry.get("weight")
				cut = entry.get("cut", 3)
			else:
				format_paths = entry
				format_type = "random"
				bed_folder_name = None
				loop_count = 1
				cut = 3
				weights = None

			format_paths = check_paths(format_paths) # process special conditions
			functions["printd"]("bed files before check_paths:", bed_folder_name)
			if bed_folder_name: # process bed files if specified
				bed_folder_name = check_paths(bed_folder_name)
			functions["printd"]("bed files after check_paths:", bed_folder_name)
			if not isinstance(format_paths, list) and not isinstance(format_paths, basestring):
				functions["printd"]("Invalid format path type skipping:", format_paths)
				continue

			for loop_index in range(loop_count):
				if isinstance(format_paths, list):
					if weights:
						if len(format_paths) != len(weights):
							functions["printd"]("Mismatch: path count vs weight count")
							functions["report_error"]("RADIO FORMAT ERROR", ["Mismatch between path and weight count", str(format_paths), str(weights)])
							continue
						selected_dir = functions["weighted_random_choice"](format_paths, weights)
					else:
						selected_dir = random.choice(format_paths)
				else:
					selected_dir = format_paths

				#selected_folder = base_folder + "/" + selected_dir + "/"
				# if first character is @, we do not use the base_folder. We trim the @ and use the rest as absolute path
				if selected_dir[0:1] == "@":
					selected_dir = selected_dir[1:]
					selected_folder = selected_dir + "/"
				else:
					selected_folder = base_folder + "/" + selected_dir + "/"


				try:
					if format_type == "random" or format_type == "commercial":
						dircontents[selected_dir] = functions["get_files_from_dir"](selected_folder, AUDIO_EXTENSIONS)
						if not dircontents[selected_dir]:
							print("No files found in folder:", selected_folder)
							break
						source = random.choice(dircontents[selected_dir])
						first_time = "N/A"
						comment = "Random selection"
					elif format_type == "balanced":
						url = "http://127.0.0.1/?" + urllib.urlencode({ 'get_next_rnd_episode': selected_folder, 'filter': filter_string })
						urlcontents = functions["open_url"](url)
						parts = urlcontents.split("|")
						if len(parts) >= 3:
							source = parts[0].strip()
							first_time = parts[1].strip()
							comment = parts[2].strip()
						else:
							functions["printd"]("Unexpected response format:", urlcontents)
							functions["report_error"]("RADIO PLAYBACK ERROR", ["Malformed response from server", urlcontents])
							break

						if first_time == "0": # indicates no valid file found
							functions["printd"]("Backend failed to return a valid file:", comment)
							functions["report_error"]("RADIO PLAYBACK ERROR", ["No valid file found", comment, programming_schedule[4]])
							return [True, None]

						if not os.path.isfile(source):
							functions["printd"]("Selected 'balanced' file does not exist:", source)
							continue

					elif format_type == "ordered":
						url = "http://127.0.0.1/?" + urllib.urlencode({ 'get_next_episode': selected_folder, 'filter': filter_string })
						urlcontents = functions["open_url"](url)
						parts = urlcontents.split("|")
						if len(parts) >= 3:
							source = parts[0].strip()
							first_time = parts[1].strip()
							comment = parts[2].strip()
						else:
							functions["printd"]("Unexpected response format:", urlcontents)
							functions["report_error"]("RADIO PLAYBACK ERROR", ["Malformed response from server", urlcontents])
							break
						
						if not os.path.isfile(source):
							functions["printd"]("Selected 'ordered' file does not exist:", source)
							functions["report_file_not_found"](source)
							continue

					elif format_type == "ordered-show":
						dircontents[selected_dir] = functions["get_files_from_dir"](selected_folder, AUDIO_EXTENSIONS)
						if not dircontents[selected_dir]:
							print("No files found in folder:", selected_folder)
							break
						source = random.choice(dircontents[selected_dir])

						url = "http://127.0.0.1/?" + urllib.urlencode({ 'getavailable': source, 'dir': selected_folder })
						urlcontents = functions["open_url"](url)
						functions["printd"]("Available episodes response:", urlcontents)
						parts = urlcontents.split("\n")
						if len(parts) >= 0:
							selfolder = random.choice(parts).strip()

							url = "http://127.0.0.1/?" + urllib.urlencode({ 'get_next_episode': selfolder, 'filter': filter_string })
							urlcontents = functions["open_url"](url)
							functions["printd"]("Next episode response:", urlcontents)
							parts = urlcontents.split("|")
							if len(parts) >= 3:
								source = parts[0].strip()
								first_time = parts[1].strip()
								comment = parts[2].strip()
							else:
								functions["printd"]("Unexpected response format:", urlcontents)
								functions["report_error"]("RADIO PLAYBACK ERROR - ordered-show A", ["Malformed response from server", urlcontents])
								break

							if not os.path.isfile(source):
								functions["printd"]("Selected 'ordered-show' file does not exist:", source)
								continue

						else:
							functions["printd"]("Unexpected response format:", urlcontents)
							functions["report_error"]("RADIO PLAYBACK ERROR - ordered-show B", ["Malformed response from server", urlcontents])
							break
					else:
						functions["printd"]("Unknown format type:", format_type)
						break

					functions["printd"]("Selected file:", source)
					functions["printd"]("First time played:", first_time)
					functions["printd"]("Comment:", comment)

					duration = functions["get_length_from_file"](source)
					if not duration:
						functions["printd"]("Could not determine duration of main file:", source)
						functions["report_error"]("RADIO ORDERED-SHOW", "could not determine duration of file", source)
						break

					if bed_folder_name and isinstance(bed_folder_name, basestring):
						bed_folder = base_folder + "/" + bed_folder_name + "/"
						bed_files = functions["get_files_from_dir"](bed_folder, AUDIO_EXTENSIONS)
						functions["printd"]("Bed files found:", bed_files, "in folder:", bed_folder)
						if bed_files:
							bed_source = random.choice(bed_files)
							functions["printd"]("Launching bed file:", bed_source)
							play_file(bed_source, end_early=0, stop_at=duration, blocking=False)
						else:
							functions["printd"]("No bed files found in:", bed_folder)

					retries = 0
					while retries < 3:
						print("Playing main file:", source, "with cut:", cut)
						file_type="video"
						if format_type == "commercial":
							file_type="commercial"
						if play_file(source, vtype=file_type, end_early=cut, stop_at=-1):
							break
						else:
							print("Playback failed for:", source, "Retrying...")
							retries += 1
					if retries == 3:
						functions["report_error"]("RADIO PLAYBACK ERROR", ["Playback failed after 3 attempts", "SOURCE", source])

				except Exception as e:
					functions["printd"]("Error during radio playback:", e)
					functions["report_error"]("RADIO PLAYBACK ERROR", ["Playback error", str(e)])
					break

		return [True, source]

players = []			# List of OMXPlayer instances
player_count = 0

def kill_players():
	global players
	while len(players) > 4:
		try:
			old = players.pop(0)
			try:
				old.quit()
				functions["printd"]("Old player quit cleanly.")
			except Exception as e:
				functions["printd"]("Quit failed, trying exit:", str(e))
				try:
					old.exit()
					functions["printd"]("Old player exited cleanly.")
				except Exception as e2:
					functions["printd"]("Exit also failed:", str(e2))
		except Exception as outer:
			functions["printd"]("Failed to pop player:", str(outer))

import threading

def play_file(source, vtype="video", end_early=0, stop_at=-1, blocking=True):
	if source is None:
		return

	if not os.path.isfile(source):
		functions["printd"]("Source file does not exist:", source)
		functions["report_error"]("RADIO PLAYBACK ERROR", ["Source file missing", "SOURCE", source])
		return False

	global players, functions, global_settings, player_count

	try:
		duration = functions["get_length_from_file"](source)
		if not duration:
			functions["printd"]("Could not determine duration.")
			functions["report_error"]("RADIO PLAY_FILE", ["Could not determine duration", "SOURCE", source])
			return

		kill_players()
		player_count += 1
		if player_count > 100:
			player_count = 0
		index = player_count
		dbus_name = "omxplayer.player" + str(index)

		try:
			print("Starting OMXPlayer with DBUS name:", dbus_name, "player settings:", global_settings["player_settings"], "for source:", source, "vtype:", vtype)
			player = OMXPlayer(source, args=global_settings["player_settings"], dbus_name=dbus_name)
			players.append(player)
		except Exception as e:
			functions["kill_omxplayer"]()
			functions["report_error"]("RADIO PLAYBACK init ERROR", ["OMXPlayer init", "SOURCE", source, str(e), traceback.format_exc()])
			return False

		functions["report_video_playback"](source, vtype)
		functions["printd"](datetime.now().strftime("%H:%M:%S"), "Waiting for DBus to be ready...")
		time.sleep(2)
		functions["printd"](datetime.now().strftime("%H:%M:%S"), "DBus is ready.")

		if not blocking:
			if stop_at > 0:
				def delayed_stop(p, delay):
					time.sleep(delay)
					try:
						p.stop()
						functions["printd"]("Non-blocking player stopped after", delay, "seconds")
					except:
						pass
				threading.Thread(target=delayed_stop, args=(player, stop_at)).start()
			return True

		last_position = [0, 0]
		while True:
			try:
				# this a hack to catch occasional position errors from omxplayer
				try:
					position = player.position()
				except:
					functions["printd"]("Player position retrieval failed, assuming playback ended.")
					break

				functions["printd"](datetime.now().strftime("%H:%M:%S"), "Position:", position, "Duration:", duration)
				if functions["get_setting"](["debug"], False) and position > int(functions["get_setting"](["debug positon"], 9999999)):
					print("Debug mode active, stopping playback early.")
					player.stop()
					break
				if position == last_position[0] and last_position[1] >= 2:
					functions["printd"]("Position not advancing, assuming playback ended.")
					break
				if position >= stop_at and stop_at != -1:
					player.stop()
					break
				if duration - position <= end_early:
					break
			except Exception as e:
				functions["kill_omxplayer"]()
				functions["report_error"]("RADIO PLAYBACK position ERROR", ["player.position", "SOURCE", source, str(e), traceback.format_exc()])
				break
			last_position = [position, last_position[1] + 1]
			sleep(1)

		return True
	except Exception as e:
		functions["kill_omxplayer"]()
		functions["report_error"]("RADIO PLAYBACK ERROR", ["SOURCE", source, str(e), traceback.format_exc()])
		functions["printd"]("Playback error:", str(e))
		return False
