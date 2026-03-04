# allrpg.info — Контекст для Claude

Портал для живых ролевых игр (LARP). PHP 8.4 + **MySQL** + Fraym framework (^0.9).

> Документация фреймворка: `vendor/alxgarshin/fraym/claude/CLAUDE_PROJECT.md`
> CLI (Console), Bootstrap-цепочка, .env-порядок, Proxy-паттерн (DB/CACHE/CURRENT_USER), RequestTypeEnum, CMSVC-паттерн, ValueObject bridge (Attribute→Item), контекст-система (полное дерево), BaseModel lifecycle, стандартные трейты, BaseController lifecycle, BaseService lifecycle hooks, fraymAction структура данных, SQLDatabaseService полный API, CacheService, CurrentUser, CSRF, JWT-аутентификация, LocaleHelper API, валидаторы, RightsHelper базовый, DataHelper, ResponseHelper, DatabaseDialect (17 методов), Migration механизм, CatalogEntity/TableEntity/MultiObjectsEntity, EnvService, ключевые env-переменные — описаны там.

---

## Стек и окружение

| Компонент | Значение |
|-----------|----------|
| Язык | PHP 8.4 |
| База данных | **MySQL** (DATABASE_TYPE=mysql во всех окружениях — dev/stage/prod) |
| Фреймворк | alxgarshin/fraym ^0.9 |
| Сервер | PHP-FPM + Nginx (внешний контейнер) |
| Контейнеры | Docker (multi-stage: dev / staging / prod) |
| Статанализ | PHPStan level 5 + Psalm level 6 (пустой baseline — кодовая база чистая) |
| Тесты | PHPUnit (bin/test: phpstan + psalm + phpunit) |

---

## Структура проекта

```
src/
├── index.php              # Роутер + диспетчер ответов (223 строки)
├── CMSVC/                 # 96 модулей (Controller / Model / Service / View)
│   └── Trait/             # Переиспользуемые трейты сервисов (8 штук)
├── Helper/                # RightsHelper, DateHelper, DesignHelper, ...
├── Template/              # MainTemplate, LoginTemplate, BannersTemplate
├── Lib/                   # Бандлованные библиотеки (не через Composer!)
│   ├── captcha/           # Captcha\ namespace
│   ├── ical/              # Ical\ namespace (SimpleICS)
│   ├── identicon/         # Identicon\ namespace
│   ├── phpQrCode/         # PhpQrCode\ namespace
│   └── wideimage/         # WideImage\ namespace
└── Migrations/
    └── Sql/               # Sql20230627140700.sql (65 таблиц, MySQL dump)
```

---

## Роутер: src/index.php

Маппинг: `$_REQUEST['kind']` (snake_case) → CamelCase → `App\CMSVC\{Name}\{Name}Controller`

```php
$CMSCVName = TextHelper::snakeCaseToCamelCase(KIND);
$controllerName = 'App\\CMSVC\\' . $CMSCVName . '\\' . $CMSCVName . 'Controller';
```

**Порядок выполнения:**
1. Логин / логаут / проверка бана
2. `RightsHelper::checkProjectRights()` → `PROJECT_RIGHTS` и `ALLOW_PROJECT_ACTIONS`
3. Auto-redirect на `last_page_visited` (cookie), если первый заход залогиненного
4. `DataHelper::activityLog()`
5. Загрузка контроллера → `checkIfIsAccessible()` → `CMSVC->init()` → `Response()` или `{ACTION}()`
6. **Фолбек:** поиск в таблице `article` по полю `attachments = KIND`
7. Если ничего — `Error404Controller`

**Проектные константы (определяются в src/index.php):**
- `BID` — block ID (`$_REQUEST['bid']`)
- `MODAL` — признак модального окна
- `PROJECT_RIGHTS` — массив прав текущего пользователя в активном проекте
- `ALLOW_PROJECT_ACTIONS` — bool, есть ли права на gamemaster-действия
- `REQUEST_PROJECT_ID` — строка `project_id=N` или null (определяется в RightsHelper)

---

## Шаблонный движок: MainTemplate

HTML строится конкатенацией строк. Инжект блоков через `preg_replace` по HTML-комментариям:

| Маркер | Что инжектируется |
|--------|-------------------|
| `<!--pagetitle-->` | Заголовок страницы |
| `<!--maincontent-->` | HTML из контроллера |
| `<!--login-->` | LoginTemplate::asHtml() |
| `<!--banners-->` | BannersTemplate::asHTML() |
| `<!--google_analytics-->` | GA-скрипт (если GOOGLE_ANALYTICS != '') |
| `<!--messages-->` | CSRF-токен + window["messages"] |

**Важно:** `DataHelper::pregQuoteReplaced()` оборачивает контент при вставке — не забывать при отладке.

