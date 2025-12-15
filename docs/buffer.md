Да, вижу. Сейчас у тебя WebApp рисуется “как обычный сайт” (белый фон, табы Bootstrap), а на скринах из ТЗ — ощущение “внутренней тёмной панели” + “чёрные блоки” под ответы. Это решается **двумя вещами**:

1. **тема/цвета под Telegram (dark/light)**
2. **переразметка табов и формы под mobile + меньше “воздуха”**

Ниже — конкретный патч для `resources/views/webapp/index.blade.php` (без теории).

---

# 1) Сделаем внешний вид “как в Telegram” (тёмный + компактный)

## 1.1 Добавь CSS (заменить `<style>...</style>` целиком)

```html
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
```

---

# 2) Подхватим Telegram themeParams (чтобы тёмная тема реально стала тёмной)

В `<script>` в самом начале, после `tg.expand();` вставь:

```js
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
```

---

# 3) Уберём “контейнер” Bootstrap и лишние рамки — сделаем компактный layout

В body замени текущий контейнер:

### Было:

```html
<div class="container py-3">
...
</div>
```

### Стало:

```html
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
```

---

# 4) Каждый таб пусть рисуется как “панель” (как на скринах)

В JS функцию `createTabPane()` измени так, чтобы `body` был внутри `.card-panel`.

### Замени `createTabPane()` целиком:

```js
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
```

---

# 5) Почему “не как на скринах” ещё?

На скринах ключевой элемент — **“чёрный блок результата”** как у сообщений Telegram (` ``` `).
В WebApp мы уже сделали `pre.codebox` — он будет выглядеть похоже (особенно в dark theme).
Но **идеально** “как в чате” будет именно ответ бота в чат (что у тебя уже есть).

---

# Как это протестить (быстро)

1. Пересобери/обнови страницу `/webapp` (Ctrl+F5 на десктопе, либо заново открыть WebApp в Telegram).
2. Переключи Telegram на тёмную тему → WebApp должен стать тёмным.
3. Проверь:

   * табы стали “пилюлями”
   * форма компактная
   * панели без белых блоков и лишних рамок
4. Нажми “Выдача” → убедись, что по submit WebApp закрывается и бот отвечает в чат.

---

Если хочешь, я следующим шагом дам **ещё более “телеграмный” UI**:

* sticky нижняя кнопка “Отправить” как в Telegram,
* автоподстановка `qty=1`,
* поля в одну колонку без лишних отступов,
* “История” с красивыми карточками вместо таблицы (в мобильном это лучше).
