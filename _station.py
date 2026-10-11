#!/usr/bin/python
# version: 102.31
# version date: 2026.09.22
#
#	Migrating to _station.py from _rnd80s.py
#	Fixed ownership issues with cache
#	Implemented a broadcast state management API to allow for thread-safe access to the current state of the video player from remote sources (e.g., web server)
#	Added new wrapper functions for state management api:
#		- api_update_main - Updates the main broadcast state (video/audio) in a thread-safe manner.
#		- api_set_override - Sets an override layer that can pause the main broadcast (e.g., for commercials).
#		- api_clear_override - Clears an override layer, allowing the main broadcast to resume.
#		- api_set_submedia - Sets an independent submedia layer (e.g., PiP, audio beds) alongside the main broadcast.
#		- api_clear_submedia - Clears an independent submedia layer.
#
# settings version: 0.996
#
#


#!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!#
# 		Do not expose your Raspberry Pi directly to the internet via port forwarding or DMZ.	 #
# 		This software is designed for local network use only.									 #
# 		Opening it up to the web will ruin your day.											 #
#!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!#

from omxplayer import OMXPlayer # the video player
from time import sleep			# used to give time for the player to load the video file
import math						# math functions
import os						# for path directory access
import random					# choosing random stuff
import time						# time functions
import datetime					# date and time, used for testing purposes
import urllib					# web stuff
import re						# regular expressions
import calendar					# used in special date calculating
import sys						# for accepting arguments from command line
import json						# settings file is in json format
import subprocess				# for rebooting the machine
import traceback				# for error reporting
import hashlib					# for generating hash IDs
import ast						# for safely evaluating mathematical expressions

from state_manager import broadcast_state	# for managing the broadcast state API in a thread-safe manner

# begin python 2/3 compatibility
try:
    # Python 3
    import urllib.request as urllib2
    import urllib.parse as urllib
    from urllib.parse import quote_plus, urlencode
except ImportError:
    # Python 2
    import urllib2
    import urllib
    from urllib import quote_plus, urlencode

# Compatibility shims for string types
if sys.version_info[0] >= 3:
    unicode = str
    basestring = str
    long = int

# Constants

SCRIPT_VERSION		= 102.31 # current version of the script
SETTINGS_VERSION	= 0.996 # the version of the "settings" this script supports

GET_VIDEOS_FROM_DIR_MIN_DURATION = 0 	 	# default minimum duration of a video in seconds when returning videos from a directory
GET_VIDEOS_FROM_DIR_MAX_DURATION = 99999 	# default maximum duration of a video in seconds when returning videos from a directory
VIDEO_EXTENSIONS = ('mp4', 'avi', 'webm', 'mpeg', 'm4v', 'mkv', 'mov', 'flv', 'wmv')	# video file extensions that are considered valid video files

# Allowed AST nodes for safe evaluation of mathematical expressions
ALLOWED_AST_NODES = (
    ast.Expression,
    ast.BinOp,        # +, -, *, /, %
    ast.UnaryOp,      # -x, +x
    ast.operator,     # Add, Sub, Mult, Div, etc.
    ast.unaryop,      # USub, UAdd
    ast.Num,          # Python 2 numbers (int, float)
    ast.Name,         # variable lookups like 'hour', 'day'
    ast.Call,         # function calls like sin(), clamp()
    ast.Compare,      # ==, !=, <, >, <=, >=
    ast.cmpop,        # comparison operators
    ast.IfExp,        # ternary 'x if cond else y'
)


PLUGINS = {} # dictionary of loaded plugins

# plugins will be standardized as such:
#   plugins will be in a user defined plugin directory defined in settings.json
#	plugins will be python files with a .py extension
#   they will register keywords of a "type"
#   the will handle the keywords with a handle() function

# /Constants

# Initialize the broadcast state API

broadcast_state.start() # Start the broadcast state API thread

# --- State Manager Plugin Wrappers ---
def api_update_main(state, media_type, file_path, position, volume=1.0, visible=True):
	broadcast_state.update_main(state, media_type, file_path, position, volume, visible)

def api_set_override(layer_id, media_type, file_path, position, volume=1.0, visible=True, **kwargs):
	broadcast_state.set_override(layer_id, media_type, file_path, position, volume, visible, **kwargs)

def api_clear_override(layer_id):
	broadcast_state.clear_override(layer_id)

def api_set_submedia(layer_id, media_type, file_path, position, volume=1.0, visible=True, **kwargs):
	broadcast_state.set_submedia(layer_id, media_type, file_path, position, volume, visible, **kwargs)

def api_clear_submedia(layer_id):
	broadcast_state.clear_submedia(layer_id)


# below is the ReferenceDecoder class used to decode the settings file and resolve $ref references within the settings file,
# allowing for more dynamic and reusable settings structures. It also keeps track of all objects it has decoded in a list 
# called "references" so that it can search through them when resolving $ref references.

class ReferenceDecoder(json.JSONDecoder):
	def __init__(self, *args, **kwargs):
		# Python 2 requires explicit class and self in super()
		super(ReferenceDecoder, self).__init__(object_hook=self.object_hook, *args, **kwargs)
		self.references = []

	def object_hook(self, obj):
		self.references.append(obj)
		for key, value in obj.items():
			if isinstance(value, basestring) and value.startswith("$ref/"):
				# expand special keywords first
				expanded = replace_all_special_words(value, skip_drive_replacement=True)
				# split into path segments
				oref = expanded.split("/")[1:]  # skip "$ref"

				for ref in self.references:
					if oref[0] in ref:
						target = ref
						try:
							for idx in oref:
								if isinstance(target, list) and is_number(idx):
									target = target[int(idx)]
								else:
									target = target[idx]
							obj[key] = target
						except Exception as e:
							report_error(
								"JSON_REF_DECODER",
								["error parsing variable in settings", e, str(key), str(value)]
							)
		return obj


def update_ram_state(state, file_path, position):
    """Atomically write the live player state to the RAM disk."""
    try:
        # Python 2.7 uses urllib.quote for URL encoding
        encoded_path = urllib.quote(file_path) if file_path else ""
        data = {
            "state": state,
            "file": encoded_path,
            "position": position
        }
        
        tmp_file = "/dev/shm/tv_status.tmp"
        final_file = "/dev/shm/tv_status.json"
        
        with open(tmp_file, "w") as f:
            json.dump(data, f)
            
        os.rename(tmp_file, final_file)
    except Exception as e:
        pass # Fail silently so it never crashes the video player

def refresh_plugins():
	"""
	Calls the refresh function of each loaded plugin, if it exists.
	:return: None
	"""
	global PLUGINS
	for name, module in PLUGINS.items():
		if hasattr(module, "refresh") and callable(module.refresh):
			module.refresh(settings)

def import_module_from_path(name, path):
	"""
	Python 2/3 helper to import a source file as a module.
	:param name: The name to assign to the imported module.
	:param path: The file path to the module source file.
	:return: The imported module object.
	"""
	if sys.version_info[0] >= 3:
		# Python 3 way (importlib)
		import importlib.util
		spec = importlib.util.spec_from_file_location(name, path)
		module = importlib.util.module_from_spec(spec)
		spec.loader.exec_module(module)
		return module
	else:
		# Python 2 way (imp)
		import imp
		return imp.load_source(name, path)

def load_plugins(plugin_dir):
	"""
	Loads plugins from the specified directory.
	:param plugin_dir: The directory from which to load plugins.
	:return: None
	"""
	global PLUGINS
	if not plugin_dir or not os.path.isdir(plugin_dir):
		return

	for filename in os.listdir(plugin_dir):
		if filename.endswith(".py"):
			name = filename[:-3]
			path = os.path.join(plugin_dir, filename)

			try:

				module = import_module_from_path(name, path)

				if not hasattr(module, "handle") or not callable(module.handle):
					report_error("PLUGIN_LOAD", [name, "missing required 'handle' function. Skipping."])
					continue
				if not hasattr(module, "keywords") or not isinstance(module.keywords, list):
					report_error("PLUGIN_LOAD", [name, "missing required 'keywords' list. Skipping."])
					continue

				PLUGINS[name] = module

				if hasattr(module, "load") and callable(module.load):
					module.load(settings)

				if hasattr(module, "requested_functions") and isinstance(module.requested_functions, list):
					if hasattr(module, "register") and callable(module.register):
						for req in module.requested_functions:
							printd("Injecting function", req, "into plugin", name)
							if req in globals() and callable(globals()[req]):
								module.register(req, globals()[req])

				print("Loaded plugin:", name)

			except Exception as e:
				print("Failed to load", name, "-", str(e))
				report_error("PLUGIN_LOAD", [name, str(e), traceback.format_exc()])

def wait_for_plugins(plugin_dir, timeout=30):
	"""
	Waits for plugins to be loaded from the specified directory.
	- If directory does not exist: returns False.
	- If directory exists and is empty: returns True silently.
	- If plugins exist: waits for PLUGINS to populate, returns True on success or False on timeout.

	:param plugin_dir: The directory to check for plugins.
	:param timeout: The maximum time to wait for plugins to load, in seconds. Default is 30 seconds.
	:return: True if loaded successfully or empty, False on failure/timeout/missing dir.
	"""
	if not plugin_dir or not os.path.isdir(plugin_dir):
		return False

	py_files = [f for f in os.listdir(plugin_dir) if f.endswith(".py")]
	if not py_files:
		return True

	start_time = time.time()
	while time.time() - start_time < timeout:
		load_plugins(plugin_dir)
		if PLUGINS:
			print("Plugins successfully loaded.")
			return True
		print("Waiting for plugins to load...")
		time.sleep(1)

	print("Plugin loading timed out after", timeout, "seconds.")
	return False

def printd(*args):
	"""
	Debug print function that only outputs messages if debug mode is enabled in the settings.

	:param args: The arguments to print.
	:return: None
	"""
	if get_setting(['debug'], False) == False:
		return
	print(' '.join(str(arg) if not isinstance(arg, list) else str(arg) for arg in args))
	print("")

def report_file_not_found(source):
	"""
	When a file is deleted but still exists in the database, the web server will still attempt to balance it with existing files.
	Reporting file not found errors helps identify missing files.

	:param source: The path to the missing balanced source file.
	:return: None
	"""
	url = "http://127.0.0.1/?" + urllib.urlencode({ 'returned_file_does_not_exists': source })
	urlcontents = open_url(url)
	

def report_video_playback(source, vtype="video"):
	"""
	Reports the currently playing video or commercial to the local web server for logging purposes. 
	Does nothing if debug mode is enabled.

	:param source: The path to the video or commercial file being played.
	:param type: A string indicating whether the source is a "video" or "commercial". Default is "video".

	:return: None
	"""

	global last_channel_name

	base_url = "http://127.0.0.1/"
	param = ""
	if vtype == "video":
		param = "current_video=" + urllib.quote_plus(ensure_string(source))
	elif vtype == "commercial":
		param = "current_comm=" + urllib.quote_plus(ensure_string(source))
	else:
		report_error("Report Video Playback", ["video OR commercial expected as vtype but gibberish was sent", vtype])
		return
	printd("report_video_playback", source, vtype, param)
	if param:
		if last_channel_name:
			param += "&channel=" + urllib.quote_plus(ensure_string(last_channel_name))
		printd("Reporting to web server:", base_url + "?" + param)
		contents = open_url(base_url + "?" + param)
		printd("report_video_playback", contents, source)
		return
	printd(["report_video_playback: no param to report", source, vtype])
	