**MODAL-режим:** при `MODAL=true` рендерится только `fraymmodal-title` + `fraymmodal-content`, без основного шаблона.

**SPA-ответ (динамический запрос, не MODAL):** возвращается JSON `{html, pageTitle, messages, executionTime}`.

**Антибот:** в HTML инжектируется `const justAnotherVar = "$_ENV['ANTIBOT_CODE']"`.

**PWA:** поддержка манифестов (`/favicons/manifest-{locale}.json`), pwacompat, иконки для iOS/Android.

---

## Система прав (relation table)

Все права хранятся в таблице `relation` как граф EAV:

```
obj_type_from | obj_id_from | type       | obj_type_to | obj_id_to
{user}        | 42          | {admin}    | {project}   | 7
{user}        | 42          | {member}   | {community} | 3
{project}     | 7           | {child}    | {conversation} | 15
```

**Типы прав (брекетированные строки):**
- `{admin}`, `{gamemaster}` — управление проектом
- `{member}`, `{moderator}`, `{responsible}`, `{friend}` — участие в объектах
- `{child}` — связь родитель-потомок между объектами
- Секционные права гейммастера хранятся в поле `comment` записи `{gamemaster}`

**App\Helper\RightsHelper** (расширяет Fraym\Helper\RightsHelper) добавляет:
- `checkProjectRights()` — главный метод, возвращает массив прав в проекте, сохраняет `project_id` в cookie
- `checkAllowProjectActions()` — bool проверка {gamemaster}/{admin}
- `getActivatedProjectId()` — из cookie `project_id` или из ID в URL (для KIND=project)
- `getAccess()` — логика вступления в {open} объект или отправки запроса через конверсацию
- `checkProjectKindAccessAndRedirect()` — стандартный гейт для разделов с project-правами
- Секционные права ({budget}, {fee}, {rooms}, {qrpg_key} и т.д.) парсятся из comment поля relation

**Активный проект** — хранится в cookie `project_id`. Гейммастер может переключаться между проектами. При каждом запросе определяется через `RightsHelper::getActivatedProjectId()`.

---

## База данных: 65 таблиц (ключевые)

**Пользователи:**
- `user` — основная таблица, поля: sid (UNIQUE), login, pass, fio, nick, em, em_verified, refresh_token
- `user__push_subscriptions` — Web Push подписки (FOREIGN KEY → user.id)

**Проекты (основной домен):**
- `project` — иерархия проектов (поле parent), тип open/close, status, sorter, currency
- `project_application` — заявки игроков, поля: project_id, creator_id, project_character_id, money, status, team_application, deleted_by_player, deleted_by_gamemaster
- `project_character` — персонажи проекта (team_character, applications_needed_count)
- `project_group` — группы/отряды (parent, rights, disallow_applications, responsible_gamemaster_id)
- `project_plot` — сюжеты (parent, project_character_ids JSON, responsible_gamemaster_id)
- `project_room` — локации/комнаты (one_place_price, places_count)
- `project_fee` — взносы (parent — иерархия взносов)
- `project_payment_type` — типы оплат
- `project_filterset` — сохранённые фильтры гейммастера
- `project_application_field` — ответы на вопросы анкеты (EAV: application_id + ruling_question_id + value)
- `project_application_history` — история изменений заявки
- `project_application_geoposition` — геопозиция игрока
- `project_application_meeting` — встречи по заявке
- `project_role_playing_setting` — игровые настройки/ресурсы (цветовые метки)

**Банк/Финансы:**
- `bank_currency` — валюты проекта
- `bank_transaction` — транзакции между заявками
- `bank_rule` — правила транзакций
- `project_transaction` — оплаты взносов

**QRPG (QR-игровая механика):**
- `qrpg_code` — QR-коды (sid, copies, category, location, hacking_settings JSON)
- `qrpg_key` — игровые ключи (keydata, consists_of JSON, img)
- `qrpg_hacking` — попытки взлома QR-кодов
- `qrpg_history` — история использования QRPG

**Конверсации/Сообщения:**
- `conversation` — диалоги (creator_id, description)
- `conversation_message` — сообщения (parent, content, message_action, message_action_data)
- `conversation_message_status` — статус прочтения/удаления (UNIQUE: message_id + user_id)

**Контент:**
- `article` — статичные страницы (поле attachments — slug для роутера)
- `news` — новости (obj_type/obj_id — полиморфная привязка)
- `publication` — публикации
- `calendar_event` — события календаря (region, area, gametype, date_from, date_to, mg)
- `calendar_event_gallery` — фотогалерея событий
- `ruling_item` — правила игры (FULLTEXT)
- `ruling_question` — вопросы анкеты (field_type, show_if JSON)
- `ruling_tag` — теги правил

