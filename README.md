# ALLRPG.INFO
Портал о и для всего связанного с ролевыми играми живого действия.
На основе фреймворка Fraym.

Среда для разработки на базе Docker.

#### Требования

- Git
- Docker engine 20.x и выше 
- Docker Compose 1.28 и выше 

#### Возможности и особенности

- **PHP 8.3**
- Базы данных: **MySQL 5.7**
- Веб-сервер **Nginx** ([сервер](https://github.com/nginx-proxy/nginx-proxy), [менеджер](https://github.com/nginx-proxy/acme-companion))

## Подготовка среды разработки в Windows

Для разворачивания необходима:
- Windows 10 (билд 19043+)
- или Windows 11

1) Запустите `Windows PowerShell` в режиме администратора.

2) Для работы рекомендуется использовать дистрибутив Ubuntu 20+ в подсистеме WSL. Для его установки введите команду:
    ```shell script
    wsl --install
    ```
   Следуйте инструкциям. В частности, установите Ваш логин пользователя.
В последних релизах WSL пользователя задавать не нужно, работа будет идти под пользователем root 
Подробные инструкции по установке можно посмотреть тут: https://docs.docker.com/desktop/windows/wsl/

3) По завершению установки дистрибутива установите Linux kernel update package:
https://wslstorestorage.blob.core.windows.net/wslblob/wsl_update_x64.msi
(_если ссылка вдруг не работает, обратитесь к инструкции: https://docs.microsoft.com/windows/wsl/wsl2-kernel_)

4) `Windows PowerShell` переключитесь на wsl 2 командой:
    ```shell script
    wsl --set-default-version 2
    ```

5) Установите Docker: https://docs.docker.com/desktop/install/windows-install/
Во время установки согласитесь с использованием WSL2.

6) Для дальнейшего удобства разработки сразу перейдите в Проводнике по адресу:
    ```
    \\wsl$\
    ```
   Щелкните правой кнопкой мыши на папке Ubuntu и выберите "Подключить сетевой диск..." Это позволит Вам открывать диск
