import argparse

from time import sleep			# used to give time for the player to load the video file
import math						# math functions
import os						# for path directory access
import random					# choosing random stuff
import time						# time functions
import datetime					# date and time, used for testing purposes
import re						# regular expressions
import calendar					# used in special date calculating
import json						# settings file is in json format
import traceback				# for debugging and error reporting
import hashlib					# for generating hash IDs
import ast						# for safely evaluating mathematical expressions

# Allowed AST nodes for safe evaluation of mathematical expressions
import ast

ALLOWED_AST_NODES = (
    ast.Expression, # Root wrapper for mode='eval'
    ast.BinOp,      # Binary operations: +, -, *, /, %, **
    ast.UnaryOp,    # Unary operations: -x, +x
    ast.operator,   # Base class matching Add, Sub, Mult, Div, Mod, Pow, etc.
    ast.unaryop,    # Base class matching USub (-x), UAdd (+x)
    ast.Num,        # Numbers (int, float, long)
    ast.Name,       # Variable/function identifiers: 'day', 'min', 'clamp'
    ast.Load,       # Identifier read context
    ast.Call,       # Function calls: clamp(...), min(...)
    ast.Compare,    # Comparisons: ==, !=, <, <=, >, >=
    ast.cmpop,      # Base class matching Eq, NotEq, Lt, Gt, etc.
    ast.IfExp,      # Ternary expressions: 'x if cond else y'
    ast.BoolOp,     # Boolean expressions: 'a and b', 'x or y'
    ast.boolop,     # Base class matching And, Or
)

output_print = []

def var_dump(variable):
	#pp = pprint.PrettyPrinter(indent=4)
	#pp.pprint(variable)
	output_print.append[{ "var_dump": variable}]

last_channel_name = None;
def get_current_channel():
	global channel_name_static, settings, last_channel_name

	# Return static channel if set
	if channel_name_static is not None:
		return channel_name_static

	# Check if external channel file setting exists
	channels = settings.get('channels')
	if not channels:
		return None

	file_path = channels.get('file')
	if not file_path or not os.path.exists(file_path):
		return None

	with open(file_path) as file:
		line = file.read().strip()  # Strip to remove any extraneous whitespace

	if line != last_channel_name:
		nc = line if line else "default"
		report_error("CHANNEL", ["Channel set to: " + nc])
		last_channel_name = line

	return line if line else None

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

def validate_json(json_str):
	try:
		return json.loads(json_str, cls=ReferenceDecoder)
	except ValueError as err:
		return None

def get_setting(find, default=None):
	"""
	Searches the settings global variable for the specified setting and returns it.

	:param find: A dictionary to search for in the settings dictionary.
	:param default: The default value to return if the setting is not found. Set to None by default.
	:return: The value of the setting if found, otherwise the default value.

	Example usage:

	>>> settings = {
	...	 "version": "0.992",
	...	 "drive": ["/media/pi/ssd", "/media/pi/ssd_b"],
	...	 "channels": {
	...		 "error": "Error",
	...		 "nested": {
	...			 "deep": {
	...				 "value": 123
	...			 }
	...		 }
	...	 }
	... }
	>>> get_setting(['version'])
	'0.992'
	>>> get_setting(['drive', 0])
	'/media/pi/ssd'
	>>> get_setting(['channels', 'error'])
	'Error'
	>>> get_setting(['channels', 'nested', 'deep', 'value'])
	123
	>>> get_setting(['channels', 'missing'], 'not found')
	'not found'
	>>> get_setting(['drive', 10], 'fallback')
	'fallback'
	"""

	global settings
	try:
		temp = settings
		for k in find:
			if k in temp:
				temp = temp[k]
			else:
				return default
		return temp
	except Exception as e:
		report_error("GET_SETTING", ["Error accessing settings", str(e)])
		return default

def report_error(type, input, local_only=False):
	global output_print
	#print(type)
	#print(input)
	if { "type": type, "input": input 	} not in output_print:
		output_print.append({ "type": type, "input": input 	})