def play_video(source, commercials, max_commercials_per_break, start_pos, bumpers=None, vtype="video"):
    """
    Play a video file using OMXPlayer on the Raspberry Pi.
    
    This is the main video playing experience.

    :param source: The path to the video file to be played.
    :param commercials: A list of commercial break times in seconds.
    :param max_commercials_per_break: The maximum number of commercials to play during a break.
    :param start_pos: The position in seconds to start the video from.
    :param bumpers: A dictionary containing 'in' and 'out' bumper video file paths (optional).

    :return: None
    """
    if os.path.splitext(source)[1].lower() == ".commercials":
        report_error("PLAY_LOOP", ["Error", "Commercial file supplied as video file. Something aint right...", "SOURCE", str(source)])
        return 2

    global last_played_video_source 
    comm_source = None 
    if source==None: 
        return 2
    
    err_pos = 0.0
    try:

        current_position = 0 
        gend_commercials = None 
        
        if type(max_commercials_per_break) == list: 
            gend_commercials = max_commercials_per_break
            if len(gend_commercials)!=0 and len(commercials)!=0:
                max_commercials_per_break = spread_division(len(gend_commercials), len(commercials))
            
            printd("MAXCOMM: " + ensure_string(max_commercials_per_break))
        else:
            if max_commercials_per_break>0:
                comm_source = get_random_commercial()
                if comm_source == None: 
                    max_commercials_per_break = [] 
                    report_error("PLAY_COMM", ["could not get a random commercial"])
                else:
                    tmp = max_commercials_per_break
                    max_commercials_per_break = [tmp] * 100

        comm_player = None 
        
        print('Main video file:' + ensure_string(source))
        print("")

        report_video_playback(source, vtype)    
        player_args = get_setting(["player_settings"], []) + ["--layer", "1"] 
        player = OMXPlayer(source, args=player_args, dbus_name="omxplayer.player" + str(random.randint(0,999))) 
        sleep(0.5) 

        player.seek(start_pos) 
        
        # [API] Register the initial playback state before entering the loop
        api_update_main(state="playing", media_type=vtype, file_path=source, position=start_pos, visible=True)

        while (1):
            err_pos = 1.0
            last_played_video_source = source 
            try: 
                current_position = player.position()
                # [API] Update main playhead position
                api_update_main(state="playing", media_type=vtype, file_path=source, position=current_position, visible=True)
            except: 
                break
            
            try:
                if commercials and max_commercials_per_break:
                    if current_position > 0 and float(current_position) >= float(commercials[0]):
                        commercials.pop(0)  

                        try:
                            player.mute()
                            player.hide_video()
                            player.pause()

                            # [API] Flag main video as paused and hidden
                            api_update_main(state="paused", media_type=vtype, file_path=source, position=current_position, visible=False)
                        except Exception:
                            printd("player hide/pause error")

                        sleep(0.5)

                        commercials_per_break = [] 
                        if bumpers and bumpers.get("out"): 
                            commercials_per_break.append(bumpers["out"].pop(0)) 

                        for i in range(0, max_commercials_per_break[0]):
                            commercials_per_break.append(gend_commercials.pop(0) if gend_commercials else get_random_commercial())

                        max_commercials_per_break.pop(0) 
                        
                        if bumpers and bumpers.get("in"): 
                            commercials_per_break.append(bumpers["in"].pop(0)) 

                        comm_i = len(commercials_per_break) - 1 
                        err_pos = 2.0
                        
                        while(comm_i>=0): 
                            try:
                                if gend_commercials == None: 
                                    comm_source = get_random_commercial() 
                                    if comm_source==None: 
                                        report_error("PLAY_COMM", ["could not get a random commercial"])
                                        continue
                                else: 
                                    if len(gend_commercials) == 0 and len(commercials_per_break) == 0: 
                                        break
                                    else: 
                                        comm_source = commercials_per_break.pop(0) 
                                        printd("Commercials remaining:", len(commercials_per_break))

                                last_played_video_source = comm_source 
                                print('Playing commercial #' + str(comm_i), comm_source) 
                                print("")
                                report_video_playback(comm_source, "commercial")
                                
                                if comm_player == None:
                                    comm_args = ["--no-osd", "--layer", "2"]
                                    comm_player = OMXPlayer(comm_source, args=comm_args, dbus_name="omxplayer.comm_player1")
                                    sleep(0.25) 
                                else:
                                    comm_player.load(comm_source)
                                
                                try:
                                    comm_player.show_video() 
                                except:
                                    printd("comm_player show error")
                                
                                comm_length = get_length_from_file(comm_source)     
                                comm_start_time = time.time()                       
                                comm_end_time = comm_start_time + comm_length + 1   
                                
                                while (1):
                                    if time.time() > comm_end_time: 
                                        print("Commercial should be over by now, moving on...")
                                        break

                                    if get_setting(['debug'], False) == True and time.time() - comm_start_time > int(get_setting(["debug positon"], 999999, int)):
                                        report_debug("COMM_PLAY_LOOP", ["Commercial has been playing for more than " + get_setting(["debug positon"],"-1") + " seconds, ending early"])
                                        comm_player.stop()
                                        break
                                        
                                    try: 
                                        comm_position = math.floor(comm_player.position())
                                        # [API] Overwrite the commercial block state with the active commercial
                                        api_set_override(layer_id="commercial_break", media_type="video", file_path=comm_source, position=comm_position, visible=True)
                                    except:
                                        break
                                    
                                    try:
                                        if player.is_playing() == True:
                                            player.hide_video()
                                            player.pause()
                                    except:
                                            printd("player hide/pause error")
                            except Exception as exce:
                                report_error("COMM_PLAY_LOOP", ["error", ensure_string(exce), "SOURCE", comm_source, traceback.format_exc()])
                            
                            comm_i = comm_i - 1
                            sleep(0.5) 
                            
                        # [API] Break is over, clear the override so the web player resumes the main feed
                        api_clear_override("commercial_break")
                        
                        player.unmute()
                        player.show_video() 
                        player.play() 
                        
            except Exception as ecce:
                report_error("COMM_PLAY", ["Error", ensure_string(ecce), "SOURCE", comm_source, traceback.format_exc()])
                # [API] Ensure the override is cleared even if the commercial block crashed
                api_clear_override("commercial_break")
                
                player.show_video()
                player.play()

        err_pos = 7.0
        player.hide_video()
        
        # [API] Video ended naturally
        api_update_main(state="offline", media_type="video", file_path="", position=0, visible=False) 
        sleep(0.5)
        
    except Exception as e:
        if(err_pos!=7.0):
            report_error("PLAY_LOOP", ["Error", ensure_string(e), "SOURCE", ensure_string(source), traceback.format_exc()])
            
        # [API] Video crashed or failed
        api_update_main(state="offline", media_type="video", file_path="", position=0, visible=False) 
        api_clear_override("commercial_break") # Failsafe clear
        
        kill_omxplayer()
        try:
            if comm_player != None:
                comm_player.quit()
        except Exception as ex:
            printd("error comm quit " + ensure_string(ex))
        try:
            if player != None:
                player.quit()
        except Exception as exx:
            printd("error player quit " + ensure_string(exx))
        
        if(err_pos!=7.0):
            return 0 

    try:
        if comm_player != None:
            comm_player.quit()
    except Exception as ex:
        report_error("COMM_quit", ["Position", ensure_string(err_pos), "Error", ensure_string(ex)])
    try:
        if player != None:
            player.quit()
    except Exception as exx:
        report_error("PLAY_quit",["Position", ensure_string(err_pos), "Error", ensure_string(exx)])
    
    return 1

def ensure_string(value):
	"""
	Converts a value to a string if it is not already a string.

	:param value: The value to convert.
	:return: The value as a string.
	"""
	if isinstance(value, unicode):
		return value.encode("utf-8")
	elif isinstance(value, str):
		return value
	else:
		try:
			return str(value)
		except Exception as e:
			report_error("ENSURE_STRING", ["Error converting value to string", str(e)])
			return ""

def convert_percentages(expr):
    """
    Converts percentage values in a mathematical expression to their decimal equivalents.
    
    :param expr: A string containing a mathematical expression with percentage values.
    :return: A string with percentage values converted to decimal.
    """

    def repl(match):
        num = float(match.group(1))
        return str(num / 100.0)

    # Match any number (int or float) followed by %
    return re.sub(r'(\d+(?:\.\d+)?)%', repl, expr)

def is_safe_equation(expr_str):
    try:
        tree = ast.parse(expr_str, mode='eval')
        for node in ast.walk(tree):
            if not isinstance(node, ALLOWED_AST_NODES):
                return False
            # Block calling unapproved attributes/methods
            if isinstance(node, ast.Attribute):
                return False
        return True
    except Exception:
        return False

def eval_equation(equation):
    """
    Tries to evaluate a mathematical equation string and return the result.
    """
    global now

    try:
        if len(equation) > 300:
            return -2

        # 1. Evaluate direct percentages first before passing to AST
        match = re.match(r'(\d+(?:\.\d+)?)%', equation.strip())
        if match and match.end() == len(equation.strip()):
            num = float(match.group(1))
            if 0.0 <= num <= 100.0:
                return float(num / 100.0)

        # 2. Validate syntax tree against sandbox escapes
        if not is_safe_equation(equation):
            return -3

        safe_globals = {
            "__builtins__": {},
            "sin": math.sin,
            "cos": math.cos,
            "tan": math.tan,
            "abs": abs,
            "min": min,
            "max": max,
            "round": round,
            "floor": math.floor,
            "ceil": math.ceil,
            "log": math.log,
            "exp": math.exp,
            "pi": math.pi,
            "e": math.e,
            "scale": lambda x: float(x) / 100,
            "clamp": lambda x, y=1.0: max(0.0, min(y, float(x))),
            "bound": lambda x, low, high: max(low, min(high, float(x))),
            "stamp": (now - datetime.datetime(1970, 1, 1)).total_seconds(),
            "date": lambda y, m, d: (datetime.datetime(int(y), int(m), int(d)) - datetime.datetime(1970, 1, 1)).total_seconds(),
            "time": lambda h, m=0, s=0: int(h) * 3600 + int(m) * 60 + int(s),
            "datetime": lambda y, mo, d, h=0, mi=0, s=0: (datetime.datetime(int(y), int(mo), int(d), int(h), int(mi), int(s)) - datetime.datetime(1970, 1, 1)).total_seconds(),
            "day": float(now.day),
            "maxdays": float(calendar.monthrange(now.year, now.month)[1]),
            "weekday": float(now.weekday()),
            "month": float(now.month),
            "hour": float(now.hour),
            "minute": float(now.minute),
            "second": float(now.second),
            "year": float(now.year)
        }
        
        return eval(equation, safe_globals, {})
    except:
        return -1

def get_setting(find, default=None, force_type=None):
	"""
	Searches the settings global variable for the specified setting and returns it.

	:param find: A list of keys/indices to traverse in the settings dictionary/list.
	:param default: The default value to return if the setting is not found. Set to None by default.
	:param force_type: If specified, attempts to convert the found value to this type (int or str). If conversion fails, returns default.

	:return: The value of the setting if found, otherwise the default value.
	"""	
	global settings
	try:
		temp = settings
		for i, k in enumerate(find):
			if temp is None:
				printd("GET_SETTING", "NoneType encountered at depth {}".format(i),
					"Key being accessed: {}".format(k),
					"Full path: {}".format(find))
				return default
			if isinstance(temp, dict):
				if k in temp:
					temp = temp[k]
				else:
					printd("GET_SETTING", "Missing key '{}' at depth {}".format(k, i),
						"Full path: {}".format(find))
					return default
			elif isinstance(temp, list):
				if isinstance(k, int) and 0 <= k < len(temp):
					temp = temp[k]
				else:
					printd("GET_SETTING", "Invalid list index '{}' at depth {}".format(k, i),
						"Full path: {}".format(find))
					return default
			else:
				printd("GET_SETTING", "Unsupported type '{}' at depth {}".format(type(temp), i),
					"Key: {}".format(k),
					"Full path: {}".format(find))
				return default

		# --- force_type enforcement ---
		if force_type is not None:
			if force_type == int:
				try:
					return int(temp)
				except (ValueError, TypeError):
					return default
			elif force_type == str:
				try:
					return str(temp)
				except (ValueError, TypeError):
					return default
			elif force_type == dict:
				return temp if isinstance(temp, dict) else default
			elif force_type == list:
				return temp if isinstance(temp, list) else default
			else:
				# unsupported force_type, just return default
				return default

		return temp
	except Exception as e:
		report_error("GET_SETTING", ["Unhandled exception", str(e), traceback.format_exc()])
		return default

def get_short_month_name(month_number):
	"""
	Returns the abbreviated name of the month for a given month number (1-12).
	
	:param month_number: An integer representing the month (1 for Jan, 2 for Feb, etc.).
	:return: The abbreviated name of the month (e.g., "Jan", "Feb", etc.).
	"""
	month_abbr = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"]
	if 1 <= month_number <= 12:
		return month_abbr[month_number - 1]
	else:
		raise ValueError("Month number must be in the range 1-12")

def readkey():
	"""
	Reads a single key press from the terminal without waiting for Enter to be pressed.

	:return: The character of the key pressed.
	"""
	import sys, tty, termios
	fd = sys.stdin.fileno()
	old = termios.tcgetattr(fd)
	try:
		tty.setraw(fd)
		return sys.stdin.read(1)
	finally:
		termios.tcsetattr(fd, termios.TCSADRAIN, old)

