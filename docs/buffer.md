# AccessHub — UI ТЗ (v9) Final polish (BotFather-like)

Дата: **2025-12-15**  
Проект: **accesshub**  
Стек: **Laravel 12 + PHP 8.3 + MySQL + Bootstrap 5 + Telegram WebApp**  
Файл работ: `resources/views/webapp/index.blade.php` (backend API не меняем)

---

## 1) Цель

Довести WebApp до “идеального” BotFather-подобного вида:

- **Always Dark** UI (не зависит от темы Telegram).
- Цвета/геометрия максимально близкие к BotFather.
- **Меню вкладок не вылазит** за край (и визуально понятно, что оно скроллится).
- **Одна главная кнопка в футере**, если в форме одно действие (как у BotFather).
- “История” карточками + **рабочая пагинация Prev/Next**.
- Мелкие улучшения: meta-bar (Role слева, TG справа), центрированный tab-header (icon/title/subtitle).
- “Дожим” визуала: секции как в BotFather Settings (group cards + dividers + компактная типографика).
- Рекомендации по **умеренному рефакторингу** (без ломки).

---

## 2) Палитра и геометрия (фиксировано)

### 2.1 Цвета
- background: `#0F1722`
- header (Telegram WebApp header): `#28323A`
- panel: `#182232`
- section (внутренние группы): `#141C29`
- accent (кнопки BotFather-like): `#2481C9`
- text: `#E6EDF3`
- hint: `rgba(230,237,243,.60)`
- placeholder: `rgba(230,237,243,.45)`
- border: `rgba(255,255,255,.10)`
- input bg: `rgba(255,255,255,.06)`

### 2.2 Радиусы (уменьшаем)
- input/button: **8px**
- tabs: **10px**
- panels: **12px**

---

## 3) Telegram WebApp header (как у BotFather)

После `tg.ready()`:
```js
tg.setBackgroundColor('#0F1722');
tg.setHeaderColor('#28323A');
document.documentElement.style.setProperty('--ah-accent', '#2481C9');
```

---

## 4) Верхняя зона: убрать “много информации”

### 4.1 Убираем повторный заголовок
Если “AccessHub” уже есть в системном header — в контенте оставляем только **meta-bar**.

### 4.2 Meta-bar (Role слева, TG справа)
HTML:
```html
<div class="meta-bar">
  <div class="meta-left">Role: <span id="roleText">admin</span></div>
  <div class="meta-pill">TG: <span id="tgIdText">5826...</span></div>
</div>
```

---

## 5) Tab header как у BotFather (по центру)

### 5.1 Иконка/тайтл/описание по центру (только “шапка” вкладки)
- icon по центру
- title по центру
- subtitle по центру (если есть)
- ниже — панель/форма (может быть left-aligned)

Schema (опционально):
```json
"header": {
  "icon": "🎮",
  "title": "Выдача",
  "subtitle": "Получить аккаунт"
}
```

Если `header` нет — fallback на `tab.title`.

---

## 6) Меню вкладок: не вылазит и “как приложение”

Оставляем top-tabs (как сейчас), но делаем правильно:

- `.tabs-wrap` получает **padding справа**, чтобы последний чип не резался
- градиентные **fade edges** слева/справа
- `.nav-link` = `flex: 0 0 auto;`

HTML (обязательно):
```html
<div class="tabs-wrap">
  <ul class="nav nav-tabs" id="tabsNav"></ul>
</div>
```

---

## 7) Формы: BotFather-стиль ввода

### 7.1 Placeholder вместо label
- `.form-label` скрыть
- placeholder задавать из схемы

В `renderForm()`:
```js
input.placeholder = f.placeholder ?? f.label ?? f.name;
```

### 7.2 Focus без смены фона
- на focus фон остаётся `rgba(255,255,255,.06)`
- подсветка через border + box-shadow

---

## 8) Кнопка действия как у BotFather: футер + 100% ширина

Правило:
- если у формы **одно действие** (submit) — показываем **одну primary кнопку** в `footer-action` (fixed снизу), “Очистить” не показываем
- иначе — обычные кнопки в `.sticky-actions`

HTML (внизу body):
```html
<div id="footerAction" class="footer-action d-none"></div>
```

JS (логика):
- single-action → показываем футер, добавляем `body padding-bottom`
- multi-action → футер скрыть, padding-bottom убрать

---

## 9) История: карточки + рабочая пагинация

### 9.1 Карточки (как сейчас)
Оставляем карточки.

### 9.2 Prev/Next
Внизу вкладки “История” добавить:
- Prev (если page > 1)
- Next (если page * per_page < total)
- “Стр. X из Y”

При клике — повторный запрос с `?page=N`.

---

## 10) “Дожим” визуала как в BotFather Settings

### 10.1 Внутренние секции
Внутри `card-panel` сделать “группы”:
- `card-section` с фоном `#141C29`
- разделители `divider` между логическими блоками

Пример:
- header вкладки (центр)
- section: форма
- divider
- section: результат/подсказка

### 10.2 Типографика и расстояния
- заголовки: чуть меньше, плотнее
- лейблы скрыты (placeholder)
- больше воздуха между секциями, меньше внутри полей

---

## 11) CSS: единый блок (вставить/привести к эквиваленту)

