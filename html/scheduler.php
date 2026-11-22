<?php
require_once("scheduler_backend.php");

$schedule = getActiveScheduleData();
$active_filename = basename($schedule_file);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Schedule Manager</title>
<style>
	:root {
		--bg: #121417;
		--card-bg: #1a1e24;
		--border: #2b313a;
		--primary: #2f81f7;
		--primary-active: #1f6feb;
		--text: #e6edf3;
		--text-muted: #8b949e;
		--danger: #f85149;
		--danger-bg: #3c1e20;
		--warn: #e3b341;
		--warn-bg: #3d3419;
		--success: #3fb950;
	}

	* { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
	body {
		background: var(--bg);
		color: var(--text);
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
		padding-bottom: 85px;
	}

	header {
		background: #161b22;
		border-bottom: 1px solid var(--border);
		position: sticky;
		top: 0;
		z-index: 50;
		padding: 10px 14px;
	}

	.top-row {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 8px;
	}

	h1 { font-size: 1.05rem; font-weight: 700; }
	.file-badge {
		font-size: 0.72rem;
		color: var(--text-muted);
		background: #0d1117;
		padding: 2px 6px;
		border-radius: 4px;
		border: 1px solid var(--border);
	}

	.btn {
		background: var(--primary);
		color: #fff;
		border: none;
		padding: 8px 14px;
		border-radius: 6px;
		font-weight: 600;
		font-size: 0.85rem;
		cursor: pointer;
	}
	.btn:active { background: var(--primary-active); }
	.btn-sm { padding: 5px 9px; font-size: 0.75rem; }
	.btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text); }
	.btn-danger { background: var(--danger); }
	.btn-icon {
		background: transparent;
		border: 1px solid var(--border);
		color: var(--text);
		padding: 6px 12px;
		border-radius: 4px;
		font-size: 0.85rem;
		cursor: pointer;
	}

	.filter-mobile-wrapper {
		display: none;
		margin-top: 4px;
	}
	.mobile-cat-picker {
		width: 100%;
		background: #0d1117;
		border: 1px solid var(--border);
		color: var(--text);
		padding: 8px 12px;
		border-radius: 6px;
		font-size: 0.9rem;
		font-weight: 600;
		outline: none;
	}
	.mobile-cat-picker:focus { border-color: var(--primary); }

	.filter-scroll {
		display: flex;
		flex-wrap: wrap;
		gap: 6px;
		padding-top: 4px;
		max-height: 110px;
		overflow-y: auto;
	}

	.filter-chip {
		background: var(--card-bg);
		border: 1px solid var(--border);
		padding: 5px 12px;
		border-radius: 20px;
		font-size: 0.78rem;
		white-space: nowrap;
		color: var(--text-muted);
		cursor: pointer;
		user-select: none;
		display: inline-flex;
		align-items: center;
		gap: 6px;
	}
	.filter-chip.active {
		background: var(--primary);
		color: #fff;
		border-color: var(--primary);
		font-weight: 600;
	}

	.cat-color-dot {
		width: 8px;
		height: 8px;
		border-radius: 50%;
		display: inline-block;
	}

	@media (max-width: 768px) {
		.filter-scroll { display: none; }
		.filter-mobile-wrapper { display: block; }
	}

	main {
		max-width: 600px;
		margin: auto;
		padding: 12px 14px;
	}

	.add-card {
		background: var(--card-bg);
		border: 1px solid var(--border);
		border-radius: 8px;
		padding: 12px;
		margin-bottom: 14px;
	}
	.input-field {
		width: 100%;
		background: #0d1117;
		border: 1px solid var(--border);
		color: var(--text);
		padding: 9px 10px;
		border-radius: 6px;
		font-size: 0.85rem;
		margin-bottom: 8px;
	}
	.input-field:focus { border-color: var(--primary); outline: none; }
	.add-row {
		display: flex;
		gap: 8px;
	}

	.inline-input-group {
		background: #12161c;
		border: 1px dashed var(--primary);
		border-radius: 6px;
		padding: 8px;
		margin-top: 8px;
		display: flex;
		gap: 8px;
		align-items: center;
	}

	.show-card {
		background: var(--card-bg);
		border: 1px solid var(--border);
		border-radius: 8px;
		margin-bottom: 6px;
		overflow: hidden;
		transition: border-color 0.15s ease;
	}
	.show-card.expanded {
		border-color: var(--primary);
		background: #1e242d;
	}
	.show-summary {
		padding: 10px 12px;
		display: flex;
		justify-content: space-between;
		align-items: center;
		cursor: pointer;
		gap: 8px;
	}
	.show-title-text {
		font-size: 0.92rem;
		font-weight: 600;
		word-break: break-word;
	}
	.show-alias-sub {
		font-size: 0.73rem;
		color: var(--text-muted);
		display: block;
		margin-top: 1px;
	}

	.badge {
		font-size: 0.7rem;
		padding: 3px 6px;
		border-radius: 4px;
		font-weight: 600;
		white-space: nowrap;
	}
	.badge-warn { background: var(--warn-bg); color: var(--warn); border: 1px solid #5c4718; }
	.badge-cat { background: #1f2d42; color: #58a6ff; }

	.show-drawer {
		border-top: 1px solid #28303b;
		background: #14181f;
		padding: 12px;
		display: flex;
		flex-direction: column;
		gap: 10px;
	}
	.drawer-label {
		font-size: 0.72rem;
		color: var(--text-muted);
		text-transform: uppercase;
		letter-spacing: 0.5px;
		margin-bottom: 4px;
		display: block;
	}
	.drawer-row {
		display: flex;
		gap: 8px;
		align-items: center;
		justify-content: space-between;
	}
	.cat-select {
		background: #0d1117;
		border: 1px solid var(--border);
		color: var(--text);
		padding: 7px 10px;
		border-radius: 6px;
		font-size: 0.85rem;
		flex-grow: 1;
	}

	.color-ctrl-row {
		display: flex;
		align-items: center;
		gap: 10px;
		background: #0d1117;
		border: 1px solid var(--border);
		border-radius: 6px;
		padding: 5px 10px;
		margin-top: 8px;
	}
	.color-ctrl-row input[type="color"] {
		width: 32px;
		height: 26px;
		border: none;
		cursor: pointer;
		background: transparent;
	}
	.color-hex-label {
		font-family: monospace;
		font-size: 0.8rem;
		color: var(--text-muted);
	}

	.footer-bar {
		position: fixed;
		bottom: 0;
		left: 0;
		right: 0;
		background: #161b22;
		border-top: 1px solid var(--border);
		padding: 10px 14px;
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 8px;
		z-index: 50;
	}
	.footer-actions {
		display: flex;
		gap: 8px;
		align-items: center;
		flex-shrink: 0;
	}
	.status-text { font-size: 0.8rem; color: var(--text-muted); }

	.modal-backdrop {
		position: fixed;
		inset: 0;
		background: rgba(0, 0, 0, 0.72);
		display: none;
		align-items: center;
		justify-content: center;
		padding: 14px;
		z-index: 100;
	}
	.modal-backdrop.open {
		display: flex;
	}
	.modal-dialog {
		background: var(--card-bg);
		border: 1px solid var(--border);
		border-radius: 10px;
		width: 100%;
		max-width: 540px;
		padding: 14px;
		box-shadow: 0 12px 32px rgba(0, 0, 0, 0.55);
	}
	.modal-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 8px;
	}
	.modal-title {
		font-size: 0.95rem;
		font-weight: 700;
	}
	.modal-sub {
		font-size: 0.78rem;
		color: var(--text-muted);
		margin-bottom: 10px;
	}
	.bulk-textarea {
		width: 100%;
		min-height: 180px;
		background: #0d1117;
		border: 1px solid var(--border);
		color: var(--text);
		padding: 10px;
		border-radius: 6px;
		font-family: monospace;
		font-size: 0.8rem;
		resize: vertical;
		margin-bottom: 10px;
	}
	.bulk-textarea:focus { border-color: var(--primary); outline: none; }
</style>
</head>
<body>

<header>
	<div class="top-row">
		<div>
			<h1>Show Roster</h1>
			<span class="file-badge"><?= htmlspecialchars($active_filename) ?></span>
		</div>
		<button class="btn btn-sm" onclick="saveToServer()">Save Changes</button>
	</div>
	<div class="filter-mobile-wrapper">
		<select id="mobileCategorySelect" class="mobile-cat-picker" onchange="setFilter(this.value)">
		</select>
	</div>
	<div class="filter-scroll" id="filterScroll"></div>
</header>

<main>
	<section class="add-card">
		<input type="text" id="newShowInput" class="input-field" placeholder="Show Name (or SearchTarget=DisplayTitle)..." 
			onkeydown="if(event.key==='Enter') handleAddShow()" />
		
		<div class="add-row">
			<select id="newShowCatSelect" class="input-field" style="margin-bottom:0;flex-grow:1;" onchange="handleCatSelectChange(this.value)">
			</select>
			<button class="btn" style="flex-shrink:0;" onclick="handleAddShow()">Add Show</button>
		</div>

		<div id="inlineNewCatBox" style="display:none;margin-top:8px;">
			<input type="text" id="inlineCatInput" class="input-field" placeholder="Enter new category name (e.g. sum_Sunday)..." style="margin-bottom:0;" 
				onkeydown="if(event.key==='Enter') handleAddShow()" 
				oninput="this.style.borderColor='var(--border)'" />
		</div>
	</section>

	<section id="categoryManageCard" class="add-card" style="display:none;"></section>

	<div id="showsList"></div>
</main>

<div id="bulkJsonModal" class="modal-backdrop" onclick="if(event.target===this) closeBulkJsonModal()">
	<div class="modal-dialog">
		<div class="modal-header">
			<span class="modal-title">Bulk Import JSON</span>
			<button type="button" class="btn-icon" style="padding:2px 8px;" onclick="closeBulkJsonModal()">&times;</button>
		</div>
		<p class="modal-sub">Paste a JSON object mapping categories to arrays of artists/shows. New categories are created automatically and changes save immediately.</p>
		<textarea id="bulkJsonInput" class="bulk-textarea" placeholder='{"Top 40": ["Artist A", "Artist B"], "Hard Rock": ["Artist C"]}' oninput="this.style.borderColor='var(--border)'"></textarea>
		<div class="add-row" style="justify-content:flex-end;">
			<button type="button" class="btn btn-outline" onclick="closeBulkJsonModal()">Cancel</button>
			<button type="button" class="btn" onclick="handleBulkJsonImport()">Import &amp; Save</button>
		</div>
	</div>
</div>

<footer class="footer-bar">
	<span id="statusIndicator" class="status-text">Schedule ready</span>
	<div class="footer-actions">
		<button type="button" class="btn btn-outline" onclick="openBulkJsonModal()">Bulk JSON</button>
		<button type="button" class="btn" onclick="saveToServer()">Save Changes</button>
	</div>
</footer>

<script>
let schedule = <?= json_encode(empty($schedule) ? (object)[] :$schedule) ?>;
if (Array.isArray(schedule)) {
	schedule = {};
}
let categoryColors = <?= json_encode($show_type_colors) ?> || {};
let activeFilter = "ALL";
let expandedShowId = null;

function cleanTarget(str) {
	if (!str) return '';
	let target = str.indexOf('=') !== -1 ? str.split('=')[0] : str;
	return target.replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
}

function getDisplayName(str) {
	if (!str) return '';
	return str.indexOf('=') !== -1 ? str.split('=')[1] : str;
}

function findExistingShow(showName) {
	let clean = cleanTarget(showName);
	if (!clean) return null;

	for (let cat in schedule) {
		for (let i = 0; i < schedule[cat].length; i++) {
			if (cleanTarget(schedule[cat][i]) === clean) {
				return { category: cat, index: i, raw: schedule[cat][i] };
			}
		}
	}
	return null;
}

function openBulkJsonModal() {
	const modal = document.getElementById('bulkJsonModal');
	const input = document.getElementById('bulkJsonInput');
	input.style.borderColor = 'var(--border)';
	modal.classList.add('open');
	input.focus();
}

function closeBulkJsonModal() {
	document.getElementById('bulkJsonModal').classList.remove('open');
}

document.addEventListener('keydown', function(e) {
	if (e.key === 'Escape') {
		closeBulkJsonModal();
	}
});

function handleBulkJsonImport() {
	const input = document.getElementById('bulkJsonInput');
	const rawText = input.value.trim();

	if (!rawText) {
		input.style.borderColor = 'var(--danger)';
		input.focus();
		return;
	}

	let parsed;
	try {
		parsed = JSON.parse(rawText);
	} catch (e) {
		input.style.borderColor = 'var(--danger)';
		alert("Invalid JSON format:\n" + e.message);
		return;
	}

	if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
		input.style.borderColor = 'var(--danger)';
		alert('JSON must be an object mapping category names to arrays of items, e.g. {"Top 40": ["Artist 1"]}');
		return;
	}

	let addedCount = 0;
	let movedCount = 0;
	let skippedCount = 0;
	let lastCategory = null;

	for (let rawCat in parsed) {
		if (!Object.prototype.hasOwnProperty.call(parsed, rawCat)) continue;
		const catName = String(rawCat).trim();
		const items = parsed[rawCat];

		if (!catName || !Array.isArray(items)) continue;

		if (!schedule[catName]) {
			schedule[catName] = [];
		}

		items.forEach(rawItem => {
			if (typeof rawItem !== 'string' && typeof rawItem !== 'number') return;
			const itemStr = String(rawItem).trim();
			if (!itemStr) return;

			const existing = findExistingShow(itemStr);
			if (existing) {
				if (existing.category === catName) {
					skippedCount++;
				} else {
					schedule[existing.category].splice(existing.index, 1);
					schedule[catName].push(itemStr);
					movedCount++;
					lastCategory = catName;
				}
			} else {
				schedule[catName].push(itemStr);
				addedCount++;
				lastCategory = catName;
			}
		});
	}

	if (addedCount === 0 && movedCount === 0) {
		alert(`No new items added (${skippedCount} duplicate(s) already existed in their target categories).`);
		return;
	}

	input.value = '';
	closeBulkJsonModal();
	if (lastCategory) {
		activeFilter = lastCategory;
	}
	render();
	saveToServer();

	const status = document.getElementById('statusIndicator');
	status.innerText = `Bulk imported: ${addedCount} added, ${movedCount} moved, ${skippedCount} skipped`;
	status.style.color = "var(--success)";
}