# Function to replace %MAXDAYS% with the correct number of days in the month
def replace_special_words(date_str):
	"""
	Replaces special placeholders in the input string with their corresponding values based on the current date and time.

	:param date_str: The input string containing placeholders to be replaced.
	:return: The input string with placeholders replaced by their corresponding values.
	"""
	global now

    # --- Custom Logic for %TOPTENSMIN% (00-05 or 30-35 minute triggers) ---
	minute_tens = now.strftime("%M")[0]
	top_tens_min_replacement = ""
    # Only allow substitution if the current minute starts with a '0' or '3'
	if minute_tens == '0' or minute_tens == '3':
		top_tens_min_replacement = minute_tens

	# Create a dictionary for placeholders and their replacement values
	replacements = {
		"%HOUR%": now.strftime("%I"),
		"%TENSHOUR%": now.strftime("%I")[0],
		"%UNITSHOUR%": now.strftime("%I")[1],
		"%AMPM%": now.strftime("%p"),
		"%MIN%": now.strftime("%M"),
		"%TENSMIN%": now.strftime("%M")[0],
		"%UNITSMIN%": now.strftime("%M")[1],
		"%TOPTENSMIN%": top_tens_min_replacement,
		"%MONTH%": get_short_month_name(now.month),
		"%DAY%": "{:02d}".format(now.day),
		"%YEAR%": str(now.year)
	}

	# Replace each placeholder with its corresponding value
	for key, value in replacements.items():
		if key in date_str:
			date_str = date_str.replace(key, value)

	if "%MAXDAYS%" in date_str:
		# Extract the month
		month_str = date_str.split(" ")[0]
		
		# Determine the year (current year)
		current_year = now.year
		
		# Find the last day of the month
		month_num = datetime.datetime.strptime(month_str, "%b").month
		next_month = datetime.datetime(current_year, month_num % 12 + 1, 1)
		max_days = (next_month - datetime.timedelta(days=1)).day
		
		# Replace %MAXDAYS% with the calculated max days
		date_str = date_str.replace("%MAXDAYS%", str(max_days))
	return date_str

def check_time_match(time_range_list, current_datetime):
    """
    Checks if current_datetime is within a single time range.
    
    :param time_range_list: A list containing [start_time_str, end_time_str].
    :param current_datetime: The datetime object to check against.
    :return: True if the current time is within the range, False otherwise.
    """
    # Defensive check: ensure we have a list of two strings/items
    if not isinstance(time_range_list, list) or len(time_range_list) < 2:
        return False

    # 1. Apply placeholders (like %HOUR%)
    start_time_str = replace_special_words(time_range_list[0])
    end_time_str = replace_special_words(time_range_list[1])
    current_time = current_datetime.time()

    try:
        # 2. Convert to time objects
        check_start_time = datetime.datetime.strptime(start_time_str, "%I:%M%p").time()
        end_time = datetime.datetime.strptime(end_time_str, "%I:%M%p").time()

        # 3. Perform comparison
        return check_start_time <= current_time <= end_time
    except ValueError:
        # Fails silently if a resulting time string is invalid
        return False


def is_within_range(data):
    """
    Checks if the current date and time fall within the specified ranges, 
    using AND/OR logic for time based on nested lists.

    :param data: A dictionary containing date, time, and year ranges.
    :return: True if the current date and time fall within the ranges, False otherwise.
    """
    global now # always use the global 'now' variable for date and time
    current_datetime = now
    
    # We will keep the original (OR) logic for dates and years as you intended.
    
    date_ranges = data.get("dates", [])
    time_ranges = data.get("times", [])
    year_ranges = data.get("years", [])

    # --- Check Year Ranges (Original OR Logic) ---
    year_within_range = True
    for year_range in year_ranges:
        year_within_range = False
        if replace_special_words(str(year_range)) == str(current_datetime.year):
            year_within_range = True
            break

    # --- Check Date Ranges (Original OR Logic) ---
    date_within_range = True
    for date_range in date_ranges:
        date_within_range = False
        if isinstance(date_range, list): # a list of dates [start, end]
            start_date_str = replace_special_words(date_range[0])
            end_date_str = replace_special_words(date_range[1])
            
            start_date = datetime.datetime.strptime(start_date_str + " " + str(now.year), "%b %d %Y")
            end_date = datetime.datetime.strptime(end_date_str + " " + str(now.year), "%b %d %Y")
            
            if start_date.date() <= current_datetime.date() <= end_date.date():
                date_within_range = True
                break
        else: # a single date
            start_date_str = replace_special_words(date_range)
            start_date = datetime.datetime.strptime(start_date_str + " " + str(now.year), "%b %d %Y")
            if start_date.date() == current_datetime.date():
                date_within_range = True
                break
    
    # --- Check Time Ranges (NEW Nested AND/OR Logic) ---
    if not time_ranges:
        time_within_range = True
    else:
        time_within_range = False
        
        # Outer Loop: OR Logic (Must match ANY top-level item)
        for top_level_item in time_ranges:
            
            current_item_matches = False
            
            # Case A: Nested AND Check (e.g., [ ['start', 'end'], ['start2', 'end2'] ] )
            if isinstance(top_level_item[0], list): 
                
                is_and_match = True
                for inner_range in top_level_item:
                    # Use the helper to check the inner condition
                    if not check_time_match(inner_range, current_datetime):
                        is_and_match = False
                        break # Failed the AND requirement
                
                if is_and_match:
                    current_item_matches = True

            # Case B: Simple OR Check (e.g., ['start', 'end'])
            else:
                if check_time_match(top_level_item, current_datetime):
                    current_item_matches = True
                    
            # Overall OR Break: If a match was found for this top-level item
            if current_item_matches:
                time_within_range = True
                break
    
    # Final Result: All conditions must be met
    return date_within_range and time_within_range and year_within_range

def calculate_fill_time(video_length, current_time, offset=0):
	"""
	Calculates the number of seconds needed to align a video's end time
	to the next half-hour or top-of-the-hour mark, rolling forward if
	the remaining time is less than 60 seconds.

	:param video_length: Length of the video in seconds.
	:param current_time: Current time as a datetime object.
	:return: Time in seconds required to reach the next interval.
	"""
	# Extract current hour, minute, and second
	curr_hour = current_time.hour
	curr_minute = current_time.minute
	curr_second = current_time.second
	total_seconds_past_hour = (curr_minute * 60) + curr_second  # Seconds past the last full hour

	# Determine next alignment point
	next_target_seconds = 1800 if total_seconds_past_hour < 1800 else 3600

	# Roll forward if necessary, ensuring we always land on a half-hour/full-hour boundary
	while total_seconds_past_hour + video_length >= next_target_seconds:
		next_target_seconds += 1800  # Move forward in 30-minute increments

	# Calculate the remaining time to fill
	fill_time = next_target_seconds - (total_seconds_past_hour + video_length)

	# If the remaining time is less than 60 seconds, roll forward again
	if fill_time < 60:
		next_target_seconds += 1800  # Move to the next half-hour/full-hour mark
		fill_time = next_target_seconds - (total_seconds_past_hour + video_length)

	return max(fill_time + offset, 0)  # Avoid negative values


# Part of "repeatedly" feature set by minimum-before-repeat.
# 	stores the last played time for files set to have minimum time between plays
# repeatedly_tracked_plays structure:
# {
#    "file": source,
#	"last_played": timestamp
# }
# Dictionary to track last played time and minimum cooldown

repeatedly_tracked_plays = {}

def repeatedly_generate_id_from_dict(data):
	"""
	Part of "repeatedly" feature set by minimum-before-repeat.
	Generate a deterministic hash ID from dictionary contents.

	:param data: A dictionary representing the item.
	:return: A string representing the generated ID.
	"""
	serialized = json.dumps(data, sort_keys=True)
	return hashlib.md5(serialized.encode('utf-8')).hexdigest()


def repeatedly_register_playable(entry, override=False, offset=0):
	"""
	Part of "repeatedly" feature set by minimum-before-repeat.
	Initialize tracking for a new item using its content-derived ID.

	:param entry: A dictionary representing the item to register.
	:param override: A boolean indicating whether to override existing tracking data (default is False).
	:param offset: A time offset in seconds to adjust the last played time (default is 0).
	:return: The generated ID for the item.
	"""
	global repeatedly_tracked_plays

	repeatedly_export_schedules_if_debug()

	raw_min = entry.get("minimum-before-repeat")

	if is_number(raw_min):
		minimum = raw_min
	elif isinstance(raw_min, list) and len(raw_min) == 2:
		start, end = raw_min
		if is_number(start) and is_number(end):
			minimum = random.randint(start, end)
		else:
			printd("Skipping registration: invalid range values")
			return
	else:
		printd("Skipping registration: minimum-before-repeat must be number or list of two numbers")
		return

	id = repeatedly_generate_id_from_dict(entry)
	if id not in repeatedly_tracked_plays or override:
		repeatedly_tracked_plays[id] = {
			"last_played": time.time() - offset,
			"minimum": float(minimum)
		}
	return id  # Optional: return for external use

def repeatedly_can_play(entry):
	"""
	Part of "repeatedly" feature set by minimum-before-repeat.
	Check if the item can be played again using its content-derived ID.

	:param entry: A dictionary representing the item to check.
	:return: True if the item can be played, False otherwise.
	"""
	global repeatedly_tracked_plays
	current_time = time.time()
	id = repeatedly_generate_id_from_dict(entry)
	entry_data = repeatedly_tracked_plays.get(id)
	if not entry_data:
		return True
	return (current_time - entry_data["last_played"]) >= entry_data["minimum"]

def repeatedly_reset():
	"""
	Part of "repeatedly" feature set by minimum-before-repeat.
	Resets the tracking data for all items.

	:return: None
	"""
	global repeatedly_tracked_plays
	repeatedly_tracked_plays = {}

import json
import time

def repeatedly_export_schedules_if_debug():
	"""
	Export the currently registered repeatedly schedules to a text file
	if debug mode is enabled.

	:param filename: Name of the file to export to (default: repeatedly_debug_export.txt)
	:return: None
	"""
	if get_setting(['debug'], False) is True:
		global repeatedly_tracked_plays, base_directory
		try:
			filename=base_directory + "repeatedly_debug_export.txt"
			with open(filename, "w", encoding="utf-8") as f:
				# Pretty-print with timestamps converted to human-readable
				export_data = {}
				for id, data in repeatedly_tracked_plays.items():
					export_data[id] = {
						"last_played": time.strftime(
							"%Y-%m-%d %H:%M:%S", time.localtime(data["last_played"])
						),
						"minimum": data["minimum"]
					}
				f.write(json.dumps(export_data, indent=4))
			printd("Repeatedly schedules exported to " + filename)
		except Exception as e:
			printd("Failed to export schedules: " + str(e))

def check_video_times(obj, channel=None, allow_chance=True):
	"""
	Checks the current time against a list of programming times and returns the first matching schedule.

	:param obj: A list of dictionaries containing programming times and conditions.
	:param channel: The channel to check against (optional).
	:param allow_chance: A boolean indicating whether to allow chance-based programming (default is True).
	:return: A list containing the programming schedule if a match is found, otherwise None.
	"""
	try:
		update_current_time()
		global now
		global current_video_tag

		month, now_h, now_m = now.month, now.hour, now.minute
		now_d, ddm, now_y = now.weekday(), now.day, now.year
		day_of_week = getDayOfWeek(now_d)

		for time_item in reversed(obj):
			is_static = [False, 0, ""]
			skip = dict.fromkeys([
				'month', 'date', 'dayOfWeek', 'time', 'special',
				'chance', 'between', 'channel', 'minimum'
			], False)

			video_type = time_item.get('type') or "video"

		 # Channel check
			if 'channel' in time_item:
				if time_item['channel'] is None and channel is None:
					pass
				elif time_item['channel'] in wildcard_array():
					pass
				elif time_item['channel'] != channel:
					continue

			# Minimum repeat check
			if time_item.get('minimum-before-repeat') is not None:
				min_repeat = time_item.get("minimum-before-repeat")
				if (
					(is_number(min_repeat)) or
					(isinstance(min_repeat, list) and len(min_repeat) == 2 and is_number(min_repeat[0]) and is_number(min_repeat[1]))
				) and not repeatedly_can_play(time_item):
					continue

			# Special time check
			if time_item.get('special') is not None:
				if not is_special_time(time_item['special']):
					continue

			# Chance check
			if 'chance' in time_item:
				chance_eval = eval_equation(time_item['chance'])
				chance_rnd = random.random()
				printd("Chance Eval", chance_eval, "rnd", chance_rnd, "allow_chance", allow_chance, "uneval", time_item['chance'])
				if chance_rnd > float(chance_eval) or not allow_chance:
					continue

			# Between check
			if time_item.get('between') is not None:
				if is_dict(time_item['between']):
					if not is_within_range(time_item['between']):
						continue
				else:
					report_error("CHECK_TIMES", ["between is not a dictionary", str(time_item['between'])])

			# Month check
			if time_item.get('month') is not None:
				if not any(m == month or m in wildcard_array() for m in time_item['month']):
					continue

			# Date check
			if time_item.get('date') is not None:
				if not any(d == ddm or d in wildcard_array() for d in time_item['date']):
					continue

			# Day of week check
			if time_item.get('dayOfWeek') is not None:
				if not any(d.lower() == day_of_week or d in wildcard_array() for d in time_item['dayOfWeek']):
					continue

			# Tag check
			if 'tag' in time_item:
				if current_video_tag is None or time_item['tag'].lower() != current_video_tag.lower():
					continue

			# Time range check
			if 'start' in time_item and 'end' in time_item:
				ntime = now
				start_h, start_m = time_item['start']
				end_h, end_m = time_item['end']

				if start_h in wildcard_array(): start_h = now_h
				if start_m in wildcard_array(): start_m = now_m
				if end_h in wildcard_array(): end_h = now_h
				if end_m in wildcard_array(): end_m = now_m

				stime_str = "{:02d}/{:02d}/{:04d} {:02d}:{:02d}:00".format(ddm, month, now_y, start_h, start_m)
				etime_str = "{:02d}/{:02d}/{:04d} {:02d}:{:02d}:59".format(ddm, month, now_y, end_h, end_m)

				stime = datetime.datetime.strptime(stime_str, "%d/%m/%Y %H:%M:%S")
				etime = datetime.datetime.strptime(etime_str, "%d/%m/%Y %H:%M:%S")

				printd(time_item['name'], str(stime), now, ntime, etime)

				if not (stime <= ntime <= etime):
					continue

			# Static flag
			if time_item.get('static') is not None:
				if is_number(time_item['static'][1]):
					is_static = time_item['static']

			return [
				time_item['name'],
				not skip['chance'],
				video_type,
				is_static,
				time_item,
				now
			]

	except Exception as valerr:
		report_error("CHECK_TIMES", [str(valerr), traceback.format_exc(), "item failed:", time_item])

	return None

