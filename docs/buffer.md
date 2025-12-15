Скролл в смартфоне работает когда я свайпинг делаю. А вот в десктопной версии и колесико кручу и всякое остальное - не помогает
----------------
Понял. На десктопе **колёсико по умолчанию скроллит вертикаль**, а горизонтальный скролл в “rail” почти никогда не срабатывает без:

* **Shift + колесо** (но это неочевидно пользователю),
* или явного JS, который переводит wheel → horizontal scroll.

Надо сделать так: **если курсор над меню вкладок**, то прокрутка колёсиком должна скроллить **вправо/влево**.

## 1) Что добавить (JS, минимально и безопасно)

В `index.blade.php` после того, как `tabs-wrap` уже есть в DOM:

```js
function enableHorizontalWheelScroll(container) {
	if (!container) {
		return;
	}

	container.addEventListener('wheel', (e) => {
		const hasHorizontal = container.scrollWidth > container.clientWidth;
		if (!hasHorizontal) {
			return;
		}

		// Convert vertical wheel to horizontal scroll
		if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
			e.preventDefault();
			container.scrollLeft += e.deltaY;
		}
	}, { passive: false });
}

// Call after tabs are rendered
enableHorizontalWheelScroll(document.querySelector('.tabs-wrap'));
```

## 2) Что проверить в CSS (чтобы десктоп не “ломался”)

У `.tabs-wrap` должно быть:

```css
.tabs-wrap{
	overflow-x:auto;
	overflow-y:hidden;
}
```

И у `.nav-tabs`:

```css
.nav-tabs{
	flex-wrap:nowrap;
}
```

## 3) Как протестить

### Desktop

1. Открой WebApp в Telegram Desktop.
2. Наведи мышь на верхние вкладки.
3. Крути колёсико:

* должно двигать вкладки горизонтально.

4. Уведи курсор вниз на контент:

* колёсико снова должно скроллить страницу вертикально.

### Mobile

* Свайп по вкладкам остаётся как был.