**Социальное:**
- `relation` — граф прав и связей (центральная таблица)
- `community` — сообщества (FULLTEXT: tags)
- `notion` — отзывы/рейтинги пользователей на события
- `subscription` — подписки на объекты
- `subscription_push` — push-подписки
- `played` — участие пользователя в событии
- `report` — репортажи о событиях

**Прочее:**
- `area` — площадки (tipe, name, city, coordinates, havegood JSON, havebad JSON)
- `geography` — гео-объекты (city/region)
- `geography_hierarchy` — иерархия гео
- `exchange_item` / `exchange_category` — биржа предметов
- `resource` — ресурсы/предметы проекта
- `task_and_event` — задачи и события (используется для обоих типов!)
- `activity_log` — лог активности
- `banner` — баннеры
- `tag` — теги
- `regstamp` — коды регистрации (hash + code)
- `api_application` — API-приложения (google_api_key, apple_api_key)
- `achievement` — достижения

**Важно:** таблица `task_and_event` используется для KIND='task' И KIND='event' — в коде есть `if ($tableObjType === 'event' || $tableObjType === 'task') { $tableObjType = 'task_and_event'; }`.

---

## Кастомные библиотеки (src/Lib/)

Не через Composer — бандлованы в репозитории:

| Namespace | Библиотека | Назначение |
|-----------|-----------|-----------|
| `Captcha\` | src/Lib/captcha/ | CAPTCHA-генерация |
| `Ical\` | src/Lib/ical/ | iCalendar экспорт (SimpleICS) |
| `Identicon\` | src/Lib/identicon/ | Генерация аватаров-идентиконов |
| `PhpQrCode\` | src/Lib/phpQrCode/ | Генерация QR-кодов |
| `WideImage\` | src/Lib/wideimage/ | Обработка изображений |

Единственная Composer-зависимость бизнес-логики: `minishlink/web-push` ^9.0 (Web Push уведомления).

---

## Внешние сервисы / интеграции

| Сервис | Конфигурация |
|--------|-------------|
| **VKontakte OAuth** | VK_APP_ID, VK_APP_SECRET (.env.dev) |
| **Facebook OAuth** | FB_APP_ID, FB_APP_SECRET |
| **Web Push (VAPID)** | VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY |
| **Google Analytics** | GOOGLE_ANALYTICS (UA-2587146-1 на prod) |
| **PayAnyWay** | PAYANYWAY_* |
| **YKassa (ЮКасса)** | YKASSA_* |
| **PayMaster** | PAYMASTER_* |
| **PayKeeper** | PAYKEEPER_* |
| **Email** | SMTP через ssmtp (Dockerfile) |

---

## 96 CMSVC-модулей: карта домена

### Аутентификация и пользователи
`Login`, `Logout`, `Register`, `Registration`, `Fbauth`, `Vkauth`, `User`, `Profile`, `Agreement`, `Privacy`, `Oferta`

### Главная / навигация
`Start`, `Search`, `Go` (редирект), `Error404`, `Article`, `News`, `NewsEdit`, `Publication`, `PublicationsEdit`, `ArticlesEdit`, `Help`, `Faq`

### Проект (ядро)
`Application`, `Myapplication` — заявки игроков и гейммастера
`Character` — персонажи
`Plot` — сюжеты (поддерживает вложенные PlotPlotModel)
`Group` — группы/отряды
`Rooms` — размещение
`Fee` — взносы (FeeOptionModel — вложенная)
`Budget` — бюджет
`PaymentType` — типы оплат
`Transaction` — транзакции взносов
`Roles` — просмотр участников
`Ruling`, `RulingEdit`, `RulingItemEdit`, `RulingQuestionEdit`, `RulingTagEdit` — правила
`Setup` — настройки проекта

### Финансы
`BankTransaction`, `BankCurrency`, `BankRule`, `IngameBankTransaction`

### QRPG
`QrpgKey`, `QrpgCode`, `QrpgGenerator`, `QrpgHistory`, `Kogdaigra`

### Социальное
`Message`, `Conversation`, `Wall`, `Wall2`, `Community`, `Gamemaster`, `People`, `Notion`, `Report`, `Mark`, `Task`, `Tasklist`, `Event`

### Контент
`Calendar`, `CalendarEvent`, `CalendarEventGroup`, `CalendarEventGallery`, `Ical`, `Portfolio`, `Document`, `Photo`, `File`

### Гео
`Area`, `Geoposition`, `HelperGeographyCity`

### Биржа
`Exchange`, `ExchangeCategoryEdit`, `ExchangeItemEdit`

### Вспомогательные / утилиты
`Org`, `Community`, `BannersEdit`, `Siteroles`, `Csvimport`, `Filterset`, `Ingame`, `Mobile`, `Mailtest`, `PopupHelper`

### Helpers (BaseHelper, не BaseController)
`HelperApplication`, `HelperUsersList`, `HelperGamesList`, `HelperSearch`

---

## Переиспользуемые трейты (src/CMSVC/Trait/)

| Трейт | Назначение |
|-------|-----------|
| `ApplicationServiceTrait` | 79KB — вся сложная логика фильтрации/отображения заявок |
| `GamemastersListTrait` | Список гейммастеров проекта |
| `GetUpdatedAtCustomAsHTMLRendererTrait` | Рендер timestamp с именем редактора |
| `ProjectDataTrait` | Кэш данных проекта, getProjectId(), getActivatedProjectId(), checkRightsRestrict() SQL |
| `ProjectIdTrait` | Поле $project_id с OnCreate-коллбеком |
| `ProjectSectionsPostViewHandlerTrait` | PostViewHandler для разделов проекта |
| `RequestCheckSearchTrait` | Валидация поискового запроса (min 3 chars) |
| `UserServiceTrait` | Lazy-load UserService через CMSVCHelper::getService('user') |

---

## Кастомные хелперы (src/Helper/)

| Класс | Назначение |
|-------|-----------|
| `RightsHelper` | Расширяет Fraym\Helper\RightsHelper: project rights, getAccess(), dynamicAddRights() |
| `DateHelper` | Работа с датами |
| `DesignHelper` | checkMenuItemVisibility(), replaceVarsInMenu(), configurable DESIGN_PATH |
| `FileHelper` | Загрузка/обработка файлов |
| `MessageHelper` | Работа с сообщениями |
| `TextHelper` | Форматирование текста |
| `UniversalHelper` | Универсальный хелпер |

---

## Шаблоны (src/Template/)

| Класс | Назначение |
|-------|-----------|
| `MainTemplate` | Полный HTML-документ: меню, header, footer, PWA, dark mode, виджеты задач и диалогов |
| `LoginTemplate` | Блок входа/профиля пользователя |
| `BannersTemplate` | Рекламные баннеры |

**Меню в MainTemplate** строится из `$LOCALE['main_menu']` (из locale.json). Видимость пунктов — через `DesignHelper::checkMenuItemVisibility()`. Проектное управление (гейммастерские разделы) выводится через `$LOCALE['project_control_items']` и фильтруется по `PROJECT_RIGHTS`.

---

## Специфика разработки

### Docker
```
bin/d install         # первичная установка (clone → build → composer → migrate)
bin/d up              # запуск
bin/d app             # войти в контейнер
bin/d cache           # очистить кэш
bin/test              # phpstan + psalm + phpunit
composer app:cs:fix   # fix code style
```

### Миграции
`src/Migrations/Sql/Sql20230627140700.sql` — основной дамп (MySQL, 65 таблиц).
`src/Migrations/Sql/Sql20251118152435.sql` — пустой (placeholder).

### PHPStan bootstrap
`tests/phpstan-bootstrap.php` — определяет все глобальные константы и fake-инстансы DB/CACHE для статанализа без реального соединения.

---

## Нестандартные архитектурные решения

1. **Relation table как универсальный граф** — права, членство, иерархии, дружба — всё через одну таблицу `relation` с EAV-подобной структурой. Нет отдельных pivot-таблиц.

2. **task_and_event** — одна таблица для двух разных KIND ('task' и 'event'). Роутер делает `if task/event → task_and_event`.

3. **Article table как статический роутер** — если KIND не найден в CMSVC, ищется запись в `article.attachments`. Это позволяет создавать "статичные страницы" без PHP-кода.

4. **Секционные права гейммастера в comment** — поле `comment` строки `{gamemaster}` в `relation` содержит JSON/CSV список разрешённых разделов. Парсится в `RightsHelper::checkProjectRights()`.

5. **project_id в cookie** — активный проект определяется по cookie, не по URL. Это позволяет гейммастеру переходить по разным разделам без потери контекста проекта.

6. **Бандлованные Lib** — WideImage, phpQrCode, SimpleICS, identicon, captcha — не в Composer, а в src/Lib/ с собственными PSR-4 namespace.

7. **Шаблонизация через preg_replace** — вместо Twig/Blade — чистые HTML-маркеры `<!--name-->` + regex замена. DataHelper::pregQuoteReplaced() для безопасности.

8. **Двойные delete флаги на заявке** — `deleted_by_player` и `deleted_by_gamemaster` — мягкое удаление с разграничением по роли.

9. **project.parent** — проекты иерархичны, поддерживают вложенность. В рутинах нужно учитывать parent при агрегации.

10. **project_application_field** — анкетные ответы хранятся в EAV (application_id + ruling_question_id + value), а не как колонки.
