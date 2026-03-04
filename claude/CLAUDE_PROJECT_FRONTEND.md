# allrpg.info — Фронтенд: специфика проекта

> Общая архитектура фронтенда (FraymElement API, SPA/updateState, actionRequest полная спецификация, fetchData детально, window.fetch Proxy (JWT auto-refresh), event delegation, MutationObserver batch processing, lazy-загрузка JS/CSS, JWT/CSRF, UI-компоненты: Modal, Tabs, FilePond, Quill, Autocomplete, DragDrop, Noty, SBI, FraymStyler, FraymAudioPlayer, Dropfield, паттерны CMSVC-модулей, условные поля, валидация форм, deep links/hash-routing, CSS-архитектура, локали, keyboard shortcuts, swipe gestures, .careful, no_dynamic_content, утилиты, антипаттерны):
> `vendor/alxgarshin/fraym/claude/CLAUDE_PROJECT_FRONTEND.md`

---

## Хук projectInit()

Fraym вызывает `projectInit(withDocumentEvents)` из `public/js/global.js`. В allrpg.info хук выполняет:

```javascript
function projectInit(withDocumentEvents) {
    // Загрузка переиспользуемых JS-компонентов
    loadJsComponent('actions');
    loadJsComponent('conversations_widget');
    loadJsComponent('tasks_widget');
    loadJsComponent('wall_notion_conversation');
    loadJsComponent('conversation_form');
    // ... и другие

    getNewEventsTimeout();   // запуск real-time polling
    autoClickLoads();        // viewport-based auto-load

    // Обработка show_hidden, show_hidden_table (делегирование на document)
}
```

---

## JsComponents (src/JsComponent/)

Переиспользуемые компоненты проекта, загружаются через `loadJsComponent()`:

| Компонент | Назначение |
|-----------|-----------|
| `actions` | Универсальные действия: invite, accept/decline, mark_important, leave_dialog |
| `conversations_widget` | Виджет диалогов в шапке |
| `tasks_widget` | Виджет задач в шапке |
| `wall_notion_conversation` | Стена, отзывы, комментарии к диалогам |
| `conversation_form` | Форма отправки сообщения |
| `library` | Медиа-библиотека (фото, видео) |
| `webpush` | Web Push подписка (только для залогиненных) |
| `task_event` | Карточка задачи/события |
| `application` | Карточка заявки (переиспользуется вне раздела application) |

---

## Real-time polling

```javascript
// Запускается каждые 30 секунд (или 300 при mute)
function getNewEventsTimeout() {
    actionRequest({ action: 'user/get_new_events', ... });
    debounce('getNewEvents', getNewEventsTimeout, getNewEventsTimeoutTimer);
}
```

**Ответ сервера обрабатывает `_arSuccess('get_new_events')`:**
- Новые диалоги → обновляет счётчики в `conversations_widget`
- Новые задачи → обновляет `tasks_widget`
- Новые комментарии к заявкам → `newApplicationCommentsIds`
- Онлайн-статусы контактов
- Звуковое уведомление через `<audio id="new_message_alert">`

**Управление интервалом:**
```javascript
getNewEventsTimeoutTimer = 300000;  // 5 минут (mute)
getNewEventsTimeoutTimer = 30000;   // 30 секунд (обычный режим)
```

---

## Lazy-auto-load: конкретные вызовы в проекте

Паттерн `autoClickLoad` описан в документации фреймворка. В allrpg.info используется для:

```javascript
autoClickLoad('a.load_wall', null, 'wall_notion_conversation');
autoClickLoad('a.load_conversation', ...);
autoClickLoad('a.load_library', null, 'library');
autoClickLoad('a.load_users_list');
autoClickLoad('a.load_tasks_list');
```

---

## window[] переменные проекта

Инжектируются PHP в `MainTemplate::asHTML()` (проектные, не фреймворковые):

| Переменная | Тип | Назначение |
|-----------|-----|-----------|
| `window['projectControlId']` | int | Активный project_id |
| `window['projectControlItems']` | 'show'/'hide' | Наличие прав гейммастера |
| `window['projectControlItemsName']` | string | Название проекта |
| `window['projectControlItemsRights']` | string | Права (пробел-разделённый список) |

---

## customHashHandler: allrpg-якоря

В `public/js/global.js` реализован `customHashHandler(parsedHref)`:

```javascript
// #wall{N}  → открывает комментарий N стены через fraymmodal
// #wmc_{N}  → скроллит к сообщению N в диалоге, раскрывает скрытые
// #notion   → активирует таб отзывов
// Любой другой hash → ищет [id="hash"] или .fraymmodal-window[hash="hash"] и кликает
```

---

## HTML-структура страницы

Специфика allrpg.info (структура `div.maincontent_data` — в доке фреймворка):

```html
<body class="allrpg">
  <!-- Виджеты (только залогиненные) -->
  <div class="tasks_widget_container">...</div>
  <div class="conversations_widget_container">...</div>
  <audio id="new_message_alert" ...></audio>

  <div class="fullpage">
    <div class="mobile_menu">...</div>     <!-- боковое меню -->
    <div class="fullpage_wrapper">
      <div class="header">
        <div class="header_left">          <!-- search -->
        <div class="header_middle">        <!-- logo -->
        <div class="header_right">         <!-- login/user block -->
      </div>
      <div class="content">
        <div class="maincontent">
          <div class="maincontent_wrapper">
            <div class="maincontent_data"> <!-- SPA-область (Fraym) -->
          </div>
        </div>
      </div>
      <div class="footer">...</div>
    </div>
  </div>

  <!-- Антибот-переменная -->
  <script>const justAnotherVar = "...";</script>
  <!--google_analytics-->
  <!--messages-->  <!-- CSRF-токен + уведомления -->
</body>
```

---

## CSS: проектные переменные

`public/css/global.css` определяет на `body.allrpg`:

```css
body.allrpg {
    --font-family: "Roboto", Arial, sans-serif;
    --deep-blue: #1d2632;
    --deep-blue-contrast: #2D425B;
    --blocks-borders-and-separators: #eaeaea;
    --shadow-gray-15: rgba(48, 60, 106, .15);
    --green-marker: rgb(0, 150, 0);
    --special-red: rgb(197, 44, 63);
    --special-gray: #A5ACBF;
}
```

Проектные CSS-файлы:
```
public/css/
├── global.css             # Основной CSS (111KB)
├── alt_ingame.css         # Стили для ingame-режима
└── components/            # 20 компонентных файлов
    ├── conversations.css
    ├── wall_message.css
    ├── tasks_table.css
    ├── plot.css
    └── ...
```

---

## PWA

- **Service Worker:** `/js/pwa_sw.min.js`
- **Web Push SW:** `/js/webpush-messaging-sw.min.js`
- **Манифест:** `/favicons/manifest-{locale}.json` (locale-зависимый)
- **Install prompt:** `beforeinstallprompt` → кнопка в `div.PWAinfo`
- **iOS:** ручная инструкция "добавить на главный экран"
- **VAPID:** `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` в `.env`

---

## Локали: пути allrpg.info

```
public/locale/{RU|EN|ES}/locale.json   # глобальные строки проекта
src/CMSVC/{Kind}/{RU|EN|ES}.json       # строки раздела
```

(Строки фреймворка — `public/vendor/fraym/locale/`)
