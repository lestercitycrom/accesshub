<!doctype html>
<html lang="ru">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>AccessHub</title>

	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<script src="https://telegram.org/js/telegram-web-app.js"></script>

	<style>
		:root {
			--ah-bg: #0F1722;
			--ah-header: #28323A;
			--ah-panel: #182232;
			--ah-panel-2: #141C29;
			--ah-border: rgba(255, 255, 255, .10);
			--ah-input: rgba(255, 255, 255, .06);
			--ah-text: #E6EDF3;
			--ah-hint: rgba(230, 237, 243, .60);
			--ah-placeholder: rgba(230, 237, 243, .45);
			--ah-accent: #2481C9;
			--ah-accent-weak: rgba(36, 129, 201, .20);
			--ah-code: rgba(0, 0, 0, .55);
		}

		html, body {
			background: var(--ah-bg) !important;
			color: var(--ah-text) !important;
		}

		.app-shell {
			padding: 10px;
			padding-bottom: 80px;
		}

		.meta-bar {
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: 12px;
			color: var(--ah-hint);
			font-size: 12px;
			margin: 8px 0 10px;
		}

		.meta-bar .meta-right {
			color: var(--ah-hint);
			font-size: 12px;
		}

		.tabs-rail {
			position: relative;
			overflow: hidden;
			padding: 0 10px 6px;
		}

		.tabs-wrap {
			overflow-x: auto;
			overflow-y: hidden;
			-webkit-overflow-scrolling: touch;
			scrollbar-width: none;
		}

		.tabs-wrap::-webkit-scrollbar {
			display: none;
		}

		.tabs-rail::before,
		.tabs-rail::after {
			content: '';
			position: absolute;
			top: 0;
			width: 18px;
			height: 44px;
			pointer-events: none;
			z-index: 5;
		}

		.tabs-rail::before {
			left: 0;
			background: linear-gradient(to right, var(--ah-bg), rgba(0, 0, 0, 0));
		}

		.tabs-rail::after {
			right: 0;
			background: linear-gradient(to left, var(--ah-bg), rgba(0, 0, 0, 0));
		}

		.nav-tabs {
			border: 0;
			flex-wrap: nowrap;
			gap: 8px;
		}

		.nav-tabs .nav-link {
			flex: 0 0 auto;
			border: 1px solid var(--ah-border);
			background: rgba(255, 255, 255, .04);
			color: var(--ah-text);
			border-radius: 10px;
			padding: 6px 10px;
			font-size: 14px;
			white-space: nowrap;
		}

		.nav-tabs .nav-link.active {
			background: var(--ah-accent);
			border-color: var(--ah-accent);
			color: #fff;
		}

		.tab-content {
			background: transparent !important;
			border: 0 !important;
			padding: 0 !important;
		}

		.card-panel {
			background: var(--ah-panel);
			border: 1px solid var(--ah-border);
			border-radius: 12px;
			padding: 12px;
			margin-top: 10px;
		}

		.card-panel form {
			background: transparent !important;
			border: none !important;
			padding: 0 !important;
			margin: 0 !important;
		}

		.card-section {
			background: var(--ah-panel-2);
			border: 1px solid var(--ah-border);
			border-radius: 10px;
			padding: 12px;
			margin-bottom: 8px;
		}

		.divider {
			height: 1px;
			background: var(--ah-border);
			margin: 12px 0;
			border: 0;
		}

		.form-label {
			display: none !important;
		}

		.form-control {
			border-radius: 8px !important;
			border: 1px solid var(--ah-border) !important;
			background: var(--ah-input) !important;
			color: var(--ah-text) !important;
			box-shadow: none !important;
		}

		.form-control::placeholder {
			color: var(--ah-placeholder) !important;
		}

		.form-control:focus {
			background: var(--ah-input) !important;
			border-color: rgba(36, 129, 201, .55) !important;
			box-shadow: 0 0 0 2px var(--ah-accent-weak) !important;
			outline: none !important;
		}

		.form-control:focus::placeholder {
			color: rgba(230, 237, 243, .30) !important;
		}

		.btn {
			border-radius: 8px !important;
		}

		.tab-header {
			text-align: center;
			padding-top: 12px;
			padding-bottom: 10px;
		}

		.tab-icon-wrap {
			width: 72px;
			height: 72px;
			border-radius: 999px;
			margin: 0 auto 12px;
			border: 1px solid var(--ah-border);
			background: rgba(255, 255, 255, .03);
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 26px;
		}

		.tab-title {
			font-size: 20px;
			font-weight: 700;
			margin: 0;
		}

		.tab-subtitle {
			margin-top: 6px;
			font-size: 12px;
			color: rgba(230, 237, 243, .55);
		}

		.history-pagination {
			display: flex;
			align-items: center;
			justify-content: space-between;
			margin-top: 16px;
			gap: 12px;
		}

		.history-pagination-info {
			color: var(--ah-hint);
			font-size: 12px;
		}

		.history-pagination-buttons {
			display: flex;
			gap: 8px;
		}

		.history-pagination-btn {
			padding: 6px 12px;
			border-radius: 10px;
			border: 1px solid var(--ah-border);
			background: var(--ah-panel);
			color: var(--ah-text);
			font-size: 13px;
			cursor: pointer;
		}

		.history-pagination-btn:hover:not(:disabled) {
			background: var(--ah-panel-2);
		}

		.history-pagination-btn:disabled {
			opacity: 0.5;
			cursor: not-allowed;
		}

		#historyList {
			min-height: 100px;
		}

		#historyLoader {
			text-align: center;
			padding: 16px;
			color: var(--ah-hint);
			font-size: 13px;
		}

		#historyLoadMoreBtn {
			width: 100%;
			margin-top: 12px;
		}

		#historyEnd {
			text-align: center;
			padding: 16px;
			color: var(--ah-hint);
			font-size: 12px;
		}

		#historySentinel {
			height: 1px;
			margin: 10px 0;
		}

		.sticky-actions {
			position: sticky;
			bottom: 10px;
			padding-top: 10px;
			background: linear-gradient(to top, var(--ah-panel) 70%, rgba(0, 0, 0, 0));
		}

		.footer-action {
			position: fixed;
			left: 0;
			right: 0;
			bottom: 0;
			padding: 10px 10px calc(10px + env(safe-area-inset-bottom));
			background: var(--ah-bg);
			border-top: 1px solid var(--ah-border);
			z-index: 100;
		}

		.footer-action .btn {
			width: 100%;
			border-radius: 8px !important;
			padding: 10px 12px;
			font-size: 14px;
			font-weight: 600;
		}

		pre.codebox {
			white-space: pre-wrap;
			word-break: break-word;
			padding: 12px;
			border-radius: 10px;
			background: var(--ah-code);
			border: 1px solid var(--ah-border);
			margin: 0;
			font-size: 13px;
			line-height: 1.35;
			color: var(--ah-text);
		}

		.history-card {
			background: var(--ah-panel);
			border: 1px solid var(--ah-border);
			border-radius: 12px;
			padding: 10px;
			margin-bottom: 8px;
		}

		.history-card:last-of-type {
			margin-bottom: 0;
		}

		.history-card-line {
			margin-bottom: 4px;
			font-size: 13px;
			line-height: 1.4;
		}

		.history-card-line:last-child {
			margin-bottom: 0;
		}

		.history-card-line strong {
			color: var(--ah-hint);
			font-weight: 600;
		}

		.history-meta {
			color: var(--ah-hint);
			font-size: 11px;
			margin-top: 12px;
		}

		.table {
			color: var(--ah-text);
		}

		.table thead th {
			border-color: var(--ah-border);
			color: var(--ah-hint);
		}

		.table tbody td {
			border-color: var(--ah-border);
		}

		.table-striped > tbody > tr:nth-of-type(odd) > td {
			background-color: rgba(255, 255, 255, .02);
		}

		#copyright {
			color: var(--ah-hint);
			font-size: 11px;
		}
	</style>
