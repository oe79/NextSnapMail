/**
 * Nextcloud - SnappyMail mail plugin
 *
 * @author RainLoop Team, Nextgen-Networks (@nextgen-networks), Tab Fitts (@tabp0le), Pierre-Alain Bandinelli (@pierre-alain-b), SnappyMail, Rene Hampölz (@hampoelz)
 *
 * Based initially on https://github.com/RainLoop/rainloop-webmail/tree/master/build/owncloud/rainloop-app
 */

// Do the following things once the document is fully loaded.
document.onreadystatechange = () => {
	if (document.readyState === 'complete') {
		watchIFrameTitle();
		passThemesToIFrame();
		let form = document.querySelector('form.nextsnapmail');
		form && SnappyMailFormHelper(form);
		setupSnappyMailImportPreview();
		setupNextSnapMailReset();
		setupNextSnapMailPluginUpload();
		setupUnifiedSearchListener()
	}
};

function setupIntegratedAdminFrame()
{
	const iframe = document.getElementById('nextsnapmail-admin-frame');
	if (!iframe) {
		return;
	}
	const workspace = document.querySelector('[data-nextsnapmail-admin-workspace]');
	const saveArea = document.querySelector('[data-nextsnapmail-save-area]');
	const pageButtons = [...document.querySelectorAll('[data-nextsnapmail-page]')];
	const pagePanels = [...document.querySelectorAll('[data-nextsnapmail-panel]')];
	const contextPanels = [...document.querySelectorAll('[data-nextsnapmail-context]')];
	const routeButtons = [...document.querySelectorAll('[data-nextsnapmail-admin-route]')];
	const embeddedPages = new Set(['domains', 'webmail', 'extensions']);
	const savePages = new Set(['domains', 'integration']);
	let webmailRoute = 'general';

	const setAdminRoute = route => {
		const nextHash = '#/' + route;
		if (iframe.contentWindow && iframe.contentWindow.location.hash !== nextHash) {
			iframe.contentWindow.location.hash = nextHash;
		}
	};

	const showPage = page => {
		if (!pageButtons.some(button => button.dataset.nextsnapmailPage === page)) {
			page = 'domains';
		}

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

		if (workspace) {
			workspace.hidden = !embeddedPages.has(page);
		}
		if (saveArea) {
			saveArea.hidden = !savePages.has(page);
		}

		if ('domains' === page) {
			setAdminRoute('domains');
		} else if ('extensions' === page) {
			setAdminRoute('packages');
		} else if ('webmail' === page) {
			setAdminRoute(webmailRoute);
		}

		try {
			sessionStorage.setItem('nextsnapmail-admin-page', page);
		} catch (error) {
			// Storage may be disabled without affecting the settings page.
		}
	};

	pageButtons.forEach(button => button.addEventListener('click', () => {
		showPage(button.dataset.nextsnapmailPage);
	}));

	routeButtons.forEach(button => button.addEventListener('click', () => {
		webmailRoute = button.dataset.nextsnapmailAdminRoute || 'general';
		routeButtons.forEach(candidate => candidate.classList.toggle('active', candidate === button));
		setAdminRoute(webmailRoute);
	}));

	iframe.addEventListener('load', () => {
		try {
			const iframeDocument = iframe.contentDocument;
			iframeDocument?.documentElement?.classList.add('nextsnapmail-integrated-admin-document');
			iframeDocument?.body?.classList.add('nextsnapmail-integrated-admin-body');
			if (iframeDocument && !iframeDocument.getElementById('nextsnapmail-integrated-admin-style')) {
				const style = iframeDocument.createElement('style');
				style.id = 'nextsnapmail-integrated-admin-style';
				style.textContent = `
					#rl-left, #V-AdminPane > .b-toolbar { display: none !important; }
					#rl-right { min-width: 100% !important; }
					#V-AdminPane {
						height: calc(100% - 16px) !important;
						margin: 8px !important;
						box-shadow: none !important;
					}
				`;
				iframeDocument.head.appendChild(style);
			}
			const activePage = pageButtons.find(button => button.classList.contains('active'))?.dataset.nextsnapmailPage;
			if ('extensions' === activePage) {
				setAdminRoute('packages');
			} else if ('webmail' === activePage) {
				setAdminRoute(webmailRoute);
			} else {
				setAdminRoute('domains');
			}
		} catch (error) {
			console.warn('Could not prepare integrated NextSnapMail administration.', error);
		}
	});

	let initialPage = 'domains';
	try {
		initialPage = sessionStorage.getItem('nextsnapmail-admin-page') || initialPage;
	} catch (error) {
		// Keep the default page when storage is unavailable.
	}
	showPage(initialPage);
}