function handleCatSelectChange(val) {
	const inlineBox = document.getElementById('inlineNewCatBox');
	const inlineInput = document.getElementById('inlineCatInput');
	
	if (val === "__CREATE_NEW__") {
		inlineBox.style.display = "block";
		inlineInput.style.borderColor = 'var(--border)';
		inlineInput.focus();
	} else {
		inlineBox.style.display = "none";
		inlineInput.value = '';
	}
}

function handleAddShow() {
	const nameInput = document.getElementById('newShowInput');
	const catSelect = document.getElementById('newShowCatSelect');
	const inlineBox = document.getElementById('inlineNewCatBox');
	const inlineInput = document.getElementById('inlineCatInput');

	const rawName = nameInput.value.trim();
	let targetCat = catSelect.value;

	if (!rawName) {
		nameInput.style.borderColor = 'var(--danger)';
		nameInput.focus();
		return;
	}
	nameInput.style.borderColor = 'var(--border)';

	if (targetCat === "__CREATE_NEW__") {
		const newCatName = inlineInput.value.trim();
		if (!newCatName) {
			inlineInput.style.borderColor = 'var(--danger)';
			inlineInput.focus();
			return;
		}
		targetCat = newCatName;
	}

	if (!targetCat) {
		catSelect.focus();
		return;
	}

	const existing = findExistingShow(rawName);
	if (existing) {
		let confirmMove = confirm(`"${rawName}" already exists in [${existing.category}].\n\nMove and reassign it to [${targetCat}]?`);
		if (confirmMove) {
			schedule[existing.category].splice(existing.index, 1);
			if (!schedule[targetCat]) schedule[targetCat] = [];
			schedule[targetCat].push(rawName);
			
			nameInput.value = '';
			inlineInput.value = '';
			inlineBox.style.display = "none";
			activeFilter = targetCat;
			markDirty();
			render();
		}
		return;
	}

	if (!schedule[targetCat]) {
		schedule[targetCat] = [];
	}

	schedule[targetCat].push(rawName);
	nameInput.value = '';
	inlineInput.value = '';
	inlineBox.style.display = "none";
	activeFilter = targetCat;
	markDirty();
	render();

	const updatedCatSelect = document.getElementById('newShowCatSelect');
	if (updatedCatSelect) updatedCatSelect.value = targetCat;
	nameInput.focus();
}