</head>
<body>
<div class="app-shell">
	<div class="meta-bar">
		<div class="meta-left" id="roleLine">Role: <span id="roleText">...</span></div>
		<div class="meta-right" id="tgIdLine">TG: <span id="tgIdText">-</span></div>
	</div>

	<div id="alerts"></div>

	<div class="tabs-rail">
		<div class="tabs-wrap">
			<ul class="nav nav-tabs" id="tabsNav"></ul>
		</div>
	</div>
	<div class="tab-content" id="tabsContent"></div>
</div>

<div id="footerAction" class="footer-action d-none"></div>
<div id="copyright" class="text-center small text-muted my-3">@accesshub_123_bot</div>

<script>
	(function () {
		const tg = window.Telegram?.WebApp;
		if (!tg) {
			alert('Telegram WebApp API not found');
			return;
		}

		tg.ready();
		tg.expand();

		// Hide Telegram bot menu (ReplyKeyboard) - menu is automatically hidden when WebApp is open
		// Ensure WebApp takes full height to prevent menu from showing
		if (tg.viewportStableHeight !== undefined) {
			tg.viewportStableHeight = window.innerHeight;
		}

		// Set Telegram WebApp header colors
		tg.setBackgroundColor('#0F1722');
		tg.setHeaderColor('#28323A');
		document.documentElement.style.setProperty('--ah-accent', '#2481C9');

		const initData = tg.initData || '';
		const user = tg.initDataUnsafe?.user;
		const tgLang = user?.language_code ? String(user.language_code).slice(0, 2).toLowerCase() : '';

		// Update TG ID after translations are loaded (in init())

		const alerts = document.getElementById('alerts');
		const footerAction = document.getElementById('footerAction');
		const copyright = document.getElementById('copyright');

		// Translations storage
		let translations = {};

		function t(key, params = {}) {
			let text = translations[key] || key;
			for (const [k, v] of Object.entries(params)) {
				text = text.replace(':' + k, v);
			}
			return text;
		}

		function updateCopyrightVisibility() {
			const isFooterVisible = footerAction && !footerAction.classList.contains('d-none');
			if (copyright) {
				if (isFooterVisible) {
					copyright.classList.add('d-none');
				} else {
					copyright.classList.remove('d-none');
				}
			}
		}

		function showAlert(type, text) {
			alerts.innerHTML = `
				<div class="alert alert-${type} alert-dismissible fade show" role="alert">
					${escapeHtml(text)}
					<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
				</div>
			`;
		}

		function escapeHtml(s) {
			return String(s)
				.replaceAll('&', '&amp;')
				.replaceAll('<', '&lt;')
				.replaceAll('>', '&gt;')
				.replaceAll('"', '&quot;')
				.replaceAll("'", '&#039;');
		}

		async function apiGet(url) {
			const resp = await fetch(url, {
				method: 'GET',
				headers: {
					'X-TG-INIT-DATA': initData,
					...(tgLang ? { 'X-Tg-Lang': tgLang } : {}),
					'Accept': 'application/json',
				},
			});

			const data = await resp.json().catch(() => null);

			if (!resp.ok || !data) {
				const message = data?.error?.message || 'API request failed';
					if (resp.status === 403 || message === 'no access') {
						throw new Error(t('no_access'));
					}
					if (resp.status === 401) {
						throw new Error(t('auth_error'));
					}
				throw new Error(message);
			}

			if (data.ok !== true) {
				const message = data?.error?.message || 'API error';
				if (data?.error?.code === 'FORBIDDEN' || message === 'no access') {
					throw new Error(t('no_access'));
				}
				throw new Error(message);
			}

			return data;
		}

		async function apiPost(url, payload) {
			const resp = await fetch(url, {
				method: 'POST',
				headers: {
					'X-TG-INIT-DATA': initData,
					...(tgLang ? { 'X-Tg-Lang': tgLang } : {}),
					'Accept': 'application/json',
					'Content-Type': 'application/json',
				},
				body: JSON.stringify(payload || {}),
			});

			const data = await resp.json().catch(() => null);

			if (!resp.ok || !data) {
				const message = data?.error?.message || 'API request failed';
				if (resp.status === 403 || message === 'no access') {
					throw new Error(t('no_access'));
				}
				if (resp.status === 401) {
					throw new Error(t('auth_error'));
				}
				throw new Error(message);
			}

			if (data.ok !== true) {
				const message = data?.error?.message || 'API error';
				if (data?.error?.code === 'FORBIDDEN' || message === 'no access') {
					throw new Error(t('no_access'));
				}
				const fieldsObj = data?.error?.fields;
				if (fieldsObj && typeof fieldsObj === 'object') {
					const lines = [];
					for (const [field, msgs] of Object.entries(fieldsObj)) {
						if (Array.isArray(msgs)) {
							lines.push(`${field}: ${msgs.join(', ')}`);
						} else {
							lines.push(`${field}: ${String(msgs)}`);
						}
					}
					throw new Error(message + (lines.length ? ('\n' + lines.join('\n')) : ''));
				}
				throw new Error(message);
			}

			return data;
		}

		function enableTabsWheelScroll() {
			const rail = document.querySelector('.tabs-rail');
			const wrap = document.querySelector('.tabs-wrap');
			if (!rail || !wrap) return;

			rail.addEventListener('wheel', (e) => {
				const canScrollX = wrap.scrollWidth > wrap.clientWidth;
				if (!canScrollX) return;

				if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) return;

				e.preventDefault();
				wrap.scrollLeft += e.deltaY;
			}, { passive: false });
		}

		function buildQuery(params) {
			const q = new URLSearchParams();

			for (const [k, v] of Object.entries(params || {})) {
				if (v === null || v === undefined) continue;
				const s = String(v).trim();
				if (s === '') continue;
				q.append(k, v);
			}

			return q.toString();
		}

		function createTabNavItem(tab, isActive) {
			const li = document.createElement('li');
			li.className = 'nav-item';

			const btn = document.createElement('button');
			btn.className = 'nav-link' + (isActive ? ' active' : '');
			btn.type = 'button';
			btn.dataset.bsToggle = 'tab';
			btn.dataset.bsTarget = '#tab_' + tab.id;
			btn.textContent = tab.title;

			li.appendChild(btn);
			return li;
		}

		function createTabPane(tab, isActive) {
			const pane = document.createElement('div');
			pane.className = 'tab-pane fade' + (isActive ? ' show active' : '');
			pane.id = 'tab_' + tab.id;

			const panel = document.createElement('div');
			panel.className = 'card-panel';

			// Tab header with icon (centered, BotFather-style with circle)
			if (tab.header) {
				const header = document.createElement('div');
				header.className = 'tab-header';

				if (tab.header.icon) {
					const iconWrap = document.createElement('div');
					iconWrap.className = 'tab-icon-wrap';
					iconWrap.textContent = tab.header.icon;
					header.appendChild(iconWrap);
				}

				const title = document.createElement('h2');
				title.className = 'tab-title';
				title.textContent = tab.header.title || tab.title;
				header.appendChild(title);

				if (tab.header.subtitle) {
					const subtitle = document.createElement('div');
					subtitle.className = 'tab-subtitle';
					subtitle.textContent = tab.header.subtitle;
					header.appendChild(subtitle);
				}

				panel.appendChild(header);
			} else {
				const header = document.createElement('div');
				header.className = 'tab-header';
				const title = document.createElement('h2');
				title.className = 'tab-title';
				title.textContent = tab.title;
				header.appendChild(title);
				panel.appendChild(header);
			}

			const body = document.createElement('div');
			body.id = 'body_' + tab.id;
			panel.appendChild(body);

			pane.appendChild(panel);
			return pane;
		}

		function renderStatic(tab, root) {
			const pre = document.createElement('pre');
			pre.className = 'codebox';
			pre.textContent = tab.content || '';
			root.appendChild(pre);
		}

		function renderForm(tab, root) {
			const form = document.createElement('form');
			form.className = 'row g-2';
			// Remove any background/border that might create panel-like appearance
			form.style.background = 'transparent';
			form.style.border = 'none';
			form.style.padding = '0';

			const fields = Array.isArray(tab.fields) ? tab.fields : [];

			for (const f of fields) {
				const col = document.createElement('div');
				col.className = 'col-12';

				let input;

				if (f.type === 'textarea') {
					input = document.createElement('textarea');
					input.rows = 5;
				} else {
					input = document.createElement('input');
					if (f.type === 'textarea') {
						input = document.createElement('textarea');
						input.className = 'form-control';
						input.rows = 8;
					} else {
						input.type = (f.type === 'number') ? 'number' : 'text';
					}
				}

				input.className = 'form-control';
				input.name = f.name;
				input.placeholder = f.placeholder || f.label || f.name;

				if (f.required) input.required = true;
				if (f.pattern) input.pattern = f.pattern;
				if (f.min !== undefined) input.min = String(f.min);
				if (f.max !== undefined) input.max = String(f.max);
				if (f.default !== undefined) input.value = String(f.default);

				col.appendChild(input);
				form.appendChild(col);
			}

			// Check if single-action form (BotFather-style footer)
			// Single action if: no allow_clear (only one button will be shown)
			const hasAllowClear = tab.allow_clear === true;
			const isSingleAction = !hasAllowClear;

			// Mark form for tab switching
			form.dataset.singleAction = isSingleAction ? 'true' : 'false';

			if (isSingleAction) {
				// Single action: use footer
				const isActive = root.closest('.tab-pane')?.classList.contains('active');
				if (isActive) {
					footerAction.classList.remove('d-none');
					footerAction.innerHTML = '';
				const footerBtn = document.createElement('button');
				footerBtn.type = 'button'; // Prevent form submit - handle manually
				footerBtn.className = 'btn btn-primary';
				footerBtn.textContent = t('submit');
					footerBtn.addEventListener('click', (e) => {
						e.preventDefault();
						e.stopPropagation();
						handleSubmit(e);
					});
					footerAction.appendChild(footerBtn);
					document.querySelector('.app-shell').style.paddingBottom = '80px';
					updateCopyrightVisibility();
				}
			} else {
				// Multi-action: use sticky actions
				footerAction.classList.add('d-none');
				document.querySelector('.app-shell').style.paddingBottom = '10px';
				updateCopyrightVisibility();

				const actions = document.createElement('div');
				actions.className = 'col-12 mt-2 d-flex gap-2 sticky-actions';

				const submitBtn = document.createElement('button');
				submitBtn.type = 'submit';
				submitBtn.className = 'btn btn-primary';
				submitBtn.textContent = t('submit');

				actions.appendChild(submitBtn);

				// Show "Очистить" only if allow_clear is true
				if (tab.allow_clear === true) {
				const resetBtn = document.createElement('button');
				resetBtn.type = 'button';
				resetBtn.className = 'btn btn-outline-secondary';
				resetBtn.textContent = t('clear');
					resetBtn.addEventListener('click', () => form.reset());
					actions.appendChild(resetBtn);
				}

				form.appendChild(actions);
			}

			const resultBox = document.createElement('div');
			resultBox.className = 'col-12 mt-3';
			const placeholderDiv = document.createElement('div');
			placeholderDiv.className = 'small';
			placeholderDiv.style.color = 'rgba(230, 237, 243, .55)';
			placeholderDiv.style.fontSize = '12px';
			placeholderDiv.textContent = t('result_placeholder');
			resultBox.appendChild(placeholderDiv);

			form.appendChild(resultBox);

			// Handle submit from both footer and form
			const handleSubmit = async (e) => {
				if (e) e.preventDefault();

				const payload = {};
				const fd = new FormData(form);
				fd.forEach((v, k) => payload[k] = v);

				// Ensure numbers
				for (const f of fields) {
					if (f.type === 'number' && payload[f.name] !== undefined) {
						const n = parseInt(String(payload[f.name]), 10);
						payload[f.name] = Number.isFinite(n) ? n : 0;
					}
				}

				try {
					if (tab.submit?.type === 'bot') {
						const data = {
							action: tab.submit.action,
							request_id: (crypto?.randomUUID ? crypto.randomUUID() : String(Date.now())),
							payload: payload,
						};

						tg.sendData(JSON.stringify(data));
						tg.close();
						return;
					}

					if (tab.submit?.type === 'api') {
						const endpoint = tab.submit.endpoint;
						
						// Check if this is history tab
						const isHistoryTab = tab.id === 'history';
						
						if (isHistoryTab) {
							// History: setup infinite scroll with new filters
							resultBox.innerHTML = '';
							
							historyReset();
							historyState.endpoint = endpoint;
							historyState.baseParams = payload;
							
							// Create history structure
							const listContainer = document.createElement('div');
							listContainer.id = 'historyList';
							resultBox.appendChild(listContainer);

							const loader = document.createElement('div');
							loader.id = 'historyLoader';
							loader.style.display = 'none';
							loader.textContent = 'Загрузка...';
							resultBox.appendChild(loader);

							const loadMoreBtn = document.createElement('button');
							loadMoreBtn.id = 'historyLoadMoreBtn';
							loadMoreBtn.className = 'btn btn-outline-primary history-pagination-btn';
							loadMoreBtn.style.display = 'none';
							loadMoreBtn.textContent = t('load_more');
							resultBox.appendChild(loadMoreBtn);

							const end = document.createElement('div');
							end.id = 'historyEnd';
							end.style.display = 'none';
							end.textContent = t('end_of_list');
							resultBox.appendChild(end);

							const sentinel = document.createElement('div');
							sentinel.id = 'historySentinel';
							resultBox.appendChild(sentinel);

							historyState.listContainer = listContainer;
							historyState.sentinel = sentinel;

							// Load first page
							historyFetchNext().then(() => {
								historyAttachObserver();
							});
						} else {
							// Other API calls: regular rendering
						const method = String(tab.submit.method || 'GET').toUpperCase();

						let resp;
						if (method === 'POST') {
							resp = await apiPost(endpoint, payload);
						} else {
							const query = buildQuery(payload);
							const url = query ? (endpoint + '?' + query) : endpoint;
							resp = await apiGet(url);
						}

							resultBox.innerHTML = '';
							renderApiResult(resp, resultBox, endpoint, payload);
						}
						return;
					}

					throw new Error('Unknown submit type');
				} catch (err) {
					if (resultBox) {
						resultBox.innerHTML = `<div class="alert alert-danger">${escapeHtml(err.message || 'Error')}</div>`;
					} else {
						showAlert('danger', err.message || 'Error');
					}
				}
			};

			form.addEventListener('submit', handleSubmit);

			root.appendChild(form);
		}

		// History infinite scroll state
		const historyState = {
			page: 1,
			perPage: 20,
			total: 0,
			loading: false,
			done: false,
			seenIds: new Set(),
			endpoint: null,
			baseParams: null,
			listContainer: null,
			sentinel: null,
			observer: null,
		};

		function historyReset() {
			historyState.page = 1;
			historyState.total = 0;
			historyState.loading = false;
			historyState.done = false;
			historyState.seenIds.clear();
			if (historyState.observer && historyState.sentinel) {
				historyState.observer.unobserve(historyState.sentinel);
			}
		}

		function historyCreateCard(row) {
			const card = document.createElement('div');
			card.className = 'history-card';

			const line1 = document.createElement('div');
			line1.className = 'history-card-line';
			const orderText = t('order', { id: row.order_id || '-' });
			const dateText = row.issued_at ? new Date(row.issued_at).toLocaleString('ru-RU') : '';
			line1.innerHTML = '<strong>' + escapeHtml(orderText) + '</strong>' + (dateText ? ' <span style="color: var(--ah-hint); font-size: 11px;">' + escapeHtml(dateText) + '</span>' : '');
			card.appendChild(line1);

			const line2 = document.createElement('div');
			line2.className = 'history-card-line';
			line2.textContent = (row.game || '-') + ' (' + (row.platform || '-') + ')';
			card.appendChild(line2);

			const line3 = document.createElement('div');
			line3.className = 'history-card-line';
			line3.textContent = 'Account ID: ' + (row.account_id || '-');
			card.appendChild(line3);

			return card;
		}

		async function historyFetchNext() {
			if (historyState.loading || historyState.done || !historyState.endpoint || !historyState.listContainer) {
				return;
			}

			historyState.loading = true;

			// Show loader
			const loader = document.getElementById('historyLoader');
			if (loader) loader.style.display = 'block';

			try {
				const params = {
					...(historyState.baseParams || {}),
					page: historyState.page,
					per_page: historyState.perPage,
				};
				const query = buildQuery(params);
				const url = query ? (historyState.endpoint + '?' + query) : historyState.endpoint;

				const resp = await apiGet(url);
				const data = resp.data || {};
				const items = Array.isArray(data.items) ? data.items : [];

				if (items.length === 0) {
					historyState.done = true;
					const end = document.getElementById('historyEnd');
					if (end) end.style.display = 'block';
				} else {
					historyState.total = data.total || 0;

					for (const row of items) {
						// Create unique ID for deduplication
						const uniqueId = row.id || `${row.order_id}_${row.account_id}_${row.issued_at || ''}`;
						if (historyState.seenIds.has(uniqueId)) {
							continue;
						}
						historyState.seenIds.add(uniqueId);

						const card = historyCreateCard(row);
						historyState.listContainer.appendChild(card);
					}

					historyState.page++;
					const loadedCount = historyState.seenIds.size;
					if (loadedCount >= historyState.total) {
						historyState.done = true;
						const end = document.getElementById('historyEnd');
						if (end) end.style.display = 'block';
					}
				}
			} catch (err) {
				// Show fallback button
				const loadMoreBtn = document.getElementById('historyLoadMoreBtn');
				if (loadMoreBtn) {
					loadMoreBtn.style.display = 'block';
					loadMoreBtn.onclick = () => {
						loadMoreBtn.style.display = 'none';
						historyFetchNext();
					};
				}
				showAlert('danger', err.message || 'Ошибка загрузки');
			} finally {
				historyState.loading = false;
				if (loader) loader.style.display = 'none';
			}
		}

		function historyAttachObserver() {
			if (!historyState.sentinel || !window.IntersectionObserver) {
				const loadMoreBtn = document.getElementById('historyLoadMoreBtn');
				if (loadMoreBtn) {
					loadMoreBtn.style.display = 'block';
					loadMoreBtn.onclick = () => {
						historyFetchNext();
					};
				}
				return;
			}

			historyState.observer = new IntersectionObserver((entries) => {
				if (entries[0].isIntersecting && !historyState.loading && !historyState.done) {
					historyFetchNext();
				}
			}, {
				rootMargin: '100px',
			});

			historyState.observer.observe(historyState.sentinel);
		}

		function renderApiResult(resp, root, endpoint, baseParams) {
			const data = resp.data || {};
			if (Array.isArray(data.items)) {
				// Check if it's history (has order_id, game, platform, account_id)
				const first = data.items[0] || {};
				const isHistory = first.hasOwnProperty('order_id') && first.hasOwnProperty('game') && first.hasOwnProperty('platform') && first.hasOwnProperty('account_id');

				if (isHistory && endpoint) {
					// History: infinite scroll setup
					historyReset();

					// Clear root and create history structure
					root.innerHTML = '';

					const listContainer = document.createElement('div');
					listContainer.id = 'historyList';
					root.appendChild(listContainer);

					const loader = document.createElement('div');
					loader.id = 'historyLoader';
					loader.style.display = 'none';
					loader.textContent = 'Загрузка...';
					root.appendChild(loader);

					const loadMoreBtn = document.createElement('button');
					loadMoreBtn.id = 'historyLoadMoreBtn';
					loadMoreBtn.className = 'btn btn-outline-primary history-pagination-btn';
					loadMoreBtn.style.display = 'none';
					loadMoreBtn.textContent = 'Загрузить ещё';
					root.appendChild(loadMoreBtn);

					const end = document.createElement('div');
					end.id = 'historyEnd';
					end.style.display = 'none';
					end.textContent = 'Конец списка';
					root.appendChild(end);

					const sentinel = document.createElement('div');
					sentinel.id = 'historySentinel';
					root.appendChild(sentinel);

					// Setup state
					historyState.endpoint = endpoint;
					historyState.baseParams = baseParams;
					historyState.listContainer = listContainer;
					historyState.sentinel = sentinel;
					historyState.total = data.total || 0;
					historyState.perPage = data.per_page || 20;

					// Load first page immediately
					historyFetchNext().then(() => {
						historyAttachObserver();
					});

					return;
				}

				// For other arrays, render as cards (universal card rendering)
				const cols = Object.keys(first);
				
				for (const row of data.items) {
					const card = document.createElement('div');
					card.className = 'history-card';

					for (const col of cols) {
						const value = row[col];
						if (value === null || value === undefined) continue;

						const line = document.createElement('div');
						line.className = 'history-card-line';
						
						// Format column name (snake_case to Title Case)
						const label = col.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
						
						// Format value based on type
						let displayValue = String(value);
						if (typeof value === 'object' && value !== null) {
							displayValue = JSON.stringify(value);
						} else if (col.includes('date') || col.includes('at') || col.includes('_at')) {
							// Try to format as date
							try {
								const date = new Date(value);
								if (!isNaN(date.getTime())) {
									displayValue = date.toLocaleString('ru-RU');
								}
							} catch (e) {
								// Keep original value
							}
						}
						
						line.innerHTML = '<strong>' + escapeHtml(label) + ':</strong> ' + escapeHtml(displayValue);
						card.appendChild(line);
					}

					root.appendChild(card);
				}

				// Pagination UI (if endpoint provided and pagination data available)
				if (endpoint && (data.page !== undefined || data.total !== undefined)) {
					const page = data.page || 1;
					const perPage = data.per_page || 20;
					const total = data.total || 0;
					const totalPages = Math.ceil(total / perPage);

					if (totalPages > 1) {
						const pagination = document.createElement('div');
						pagination.className = 'history-pagination';

						const info = document.createElement('div');
						info.className = 'history-pagination-info';
						info.textContent = `Стр. ${page} из ${totalPages}`;
						pagination.appendChild(info);

						const buttons = document.createElement('div');
						buttons.className = 'history-pagination-buttons';

						const prevBtn = document.createElement('button');
						prevBtn.className = 'history-pagination-btn';
						prevBtn.textContent = '←';
						prevBtn.disabled = page <= 1;
						prevBtn.addEventListener('click', async () => {
							if (page > 1) {
								const params = { ...(baseParams || {}), page: page - 1, per_page: perPage };
								const query = buildQuery(params);
								const url = query ? (endpoint + '?' + query) : endpoint;
								try {
									const resp = await apiGet(url);
									root.innerHTML = '';
									renderApiResult(resp, root, endpoint, params);
								} catch (err) {
									showAlert('danger', err.message || 'Error');
								}
							}
						});
						buttons.appendChild(prevBtn);

						const nextBtn = document.createElement('button');
						nextBtn.className = 'history-pagination-btn';
						nextBtn.textContent = '→';
						nextBtn.disabled = page >= totalPages;
						nextBtn.addEventListener('click', async () => {
							if (page < totalPages) {
								const params = { ...(baseParams || {}), page: page + 1, per_page: perPage };
								const query = buildQuery(params);
								const url = query ? (endpoint + '?' + query) : endpoint;
								try {
									const resp = await apiGet(url);
									root.innerHTML = '';
									renderApiResult(resp, root, endpoint, params);
								} catch (err) {
									showAlert('danger', err.message || 'Error');
								}
							}
						});
						buttons.appendChild(nextBtn);

						pagination.appendChild(buttons);
						root.appendChild(pagination);
					}
				}

				// Show "Всего" or "Конец списка" if all data loaded
				if (data.total !== undefined && data.total > 0) {
					const page = data.page || 1;
					const perPage = data.per_page || 20;
					const totalPages = Math.ceil(data.total / perPage);
					
					if (page >= totalPages) {
						const end = document.createElement('div');
						end.className = 'history-meta';
						end.style.textAlign = 'center';
						end.style.paddingTop = '12px';
						end.textContent = t('total', { count: data.total });
						root.appendChild(end);
					}
				} else if (data.items && data.items.length === 0) {
					const empty = document.createElement('div');
					empty.className = 'history-meta';
					empty.style.textAlign = 'center';
					empty.style.paddingTop = '12px';
					empty.textContent = 'Нет данных';
					root.appendChild(empty);
				}

				return;
			}

			const pre = document.createElement('pre');
			pre.className = 'codebox';
			pre.textContent = JSON.stringify(resp, null, 2);
			root.appendChild(pre);
		}

		async function downloadCsv(url, filename) {
			const resp = await fetch(url, {
				method: 'GET',
				headers: {
					'X-TG-INIT-DATA': initData,
					...(tgLang ? { 'X-Tg-Lang': tgLang } : {}),
				},
			});

			if (!resp.ok) {
				throw new Error('Export failed: ' + resp.status);
			}

			const blob = await resp.blob();
			const a = document.createElement('a');
			a.href = URL.createObjectURL(blob);
			a.download = filename;
			document.body.appendChild(a);
			a.click();
			URL.revokeObjectURL(a.href);
			a.remove();
		}

		function renderExportTab(root) {
			const wrap = document.createElement('div');
			wrap.className = 'd-flex flex-column gap-3';

			// Accounts export button
			const accountsSection = document.createElement('div');
			accountsSection.className = 'd-flex flex-column gap-2';
			const btn1 = document.createElement('button');
			btn1.type = 'button';
			btn1.className = 'btn btn-outline-primary';
			btn1.textContent = t('download_accounts');
			btn1.addEventListener('click', async () => {
				try {
					await downloadCsv('/api/webapp/api/admin/export/accounts.csv', 'accounts.csv');
					showAlert('success', t('downloaded', { file: 'accounts.csv' }));
				} catch (e) {
					showAlert('danger', e.message || 'export error');
				}
			});
			accountsSection.appendChild(btn1);

			// Issuance logs export with filters
			const logsSection = document.createElement('div');
			logsSection.className = 'd-flex flex-column gap-2';

			const logsTitle = document.createElement('div');
			logsTitle.className = 'small text-muted';
			logsTitle.textContent = t('download_logs');

			const logsForm = document.createElement('div');
			logsForm.className = 'd-flex flex-column gap-2';
			logsForm.style.marginBottom = '0.5rem';

			const fields = [
				{ name: 'date_from', label: 'Date from', type: 'date', placeholder: 'YYYY-MM-DD' },
				{ name: 'date_to', label: 'Date to', type: 'date', placeholder: 'YYYY-MM-DD' },
				{ name: 'operator_telegram_id', label: 'Operator ID', type: 'text', placeholder: 'Telegram ID' },
				{ name: 'game', label: 'Game', type: 'text', placeholder: 'Game name' },
				{ name: 'platform', label: 'Platform', type: 'text', placeholder: 'Platform name' },
				{ name: 'order_id', label: 'Order ID', type: 'text', placeholder: 'Order ID' },
			];

			fields.forEach(field => {
				const group = document.createElement('div');
				group.className = 'mb-2';

				const label = document.createElement('label');
				label.className = 'form-label small';
				label.textContent = field.label;
				label.setAttribute('for', 'export_' + field.name);

				const input = document.createElement('input');
				input.type = field.type;
				input.className = 'form-control form-control-sm';
				input.id = 'export_' + field.name;
				input.name = field.name;
				input.placeholder = field.placeholder || '';

				group.appendChild(label);
				group.appendChild(input);
				logsForm.appendChild(group);
			});

			const btn2 = document.createElement('button');
			btn2.type = 'button';
			btn2.className = 'btn btn-outline-primary';
			btn2.textContent = t('download_logs');
			btn2.addEventListener('click', async () => {
				try {
					const params = {};
					fields.forEach(field => {
						const input = document.getElementById('export_' + field.name);
						if (input && input.value.trim() !== '') {
							params[field.name] = input.value.trim();
						}
					});

					const query = buildQuery(params);
					const url = '/api/webapp/api/admin/export/issuance_logs.csv' + (query ? ('?' + query) : '');
					await downloadCsv(url, 'issuance_logs.csv');
					showAlert('success', t('downloaded', { file: 'issuance_logs.csv' }));
				} catch (e) {
					showAlert('danger', e.message || 'export error');
				}
			});

			logsSection.appendChild(logsTitle);
			logsSection.appendChild(logsForm);
			logsSection.appendChild(btn2);

			wrap.appendChild(accountsSection);
			wrap.appendChild(document.createElement('hr'));
			wrap.appendChild(logsSection);

			root.appendChild(wrap);
		}

		async function init() {
			try {
				const schemaResp = await apiGet('/api/webapp/api/schema');

				// Store translations
				translations = schemaResp.data.translations || {};

				// Update role and TG ID with translations
				const roleLine = t('role', { role: schemaResp.data.role || '-' });
				document.getElementById('roleLine').innerHTML = roleLine.replace(':role', '<span id="roleText">' + (schemaResp.data.role || '-') + '</span>');

				const user = tg.initDataUnsafe?.user;
				if (user?.id) {
					const tgIdLine = t('tg_id', { id: String(user.id).slice(-4) });
					document.getElementById('tgIdLine').innerHTML = tgIdLine.replace(':id', '<span id="tgIdText">' + String(user.id).slice(-4) + '</span>');
				}

				const tabs = schemaResp.data.tabs || [];

				const nav = document.getElementById('tabsNav');
				const content = document.getElementById('tabsContent');

				nav.innerHTML = '';
				content.innerHTML = '';

				tabs.forEach((tab, idx) => {
					nav.appendChild(createTabNavItem(tab, idx === 0));
					const pane = createTabPane(tab, idx === 0);
					content.appendChild(pane);

					const root = pane.querySelector('#body_' + tab.id);

					if (tab.id === 'admin_export') {
						renderExportTab(root);
						return;
					}

					if (tab.type === 'static') {
						renderStatic(tab, root);
						return;
					}

					renderForm(tab, root);

					// Setup history infinite scroll on first load if history tab is active
					if (tab.id === 'history' && tab.submit?.type === 'api' && idx === 0) {
						const endpoint = tab.submit.endpoint;
						const resultBox = root.querySelector('.col-12.mt-3');
						if (resultBox) {
							historyReset();
							historyState.endpoint = endpoint;
							historyState.baseParams = {};

							const listContainer = document.createElement('div');
							listContainer.id = 'historyList';
							resultBox.innerHTML = '';
							resultBox.appendChild(listContainer);

							const loader = document.createElement('div');
							loader.id = 'historyLoader';
							loader.style.display = 'none';
							loader.textContent = 'Загрузка...';
							resultBox.appendChild(loader);

							const loadMoreBtn = document.createElement('button');
							loadMoreBtn.id = 'historyLoadMoreBtn';
							loadMoreBtn.className = 'btn btn-outline-primary history-pagination-btn';
							loadMoreBtn.style.display = 'none';
							loadMoreBtn.textContent = t('load_more');
							resultBox.appendChild(loadMoreBtn);

							const end = document.createElement('div');
							end.id = 'historyEnd';
							end.style.display = 'none';
							end.textContent = t('end_of_list');
							resultBox.appendChild(end);

							const sentinel = document.createElement('div');
							sentinel.id = 'historySentinel';
							resultBox.appendChild(sentinel);

							historyState.listContainer = listContainer;
							historyState.sentinel = sentinel;

							// Load first page
							historyFetchNext().then(() => {
								historyAttachObserver();
							});
						}
					}
				});

				// Handle tab switching for footer-action and history loading
				const tabButtons = document.querySelectorAll('[data-bs-toggle="tab"]');
				tabButtons.forEach(btn => {
					btn.addEventListener('shown.bs.tab', (e) => {
						const targetId = e.target.getAttribute('data-bs-target');
						const pane = document.querySelector(targetId);
						if (pane) {
							const form = pane.querySelector('form');
							
							// Check if this is history tab - load data if needed
							if (pane.id === 'tab_history') {
								const listContainer = document.getElementById('historyList');
								if (listContainer && listContainer.children.length === 0 && !historyState.loading && !historyState.done) {
									historyFetchNext().then(() => {
										historyAttachObserver();
									});
								}
							}
							
							// Hide footer by default when switching tabs
							footerAction.classList.add('d-none');
							document.querySelector('.app-shell').style.paddingBottom = '10px';
							
							if (form && form.dataset.singleAction === 'true') {
								footerAction.classList.remove('d-none');
								footerAction.innerHTML = '';
								const footerBtn = document.createElement('button');
								footerBtn.type = 'button'; // Prevent form submit
								footerBtn.className = 'btn btn-primary';
								footerBtn.textContent = t('submit');
								footerBtn.addEventListener('click', (e) => {
									e.preventDefault();
									e.stopPropagation();
									// Find the handleSubmit function from form's event listeners
									form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
								});
								footerAction.appendChild(footerBtn);
								document.querySelector('.app-shell').style.paddingBottom = '80px';
							}
							updateCopyrightVisibility();
						}
					});
				});

				// Enable bootstrap tab behavior
				const bs = document.createElement('script');
				bs.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
				document.body.appendChild(bs);

				// Enable horizontal wheel scroll for tabs on desktop
				enableTabsWheelScroll();
			} catch (e) {
				showAlert('danger', e.message || 'Init error');
			}
		}

		init();
	})();
</script>
</body>
</html>