```css
:root{
  --ah-bg:#0F1722;
  --ah-header:#28323A;
  --ah-panel:#182232;
  --ah-panel-2:#141C29;
  --ah-border:rgba(255,255,255,.10);
  --ah-input:rgba(255,255,255,.06);
  --ah-text:#E6EDF3;
  --ah-hint:rgba(230,237,243,.60);
  --ah-placeholder:rgba(230,237,243,.45);
  --ah-accent:#2481C9;
  --ah-accent-weak:rgba(36,129,201,.20);
  --ah-code:rgba(0,0,0,.55);
}

html,body{ background:var(--ah-bg)!important; color:var(--ah-text)!important; }

/* meta-bar */
.meta-bar{
  display:flex; justify-content:space-between; align-items:center;
  gap:12px; color:var(--ah-hint); font-size:12px; margin:8px 0 10px;
}
.meta-pill{
  padding:6px 10px; border:1px solid var(--ah-border);
  background:rgba(255,255,255,.03);
  border-radius:10px; color:var(--ah-hint);
}

/* smaller radii */
.form-control,.btn{ border-radius:8px!important; }
.nav-tabs .nav-link{ border-radius:10px!important; }
.card-panel{ border-radius:12px!important; }

/* panels + sections */
.card-panel{
  background:var(--ah-panel);
  border:1px solid var(--ah-border);
  padding:12px;
  margin-top:10px;
}
.card-section{
  background:var(--ah-panel-2);
  border:1px solid var(--ah-border);
  border-radius:10px;
  padding:12px;
}
.divider{
  height:1px;
  background:var(--ah-border);
  margin:12px 0;
  border:0;
}

/* centered tab header */
.tab-header{
  text-align:center;
  margin-bottom:10px;
}
.tab-header .tab-icon{
  font-size:20px;
  line-height:1;
  margin-bottom:6px;
}
.tab-header .tab-title{
  font-size:18px;
  font-weight:700;
  margin:0;
}
.tab-header .tab-subtitle{
  color:var(--ah-hint);
  font-size:12px;
  margin-top:4px;
}

/* placeholder-only forms */
.form-label{ display:none!important; }
.form-control{
  background:var(--ah-input)!important;
  color:var(--ah-text)!important;
  border:1px solid var(--ah-border)!important;
  box-shadow:none!important;
}
.form-control::placeholder{ color:var(--ah-placeholder)!important; }
.form-control:focus{
  background:var(--ah-input)!important;
  border-color:rgba(36,129,201,.55)!important;
  box-shadow:0 0 0 2px var(--ah-accent-weak)!important;
  outline:none!important;
}
.form-control:focus::placeholder{ color:rgba(230,237,243,.30)!important; }

/* tabs wrap: prevent overflow + fade edges */
.tabs-wrap{
  position:relative;
  overflow-x:auto;
  -webkit-overflow-scrolling:touch;
  padding:0 18px 6px 8px;
  scrollbar-width:none;
}
.tabs-wrap::-webkit-scrollbar{ display:none; }

.tabs-wrap::before,
.tabs-wrap::after{
  content:'';
  position:sticky;
  top:0;
  width:18px;
  height:44px;
  display:block;
  pointer-events:none;
}
.tabs-wrap::before{
  left:0; float:left;
  background:linear-gradient(to right, var(--ah-bg), rgba(0,0,0,0));
}
.tabs-wrap::after{
  right:0; float:right;
  background:linear-gradient(to left, var(--ah-bg), rgba(0,0,0,0));
}

.nav-tabs{ border:0; flex-wrap:nowrap; gap:8px; }
.nav-tabs .nav-link{
  flex:0 0 auto;
  border:1px solid var(--ah-border);
  background:rgba(255,255,255,.04);
  color:var(--ah-text);
  padding:6px 10px;
  white-space:nowrap;
}
.nav-tabs .nav-link.active{
  background:var(--ah-accent);
  border-color:var(--ah-accent);
  color:#fff;
}

/* footer action (BotFather-style) */
.footer-action{
  position:fixed;
  left:0; right:0; bottom:0;
  padding:12px 12px calc(12px + env(safe-area-inset-bottom));
  background:var(--ah-bg);
  border-top:1px solid var(--ah-border);
}
.footer-action .btn{
  width:100%;
  padding:12px 14px;
  font-weight:600;
}

/* codebox */
pre.codebox{
  white-space:pre-wrap;
  word-break:break-word;
  padding:12px;
  border-radius:10px;
  background:var(--ah-code);
  border:1px solid var(--ah-border);
  margin:0;
  font-size:13px;
  line-height:1.35;
  color:var(--ah-text);
}
```

---

## 12) Умеренный рефакторинг (чтобы код был понятным)

В рамках **одного** `index.blade.php`:

- **tgInit()**: `tg.ready()`, header/background, initData заголовки
- **apiClient**: `apiGet/apiPost/downloadCsv`
- **schemaRenderer**: `renderTabs/renderTabHeader/createPane`
- **formRenderer**: `renderForm/serialize/validateMinimal`
- **historyRenderer**: `renderHistoryCards/renderPagination`
- **utils**: `escapeHtml/buildQuery/clamp`

Правило: не менять архитектуру, только разнести функции по блокам и убрать “магические строки” в константы.

---

## 13) Критерии приёмки + тест

### Acceptance
1. WebApp всегда тёмный.
2. Header цвет близкий к BotFather (`#28323A`) на поддерживаемых клиентах.
3. Meta-bar: Role слева, TG справа, одинаковый размер.
4. Tab header по центру (icon/title/subtitle).
5. Меню вкладок не режется и не вылезает.
6. Формы: placeholder вместо label, focus не “белеет”.
7. Single-action формы: одна большая кнопка в футере, без “Очистить”.
8. История: карточки + Prev/Next работает.

### Регресс-тест
- Открыть WebApp в светлой теме Telegram → UI тёмный.
- Проверить скролл табов и отсутствие обрезания справа.
- Выдача → отправить → WebApp закрывается → бот отвечает.
- История → Next/Prev перелистывают страницы.
- Экспорт → CSV скачивается.