function toggleExpand(id) {
	expandedShowId = (expandedShowId === id) ? null : id;
	render();
}

function handleDrawerCatSelect(oldCat, index, val) {
	if (val === "__DRAWER_CREATE_NEW__") {
		const row = document.getElementById(`drawerNewCatRow__${oldCat}__${index}`);
		if (row) {
			row.style.display = "flex";
			const input = document.getElementById(`drawerNewCatInput__${oldCat}__${index}`);
			if (input) {
				input.value = '';
				input.focus();
			}
		}
	} else {
		reassignShow(oldCat, index, val);
	}
}

function commitDrawerNewCategory(oldCat, index) {
	const input = document.getElementById(`drawerNewCatInput__${oldCat}__${index}`);
	let newCat = input ? input.value.trim() : '';

	if (!newCat) return;

	if (!schedule[newCat]) {
		schedule[newCat] = [];
	}

	reassignShow(oldCat, index, newCat);
}

function cancelDrawerNewCategory(oldCat, index) {
	const row = document.getElementById(`drawerNewCatRow__${oldCat}__${index}`);
	if (row) row.style.display = "none";
	const select = document.getElementById(`drawerCatSelect__${oldCat}__${index}`);
	if (select) select.value = oldCat;
}

function reassignShow(oldCat, index, newCat) {
	if (oldCat === newCat) return;
	const showVal = schedule[oldCat][index];
	schedule[oldCat].splice(index, 1);
	if (!schedule[newCat]) schedule[newCat] = [];
	schedule[newCat].push(showVal);
	expandedShowId = null;
	markDirty();
	render();
}

