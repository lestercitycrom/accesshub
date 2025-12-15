# AccessHub — UI ТЗ (v10) Pixel-match BotFather

Дата: **2025-12-15**  
Файл работ: `resources/views/webapp/index.blade.php`  
Backend API **не менять**.

---

## 0) Цель

Свести UI максимально близко к BotFather по:
- пропорциям (padding/margins/типографика),
- поведению (footer primary button),
- визуалу (иконка в круге, секции без лишних панелей),
- и **обязательно** решить проблему: **меню вкладок не должно “вылазить” за край**.

---

## 1) Исправления по пунктам (обязательные)

### 1.1 Меню вкладок всё ещё вылазит (критично)
Причина обычно в том, что контейнер табов не ограничивает ширину/overflow, либо есть элементы с `min-width`/`float`.

**Сделать так:**
1) Обёртка табов должна быть единственным горизонтальным скроллом:
```html
<div class="tabs-rail">
  <div class="tabs-wrap">
    <ul class="nav nav-tabs" id="tabsNav"></ul>
  </div>
</div>
```

2) На `.tabs-rail` включить `overflow:hidden` (чтобы ничего не выходило за пределы окна), а скролл оставить только на `.tabs-wrap`.

3) У табов **запретить** растягивание и любые переносы:
- `.nav-tabs { flex-wrap: nowrap; }`
- `.nav-link { flex: 0 0 auto; white-space: nowrap; }`

> Важно: убрать `float` и `position: sticky` псевдоэлементы из `.tabs-wrap::before/after`, если они ломают layout. Вместо них использовать отдельные overlay-градиенты через `.tabs-rail`.

---

### 1.2 TG ID без обводки (как Role)
Сделать одну строку meta без “pill”:
- слева: `Role: admin`
- справа: `TG: 5762`
Одинаковый шрифт/цвет.

---

### 1.3 Форма не должна быть “в панели внутри панели”
Правило:
- На вкладке **должна быть только одна основная панель** (`.card-panel`), внутри неё:
  - `tab-header` (icon/title/subtitle)
  - форма (без дополнительного `.card-section` вокруг полей)

`card-section` использовать только для “настроечных списков” (как в BotFather settings), но **не** для базовых форм выдачи/истории.

---

### 1.4 Footer: если есть кнопка — копирайта нет
Если отображается `#footerAction` (primary button), то `@accesshub_123_bot` **не показывать**.

Если footerAction скрыт — копирайт можно показывать.

---

### 1.5 “Очистить” показывать только когда реально нужно
По умолчанию **не показывать** “Очистить”.

Показывать только если:
- в schema у формы есть `allow_clear: true`, или
- actions > 1 и явно указан action `type: "reset"`.

---

### 1.6 Иконка как у BotFather: круглая обводка + отступы + типографика
Сделать:
- круг 72px (можно 64–72), border 1px, лёгкая заливка
- иконка по центру
- больше отступ сверху
- subtitle меньшим шрифтом и светлее

---

### 1.7 Primary button как у BotFather: ниже высота, меньше радиус, меньше шрифт
Для footer primary:
- height ~ 44–48px
- font-size 14px
- radius 8px
- padding меньше

---

## 2) CSS (вставить/привести к эквиваленту)

