import os
import re
import sys
import math
import subprocess

TARGET_EXTENSIONS = {
	".3g2", ".3gp", ".asf", ".avi", ".flv", ".h264",
	".m2t", ".m2ts", ".m4a", ".m4v", ".mkv", ".mod",
	".mov", ".mp3", ".ogg", ".wav", "wav", ".mp4",
	".mpg", ".png", ".tod", ".ts", ".vob", ".webm", ".wmv"
}

TAG_REGEX = re.compile(r"%T\(\d+\)%")


def get_accurate_video_length(filepath):
	"""
	Probes the video stream (v:0) duration using ffprobe.
	Falls back to container duration if no video stream exists (e.g. audio files).
	Returns duration in float seconds, or None if probe fails.
	"""
	cmd = [
		"ffprobe",
		"-v", "error",
		"-select_streams", "v:0",
		"-show_entries", "stream=duration",
		"-of", "default=noprint_wrappers=1:nokey=1",
		filepath
	]

	try:
		output = subprocess.check_output(cmd).strip()
		if output and output != "N/A":
			return float(output)
	except Exception:
		pass

	# Fallback: container duration for audio-only or missing stream duration tags
	cmd_fallback = [
		"ffprobe",
		"-v", "error",
		"-show_entries", "format=duration",
		"-of", "default=noprint_wrappers=1:nokey=1",
		filepath
	]

	try:
		output = subprocess.check_output(cmd_fallback).strip()
		if output and output != "N/A":
			return float(output)
	except Exception as e:
		print("  [ERROR] ffprobe failed on: %s (%s)" % (filepath, str(e)))

	return None


def process_directory(root_dir):
	"""
	Recursively scans directory and updates %T(###)% tags using ceiling duration.
	Also renames associated .commercials sidecar files if present.
	"""
	for dirpath, _, filenames in os.walk(root_dir):
		for filename in filenames:
			# Check extension (case-insensitive)
			ext = os.path.splitext(filename)[1].lower()
			if ext not in TARGET_EXTENSIONS and filename.lower().endswith("wav"):
				continue
			if ext not in TARGET_EXTENSIONS:
				continue

			# Skip sidecar and metadata files
			if filename.endswith(".commercials"):
				continue

			full_path = os.path.join(dirpath, filename)
			base_name, file_ext = os.path.splitext(filename)

			duration_sec = get_accurate_video_length(full_path)
			if duration_sec is None or duration_sec <= 0:
				print("[SKIP] Could not determine duration: %s" % filename)
				continue

			# Round up to nearest whole second
			duration_rounded = int(math.ceil(duration_sec))
			new_tag = "%%T(%d)%%" % duration_rounded

			# Replace existing tag or append new one
			if TAG_REGEX.search(base_name):
				new_base_name = TAG_REGEX.sub(new_tag, base_name)
			else:
				new_base_name = "%s%s" % (base_name, new_tag)

			new_filename = "%s%s" % (new_base_name, file_ext)
			new_full_path = os.path.join(dirpath, new_filename)

			# Nothing to change
			if full_path == new_full_path:
				continue

			if os.path.exists(new_full_path):
				print("[WARN] Target exists, skipping: %s" % new_filename)
				continue

			# Rename main media file
			try:
				os.rename(full_path, new_full_path)
				print("[RENAMED] %s -> %s (Raw: %.2fs)" % (filename, new_filename, duration_sec))
			except Exception as e:
				print("[ERROR] Renaming failed for %s: %s" % (filename, str(e)))
				continue

			# Rename matching .commercials sidecar if it exists
			old_sidecar = full_path + ".commercials"
			new_sidecar = new_full_path + ".commercials"

			if os.path.isfile(old_sidecar):
				try:
					os.rename(old_sidecar, new_sidecar)
					print("  [SIDECAR] Renamed: %s.commercials" % new_filename)
				except Exception as e:
					print("  [ERROR] Sidecar rename failed: %s" % str(e))


if __name__ == "__main__":
	if len(sys.argv) < 2:
		print("Usage: python script.py /path/to/media/directory")
		sys.exit(1)

	target_directory = sys.argv[1]
	if not os.path.isdir(target_directory):
		print("Directory does not exist: %s" % target_directory)
		sys.exit(1)

	print("Scanning %s..." % target_directory)
	process_directory(target_directory)
	print("Finished.")