// Pass Nextcloud themes and theme attributes to SnappyMail on
// first load and when the SnappyMail iframe is reloaded.
function passThemesToIFrame() {
	const iframe = document.getElementById('rliframe');
	if (!iframe) return;

	let firstLoad = true;

	iframe.addEventListener('load', event => {
		// repass theme styles when iframe is reloaded
		if (!firstLoad) {
			passThemes(event.target);
		}
		firstLoad = false;
	});

	passThemes(iframe);
}

// Pass Nextcloud themes and theme attributes to SnappyMail.
function passThemes(iframe) {
	if (!iframe) return;

	const target = iframe.contentWindow.document;

	const ncStylesheets = [...document.querySelectorAll('link.theme')];
	ncStylesheets.forEach(ncSheet => {
		const smSheet = target.importNode(ncSheet, true);
		target.head.appendChild(smSheet);
	});

	const themes = [...document.body.attributes].filter(att => att.name.startsWith('data-theme'));
	themes.forEach(theme => target.body.setAttribute(theme.name, theme.value));
}

// The SnappyMail application is already configured to modify the <title> element
// of its root document with the number of unread messages in the inbox.
// However, its document is the SnappyMail iframe. This function sets up a
// Mutation Observer to watch the <title> element of the iframe for changes in
// the unread message count and propagates that to the parent <title> element,
// allowing the unread message count to be displayed in the NC tab's text when
// the SnappyMail app is selected.
function watchIFrameTitle() {
	let iframe = document.getElementById('rliframe');
	if (!iframe) {
		return;
	}
	let target = iframe.contentDocument.getElementsByTagName('title')[0];
	let config = {
		characterData: true,
		childList: true,
		subtree: true
	};
	let observer = new MutationObserver(mutations => {
		let title = mutations[0].target.innerText;
		if (title) {
			let matches = title.match(/\(([0-9]+)\)/);
			if (matches) {
				document.title = '('+ matches[1] + ') ' + t('nextsnapmail', 'Email') + ' - Nextcloud';
			} else {
				document.title = t('nextsnapmail', 'Email') + ' - Nextcloud';
			}
		}
	});
	observer.observe(target, config);
}

function SnappyMailFormHelper(oForm)
{
	try
	{
		var
			oSubmit = document.getElementById('nextsnapmail-save-button'),
			sSubmitValue = oSubmit.textContent,
			oDesc = oForm.querySelector('.nextsnapmail-result-desc')
		;

		oForm.addEventListener('submit', oEvent => {
			oEvent.preventDefault();

			oForm.classList.add('nextsnapmail-fetch')
			oForm.classList.remove('nextsnapmail-error')
			oForm.classList.remove('nextsnapmail-success')

			oDesc.textContent = '';
			oSubmit.textContent = '...';

			let data = new FormData(oForm);
			data.set('appname', 'nextsnapmail');

			fetch(OC.filePath('nextsnapmail', 'fetch', oForm.getAttribute('action')), {
				mode: 'same-origin',
				cache: 'no-cache',
				redirect: 'error',
				referrerPolicy: 'no-referrer',
				credentials: 'same-origin',
				method: 'POST',
				headers: {},
				body: data
			})
			.then(response => response.json())
			.then(oData => {
				let bResult = 'success' === oData?.status;
				oForm.classList.remove('nextsnapmail-fetch');
				oSubmit.textContent = sSubmitValue;
				if (oData?.Message) {
					oDesc.textContent = t('nextsnapmail', oData.Message);
				}
				if (bResult) {
					oForm.classList.add('nextsnapmail-success');
				} else {
					oForm.classList.add('nextsnapmail-error');
					if ('' === oDesc.textContent) {
						oDesc.textContent = t('nextsnapmail', 'Error');
					}
				}
			});

			return false;
		});
	} catch (e) {
		console.error(e);
	}
}

