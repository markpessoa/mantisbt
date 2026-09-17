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
	var tokenName = board.getAttribute('data-form-token-name');
	var tokenValue = board.getAttribute('data-form-token-value');
	var storagePrefix = 'kanban-lane-collapsed-';
	var emptyLabel = board.getAttribute('data-empty-label') || '// EMPTY';
	var THRESHOLD = 6;

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

	function navigateCard(card) {
		var href = card.getAttribute('data-href');
		if (href) {
			window.location.href = href;
		}
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
			navigateCard(card);
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
		navigateCard(card);
	});
})();
