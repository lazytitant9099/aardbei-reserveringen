(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var openButton = document.querySelector('[data-aardbei-popup-open]');
		var drawer = document.querySelector('[data-aardbei-popup-drawer]');
		var backdrop = document.querySelector('.aardbei-popup-backdrop');
		var closeButtons = document.querySelectorAll('[data-aardbei-popup-close]');

		if (!openButton || !drawer) {
			return;
		}

		function openDrawer() {
			drawer.classList.add('is-open');
			drawer.setAttribute('aria-hidden', 'false');
			if (backdrop) {
				backdrop.classList.add('is-open');
			}
			document.dispatchEvent(new CustomEvent('aardbei:popup-opened'));
		}

		function closeDrawer() {
			drawer.classList.remove('is-open');
			drawer.setAttribute('aria-hidden', 'true');
			if (backdrop) {
				backdrop.classList.remove('is-open');
			}
		}

		openButton.addEventListener('click', openDrawer);
		closeButtons.forEach(function (button) {
			button.addEventListener('click', closeDrawer);
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				closeDrawer();
			}
		});
	});
}());