function deleteShow(cat, index) {
	if (!confirm(`Delete "${schedule[cat][index]}" from schedule?`)) return;
	schedule[cat].splice(index, 1);
	expandedShowId = null;
	markDirty();
	render();
}

function commitRename(cat, index, newVal) {
	const trimmed = newVal.trim();
	if (!trimmed) {
		render();
		return;
	}

	const currentVal = schedule[cat][index];
	if (trimmed !== currentVal) {
		const existing = findExistingShow(trimmed);
		if (existing && !(existing.category === cat && existing.index === index)) {
			alert(`Cannot rename to "${trimmed}". Show already exists in [${existing.category}].`);
			render();
			return;
		}
		schedule[cat][index] = trimmed;
		markDirty();
	}
	render();
}

function setFilter(cat) {
	activeFilter = cat;
	expandedShowId = null;
	render();
}

function markDirty() {
	const el = document.getElementById('statusIndicator');
	el.innerText = "Unsaved changes";
	el.style.color = "var(--warn)";
}

function saveCategoryColor(cat, hexColor) {
	const status = document.getElementById('statusIndicator');
	status.innerText = `Saving color for [${cat}]...`;
	status.style.color = "var(--warn)";

	const cleanHex = hexColor.replace('#', '').toUpperCase();

	const fd = new FormData();
	fd.append('action', 'save_category_color');
	fd.append('category', cat);
	fd.append('color', cleanHex);

	fetch('scheduler_backend.php', { method: 'POST', body: fd })
		.then(r => r.json())
		.then(res => {
			if (res.status === 'success') {
				categoryColors[cat] = cleanHex;
				status.innerText = `Color saved for [${cat}]`;
				status.style.color = "var(--success)";
				render();
			} else {
				status.innerText = "Color save failed: " + res.message;
				status.style.color = "var(--danger)";
			}
		})
		.catch(err => {
			status.innerText = "Color save error: " + err.message;
			status.style.color = "var(--danger)";
		});
}

