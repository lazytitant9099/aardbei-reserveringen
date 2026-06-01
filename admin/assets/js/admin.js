(function () {
	'use strict';

	var data = window.aardbeiAdminData || {};
	var ajaxUrl = data.ajaxUrl || '';
	var nonce = data.nonce || '';
	var i18n = data.i18n || {};

	/* ---- Toast ---- */

	var toastContainer = null;

	function getToastContainer() {
		if (!toastContainer) {
			toastContainer = document.createElement('div');
			toastContainer.className = 'aardbei-toast-container';
			document.body.appendChild(toastContainer);
		}
		return toastContainer;
	}

	function showToast(message, type) {
		var container = getToastContainer();
		var toast = document.createElement('div');
		var iconSvg = '';

		if (type === 'success') {
			iconSvg = '<svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
		} else if (type === 'error') {
			iconSvg = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
		} else {
			iconSvg = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
		}

		toast.className = 'aardbei-toast aardbei-toast--' + (type || 'info');
		toast.innerHTML = iconSvg;
		var span = document.createElement('span');
		span.textContent = message;
		toast.appendChild(span);
		container.appendChild(toast);

		setTimeout(function () {
			toast.classList.add('is-leaving');
			setTimeout(function () {
				if (toast.parentNode) {
					toast.parentNode.removeChild(toast);
				}
			}, 240);
		}, 3200);
	}

	/* ---- AJAX helper ---- */

	function adminPost(action, extraData) {
		var body = new URLSearchParams();
		body.append('action', action);
		body.append('nonce', nonce);
		if (extraData) {
			Object.keys(extraData).forEach(function (key) {
				var val = extraData[key];
				if (Array.isArray(val)) {
					val.forEach(function (v) { body.append(key + '[]', v); });
				} else {
					body.append(key, val);
				}
			});
		}
		return fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		}).then(function (r) { return r.json(); });
	}

	/* ---- Auto-show toast from URL params ---- */

	function initToastFromUrl() {
		var params = new URLSearchParams(window.location.search);
		if (params.has('updated') && params.has('message')) {
			showToast(decodeURIComponent(params.get('message')), 'success');
		} else if (params.has('error') && params.has('message')) {
			showToast(decodeURIComponent(params.get('message')), 'error');
		}
	}

	/* ---- Confirm on destructive form submits ---- */

	function initConfirmForms() {
		document.querySelectorAll('.aardbei-admin-wrap form').forEach(function (form) {
			form.addEventListener('submit', function (event) {
				var cancelDestructive = form.querySelector('.link-delete, [name="action"][value="aardbei_delete_week_template"], [name="action"][value="aardbei_cancel_reservation"]');
				if (cancelDestructive && !window.confirm(i18n.confirmCancel || 'Weet je zeker?')) {
					event.preventDefault();
					return;
				}
				var deleteSlot = form.querySelector('[name="action"][value="aardbei_delete_slot"]');
				if (deleteSlot && !window.confirm(i18n.confirmDeleteSlot || 'Weet je zeker dat je dit tijdslot wilt verwijderen?')) {
					event.preventDefault();
				}
			});
		});
	}

	/* ---- Inline AJAX cancel reservation ---- */

	function initAjaxCancel() {
		document.addEventListener('click', function (e) {
			var btn = e.target.closest('.aardbei-ajax-cancel');
			if (!btn) return;

			if (!window.confirm(i18n.confirmCancel || 'Weet je zeker dat je deze reservering wilt annuleren?')) return;

			var id = btn.dataset.id;
			btn.disabled = true;

			adminPost('aardbei_cancel_reservation', { reservation_id: id }).then(function (json) {
				if (!json.success) {
					showToast((json.data && json.data.message) || i18n.error || 'Fout opgetreden.', 'error');
					btn.disabled = false;
					return;
				}
				showToast(json.data.message || i18n.cancelled || 'Geannuleerd', 'success');

				var row = btn.closest('tr.aardbei-expandable-row');
				if (row) {
					var next = row.nextElementSibling;
					var statusCell = row.querySelector('.aardbei-status');
					if (statusCell) {
						statusCell.className = 'aardbei-status aardbei-status-cancelled';
						statusCell.textContent = i18n.cancelled || 'Geannuleerd';
					}
					var checkbox = row.querySelector('.aardbei-row-check');
					if (checkbox) { checkbox.parentNode.removeChild(checkbox); }
					btn.parentNode.innerHTML = '<span class="aardbei-muted-text" style="font-size:12px;">—</span>';
					row.classList.remove('is-confirmed');
					row.classList.add('is-cancelled');
					if (next && next.classList.contains('aardbei-detail-row')) {
						var bulkCheck = row.querySelector('.aardbei-row-check');
						if (bulkCheck) { bulkCheck.parentNode.removeChild(bulkCheck); }
					}
					updateBulkBar();
				}
			}).catch(function () {
				showToast(i18n.error || 'Fout opgetreden.', 'error');
				btn.disabled = false;
			});
		});
	}

	/* ---- Bulk cancel ---- */

	var bulkBar, bulkCountEl, bulkCancelBtn, selectAllCheckbox;

	function getCheckedIds() {
		return Array.from(document.querySelectorAll('.aardbei-row-check:checked')).map(function (cb) { return cb.value; });
	}

	function updateBulkBar() {
		if (!bulkBar) return;
		var ids = getCheckedIds();
		var count = ids.length;
		if (count > 0) {
			bulkBar.hidden = false;
			if (bulkCountEl) {
				bulkCountEl.textContent = count + ' geselecteerd';
			}
		} else {
			bulkBar.hidden = true;
		}
		if (selectAllCheckbox) {
			var allCheckboxes = document.querySelectorAll('.aardbei-row-check');
			selectAllCheckbox.checked = allCheckboxes.length > 0 && allCheckboxes.length === count;
			selectAllCheckbox.indeterminate = count > 0 && count < allCheckboxes.length;
		}
	}

	function initBulkActions() {
		bulkBar = document.getElementById('aardbei-bulk-bar');
		bulkCountEl = document.getElementById('aardbei-bulk-count');
		bulkCancelBtn = document.getElementById('aardbei-bulk-cancel');
		selectAllCheckbox = document.getElementById('aardbei-select-all');
		var deselectBtn = document.getElementById('aardbei-bulk-deselect');

		if (!bulkBar) return;

		document.addEventListener('change', function (e) {
			if (e.target.classList.contains('aardbei-row-check')) {
				updateBulkBar();
			}
		});

		if (selectAllCheckbox) {
			selectAllCheckbox.addEventListener('change', function () {
				document.querySelectorAll('.aardbei-row-check').forEach(function (cb) {
					cb.checked = selectAllCheckbox.checked;
				});
				updateBulkBar();
			});
		}

		if (deselectBtn) {
			deselectBtn.addEventListener('click', function () {
				document.querySelectorAll('.aardbei-row-check').forEach(function (cb) { cb.checked = false; });
				if (selectAllCheckbox) { selectAllCheckbox.checked = false; selectAllCheckbox.indeterminate = false; }
				updateBulkBar();
			});
		}

		if (bulkCancelBtn) {
			bulkCancelBtn.addEventListener('click', function () {
				var ids = getCheckedIds();
				if (!ids.length) return;

				if (!window.confirm((i18n.confirmBulk || 'Weet je zeker dat je de geselecteerde reserveringen wilt annuleren?') + ' (' + ids.length + ')')) return;

				bulkCancelBtn.disabled = true;

				adminPost('aardbei_bulk_cancel_reservations', { ids: ids }).then(function (json) {
					bulkCancelBtn.disabled = false;
					if (!json.success) {
						showToast((json.data && json.data.message) || i18n.error || 'Fout opgetreden.', 'error');
						return;
					}
					showToast(json.data.message || 'Geannuleerd', 'success');
					ids.forEach(function (id) {
						var row = document.querySelector('tr.aardbei-expandable-row[data-id="' + id + '"]');
						if (!row) return;
						var statusCell = row.querySelector('.aardbei-status');
						if (statusCell) {
							statusCell.className = 'aardbei-status aardbei-status-cancelled';
							statusCell.textContent = i18n.cancelled || 'Geannuleerd';
						}
						var checkbox = row.querySelector('.aardbei-row-check');
						if (checkbox) { checkbox.parentNode.removeChild(checkbox); }
						var actionCell = row.querySelector('[data-no-expand]:last-child');
						if (actionCell) {
							actionCell.innerHTML = '<span class="aardbei-muted-text" style="font-size:12px;">—</span>';
						}
					});
					document.querySelectorAll('.aardbei-row-check:checked').forEach(function (cb) { cb.checked = false; });
					updateBulkBar();
				}).catch(function () {
					bulkCancelBtn.disabled = false;
					showToast(i18n.error || 'Fout opgetreden.', 'error');
				});
			});
		}
	}

	/* ---- Row expand (detail view) ---- */

	function initRowExpand() {
		document.addEventListener('click', function (e) {
			var row = e.target.closest('tr.aardbei-expandable-row');
			if (!row) return;
			if (e.target.closest('[data-no-expand]')) return;

			var detail = row.nextElementSibling;
			if (!detail || !detail.classList.contains('aardbei-detail-row')) return;

			var isOpen = !detail.hidden;
			detail.hidden = isOpen;
			row.classList.toggle('is-expanded', !isOpen);
		});
	}

	/* ---- Search ---- */

	function initSearch() {
		var input = document.getElementById('aardbei-search');
		var countEl = document.getElementById('aardbei-result-count');
		var table = document.getElementById('aardbei-reservations-table');

		if (!input || !table) return;

		input.addEventListener('input', function () {
			var q = input.value.toLowerCase().trim();
			var rows = table.querySelectorAll('tbody tr.aardbei-expandable-row');
			var visible = 0;

			rows.forEach(function (row) {
				var search = (row.dataset.search || '').toLowerCase();
				var matches = !q || search.indexOf(q) !== -1;
				row.style.display = matches ? '' : 'none';
				var detail = row.nextElementSibling;
				if (detail && detail.classList.contains('aardbei-detail-row')) {
					detail.style.display = matches ? '' : 'none';
				}
				if (matches) visible++;
			});

			if (countEl) {
				countEl.textContent = q ? visible + ' gevonden' : '';
			}
		});
	}

	/* ---- Tabs ---- */

	function initTabs() {
		var nav = document.getElementById('aardbei-tabs-nav');
		if (!nav) return;

		nav.querySelectorAll('.aardbei-tab-btn').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var tab = btn.dataset.tab;
				nav.querySelectorAll('.aardbei-tab-btn').forEach(function (b) { b.classList.remove('is-active'); });
				document.querySelectorAll('.aardbei-tab-panel').forEach(function (p) { p.classList.remove('is-active'); });
				btn.classList.add('is-active');
				var panel = document.getElementById('aardbei-tab-' + tab);
				if (panel) panel.classList.add('is-active');
			});
		});

		var params = new URLSearchParams(window.location.search);
		var activeTab = params.get('tab');
		if (activeTab) {
			var tabBtn = nav.querySelector('[data-tab="' + activeTab + '"]');
			if (tabBtn) tabBtn.click();
		}
	}

	/* ---- Live color preview ---- */

	function initColorPreview() {
		document.querySelectorAll('.aardbei-color-preview-input').forEach(function (input) {
			input.addEventListener('input', function () {
				var targetId = input.dataset.target;
				var preview = document.getElementById(targetId);
				if (!preview) return;
				var bgKey = preview.dataset.bg;
				var textKey = preview.dataset.text;
				var bgInput = bgKey ? document.querySelector('[name="' + bgKey + '"]') : null;
				var textInput = textKey ? document.querySelector('[name="' + textKey + '"]') : null;
				if (bgInput) preview.style.background = bgInput.value;
				if (textInput) preview.style.color = textInput.value;
			});
		});
	}

	/* ---- Check for updates button ---- */

	function initCheckUpdates() {
		var btn = document.getElementById('aardbei-check-updates');
		if (!btn) return;

		btn.addEventListener('click', function () {
			btn.disabled = true;
			var originalText = btn.textContent.trim();
			btn.textContent = i18n.checking || 'Controleren…';

			adminPost('aardbei_check_for_update', {}).then(function (json) {
				btn.disabled = false;
				btn.textContent = originalText;

				if (!json.success) {
					showToast((json.data && json.data.message) || i18n.error || 'Fout opgetreden.', 'error');
					return;
				}

				var s = json.data;
				var type = s.update_available ? 'info' : 'success';
				showToast(s.message || 'Gecontroleerd', type);

				var statusDiv = btn.closest('.aardbei-update-status');
				if (statusDiv) {
					statusDiv.className = 'aardbei-update-status aardbei-update-status--' + s.status;
					var strong = statusDiv.querySelector('strong');
					if (strong) {
						strong.textContent = s.update_available
							? 'Update beschikbaar'
							: (s.status === 'connected' ? 'Verbonden met GitHub' : 'Niet geconfigureerd');
					}
					var spans = statusDiv.querySelectorAll('.aardbei-update-status-text span');
					if (spans[0]) { spans[0].textContent = s.message || ''; }
					if (spans[1] && s.current_version) {
						spans[1].textContent = 'Huidig: v' + s.current_version + (s.latest_version ? ' · Nieuwste: v' + s.latest_version : '');
					}
				}
			}).catch(function () {
				btn.disabled = false;
				btn.textContent = originalText;
				showToast(i18n.error || 'Fout opgetreden.', 'error');
			});
		});
	}

	/* ---- Slot bulk delete ---- */

	function initSlotBulkDelete() {
		var bar = document.getElementById('aardbei-slots-bulk-bar');
		var countEl = document.getElementById('aardbei-slots-bulk-count');
		var deleteBtn = document.getElementById('aardbei-slots-bulk-delete');
		var deselectBtn = document.getElementById('aardbei-slots-bulk-deselect');
		var selectAll = document.getElementById('aardbei-slots-select-all');

		if (!bar) return;

		function getCheckedSlotIds() {
			return Array.from(document.querySelectorAll('.aardbei-slot-check:checked')).map(function (cb) { return cb.value; });
		}

		function updateSlotBulkBar() {
			var ids = getCheckedSlotIds();
			var count = ids.length;
			bar.hidden = count === 0;
			if (countEl) countEl.textContent = count + ' geselecteerd';

			if (selectAll) {
				var all = document.querySelectorAll('.aardbei-slot-check:not(:disabled)');
				selectAll.checked = all.length > 0 && all.length === count;
				selectAll.indeterminate = count > 0 && count < all.length;
			}
		}

		document.addEventListener('change', function (e) {
			if (e.target.classList.contains('aardbei-slot-check')) {
				updateSlotBulkBar();
			}
		});

		if (selectAll) {
			selectAll.addEventListener('change', function () {
				document.querySelectorAll('.aardbei-slot-check:not(:disabled)').forEach(function (cb) {
					cb.checked = selectAll.checked;
				});
				updateSlotBulkBar();
			});
		}

		if (deselectBtn) {
			deselectBtn.addEventListener('click', function () {
				document.querySelectorAll('.aardbei-slot-check').forEach(function (cb) { cb.checked = false; });
				if (selectAll) { selectAll.checked = false; selectAll.indeterminate = false; }
				updateSlotBulkBar();
			});
		}

		if (deleteBtn) {
			deleteBtn.addEventListener('click', function () {
				var ids = getCheckedSlotIds();
				if (!ids.length) return;

				if (!window.confirm((i18n.confirmBulkDeleteSlots || 'Weet je zeker dat je de geselecteerde tijdsloten wilt verwijderen?') + ' (' + ids.length + ')')) return;

				deleteBtn.disabled = true;

				adminPost('aardbei_bulk_delete_slots', { ids: ids }).then(function (json) {
					deleteBtn.disabled = false;
					if (!json.success) {
						showToast((json.data && json.data.message) || i18n.error || 'Fout opgetreden.', 'error');
						return;
					}
					showToast(json.data.message || (i18n.deleted || 'Verwijderd'), 'success');

					// Verwijder alleen de rijen die daadwerkelijk uit de DB zijn verwijderd.
					var removedIds = (json.data && json.data.deleted_ids) ? json.data.deleted_ids.map(String) : ids;
					removedIds.forEach(function (id) {
						var row = document.querySelector('tr[data-slot-id="' + id + '"]');
						if (row) row.parentNode.removeChild(row);
					});

					document.querySelectorAll('.aardbei-slot-check:checked').forEach(function (cb) { cb.checked = false; });
					updateSlotBulkBar();
				}).catch(function () {
					deleteBtn.disabled = false;
					showToast(i18n.error || 'Fout opgetreden.', 'error');
				});
			});
		}
	}

	/* ---- Init ---- */

	/* ---- Testmail ---- */

	function initTestMail() {
		var btn = document.getElementById('aardbei-send-test-mail');
		if (!btn) return;

		btn.addEventListener('click', function () {
			btn.disabled = true;
			var originalText = btn.textContent.trim();
			btn.textContent = 'Versturen…';

			var result = document.getElementById('aardbei-test-mail-result');

			adminPost('aardbei_send_test_mail', {}).then(function (json) {
				btn.disabled = false;
				btn.textContent = originalText;

				if (result) {
					result.style.display = 'block';
					if (json.success) {
						result.style.color = '#166534';
						result.textContent = (json.data && json.data.message) || 'Testmail verstuurd.';
					} else {
						result.style.color = '#991b1b';
						result.textContent = 'Fout: ' + ((json.data && json.data.message) || 'Onbekende fout.');
					}
				}
			}).catch(function () {
				btn.disabled = false;
				btn.textContent = originalText;
				if (result) {
					result.style.display = 'block';
					result.style.color = '#991b1b';
					result.textContent = 'Fout: geen verbinding.';
				}
			});
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		initToastFromUrl();
		initConfirmForms();
		initAjaxCancel();
		initBulkActions();
		initRowExpand();
		initSearch();
		initTabs();
		initColorPreview();
		initCheckUpdates();
		initSlotBulkDelete();
		initTestMail();
	});
}());
