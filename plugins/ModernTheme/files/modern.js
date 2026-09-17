/**
 * ModernTheme — apply dark class and place the toggle in the Ace navbar.
 * Inline scripts are blocked by MantisBT CSP (script-src 'self').
 */
(function () {
	var meta = document.querySelector('meta[name="modern-theme-color-scheme"]');
	if (meta && meta.getAttribute('content') === 'dark') {
		document.documentElement.classList.add('modern-theme-dark');
	} else {
		document.documentElement.classList.remove('modern-theme-dark');
	}

	function placeToggle() {
		var nav = document.querySelector('ul.ace-nav');
		var btn = document.getElementById('modern-theme-toggle');
		if (!nav || !btn || btn.parentElement === nav) {
			return;
		}

		var item = document.createElement('li');
		item.className = 'modern-theme-toggle grey';
		item.appendChild(btn);
		btn.classList.remove('modern-theme-toggle-btn');
		nav.insertBefore(item, nav.firstChild);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', placeToggle);
	} else {
		placeToggle();
	}
})();