def now_totheminute():
	now = datetime.datetime.now()
	jt_string = now.strftime("%d/%m/%Y %H:%M")
	return datetime.datetime.strptime(jt_string, "%d/%m/%Y %H:%M")

def wildcard_array():
	return ['all', 'any', '*']

def IsXmas(daysFromXmas):
	global now # always use the global datetime 'now' so it doesn't break test dates
	
	xmas = datetime.datetime.strptime(str("Dec 25 " + str(now.year) + " " + str(now.hour).zfill(2) + ":" + str(now.minute).zfill(2)), '%b %d %Y %H:%M')
	target_date = xmas + datetime.timedelta(days=daysFromXmas)
	return is_date_within_range(now, xmas, daysFromXmas)

def IsThanksgiving(daysFromThanksgiving):
	global now # always use the global datetime 'now' so it doesn't break test dates
	
	year = str(now.year)
	d = datetime.datetime.strptime(str("Nov 1 " + year), '%b %d %Y')
	dw = d.weekday()
	thanksgiving = datetime.datetime.strptime(str("Nov " + str(22 + (10 - dw) % 7) + " " + str(d.year) + " " + str(now.hour).zfill(2) + ":" + str(now.minute).zfill(2)), '%b %d %Y %H:%M')
	target_date = thanksgiving + datetime.timedelta(days=daysFromThanksgiving)
	return is_date_within_range(now, thanksgiving, daysFromThanksgiving)

def PastThanksgiving(is_thanksgiving):
	global now # always use the global datetime 'now' so it doesn't break test dates

	
	year = str(now.year)
	d = datetime.datetime.strptime(str("Nov 1 " + year), '%b %d %Y')
	dw = d.weekday()
	datme = datetime.datetime.strptime(str("Nov " + str(22 + (10 - dw) % 7) + " " + str(d.year) + " " + str(now.hour).zfill(2) + ":" + str(now.minute).zfill(2)), '%b %d %Y %H:%M')
	#print(now, datme)
	if(is_thanksgiving==True): #check if today is thanksgiving
		if now==datme and now.month==now.month:
			return True
	else:
		if now > datme:
			if now.month>=12 and now.day>25:
				#it's past thanksgiving and also past xmas
				return False
			else:
				#past thanksgiving and is xmas or before
				return True
	return False


def is_date_within_range(date1, date2, range_days):
	"""
	Checks if date1 is within range_days of date2.
	
	:param date1: The first date to check (datetime.date).
	:param date2: The second date to check (datetime.date).
	:param range_days: The range in days (int). Positive for future range, negative for past range.
	:return: True if date1 is within range_days of date2, False otherwise.
	"""
	delta = (date1 - date2).days
	if (range_days >= 0 and 0 <= delta <= range_days) or (range_days < 0 and range_days <= delta <= 0):
		return True
	return False

def IsEaster(daysFromEaster):
	global now
	# Using the Anonymous Gregorian algorithm to calculate Easter Sunday
	year = now.year  # Determine the year from the global variable 'now'
	a = year % 19
	b = year // 100
	c = year % 100
	d = b // 4
	e = b % 4
	f = (b + 8) // 25
	g = (b - f + 1) // 3
	h = (19 * a + b - d - g + 15) % 30
	i = c // 4
	k = c % 4
	l = (32 + 2 * e + 2 * i - h - k) % 7
	m = (a + 11 * h + 22 * l) // 451
	month = (h + l - 7 * m + 114) // 31
	day = ((h + l - 7 * m + 114) % 31) + 1
	
	easter_sunday = datetime.date(year, month, day)
	#target_date = easter_sunday + datetime.timedelta(days=daysFromEaster)
	return is_date_within_range(datetime.date(now.year, now.month, now.day), easter_sunday, daysFromEaster)

from datetime import date, timedelta