def get_video_tag(video_file):
	"""
	Returns the video tag from the video file name.

	:param video_file: The video file path.
	:return: The video tag or None if not found.
	"""
	# The tag is expected to be in the format @tag@ in the file name
	matches = re.findall(r'@([^@]+)@', video_file)
	if matches:
		return matches[-1].split()[-1]
	return None

def generate_commercials_list(total_time_seconds, multiplier=50):
	"""
	Fills the given total time with randomly selected choices from a random commercial. 

	:param total_time_seconds: Total time in seconds.
	:param multiplier: A multiplier to adjust the maximum time allowed to generate the list of commercials. Default is 50.
	:return: A list of randomly selected commercials that (roughly) sum up to the total time.
	"""
	max_time_before_timeout = max(((float(total_time_seconds)/100) / 100) * multiplier, .1)
	printd("Max time:", max_time_before_timeout)
	remaining_time = total_time_seconds
	selected_intervals = []
	gen_start_time = time.time()

	while True:
		c = get_random_commercial()
		if c == None:
			report_error("GEN_COMM_LIST", ["could not get a random commercial"])
			continue
		
		cTime = get_length_from_file(c)
		if cTime==None:
			report_error("GEN_COMM_LIST", ["commercial file has no length", str(c)])		
		
		while(cTime == None):
			c = get_random_commercial()
			if c == None:
				report_error("GEN_COMM_LOST", ["could not get a random commercial"])
				continue
			cTime = get_length_from_file(c)
			if cTime==None:
				report_error("GEN_COMM_LIST", ["commercial file has no length", str(c)])		

	
		if remaining_time - cTime > 1:
			selected_intervals.append(c)
			remaining_time -= cTime
		
		if time.time() - gen_start_time > max_time_before_timeout:
			printd("taking too long to generate commercials, stopping", remaining_time)
			break
		if remaining_time < 20:
			printd("Remaining time:", remaining_time)
			break
	random.shuffle(selected_intervals) # shuffle the list of commercials to randomize the order
	return selected_intervals

def get_args(index):
	"""
	Returns the command line argument at the specified index.

	:param index: The index of the command line argument to retrieve.
	:return: The command line argument at the specified index or None if not found.
	"""
	if index < len(sys.argv):
		return sys.argv[index]
	return None 

def get_commercials(source):
	"""
	Returns a list of times as defined in the commercial file from the specified source video file.

	:param source: The source video file path.
	:return: A list of commercials in seconds or an empty list if not found.
	"""
	#commercials file name must match exactly the source video with the file extension '.commercials'
	#each line of a .commercials file will be the time of a commercial break in seconds (and milliseconds if required) ex: 620.34
	#first line can be text and number to set a minimum length time for the video plus inserted commercials ex: length:MIN_LENGTH

	#load single file for all commercials
	#usefull when commercial breaks aren't easily identifiable for a lot of episodes from a single show
	#first 8 chrs need to exactly the same
	if os.path.isfile(source[:8] + '.commercials.master') == True:
		with open(source[:8] + '.commercials.master') as temp_file:
			return [float(line.rstrip()) for line in temp_file]
	
	#load individual file for video
	if os.path.isfile(source + '.commercials') == True:
		ret = []
		with open(source + '.commercials') as temp_file:
			for i,line in enumerate(temp_file):
				if is_number(line.rstrip()):
					ret.append(float(line.rstrip()))
		return ret
	return []

def get_length_from_file(file):
	"""
	Returns the length of the video file in seconds.
	
	:param file: The video file path.
	:return: The length of the video in seconds or None if not found.
	"""
	# instead of using ffmpeg or whatever to get the video's length
	# it is stored in the file name
	# %T(TOTAL_LENGTH_IN_SECONDS)%
	try:
		start = file.index("%T(") + 3
		end = file.index(")%", start)
		if is_number(file[start:end]):
			return float(file[start:end])
		else:
			return None
	except Exception as ValueError:
		# if the file name doesn't contain the length, report the error and return None
		report_error("GET_VIDEO_LENGTH", [str(ValueError), str(file)])
		return None

def get_random_video_from_dir(dir):
	"""
	Returns a random video from the specified directory that falls within the specified length range.

	:param dir: The directory to search for videos.
	:param min_len: The minimum length of the video in seconds. Default is 0.
	:param max_len: The maximum length of the video in seconds. Default is 99999.
	:return: A random video file path or None if no videos are found.
	"""
	try:

		videos = get_videos_from_dir_cached(dir)
		if len(videos) == 0:
			return None
		random.shuffle(videos) # shuffle the list of videos to randomize the order
		return random.choice(videos)
	except Exception as valerr:
		#an unknown error occurred, report it and return nothing		
		report_error("GET_RND_VIDEO", ["##unexpected error##", str(valerr), traceback.format_exc()])
		return None

def get_random_commercial():
	"""
	Returns a random commercial from the list of commercials in the settings file.
	
	:return: A random commercial file path or None if no commercials are found.
	"""
	global settings

	try:
		programming_schedule = check_video_times(settings['commercial_times'], get_current_channel()) # check to see if any commercials are scheduled
		if programming_schedule == None:
			#no specified commercials were set for this time perioid, even tho the settings suggests there might be
			report_error("GET_RND_COMM", ["No commercials block found", "check settings.json file"]) #could be intentional, but we're going to report it just in case
			return None

		min_len=0
		if 'min-length' in programming_schedule[4]:
			min_len = programming_schedule[4]['min-length']
			if not is_number(min_len): # check to see if the min-length is a number
				report_error("COMMERCIALS", ["min-length is set but it is not a number", "check settings.json file", programming_schedule[4]])

		max_len=99999 # 2 days worth of seconds
		if 'max-length' in programming_schedule[4]:
			max_len = programming_schedule[4]['max-length']
			if not is_number(max_len): # check to see if the max-length is a number
				report_error("COMMERCIALS", ["max-length is set but it is not a number", "check settings.json file", programming_schedule[4]])

		folder = programming_schedule[0] # the results, if any, returns None type of not
		if folder:
			#commercials are scheduled, so let's gather the videos from the specified paths (could only be 1 or could be many)

			# going to loop through all returned directories, replacing special keywords and validating that the directory does exist
			new_folder=[] # temp list of new directories
			for i in range(0, len(folder)):
				tmp_folder = replace_all_special_words(folder[i]) # replace special keywords
				if os.path.isdir(tmp_folder) == False: # folder doesn't exist
					report_error("GET_RND_COMM", [folder, "directory does not exist", "check settings.json file"]) # report error that directory exist
				else:
					new_folder.append(tmp_folder) # add directories that do exist
			
			if len(new_folder)==0: # no directories exist
				report_error("GET_RND_COMM", ["No searachble directories are found", "check settings.json file"])
				return None
			
			folder = new_folder # replace old folders with the newly verified folders
			printd("get_random_commercial: ", "Verified commercial folders:", folder)
			# settings wants the random video to be selected from multiple folders but prefers the random video come from the folders supplied rather than the combine contents of each folder
			# this can help balance the types of videos played when supplying different folders where one type with more videos would be more randomly favored
			folder_chance = None 
			if 'prefer-folder' in programming_schedule[4]:
				folder_chance = programming_schedule[4]['prefer-folder']
			
			if folder_chance != None: # check to see if the user has set a folder preference
				if 'weighted' in programming_schedule[4]: # check to see if the user has set a weighted folder selection
					if len(programming_schedule[4]['weighted']) != len(folder): # check to see if the weighted folder count matches the folder count
						report_error("GET_RND_COMM", ["Weighted folder count supplied does not match folder count", "check settings.json file", programming_schedule[4]]) # report error if they don't match
						random.shuffle(folder) # shuffle the list of folders to randomize the order
						rfolder = random.choice(folder) # also if they don't match, just select a random folder
					else:
						rfolder = weighted_random_choice(folder, programming_schedule[4]['weighted']) # select a folder based on the weighted values
						if rfolder == None:
							report_error("GET_RND_COMM", ["Weighted folder selection failed", "check settings.json file", programming_schedule[4]]) # report error if the weighted values are invalid
							random.shuffle(folder) # shuffle the list of folders to randomize the order
							rfolder = random.choice(folder) # if the weighted values are invalid, just select a random folder
				else:
					random.shuffle(folder) # shuffle the list of folders to randomize the order
					rfolder = random.choice(folder) # if no weighted values are set, just select a random folder
				
				video = get_videos_from_dir_cached(replace_all_special_words(rfolder), min_len, max_len) # get the videos from the selected folder
			else: # no folder preference set
				video = get_videos_from_dir_cached(replace_all_special_words(folder[0]), min_len, max_len) # get the videos from the first folder in the list
				for itemX in range(1,len(folder)): # loop through the rest of the folders
					video = video + get_videos_from_dir_cached(replace_all_special_words(folder[itemX]), min_len, max_len) # add the videos from the folder to the list of videos
			printd("get_random_commercial: ", "Commercial videos found:", len(video))
			random.shuffle(video) # shuffle the list of videos to randomize the order
			printd("get_random_commercial: ", "Shuffled: ", len(video))
			printd("get_random_commercial: ", "Random: ", random.choice(video))
			return random.choice(video) #return a random video from the combined paths
	except Exception as valerr:
		#an unknown error occurred, report it and return nothing		
		report_error("GET_RND_COMM", ["##unexpected error##", "check settings.json file", str(valerr), traceback.format_exc(), programming_schedule if programming_schedule!=None else "no programming schedule"])
		return None	

def get_current_channel():
	"""Returns the current channel name based on settings or external file."""
	global channel_name_static, last_channel_name

	if channel_name_static is not None:
		return channel_name_static

	channel_file = get_setting(['channels', 'file'])
	if not channel_file or not os.path.exists(channel_file):
		return None

	with open(channel_file) as f:
		line = f.read().strip()

	nc = line or "default"
	if nc != last_channel_name:
		report_error("CHANNEL", ["Channel set to: " + nc])
		last_channel_name = nc

	return line or None

def getDayOfWeek(d):
	"""
	Returns the name of the day of the week for a given integer.

	:param d: An integer representing the day of the week (0=Monday, 6=Sunday).
	:return: The name of the day of the week in lowercase.
	"""
	return calendar.day_name[d].lower()
		
def getMonth(m):
	"""
	Returns the month name for a given month number.

	:param m: The month number (1-12).
	:return: The name of the month.
	"""
	return [None,'january','february','march','april','may','june','july','august','september','october','november','december'][m % 12 or 12]

def get_folders_from_server(showType, showDir):
	"""
	Returns a list of available shows from the server.

	:param showType: The type of show to search for.
	:param showDir: The directory to search in.
	:return: A list of available shows.
	"""
	contents = open_url("http://127.0.0.1/?getavailable=" + urllib.quote_plus(ensure_string(showType)) + "&dir=" + urllib.quote_plus(ensure_string(showDir)))
	printd("get_folders_from_server: ", "Contents from server:", contents)
	if contents == '0':
		report_error("GET_FOLDERS_FROM_SERVER", ["No shows found on server!", "Show type: " + ensure_string(showType), "Show dir: " + ensure_string(showDir)	])
		return None
	return contents.split("\n")

def get_folders_from_dir(path):
	"""
	Returns a list of directories in the specified path.

	:param path: The path to search for directories.
	:return: A list of directories found in the specified path.
	"""
	dirs = os.walk(path).next()[1]
	for i in range(len(dirs)):
		dirs[i] = path + "/" + dirs[i]
	return dirs

def clean_up_cache_files(dir_name,dir):
	"""
	Removes cache files from the specified directory that match the given directory name.

	:param dir_name: The directory where the cache files are located.
	:param dir: The directory name to match against the cache files.
	"""
	test = os.listdir(dir_name)

	for item in test:
		if item.endswith(".cache"):
			if item.split()[0] == dir:
				os.remove(os.path.join(dir_name, item))



