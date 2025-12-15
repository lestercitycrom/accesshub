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
			--ah-panel: #182232;
			--ah-panel-2: #141C29;
			--ah-border: rgba(255, 255, 255, .10);
			--ah-input: rgba(255, 255, 255, .06);
			--ah-text: #E6EDF3;
			--ah-hint: rgba(230, 237, 243, .60);
			--ah-placeholder: rgba(230, 237, 243, .45);
			--ah-accent: #2F81F7;
			--ah-accent-weak: rgba(47, 129, 247, .20);
			--ah-code: rgba(0, 0, 0, .55);
		}

		html, body {
			background: var(--ah-bg) !important;
			color: var(--ah-text) !important;
		}

		.app-shell {
			padding: 10px;
		}

		.header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: 10px;
			margin-bottom: 10px;
		}

		.role-line {
			color: var(--ah-hint);
			font-size: 12px;
		}

		.badge-soft {
			background: var(--ah-panel);
			border: 1px solid var(--ah-border);
			color: var(--ah-text);
			padding: 6px 10px;
			border-radius: 999px;
			font-size: 12px;
		}

		.tabs-wrap {
			display: flex;
			overflow-x: auto;
			gap: 8px;
			padding-bottom: 6px;
			-webkit-overflow-scrolling: touch;
			scrollbar-width: none;
			position: relative;
		}

		.tabs-wrap::before,
		.tabs-wrap::after {
			content: '';
			position: sticky;
			width: 20px;
			height: 100%;
			z-index: 1;
			pointer-events: none;
		}

		.tabs-wrap::before {
			left: 0;
			background: linear-gradient(to right, var(--ah-bg), transparent);
		}

		.tabs-wrap::after {
			right: 0;
			background: linear-gradient(to left, var(--ah-bg), transparent);
		}

		.tabs-wrap::-webkit-scrollbar {
			display: none;
		}

		.nav-tabs {
			border: 0;
			flex-wrap: nowrap;
			gap: 8px;
		}

		.nav-tabs .nav-link {
			border: 1px solid var(--ah-border);
			background: rgba(255, 255, 255, .04);
			color: var(--ah-text);
			border-radius: 12px;
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
			border-radius: 16px;
			padding: 12px;
			margin-top: 10px;
		}

		.form-label {
			display: none;
		}

		.form-control {
			border-radius: 10px;
			border: 1px solid var(--ah-border);
			background: var(--ah-input);
			color: var(--ah-text);
			transition: border-color 0.2s, box-shadow 0.2s;
		}

		.form-control:focus {
			background: var(--ah-input);
			border-color: var(--ah-accent);
			box-shadow: 0 0 0 3px var(--ah-accent-weak);
			color: var(--ah-text);
			outline: none;
		}

		.form-control::placeholder {
			color: var(--ah-placeholder);
		}

		.btn {
			border-radius: 10px;
		}

		.card-section {
			background: var(--ah-panel-2);
			border: 1px solid var(--ah-border);
			border-radius: 12px;
			padding: 10px;
			margin-bottom: 8px;
		}

		.tab-header {
			display: flex;
			align-items: flex-start;
			margin-bottom: 16px;
		}

		.tab-header-icon {
			font-size: 24px;
			margin-right: 8px;
			line-height: 1;
		}

		.tab-header-title {
			font-size: 18px;
			font-weight: 600;
			margin-bottom: 4px;
		}

		.tab-header-subtitle {
			color: var(--ah-hint);
			font-size: 13px;
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

		.sticky-actions {
			position: sticky;
			bottom: 10px;
			padding-top: 10px;
			background: linear-gradient(to top, var(--ah-panel) 70%, rgba(0, 0, 0, 0));
		}

		pre.codebox {
			white-space: pre-wrap;
			word-break: break-word;
			padding: 12px;
			border-radius: 12px;
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

		.history-card-line {
			margin-bottom: 4px;
			font-size: 13px;
		}

		.history-card-line:last-child {
			margin-bottom: 0;
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
	</style>
</head>
<body>
<div class="app-shell">
	<div class="header">
		<div>
			<div class="fw-semibold">AccessHub</div>
			<div class="role-line" id="roleLine">Role: ...</div>
		</div>
		<div class="badge-soft" id="userBadge">TG: -</div>
	</div>

	<div id="alerts"></div>

	<div class="tabs-wrap">
		<ul class="nav nav-tabs" id="tabsNav"></ul>
	</div>
	<div class="tab-content" id="tabsContent"></div>
</div>

<script>
	(function () {
		const tg = window.Telegram?.WebApp;
		if (!tg) {
			alert('Telegram WebApp API not found');
			return;
		}

		tg.ready();
		tg.expand();

		// Set Telegram WebApp header colors
		tg.setBackgroundColor('#0F1722');
		tg.setHeaderColor('#0F1722');

		// Optional: use accent color from Telegram themeParams
		const tp = tg.themeParams || {};
		if (tp.button_color) {
			document.documentElement.style.setProperty('--ah-accent', tp.button_color);
		}

		const initData = tg.initData || '';
		const user = tg.initDataUnsafe?.user;

		if (user?.id) {
			document.getElementById('userBadge').textContent = 'TG: ' + user.id;
		}

		const alerts = document.getElementById('alerts');

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
					'Accept': 'application/json',
				},
			});

			const data = await resp.json().catch(() => null);

			if (!resp.ok || !data) {
				const message = data?.error?.message || 'API request failed';
				if (resp.status === 403 || message === 'no access') {
					throw new Error('Нет доступа. Обратитесь к администратору для добавления в систему.');
				}
				if (resp.status === 401) {
					throw new Error('Ошибка авторизации. Перезагрузите WebApp.');
				}
				throw new Error(message);
			}

			if (data.ok !== true) {
				const message = data?.error?.message || 'API error';
				if (data?.error?.code === 'FORBIDDEN' || message === 'no access') {
					throw new Error('Нет доступа. Обратитесь к администратору для добавления в систему.');
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
					'Accept': 'application/json',
					'Content-Type': 'application/json',
				},
				body: JSON.stringify(payload || {}),
			});

			const data = await resp.json().catch(() => null);

			if (!resp.ok || !data) {
				const message = data?.error?.message || 'API request failed';
				if (resp.status === 403 || message === 'no access') {
					throw new Error('Нет доступа. Обратитесь к администратору.');
				}
				if (resp.status === 401) {
					throw new Error('Ошибка авторизации. Перезагрузите WebApp.');
				}
				throw new Error(message);
			}

			if (data.ok !== true) {
				const message = data?.error?.message || 'API error';
				if (data?.error?.code === 'FORBIDDEN' || message === 'no access') {
					throw new Error('Нет доступа. Обратитесь к администратору.');
				}
				const fields = data?.error?.fields ? JSON.stringify(data.error.fields) : '';
				throw new Error(message + (fields ? (' ' + fields) : ''));
			}

			return data;
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

			// Tab header with icon (if available)
			if (tab.header) {
				const header = document.createElement('div');
				header.className = 'tab-header';

				if (tab.header.icon) {
					const icon = document.createElement('span');
					icon.className = 'tab-header-icon';
					icon.textContent = tab.header.icon;
					header.appendChild(icon);
				}

				const titleWrap = document.createElement('div');
				const title = document.createElement('div');
				title.className = 'tab-header-title';
				title.textContent = tab.header.title || tab.title;
				titleWrap.appendChild(title);

				if (tab.header.subtitle) {
					const subtitle = document.createElement('div');
					subtitle.className = 'tab-header-subtitle';
					subtitle.textContent = tab.header.subtitle;
					titleWrap.appendChild(subtitle);
				}

				header.appendChild(titleWrap);
				panel.appendChild(header);
			} else {
				const title = document.createElement('div');
				title.className = 'mb-2 fw-semibold';
				title.textContent = tab.title;
				panel.appendChild(title);
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
					input.type = (f.type === 'number') ? 'number' : 'text';
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

			const actions = document.createElement('div');
			actions.className = 'col-12 mt-2 d-flex gap-2 sticky-actions';

			const submitBtn = document.createElement('button');
			submitBtn.type = 'submit';
			submitBtn.className = 'btn btn-primary';
			submitBtn.textContent = 'Отправить';

			const resetBtn = document.createElement('button');
			resetBtn.type = 'button';
			resetBtn.className = 'btn btn-outline-secondary';
			resetBtn.textContent = 'Очистить';
			resetBtn.addEventListener('click', () => form.reset());

			actions.appendChild(submitBtn);
			actions.appendChild(resetBtn);

			form.appendChild(actions);

			const resultBox = document.createElement('div');
			resultBox.className = 'col-12 mt-3';
			resultBox.innerHTML = '<div class="text-muted small">Результат появится здесь.</div>';

			form.appendChild(resultBox);

			form.addEventListener('submit', async (e) => {
				e.preventDefault();

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
						const query = buildQuery(payload);
						const url = query ? (endpoint + '?' + query) : endpoint;

						const resp = await apiGet(url);

						resultBox.innerHTML = '';
						renderApiResult(resp, resultBox, endpoint, payload);
						return;
					}

					throw new Error('Unknown submit type');
				} catch (err) {
					resultBox.innerHTML = `<div class="alert alert-danger">${escapeHtml(err.message || 'Error')}</div>`;
				}
			});

			root.appendChild(form);
		}

		function renderApiResult(resp, root, endpoint, baseParams) {
			const data = resp.data || {};
			if (Array.isArray(data.items)) {
				// Check if it's history (has order_id, game, platform, account_id)
				const first = data.items[0] || {};
				const isHistory = first.hasOwnProperty('order_id') && first.hasOwnProperty('game') && first.hasOwnProperty('platform') && first.hasOwnProperty('account_id');

				if (isHistory && endpoint) {
					// Render as cards for history
					for (const row of data.items) {
						const card = document.createElement('div');
						card.className = 'history-card';

						const line1 = document.createElement('div');
						line1.className = 'history-card-line';
						const orderText = 'Order: ' + (row.order_id || '-');
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

						root.appendChild(card);
					}

					// Pagination UI
					const page = data.page || 1;
					const perPage = data.per_page || 20;
					const total = data.total || 0;
					const totalPages = Math.ceil(total / perPage);

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

					return;
				}

				// For other arrays, render as table
				const table = document.createElement('table');
				table.className = 'table table-sm table-striped';

				const thead = document.createElement('thead');
				const trh = document.createElement('tr');

				const cols = Object.keys(first);

				for (const c of cols) {
					const th = document.createElement('th');
					th.textContent = c;
					trh.appendChild(th);
				}

				thead.appendChild(trh);
				table.appendChild(thead);

				const tbody = document.createElement('tbody');
				for (const row of data.items) {
					const tr = document.createElement('tr');
					for (const c of cols) {
						const td = document.createElement('td');
						td.textContent = (row[c] === null || row[c] === undefined) ? '' : String(row[c]);
						tr.appendChild(td);
					}
					tbody.appendChild(tr);
				}
				table.appendChild(tbody);

				root.appendChild(table);

				const meta = document.createElement('div');
				meta.className = 'history-meta';
				meta.textContent = `page=${data.page ?? '-'} per_page=${data.per_page ?? '-'} total=${data.total ?? '-'}`;
				root.appendChild(meta);

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
			wrap.className = 'd-flex flex-column gap-2';

			const btn1 = document.createElement('button');
			btn1.type = 'button';
			btn1.className = 'btn btn-outline-primary';
			btn1.textContent = 'Скачать accounts.csv';
			btn1.addEventListener('click', async () => {
				try {
					await downloadCsv('/api/webapp/api/admin/export/accounts.csv', 'accounts.csv');
					showAlert('success', 'accounts.csv скачан');
				} catch (e) {
					showAlert('danger', e.message || 'export error');
				}
			});

			const btn2 = document.createElement('button');
			btn2.type = 'button';
			btn2.className = 'btn btn-outline-primary';
			btn2.textContent = 'Скачать issuance_logs.csv';
			btn2.addEventListener('click', async () => {
				try {
					await downloadCsv('/api/webapp/api/admin/export/issuance_logs.csv', 'issuance_logs.csv');
					showAlert('success', 'issuance_logs.csv скачан');
				} catch (e) {
					showAlert('danger', e.message || 'export error');
				}
			});

			wrap.appendChild(btn1);
			wrap.appendChild(btn2);

			root.appendChild(wrap);
		}

		async function init() {
			try {
				const schemaResp = await apiGet('/api/webapp/api/schema');

				document.getElementById('roleLine').textContent = 'Role: ' + (schemaResp.data.role || '-');

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
				});

				// Enable bootstrap tab behavior
				const bs = document.createElement('script');
				bs.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
				document.body.appendChild(bs);
			} catch (e) {
				showAlert('danger', e.message || 'Init error');
			}
		}

		init();
	})();
</script>
</body>
</html>
