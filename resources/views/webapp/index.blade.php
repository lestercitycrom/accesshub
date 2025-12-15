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
			--tg-bg: #ffffff;
			--tg-text: #0f172a;
			--tg-hint: rgba(15, 23, 42, .55);
			--tg-card: rgba(0, 0, 0, .04);
			--tg-border: rgba(0, 0, 0, .10);
			--tg-accent: #1677ff;
		}

		/* Dark-mode friendly baseline */
		body {
			background: var(--tg-bg) !important;
			color: var(--tg-text) !important;
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

		.badge-soft {
			background: var(--tg-card);
			border: 1px solid var(--tg-border);
			color: var(--tg-text);
			padding: 6px 10px;
			border-radius: 999px;
			font-size: 12px;
		}

		.role-line {
			color: var(--tg-hint);
			font-size: 12px;
		}

		/* Tabs: make them look like Telegram pills */
		.nav-tabs {
			border-bottom: 0;
			gap: 6px;
			flex-wrap: wrap;
		}

		.nav-tabs .nav-link {
			border: 1px solid var(--tg-border);
			background: var(--tg-card);
			color: var(--tg-text);
			border-radius: 999px;
			padding: 6px 10px;
			font-size: 14px;
		}

		.nav-tabs .nav-link.active {
			background: var(--tg-accent);
			border-color: var(--tg-accent);
			color: #fff;
		}

		.tab-content {
			background: transparent !important;
			border: 0 !important;
			padding: 0 !important;
		}

		.card-panel {
			background: var(--tg-card);
			border: 1px solid var(--tg-border);
			border-radius: 14px;
			padding: 12px;
			margin-top: 10px;
		}

		.form-label {
			font-size: 12px;
			color: var(--tg-hint);
			margin-bottom: 4px;
		}

		.form-control {
			border-radius: 12px;
			border: 1px solid var(--tg-border);
			background: rgba(0,0,0,.02);
		}

		.btn {
			border-radius: 12px;
		}

		pre.codebox {
			white-space: pre-wrap;
			word-break: break-word;
			padding: 12px;
			border-radius: 12px;
			background: rgba(0,0,0,.08);
			border: 1px solid var(--tg-border);
			margin: 0;
			font-size: 13px;
			line-height: 1.35;
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

	<ul class="nav nav-tabs" id="tabsNav"></ul>
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

		// Apply Telegram theme params (dark/light)
		const tp = tg.themeParams || {};
		const root = document.documentElement;

		function setVar(name, val) {
			if (val) root.style.setProperty(name, val);
		}

		// Telegram gives hex colors in themeParams
		setVar('--tg-bg', tp.bg_color);
		setVar('--tg-text', tp.text_color);
		setVar('--tg-hint', tp.hint_color ? tp.hint_color + 'AA' : null);
		setVar('--tg-accent', tp.button_color);

		// Fallbacks for dark mode if Telegram doesn't provide enough
		if (tg.colorScheme === 'dark') {
			if (!tp.bg_color) setVar('--tg-bg', '#0b1220');
			if (!tp.text_color) setVar('--tg-text', '#e5e7eb');
			if (!tp.hint_color) setVar('--tg-hint', 'rgba(229,231,235,.65)');
			setVar('--tg-card', 'rgba(255,255,255,.06)');
			setVar('--tg-border', 'rgba(255,255,255,.12)');
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

			const title = document.createElement('div');
			title.className = 'mb-2 fw-semibold';
			title.textContent = tab.title;

			panel.appendChild(title);

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

				const label = document.createElement('label');
				label.className = 'form-label';
				label.textContent = f.label || f.name;

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

				if (f.required) input.required = true;
				if (f.pattern) input.pattern = f.pattern;
				if (f.min !== undefined) input.min = String(f.min);
				if (f.max !== undefined) input.max = String(f.max);
				if (f.default !== undefined) input.value = String(f.default);

				col.appendChild(label);
				col.appendChild(input);
				form.appendChild(col);
			}

			const actions = document.createElement('div');
			actions.className = 'col-12 mt-2 d-flex gap-2';

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
						renderApiResult(resp, resultBox);
						return;
					}

					throw new Error('Unknown submit type');
				} catch (err) {
					resultBox.innerHTML = `<div class="alert alert-danger">${escapeHtml(err.message || 'Error')}</div>`;
				}
			});

			root.appendChild(form);
		}

		function renderApiResult(resp, root) {
			const data = resp.data || {};
			if (Array.isArray(data.items)) {
				const table = document.createElement('table');
				table.className = 'table table-sm table-striped';

				const thead = document.createElement('thead');
				const trh = document.createElement('tr');

				const first = data.items[0] || {};
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
				meta.className = 'text-muted small';
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