def is_cache_valid(cache_path, dir_path):
	"""
	Checks if the cache file is valid by comparing the cached filenames with the current filenames in the directory.

	:param cache_path: The path to the cache file.
	:param dir_path: The directory to check against the cached filenames.
	
	:return: True if the cache is valid, False otherwise.
	"""
	try:
		# Read cached filenames
		with open(cache_path, 'r') as f:
			cached_filenames = set(json.load(f))

		# Get current filenames in target directory (non-recursive)
		current_filenames = set(os.listdir(dir_path))

		# Compare sets
		if cached_filenames == current_filenames:
			return True
		else:
			return False

	except Exception as e:
		report_error("CACHE_VALIDATION", ["Error validating cache", ensure_string(e)])
		return False

def set_pi_permissions(path):
	"""Ensure pi:pi ownership and appropriate permissions in Python 2."""
	try:
		if os.geteuid() == 0:
			os.chown(path, 1000, 1000)

		if os.path.isdir(path):
			os.chmod(path, 0775)
		else:
			os.chmod(path, 0664)
	except Exception as e:
		report_error("SET_PI_PERMISSIONS", ["Warning: set_pi_permissions failed on %s: %s" % (path, str(e))])


def get_files_from_dir(dir_path, extensions=VIDEO_EXTENSIONS, min_length=GET_VIDEOS_FROM_DIR_MIN_DURATION, max_length=GET_VIDEOS_FROM_DIR_MAX_DURATION):
	"""
	Loads all video filenames from a directory into an array, using caching and OS modification time checks.
	Caches all filenames, but filters the returned results based on length.

	:param dir_path: The directory to search for video files.
	:param min_length: Minimum length (in seconds) for video files to be returned. Defaults to 0.
	:param max_length: Maximum length (in seconds) for video files to be returned. Defaults to 99999.
	:return: A list of full paths to video files found in the directory that meet the length criteria.
	"""

	cache_dir_name = get_setting(['cache_path'], os.path.join(os.path.dirname(os.path.realpath(__file__)), "cache"))
	
	sanitized_dir = re.sub(r'[^a-zA-Z0-9]', '', dir_path)
	cache_fname = os.path.join(cache_dir_name, "{}.cache".format(sanitized_dir))

	if not os.path.exists(cache_dir_name):
		try:
			os.makedirs(cache_dir_name)
		except OSError:
			pass

	# Ensure the directory itself is owned by pi:pi on every execution
	set_pi_permissions(cache_dir_name)

	all_filenames = []
	update_cache = True

	# Check if existing cache file is valid and newer than directory mtime
	if os.path.exists(cache_fname):
		cache_mtime = os.path.getmtime(cache_fname)
		dir_mtime = os.path.getmtime(dir_path)

		if cache_mtime >= dir_mtime and is_cache_valid(cache_fname, dir_path):
			try:
				with open(cache_fname, 'r') as f:
					all_filenames = json.load(f)
				update_cache = False
			except Exception:
				update_cache = True

	# Single scan and dump path using Python 2 os.listdir
	if update_cache:
		valid_exts = tuple('.' + ext.lower().lstrip('.') for ext in extensions)
		filenames_from_scan = []

		try:
			for fname in os.listdir(dir_path):
				if fname.lower().endswith(valid_exts):
					full_path = os.path.join(dir_path, fname)
					if os.path.isfile(full_path):
						filenames_from_scan.append(fname)
		except OSError:
			filenames_from_scan = []

		with open(cache_fname, 'w') as f:
			json.dump(filenames_from_scan, f)
		set_pi_permissions(cache_fname)
		all_filenames = filenames_from_scan

	# Filter all_filenames based on duration tag regex
	length_regex = re.compile(r'%T\((\d+)\)%')
	filtered_results_full_paths = []

	for filename in all_filenames:
		match = length_regex.search(filename)
		if match:
			try:
				video_length = int(match.group(1))
				if min_length <= video_length <= max_length:
					filtered_results_full_paths.append(os.path.join(dir_path, filename))
			except ValueError:
				pass

	return filtered_results_full_paths

_cached_get_videos_from_dir = {}

def get_videos_from_dir_cached(dir_path, min_length=GET_VIDEOS_FROM_DIR_MIN_DURATION, max_length=GET_VIDEOS_FROM_DIR_MAX_DURATION, filter=VIDEO_EXTENSIONS):
	"""
	Retrieves video files from a directory while avoiding hitting the file system by storing the cache in a variable for immediate access in the near future.

	:param dir_path: The directory to search for video files.
	:param min_length: Minimum length (in seconds) for video files to be returned. Defaults to 0.
	:param max_length: Maximum length (in seconds) for video files to be returned. Defaults to 99999.

	:return: A list of full paths to video files found in the directory that meet the length criteria.
	"""
	if not os.path.isdir(dir_path):
		return []

	global _cached_get_videos_from_dir
	now = time.time()

	cache_key = (dir_path, min_length, max_length)
	cache_entry = _cached_get_videos_from_dir.get(cache_key)

	# Use cached data if it exists and is recent
	if cache_entry and now - cache_entry['timestamp'] < 120:  # 2 min freshness window
		return cache_entry['results']

    # Otherwise, refresh from disk
	results = get_files_from_dir(dir_path, filter, min_length, max_length)
	_cached_get_videos_from_dir[cache_key] = {
		'timestamp': now,
		'results': results
	}
	return results

def get_uptime(script_start_time):
	"""
	Returns the uptime of the script in seconds.

	:param start_time: The time the script started.
	:return: The uptime in seconds.
	"""
	return (time.time() - script_start_time)

def is_date_within_range(date1, date2, range_days):
	"""	
	Checks if the difference between two dates is within a specified range of days.	

	:param date1: The first date to compare.
	:param date2: The second date to compare.
	:param range_days: The range of days to check against.
	:return: True if the difference is within the range, False otherwise.
	"""
	delta = (date1 - date2).days
	if (range_days >= 0 and 0 <= delta <= range_days) or (range_days < 0 and range_days <= delta <= 0):
		return True
	return False


def is_dict(var):
	"""
	Checks if the variable is a dictionary.

	:param var: The variable to check.
	:return: True if the variable is a dictionary, False otherwise.
	"""
	return isinstance(var, dict)

holidays = [ "thanksgiving", "xmas", "halloween", "mothers day", "fathers day", "memorial day", "easter", "new years day", "mlk day", "valentines day", "presidents day", "st patricks day", "april fools day", "independence day", "4th of july", "labor day", "columbus day", "veterans day", "christmas eve", "new years eve" ]

def get_holiday_datetime(holiday, year=None):
	"""Calculates Thanksgiving (4th Thursday of Nov) for a given year."""
	global now
	search_year = year if year is not None else now.year
	holiday = holiday.lower().replace(" ", "")
	ret = -1

	if holiday == "thanksgiving":
    # Thanksgiving is the 4th Thursday of November (falls between Nov 22 and Nov 28)
		first_of_nov = datetime.datetime(search_year, 11, 1)
		dw = first_of_nov.weekday()
		day = 22 + (10 - dw) % 7
		ret = datetime.datetime(search_year, 11, day, now.hour, now.minute)

	elif holiday == "mothersday":
		# Mother's Day is the 2nd Sunday in May
		may_first = datetime.datetime(search_year, 5, 1)
		# Calculate the first Sunday, then add 7 days for the second Sunday
		first_sunday_day = 1 + (6 - may_first.weekday() + 7) % 7
		day = first_sunday_day + 7
		ret = datetime.datetime(search_year, 5, day, now.hour, now.minute)

	elif holiday == "xmas":
		# The "xmas" keyword is used to check for the period after Thanksgiving and on or before Christmas.
		# For a true Christmas check, use the holiday "christmas"
		first_of_nov = datetime.datetime(search_year, 11, 1)
		dw = first_of_nov.weekday()
		day = 22 + (10 - dw) % 7
		tg = datetime.datetime(search_year, 11, day, now.hour, now.minute)
		cd = datetime.datetime(search_year, 12, 25, now.hour, now.minute)
		if now > tg and now <= cd:
			ret = cd

	elif holiday == "christmas":
		# Christmas is a fixed date: Dec 25th. If you want to check for the period after Thanksgiving day and on or before Christmas, use the holiday "xmas"
		# Fixed date: Dec 25th
		ret = datetime.datetime(search_year, 12, 25, now.hour, now.minute)
		
	elif holiday == "halloween":
		# Fixed date: Oct 31st
		ret = datetime.datetime(search_year, 10, 31, now.hour, now.minute)

	elif holiday == "memorialday":
		# Memorial Day is the last Monday in May
		# Start at May 31st and subtract the weekday to get back to the previous Monday
		may_31 = datetime.datetime(search_year, 5, 31)
		dw = may_31.weekday() # Monday is 0, Sunday is 6
		day = 31 - dw
		ret = datetime.datetime(search_year, 5, day, now.hour, now.minute)

	elif holiday == "fathersday":
		# Father's Day is the 3rd Sunday in June
		june_first = datetime.datetime(search_year, 6, 1)
		# First Sunday
		first_sunday_day = 1 + (6 - june_first.weekday() + 7) % 7
		# Add 14 days to get from the 1st Sunday to the 3rd
		day = first_sunday_day + 14
		ret = datetime.datetime(search_year, 6, day, now.hour, now.minute)

	elif holiday == "easter":
		# Using the Anonymous Gregorian algorithm to calculate Easter Sunday
		# Optimized for Python 2.7 integer division
		a = search_year % 19
		b = search_year // 100
		c = search_year % 100
		d = b // 4
		e = b % 4
		f = (b + 8) // 25
		g = (b - f + 1) // 3
		h = (19 * a + b - d - g + 15) % 30
		i = c // 4
		k = c % 100 % 4
		l = (32 + 2 * e + 2 * i - h - k) % 7
		m = (a + 11 * h + 22 * l) // 451
		month = (h + l - 7 * m + 114) // 31
		day = ((h + l - 7 * m + 114) % 31) + 1
		ret = datetime.datetime(search_year, month, day, now.hour, now.minute)

	elif holiday == "newyearsday":
		# Fixed date: Jan 1st
		ret = datetime.datetime(search_year, 1, 1, now.hour, now.minute)

	elif holiday == "mlkday":
		# MLK Day is the 3rd Monday in January
		jan_first = datetime.datetime(search_year, 1, 1)
		first_monday_day = 1 + (0 - jan_first.weekday() + 7) % 7
		day = first_monday_day + 14
		ret = datetime.datetime(search_year, 1, day, now.hour, now.minute)

	elif holiday == "valentinesday":
		# Fixed date: Feb 14th
		ret = datetime.datetime(search_year, 2, 14, now.hour, now.minute)

	elif holiday == "presidentsday":
		# Presidents Day is the 3rd Monday in February
		feb_first = datetime.datetime(search_year, 2, 1)
		first_monday_day = 1 + (0 - feb_first.weekday() + 7) % 7
		day = first_monday_day + 14
		ret = datetime.datetime(search_year, 2, day, now.hour, now.minute)

	elif holiday == "stpatricksday":
		# Fixed date: March 17th
		ret = datetime.datetime(search_year, 3, 17, now.hour, now.minute)

	elif holiday == "aprilfoolsday":
		# Fixed date: April 1st
		ret = datetime.datetime(search_year, 4, 1, now.hour, now.minute)

	elif holiday == "independenceday" or holiday == "4thofjuly":
		# Fixed date: July 4th
		ret = datetime.datetime(search_year, 7, 4, now.hour, now.minute)

	elif holiday == "laborday":
		# Labor Day is the 1st Monday in September
		sep_first = datetime.datetime(search_year, 9, 1)
		day = 1 + (0 - sep_first.weekday() + 7) % 7
		ret = datetime.datetime(search_year, 9, day, now.hour, now.minute)

	elif holiday == "columbusday":
		# Columbus Day is the 2nd Monday in October
		oct_first = datetime.datetime(search_year, 10, 1)
		first_monday_day = 1 + (0 - oct_first.weekday() + 7) % 7
		day = first_monday_day + 7
		ret = datetime.datetime(search_year, 10, day, now.hour, now.minute)

	elif holiday == "veteransday":
		# Fixed date: Nov 11th
		ret = datetime.datetime(search_year, 11, 11, now.hour, now.minute)

	elif holiday == "christmaseve":
		# Fixed date: Dec 24th
		ret = datetime.datetime(search_year, 12, 24, now.hour, now.minute)

	elif holiday == "newyearseve":
		# Fixed date: Dec 31st
		ret = datetime.datetime(search_year, 12, 31, now.hour, now.minute)

	return ret

def is_number(s):
	""" 
	Checks if the string is a number by testing if it can be converted to a float.

	:param s: The string to check.
	:return: True if it can be converted, False otherwise.
	"""

	try:
		float(s)
		return True
	except (TypeError, ValueError):
		return False


