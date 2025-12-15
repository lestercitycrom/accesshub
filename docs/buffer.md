# AccessHub — UI ТЗ (v11) Infinite History + Desktop Tabs Wheel Scroll

Дата: **2025-12-15**  
Файл работ: `resources/views/webapp/index.blade.php`  
Backend API: **не менять** (используем текущие `page/per_page/total`).

---

## 1) Цель

1) Заменить постраничник истории на **infinite scroll** (лента), с безопасным fallback “Загрузить ещё”.  
2) Исправить UX табов на Desktop: колёсико мыши **скроллит меню вкладок горизонтально**, не страницу.

---

## 2) История: infinite scroll (рекомендованный UX)

### 2.1 Поведение
- При открытии вкладки “История” загружается `page=1`.
- При прокрутке вниз, когда пользователь дошёл до конца списка — автоматически подгружается следующая страница `page++`.
- Данные **добавляются в конец** (append), без перерендера уже показанных карточек.
- Когда `loadedCount >= total` — показывать “Конец списка”.
- Если IntersectionObserver недоступен или произошла ошибка — показывать кнопку **“Загрузить ещё”**.

### 2.2 Состояние в памяти (в JS)
Держать в объекте состояния:
- `historyPage` (int, старт 1)
- `historyPerPage` (int)
- `historyTotal` (int)
- `historyLoading` (bool)
- `historyDone` (bool)
- `historySeenIds` (Set) — анти-дубликаты (по `log_id` или `(order_id,account_id,created_at)` fallback)

### 2.3 Маркер конца списка
В конце списка карточек добавить элемент:
```html
<div id="historySentinel"></div>
```
И наблюдать его через `IntersectionObserver`.

### 2.4 UI элементы (обязательные)
- `#historyList` — контейнер карточек
- `#historyLoader` — индикатор загрузки
- `#historyLoadMoreBtn` — кнопка “Загрузить ещё” (fallback)
- `#historyEnd` — “Конец списка”

---

## 3) Tabs: wheel scroll на Desktop (критично для UX)

### 3.1 Требование
Когда курсор над зоной вкладок (`.tabs-rail`), колесо мыши должно:
- скроллить `.tabs-wrap` по X,
- **не скроллить страницу вниз**,
- работать только если вкладки реально шире контейнера.

### 3.2 JS (готовый код)
Вызвать **после рендера табов**:

```js
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

enableTabsWheelScroll();
```

CSS must-have:
```css
.tabs-rail { overflow:hidden; }
.tabs-wrap { overflow-x:auto; overflow-y:hidden; }
```

---

## 4) Реализация: шаги

### 4.1 История
1) Удалить текущий постраничник UI (строка `page=... per_page=... total=...` и Prev/Next).
2) Добавить блоки `historyList/Loader/LoadMoreBtn/End/Sentinel`.
3) Реализовать:
- `historyReset()` — очистить state + DOM при смене фильтра/вкладки
- `historyFetchNext()` — запрос `?page=historyPage`, append карточки, обновить state
- `historyAttachObserver()` — IntersectionObserver на sentinel
4) В обработчике формы истории:
- при submit: historyReset(); historyFetchNext()

---

## 5) Тестирование

### 5.1 История infinite scroll
- Открыть История → загрузка первой страницы.
- Скроллить вниз → подгружается page 2 → карточки добавляются.
- Дойти до конца → показывается “Конец списка”.
- Ошибка → появляется “Загрузить ещё” (fallback).

### 5.2 Desktop tabs wheel
- Telegram Desktop: навести мышь на меню вкладок → крутить колесо:
  - вкладки двигаются влево/вправо,
  - страница не уезжает вниз.
- Навести мышь на контент → колесо снова скроллит страницу.

---

## 6) Acceptance

- История работает как лента: авто-подгрузка + кнопка “Загрузить ещё”.
- Нет дублей карточек (Set).
- Табы на Desktop скроллятся колёсиком, без прокрутки страницы.
