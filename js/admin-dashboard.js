/**
 * NextSnapMail page-based administration navigation and iframe styling.
 * Kept in a dedicated asset so layout updates are not hidden by an older
 * cached copy of the shared NextSnapMail integration script.
 */
(() => {
	'use strict';

	const initialize = () => {
		const iframe = document.getElementById('nextsnapmail-admin-frame');
		if (!iframe || iframe.dataset.nextsnapmailLayoutReady === '1') {
			return;
		}
		iframe.dataset.nextsnapmailLayoutReady = '1';

		const workspace = document.querySelector('[data-nextsnapmail-admin-workspace]');
		const saveArea = document.querySelector('[data-nextsnapmail-save-area]');
		const pageButtons = [...document.querySelectorAll('[data-nextsnapmail-page]')];
		const pagePanels = [...document.querySelectorAll('[data-nextsnapmail-panel]')];
		const contextPanels = [...document.querySelectorAll('[data-nextsnapmail-context]')];
		const adminRoutes = {
			domains: 'domains',
			general: 'general',
			login: 'login',
			branding: 'branding',
			contacts: 'contacts',
			security: 'security',
			extensions: 'packages',
			config: 'config',
			about: 'about'
		};
		const embeddedPages = new Set(Object.keys(adminRoutes));
		const savePages = new Set(['domains', 'integration']);

		const setAdminRoute = route => {
			const nextHash = '#/settings/' + route;
			if (iframe.contentWindow && iframe.contentWindow.location.hash !== nextHash) {
				iframe.contentWindow.location.hash = nextHash;
			}
		};

		const showPage = requestedPage => {
			const page = pageButtons.some(button => button.dataset.nextsnapmailPage === requestedPage)
				? requestedPage
				: 'integration';

			pageButtons.forEach(button => {
				const active = button.dataset.nextsnapmailPage === page;
				button.classList.toggle('active', active);
				button.setAttribute('aria-current', active ? 'page' : 'false');
			});
			pagePanels.forEach(panel => {
				const active = panel.dataset.nextsnapmailPanel === page;
				panel.hidden = !active;
				panel.classList.toggle('active', active);
			});
			contextPanels.forEach(panel => {
				const active = panel.dataset.nextsnapmailContext === page;
				panel.hidden = !active;
				panel.classList.toggle('active', active);
			});

			workspace.hidden = !embeddedPages.has(page);
			if (saveArea) {
				saveArea.hidden = !savePages.has(page);
			}

			if (adminRoutes[page]) {
				setAdminRoute(adminRoutes[page]);
			}

			try {
				sessionStorage.setItem('nextsnapmail-admin-page', page);
			} catch (error) {
				// Storage is optional.
			}
		};

		pageButtons.forEach(button => button.addEventListener('click', event => {
			// Capture the event before a possibly cached older admin script can
			// apply the obsolete route format.
			event.stopImmediatePropagation();
			showPage(button.dataset.nextsnapmailPage);
		}, true));

		const prepareIframe = () => {
			try {
				const iframeDocument = iframe.contentDocument;
				iframeDocument?.documentElement?.classList.add('nextsnapmail-integrated-admin-document');
				iframeDocument?.body?.classList.add('nextsnapmail-integrated-admin-body');
				if (iframeDocument && !iframeDocument.getElementById('nextsnapmail-integrated-admin-css')) {
					const sourceStylesheet = document.querySelector('link[href*="admin-dashboard.css"]');
					if (sourceStylesheet) {
						const stylesheet = iframeDocument.createElement('link');
						stylesheet.id = 'nextsnapmail-integrated-admin-css';
						stylesheet.rel = 'stylesheet';
						stylesheet.href = sourceStylesheet.href;
						iframeDocument.head.appendChild(stylesheet);
					}
				}

				const activePage = pageButtons.find(button => button.classList.contains('active'))?.dataset.nextsnapmailPage;
				setAdminRoute(adminRoutes[activePage] || 'domains');
			} catch (error) {
				console.warn('Could not prepare integrated NextSnapMail administration.', error);
			}
		};

		iframe.addEventListener('load', prepareIframe);
		if ('complete' === iframe.contentDocument?.readyState) {
			prepareIframe();
		}

		let initialPage = 'integration';
		try {
			initialPage = sessionStorage.getItem('nextsnapmail-admin-page') || initialPage;
		} catch (error) {
			// Keep the default page.
		}
		showPage(initialPage);

		// Run once more after every document and iframe resource has finished.
		// This deliberately wins over an older cached copy of nextsnapmail.js
		// that may still execute its former administration initializer.
		window.addEventListener('load', () => {
			const activePage = pageButtons.find(button => button.classList.contains('active'))?.dataset.nextsnapmailPage || initialPage;
			showPage(activePage);
			prepareIframe();
		}, {once: true});
	};

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', initialize, {once: true});
	} else {
		initialize();
	}
})();