def is_special_time(check):
	"""
	Checks if the current date is a special holiday or event by 
	looping through the global holiday array.

	:param check: The string to check against the holiday names.
	:return: True if the current date matches the holiday criteria, False otherwise.
	"""
	check_lower = check.lower().strip()

	for holiday in holidays:
		# We check if the input starts with the holiday name
		if check_lower.startswith(holiday):
			
			# Extract potential numeric offset (the part after the holiday name)
			remaining_part = check_lower[len(holiday):].strip()
			printd("Checking if today is:", holiday, "remaining part:", remaining_part)
			num = 0
			if is_number(remaining_part):
				num = int(remaining_part)

			# Call your date calculation function
			# Assuming get_holiday_datetime returns a valid datetime object or equivalent
			holiday_date = get_holiday_datetime(holiday)
			if holiday_date == -1:
				printd("Holiday not recognized:", holiday)
				continue
			printd("Checking special time for:", check, "Holiday:", holiday, "Holiday Date:", holiday_date, "Current Date:", datetime.date(now.year, now.month, now.day), "Range (days):", num)
			is_within_range = is_date_within_range(datetime.date(now.year, now.month, now.day), holiday_date.date(), num)
			
			return True if is_within_range else False

	return False

def kill_omxplayer():
	command = "/usr/bin/sudo pkill omxplayer"
	process = subprocess.Popen(command.split(), stdout=subprocess.PIPE)
	output = process.communicate()[0]
	print(output)

def now_totheminute():
	"""Returns the current datetime rounded to the minute."""
	now = datetime.datetime.now()
	return now.replace(second=0, microsecond=0)

def open_url(url):
	"""
	Opens a URL and returns the response content.

	:param url: The URL to open.
	:return: The content of the response.
	"""
	try:
		
		if get_setting(['debug'], False) == True:
			printd(url)
		return urllib2.urlopen(url).read()
	except:
		return None


def replace_all_special_words(s, skip_drive_replacement=False):
	"""
	Replaces special keywords in a string with their actual values.
	Handles keywords like %D[index]%, %day%, %day_of_week%, %month%, etc.

	:param s: The string to process.
	:return: The processed string with special keywords replaced.
	"""
	global settings
	global now
	d = now.weekday()
	month = now.month
	# drive(s) location is set via an array in the settings.
	# iterate each drive replacing the special keyword with the actual drive location
	# 		%D[index]%
	if not skip_drive_replacement:
		for i in range(len(settings['drive'])):
			s = s.replace("%D[" + str((i+1)) + "]%", settings['drive'][i])
	# the day of the week and month special keywords can be replaced with the current day or week or month
	for r in [["%day%", str(d)], ["%day_of_week%", getDayOfWeek(d)], ["%month%", getMonth(month)], ["%prev-month%", getMonth(month-1)], ["%next-month%", getMonth(month+1)]]:
		s = s.replace(*r)
	return s

last_report_error = [0, 0, 0, 0] # [last error time, last error report time, error count, reserved]
def report_debug(type, input, local_only=False):
	"""	
	Reports debug information to the local server and prints it to the console.
	
	:param type: The type of debug information.
	:param input: The debug information to report.
	:param local_only: If True, only report to the local server.
	"""
	global settings
	if 'debug' in settings:
		if settings['debug'] == True:
			report_error("DEBUG[" + type + "]", input + ["Debug mode is Enabled, disable it in settings"], False) # debug reports are always reported

def report_error(type, input, local_only=False):
	"""
	Reports an error to the local server and prints it to the console.
	Also checks if the error is in a loop and exits if it is.

	:param type: The type of error.
	:param input: The error message or data to report.
	:param local_only: If True, only report to the local server.
	"""
	try:
		global settings, channel_name_static, error_channel_set, start_time
		global last_played_video_source
		global last_report_error

		message = ""

	 # Check for error loop
		if (time.time() - last_report_error[0]) < 10:
			if last_report_error[2] >= 10:
					contents = open_url("http://127.0.0.1/?error=" + urllib.quote_plus(
						ensure_string("ERROR LOOP|STUCK IN ERROR LOOP|EXITING PROGRAM")
					))
					printd("#####: ERROR_LOOP :: STUCK IN ERROR LOOP.", "Server response:", contents)
					time.sleep(10)
					exit()

		else:
			# Not stuck in loop, reset timer and count
			last_report_error[0] = time.time()
			last_report_error[2] = 0

		# Build message
		for s in input:
			if hasattr(s, '__iter__'):
				message += '|'.join(ensure_string(x) for x in s) + "|"
			elif isinstance(s, basestring):
				message += s + "|"
			else:
				message += "REPORT_ERROR|unknown input type|"
				try:
					message += ensure_string(s) + "|"
				except Exception as e:
					message += ensure_string(e) + "|"

		if message.endswith("|"):
			message = message[:-1]

		addmsg = ""
		if last_played_video_source is not None:
			addmsg = "|Source|" + last_played_video_source

		print("#####: " + type + " :: " + message + addmsg)
		print("")

		if not local_only and settings.get("report_data", True):
			contents = open_url("http://127.0.0.1/?error=" + urllib.quote_plus(
				ensure_string(type) + "|" + ensure_string(message)
			))

		last_report_error = [last_report_error[0], time.time(), last_report_error[2] + 1, last_report_error[3]]

	except Exception as e:
		contents = open_url("http://127.0.0.1/?error=ERROR_REPORTING_ERROR|" + ensure_string(e) + "|" + traceback.format_exc())

def restart():
	"""Restarts the system using sudo shutdown command."""
	command = "/usr/bin/sudo /sbin/shutdown -r now"
	process = subprocess.Popen(command.split(), stdout=subprocess.PIPE)
	output = process.communicate()[0]
	print(output)

def returncleanASCII(s):
	"""
	Removes non-ASCII characters except '%', improving efficiency.
	
	:param s: The string to clean.
	:return: The cleaned string with only ASCII characters and '%'
	"""
	s = ensure_string(s)  # Ensure input is a string
	if not isinstance(s, str):
		report_error("RETURN_CLEAN_ASCII", ["Input is not a string"])
		return ""
	# Use a generator expression to filter out non-ASCII characters
	return s #"".join(c for c in s if 32 <= ord(c) <= 127 or c == '%')

def search_videos_within_range(file_list, start_num, end_num):
	"""
	Searches a list for file names containing %T(NUM)% where NUM is in range.
	
	:param file_list: List of file names to search.
	:param start_num: Starting number for the range.
	:param end_num: Ending number for the range.
	:return: List of file names that match the pattern and are within the range.
	"""
	if start_num is None or end_num is None:
		return file_list

	if start_num > end_num:
		report_error("SEARCH_VIDEOS", ["Invalid range: start_num > end_num", start_num, end_num])
		return []
	
	pattern = re.compile(r'%T\((\d+)\)%')
	matching_files = []

	for filename in file_list:
		match = pattern.search(filename)
		if match:
			num = int(match.group(1))  # Extract NUM
			if start_num <= num <= end_num:  # Check range
				matching_files.append(filename)

	return matching_files

def spread_division(total, parts):
	"""
	Distributes the total into parts as evenly as possible.
	Used by the commercial break generator to divide the total time into parts.

	:param total: Total value to be divided.
	:param parts: Number of parts to divide into.
	:return: List of integers representing the distributed values.
	"""
	base_value = total // parts  # Get the whole number portion
	remainder = total % parts  # Find the leftover value

	result = [base_value] * parts  # Start with equal distribution

	# Spread out the remainder across the first few elements
	for i in range(remainder):
		result[i] += 1
	random.shuffle(result)  # Shuffle the result to randomize the order
	return result

def update_current_time():
	"""
	Updates the global 'now' variable to the current date and time.
	Also checks if a test date is set in the settings and updates 'now' accordingly.

	:return: None
	"""
	global now
	global settings
	now = now_totheminute() # set the date time to the minute so seconds do not come into play when comparing times
	# check if the settings file has a test date 
	if 'time_test' in settings:
		if settings['time_test'] != None:
			# if it is, we set the global 'now' var to the test date
			# useful for testing holiday programming and other date/time specific programming
			now = datetime.datetime.strptime(str(settings['time_test']), '%b %d %Y %I:%M%p')

def update_settings():
	"""
	Updates the global settings variable by reading the settings file and validating its JSON format.

	:return: None
	"""
	global settings
	global settings_file
	
	f = open(settings_file, "r")
	# we need to verify that the settings file is JSON formatted.
	settings = validate_json(f.read())
	if settings == None:
		# report the error and exit script if it is not
		report_error("SETTINGS", ["settings file isn't valid JSON"])
		sleep(10)
		exit()

	f.close()

	#repeatedly_reset() # since settings have been updated, reset "repeatedly" tracked times

	settings['load_time'] = os.path.getmtime(settings_file)

	if 'version' in settings:
		if is_number(settings['version']):
			if float(settings['version']) != SETTINGS_VERSION:
				report_error("Settings", ["Settings version mismatch. Things might not work so well.", "A"])
		else:
			report_error("Settings", ["Settings version mismatch. Things might not work so well.", "B"])
	else:
		report_error("Settings", ["Settings version mismatch. Things might not work so well.", "C"])


def validate_json(json_str):
	"""
	Validates the JSON string and returns a dictionary or None if invalid.

	:param json_str: JSON string to validate.
	:return: Dictionary if valid, None if invalid.
	"""
	try:
		return json.loads(json_str, cls=ReferenceDecoder)
	except ValueError as err:
		return None

def weighted_random_choice(items, percentages):
	"""
	Selects a random item from a list based on the given percentages.

	Args:
	items (list): List of items to choose from.
	percentages (list): List of percentages corresponding to the items.

	Returns:
	A randomly selected item from the list.
	"""
	if len(items) != len(percentages):
		#raise ValueError("The number of items and percentages must match.")
		return None

	if sum(percentages) != 100:
		#raise ValueError("The percentages must add up to 100.")
		return None

	# Create a cumulative distribution
	cumulative_distribution = []
	cumulative_sum = 0

	for percent in percentages:
		cumulative_sum += percent
		cumulative_distribution.append(cumulative_sum)

	# Generate a random number between 0 and 100
	random_number = random.uniform(0, 100)

	# Select the item based on the random number and cumulative distribution
	for i, threshold in enumerate(cumulative_distribution):
		if random_number <= threshold:
			return items[i]

def weighted_random_choice_auto(items, percentages):
	"""
	Selects a random item from a list based on the given percentages.

	Args:
	items (list): List of items to choose from.
	percentages (list): List of percentages corresponding to the items or can be set to the string 'auto'.


	Returns:
	A randomly selected item from the list.
	"""

	# If percentages is set to 'auto', we will automatically calculate the percentages based on the number of files in the items, which point to directories.
	# Then we can sent the percentages var based on those values and continue as normal.
	# This is useful for randomly selecting from a list of directories where the number of files in each directory should determine the chances of it being selected.

	if isinstance(percentages, basestring) and percentages.lower() == 'auto':
		counts = []
		total_files = 0
		for item in items:
			if os.path.isdir(item):
				num_files = len(get_videos_from_dir_cached(item))
				counts.append(num_files)
				total_files += num_files
			else:
				counts.append(0) 

		if total_files > 0:
			# Multiply by 100 before dividing to handle integer division in Python 2.7
			percentages = [(c * 100) / total_files for c in counts]
			
			# Largest Remainder Method: Distribute the losing remainders to guarantee exactly 100%
			remainder = 100 - sum(percentages)
			if remainder > 0:
				# Sort indices by their modulo remainder descending
				rem_indices = sorted(
					range(len(counts)), 
					key=lambda i: (counts[i] * 100) % total_files, 
					reverse=True
				)
				for i in range(remainder):
					percentages[rem_indices[i]] += 1
		else:
			# Equal integer distribution if no files are found
			num_items = len(items)
			if num_items > 0:
				base = 100 / num_items
				remainder = 100 % num_items
				percentages = [base] * num_items
				for i in range(remainder):
					percentages[i] += 1
			else:
				percentages = []

	if len(items) != len(percentages):
		#raise ValueError("The number of items and percentages must match.")
		return None

	if sum(percentages) != 100:
		#raise ValueError("The percentages must add up to 100.")
		return None

	# Create a cumulative distribution
	cumulative_distribution = []
	cumulative_sum = 0

	for percent in percentages:
		cumulative_sum += percent
		cumulative_distribution.append(cumulative_sum)

	# Generate a random number between 0 and 100
	random_number = random.uniform(0, 100)

	# Select the item based on the random number and cumulative distribution
	for i, threshold in enumerate(cumulative_distribution):
		if random_number <= threshold:
			return items[i]

def wildcard_array():
	"""
	Returns a list of wildcard options for the settings file.

	:return: List of wildcard options.
	"""
	return ['all', 'any', '*']

