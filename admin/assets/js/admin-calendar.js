(function () {
	'use strict';

	function post(action, data) {
		var body = new URLSearchParams();
		body.append('action', action);
		body.append('nonce', aardbeiAdmin.nonce);

		Object.keys(data || {}).forEach(function (key) {
			body.append(key, data[key]);
		});

		return fetch(aardbeiAdmin.ajaxUrl, {
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

	function textRow(label, value) {
		var row = document.createElement('p');
		var strong = document.createElement('strong');
		strong.textContent = label + ': ';
		row.appendChild(strong);
		row.appendChild(document.createTextNode(value));
		return row;
	}

	function renderDetails(container, data, calendar) {
		var slot = data.slot;
		var reservations = data.reservations || [];
		container.innerHTML = '';

		container.appendChild(textRow('Datum', slot.date));
		container.appendChild(textRow('Tijd', slot.start_time.substring(0, 5) + ' - ' + slot.end_time.substring(0, 5)));
		container.appendChild(textRow('Status', slot.status));
		container.appendChild(textRow('Capaciteit', slot.capacity));
		container.appendChild(textRow('Geboekt', slot.booked_persons));
		container.appendChild(textRow('Resterend', slot.remaining));

		var actions = document.createElement('div');
		actions.className = 'aardbei-panel-actions';

		var closeButton = document.createElement('button');
		closeButton.type = 'button';
		closeButton.className = 'button';
		closeButton.textContent = aardbeiAdmin.i18n.closeSlot;
		closeButton.addEventListener('click', function () {
			post('aardbei_admin_close_slot', { slot_id: slot.id }).then(function (json) {
				if (!json.success) {
					alert(json.data && json.data.message ? json.data.message : aardbeiAdmin.i18n.loadingError);
					return;
				}
				calendar.refetchEvents();
			});
		});

		var openButton = document.createElement('button');
		openButton.type = 'button';
		openButton.className = 'button button-primary';
		openButton.textContent = aardbeiAdmin.i18n.openSlot;
		openButton.addEventListener('click', function () {
			post('aardbei_admin_open_slot', { slot_id: slot.id }).then(function (json) {
				if (!json.success) {
					alert(json.data && json.data.message ? json.data.message : aardbeiAdmin.i18n.loadingError);
					return;
				}
				calendar.refetchEvents();
			});
		});

		actions.appendChild(closeButton);
		actions.appendChild(openButton);

		// Verwijderknop — alleen tonen als er geen reserveringen zijn
		if (parseInt(slot.booked_persons, 10) === 0) {
			var deleteButton = document.createElement('button');
			deleteButton.type = 'button';
			deleteButton.className = 'button';
			deleteButton.textContent = aardbeiAdmin.i18n.deleteSlot || 'Verwijderen';
			deleteButton.style.cssText = 'color:#dc2626;border-color:#dc2626;margin-top:4px;';
			deleteButton.addEventListener('click', function () {
				if (!window.confirm(aardbeiAdmin.i18n.confirmDeleteSlot || 'Weet je zeker dat je dit tijdslot wilt verwijderen?')) {
					return;
				}
				deleteButton.disabled = true;
				post('aardbei_admin_delete_slot', { slot_id: slot.id }).then(function (json) {
					if (!json.success) {
						alert(json.data && json.data.message ? json.data.message : aardbeiAdmin.i18n.loadingError);
						deleteButton.disabled = false;
						return;
					}
					container.innerHTML = '<p style="color:#16a34a;font-weight:600;">✓ Tijdslot verwijderd.</p>';
					calendar.refetchEvents();
				}).catch(function () {
					deleteButton.disabled = false;
				});
			});
			actions.appendChild(deleteButton);
		}

		container.appendChild(actions);

		var title = document.createElement('h3');
		title.textContent = 'Reserveringen';
		container.appendChild(title);

		if (!reservations.length) {
			var empty = document.createElement('p');
			empty.textContent = 'Geen reserveringen voor dit tijdslot.';
			container.appendChild(empty);
			return;
		}

		var list = document.createElement('ul');
		list.className = 'aardbei-reservation-list';
		reservations.forEach(function (reservation) {
			var item = document.createElement('li');
			item.textContent = reservation.name + ' - ' + reservation.persons + ' personen - ' + reservation.status;
			list.appendChild(item);
		});
		container.appendChild(list);
	}

	document.addEventListener('DOMContentLoaded', function () {
		var calendarEl = document.getElementById('aardbei-admin-calendar');
		var detailsEl = document.getElementById('aardbei-admin-slot-details');

		if (!calendarEl) {
			return;
		}

		if (typeof FullCalendar === 'undefined') {
			calendarEl.textContent = aardbeiAdmin.i18n.loadingError;
			return;
		}

		var calendar = new FullCalendar.Calendar(calendarEl, {
			initialView: 'timeGridWeek',
			firstDay: 1,
			locale: 'nl',
			height: 'auto',
			allDaySlot: false,
			slotMinTime: '07:00:00',
			slotMaxTime: '21:00:00',
			nowIndicator: true,
			lazyFetching: false,
			events: function (info, successCallback, failureCallback) {
				post('aardbei_get_admin_slots', {
					start: info.startStr,
					end: info.endStr
				}).then(function (json) {
					if (!json.success) {
						failureCallback();
						return;
					}
					successCallback(json.data);
				}).catch(failureCallback);
			},
			eventClick: function (info) {
				var slotId = info.event.extendedProps.slot_id;
				if (!slotId || !detailsEl) {
					return;
				}

				detailsEl.textContent = 'Laden...';
				post('aardbei_get_slot_details', { slot_id: slotId }).then(function (json) {
					if (!json.success) {
						detailsEl.textContent = json.data && json.data.message ? json.data.message : aardbeiAdmin.i18n.loadingError;
						return;
					}
					renderDetails(detailsEl, json.data, calendar);
				});
			}
		});

		calendar.render();
	});
}());