function saveToServer() {
	const status = document.getElementById('statusIndicator');
	status.innerText = "Saving atomically...";
	status.style.color = "var(--warn)";

	const payload = Object.assign({}, schedule);

	const fd = new FormData();
	fd.append('action', 'save_schedule');
	fd.append('data', JSON.stringify(payload));

	fetch('scheduler_backend.php', { method: 'POST', body: fd })
		.then(r => {
			if (!r.ok) throw new Error(`HTTP ${r.status}`);
			return r.json();
		})
		.then(res => {
			if (res.status === 'success') {
				status.innerText = res.message;
				status.style.color = "var(--success)";
			} else {
				status.innerText = "Save failed: " + res.message;
				status.style.color = "var(--danger)";
			}
		})
		.catch(err => {
			status.innerText = "Save error: " + err.message;
			status.style.color = "var(--danger)";
		});
}

function escapeHtml(str) {
	return (str || '').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}

function getCatColorHex(cat) {
	let raw = categoryColors[cat] || '58A6FF';
	return raw.startsWith('#') ? raw : '#' + raw;
}

function renderCategoryDropdown() {
	const addSelect = document.getElementById('newShowCatSelect');
	const inlineBox = document.getElementById('inlineNewCatBox');
	const inlineInput = document.getElementById('inlineCatInput');
	let categories = Object.keys(schedule).sort((a, b) => 
    	a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' })
	);
	let selectOptions = '';

	if (categories.length === 0) {
		selectOptions = `<option value="__CREATE_NEW__" selected>+ New Category...</option>`;
		addSelect.innerHTML = selectOptions;
		if (inlineBox) {
			inlineBox.style.display = "block";
			inlineInput.style.borderColor = 'var(--border)';
		}
		return;
	}

	categories.forEach(cat => {
		let isSelected = (activeFilter !== "ALL" && activeFilter === cat);
		selectOptions += `<option value="${cat}" ${isSelected ? "selected" : ""}>${cat}</option>`;
	});

	selectOptions += `<option value="__CREATE_NEW__">+ New Category...</option>`;
	addSelect.innerHTML = selectOptions;

	if (addSelect.value !== "__CREATE_NEW__" && inlineBox) {
		inlineBox.style.display = "none";
	}
}