function setupSnappyMailImportPreview()
{
	const button = document.getElementById('nextsnapmail-import-snappymail-button');
	if (!button) {
		return;
	}

	const result = document.querySelector('.nextsnapmail-import-result-desc');
	button.addEventListener('click', event => {
		event.preventDefault();

		const originalText = button.textContent;
		button.textContent = '...';
		if (result) {
			result.textContent = '';
		}

		nextsnapmailAdminRequest({
			'nextsnapmail-list-old-snappymail-accounts': '1'
		})
		.then(response => response.json())
		.then(data => {
			button.textContent = originalText;
			renderOldSnappyMailAccounts(data, result);
		})
		.catch(error => {
			button.textContent = originalText;
			if (result) {
				result.textContent = error?.message || t('nextsnapmail', 'Error');
			}
		});
	});
}

function setupNextSnapMailReset()
{
	const button = document.getElementById('nextsnapmail-reset-button');
	if (!button) {
		return;
	}

	const result = document.querySelector('.nextsnapmail-reset-result-desc');
	button.addEventListener('click', event => {
		event.preventDefault();

		if (!confirm(t('nextsnapmail', 'Do you really want to remove all accounts from the database and delete the data folder?'))) {
			return;
		}

		const confirmationCode = 'RESET';
		const confirmation = prompt(t('nextsnapmail', 'To confirm reset, type: {code}', {code: confirmationCode}));
		if (confirmation !== confirmationCode) {
			if (result) {
				result.textContent = t('nextsnapmail', 'NextSnapMail data was not reset because the confirmation code was missing or invalid.');
			}
			return;
		}

		const originalText = button.textContent;
		button.textContent = '...';
		if (result) {
			result.textContent = '';
		}

		nextsnapmailAdminRequest({
			'nextsnapmail-reset-data': '1',
			'nextsnapmail-reset-confirmation': confirmation
		})
		.then(response => response.json())
		.then(responseData => {
			button.textContent = originalText;
			renderOldSnappyMailAccounts(responseData, result);
		})
		.catch(error => {
			button.textContent = originalText;
			if (result) {
				result.textContent = error?.message || t('nextsnapmail', 'Error');
			}
		});
	});
}

function setupNextSnapMailPluginUpload()
{
	const button = document.getElementById('nextsnapmail-upload-plugin-button');
	if (!button) {
		return;
	}

	const fileInput = document.getElementById('nextsnapmail-plugin-package');
	const overwriteInput = document.getElementById('nextsnapmail-plugin-overwrite');
	const result = document.querySelector('.nextsnapmail-plugin-upload-result-desc');

	button.addEventListener('click', event => {
		event.preventDefault();

		const file = fileInput?.files?.[0];
		if (!file) {
			if (result) {
				result.textContent = t('nextsnapmail', 'Please select a plugin package first.');
			}
			return;
		}

		if (!confirm(t('nextsnapmail', 'Only upload trusted plugin packages. Plugins are executable PHP code. Continue?'))) {
			return;
		}

		const originalText = button.textContent;
		button.textContent = '...';
		if (result) {
			result.textContent = '';
		}

		const data = new FormData();
		data.set('appname', 'nextsnapmail');
		data.set('nextsnapmail-upload-plugin', '1');
		data.set('nextsnapmail-plugin-package', file);
		if (overwriteInput?.checked) {
			data.set('nextsnapmail-plugin-overwrite', '1');
		}

		const requestToken = document.getElementById('requesttoken');
		if (requestToken) {
			data.set('requesttoken', requestToken.value);
		}

		fetch(OC.filePath('nextsnapmail', 'fetch', 'admin.php'), {
			mode: 'same-origin',
			cache: 'no-cache',
			redirect: 'error',
			referrerPolicy: 'no-referrer',
			credentials: 'same-origin',
			method: 'POST',
			headers: {},
			body: data
		})
		.then(response => response.json())
		.then(data => {
			button.textContent = originalText;
			if (result) {
				result.textContent = data?.Message || t('nextsnapmail', 'Error');
			}
			if ('success' === data?.status && fileInput) {
				fileInput.value = '';
			}
		})
		.catch(error => {
			button.textContent = originalText;
			if (result) {
				result.textContent = error?.message || t('nextsnapmail', 'Error');
			}
		});
	});
}