из "Мой / этот компьютер" и в целом удобно навигировать файлы Ubuntu-дистрибутива.
для некоторых сборок windows адрес может быть такой: `\\wsl.localhost\`

7) Запустите приложение Ubuntu (оно должно быть отдельным пунктом в списке Ваших программ в меню "Пуск"). Перейдите в Ваш
основной рабочий каталог:
    ```shell script
    /home/USER/dev
    ```
   Везде далее вместо `USER` ставьте логин, который Вы указали при установке Ubuntu.
   Если вы работаете под root-пользователем, соответственно рабочий каталог будет /root/dev

8) Выполните клонирование рабочего проекта. Он будет установлен в отдельный каталог **allrpg**.
    ```shell script
    git clone git@github.com:alxgarshin/allrpg.info.git
    ```

9) Перейдите в `/home/USER/dev/allrpg/` и отключите сохранение прав у файлов в репозитории:
    ```shell script
    git config --global core.filemode false
    git config core.filemode false
    ```
    Если в дальнейшем нужно будет для какого-то файла изменить права, то это делается командой: 
    ```shell script
    git update-index --chmod=+x 'script.ext'
    ```

10) В `/home/USER/dev/allrpg/` необходимо переименовать файл **.env.dev.template** в **env.dev**.

12) В `/home/USER/dev/allrpg/` запустите билд контейнеров Docker и выполнение инсталяционных скриптов командой:
    ```shell script
    bin/d install
    ```

13) Добавьте в файл `c:\Windows\System32\Drivers\etc\hosts` строки
    ```
    127.0.0.1 allrpg.loc
    127.0.0.1 www.allrpg.loc
    ```

14) Осталось подключить доверие к центру сертификата, чтобы иметь возможность работать по https. Для этого:
    * Дважды кликните на сертификате (docker/nginxproxy/dev_ssl_certs/ca.crt). 
    * Кликните на кнопку «Установить сертификат».
    * Выберите, хотите ли вы хранить его на уровне пользователя или на уровне машины.
    * Кликните «Дальше».
    * Выберите «Разместить все сертификаты в следующем хранилище».
    * Кликните «Обзор».
    * Выберите «**Доверенные корневые источники (центры) сертификатов**».
    * Проведите процесс до конца.

15) Если всё пройдет успешно, то не возникнет никаких ошибок, и Вы сможете в браузере открыть проект: https://www.allrpg.loc

16) Если были успешно применены миграции, сайт будет содержать набор тестовых данных, в том числе четырех пользователей для входа (все с паролем: *123456*)
    * admin@allrpg.info - полный админ системы;
    * master@allrpg.info - администратор тестового проекта;
    * player1@allrpg.info - игрок, подавший индивидуальную заявку;
    * player2@allrpg.info - игрок, подавший командную заявку.

## Команды управления Docker

**Все команды исполняются через Ubuntu в каталоге `/home/USER/dev/allrpg/`**

Вы также можете управлять готовыми контейнерами через установленный Docker Desktop, но это не рекомендуется.
При запуске из консоли могут задаваться дополнительные параметры, в том числе пересборка контейнеров для обновления кода.

#### Запустить все контейнеры:
```shell script
bin/d up
```

#### Показать список запущенных контейнеров
```shell script
bin/d ps
```  

Вы увидите примерно такой результат:
```
CONTAINER ID   IMAGE                 COMMAND                  CREATED          STATUS         PORTS                                      NAMES
941f31487a69   nginx:stable-alpine   "/docker-entrypoint.…"   12 seconds ago   Up 9 seconds   0.0.0.0:80->80/tcp, 0.0.0.0:443->443/tcp   allrpg-nginx
be2d51c06f60   docker_php            "docker-php-entrypoi…"   12 seconds ago   Up 9 seconds   0.0.0.0:9000->9000/tcp                     allrpg-app
2ed09a2a71cc   mysql:5.7.42          "docker-entrypoint.s…"   14 seconds ago   Up 9 seconds   5432/tcp                                   allrpg-db
```

#### Для работы с PHP
Необходимо зайти в контейнер командой:
```shell script
bin/d allrpg-app
```
После этого Вы можете использовать любые необходимые команды, например:
```shell script
bin/console make:cmsvc --cmsvc=test_set
```
Или же воспользуйтесь командой docker'а без входа в контейнер
```shell script
docker compose exec app make:cmsvc --cmsvc=test_set
```

#### Завершение работы контейнеров _на локальном компьютере_
```shell script
bin/d down
```

### Дополнительно

#### Удаление всех активных контейнеров:
```shell script
docker rm -v $(docker ps -q) # Все активные
```

#### Удаление всех неактивных контейнеров:
```shell script
docker rm -v $(docker ps -aq -f status=exited) # Все неактивные
```

### Проблемы и решения

---

`/bin/d install` выдаёт справку, список команд docker:
* Обновить docker-compose до свежей версии:  https://docs.docker.com/compose/install/

---

На Windows при запуске `composer install` выдаётся ошибка `error: Operation not permitted`
* Создать файл `/etc/wsl.conf` c содержимым:
    ```text
    [automount]
    options = "metadata"
    ```

---

Создание SSL сертификата
*   https://medium.com/nuances-of-programming/%D0%BA%D0%B0%D0%BA-%D1%81%D0%BE%D0%B7%D0%B4%D0%B0%D0%B2%D0%B0%D1%82%D1%8C-%D0%BD%D0%B0%D0%B4%D0%B5%D0%B6%D0%BD%D1%8B%D0%B5-ssl-%D1%81%D0%B5%D1%80%D1%82%D0%B8%D1%84%D0%B8%D0%BA%D0%B0%D1%82%D1%8B-%D0%B4%D0%BB%D1%8F-%D0%BB%D0%BE%D0%BA%D0%B0%D0%BB%D1%8C%D0%BD%D0%BE%D0%B9-%D1%80%D0%B0%D0%B7%D1%80%D0%B0%D0%B1%D0%BE%D1%82%D0%BA%D0%B8-8f73f76df3d4

---