def IsMothersDay(daysFromMothersDay):
	global now
	may_first = date(now.year, 5, 1)  # May 1st of the current year
	first_sunday = may_first + timedelta(days=(6 - may_first.weekday() + 7) % 7)  # First Sunday of May
	mothers_day_date = first_sunday + timedelta(weeks=1)  # Second Sunday of May
	#target_date = mothers_day_date + datetime.timedelta(days=daysFromMothersDay)
	return is_date_within_range(datetime.date(now.year, now.month, now.day), mothers_day_date, daysFromMothersDay)

def is_special_time(check):
	"""
	Checks if the current date matches a special holiday condition.
	
	:param check: A string representing the special holiday condition.
	:return: True if the current date matches the condition, False otherwise.
	"""


	if check[:4].lower() == 'xmas':
		if is_number(check[4:].strip()):
			num = int(check[4:])
		else:
			return True if IsThanksgiving(8) or IsXmas(-25) else False
		return True if IsXmas(num) else False

	if check[:9].lower() == 'christmas':
		if is_number(check[9:].strip()):
			num = int(check[9:])
		else:
			return True if IsThanksgiving(8) or IsXmas(-25) else False
		return True if IsXmas(num) else False

	if check[:12].lower() == 'thanksgiving':
			num = 0
			if is_number(check[12:].strip()):
					num = int(check[12:])
			return True if IsThanksgiving(num) else False

	if check[:6].lower() == 'easter':
		num = 0
		if is_number(check[6:].strip()):
			num = int(check[6:])
		return True if IsEaster(num) else False

	if check[:11].lower() == 'mothers day':
		num = 0
		if is_number(check[11:].strip()):
			num = int(check[11:])
		return True if IsMothersDay(num) else False

	return False

def is_valid_date(date_string):
	try:
		# Define the expected format
		format = "%b %d %Y %I:%M%p"
		
		# Try to parse the date string
		parsed = datetime.datetime.strptime(date_string, format)
		
		# Optional: return True if it matches, False otherwise
		return True
	except:
		return False

def update_current_time(report=True):
	global now, settings
	now = now_totheminute()  # Set the date time

	# Check if the settings file has a test date
	test_date = settings.get('time_test')
	if not is_valid_date(test_date) and test_date is not None:
		if report:
			report_error("TIME/DATE", ["Invalid test date format.", "Date supplied by user: '" + test_date + "'", "Expected format: 'Sep 09 2006 10:00PM'", "Defaulting to current system time."])
		return

	if test_date is not None:
		# If it does, set the global 'now' variable to the test date
		now = datetime.datetime.strptime(str(test_date), '%b %d %Y %I:%M%p')							

def update_settings():
	global settings, settings_file

	try:
		with open(settings_file, "r") as f:
			settings = validate_json(f.read())
	except Exception as e:
		report_error("SETTINGS", ["Could not read settings file: " + str(e)])
		sleep(10)
		exit()

	if settings is None:
		report_error("SETTINGS", ["Settings file isn't valid JSON"])
		sleep(10)
		exit()

	settings['load_time'] = os.path.getmtime(settings_file)

	version = settings.get('version')
	if version:
		if is_number(version):
			if float(version) != SETTINGS_VERSION:
				report_error("Settings", ["Settings version mismatch. Things might not work so well."])
		else:
			report_error("Settings", ["Settings version mismatch. Things might not work so well."])
	else:
		report_error("Settings", ["Settings version mismatch. Things might not work so well."])

def getDayOfWeek(d):
	return calendar.day_name[d].lower()
		
def getMonth(m):
	return ['invalid', 'january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'][m % 12]

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

