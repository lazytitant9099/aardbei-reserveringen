(function () {
	'use strict';

	function post(action, data) {
		var body = new URLSearchParams();
		body.append('action', action);
		body.append('nonce', aardbeiFrontend.nonce);

		Object.keys(data || {}).forEach(function (key) {
			body.append(key, data[key]);
		});

		return fetch(aardbeiFrontend.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded'
			},
			body: body.toString()
		}).then(function (response) {
			return response.json();
		});
	}

	function setMessage(element, message, type) {
		if (!element) {
			return;
		}

		element.textContent = message || '';
		element.className = 'aardbei-message';
		if (type) {
			element.classList.add('aardbei-message--' + type);
		}
	}

	function toDateKey(date) {
		var year = date.getFullYear();
		var month = String(date.getMonth() + 1).padStart(2, '0');
		var day = String(date.getDate()).padStart(2, '0');

		return year + '-' + month + '-' + day;
	}

	function addDays(date, amount) {
		var copy = new Date(date.getTime());
		copy.setDate(copy.getDate() + amount);
		return copy;
	}

	function monthStart(date) {
		return new Date(date.getFullYear(), date.getMonth(), 1);
	}

	function parseDateKey(dateKey) {
		var parts = dateKey.split('-').map(Number);
		return new Date(parts[0], parts[1] - 1, parts[2]);
	}

	function formatDateLabel(dateKey) {
		var date = parseDateKey(dateKey);
		var today = new Date();
		var tomorrow = addDays(today, 1);
		var prefix = '';
		var formatted = new Intl.DateTimeFormat('nl-NL', {
			day: 'numeric',
			month: 'long'
		}).format(date);

		if (dateKey === toDateKey(today)) {
			prefix = 'Vandaag, ';
		} else if (dateKey === toDateKey(tomorrow)) {
			prefix = 'Morgen, ';
		}

		return prefix + formatted;
	}

	function formatMonthLabel(date) {
		return new Intl.DateTimeFormat('nl-NL', {
			month: 'long',
			year: 'numeric'
		}).format(date);
	}

	function parseSlot(event) {
		var start = event.start || '';
		var end = event.end || '';
		var props = event.extendedProps || {};

		return {
			id: props.slot_id || event.id,
			date: start.substring(0, 10),
			startTime: start.substring(11, 16),
			endTime: end.substring(11, 16),
			remaining: Number(props.remaining || 0),
			capacity: Number(props.capacity || 0),
			title: event.title || ''
		};
	}

	function initWidget(widget) {
		var panels = widget.querySelectorAll('[data-aardbei-panel]');
		var panelToggles = widget.querySelectorAll('[data-aardbei-panel-toggle]');
		var guestsLabel = widget.querySelector('[data-aardbei-guests-label]');
		var dateLabel = widget.querySelector('[data-aardbei-date-label]');
		var timeLabel = widget.querySelector('[data-aardbei-time-label]');
		var personsDisplay = widget.querySelector('[data-aardbei-persons-display]');
		var personsInput = widget.querySelector('[data-aardbei-persons-input]');
		var minusButton = widget.querySelector('[data-aardbei-persons-minus]');
		var plusButton = widget.querySelector('[data-aardbei-persons-plus]');
		var daysGrid = widget.querySelector('[data-aardbei-days-grid]');
		var monthLabel = widget.querySelector('[data-aardbei-month-label]');
		var prevMonth = widget.querySelector('[data-aardbei-prev-month]');
		var nextMonth = widget.querySelector('[data-aardbei-next-month]');
		var timeGrid = widget.querySelector('[data-aardbei-time-grid]');
		var form = widget.querySelector('[data-aardbei-form]');
		var openFormButton = widget.querySelector('[data-aardbei-open-form]');
		var slotInput = widget.querySelector('[data-aardbei-slot-id]');
		var selectedSlot = widget.querySelector('[data-aardbei-selected-slot]');
		var formMessage = widget.querySelector('[data-aardbei-message]');
		var mainMessage = widget.querySelector('[data-aardbei-message-main]');
		var availabilityCard = widget.querySelector('[data-aardbei-availability-card]');

		var state = {
			persons: Number(personsDisplay ? personsDisplay.value : 2) || 2,
			slots: [],
			slotsByDate: {},
			selectedDate: '',
			selectedSlotId: '',
			currentMonth: monthStart(new Date()),
			minMonth: null,
			maxMonth: null
		};

		function closePanels(except) {
			panels.forEach(function (panel) {
				if (panel.getAttribute('data-aardbei-panel') !== except) {
					panel.hidden = true;
				}
			});
			panelToggles.forEach(function (button) {
				button.classList.toggle('is-open', button.getAttribute('data-aardbei-panel-toggle') === except);
			});
		}

		function togglePanel(name) {
			var panel = widget.querySelector('[data-aardbei-panel="' + name + '"]');
			if (!panel) {
				return;
			}

			var willOpen = panel.hidden;
			closePanels(willOpen ? name : '');
			panel.hidden = !willOpen;
		}

		function getSelectedSlot() {
			return state.slots.find(function (slot) {
				return String(slot.id) === String(state.selectedSlotId) && isSlotAvailable(slot);
			}) || null;
		}

		function sortSlots(slots) {
			return slots.slice().sort(function (a, b) {
				return a.startTime.localeCompare(b.startTime);
			});
		}

		function getSlotStartDate(slot) {
			var dateParts = String(slot.date || '').split('-').map(Number);
			var timeParts = String(slot.startTime || '').split(':').map(Number);

			return new Date(dateParts[0], dateParts[1] - 1, dateParts[2], timeParts[0] || 0, timeParts[1] || 0);
		}

		function isSlotInFuture(slot) {
			return getSlotStartDate(slot).getTime() > Date.now();
		}

		function isSlotAvailable(slot) {
			return Number(slot.remaining || 0) >= state.persons && isSlotInFuture(slot);
		}

		function getAvailableSlots(dateKey) {
			var slots = dateKey ? state.slotsByDate[dateKey] || [] : state.slots;

			return sortSlots(slots.filter(isSlotAvailable));
		}

		function getAvailableDates() {
			return Object.keys(state.slotsByDate).filter(function (dateKey) {
				return getAvailableSlots(dateKey).length > 0;
			}).sort();
		}

		function groupSlots(slots) {
			state.slotsByDate = {};
			slots.forEach(function (slot) {
				if (!state.slotsByDate[slot.date]) {
					state.slotsByDate[slot.date] = [];
				}
				state.slotsByDate[slot.date].push(slot);
			});

			Object.keys(state.slotsByDate).forEach(function (dateKey) {
				state.slotsByDate[dateKey] = sortSlots(state.slotsByDate[dateKey]);
			});
		}

		function updateLabels() {
			var slot = getSelectedSlot();
			var guestText = state.persons === 1 ? '1 gast' : state.persons + ' gasten';

			if (guestsLabel) {
				guestsLabel.textContent = guestText;
			}
			if (personsDisplay) {
				personsDisplay.value = state.persons;
			}
			if (personsInput) {
				personsInput.value = state.persons;
			}
			if (dateLabel) {
				dateLabel.textContent = state.selectedDate ? formatDateLabel(state.selectedDate) : 'Kies een datum';
			}
			if (timeLabel) {
				timeLabel.textContent = slot ? slot.startTime : 'Kies een tijd';
			}
			if (slotInput) {
				slotInput.value = state.selectedSlotId || '';
			}
			if (selectedSlot && slot) {
				selectedSlot.hidden = false;
				selectedSlot.textContent = guestText + ' · ' + formatDateLabel(slot.date) + ' · ' + slot.startTime + ' - ' + slot.endTime;
			} else if (selectedSlot) {
				selectedSlot.hidden = true;
				selectedSlot.textContent = '';
			}
		}

		function updateStepIndicator() {
				var steps = widget.querySelector('[data-aardbei-steps]');
				if (!steps) return;
				var step = 1;
				if (state.selectedDate && state.selectedSlotId) {
					step = 3;
				} else if (state.selectedDate) {
					step = 2;
				}
				steps.querySelectorAll('[data-aardbei-step]').forEach(function (el) {
					var n = parseInt(el.getAttribute('data-aardbei-step'), 10);
					el.classList.toggle('is-active', n === step);
					el.classList.toggle('is-done', n < step);
				});
			}

		function updateMonthLimits() {
			var dates = getAvailableDates();
			if (!dates.length) {
				state.minMonth = monthStart(new Date());
				state.maxMonth = monthStart(new Date());
				return;
			}

			state.minMonth = monthStart(parseDateKey(dates[0]));
			state.maxMonth = monthStart(parseDateKey(dates[dates.length - 1]));
		}

		function renderDays() {
			if (!daysGrid || !monthLabel) {
				return;
			}

			var first = monthStart(state.currentMonth);
			var firstDayOffset = (first.getDay() + 6) % 7;
			var daysInMonth = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
			var minMonthKey = state.minMonth ? toDateKey(state.minMonth) : '';
			var maxMonthKey = state.maxMonth ? toDateKey(state.maxMonth) : '';
			var currentMonthKey = toDateKey(first);

			monthLabel.textContent = formatMonthLabel(first);
			daysGrid.innerHTML = '';

			if (prevMonth) {
				prevMonth.disabled = minMonthKey && currentMonthKey <= minMonthKey;
			}
			if (nextMonth) {
				nextMonth.disabled = maxMonthKey && currentMonthKey >= maxMonthKey;
			}

			for (var blank = 0; blank < firstDayOffset; blank++) {
				daysGrid.appendChild(document.createElement('span'));
			}

			for (var day = 1; day <= daysInMonth; day++) {
				var date = new Date(first.getFullYear(), first.getMonth(), day);
				var dateKey = toDateKey(date);
				var hasSlots = getAvailableSlots(dateKey).length > 0;
				var button = document.createElement('button');
				button.type = 'button';
				button.textContent = String(day);
				button.disabled = !hasSlots;
				button.className = 'aardbei-day-button';
				button.classList.toggle('is-selected', state.selectedDate === dateKey);
				button.addEventListener('click', function (clickedDateKey) {
					return function () {
						selectDate(clickedDateKey);
						closePanels('time');
						var timePanel = widget.querySelector('[data-aardbei-panel="time"]');
						if (timePanel) {
							timePanel.hidden = false;
						}
					};
				}(dateKey));
				daysGrid.appendChild(button);
			}
		}

		function renderTimes() {
			if (!timeGrid) {
				return;
			}

			var slots = state.selectedDate ? getAvailableSlots(state.selectedDate) : [];
			timeGrid.innerHTML = '';

			if (!slots.length) {
				var empty = document.createElement('p');
				empty.className = 'aardbei-empty-state';
				empty.textContent = 'Geen beschikbare tijden voor dit aantal personen.';
				timeGrid.appendChild(empty);
				return;
			}

			slots.forEach(function (slot) {
				var button = document.createElement('button');
				button.type = 'button';
				button.className = 'aardbei-time-button';
				button.classList.toggle('is-selected', String(slot.id) === String(state.selectedSlotId));

				var timeText = slot.startTime + ' – ' + slot.endTime;
				button.textContent = timeText;

				if (aardbeiFrontend.showRemainingCapacity && slot.remaining > 0) {
					var badge = document.createElement('span');
					badge.className = 'aardbei-time-remaining';
					badge.textContent = slot.remaining === 1 ? 'nog 1 plek' : 'nog ' + slot.remaining + ' plekken';
					button.appendChild(badge);
				}

				button.addEventListener('click', function () {
					state.selectedSlotId = slot.id;
					closePanels('');
					updateLabels();
					renderTimes();
					setMessage(mainMessage, '', '');
					updateStepIndicator();
				});
				timeGrid.appendChild(button);
			});
		}

		function renderAvailability() {
			var hasSelection = Boolean(getSelectedSlot());
			if (availabilityCard) {
				availabilityCard.classList.toggle('is-muted', !hasSelection);
			}
		}

		function render() {
			updateLabels();
			renderDays();
			renderTimes();
			renderAvailability();
		}

		function selectDate(dateKey) {
			var slots = getAvailableSlots(dateKey);

			if (!slots.length) {
				return;
			}

			state.selectedDate = dateKey;
			state.currentMonth = monthStart(parseDateKey(dateKey));
			state.selectedSlotId = slots[0].id;
			render();
			updateStepIndicator();
		}

		function selectFirstAvailableSlot() {
			var dates = getAvailableDates();
			if (!dates.length) {
				state.selectedDate = '';
				state.selectedSlotId = '';
				render();
				setMessage(mainMessage, aardbeiFrontend.i18n.noSlots, 'info');
				return;
			}

			if (!state.selectedDate || !getAvailableSlots(state.selectedDate).length) {
				selectDate(dates[0]);
				return;
			}

			if (!getSelectedSlot()) {
				state.selectedSlotId = getAvailableSlots(state.selectedDate)[0].id;
			}

			render();
		}

		function updateAvailabilityForPersonsChange(showMessage) {
			updateMonthLimits();
			selectFirstAvailableSlot();

			if (!getSelectedSlot() && showMessage) {
				setMessage(mainMessage, 'Geen beschikbare tijden voor ' + state.persons + ' personen.', 'info');
			} else if (showMessage) {
				setMessage(mainMessage, '', '');
			}
		}

		function fetchSlots(silent) {
			var start = toDateKey(new Date());
			var end = toDateKey(addDays(new Date(), 120));

			if (!silent) {
				setMessage(mainMessage, 'Beschikbare pluktijden laden...', 'info');
			}
			return post('aardbei_get_public_slots', {
				start: start,
				end: end
			}).then(function (json) {
				if (!json.success) {
					if (!silent) {
						setMessage(mainMessage, aardbeiFrontend.i18n.loadingError, 'error');
					}
					return;
				}

				state.slots = json.data.map(parseSlot).filter(function (slot) {
					return slot.remaining > 0;
				});
				groupSlots(state.slots);
				updateMonthLimits();
				selectFirstAvailableSlot();
				if (!silent) {
					if (getSelectedSlot()) {
						setMessage(mainMessage, '', '');
					} else if (state.slots.length) {
						setMessage(mainMessage, 'Geen beschikbare tijden voor ' + state.persons + ' personen.', 'info');
					}
				}
			}).catch(function () {
				if (!silent) {
					setMessage(mainMessage, aardbeiFrontend.i18n.loadingError, 'error');
				}
			});
		}

		panelToggles.forEach(function (button) {
			button.addEventListener('click', function () {
				togglePanel(button.getAttribute('data-aardbei-panel-toggle'));
			});
		});

		if (minusButton) {
			minusButton.addEventListener('click', function () {
				state.persons = Math.max(1, state.persons - 1);
				updateAvailabilityForPersonsChange(true);
			});
		}

		if (plusButton) {
			plusButton.addEventListener('click', function () {
				state.persons += 1;
				updateAvailabilityForPersonsChange(true);
			});
		}

		if (personsDisplay) {
			personsDisplay.addEventListener('change', function () {
				state.persons = Math.max(1, Number(personsDisplay.value) || 1);
				updateAvailabilityForPersonsChange(true);
			});
		}

		if (prevMonth) {
			prevMonth.addEventListener('click', function () {
				state.currentMonth = new Date(state.currentMonth.getFullYear(), state.currentMonth.getMonth() - 1, 1);
				renderDays();
			});
		}

		if (nextMonth) {
			nextMonth.addEventListener('click', function () {
				state.currentMonth = new Date(state.currentMonth.getFullYear(), state.currentMonth.getMonth() + 1, 1);
				renderDays();
			});
		}

		if (openFormButton) {
			openFormButton.addEventListener('click', function () {
				var slot = getSelectedSlot();
				if (!slot) {
					setMessage(mainMessage, aardbeiFrontend.i18n.chooseSlot, 'error');
					return;
				}
				if (state.persons > slot.remaining) {
					setMessage(mainMessage, 'Er zijn nog maar ' + slot.remaining + ' plekken beschikbaar.', 'error');
					return;
				}

				if (form) {
					form.hidden = false;
				}
				setMessage(mainMessage, '', '');
				updateLabels();
				var steps = widget.querySelector('[data-aardbei-steps]');
				if (steps) {
					steps.querySelectorAll('[data-aardbei-step]').forEach(function (el) {
						var n = parseInt(el.getAttribute('data-aardbei-step'), 10);
						el.classList.toggle('is-active', n === 3);
						el.classList.toggle('is-done', n < 3);
					});
				}
				if (form) {
					form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
				}
			});
		}

		function buildGoogleCalendarUrl(slotInfo, title, guests) {
			if (!slotInfo || !slotInfo.date) return '';
			var dateStr = slotInfo.date.replace(/-/g, '');
			var startStr = dateStr + 'T' + slotInfo.startTime.replace(':', '') + '00';
			var endStr   = dateStr + 'T' + slotInfo.endTime.replace(':', '') + '00';
			var text = encodeURIComponent(title + ' (' + guests + ')');
			return 'https://calendar.google.com/calendar/r/eventedit?text=' + text + '&dates=' + startStr + '/' + endStr;
		}

		function showConfirmation(submittedData, slotInfo, reservationId, cancelToken) {
			var dateLabel = slotInfo ? formatDateLabel(slotInfo.date) : '';
			var timeLabel = slotInfo ? (slotInfo.startTime + ' – ' + slotInfo.endTime) : '';
			var guestText = state.persons === 1 ? '1 gast' : state.persons + ' gasten';

			var html = '<div class="aardbei-confirmation">'
				+ '<div class="aardbei-confirmation-icon"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>'
				+ '<h3>Reservering bevestigd!</h3>'
				+ '<p>Je ontvangt een bevestiging op <strong>' + escapeHtml(submittedData.email || '') + '</strong></p>'
				+ '<div class="aardbei-confirmation-details">';

			if (reservationId) {
				html += '<div class="aardbei-confirmation-row">'
					+ '<span class="aardbei-confirmation-row-label">Reserveringsnr.</span>'
					+ '<span class="aardbei-confirmation-row-value"><strong>#' + escapeHtml(String(reservationId)) + '</strong></span>'
					+ '</div>';
			}

			if (dateLabel) {
				html += '<div class="aardbei-confirmation-row">'
					+ '<span class="aardbei-confirmation-row-label">Datum</span>'
					+ '<span class="aardbei-confirmation-row-value">' + escapeHtml(dateLabel) + '</span>'
					+ '</div>';
			}

			if (timeLabel) {
				html += '<div class="aardbei-confirmation-row">'
					+ '<span class="aardbei-confirmation-row-label">Tijd</span>'
					+ '<span class="aardbei-confirmation-row-value">' + escapeHtml(timeLabel) + '</span>'
					+ '</div>';
			}

			html += '<div class="aardbei-confirmation-row">'
				+ '<span class="aardbei-confirmation-row-label">Personen</span>'
				+ '<span class="aardbei-confirmation-row-value">' + escapeHtml(guestText) + '</span>'
				+ '</div>';

			if (submittedData.name) {
				html += '<div class="aardbei-confirmation-row">'
					+ '<span class="aardbei-confirmation-row-label">Naam</span>'
					+ '<span class="aardbei-confirmation-row-value">' + escapeHtml(submittedData.name) + '</span>'
					+ '</div>';
			}

			html += '</div>';

			var gcalUrl = buildGoogleCalendarUrl(slotInfo, 'Aardbeien plukken', guestText);
			if (gcalUrl || reservationId) {
				html += '<div class="aardbei-calendar-links">';
				if (gcalUrl) {
					html += '<a href="' + gcalUrl + '" target="_blank" rel="noopener noreferrer" class="aardbei-calendar-link aardbei-calendar-link--google">'
						+ '<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>'
						+ escapeHtml((aardbeiFrontend.i18n && aardbeiFrontend.i18n.googleCalendar) || 'Google Agenda')
						+ '</a>';
				}
				if (reservationId) {
					var icsUrl = aardbeiFrontend.ajaxUrl + '?action=aardbei_download_ics&reservation_id=' + encodeURIComponent(reservationId) + '&token=' + encodeURIComponent(cancelToken || '') + '&nonce=' + encodeURIComponent(aardbeiFrontend.nonce);
					html += '<a href="' + icsUrl + '" class="aardbei-calendar-link aardbei-calendar-link--ics">'
						+ '<svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>'
						+ escapeHtml((aardbeiFrontend.i18n && aardbeiFrontend.i18n.downloadIcs) || 'iCal (.ics)')
						+ '</a>';
				}
				html += '</div>';
			}

			html += '<button type="button" class="aardbei-reset-button" data-aardbei-reset>Nieuwe reservering maken</button>'
				+ '</div>';

			var confirmEl = document.createElement('div');
			confirmEl.innerHTML = html;

			var inner = widget.querySelector('[data-aardbei-booking-body]') || widget.querySelector('.aardbei-booking-selectors');
			if (inner && inner.parentNode) {
				inner.parentNode.insertBefore(confirmEl.firstElementChild, inner);
				inner.hidden = true;
			}

			if (openFormButton) openFormButton.closest('[data-aardbei-selector-section]') && (openFormButton.closest('[data-aardbei-selector-section]').hidden = true);

			var resetBtn = widget.querySelector('[data-aardbei-reset]');
			if (resetBtn) {
				resetBtn.addEventListener('click', function () {
					var conf = widget.querySelector('.aardbei-confirmation');
					if (conf) conf.parentNode.removeChild(conf);
					if (inner) inner.hidden = false;
					if (openFormButton) {
						var section = openFormButton.closest('[data-aardbei-selector-section]');
						if (section) section.hidden = false;
					}
					state.selectedDate = '';
					state.selectedSlotId = '';
					if (form) {
						form.reset();
						form.hidden = true;
					}
					setMessage(mainMessage, '', '');
					updateStepIndicator();
					fetchSlots(true);
				});
			}
		}

		function escapeHtml(str) {
			return String(str)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;');
		}

		if (form) {
			form.addEventListener('submit', function (event) {
				event.preventDefault();

				if (!state.selectedSlotId) {
					setMessage(formMessage, aardbeiFrontend.i18n.chooseSlot, 'error');
					return;
				}

				var formData = new FormData(form);
				var submittedData = {};
				formData.forEach(function (value, key) {
					submittedData[key] = value;
				});
				submittedData.slot_id = state.selectedSlotId;
				submittedData.persons = state.persons;

				setMessage(formMessage, 'Bezig met reserveren...', 'info');
				post('aardbei_create_reservation', submittedData).then(function (json) {
					if (!json.success) {
						setMessage(formMessage, json.data && json.data.message ? json.data.message : 'Reserveren is niet gelukt.', 'error');
						return;
					}

					var slotInfo = getSelectedSlot();
					var reservationId = json.data && json.data.reservation_id ? json.data.reservation_id : null;
					var cancelToken = json.data && json.data.cancel_token ? json.data.cancel_token : '';
					form.hidden = true;
					setMessage(formMessage, '', '');
					setMessage(mainMessage, '', '');
					showConfirmation(submittedData, slotInfo, reservationId, cancelToken);
					fetchSlots(true);
				}).catch(function () {
					setMessage(formMessage, 'Reserveren is niet gelukt. Probeer het opnieuw.', 'error');
				});
			});
		}

		fetchSlots();
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-aardbei-widget]').forEach(initWidget);
	});
}());
