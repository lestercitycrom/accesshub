# AccessHub — ТЗ на доработку интерфейса WebApp (v7, BotFather-style + Refactor)

Дата: **2025-12-15**  
Стек: **Laravel 12 + PHP 8.3 + MySQL + Bootstrap 5 + Telegram WebApp**

## 1. Цель

Сделать WebApp визуально максимально близким к BotFather (тёмная модалка, аккуратные отступы, “карточные” секции, placeholder вместо label) и довести UX:

- **Always Dark** (не зависит от темы Telegram)
- верхнее меню вкладок без вылезаний/переносов (гор. скролл или альтернативный современный вариант)
- “История” карточками + полноценная пагинация UI
- формы: placeholder, корректный focus, одинаковая геометрия
- “шапка” WebApp: попытаться приблизить к BotFather через `setHeaderColor/setBackgroundColor`
- рекомендации по **рефакторингу** (без фанатизма) чтобы код был понятен и переиспользуем

## 2. Область работ

Основной файл: `resources/views/webapp/index.blade.php`  
Backend API не меняем.

## 3. Дизайн (BotFather-style)

### 3.1 Always Dark palette (фиксированная)
Использовать как базу:

- `--ah-bg: #0F1722`
- `--ah-panel: #182232`
- `--ah-panel-2: #141C29` (второй тон для секций/групп)
- `--ah-border: rgba(255,255,255,.10)`
- `--ah-input: rgba(255,255,255,.06)`
- `--ah-text: #E6EDF3`
- `--ah-hint: rgba(230,237,243,.60)`
- `--ah-placeholder: rgba(230,237,243,.45)`
- `--ah-accent: #2F81F7`
- `--ah-accent-weak: rgba(47,129,247,.20)`
- `--ah-code: rgba(0,0,0,.55)`

### 3.2 Telegram WebApp header colors (опционально)
После `tg.ready()` установить:
```js
tg.setBackgroundColor('#0F1722');
tg.setHeaderColor('#0F1722');
```
> Поведение зависит от клиента Telegram, но это единственный официальный способ приблизить верхнюю системную полосу.

### 3.3 Placeholder вместо label
- В формах не показывать `.form-label`
- Использовать placeholder (как у BotFather).
- На focus фон инпута не меняется (остаётся тёмным), меняется только обводка/подсветка.

### 3.4 Геометрия (скругления)
- Инпуты и кнопки: radius **10px**
- Табы: radius **12px**
- Панели: radius **16px**

### 3.5 Заголовки вкладок как “страницы”
Как в BotFather: иконка + заголовок + короткое описание (опционально) над формой.

Данные брать из schema (`tab.header`):
```json
"header": { "icon": "🧾", "title": "История", "subtitle": "Просмотр выдач" }
```

## 4. Навигация вкладок (современно)

### Вариант A (рекомендуемый): Bottom navigation
Если вкладок много/длинные названия — лучше нижняя навигация (как приложение).
- 4–5 основных пунктов (Выдача, История, Помощь, Админ)
- админские подпункты внутри вкладки “Админ” (Search/Export/Users/…).

> Если уже реализованы top tabs и они устраивают — допускается оставить **top tabs**, но обязательно:
- без переносов,
- horizontal scroll,
- градиентные “fade edges” по краям.

## 5. История: карточки + UI-пагинация

### 5.1 Рендер карточек
Оставить карточный формат (как сейчас).

### 5.2 Управление страницами
Добавить UI:
- `←` (Prev), если page > 1
- `→` (Next), если page * per_page < total
- “Стр. X из Y” (Y = ceil(total / per_page)).

При клике — повторить запрос с `?page=N`.

## 6. Формы: поведение как у BotFather

### 6.1 Placeholder вместо label (обязательно)
В `renderForm()`:
- label не добавлять (или скрыть CSS)
- `input.placeholder = f.placeholder ?? f.label ?? f.name`

### 6.2 Focus без “белого фона”
CSS:
- `.form-control` и `.form-control:focus` имеют одинаковый background (`--ah-input`)
- подсветка через border + box-shadow (`--ah-accent-weak`)

### 6.3 Кнопки менее округлые
- `.btn` radius 10px

## 7. Конкретные правки (чеклист)

### 7.1 CSS (в `<style>`)
- внедрить палитру из п.3.1
- `.card-panel` + optional `.card-section` (panel-2)
- `.form-label{display:none}`
- `.form-control` focus rules + placeholder colors
- `.btn` радиусы
- навигация: top tabs (tabs-wrap + fade edges) или bottom nav

### 7.2 JS
- после `tg.ready()`:
  - `tg.setBackgroundColor(...)`
  - `tg.setHeaderColor(...)`
- `renderTabHeader()` (icon/title/subtitle)
- `history pagination` (Prev/Next) и запросы `?page=N`

## 8. Рефакторинг (умеренно, обязательно)

Цель: сохранить работоспособность, но сделать код читаемым и пригодным для повторного использования.

### 8.1 Структура JS (внутри одного файла)
В `index.blade.php` оставить один `<script>`, но разделить на блоки:

- `tgInit()` — init/цвета/заголовок
- `apiClient` — `apiGet/apiPost/downloadCsv`
- `schemaRenderer` — создание табов/панелей/хедера
- `formRenderer` — renderForm, serialize (минимум)
- `historyRenderer` — карточки + пагинация
- `utils` — escapeHtml, buildQuery

### 8.2 Единый формат API
UI опирается только на:
- `ok`, `data`, `error.message`, `error.fields` (если есть)

### 8.3 Минимизация “магических строк”
- tab ids и action strings вынести в константы JS.
- цвета только через CSS vars.

### 8.4 Ограниченное переиспользование
- Можно вынести палитру/базовые классы в `resources/views/webapp/_theme.blade.php`.
- Можно вынести JS в `resources/js/webapp.js` (если Vite уже используется).
- Не добавлять сложную сборку, если сейчас всё работает в одном blade.

## 9. Критерии приёмки

1) WebApp всегда тёмный (в Telegram светлая/тёмная — не важно).
2) Поля: placeholder, нет label; focus без смены фона.
3) Кнопки менее округлые (10px), табы 12px.
4) История: карточки + рабочие кнопки Prev/Next.
5) Шапка WebApp: `setHeaderColor/setBackgroundColor` применены.
6) Код структурирован (см. 8), без регресса.

## 10. Регресс-тест

1) Telegram в светлой теме → открыть WebApp → UI тёмный.
2) Выдача: заполнить → Отправить → WebApp закрывается → бот отвечает в чат.
3) История: пролистать Prev/Next (page меняется, данные новые).
4) Экспорт: скачать CSV.