```css
:root{
  --ah-bg:#0F1722;
  --ah-header:#28323A;
  --ah-panel:#182232;
  --ah-panel-2:#141C29;
  --ah-accent:#2481C9;
}

/* --- Meta (Role | TG) --- */
.meta-bar {
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
  color:var(--ah-hint);
  font-size:12px;
  margin:8px 0 10px;
}
.meta-bar .meta-right {
  color:var(--ah-hint);
  font-size:12px;
}

/* --- Tabs: never overflow outside window --- */
.tabs-rail {
  position:relative;
  overflow:hidden;              /* IMPORTANT */
  padding:0 10px 6px;
}

.tabs-wrap {
  overflow-x:auto;              /* only scroll here */
  -webkit-overflow-scrolling:touch;
  scrollbar-width:none;
}
.tabs-wrap::-webkit-scrollbar{ display:none; }

.nav-tabs {
  border:0;
  flex-wrap:nowrap;
  gap:8px;
}
.nav-tabs .nav-link {
  flex:0 0 auto;
  white-space:nowrap;
  border:1px solid var(--ah-border);
  background:rgba(255,255,255,.04);
  color:var(--ah-text);
  padding:6px 10px;
  border-radius:10px;
}
.nav-tabs .nav-link.active {
  background:var(--ah-accent);
  border-color:var(--ah-accent);
  color:#fff;
}

/* fade edges as overlays (do not affect layout) */
.tabs-rail::before,
.tabs-rail::after {
  content:'';
  position:absolute;
  top:0;
  width:18px;
  height:44px;
  pointer-events:none;
  z-index:5;
}
.tabs-rail::before {
  left:0;
  background:linear-gradient(to right, var(--ah-bg), rgba(0,0,0,0));
}
.tabs-rail::after {
  right:0;
  background:linear-gradient(to left, var(--ah-bg), rgba(0,0,0,0));
}

/* --- Panel structure --- */
.card-panel {
  background:var(--ah-panel);
  border:1px solid var(--ah-border);
  border-radius:12px;
  padding:12px;
  margin-top:10px;
}

/* --- Tab header like BotFather --- */
.tab-header {
  text-align:center;
  padding-top:12px;
  padding-bottom:10px;
}
.tab-icon-wrap {
  width:72px;
  height:72px;
  border-radius:999px;
  margin:0 auto 12px;
  border:1px solid var(--ah-border);
  background:rgba(255,255,255,.03);
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:26px;
}
.tab-title {
  font-size:20px;
  font-weight:700;
  margin:0;
}
.tab-subtitle {
  margin-top:6px;
  font-size:12px;
  color:rgba(230,237,243,.55);
}

/* --- Inputs (placeholder only) --- */
.form-label{ display:none!important; }
.form-control {
  border-radius:8px!important;
  background:var(--ah-input)!important;
  color:var(--ah-text)!important;
  border:1px solid var(--ah-border)!important;
  box-shadow:none!important;
}
.form-control::placeholder{ color:var(--ah-placeholder)!important; }
.form-control:focus {
  background:var(--ah-input)!important;
  border-color:rgba(36,129,201,.55)!important;
  box-shadow:0 0 0 2px rgba(36,129,201,.20)!important;
  outline:none!important;
}
.form-control:focus::placeholder{ color:rgba(230,237,243,.30)!important; }

/* --- Footer primary button (BotFather-like) --- */
.footer-action {
  position:fixed;
  left:0; right:0; bottom:0;
  padding:10px 10px calc(10px + env(safe-area-inset-bottom));
  background:var(--ah-bg);
  border-top:1px solid var(--ah-border);
}
.footer-action .btn {
  width:100%;
  border-radius:8px!important;
  padding:10px 12px;
  font-size:14px;
  font-weight:600;
}
```

---

## 3) HTML (обязательная структура)

Верх страницы:
```html
<div class="meta-bar">
  <div class="meta-left">Role: <span id="roleText">admin</span></div>
  <div class="meta-right">TG: <span id="tgIdText">5762</span></div>
</div>

<div class="tabs-rail">
  <div class="tabs-wrap">
    <ul class="nav nav-tabs" id="tabsNav"></ul>
  </div>
</div>
```

Низ страницы:
```html
<div id="footerAction" class="footer-action d-none"></div>
<div id="copyright" class="text-center small text-muted my-3">@accesshub_123_bot</div>
```

---

## 4) JS логика (точечно)

### 4.1 Footer vs copyright
- Если показываем footerAction → `#copyright` скрыть.
- Если footerAction скрыт → `#copyright` показать.

### 4.2 Clear button
В `renderForm()`:
- по умолчанию не рисовать “Очистить”
- рисовать только если `form.allow_clear === true` или есть `reset` action

### 4.3 Tab header icon circle
В `renderTabHeader()`:
```html
<div class="tab-header">
  <div class="tab-icon-wrap">🎮</div>
  <h2 class="tab-title">Выдача</h2>
  <div class="tab-subtitle">Получить аккаунт</div>
</div>
```

### 4.4 Убрать “panel-in-panel”
Убедиться, что вокруг полей формы нет дополнительного `.card-section`.
Оставить только `.card-panel`.

---

## 5) Acceptance + тест

### Acceptance
1) Вкладки **никогда** не выходят за край окна (только скроллятся внутри).
2) TG ID без обводки, стиль как у Role.
3) Нет “панели в панели” у форм выдачи/истории.
4) Если есть footer-кнопка — копирайта нет.
5) “Очистить” только там, где разрешено схемой.
6) Иконка в круге + центрированный header, subtitle маленький.
7) Footer primary button: меньше высота/шрифт/радиус.

### Регресс-тест
- Открыть WebApp → вкладки пролистать → ничего не обрезается и не “вылазит”.
- Выдача → отправить → footer button виден, копирайта нет.
- История → “Очистить” есть только при allow_clear/reset.
