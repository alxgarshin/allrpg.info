<?php

declare(strict_types=1);

namespace App;

use App\CMSVC\Article\ArticleController;
use App\CMSVC\Error404\Error404Controller;
use App\Helper\RightsHelper;
use App\Template\{BannersTemplate, LoginTemplate, MainTemplate};
use Fraym\BaseObject\{BaseController, BaseHelper};
use Fraym\Enum\ActionEnum;
use Fraym\Helper\{CookieHelper, DataHelper, LocaleHelper, ResponseHelper, TextHelper};
use Fraym\Interface\Response;
use Fraym\Response\{ArrayResponse, HtmlResponse};
use Fraym\Service\GlobalTimerService;

/** Проверяем наличие таймера */
if (!isset($_GLOBALTIMER)) {
    $_GLOBALTIMER = new GlobalTimerService();
}

/** Устанавливаем глобальные переменные */
define('BID', $_REQUEST['bid'] ?? null);
define('MODAL', $_REQUEST['modal'] ?? false);
define('OBJ_TYPE', $_REQUEST['obj_type'] ?? null);
$objId = ($_REQUEST['obj_id'] ?? false) ? (is_array($_REQUEST['obj_id']) ? $_REQUEST['obj_id'][0] : $_REQUEST['obj_id']) : null;
define('OBJ_ID', is_numeric($objId) ? (int) $objId : $objId);

/** Логинимся / выходим */
if ('logout' === ACTION) {
    CURRENT_USER->authLogout();
} elseif (CURRENT_USER->isLogged() && CURRENT_USER->isBanned()) {
    CURRENT_USER->authLogout(LocaleHelper::getLocale(['user'])['you_re_banned']);
}

/** Проверяем права доступа к проекту */
define('PROJECT_RIGHTS', RightsHelper::checkProjectRights());
define('ALLOW_PROJECT_ACTIONS', RightsHelper::checkAllowProjectActions(PROJECT_RIGHTS));

/** Если это первая открытая страница сайта и пользователь залогинен, проверяем, нет ли cookie последней успешно сгенеренной страницы */
if (
    CURRENT_USER->isLogged() && CookieHelper::getCookie('last_page_visited')
    && !preg_match('#' . ABSOLUTE_PATH . '#', $_SERVER['HTTP_REFERER'] ?? '')
    && in_array(KIND, [$_ENV['STARTING_KIND'], ''])
) {
    if (!CURRENT_USER->getBlockAutoRedirect()) {
        $lastPageVisited = CookieHelper::getCookie('last_page_visited');
        CookieHelper::batchDeleteCookie(['last_page_visited']);

        if (!in_array($lastPageVisited, [ABSOLUTE_PATH, ABSOLUTE_PATH . '/' . $_ENV['STARTING_KIND'] . '/'])) {
            ResponseHelper::redirect($lastPageVisited);
        }
    }
}

/** Записываем данные в лог */
DataHelper::activityLog();

/** Подгружаем соответствующий запросу контроллер раздела: он в свою очередь подключает необходимые модели и вьюшку */
$RESPONSE_DATA = null;
$CMSCVName = TextHelper::snakeCaseToCamelCase(KIND);
$controllerName = 'App\\CMSVC\\' . $CMSCVName . '\\' . $CMSCVName . 'Controller';
$controller = null;

if (class_exists($controllerName)) {
    /** @var BaseHelper|BaseController $controller */
    $controller = new $controllerName();

    if ($controller instanceof BaseController) {
        $controller->construct(CMSVCinit: false);
    }

    if ($controller instanceof BaseHelper || ($controller->checkIfIsAccessible() && $controller->checkIfHasToBeAndIsAdmin())) {
        if ($controller instanceof BaseController) {
            $controller->getCMSVC()->init();
        }

        if (is_null(ACTION) || in_array(ACTION, ActionEnum::cases())) {
            $RESPONSE_DATA = $controller->Response();
        } elseif (method_exists($controller, ACTION)) {
            if ($controller instanceof BaseHelper || $controller->checkIfIsAccessible(ACTION)) {
                $RESPONSE_DATA = $controller->{ACTION}();
            }
        } else {
            $LOCALE_CONVERSATION = LocaleHelper::getLocale(['conversation', 'global']);
            $RESPONSE_DATA = new ArrayResponse([
                'response' => 'error',
                'response_error_code' => 'wrong_action',
                'response_text' => $LOCALE_CONVERSATION['messages']['wrong_action'],
            ]);
        }
    }
}

/** Если нет контента, проверяем таблицу article на наличие фиксированных статей */
if (is_null($RESPONSE_DATA)) {
    $sectionData = DB->select(
        'article',
        [
            ['attachments', KIND],
            ['active', '1'],
        ],
        true,
    );

    if ($sectionData) {
        $RESPONSE_DATA = (new ArticleController())->construct()->init()->Default();
    }
}

/** Если в результате обработки контента нет, ошибка 404 */
if (!($RESPONSE_DATA instanceof Response)) {
    (new Error404Controller())->construct(CMSVCinit: false)->init()->Default();
}

