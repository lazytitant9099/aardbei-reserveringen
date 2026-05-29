(function () {
	'use strict';

	var data    = window.aardbeiKassaData || {};
	var ajaxUrl = data.ajaxUrl || '';
	var nonce   = data.nonce || '';
	var i18n    = data.i18n || {};

	/* ---- Toast (eigen implementatie voor kassapagina) ---- */

	var toastContainer = null;

	function getToastContainer() {
		if ( ! toastContainer ) {
			toastContainer = document.createElement( 'div' );
			toastContainer.className = 'aardbei-toast-container';
			document.body.appendChild( toastContainer );
		}
		return toastContainer;
	}

	function showToast( message, type ) {
		var container = getToastContainer();
		var toast     = document.createElement( 'div' );
		var iconSvg   = type === 'success'
			? '<svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
			: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';

		toast.className = 'aardbei-toast aardbei-toast--' + ( type || 'info' );
		toast.innerHTML = iconSvg;
		var span = document.createElement( 'span' );
		span.textContent = message;
		toast.appendChild( span );
		container.appendChild( toast );

		setTimeout( function () {
			toast.classList.add( 'is-leaving' );
			setTimeout( function () {
				if ( toast.parentNode ) {
					toast.parentNode.removeChild( toast );
				}
			}, 240 );
		}, 3200 );
	}

	/* ---- AJAX helper ---- */

	function kassaPost( action, extraData ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', nonce );
		if ( extraData ) {
			Object.keys( extraData ).forEach( function ( key ) {
				body.append( key, extraData[ key ] );
			} );
		}
		return fetch( ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} ).then( function ( r ) { return r.json(); } );
	}

	/* ---- Teller in slot-header bijwerken ---- */

	function updateSlotProgress( row ) {
		var table  = row.closest( 'table' );
		if ( ! table ) return;
		var slot   = table.closest( '.aardbei-kassa-slot' );
		if ( ! slot ) return;
		var header = slot.querySelector( '.aardbei-kassa-slot-progress' );
		if ( ! header ) return;

		var allRows     = table.querySelectorAll( 'tbody tr.aardbei-kassa-row' );
		var checkedIn   = table.querySelectorAll( 'tbody tr.aardbei-kassa-row.is-checked-in' );
		var total       = allRows.length;
		var count       = checkedIn.length;
		var isComplete  = total > 0 && count === total;

		header.textContent = count + ' / ' + total + ' aangemeld';
		header.classList.toggle( 'is-complete', isComplete );
	}

	/* ---- Check-in knop ---- */

	function initCheckIn() {
		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.aardbei-kassa-checkin' );
			if ( ! btn ) return;

			var id = btn.dataset.id;
			btn.disabled = true;

			kassaPost( 'aardbei_check_in_reservation', { reservation_id: id } )
				.then( function ( json ) {
					if ( ! json.success ) {
						showToast( ( json.data && json.data.message ) || i18n.error || 'Fout opgetreden.', 'error' );
						btn.disabled = false;
						return;
					}

					var time = ( json.data && json.data.checked_in_at ) ? json.data.checked_in_at : '';
					var row  = btn.closest( 'tr' );
					if ( row ) {
						row.classList.add( 'is-checked-in' );

						var statusCell = row.querySelector( '.aardbei-kassa-status-cell' );
						if ( statusCell ) {
							statusCell.innerHTML =
								'<span class="aardbei-status aardbei-status-checked-in">' +
								'<svg viewBox="0 0 24 24" style="width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:3;stroke-linecap:round;stroke-linejoin:round;vertical-align:middle;margin-right:3px;"><polyline points="20 6 9 17 4 12"/></svg>' +
								( i18n.aangemeld || 'Aangemeld' ) + ( time ? ' ' + time : '' ) +
								'</span>';
						}

						var actionCell = row.querySelector( '.aardbei-kassa-action-cell' );
						if ( actionCell ) {
							actionCell.innerHTML =
								'<button type="button" class="aardbei-btn aardbei-btn--outline aardbei-btn--sm aardbei-kassa-undo" data-id="' + id + '">' +
								( i18n.ongedaan || 'Ongedaan' ) +
								'</button>';
						}

						updateSlotProgress( row );
					}

					showToast( json.data.message || 'Aangemeld', 'success' );
				} )
				.catch( function () {
					showToast( i18n.error || 'Fout opgetreden.', 'error' );
					btn.disabled = false;
				} );
		} );
	}

	/* ---- Ongedaan-knop ---- */

	function initUndoCheckIn() {
		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.aardbei-kassa-undo' );
			if ( ! btn ) return;

			if ( ! window.confirm( i18n.confirmUndo || 'Aanmelding ongedaan maken?' ) ) return;

			var id = btn.dataset.id;
			btn.disabled = true;

			kassaPost( 'aardbei_undo_check_in', { reservation_id: id } )
				.then( function ( json ) {
					if ( ! json.success ) {
						showToast( ( json.data && json.data.message ) || i18n.error || 'Fout opgetreden.', 'error' );
						btn.disabled = false;
						return;
					}

					var row = btn.closest( 'tr' );
					if ( row ) {
						row.classList.remove( 'is-checked-in' );

						var statusCell = row.querySelector( '.aardbei-kassa-status-cell' );
						if ( statusCell ) {
							statusCell.innerHTML = '<span class="aardbei-status aardbei-status-confirmed">Verwacht</span>';
						}

						var actionCell = row.querySelector( '.aardbei-kassa-action-cell' );
						if ( actionCell ) {
							actionCell.innerHTML =
								'<button type="button" class="aardbei-btn aardbei-btn--success aardbei-btn--sm aardbei-kassa-checkin" data-id="' + id + '">' +
								'<svg viewBox="0 0 24 24" style="width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:3;stroke-linecap:round;stroke-linejoin:round;"><polyline points="20 6 9 17 4 12"/></svg>' +
								'Aanmelden' +
								'</button>';
						}

						updateSlotProgress( row );
					}

					showToast( json.data.message || 'Ongedaan gemaakt', 'success' );
				} )
				.catch( function () {
					showToast( i18n.error || 'Fout opgetreden.', 'error' );
					btn.disabled = false;
				} );
		} );
	}

	/* ---- Zoekfunctie ---- */

	function initSearch() {
		var input = document.getElementById( 'aardbei-kassa-search' );
		if ( ! input ) return;

		input.addEventListener( 'input', function () {
			var q    = input.value.toLowerCase().trim();
			var rows = document.querySelectorAll( '.aardbei-kassa-row' );

			rows.forEach( function ( row ) {
				var search  = ( row.dataset.search || '' ).toLowerCase();
				var matches = ! q || search.indexOf( q ) !== -1;
				row.style.display = matches ? '' : 'none';
			} );
		} );
	}

	/* ---- Vernieuwen knop ---- */

	function initRefresh() {
		var btn = document.getElementById( 'aardbei-kassa-refresh' );
		if ( ! btn ) return;

		btn.addEventListener( 'click', function () {
			window.location.reload();
		} );
	}

	/* ---- Toast van URL params ---- */

	function initToastFromUrl() {
		var params = new URLSearchParams( window.location.search );
		if ( params.has( 'updated' ) && params.has( 'message' ) ) {
			showToast( decodeURIComponent( params.get( 'message' ) ), 'success' );
		} else if ( params.has( 'error' ) && params.has( 'message' ) ) {
			showToast( decodeURIComponent( params.get( 'message' ) ), 'error' );
		}
	}

	/* ---- Init ---- */

	document.addEventListener( 'DOMContentLoaded', function () {
		initToastFromUrl();
		initCheckIn();
		initUndoCheckIn();
		initSearch();
		initRefresh();
	} );
}());