def eval_equation(equation, now):
	"""
	Tries to evaluate a mathematical equation string and return the result.
	"""

	try:
		if isinstance(equation, (int, float, long)):
			return float(equation)

		equation = str(equation).strip()
		if is_number(equation):
			return float(equation)

		if len(equation) > 300:
			report_error("EVAL_EQUATION", ["Equation too long", equation])
			return -2

		# 1. Evaluate direct percentages first before passing to AST
		match = re.match(r'(\d+(?:\.\d+)?)%', equation.strip())
		if match and match.end() == len(equation.strip()):
			num = float(match.group(1))
			if 0.0 <= num <= 100.0:
				return float(num / 100.0)

		# 2. Parse into an AST expression tree
		tree = ast.parse(equation, mode='eval')

		# 3. Validate syntax against allowed nodes and convert ints to floats in-place
		for node in ast.walk(tree):
			if not isinstance(node, ALLOWED_AST_NODES):
				report_error("EVAL_EQUATION", ["Disallowed syntax in equation", equation, type(node).__name__])
				return -3
			if isinstance(node, ast.Attribute):
				report_error("EVAL_EQUATION", ["Disallowed attribute access in equation", equation, node.attr])
				return -3
			if isinstance(node, ast.Num) and isinstance(node.n, (int, long)):
				node.n = float(node.n)

		# 4. Compile the modified AST into bytecode
		code_obj = compile(tree, '<string>', 'eval')

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
			"scale": lambda x: float(x) / 100.0,
			"clamp": lambda x, y=1.0: max(0.0, min(y, float(x))),
			"bound": lambda x, low, high: max(low, min(high, float(x))),
			"stamp": (now - datetime.datetime(1970, 1, 1)).total_seconds(),
			"date": lambda y, m, d: (datetime.datetime(int(y), int(m), int(d)) - datetime.datetime(1970, 1, 1)).total_seconds(),
			"time": lambda h, m=0, s=0: int(h) * 3600.0 + int(m) * 60.0 + int(s),
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
		printd("day", safe_globals["day"], now.day)
		return eval(code_obj, safe_globals, {})
	except Exception:
		report_error("EVAL_EQUATION", ["Error evaluating equation", equation, LAST_PROGRAMMING_SCHEDULE, traceback.format_exc()])
		return -1

def get_short_month_name(month_number):
	month_abbr = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"]
	if 1 <= month_number <= 12:
		return month_abbr[month_number - 1]
	else:
		raise ValueError("Month number must be in the range 1-12")

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

# Function to replace %MAXDAYS% with the correct number of days in the month
def replace_special_words(date_str):
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

def is_dict(var):
	"""
	Checks if the variable is a dictionary.

	:param var: The variable to check.
	:return: True if the variable is a dictionary, False otherwise.
	"""
	return isinstance(var, dict)

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
				chance_eval = eval_equation(time_item['chance'], now)
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
	import re
	matches = re.findall(r'@([^@]+)@', video_file)
	if matches:
		return matches[-1].split()[-1]
	return None

def resolve_directory_placeholders(path_string, drives):
	"""
	Replaces %D[#]% placeholders in a path string with the corresponding drive path.
	"""
	def replacer(match):
		index_str = match.group(1)
		try:
			# Convert to integer and subtract 1 for 0-based list access
			index = int(index_str) - 1
			if 0 <= index < len(drives):
				return drives[index]
			else:
				print("WARNING: Drive index {} out of bounds in path: {}".format(index_str, path_string))
				return match.group(0)
		except ValueError:
			return match.group(0)

	# 1. Resolve %D[#]% placeholders
	resolved_path = re.sub(r'\%D\[(\d+)\]\%', replacer, path_string)
	
	# 2. Resolve %MONTH%, %DAY%, etc. placeholders
	final_path = replace_all_special_words(resolved_path)
	
	return final_path


def get_parent_dir(path):
	"""
	Given a path, returns the parent directory to check.
	Returns None if no directory component is found.
	"""
	if path.endswith('/') or path.endswith('\\'):
		# It's an explicit directory path
		return path
	else:
		# It might be a file; check its containing directory
		parent_dir = os.path.dirname(path)
		# If path is just a file name (no directory component), dirname returns ''
		return parent_dir if parent_dir else None