function commitRenameCategory(oldCat) {
	const input = document.getElementById('renameCategoryInput');
	if (!input) return;
	const newCat = input.value.trim();

	if (!newCat || newCat === oldCat) {
		input.value = oldCat;
		return;
	}

	if (schedule[newCat]) {
		alert(`Category "${newCat}" already exists.`);
		input.focus();
		return;
	}

	const newSchedule = {};
	for (let cat in schedule) {
		if (cat === oldCat) {
			newSchedule[newCat] = schedule[oldCat];
		} else {
			newSchedule[cat] = schedule[cat];
		}
	}
	schedule = newSchedule;

	if (categoryColors[oldCat]) {
		categoryColors[newCat] = categoryColors[oldCat];
		saveCategoryColor(newCat, categoryColors[newCat]);
	}

	activeFilter = newCat;
	markDirty();
	render();
}

function deleteCategory(cat) {
	const count = schedule[cat] ? schedule[cat].length : 0;
	const msg = count > 0 
		? `Delete category "${cat}" and all ${count} show(s) inside it?` 
		: `Delete empty category "${cat}"?`;

	if (!confirm(msg)) return;

	delete schedule[cat];
	activeFilter = "ALL";
	markDirty();
	render();
}

function render() {
	let categories = Object.keys(schedule).sort((a, b) => 
		a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' })
	);
	let orderedCats = ["ALL", ...categories];

	// Filter Chips
	const filterScroll = document.getElementById('filterScroll');
	if (filterScroll) {
		filterScroll.innerHTML = orderedCats.map(cat => {
			let count = cat === "ALL" 
				? Object.values(schedule).reduce((acc, arr) => acc + arr.length, 0)
				: (schedule[cat] ? schedule[cat].length : 0);
			let isActive = activeFilter === cat ? "active" : "";
			let colorDot = (cat !== "ALL")
				? `<span class="cat-color-dot" style="background:${getCatColorHex(cat)};"></span>` 
				: '';
			return `<div class="filter-chip ${isActive}" onclick="setFilter('${cat}')">${colorDot}${cat} (${count})</div>`;
		}).join('');
	}

	// Mobile Dropdown
	const mobileSelect = document.getElementById('mobileCategorySelect');
	if (mobileSelect) {
		mobileSelect.innerHTML = orderedCats.map(cat => {
			let count = cat === "ALL" 
				? Object.values(schedule).reduce((acc, arr) => acc + arr.length, 0)
				: (schedule[cat] ? schedule[cat].length : 0);
			let isSelected = activeFilter === cat ? "selected" : "";
			return `<option value="${cat}" ${isSelected}>${cat} (${count} shows)</option>`;
		}).join('');
	}

	renderCategoryDropdown();

	// Category Management Card
	const catManageCard = document.getElementById('categoryManageCard');
	if (activeFilter === "ALL" || !schedule[activeFilter]) {
		catManageCard.style.display = "none";
		catManageCard.innerHTML = '';
	} else {
		let currentHex = getCatColorHex(activeFilter);
		catManageCard.style.display = "block";
		catManageCard.innerHTML = `
			<span class="drawer-label">Manage Category: ${escapeHtml(activeFilter)}</span>
			<div class="add-row" style="margin-top:6px;">
				<input type="text" id="renameCategoryInput" class="input-field" style="margin-bottom:0;flex-grow:1;" 
					value="${escapeHtml(activeFilter)}" 
					onkeydown="if(event.key==='Enter') commitRenameCategory('${escapeHtml(activeFilter)}')" />
				<button class="btn btn-outline" style="flex-shrink:0;" onclick="commitRenameCategory('${escapeHtml(activeFilter)}')">Rename</button>
				<button class="btn btn-outline btn-danger" style="flex-shrink:0;" onclick="deleteCategory('${escapeHtml(activeFilter)}')">Delete</button>
			</div>
			<div class="color-ctrl-row">
				<input type="color" value="${currentHex}" 
					oninput="document.getElementById('catHexDisplay').innerText = this.value.toUpperCase();"
					onchange="saveCategoryColor('${escapeHtml(activeFilter)}', this.value)" />
				<span class="color-hex-label">UI Color: <b id="catHexDisplay">${currentHex.toUpperCase()}</b></span>
			</div>
		`;
	}

	// Shows List
	const showsList = document.getElementById('showsList');
	showsList.innerHTML = '';

	let visibleItems = [];
	if (activeFilter === "ALL") {
		categories.forEach(cat => {
			let sortedCatItems = (schedule[cat] || [])
				.map((show, idx) => ({ cat: cat, index: idx, show: show }))
				.sort((a, b) => getDisplayName(a.show).localeCompare(getDisplayName(b.show), undefined, { numeric: true, sensitivity: 'base' }));
			visibleItems.push(...sortedCatItems);
		});
	} else {
		visibleItems = (schedule[activeFilter] || [])
			.map((show, idx) => ({ cat: activeFilter, index: idx, show: show }))
			.sort((a, b) => getDisplayName(a.show).localeCompare(getDisplayName(b.show), undefined, { numeric: true, sensitivity: 'base' }));
	}

	if (visibleItems.length === 0) {
		showsList.innerHTML = `<div style="text-align:center;color:var(--text-muted);padding:35px 10px;font-size:0.9rem;">No shows found in this view.</div>`;
		return;
	}

	visibleItems.forEach(item => {
		const uniqueId = `${item.cat}__${item.index}`;
		const isExpanded = expandedShowId === uniqueId;

		let hasAlias = item.show.indexOf('=') !== -1;
		let searchPart = hasAlias ? item.show.split('=')[0] : item.show;
		let displayPart = hasAlias ? item.show.split('=')[1] : item.show;
		let cleaned = cleanTarget(searchPart);
		let isShort = cleaned.length > 0 && cleaned.length <= 3;
		let catHex = getCatColorHex(item.cat);

		const card = document.createElement('div');
		card.className = `show-card ${isExpanded ? 'expanded' : ''}`;

		let summaryHtml = `
			<div class="show-summary" onclick="toggleExpand('${uniqueId}')">
				<div>
					<span class="show-title-text">${escapeHtml(displayPart)}</span>
					${hasAlias ? `<span class="show-alias-sub">Fuzzy Search Target: <b>${escapeHtml(searchPart)}</b></span>` : ''}
				</div>
				<div style="display:flex;gap:4px;align-items:center;">
					${isShort ? `<span class="badge badge-warn">&Delta; Short (${cleaned})</span>` : ''}
					${activeFilter === "ALL" ? `<span class="badge" style="background:#1f2d42;color:${catHex};">${item.cat}</span>` : ''}
					<span style="color:var(--text-muted);font-size:0.8rem;margin-left:4px;">${isExpanded ? '&#9650;' : '&#9660;'}</span>
				</div>
			</div>
		`;

		let drawerHtml = '';
		if (isExpanded) {
			let sortedCats = Object.keys(schedule).sort((a, b) => 
				a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' })
			);
			let catOptions = sortedCats.map(c => {
				return `<option value="${c}" ${c === item.cat ? 'selected' : ''}>${c}</option>`;
			}).join('');
			catOptions += `<option value="__DRAWER_CREATE_NEW__">+ New Category...</option>`;

			drawerHtml = `
				<div class="show-drawer">
					<div>
						<span class="drawer-label">Rename / Alias Override</span>
						<input type="text" class="input-field" style="margin-bottom:0;" value="${escapeHtml(item.show)}"
							onkeydown="if(event.key==='Enter') commitRename('${item.cat}', ${item.index}, this.value)"
							onblur="commitRename('${item.cat}', ${item.index}, this.value)" />
					</div>
					<div>
						<span class="drawer-label">Assign Category</span>
						<div class="drawer-row">
							<select id="drawerCatSelect__${item.cat}__${item.index}" class="cat-select" onchange="handleDrawerCatSelect('${item.cat}', ${item.index}, this.value)">
								${catOptions}
							</select>
							<button class="btn btn-outline btn-danger btn-sm" onclick="deleteShow('${item.cat}', ${item.index})">Delete</button>
						</div>

						<div id="drawerNewCatRow__${item.cat}__${item.index}" class="inline-input-group" style="display:none;margin-top:6px;">
							<input type="text" id="drawerNewCatInput__${item.cat}__${item.index}" class="input-field" placeholder="New category name..." style="margin-bottom:0;" 
								onkeydown="if(event.key==='Enter') commitDrawerNewCategory('${item.cat}', ${item.index})" />
							<button class="btn btn-sm" style="flex-shrink:0;" onclick="commitDrawerNewCategory('${item.cat}', ${item.index})">Move</button>
							<button class="btn btn-sm btn-outline" style="flex-shrink:0;" onclick="cancelDrawerNewCategory('${item.cat}', ${item.index})">Cancel</button>
						</div>
					</div>
				</div>
			`;
		}

		card.innerHTML = summaryHtml + drawerHtml;
		showsList.appendChild(card);
	});
}

render();
</script>
</body>
</html>