def generate_bumpers_list(bumpers_in, bumpers_out, total, in_chance=1.0, out_chance=1.0):
	"""
	Generates a list of in and out bumpers for each commercial break.
	
	:param bumpers_in[]: A list of paths for the in bumpers.
	:param bumpers_out[]: A list of paths for the out bumpers.
	:param total: The total number of commercial breaks.
	:param in_chance: The chance of an "in bumper" being played (0.0 to 1.0). Defaults to 1.0 (always play).
	:param out_chance: The chance of an "out bumper" being played (0.0 to 1.0). Defaults to 1.0 (always play).

	:return: A list of paths that point to the bumbers to be played.
	"""

	if bumpers_in == None and bumpers_out == None:
		return None

	in_total = 0
	out_total = 0

	bin = []
	bout = []
	tin = []
	tout = []

	for item in bumpers_in:
		if os.path.exists(item):
			tin += get_videos_from_dir_cached(item)

	for item in bumpers_out:
		if os.path.exists(item):
			tout += get_videos_from_dir_cached(item)

	for i in range(1, total):
		if len(tin) > 0:
			if eval_equation(in_chance) >= random.random():
				temp = random.choice(tin)
				in_total += get_length_from_file(temp)
				bin.append(temp)

		if len(tout) > 0:
			if eval_equation(out_chance) >= random.random():
				temp = random.choice(tout)
				out_total += get_length_from_file(temp)
				bout.append(temp)

	printd(["BUMPERS: IN: ", bin,"OUT: ", bout])
	return { "in" : bin, "out": bout, "in_total": in_total, "out_total": out_total }

def verify_cache_dir():
	"""
	Verifies the cache directory and creates it if it doesn't exist.
	"""
	try:
		cache_dir_name = get_setting(['cache_path'], None)
		if cache_dir_name == None:
			cache_dir_name = os.path.join(os.path.dirname(os.path.realpath(__file__)), "cache")
			report_error("CACHE_DIRECTORY", ["Cache path not set in settings, using default:", cache_dir_name])

		if not os.path.exists(cache_dir_name):
			os.makedirs(cache_dir_name)
	except Exception as e:
		report_error("CACHE_DIRECTORY", ["Error creating cache directory", ensure_string(e)])

# global variables
# Global variables
channel_name_static = None
last_channel_name = None
now = datetime.datetime.now()
last_played_video_source = None
script_load_time = time.time()
curr_static = None
start_time = time.time()
error_channel_set = False
current_video_tag = None
last_video_played = ""
last_error = (7.0, '', 0)

base_directory = os.path.dirname(__file__)
if base_directory:
	base_directory = base_directory.rstrip("/") + "/"

# Settings
settings = None
settings_file = base_directory + "settings.json"

#printd("Loading settings from:", settings_file)
update_settings()
printd("Settings loaded")

verify_cache_dir()
printd("Cache directory verified")

# Load plugins
plugin_dir = get_setting(["plugins directory"], None)
if plugin_dir:
	if not os.path.exists(plugin_dir):
		try:
			os.makedirs(plugin_dir)
			os.chmod(plugin_dir, 0777)
			try:
				import pwd, grp
				uid = pwd.getpwnam("pi").pw_uid
				gid = grp.getgrnam("pi").gr_gid
				os.chown(plugin_dir, uid, gid)
			except Exception:
				pass
			report_error("PLUGIN_LOAD", ["Plugins directory set in settings file but did not exist, it does now."])
		except Exception as e:
			report_error("PLUGIN_LOAD", ["Failed to create plugin directory", plugin_dir, str(e)])

	if not wait_for_plugins(plugin_dir):
		report_error("PLUGIN_LOAD", [
			"Plugin directory could not be accessed or plugins failed to load.",
			plugin_dir
		])
else:
	printd("No plugin directory set")

# Playback control variables
allow_chance = True
source = None
folder = None

def end_of_loop_tasks():
	"""
	Final cleanup and maintenance tasks after each loop iteration.
	Also handles uptime logging and optional daily reboot.
	"""
	global start_time, now, script_load_time

	update_current_time()
	printd("-------------------------------------------------------------------------------")
	start_time = time.time()

	uptime = get_uptime(script_load_time)
	report_error("UPTIME", [str(uptime)])
	printd("Loop completed. Uptime:", uptime)

	if get_setting(["daily reboot"], False):
		try:
			curr_dt_now = now
			printd("Checking for daily reboot window...")
			printd("Current hour:", curr_dt_now.hour)
			printd("Uptime:", uptime)

			if (curr_dt_now.hour >= 2 and curr_dt_now.hour <= 4 and uptime > 20801):
				report_error("RESTART", ["success"])
				print("Daily Reboot Time Reached, Rebooting Now...")
				sleep(15)
				if get_setting(["debug"], False) == False:
					restart()
				else:
					printd("Debug mode enabled, skipping reboot.")
					report_error("RESTART", ["skipped due to debug mode"])
		except Exception as e:
			report_error("RESTART", ["failed", str(e)])
			printd("Reboot check failed:", str(e))

def resolve_programming_schedule_block():
	"""
	Resolves the current programming schedule, including static block logic and error channel fallback.

	Returns a valid programming_schedule or None if unrecoverable.
	"""
	global curr_static, channel_name_static, error_channel_set, now

	printd("Resolving programming schedule block...")

	update_settings()
	update_current_time()

	# Check if static block is active
	if curr_static:
		expiry_time = curr_static[3][1]
		printd("Static block is active. Expiry time:", expiry_time)
		if time.mktime(now.timetuple()) > expiry_time:
			printd("Static block expired. Clearing static state.")
			curr_static = None
			channel_name_static = None
		else:
			printd("Static block still valid. Using static schedule.")
			return curr_static

	# No static block, check schedule
	printd("No static block set. Checking programming schedule.")
	schedule = check_video_times(settings['times'], get_current_channel(), allow_chance)

	if not schedule:
		report_error("PROGRAMMING_SCHEDULE", [
			"The programming schedule returned no results.",
			"Check your settings file to ensure programming is set for this time.",
			"Entering Error Channel mode (if set, check settings.json), otherwise the script will end now."
		])
		if get_setting(['channels', 'error']):
			channel_name_static = settings['channels']['error']
			error_channel_set = True
			printd("Error channel set:", channel_name_static)
			return None
		else:
			printd("No error channel set. Exiting.")
			exit()

	# If schedule has static block, activate it
	if schedule[3] and schedule[3][0] == True:
		duration = float(schedule[3][1])
		schedule[3][1] = time.mktime(now.timetuple()) + duration
		curr_static = schedule
		channel_name_static = schedule[3][2]
		report_error("STATIC", ["static set", channel_name_static])
		printd("Static block activated. Duration:", duration, "Channel:", channel_name_static)

	return schedule

def select_video_from_schedule(schedule):
	"""
	Validates folders and selects videos based on the programming schedule.

	:param schedule: The programming schedule.	
	Returns a list of video paths or None if unrecoverable.
	"""
	global error_channel_set, channel_name_static

	folders = schedule[0]
	video_type = schedule[2]
	meta = schedule[4]

	printd("Selecting video from schedule. Type:", video_type)
	printd("Raw folder list:", folders)

	valid_folders = []
	for path in folders:
		cleaned = replace_all_special_words(path)
		printd("Checking folder:", cleaned)
		if not os.path.isdir(cleaned):
			report_error("MAIN_LOOP", [folders, "directory does not exist", "check settings.json"])
		else:
			valid_folders.append(cleaned)

	if not valid_folders:
		report_error("MAIN_LOOP", ["No valid folders found", folders])
		if get_setting(['channels', 'error']):
			channel_name_static = settings['channels']['error']
			error_channel_set = True
			printd("Error channel set due to missing folders:", channel_name_static)
			return None
		else:
			printd("No error channel set. Exiting.")
			exit()

	min_len = meta.get('min-length', 0)
	max_len = meta.get('max-length', 99999)
	for key, val in [('min-length', min_len), ('max-length', max_len)]:
		if not is_number(val):
			report_error("PROGRAMMING", [key + " is not a number", "check settings.json", meta])
		else:
			printd("{} validated:".format(key), val)

	if meta.get('prefer-folder'):
		printd("Prefer-folder logic activated")
		if 'weighted' in meta:
			if len(meta['weighted']) != len(valid_folders):
				report_error("GET_RND_COMM", ["Weighted values mismatch", "check settings.json", meta])
				selected_folder = random.choice(valid_folders)
				printd("Fallback to random folder:", selected_folder)
			else:
				selected_folder = weighted_random_choice(valid_folders, meta['weighted']) or random.choice(valid_folders)
				printd("Weighted folder selected:", selected_folder)
		else:
			selected_folder = random.choice(valid_folders)
			printd("Random folder selected:", selected_folder)

		videos = get_videos_from_dir_cached(selected_folder, min_len, max_len)
	else:
		printd("No folder preference. Aggregating videos from all folders.")
		videos = []
		for folder in valid_folders:
			printd("Loading videos from:", folder)
			videos += get_videos_from_dir_cached(folder, min_len, max_len)

	if not videos:
		report_error("PROGRAMMING", ["No videos found after length filter"])
		return None

	printd("Total videos selected:", len(videos))
	return videos

def resolve_video_by_type(schedule, folders):
	"""
	Resolves video selection based on the programming type.

	:param schedule: The programming schedule.
	:param folders: List of folder paths.
	Returns a list of video paths or None if unrecoverable.
	"""
	global error_channel_set, channel_name_static

	video_type = schedule[2]
	meta = schedule[4]

	printd("Resolving video type:", video_type)

	if video_type in ["video", "video-show", "commercial"]:
		printd("Standard video type detected:", video_type)
		return select_video_from_schedule(schedule)

	elif video_type == "balanced-video":
		printd("Balanced video mode activated")
		url = "".join(["&f{}={}".format(i+1, urllib.quote_plus(ensure_string(replace_all_special_words(f)))) for i, f in enumerate(folders)])
		full_url = "http://127.0.0.1/?get_next_rnd_episode_from_dir=1" + url
		urlcontents = open_url(full_url)

		printd("BV-Response:", urlcontents)
		printd("BV-Sent:", full_url)

		acontents = urlcontents.split("|")
		try:
			if acontents[1] == "0" or "|" not in urlcontents:
				report_error("SHOW BALANCED VIDEO", acontents, meta)
				rfolder = random.choice(folders)
				printd("Fallback folder:", rfolder)
				return [random.choice(get_videos_from_dir_cached(rfolder))]
			elif os.path.exists(acontents[0]):
				printd("Balanced video selected:", acontents[0])
				return [acontents[0]]
			else:
				report_error("SHOW BALANCED VIDEO", ["File does not exist", acontents, schedule])
		except Exception as e:
			report_error("SHOW BALANCED VIDEO", [urlcontents, str(e), schedule])
		return None

	elif video_type == "ordered-video":
		printd("Ordered video mode activated")
		all_videos = []
		for folder in folders:
			all_videos += get_videos_from_dir_cached(replace_all_special_words(folder))
		if not all_videos:
			report_error("ORDERED-VIDEO", ["No videos found in folders", folders])
			return None

		selvideo = random.choice(all_videos)
		full_url = "http://127.0.0.1/?get_next_episode=" + urllib.quote_plus(ensure_string(selvideo))
		urlcontents = open_url(full_url)

		printd("OV-Response:", urlcontents)
		printd("OV-Sent:", selvideo)

		acontents = urlcontents.split("|")
		try:
			if acontents[1] == "0" or "|" not in urlcontents:
				report_error("SHOW VIDEOS IN ORDER", acontents)
				return [selvideo]
			elif os.path.exists(acontents[0]):
				printd("Ordered video selected:", acontents[0])
				return [acontents[0]]
			else:
				report_error("SHOW VIDEOS IN ORDER", ["File does not exist", acontents[0]])
				return [selvideo]
		except Exception as e:
			report_error("SHOW VIDEOS IN ORDER", [urlcontents, str(e)])
		return None

	elif video_type == "ordered-show":
		printd("Ordered show mode activated")
		cleaned_folder = replace_all_special_words(folders[0])
		subfolders = get_folders_from_dir(cleaned_folder)
		if not subfolders:
			report_error("ORDERED-SHOW", ["No subfolders found", cleaned_folder])
			return None

		first_local_folder = subfolders[0]
		first_video = get_videos_from_dir_cached(first_local_folder)[0]
		server_folders = get_folders_from_server(first_video, cleaned_folder)

		if not server_folders:
			printd("No folders returned from server, defaulting to local subfolders")
			report_error("ORDERED-SHOW", ["No folders returned from server, defaulting to local subfolders: " + str(subfolders), "First video: " + first_video, "First local folder: " + first_local_folder, "Cleaned folder: " + cleaned_folder])
			server_folders = subfolders

		selfolder = random.choice(server_folders)
		episodes = get_videos_from_dir_cached(selfolder)
		if not episodes:
			# check if file exists on server, if not, report with error
			fv_exists = os.path.exists(first_video)
			report_error("FOLDERS", ["Empty folder", selfolder, "First video: (exists? " + str(fv_exists) + ")" + first_video, "First local folder: " + first_local_folder, "Cleaned folder: " + cleaned_folder])
			if get_setting(['channels', 'error']):
				channel_name_static = settings['channels']['error']
				error_channel_set = True
				return None
			else:
				exit()
		random_episode = meta.get("random video on first play", False)
		if random_episode: # if this is the first time playing the show, play a random episode, otherwise play in order, overriding the global "random video on first play" setting if it is set in the metadata for this schedule block
			full_url = "http://127.0.0.1/?get_next_episode=" + urllib.quote_plus(ensure_string(episodes[0]) + "&random=1")
		if not random_episode: # this might be set to override the "random video on first play" global setting and force it to play the first video in the folder on the first play, then in order after that
			full_url = "http://127.0.0.1/?get_next_episode=" + urllib.quote_plus(ensure_string(episodes[0]) + "&random=0")
		else: # if the global setting is not overridden, or even set at all, let the server decide what to play
			full_url = "http://127.0.0.1/?get_next_episode=" + urllib.quote_plus(ensure_string(episodes[0]))
		
		urlcontents = open_url(full_url)

		printd("OS-Response:", urlcontents)
		printd("OS-Sent:", episodes[0])

		acontents = urlcontents.split("|")
		try:
			if acontents[1] == "0" or "|" not in urlcontents:
				printd("Server returned no next episode, defaulting to first")
				return [episodes[0]]
			elif os.path.exists(acontents[0]):
				printd("Ordered show selected:", acontents[0])
				return [acontents[0]]
			else:
				report_error("ORDERED-SHOW", ["File does not exist", acontents[0]])
				printd("Fallback: playing random episode from folder")
				return episodes
		except Exception as e:
			report_error("ORDERED-SHOW", [urlcontents, str(e)])
		return None

	elif video_type == "show":
		printd("Show mode activated")
		subfolders = get_folders_from_dir(replace_all_special_words(folders[0]))
		if not subfolders:
			report_error("SHOW", ["No subfolders found", folders[0]])
			return None
		selfolder = random.choice(subfolders)
		printd("Selected folder:", selfolder)
		return get_videos_from_dir_cached(selfolder)

	else:
		printd("Checking for plugin handler:", video_type)
		for name, plugin in PLUGINS.items():
			if hasattr(plugin, "keywords") and video_type in plugin.keywords:
				if hasattr(plugin, "handle"):
					ret_handle = plugin.handle(video_type, schedule)
					handled = ret_handle[0]
					plugin_file = ret_handle[1]
					printd("PLUGIN HANDLED:", handled, "FILE:", plugin_file)
					if handled:
						if meta.get('minimum-before-repeat'):
							repeatedly_register_playable(meta, True, get_length_from_file(plugin_file))
							printd("Tracked Plays Updated:", repeatedly_tracked_plays)
						return { "handled_by_plugin": True,	"meta": meta, "file": plugin_file }

		report_error("Programming Schedule", ["Unknown type", video_type])
		if get_setting(['channels', 'error']):
			channel_name_static = settings['channels']['error']
			error_channel_set = True
			return None
		else:
			exit()