def verify_directories(config_data):
	"""
	Verifies that all specified directories exist on the filesystem.
	"""
	return_value = []
	dirs_to_check = set()
	drives = config_data.get('drive', [])
	
	# 1. Check 'drive' directories
	for d in drives:
		dirs_to_check.add(d)
	
	# 2. Check 'cache_path'
	cache_path = config_data.get('cache_path')
	if cache_path:
		dirs_to_check.add(cache_path)
	
	# 3. Check paths in 'times' (name and bumpers)
	times_config = config_data.get('times', [])
	for entry in times_config:
		# Check 'name' paths
		names = entry.get('name', [])
		for name_path in names:
				dirs_to_check.add(name_path)

		# Check 'bumpers' paths
		bumpers = entry.get('bumpers', {})
		for bumper_type in ['out', 'in']:
			paths = bumpers.get(bumper_type, [])
			for path in paths:
				dirs_to_check.add(path)

	# Perform the checks
	for dir_path in sorted(list(dirs_to_check)):
		if dir_path in ('', '.', '/'):
			continue
			
		normalized_path = resolve_directory_placeholders(os.path.normpath(dir_path), drives)
		
		if not os.path.isdir(normalized_path):
			return_value.append(["Real Path", normalized_path, "Settings Path", dir_path])
	
	return return_value

now = datetime.datetime.now()
channel_name_static = None
base_directory = os.path.dirname(__file__)
current_video_tag = None
LAST_PROGRAMMING_SCHEDULE = None

############################ settings
SETTINGS_VERSION = 0.996

if base_directory != "":
	base_directory = base_directory + "/" if base_directory[-1] != "/" else base_directory

settings = None
settings_file = base_directory + "settings.json"

update_settings()
############################ /settings


parser = argparse.ArgumentParser(description='Process video scheduling inputs.')

parser.add_argument('-time', dest='time_test', type=str, help='Time to test in format "Sep 09 2006 10:00:00PM"')
parser.add_argument('-file', dest='test_file', type=str, help='Test file name, e.g., "The man from uncle.mp4"')
parser.add_argument('-tag', dest='video_tag', type=str, help='Optional video tag, e.g., "kids_show"')
parser.add_argument('-chance', dest='chance_test', type=str, help='Test chance evaluated expression, e.g., "0.5 * weekday"')
parser.add_argument('-channel', dest='channel', type=str, help='Sets the current channel, e.g., "Movies"')

args = parser.parse_args()

settings.update({
	'time_test': args.time_test,
	'test_file': args.test_file,
	'video_tag': args.video_tag,
	'chance_test': args.chance_test,
	'channel': args.channel,
})

output = {}

update_current_time(settings['time_test']!="eval_equation")

if settings.get('chance_test', None) != None:
	report_error("Chance", [ eval_equation(settings['chance_test'], now) ])
	output['messages'] = output_print
	print(json.dumps(output))
	quit()

current_video_tag = None

if settings.get('test_file', None) != None:
	current_video_tag = get_video_tag(settings.get('test_file'))

if settings.get('video_tag', None) != None:
	current_video_tag = settings.get('video_tag')

if settings.get('channel', None) != None:
	report_error("CHANNEL", ["Channel set to: " +  settings.get('channel')])
	channel_name_static = settings.get('channel')

#print("Current Video Tag: " + str(current_video_tag))

report_error("DIRECTORY_CHECK", ["The following directories do not exist:", str(verify_directories(settings))])
programming_schedule = check_video_times(settings['times'], get_current_channel(), True)

output['programming'] = {}

if programming_schedule and programming_schedule[4]:
	for k in programming_schedule[4]:
		output['programming'][k]= str(programming_schedule[4][k])

	if 'set-tag' in programming_schedule[4]:
		if programming_schedule[4]['set-tag'] != None:
			current_video_tag = programming_schedule[4]['set-tag']
			report_error("TAG", ["Current video tag set to: " + current_video_tag])
else:
	output_print.append({ "PROGRAMMING": "No programming found for the current time." })

programming_schedule = check_video_times(settings['commercial_times'], get_current_channel(), True)
output['commercials'] = {}

if programming_schedule and programming_schedule[4]:
	for k in programming_schedule[4]:
		output['commercials'][k] = str(programming_schedule[4][k])
else:
	output_print.append({ "COMMERCIALS": "No commercials found for the current time." })

output['messages'] = output_print
 
print(json.dumps(output))