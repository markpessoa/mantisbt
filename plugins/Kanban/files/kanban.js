/**
 * Kanban board — pointer drag between status columns and version lanes.
 */
(function () {
	'use strict';

	var board = document.getElementById('kanban-board');
	if (!board) {
		return;
	}

	var moveUrl = board.getAttribute('data-move-url');
	var issueUrl = board.getAttribute('data-issue-url');
	var issueDeleteUrl = board.getAttribute('data-issue-delete-url');
	var boardProjectId = board.getAttribute('data-project-id');
	var tokenName = board.getAttribute('data-form-token-name');
	var tokenValue = board.getAttribute('data-form-token-value');
	var issueTokenName = board.getAttribute('data-issue-token-name');
	var issueTokenValue = board.getAttribute('data-issue-token-value');
	var issueDeleteTokenName = board.getAttribute('data-issue-delete-token-name');
	var issueDeleteTokenValue = board.getAttribute('data-issue-delete-token-value');
	var storagePrefix = 'kanban-lane-collapsed-';
	var emptyLabel = board.getAttribute('data-empty-label') || '// EMPTY';
	var THRESHOLD = 6;

	var dialog = document.getElementById('kanban-issue-dialog');
	var issueForm = document.getElementById('kanban-issue-form');
	var dialogTitle = document.getElementById('kanban-dialog-title');
	var dialogError = document.getElementById('kanban-dialog-error');
	var dialogMeta = document.getElementById('kanban-dialog-meta');
	var metaCreated = document.getElementById('kanban-meta-created');
	var metaUpdated = document.getElementById('kanban-meta-updated');
	var fieldSummary = document.getElementById('kanban-field-summary');
	var fieldDescription = document.getElementById('kanban-field-description');
	var fieldReporter = document.getElementById('kanban-field-reporter');
	var fieldDue = document.getElementById('kanban-field-due');
	var fieldPriority = document.getElementById('kanban-field-priority');
	var priorityPicker = document.getElementById('kanban-priority-picker');
	var fieldStatus = document.getElementById('kanban-field-status');
	var fieldVersion = document.getElementById('kanban-field-version');
	var fieldProject = document.getElementById('kanban-field-project');
	var fieldTags = document.getElementById('kanban-field-tags');
	var tagsHint = document.getElementById('kanban-tags-hint');
	var btnArchive = document.getElementById('kanban-btn-archive');
	var btnDelete = document.getElementById('kanban-btn-delete');
	var btnSaveNew = document.getElementById('kanban-btn-save-new');
	var btnClose = document.getElementById('kanban-dialog-close');
	var newIssueBtn = document.getElementById('kanban-new-issue');
	var modalState = {
		bugId: 0,
		mode: 'create',
		permissions: {},
		tagSeparator: ',',
		labels: {
			newIssue: dialog ? dialog.getAttribute('data-title-new') || 'New task' : 'New task',
			cardPrefix: dialog ? dialog.getAttribute('data-title-card') || 'CARD #' : 'CARD #',
			confirmDelete: dialog ? dialog.getAttribute('data-confirm-delete') || 'Delete this issue?' : 'Delete this issue?',
			created: dialog ? dialog.getAttribute('data-meta-created') || 'created:' : 'created:',
			updated: dialog ? dialog.getAttribute('data-meta-updated') || 'updated:' : 'updated:',
			noVersion: dialog ? dialog.getAttribute('data-no-version') || 'No version' : 'No version'
		}
	};

	function dropCell(el) {
		while (el && el !== board) {
			if (el.classList && el.classList.contains('kanban-cell')) {
				return el;
			}
			el = el.parentElement;
		}
		return null;
	}

	function cellFromPoint(x, y) {
		var el = document.elementFromPoint(x, y);
		return dropCell(el);
	}

	function cellStatus(cell) {
		return cell ? cell.getAttribute('data-status') : null;
	}

	function cellLane(cell) {
		return cell ? cell.getAttribute('data-lane') : null;
	}

	function updateStatusCount(statusId, delta) {
		if (!statusId) {
			return;
		}
		var col = board.querySelector('.kanban-status-col[data-status="' + statusId + '"] .kanban-status-count');
		if (!col) {
			return;
		}
		var n = parseInt(col.textContent, 10) || 0;
		col.textContent = String(Math.max(0, n + delta));
	}

	function laneSection(laneKey) {
		if (!laneKey) {
			return null;
		}
		return board.querySelector('.kanban-lane[data-lane="' + laneKey + '"]');
	}

	function syncCardLaneMeta(card, laneKey) {
		if (!card || !laneKey) {
			return;
		}
		card.setAttribute('data-lane', laneKey);
		var dateEl = card.querySelector('.kanban-card-date');
		if (!dateEl) {
			return;
		}
		var lane = laneSection(laneKey);
		if (!lane) {
			return;
		}
		var laneDate = lane.querySelector('.kanban-lane-date');
		if (laneDate && laneDate.textContent) {
			dateEl.textContent = laneDate.textContent;
		}
	}

	function updateLaneCount(laneKey, delta) {
		if (!laneKey) {
			return;
		}
		var lane = board.querySelector('.kanban-lane[data-lane="' + laneKey + '"] .kanban-lane-count');
		if (!lane) {
			return;
		}
		var n = parseInt(lane.textContent, 10) || 0;
		lane.textContent = String(Math.max(0, n + delta));
	}

	function refreshCellEmpty(cell) {
		if (!cell) {
			return;
		}
		var cards = cell.querySelectorAll('.kanban-card');
		var empty = cell.querySelector('.kanban-empty');
		if (cards.length === 0 && !empty) {
			var span = document.createElement('span');
			span.className = 'kanban-empty';
			span.textContent = emptyLabel;
			cell.appendChild(span);
		} else if (cards.length > 0 && empty) {
			empty.parentNode.removeChild(empty);
		}
	}

	function showDialogError(message) {
		if (!dialogError) {
			return;
		}
		if (!message) {
			dialogError.hidden = true;
			dialogError.textContent = '';
			return;
		}
		dialogError.hidden = false;
		dialogError.textContent = message;
	}

	function fillSelect(select, options, valueKey, labelKey, selectedValue) {
		if (!select) {
			return;
		}
		select.innerHTML = '';
		options.forEach(function (opt) {
			var option = document.createElement('option');
			option.value = String(opt[valueKey]);
			option.textContent = opt[labelKey];
			if (String(selectedValue) === String(opt[valueKey])) {
				option.selected = true;
			}
			select.appendChild(option);
		});
	}

	function buildPriorityPicker(priorities, selectedId) {
		if (!priorityPicker) {
			return;
		}
		priorityPicker.innerHTML = '';
		priorities.forEach(function (p) {
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'kanban-priority-btn ' + p.class;
			btn.textContent = p.label;
			btn.setAttribute('data-priority-id', String(p.id));
			if (String(p.id) === String(selectedId)) {
				btn.classList.add('is-selected');
			}
			btn.addEventListener('click', function () {
				priorityPicker.querySelectorAll('.kanban-priority-btn').forEach(function (el) {
					el.classList.remove('is-selected');
				});
				btn.classList.add('is-selected');
				fieldPriority.value = String(p.id);
			});
			priorityPicker.appendChild(btn);
		});
		if (fieldPriority) {
			fieldPriority.value = String(selectedId);
		}
	}

	function applyPermissions(perms) {
		modalState.permissions = perms || {};
		var canEdit = !!perms.can_edit;
		fieldSummary.disabled = !canEdit;
		fieldDescription.disabled = !canEdit;
		fieldReporter.disabled = !canEdit || !perms.can_change_reporter;
		fieldDue.disabled = !canEdit || !perms.can_change_due_date;
		fieldStatus.disabled = !canEdit || !perms.can_change_status;
		fieldVersion.disabled = !canEdit || !perms.can_change_version;
		fieldProject.disabled = !canEdit || !perms.can_change_project;
		fieldTags.disabled = !canEdit || !perms.can_change_tags;
		priorityPicker.querySelectorAll('button').forEach(function (btn) {
			btn.disabled = !canEdit;
		});
		btnSaveNew.hidden = !canEdit;
		document.getElementById('kanban-btn-save').disabled = !canEdit;
		btnArchive.hidden = !perms.can_archive;
		btnDelete.hidden = !perms.can_delete;
	}

	function populateForm(data) {
		var issue = data.issue;
		var opts = data.options;
		modalState.mode = data.mode;
		modalState.bugId = issue.id || 0;
		modalState.tagSeparator = opts.tag_separator || ',';

		if (data.mode === 'edit') {
			dialogTitle.textContent = modalState.labels.cardPrefix + issue.id;
			dialogMeta.hidden = false;
			metaCreated.textContent = modalState.labels.created + ' ' + (issue.date_submitted || '');
			metaUpdated.textContent = modalState.labels.updated + ' ' + (issue.last_updated || '');
		} else {
			dialogTitle.textContent = modalState.labels.newIssue;
			dialogMeta.hidden = true;
		}

		fieldSummary.value = issue.summary || '';
		fieldDescription.value = issue.description || '';
		fillSelect(fieldReporter, opts.reporters, 'id', 'name', issue.reporter_id);
		fieldDue.value = issue.due_date || '';
		buildPriorityPicker(opts.priorities, issue.priority);
		fillSelect(fieldStatus, opts.statuses, 'id', 'label', issue.status);

		fieldVersion.innerHTML = '';
		var noneOpt = document.createElement('option');
		noneOpt.value = '__none__';
		noneOpt.textContent = modalState.labels.noVersion;
		if (!issue.target_version) {
			noneOpt.selected = true;
		}
		fieldVersion.appendChild(noneOpt);
		opts.versions.forEach(function (v) {
			var option = document.createElement('option');
			option.value = v.name;
			option.textContent = v.name;
			if (issue.target_version === v.name) {
				option.selected = true;
			}
			fieldVersion.appendChild(option);
		});

		fillSelect(fieldProject, opts.projects, 'id', 'name', issue.project_id);
		fieldTags.value = issue.tag_string || '';
		if (tagsHint) {
			tagsHint.textContent = modalState.tagSeparator;
		}
		applyPermissions(data.permissions);
		showDialogError('');
	}

	function fetchIssueForm(bugId, projectId) {
		var url = issueUrl;
		var params = new URLSearchParams();
		if (bugId) {
			params.set('bug_id', String(bugId));
		} else if (projectId) {
			params.set('project_id', String(projectId));
		}
		return fetch(url + '&' + params.toString(), {
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		}).then(function (response) {
			return response.text().then(function (text) {
				var data = {};
				try {
					data = JSON.parse(text);
				} catch (err) {
					data = { ok: false, message: 'Invalid response' };
				}
				return { ok: response.ok, data: data };
			});
		});
	}

	function openIssueModal(bugId, projectId) {
		if (!dialog) {
			return;
		}
		showDialogError('');
		fetchIssueForm(bugId || 0, projectId || boardProjectId).then(function (result) {
			if (!result.data || !result.data.ok) {
				window.alert((result.data && result.data.message) ? result.data.message : 'Failed to load');
				return;
			}
			populateForm(result.data);
			if (typeof dialog.showModal === 'function') {
				dialog.showModal();
			} else {
				dialog.setAttribute('open', 'open');
			}
		}).catch(function () {
			window.alert('Failed to load');
		});
	}

	function postIssue(bodyParams) {
		var body = new URLSearchParams();
		Object.keys(bodyParams).forEach(function (key) {
			body.set(key, bodyParams[key]);
		});
		if (issueTokenName && issueTokenValue) {
			body.set(issueTokenName, issueTokenValue);
		}
		return fetch(issueUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
				'X-Requested-With': 'XMLHttpRequest'
			},
			body: body.toString()
		}).then(function (response) {
			return response.text().then(function (text) {
				var data = {};
				try {
					data = JSON.parse(text);
				} catch (err) {
					data = { ok: false, message: 'Invalid response' };
				}
				return { ok: response.ok, data: data };
			});
		});
	}

	function collectFormParams() {
		return {
			bug_id: String(modalState.bugId || 0),
			summary: fieldSummary.value,
			description: fieldDescription.value,
			reporter_id: fieldReporter.value,
			due_date: fieldDue.value,
			priority: fieldPriority.value,
			status: fieldStatus.value,
			target_version: fieldVersion.value,
			project_id: fieldProject.value,
			tag_string: fieldTags.value,
			action: 'save'
		};
	}

	function reloadBoard() {
		window.location.reload();
	}

	function saveIssue(andNew) {
		var params = collectFormParams();
		var projectId = fieldProject.value;
		postIssue(params).then(function (result) {
			if (result.data && result.data.ok) {
				if (andNew) {
					fetchIssueForm(0, projectId).then(function (loadResult) {
						if (loadResult.data && loadResult.data.ok) {
							populateForm(loadResult.data);
						} else {
							reloadBoard();
						}
					});
					return;
				}
				reloadBoard();
				return;
			}
			showDialogError((result.data && result.data.message) ? result.data.message : 'Save failed');
		}).catch(function () {
			showDialogError('Save failed');
		});
	}

	if (dialog && issueForm) {
		issueForm.addEventListener('submit', function (e) {
			e.preventDefault();
			saveIssue(false);
		});

		btnClose.addEventListener('click', function () {
			dialog.close();
		});

		dialog.addEventListener('click', function (e) {
			if (e.target === dialog) {
				dialog.close();
			}
		});

		if (btnSaveNew) {
			btnSaveNew.addEventListener('click', function () {
				saveIssue(true);
			});
		}

		if (btnArchive) {
			btnArchive.addEventListener('click', function () {
				postIssue({
					bug_id: String(modalState.bugId),
					action: 'archive'
				}).then(function (result) {
					if (result.data && result.data.ok) {
						reloadBoard();
						return;
					}
					showDialogError((result.data && result.data.message) ? result.data.message : 'Archive failed');
				});
			});
		}

		if (btnDelete) {
			btnDelete.addEventListener('click', function () {
				if (!window.confirm(modalState.labels.confirmDelete)) {
					return;
				}
				var body = new URLSearchParams();
				body.set('bug_id', String(modalState.bugId));
				if (issueDeleteTokenName && issueDeleteTokenValue) {
					body.set(issueDeleteTokenName, issueDeleteTokenValue);
				}
				fetch(issueDeleteUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
						'X-Requested-With': 'XMLHttpRequest'
					},
					body: body.toString()
				}).then(function (response) {
					return response.text().then(function (text) {
						var data = {};
						try {
							data = JSON.parse(text);
						} catch (err) {
							data = { ok: false };
						}
						if (data.ok) {
							reloadBoard();
						} else {
							showDialogError(data.message || 'Delete failed');
						}
					});
				}).catch(function () {
					showDialogError('Delete failed');
				});
			});
		}

		fieldProject.addEventListener('change', function () {
			if (modalState.mode === 'create') {
				fetchIssueForm(0, fieldProject.value).then(function (result) {
					if (result.data && result.data.ok) {
						var keep = {
							summary: fieldSummary.value,
							description: fieldDescription.value,
							due_date: fieldDue.value,
							tag_string: fieldTags.value
						};
						populateForm(result.data);
						fieldSummary.value = keep.summary;
						fieldDescription.value = keep.description;
						fieldDue.value = keep.due_date;
						fieldTags.value = keep.tag_string;
					}
				});
			}
		});
	}

	if (newIssueBtn) {
		newIssueBtn.addEventListener('click', function () {
			openIssueModal(0, boardProjectId);
		});
	}

	function postMove(bugId, status, targetVersion) {
		var body = new URLSearchParams();
		body.set('bug_id', String(bugId));
		body.set('status', String(status));
		body.set('target_version', targetVersion === null || targetVersion === undefined ? '' : String(targetVersion));
		if (tokenName && tokenValue) {
			body.set(tokenName, tokenValue);
		}

		return fetch(moveUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
				'X-Requested-With': 'XMLHttpRequest'
			},
			body: body.toString()
		}).then(function (response) {
			return response.text().then(function (text) {
				var data = {};
				try {
					data = JSON.parse(text);
				} catch (err) {
					data = { ok: false, message: 'Invalid response' };
				}
				return { ok: response.ok, data: data };
			});
		});
	}

	function clearDropTargets() {
		board.querySelectorAll('.kanban-drop-target').forEach(function (el) {
			el.classList.remove('kanban-drop-target');
		});
	}

	board.querySelectorAll('.kanban-lane').forEach(function (lane) {
		var laneKey = lane.getAttribute('data-lane');
		var header = lane.querySelector('.kanban-lane-header');
		if (!header || !laneKey) {
			return;
		}

		try {
			if (window.localStorage.getItem(storagePrefix + laneKey) === '1') {
				lane.classList.add('is-collapsed');
				header.setAttribute('aria-expanded', 'false');
			}
		} catch (e) {
			/* ignore */
		}

		header.addEventListener('click', function () {
			var collapsed = lane.classList.toggle('is-collapsed');
			header.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
			try {
				window.localStorage.setItem(storagePrefix + laneKey, collapsed ? '1' : '0');
			} catch (err) {
				/* ignore */
			}
		});
	});

	var drag = null;

	function startDrag(e, card) {
		var rect = card.getBoundingClientRect();
		var ghost = card.cloneNode(true);
		ghost.classList.add('kanban-card-ghost');
		ghost.style.width = rect.width + 'px';
		ghost.style.left = rect.left + 'px';
		ghost.style.top = rect.top + 'px';
		document.body.appendChild(ghost);

		card.classList.add('kanban-card-origin');
		try {
			card.setPointerCapture(e.pointerId);
		} catch (err) {
			/* ignore */
		}

		drag.ghost = ghost;
		drag.offsetX = e.clientX - rect.left;
		drag.offsetY = e.clientY - rect.top;
		drag.moved = true;
		document.body.classList.add('kanban-is-dragging');
	}

	function moveGhost(e) {
		if (!drag || !drag.ghost) {
			return;
		}
		drag.ghost.style.left = (e.clientX - drag.offsetX) + 'px';
		drag.ghost.style.top = (e.clientY - drag.offsetY) + 'px';

		drag.ghost.style.visibility = 'hidden';
		var cell = cellFromPoint(e.clientX, e.clientY);
		drag.ghost.style.visibility = 'visible';

		clearDropTargets();
		if (cell) {
			cell.classList.add('kanban-drop-target');
		}
	}

	function revertMove(card, sourceCell, targetCell, oldStatus, newStatus, oldLane, newLane) {
		sourceCell.appendChild(card);
		refreshCellEmpty(targetCell);
		refreshCellEmpty(sourceCell);
		if (oldStatus !== newStatus) {
			updateStatusCount(newStatus, -1);
			updateStatusCount(oldStatus, 1);
		}
		if (oldLane !== newLane) {
			updateLaneCount(newLane, -1);
			updateLaneCount(oldLane, 1);
		}
	}

	function finishDrag(e) {
		if (!drag) {
			return;
		}

		var card = drag.card;
		var sourceCell = drag.sourceCell;
		var oldStatus = drag.status;
		var bugId = drag.bugId;
		var oldLane = drag.lane;
		var moved = drag.moved;

		if (drag.ghost && drag.ghost.parentNode) {
			drag.ghost.parentNode.removeChild(drag.ghost);
		}
		card.classList.remove('kanban-card-origin');
		document.body.classList.remove('kanban-is-dragging');
		clearDropTargets();

		try {
			card.releasePointerCapture(e.pointerId);
		} catch (err) {
			/* ignore */
		}

		drag = null;

		if (!moved) {
			return;
		}

		var cell = cellFromPoint(e.clientX, e.clientY);
		if (!cell) {
			return;
		}

		var newStatus = cellStatus(cell);
		var newLane = cellLane(cell);
		if (!newStatus || !newLane) {
			return;
		}

		if (newStatus === oldStatus && newLane === oldLane) {
			return;
		}

		var emptyInTarget = cell.querySelector('.kanban-empty');
		if (emptyInTarget) {
			emptyInTarget.parentNode.removeChild(emptyInTarget);
		}

		cell.appendChild(card);
		refreshCellEmpty(sourceCell);
		if (oldStatus !== newStatus) {
			updateStatusCount(oldStatus, -1);
			updateStatusCount(newStatus, 1);
		}
		if (oldLane !== newLane) {
			updateLaneCount(oldLane, -1);
			updateLaneCount(newLane, 1);
		}

		postMove(bugId, newStatus, newLane).then(function (result) {
			if (result.data && result.data.ok) {
				if (oldLane !== newLane) {
					syncCardLaneMeta(card, newLane);
				}
				return;
			}
			revertMove(card, sourceCell, cell, oldStatus, newStatus, oldLane, newLane);
			var msg = (result.data && result.data.message) ? result.data.message : 'Move failed';
			window.alert(msg);
		}).catch(function () {
			revertMove(card, sourceCell, cell, oldStatus, newStatus, oldLane, newLane);
			window.alert('Move failed');
		});
	}

	function onPointerMove(e) {
		if (!drag || e.pointerId !== drag.pointerId) {
			return;
		}
		if (!drag.moved) {
			var dx = e.clientX - drag.startX;
			var dy = e.clientY - drag.startY;
			if ((dx * dx + dy * dy) < THRESHOLD * THRESHOLD) {
				return;
			}
			e.preventDefault();
			startDrag(e, drag.card);
		}
		e.preventDefault();
		moveGhost(e);
	}

	function onPointerUp(e) {
		if (!drag || e.pointerId !== drag.pointerId) {
			return;
		}
		document.removeEventListener('pointermove', onPointerMove, true);
		document.removeEventListener('pointerup', onPointerUp, true);
		document.removeEventListener('pointercancel', onPointerCancel, true);
		finishDrag(e);
	}

	function onPointerCancel(e) {
		if (!drag || e.pointerId !== drag.pointerId) {
			return;
		}
		document.removeEventListener('pointermove', onPointerMove, true);
		document.removeEventListener('pointerup', onPointerUp, true);
		document.removeEventListener('pointercancel', onPointerCancel, true);
		if (drag.ghost && drag.ghost.parentNode) {
			drag.ghost.parentNode.removeChild(drag.ghost);
		}
		drag.card.classList.remove('kanban-card-origin');
		document.body.classList.remove('kanban-is-dragging');
		clearDropTargets();
		drag = null;
	}

	board.addEventListener('pointerdown', function (e) {
		if (e.button !== 0) {
			return;
		}
		var handle = e.target.closest('.kanban-card-handle');
		if (!handle || !board.contains(handle)) {
			return;
		}
		var card = handle.closest('.kanban-card-draggable');
		if (!card) {
			return;
		}

		e.preventDefault();

		var cell = dropCell(card);
		drag = {
			card: card,
			sourceCell: cell,
			status: cellStatus(cell),
			lane: cellLane(cell),
			bugId: card.getAttribute('data-bug-id'),
			startX: e.clientX,
			startY: e.clientY,
			ghost: null,
			offsetX: 0,
			offsetY: 0,
			moved: false,
			pointerId: e.pointerId
		};

		document.addEventListener('pointermove', onPointerMove, true);
		document.addEventListener('pointerup', onPointerUp, true);
		document.addEventListener('pointercancel', onPointerCancel, true);
	});

	board.addEventListener('click', function (e) {
		if (document.body.classList.contains('kanban-is-dragging')) {
			e.preventDefault();
			e.stopPropagation();
			return;
		}
		if (e.target.closest('.kanban-card-handle')) {
			return;
		}
		var card = e.target.closest('.kanban-card');
		if (card) {
			var bugId = card.getAttribute('data-bug-id');
			openIssueModal(bugId, boardProjectId);
		}
	});

	board.addEventListener('keydown', function (e) {
		if (e.key !== 'Enter' && e.key !== ' ') {
			return;
		}
		var card = e.target.closest('.kanban-card');
		if (!card) {
			return;
		}
		e.preventDefault();
		var bugId = card.getAttribute('data-bug-id');
		openIssueModal(bugId, boardProjectId);
	});
})();
