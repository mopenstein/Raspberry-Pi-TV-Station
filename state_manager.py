#
# Broadcast State Manager for Raspberry Pi TV Emulation
#

import json
import os
import threading
import time
import urllib
import copy

class BroadcastStateManager(object):
	"""
	A thread-safe, multi-layer composition manifest for Raspberry Pi TV emulation.
	Handles atomic RAM-disk writing via a dedicated background daemon.
	"""
	def __init__(self, filepath="/dev/shm/tv_status.json"):
		self.filepath = filepath
		self.tmp_filepath = filepath.replace(".json", ".tmp")
		self.lock = threading.Lock()
		self._running = False
		self._writer_thread = None
		
		# The 3-Tier Stage Blueprint
		self.state = {
			"main": {"state": "offline", "type": "video", "file": "", "position": 0, "volume": 1.0, "visible": True},
			"overrides": {},
			"submedia": {}
		}

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
		payload.update(kwargs) # Accepts coords, opacity, etc.
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
		The single traffic cop for the file system. Wakes up once a second, 
		safely clones the dictionary, and executes an atomic write to RAM.
		"""
		while self._running:
			# Lock briefly to snap a perfect copy of the dictionary
			with self.lock:
				snapshot = copy.deepcopy(self.state)
			
			# Release lock immediately to unblock plugins, then execute disk I/O
			try:
				with open(self.tmp_filepath, "w") as f:
					json.dump(snapshot, f)
				os.rename(self.tmp_filepath, self.filepath)
			except Exception:
				pass # Fail silently so I/O errors never crash the backend
			
			time.sleep(1.0)

# Instantiate a global instance so all imports share the exact same memory space
broadcast_state = BroadcastStateManager()