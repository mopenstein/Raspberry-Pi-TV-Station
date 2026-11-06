#
# Broadcast State Manager for Raspberry Pi TV Emulation
#

import json
import os
import signal
import threading
import time
import urllib
import copy

class BroadcastStateManager(object):
	"""
	A thread-safe, multi-layer composition manifest for Raspberry Pi TV emulation.
	Handles atomic RAM-disk writing via a dedicated background daemon with 
	world-writable permissions (0666) across all system users (pi, root, www-data).
	"""
	def __init__(self, filepath="/dev/shm/tv_status.json"):
		self.filepath = filepath
		self.tmp_filepath = filepath.replace(".json", ".tmp")
		self.pid_filepath = filepath.replace(".json", ".pid")
		
		# Ensure permissive access on any existing files immediately
		self._ensure_world_permissions(self.filepath)
		self._ensure_world_permissions(self.tmp_filepath)
		self._ensure_world_permissions(self.pid_filepath)

		# Terminate any orphaned instance writing to this state file
		self._kill_previous_instances()

		self.lock = threading.Lock()
		self._running = False
		self._writer_thread = None
		
		# The 3-Tier Stage Blueprint
		self.state = {
			"main": {"state": "offline", "type": "video", "file": "", "position": 0, "volume": 1.0, "visible": True},
			"overrides": {},
			"submedia": {}
		}

	def _ensure_world_permissions(self, path):
		"""Set world read/write permissions (0666) on a file if it exists."""
		try:
			if os.path.exists(path):
				os.chmod(path, 0666)
		except OSError:
			pass

	def _kill_previous_instances(self):
		"""Check for a running predecessor, terminate it, and claim the PID file."""
		my_pid = os.getpid()

		if os.path.exists(self.pid_filepath):
			try:
				with open(self.pid_filepath, "r") as f:
					old_pid = int(f.read().strip())

				if old_pid != my_pid:
					os.kill(old_pid, 0)
					os.kill(old_pid, signal.SIGKILL)
					time.sleep(0.05)
			except (OSError, ValueError):
				pass

		try:
			with open(self.pid_filepath, "w") as f:
				f.write(str(my_pid))
			self._ensure_world_permissions(self.pid_filepath)
		except Exception:
			pass

	def _encode_path(self, path):
		"""URL-encode the file path for safe web transmission in Python 2.7."""
		if not path:
			return ""
		return urllib.quote(path)

	def update_main(self, state, media_type, file_path, position, volume=1.0, visible=True):
		"""Update the permanent background broadcast."""
		with self.lock:
			self.state["main"].update({
				"state": state,
				"type": media_type,
				"file": self._encode_path(file_path),
				"position": position,
				"volume": volume,
				"visible": visible
			})

	def set_override(self, layer_id, media_type, file_path, position, volume=1.0, visible=True, **kwargs):
		"""Inject media that forces the main broadcast to pause (e.g., commercials)."""
		payload = {
			"type": media_type,
			"file": self._encode_path(file_path),
			"position": position,
			"volume": volume,
			"visible": visible
		}
		payload.update(kwargs)
		with self.lock:
			self.state["overrides"][layer_id] = payload

	def clear_override(self, layer_id):
		"""Remove an override and allow the main broadcast to resume."""
		with self.lock:
			if layer_id in self.state["overrides"]:
				del self.state["overrides"][layer_id]

	def set_submedia(self, layer_id, media_type, file_path, position, volume=1.0, visible=True, **kwargs):
		"""Inject independent layers (PiP, audio beds) alongside the broadcast."""
		payload = {
			"type": media_type,
			"file": self._encode_path(file_path),
			"position": position,
			"volume": volume,
			"visible": visible
		}
		payload.update(kwargs)
		with self.lock:
			self.state["submedia"][layer_id] = payload

	def clear_submedia(self, layer_id):
		"""Remove an independent layer."""
		with self.lock:
			if layer_id in self.state["submedia"]:
				del self.state["submedia"][layer_id]

	def start(self):
		"""Launch the dedicated RAM-writing daemon."""
		if not self._running:
			self._running = True
			self._writer_thread = threading.Thread(target=self._writer_loop)
			self._writer_thread.daemon = True 
			self._writer_thread.start()

	def stop(self):
		"""Gracefully halt the writer thread."""
		self._running = False
		if self._writer_thread:
			self._writer_thread.join()

	def _writer_loop(self):
		"""
		Takes a snapshot of state and executes an atomic POSIX rename to RAM disk.
		Applies 0666 permissions to the tmp file prior to rename so the resulting 
		json file is always readable and overwritable by any user.
		"""
		while self._running:
			with self.lock:
				snapshot = copy.deepcopy(self.state)
			
			try:
				with open(self.tmp_filepath, "w") as f:
					json.dump(snapshot, f)
				
				# Enforce rw-rw-rw- so another user's process can overwrite/rename later
				try:
					os.chmod(self.tmp_filepath, 0666)
				except OSError:
					pass

				# Atomic swap
				os.rename(self.tmp_filepath, self.filepath)

			except Exception as e:
				pass
			
			time.sleep(1.0)

# Instantiate a global instance so all imports share the exact same memory space
broadcast_state = BroadcastStateManager()