def prepare_commercials_and_bumpers(source, schedule):
	"""
	Determines how many commercials to insert and whether to use bumpers.

	:param source: The source video file path.
	:param schedule: The programming schedule entry.
	Returns a tuple: (commercials_per_break, pregenerated_commercials_list, bumpers)
	"""
	global current_video_tag

	meta = schedule[4]
	video_type = schedule[2]
	source_dir = os.path.dirname(source)
	source_total_length = get_length_from_file(source)
	commercials = get_commercials(source)
	bumpers = None
	pregenerated_commercials_list = None

	# Handle tag override
	if 'set-tag' in meta:
		current_video_tag = meta['set-tag']
	source_tag = get_video_tag(source)
	if source_tag:
		current_video_tag = source_tag

	printd("Preparing commercials and bumpers for:", source)
	printd("Video type:", video_type)
	printd("Source length:", source_total_length)

	if video_type == "commercial":
		printd("Playing a commercial video, skipping commercials")
		return (0, None, None)

	if not settings.get('insert_commercials', True):
		printd("Commercial insertion disabled in settings")
		return (1, None, None)

	if settings['commercials_per_break'] == "auto" and video_type != "commercial" and commercials:
		printd("Auto commercial mode activated")
		commercials_per_break = 0

		bumpers_in = []
		bumpers_out = []
		bumpers_in_chance = 1.0
		bumpers_out_chance = 1.0

		if os.path.isdir(source_dir + "/bumpers/in"):
			bumpers_in.append(source_dir + "/bumpers/in")
		if os.path.isdir(source_dir + "/bumpers/out"):
			bumpers_out.append(source_dir + "/bumpers/out")

		if 'bumpers' in meta and not os.path.isfile(source_dir + "/bumpers/skip"):
			bmeta = meta['bumpers']
			printd("Bumper metadata found:", bmeta)

			if 'in' in bmeta and (not os.path.isdir(source_dir + "/bumpers/in") or bmeta.get('show-override', {}).get('in')):
				for item in bmeta['in']:
					temp = replace_all_special_words(item)
					if os.path.isdir(temp):
						bumpers_in.append(temp)
					else:
						report_error("BUMPERS", ["bumpers 'IN' directory does not exist", "SOURCE", source, "BUMPERS IN", temp])
				bumpers_in_chance = bmeta.get('chance', {}).get('in', "1.0")

			if 'out' in bmeta and (not os.path.isdir(source_dir + "/bumpers/out") or bmeta.get('show-override', {}).get('out')):
				for item in bmeta['out']:
					temp = replace_all_special_words(item)
					if os.path.isdir(temp):
						bumpers_out.append(temp)
					else:
						report_error("BUMPERS", ["bumpers 'OUT' directory does not exist", "SOURCE", source, "BUMPERS OUT", temp])
				bumpers_out_chance = bmeta.get('chance', {}).get('out', "1.0")

		if bumpers_in or bumpers_out:
			bumpers = generate_bumpers_list(
				bumpers_in, bumpers_out,
				len(commercials) + 1,
				bumpers_in_chance,
				bumpers_out_chance
		 )
			printd("Bumpers generated:", bumpers)

		if source_total_length is None:
			report_error("COMM_BREAK", ["length of video could not be found", "SOURCE", source])
			return (0, None, bumpers)

		fill_time = calculate_fill_time(
			source_total_length,
			now,
			get_setting(["commercials_offset_time"], 0)
		)

		printd("Fill time calculated:", fill_time)

		if bumpers:
			fill_time -= (bumpers['in_total'] + bumpers['out_total'])
			printd("Adjusted fill time after bumpers:", fill_time)

		pregenerated_commercials_list = generate_commercials_list(
			fill_time,
			get_setting(["commercials_fill_time_multiplier"], 50)
		)

		printd("Pregenerated commercials list:", pregenerated_commercials_list)
		return (0, pregenerated_commercials_list, bumpers)

	if not is_number(settings['commercials_per_break']):
		printd("Invalid commercials_per_break setting. Defaulting to 0.")
		return (0, None, None)

	printd("Fixed commercial count mode. Count:", settings['commercials_per_break'])
	return (int(settings['commercials_per_break']), None, None)

def onStartup():
	"""Performs startup tasks such as reporting that the script is running."""

	# Wait up to 30 seconds for the web server to accept HTTP requests on boot
	server_ready = False
	for _ in range(30):
		try:
			# Query an endpoint or check if the server is returning actual data
			res = urllib2.urlopen("http://127.0.0.1/?get_last_played=1", timeout=2)
			if res.getcode() == 200:
				server_ready = True
				break
		except Exception:
			sleep(1)

	if not server_ready:
		print("Failed to connect to web server after 30 seconds.")

	report_error("STARTUP", ["Script is now running!", "Script Version: " + ensure_string(SCRIPT_VERSION), "Settings version: " + ensure_string(SETTINGS_VERSION)])

	# 1. Safely grab the list of workers from settings.json
	worker_list = get_setting(['workers'], default=[], force_type=list)
	# 2. Iterate and trigger each PHP maintenance script
	for worker in worker_list:
		# Clean the worker path/string
		worker_path = ensure_string(worker)		
		# Construct the full local URL
		full_url = "http://127.0.0.1/" + worker_path		
		# Trigger the PHP worker
		response = open_url(full_url)

		if response:
			# Ensure it's a string
			clean_res = ensure_string(response)			
			# Split the "1|Message" into ['1', 'Message']
			parts = clean_res.split('|', 1)
			
			status = parts[0]
			message = parts[1] if len(parts) > 1 else "No message provided"

			if status == "1":
				# Log success - maybe print to console or a startup file
				printd("WORKER_SUCCESS", [worker_path, message])
			else:
				# Log error - report_error would be useful here
				report_error("WORKER_FAIL", [worker_path, message])
		else:
			report_error("WORKER_TIMEOUT", [worker_path, "No response from local server"])

onStartup() # Call the onStartup function to report that the script has started

while True:
	try:
		# Error channel timeout
		if (time.time() - start_time) > 59 and error_channel_set: # if an error channel is set and no video has played in 60 seconds, exit script
			report_error("MAIN_LOOP", [	"Error channel was set but no video is playing.", "Check settings.json for proper error channel.", "Ensure error channel directory exists and has videos.", "Script will now exit."	])
			exit()

		# No video played timeout
		if (time.time() - start_time) > 59: # if no video has played in 60 seconds, try to enter error channel mode
			report_error("MAIN_LOOP", ["No Video Available to Play", "Check for misspelled or missing schedule names.", "Entering Error Channel mode or exiting." ])
			if get_setting(['channels', 'error']): # if an error channel is set, enter error channel mode
				channel_name_static = settings['channels']['error']
				error_channel_set = True
				start_time = time.time()
				printd("Error channel activated:", channel_name_static)
			else: # no error channel set, exit script
				report_error("MAIN_LOOP", ["No error channel set. Exiting."])
				exit()

		# Reload settings if changed
		if os.path.getmtime(settings_file) != settings['load_time']:
			update_settings() # Refresh settings
			refresh_plugins() # Refresh plugins
			repeatedly_reset() # Reset "repeatedly" tracked times
			report_error("SETTINGS", ["Settings Updated"]) #
			printd("Settings reloaded due to file change")

		# Default commercial break count
		if 'commercials_per_break' not in settings:
			settings['commercials_per_break'] = 5
			printd("Default commercials_per_break set to 5")

		update_current_time()

		if 'time_test' in settings and settings['time_test'] is not None:
			report_error("TEST_TIME", ["Using Test Date Time " + ensure_string(now)])
			printd("Test time override active:", now)

		# Resolve programming schedule
		programming_schedule = resolve_programming_schedule_block()
		if programming_schedule is None:
			printd("No programming schedule resolved. Continuing loop.")
			continue

		printd("Programming Schedule:", programming_schedule)

		# Resolve video selection
		reported_video_type = "commercial" if programming_schedule[2]=="commercial" else "video"
		video = resolve_video_by_type(programming_schedule, programming_schedule[0])

		if isinstance(video, dict) and video.get("handled_by_plugin"):
			if video.get("file") is None:
				report_error("PLAY", ["Plugin returned no file to play", video.get("meta")])
				printd("Plugin returned no file")
			else:
				end_of_loop_tasks()
				printd("Handled by plugin:", video.get("meta"), video.get("file"))
			continue

		if video is None or len(video) == 0:
			report_error("PLAY", ["No videos found", "Looked: " + str(programming_schedule[0]), "Programing", programming_schedule])
			printd("No videos returned from 'resolve_video_by_type'")
			continue

		# Select final source
		source = random.choice(video)
		if source is None:
			report_error("PLAY", ["No source video selected", programming_schedule[0], programming_schedule[1], programming_schedule[2]])
			printd("Source selection failed")
			continue

		printd("Selected source:", source)

		# Commercials and bumpers
		commercials_per_break, pregenerated_commercials_list, bumpers = prepare_commercials_and_bumpers(source, programming_schedule)

		printd("Attempting to play:", source)
		printd("PLAY INFO:", [str(now), programming_schedule])

		success = 0
		play_video_attempts = 0
		max_attempts = get_setting(['max_play_attempts'], 1)
		while(success == 0 and play_video_attempts < max_attempts):
			success = play_video(
				source,
				get_commercials(source),
				commercials_per_break if pregenerated_commercials_list is None else pregenerated_commercials_list,
				0,
				bumpers,
				reported_video_type
			)
			play_video_attempts += 1
			if success == 0:
				if play_video_attempts < max_attempts:
					report_error("PLAY_ATTEMPT", ["Video failed to play, retrying...", "Attempt #" + ensure_string(play_video_attempts) + " of " + ensure_string(max_attempts), "Source", ensure_string(source)])


		if programming_schedule[4].get('minimum-before-repeat'):
			repeatedly_register_playable(
				programming_schedule[4],
				True,
				get_length_from_file(source)
			)
			printd("Registered playback for:", programming_schedule[4])

		if error_channel_set:
			report_error("MAIN_LOOP", ["Exited Error Channel mode, video played successfully"])
			error_channel_set = False
			channel_name_static = None
			printd("Error channel cleared")

		end_of_loop_tasks()

	except Exception as e:
		report_error("MAIN_LOOP", ["Unhandled exception", str(e), traceback.format_exc()])
		printd("Exception caught in main loop:", str(e))