/** Подгружаем базовую локаль проекта */
$LOCALE = LocaleHelper::getLocale(['global']);

$cookieMessages = CookieHelper::getCookie('messages', true);

if ($cookieMessages && !$controller instanceof BaseHelper) {
    CookieHelper::batchDeleteCookie(['messages']);
}

if ($RESPONSE_DATA instanceof ArrayResponse) {
    $RESPONSE_RESULT = $RESPONSE_DATA->getData();

    if (!$controller instanceof BaseHelper) {
        if ($RESPONSE_RESULT['messages'] ?? false) {
            $RESPONSE_RESULT['messages'] = array_merge($RESPONSE_RESULT['messages'], $cookieMessages ?? []);
        } else {
            $RESPONSE_RESULT['messages'] = $cookieMessages ?? [];
        }
        $RESPONSE_RESULT['executionTime'] = $_GLOBALTIMER->getTimerDiff();
    }
    header('Access-Control-Allow-Origin: *');
    echo DataHelper::jsonFixedEncode($RESPONSE_RESULT);
} elseif ($RESPONSE_DATA instanceof HtmlResponse) {
    /** Если предоставлено альтернативное название страницы, убеждаемся, что оно идет с большой буквы */
    $PAGETITLE = $RESPONSE_DATA->getPagetitle();

    if (!is_null($PAGETITLE) && !in_array($PAGETITLE, ['', $LOCALE['sitename']])) {
        $PAGETITLE = TextHelper::mb_ucfirst($PAGETITLE);
    }

    if ($PAGETITLE === '' || is_null($PAGETITLE)) {
        $PAGETITLE = $LOCALE['sitename'];
    }

    if (!MODAL) {
        /** Сохраняем информацию об адресе текущей страницы */
        CookieHelper::batchSetCookie(
            [
                'last_page_visited' => ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']),
            ],
        );
    }

    if (REQUEST_TYPE->isDynamicRequest() && !MODAL) {
        $RESPONSE_RESULT = DataHelper::jsonFixedEncode(
            [
                'html' => $RESPONSE_DATA->getHtml(),
                'pageTitle' => $PAGETITLE,
                'messages' => $cookieMessages ?? [],
                'executionTime' => $_GLOBALTIMER->getTimerDiff(),
            ],
        );
        header('Access-Control-Allow-Origin: *');
        echo $RESPONSE_RESULT;
    } else {
        /** Вносим блоки информации в заданный шаблон визуализации */
        if (MODAL) {
            $RESPONSE_RESULT = '<div class="fraymmodal-title">' . $PAGETITLE . '</div><div class="fraymmodal-content">' .
                $RESPONSE_DATA->getHtml() .
                '</div><!--google_analytics--><!--messages-->';
        } else {
            /** Подгружаем глобальный шаблон */
            $RESPONSE_TEMPLATE = MainTemplate::asHTML();
            $RESPONSE_TEMPLATE = preg_replace('#<!--pagetitle-->#', $PAGETITLE, $RESPONSE_TEMPLATE);

            /** Рендерим окошко логина / пользователя */
            $RESPONSE_LOGIN = LoginTemplate::asHtml();

            /** Создаем баннеры */
            $RESPONSE_BANNERS = BannersTemplate::asHTML();

            $RESPONSE_RESULT = preg_replace('#<!--maincontent-->#', DataHelper::pregQuoteReplaced($RESPONSE_DATA->getHtml()), $RESPONSE_TEMPLATE);
            $RESPONSE_RESULT = preg_replace('#<!--login-->#', DataHelper::pregQuoteReplaced($RESPONSE_LOGIN), $RESPONSE_RESULT);
            $RESPONSE_RESULT = preg_replace('#<!--banners-->#', DataHelper::pregQuoteReplaced($RESPONSE_BANNERS), $RESPONSE_RESULT);
        }

        /** Добавляем Google Analytics */
        $googleAnalytics = '';

        if ($_ENV['GOOGLE_ANALYTICS'] !== '') {
            $googleAnalytics = '
<script async src="https://www.googletagmanager.com/gtag/js?id=' . $_ENV['GOOGLE_ANALYTICS'] . '"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag(\'js\', new Date());

  gtag(\'config\', \'' . $_ENV['GOOGLE_ANALYTICS'] . '\');
</script>
';
        }
        $RESPONSE_RESULT = preg_replace('#<!--google_analytics-->#', $googleAnalytics, $RESPONSE_RESULT);

        /** Добавляем сообщения-нотификации */
        $messageArray = '<script>
    window["messages"] = defaultFor(window["messages"], []);';

        if ($cookieMessages) {
            foreach ($cookieMessages as $message) {
                $messageArray .= 'messages.push(Array("' . $message[0] . '","' . str_replace('"', '\"', $message[1]) . '"));';
            }
        }
        $messageArray .= '</script>';
        $RESPONSE_RESULT = preg_replace('#<!--messages-->#', $messageArray, $RESPONSE_RESULT);

        /** Выводим html */
        echo $RESPONSE_RESULT;
        echo $_GLOBALTIMER->getTimerDiffStr();
    }
}
