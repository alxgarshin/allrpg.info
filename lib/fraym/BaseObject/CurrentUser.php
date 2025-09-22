<?php

/*
 * This file is part of the Fraym package.
 *
 * (c) Alex Garshin <alxgarshin@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Fraym\BaseObject;

use Fraym\Helper\{AuthHelper, CookieHelper, DataHelper, LocaleHelper, ResponseHelper};

final class CurrentUser
{
    /** Id пользователя */
    private ?int $id = null;

    /** Sid пользователя */
    private ?int $sid = null;

    /** Права пользователя */
    private array $allRights = [];

    /** Количество элементов на одной странице по умолчанию */
    private int $bazeCount = 50;

    /** Отключение по умолчанию галочки перенаправления на предыдущую страницу при сохранении объекта */
    private bool $blockSaveReferer = false;

    /** Отключение по умолчанию перенаправления на последнюю посещенную перед уходом с сайта страницу */
    private bool $blockAutoRedirect = false;

    /** Массив настоящих данных пользователя-администратора при переключении на другой профиль */
    private ?array $adminData = null;

    /** Создание или получение текущего пользователя в константу. По умолчанию: CURRENT_USER */
    public static function getInstance(string $constName = 'CURRENT_USER'): self
    {
        if (defined($constName)) {
            return constant($constName);
        } else {
            return self::forceCreate();
        }
    }

    /** Принудительное создание */
    public static function forceCreate(): self
    {
        return new self();
    }

    /** Проверка, запрещено ли пользователю видеть данные по профилям (своему и чужим) */
    public function blockedProfileEdit(): bool
    {
        if ($this->isAdmin()) {
            return false;
        }

        return $this->checkAllRights($_ENV['BLOCKED_PROFILE_EDIT_RIGHT']);
    }

    /** Проверка залогиненности пользователя */
    public function isLogged(): bool
    {
        return $this->id() > 0;
    }

    /** Проверка, забанен ли пользователь перманентно */
    public function isBanned(): bool
    {
        return $this->checkAllRights('banned');
    }

    /** Проверка, является ли пользователь администратором */
    public function isAdmin(bool $checkAdminDataAllRights = false): bool
    {
        return $this->checkAllRights('admin') ||
            (
                $checkAdminDataAllRights &&
                (CURRENT_USER->getAdminData()['rights'] ?? false) &&
                DataHelper::inArrayAny(['admin', '1'], CURRENT_USER->getAdminData()['rights'])
            );
    }

    /** Проверка прав */
    public function checkAllRights(string $right_id): bool
    {
        return in_array($right_id, $this->allRights);
    }

    /** Разлогинивание пользователя */
    public function authLogout(?string $byeMessage = null): void
    {
        CookieHelper::deleteAllCookies();

        if (!is_null($byeMessage)) {
            ResponseHelper::error($byeMessage);
        }

        ResponseHelper::redirect(ABSOLUTE_PATH . '/');
    }

    /** Логин пользователя */
    public function auth(): void
    {
        $LOCALE = LocaleHelper::getLocale(['fraym', 'basefunc']);

        /** Сначала мы проверяем запрос на наличие валидного JWT токена в Authorization: Bearer */
        $jwtTokenPayload = AuthHelper::getAuthTokenPayload();

        if (!is_null($jwtTokenPayload)) {
            if ($jwtTokenPayload['exp'] ?? false) {
                if ((int) $jwtTokenPayload['exp'] > time()) {
                    CURRENT_USER->authSetUserData($jwtTokenPayload);
                } else {
                    /** Токен есть, но он просроченный, возвращаем 401, чтобы приложение ушло с запросом на /login/action=refresh_token */
                    ResponseHelper::response401();
                }
            }
        } else {
            /** Если токена нет, то проверяем наличие cookie */
            $refreshToken = AuthHelper::getRefreshTokenCookie();

            if (!is_null($refreshToken)) {
                if (!REQUEST_TYPE->isDynamicRequest()) {
                    /** Если это не динамический запрос (т.е. просто загружается страница по адресу) */
                    $loginData = DB->select('user', ['refresh_token' => $refreshToken], true);

                    if ($loginData) {
                        if (($loginData['refresh_token_exp'] ?? false) && strtotime($loginData['refresh_token_exp']) > time()) {
                            CURRENT_USER->authSetUserData($loginData);
                        } else {
                            /** Cookie есть, но он просроченный, обновляем его */
                            AuthHelper::generateAndSaveRefreshToken();
                        }
                    }
                } else {
                    /** Это динамический запрос и куки есть, но токена нет, выдаем 401 */
                    ResponseHelper::response401();
                }
            }
        }

        /** Если ничего не подошло, но действие = login, то проверяем логин и пароль */
        if ('login' === ACTION && isset($_REQUEST['password'])) {
            $hashedPassword = md5($_REQUEST['password'] . $_ENV['PROJECT_HASH_WORD']);
            $loginData = DB->select(
                'user',
                [
                    'login' => $_REQUEST['login'],
                    'pass' => $hashedPassword,
                ],
                true,
            );

            if ($loginData === false) {
                ResponseHelper::responseOneBlock('error', $LOCALE['wrong_login_or_password']);
            } else {
                CURRENT_USER->authSetUserData($loginData);
                AuthHelper::generateAndSaveRefreshToken();
            }
        }

        if (CURRENT_USER->isLogged()) {
            /** Переключение администратора на другого пользователя */
            if (CURRENT_USER->isAdmin(true)) {
                $admUserRequest = $_REQUEST['adm_user'] ?? null;
                $admUser = (int) ($admUserRequest ?? CookieHelper::getCookie('admUser'));

                if ($admUser > 0) {
                    if (CURRENT_USER->id() === $admUser) {
                        CookieHelper::batchDeleteCookie(['admUser']);
                        ResponseHelper::success($LOCALE['switched_to_your_profile']);
                    } else {
                        $userData = DB->select(
                            'user',
                            [
                                'id' => $admUser,
                            ],
                            true,
                        );

                        if ($userData) {
                            CURRENT_USER->setAdminData([
                                'id' => CURRENT_USER->id(),
                                'sid' => CURRENT_USER->sid(),
                                'rights' => CURRENT_USER->getAllRights(),
                                'bazecount' => CURRENT_USER->getBazeCount(),
                                'block_save_referer' => CURRENT_USER->getBlockSaveReferer(),
                                'block_auto_redirect' => CURRENT_USER->getBlockAutoRedirect(),
                            ]);
                            CURRENT_USER->authSetUserData($userData);

                            if (!is_null($admUserRequest)) {
                                CookieHelper::batchSetCookie(['admUser' => (string) $admUser]);
                                ResponseHelper::success(sprintf($LOCALE['switched_to_other_user'], $admUser));
                            }
                        }
                    }
                }
            }

            if ('login' === ACTION) {
                $redirect_path = ResponseHelper::createRedirect();

                ResponseHelper::redirect($redirect_path ?? ABSOLUTE_PATH);
            }
        }
    }

    /** Выставляем набор данных пользователя при логине */
    public function authSetUserData(array $userData): void
    {
        CURRENT_USER->setId($userData['id'])
            ->setSid($userData['sid'])
            ->setAllRights($userData['rights'])
            ->setBazeCount($userData['bazecount'] ?? 50)
            ->setBlockSaveReferer($userData['block_save_referer'] === '1')
            ->setBlockAutoRedirect($userData['block_auto_redirect'] === '1');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function getSid(): ?int
    {
        return $this->sid;
    }

    public function setSid(?int $sid): self
    {
        $this->sid = $sid;

        return $this;
    }

    public function sid(): ?int
    {
        return $this->sid;
    }

    public function getAllRights(): array
    {
        return $this->allRights;
    }

    public function setAllRights(string|array|null $allRights): self
    {
        $allRights = is_string($allRights) ? DataHelper::multiselectToArray($allRights) : $allRights;
        $allRights = is_null($allRights) ? [] : $allRights;
        $this->allRights = $allRights;

        return $this;
    }

    public function getBazeCount(): int
    {
        return $this->bazeCount;
    }

    public function setBazeCount(int $bazeCount): self
    {
        $this->bazeCount = $bazeCount;

        return $this;
    }

    public function getBlockSaveReferer(): bool
    {
        return $this->blockSaveReferer;
    }

    public function setBlockSaveReferer(bool $blockSaveReferer): self
    {
        $this->blockSaveReferer = $blockSaveReferer;

        return $this;
    }

    public function getBlockAutoRedirect(): bool
    {
        return $this->blockAutoRedirect;
    }

    public function setBlockAutoRedirect(bool $blockAutoRedirect): self
    {
        $this->blockAutoRedirect = $blockAutoRedirect;

        return $this;
    }

    public function getAdminData(): ?array
    {
        return $this->adminData;
    }

    public function setAdminData(array $adminData): self
    {
        $this->adminData = $adminData;

        return $this;
    }
}