function nextsnapmailAdminRequest(values)
{
	const data = new FormData();
	data.set('appname', 'nextsnapmail');
	Object.keys(values).forEach(key => {
		const value = values[key];
		if (Array.isArray(value)) {
			value.forEach(item => data.append(key + '[]', item));
		} else {
			data.set(key, value);
		}
	});

	const requestToken = document.getElementById('requesttoken');
	if (requestToken) {
		data.set('requesttoken', requestToken.value);
	}

	return fetch(OC.filePath('nextsnapmail', 'fetch', 'admin.php'), {
		mode: 'same-origin',
		cache: 'no-cache',
		redirect: 'error',
		referrerPolicy: 'no-referrer',
		credentials: 'same-origin',
		method: 'POST',
		headers: {},
		body: data
	});
}

function renderOldSnappyMailAccounts(data, container)
{
	if (!container) {
		return;
	}

	container.textContent = '';

	const message = document.createElement('p');
	message.style.whiteSpace = 'pre-wrap';
	message.textContent = data?.Message || t('nextsnapmail', 'Error');
	container.appendChild(message);

	if ('success' !== data?.status) {
		return;
	}

	const accounts = Array.isArray(data?.Accounts) ? data.Accounts : [];
	if (!accounts.length) {
		return;
	}

	const table = document.createElement('table');
	table.className = 'grid';
	table.style.marginTop = '10px';
	table.style.marginBottom = '10px';

	const thead = document.createElement('thead');
	const headRow = document.createElement('tr');
	[
		t('nextsnapmail', 'Import'),
		t('nextsnapmail', 'User'),
		t('nextsnapmail', 'Email'),
		t('nextsnapmail', 'Password'),
		t('nextsnapmail', 'NextSnapMail account')
	].forEach(label => {
		const th = document.createElement('th');
		th.textContent = label;
		headRow.appendChild(th);
	});
	thead.appendChild(headRow);
	table.appendChild(thead);

	const tbody = document.createElement('tbody');
	accounts.forEach(account => {
		const tr = document.createElement('tr');

		const selectCell = document.createElement('td');
		const checkbox = document.createElement('input');
		checkbox.type = 'checkbox';
		checkbox.className = 'nextsnapmail-import-account-checkbox';
		checkbox.value = account.uid || '';
		checkbox.checked = !account.nextsnapmailExists;
		selectCell.appendChild(checkbox);
		tr.appendChild(selectCell);

		[
			account.uid || '',
			account.email || t('nextsnapmail', 'no email address found'),
			account.hasPassword ? t('nextsnapmail', 'password/passphrase present') : t('nextsnapmail', 'no password/passphrase found'),
			account.nextsnapmailExists ? t('nextsnapmail', 'already exists') : t('nextsnapmail', 'does not exist yet')
		].forEach(value => {
			const td = document.createElement('td');
			td.textContent = value;
			tr.appendChild(td);
		});

		tbody.appendChild(tr);
	});
	table.appendChild(tbody);

	const bulkRow = document.createElement('tr');
	const bulkCell = document.createElement('td');
	bulkCell.colSpan = 4;
	const toggleAllLabel = document.createElement('label');
	const toggleAll = document.createElement('input');
	toggleAll.type = 'checkbox';
	toggleAll.checked = true;
	toggleAll.addEventListener('change', () => {
		container.querySelectorAll('.nextsnapmail-import-account-checkbox').forEach(checkbox => {
			checkbox.checked = toggleAll.checked;
		});
	});
	toggleAllLabel.appendChild(toggleAll);
	toggleAllLabel.appendChild(document.createTextNode(' ' + t('nextsnapmail', 'Select/deselect all')));
	bulkCell.appendChild(toggleAllLabel);
	bulkRow.appendChild(bulkCell);
	tbody.appendChild(bulkRow);

	container.appendChild(table);

	const importButton = document.createElement('button');
	importButton.type = 'button';
	importButton.textContent = t('nextsnapmail', 'Import selected from SnappyMail');

	const deleteButton = document.createElement('button');
	deleteButton.type = 'button';
	deleteButton.textContent = t('nextsnapmail', 'Remove selected NextSnapMail accounts');
	deleteButton.style.marginLeft = '8px';

	const deleteOldDataButton = document.createElement('button');
	deleteOldDataButton.type = 'button';
	deleteOldDataButton.textContent = t('nextsnapmail', 'Delete old SnappyMail data');
	deleteOldDataButton.style.marginLeft = '24px';
	deleteOldDataButton.style.backgroundColor = '#d32f2f';
	deleteOldDataButton.style.borderColor = '#d32f2f';
	deleteOldDataButton.style.color = '#fff';

	const buttons = document.createElement('p');
	buttons.appendChild(importButton);
	buttons.appendChild(deleteButton);
	buttons.appendChild(deleteOldDataButton);
	container.appendChild(buttons);

	importButton.addEventListener('click', event => {
		event.preventDefault();

		const selected = [...container.querySelectorAll('.nextsnapmail-import-account-checkbox:checked')]
			.map(checkbox => checkbox.value)
			.filter(Boolean);

		if (!selected.length) {
			const noSelection = document.createElement('p');
			noSelection.textContent = t('nextsnapmail', 'No accounts were selected for import.');
			container.appendChild(noSelection);
			return;
		}

		const originalText = importButton.textContent;
		importButton.textContent = '...';

		nextsnapmailAdminRequest({
			'nextsnapmail-import-selected-snappymail-accounts': '1',
			'nextsnapmail-selected-accounts': selected,
			'nextsnapmail-import-appdata': '1'
		})
		.then(response => response.json())
		.then(responseData => {
			importButton.textContent = originalText;
			renderOldSnappyMailAccounts(responseData, container);
		})
		.catch(error => {
			importButton.textContent = originalText;
			const errorMessage = document.createElement('p');
			errorMessage.textContent = error?.message || t('nextsnapmail', 'Error');
			container.appendChild(errorMessage);
		});
	});

	deleteButton.addEventListener('click', event => {
		event.preventDefault();

		const selected = [...container.querySelectorAll('.nextsnapmail-import-account-checkbox:checked')]
			.map(checkbox => checkbox.value)
			.filter(Boolean);

		if (!selected.length) {
			const noSelection = document.createElement('p');
			noSelection.textContent = t('nextsnapmail', 'No accounts were selected for deletion.');
			container.appendChild(noSelection);
			return;
		}

		const originalText = deleteButton.textContent;
		deleteButton.textContent = '...';

		nextsnapmailAdminRequest({
			'nextsnapmail-delete-imported-account': '1',
			'nextsnapmail-delete-account-uid': selected
		})
		.then(response => response.json())
		.then(responseData => {
			deleteButton.textContent = originalText;
			renderOldSnappyMailAccounts(responseData, container);
		})
		.catch(error => {
			deleteButton.textContent = originalText;
			const errorMessage = document.createElement('p');
			errorMessage.textContent = error?.message || t('nextsnapmail', 'Error');
			container.appendChild(errorMessage);
		});
	});

	deleteOldDataButton.addEventListener('click', event => {
		event.preventDefault();

		if (!confirm(t('nextsnapmail', 'Do you really want to delete all old SnappyMail data (appdata and accounts in the database)?'))) {
			return;
		}
		const confirmationCode = 'DELETE_SNAPPYMAIL_DATA';
		const confirmation = prompt(t('nextsnapmail', 'To confirm deletion, type: {code}', {code: confirmationCode}));
		if (confirmation !== confirmationCode) {
			const cancelled = document.createElement('p');
			cancelled.textContent = t('nextsnapmail', 'Old SnappyMail data was not deleted because the confirmation code was missing or invalid.');
			container.appendChild(cancelled);
			return;
		}

		const originalText = deleteOldDataButton.textContent;
		deleteOldDataButton.textContent = '...';

		nextsnapmailAdminRequest({
			'nextsnapmail-delete-old-snappymail-data': '1',
			'nextsnapmail-delete-old-snappymail-confirmation': confirmation
		})
		.then(response => response.json())
		.then(responseData => {
			deleteOldDataButton.textContent = originalText;
			renderOldSnappyMailAccounts(responseData, container);
		})
		.catch(error => {
			deleteOldDataButton.textContent = originalText;
			const errorMessage = document.createElement('p');
			errorMessage.textContent = error?.message || t('nextsnapmail', 'Error');
			container.appendChild(errorMessage);
		});
	});
}

function setupUnifiedSearchListener() {
	const iframe = document.getElementById('rliframe');
	if (!iframe || !iframe.contentWindow) return;

	addEventListener('hashchange', (event) => {
		const hashIndex = event.newURL.indexOf('#/mailbox/');
		if (hashIndex !== -1) {
			const hash = event.newURL.substring(hashIndex + 1);
			if (/\/[\w-]+\/[\w-]+\/\w\d+\/.{0,24}/.test(hash)) {
				iframe.contentWindow.location.hash = hash;
			}
		}
	});
}
