<?php

declare(strict_types=1);

namespace App\CMSVC\File;

use App\CMSVC\User\UserService;
use App\Helper\{DateHelper, FileHelper};
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Element\{Attribute, Item};
use Fraym\Enum\{EscapeModeEnum, OperandEnum};
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper, RightsHelper, TextHelper};

#[Controller(FileController::class)]
class FileService extends BaseService
{
    /** Проверка доступа к папке */
    public function checkFolderAccess(string $objType, int $objId, bool $external = false, bool $buttonedPath = false): array
    {
        $objType = DataHelper::clearBraces($objType);
        // пускать ли пользователя в просмотр папки
        $allow = false;
        // установлены ли у папки ограничительные права
        $hasRightsSet = false;
        // может ли данный пользователь редактировать настройки прав папки
        $canEdit = false;
        // id пользователей, которых нельзя лишить доступа к данной папке
        $usersWithAbsoluteAccessList = [];
        // хлебные крошки
        $path = '';

        $parentObjType = $objType;
        $parentObjId = $objId;

        while (DataHelper::addBraces($parentObjType) === '{folder}') {
            $relation = DB->select(
                tableName: 'relation',
                criteria: [
                    'obj_type_from' => '{folder}',
                    'type' => '{child}',
                    'obj_id_from' => $parentObjId,
                ],
                oneResult: true,
            );
            $folderData = DB->findObjectById($parentObjId, 'library');

            if ($buttonedPath) {
                $path = '<button class="nonimportant folder_path" obj_id="' . $folderData['id'] . '"' . ($external ? ' external="true"' : '') . '>' .
                    DataHelper::escapeOutput($folderData['path']) . '</button>' . $path;
            } else {
                $path = ' > <a class="folder_path" obj_id="' . $folderData['id'] . '"' . ($external ? ' external="true"' : '') . '>' .
                    DataHelper::escapeOutput($folderData['path']) . '</a>' . $path;
            }

            // если это моя папка, я могу менять права в ней и всех подпапках (в т.ч. просматриваемой сейчас)
            if ($folderData['creator_id'] === CURRENT_USER->id()) {
                $allow = true;
                $canEdit = true;
                $usersWithAbsoluteAccessList[] = CURRENT_USER->id();
            }

            // если есть хотя бы одно настроенное специально правило от пользователя к папке, значит, нужно проверить, есть ли у данного конкретного пользователя доступ к ней
            $result = DB->select(
                tableName: 'relation',
                criteria: [
                    'obj_type_from' => '{user}',
                    'type' => '{has_access}',
                    'obj_type_to' => '{folder}',
                    'obj_id_from' => $parentObjId,
                ],
            );
            $rightsToFolderCount = count($result);

            if ($rightsToFolderCount > 0) {
                // если еще не определен список пользователей с доступом, определяем его
                if (!isset($usersWithAccessList)) {
                    $usersWithAccessList = [];

                    foreach ($result as $userId) {
                        $usersWithAccessList[] = $userId['obj_id_from'];
                    }
                }
                // у папки и всех ее подпапок есть установленные права
                $hasRightsSet = true;

                // если я есть среди этих прав, то у меня к ней (и ее подпапкам, в т.ч. просматриваемой сейчас) есть доступ
                if (in_array(CURRENT_USER->id(), $usersWithAccessList)) {
                    $allow = true;
                }
            }

            $parentObjType = $relation['obj_type_to'];
            $parentObjId = $relation['obj_id_to'];
        }

        if (RightsHelper::checkRights('{admin}', $parentObjType, $parentObjId) || CURRENT_USER->isAdmin()) {
            // если я админ в родительском объекте, я всегда могу смотреть папку и менять ее права
            $allow = true;
            $canEdit = true;
            $usersWithAbsoluteAccessList[] = CURRENT_USER->id();
        } elseif (!$allow && RightsHelper::checkAnyRights(
            $parentObjType,
            $parentObjId,
        ) && !isset($usersWithAccessList)) {
            // если я имею доступ к родительскому объекту и при этом ограничения не были выставлены нигде, то я могу смотреть папку
            $allow = true;
        }

        if ($allow && $canEdit) {
            // общий список пользователей родительского объекта (задачи, проекта, сообщества, события)
            $usersList = RightsHelper::findByRights(
                null,
                DataHelper::addBraces($parentObjType),
                $parentObjId,
                '{user}',
                false,
            );

            if (!isset($usersWithAccessList)) {
                $usersWithAccessList = [];
            }
            $usersWithAbsoluteAccessListAdminsOnly = RightsHelper::findByRights(
                '{admin}',
                DataHelper::addBraces($parentObjType),
                $parentObjId,
                '{user}',
                false,
            );
            $usersWithAbsoluteAccessList = array_unique(
                array_merge($usersWithAbsoluteAccessList, $usersWithAbsoluteAccessListAdminsOnly),
            );
        } else {
            $usersList = [];
            $usersWithAccessList = [];
            $usersWithAbsoluteAccessList = [];
        }

        if ($allow && DataHelper::clearBraces($objType) === 'folder') {
            $parentTable = DataHelper::clearBraces($parentObjType);

            if ($parentTable === 'task' || $parentTable === 'event') {
                $parentTable = 'task_and_event';
            }
            $parentData = DB->findObjectById($parentObjId, $parentTable);

            if ($buttonedPath) {
                $path = '<button class="nonimportant folder_top" obj_type="' . DataHelper::addBraces($parentObjType) . '" obj_id="' . $parentObjId . '"' .
                    ($external ? ' external="true"' : '') . '>' . DataHelper::escapeOutput($parentData['name']) . '</button>' . $path;
            } else {
                $path = '<a class="folder_top" obj_type="' . DataHelper::addBraces($parentObjType) . '" obj_id="' . $parentObjId . '"' .
                    ($external ? ' external="true"' : '') . '>' . DataHelper::escapeOutput($parentData['name']) . '</a>' . $path;
            }
        }

        return [
            'allow' => $allow,
            'can_edit' => $canEdit,
            'users_list' => $usersList,
            'users_with_access_list' => $usersWithAccessList,
            'users_with_absolute_access_list' => $usersWithAbsoluteAccessList,
            'has_rights_set' => $hasRightsSet,
            'parent_obj_type' => $parentObjType,
            'parent_obj_id' => $parentObjId,
            'path' => $path,
        ];
    }

    /** Изменение имени файла или папки */
    public function editFileOrFolderName(int|string $objId, string $objType, string $name, string $description = ''): array
    {
        $LOCALE = $this->getLOCALE();
        $LOCALE_FOLDERS = $LOCALE['folders'];
        $LOCALE_LINKS = $LOCALE['links'];
        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);

        $returnArr = [];

        if ($name !== '' && $objType !== '' && $objId !== '') {
            $objType = DataHelper::clearBraces($objType);

            if ($objType === 'library_link') {
                $linkResultData = DB->select(
                    tableName: 'library',
                    criteria: [
                        'id' => $objId,
                    ],
                    oneResult: true,
                );

                if ($linkResultData['creator_id'] === CURRENT_USER->id()) {
                    preg_match('#{external:([^:]+):([^}]+)}#', $linkResultData['path'], $matches);
                    DB->update(
                        tableName: 'library',
                        data: [
                            'path' => '{external:' . $name . ':' . $matches[2] . '}',
                            'description' => $description,
                        ],
                        criteria: [
                            'id' => $objId,
                        ],
                    );
                    $returnArr = [
                        'response' => 'success',
                        'response_text' => $LOCALE_LINKS['messages']['edit_link_success'],
                    ];
                }
            } elseif ($objType === 'library_folder') {
                $checkData = $this->checkFolderAccess('{folder}', $objId);

                if ($checkData['allow']) {
                    $folderResultData = DB->select(
                        tableName: 'library',
                        criteria: [
                            'id' => $objId,
                        ],
                        oneResult: true,
                    );

                    if ($folderResultData['creator_id'] === CURRENT_USER->id()) {
                        DB->update(
                            tableName: 'library',
                            data: [
                                'path' => $name,
                                'description' => $description,
                            ],
                            criteria: [
                                'id' => $objId,
                            ],
                        );

                        $returnArr = [
                            'response' => 'success',
                            'response_text' => $LOCALE_FOLDERS['messages']['edit_folder_success'],
                        ];
                    }
                }
            } elseif ($objType === 'library_file') {
                $libraryFileData = DB->select(
                    'library',
                    [
                        'path' => DataHelper::addBraces((string) $objId),
                        'creator_id' => CURRENT_USER->id(),
                    ],
                    true,
                );

                if ($libraryFileData) {
                    preg_match('#{([^:]+):([^}]+)}#', $libraryFileData['path'], $matches);
                    DB->update(
                        tableName: 'library',
                        data: [
                            'path' => '{' . $name . ':' . $matches[2] . '}',
                            'description' => $description,
                        ],
                        criteria: [
                            'id' => $libraryFileData['id'],
                        ],
                    );
                    $returnArr = [
                        'response' => 'success',
                        'response_text' => $LOCALE_FRAYM['file']['messages']['file_edit_success'],
                    ];
                }
            } elseif ($objType === 'conversation_file') {
                $conversationFileData = DB->select(
                    tableName: 'conversation_message',
                    criteria: [
                        'creator_id' => CURRENT_USER->id(),
                        ['attachments', '%' . DataHelper::addBraces((string) $objId) . '%', [OperandEnum::LIKE]],
                    ],
                    oneResult: true,
                );

                if ($conversationFileData['id'] !== '') {
                    preg_match('#([^:]+):(.+)#', (string) $objId, $matches);
                    $newAttachments = preg_replace(
                        '#\{' . $objId . '}#',
                        '{' . $name . ':' . $matches[2] . '}',
                        $conversationFileData['attachments'],
                    );
                    DB->update(
                        tableName: 'conversation_message',
                        data: [
                            'attachments' => $newAttachments,
                        ],
                        criteria: [
                            'id' => $conversationFileData['id'],
                        ],
                    );
                    $returnArr = [
                        'response' => 'success',
                        'response_text' => $LOCALE_FRAYM['file']['messages']['file_edit_success'],
                    ];
                }
            }
        }

        return $returnArr;
    }

    /** Добавление файла в библиотеку */
    public function newLibraryFile(int $objId, string $objType, string $name, string $nameShown): array
    {
        $LOCALE = LocaleHelper::getLocale(['fraym', 'file']);

        $returnArr = [];

        if ($name !== '' && $nameShown !== '') {
            $checkData = $this->checkFolderAccess($objType, $objId);

            if ($checkData['allow']) {
                DB->insert(
                    tableName: 'library',
                    data: [
                        'creator_id' => CURRENT_USER->id(),
                        'path' => '{' . $nameShown . ':' . $name . '}',
                        'updated_at' => DateHelper::getNow(),
                        'created_at' => DateHelper::getNow(),
                    ],
                );

                $fileId = DB->lastInsertId();
                RightsHelper::addRights('{child}', $objType, $objId, '{file}', $fileId);

                $returnArr = [
                    'response' => 'success',
                    'response_text' => $LOCALE['messages']['file_add_success'],
                ];
            } else {
                $returnArr = [
                    'response' => 'error',
                    'response_code' => 'file_add_fail',
                    'response_text' => $LOCALE['messages']['file_add_fail'],
                ];
            }
        }

        return $returnArr;
    }

    /** Удаление файла из библиотеки файлов */
    public function deleteLibraryFile(int|string $objId): array
    {
        $LOCALE = LocaleHelper::getLocale(['fraym']);

        $libraryFileData = DB->select(
            'library',
            [
                ['path', DataHelper::addBraces((string) $objId)],
                ['creator_id', CURRENT_USER->id()],
            ],
            true,
        );

        if ($libraryFileData['id'] !== '') {
            DB->delete('library', [
                ['id', $libraryFileData['id']],
            ]);
            RightsHelper::deleteRights('{child}', null, null, '{file}', $libraryFileData['id']);

            return [
                'response' => 'success',
                'response_text' => $LOCALE['file']['messages']['file_remove_success'],
            ];
        }

        return [
            'response' => 'error',
            'response_code' => 'file_remove_fail',
            'response_text' => $LOCALE['file']['messages']['file_remove_fail'],
        ];
    }

    /** Удаление файла из диалога */
    public function deleteConversationFile(int|string $objId): array
    {
        $LOCALE = LocaleHelper::getLocale(['fraym']);

        $result = DB->select(
            tableName: 'conversation_message',
            criteria: [
                'creator_id' => CURRENT_USER->id(),
                ['attachments', '%' . DataHelper::addBraces((string) $objId) . '%', [OperandEnum::LIKE]],
            ],
        );
        $conversationFileData = $result[0];

        if ($conversationFileData['id'] !== '') {
            $newAttachments = preg_replace('#\{' . $objId . '}#', '', $conversationFileData['attachments']);
            DB->update(
                tableName: 'conversation_message',
                data: [
                    'attachments' => $newAttachments,
                ],
                criteria: [
                    'id' => $conversationFileData['id'],
                ],
            );

            return [
                'response' => 'success',
                'response_text' => $LOCALE['file']['messages']['file_remove_success'],
            ];
        }

        return [
            'response' => 'error',
            'response_code' => 'file_remove_fail',
            'response_text' => $LOCALE['file']['messages']['file_remove_fail'],
        ];
    }

    /** Добавление папки */
    public function createFolder(int $objId, string $objType, string $name): array
    {
        $LOCALE_FOLDERS = $this->getLOCALE()['folders'];

        $returnArr = [];

        if ($name !== '') {
            $checkData = $this->checkFolderAccess($objType, $objId);

            if ($checkData['allow']) {
                DB->insert(
                    tableName: 'library',
                    data: [
                        'creator_id' => CURRENT_USER->id(),
                        'path' => $name,
                        'created_at' => DateHelper::getNow(),
                        'updated_at' => DateHelper::getNow(),
                    ],
                );
                $linkId = DB->lastInsertId();
                RightsHelper::addRights('{child}', $objType, $objId, '{folder}', $linkId);
                $returnArr = [
                    'response' => 'success',
                    'response_text' => $LOCALE_FOLDERS['messages']['add_folder_success'],
                    'id' => $linkId,
                ];
            }
        }

        return $returnArr;
    }

    /** Удаление папки из диска */
    public function deleteFolder(int $objId): array
    {
        $LOCALE_FOLDERS = $this->getLOCALE()['folders'];

        $returnArr = [];

        $checkData = $this->checkFolderAccess('{folder}', $objId);

        if ($checkData['allow']) {
            $uploadType = match (DataHelper::clearBraces($checkData['parent_obj_type'])) {
                'task', 'event' => 3,
                'project' => 4,
                'community' => 5,
                default => 1,
            };

            $this->removeChildFolder($objId, $uploadType);

            $returnArr = [
                'response' => 'success',
                'response_text' => $LOCALE_FOLDERS['messages']['remove_folder_success'],
            ];
        }

        return $returnArr;
    }

    /** Удаление подпапок и их содержимого */
    public function removeChildFolder(int $objId, int $uploadType): void
    {
        $childFolders = RightsHelper::findByRights('{child}', '{folder}', $objId, '{folder}', false);

        if ($childFolders) {
            foreach ($childFolders as $value) {
                $this->removeChildFolder($value, $uploadType);
            }
        }

        $childFiles = RightsHelper::findByRights('{child}', '{folder}', $objId, '{file}', false);

        if ($childFiles) {
            foreach ($childFiles as $value) {
                $fileData = DB->findObjectById($value, 'library');
                preg_match_all('#{([^:]+):([^}:]+)}#', $fileData['path'], $matches);

                foreach ($matches[0] as $key => $value2) {
                    if ($matches[1][$key] !== 'external') {
                        unlink(INNER_PATH . $_ENV['UPLOADS'][$uploadType]['path'] . basename($matches[2][$key]));
                    }
                }

                RightsHelper::deleteRights('{child}', null, null, '{file}', $value);
                DB->delete(
                    tableName: 'library',
                    criteria: [
                        'id' => $value,
                    ],
                );
            }
        }

        RightsHelper::deleteRights('{child}', null, null, '{folder}', $objId);
        RightsHelper::deleteRights('{has_access}', '{folder}', $objId, '{user}', 0);
        DB->delete(
            tableName: 'library',
            criteria: [
                'id' => $objId,
            ],
        );
    }

    /** Добавление ссылки */
    public function addLink(int $objId, string $objType, string $name, string $link): array
    {
        $LOCALE_LINKS = $this->getLOCALE()['links'];

        $returnArr = [];

        if ($link !== '' && $name !== '') {
            $checkData = $this->checkFolderAccess($objType, $objId);

            if ($checkData['allow']) {
                DB->insert(
                    tableName: 'library',
                    data: [
                        'creator_id' => CURRENT_USER->id(),
                        'path' => '{external:' . $name . ':' . $link . '}',
                        'created_at' => DateHelper::getNow(),
                        'updated_at' => DateHelper::getNow(),
                    ],
                );

                $linkId = DB->lastInsertId();
                RightsHelper::addRights('{child}', $objType, $objId, '{file}', $linkId);

                $returnArr = [
                    'response' => 'success',
                    'response_text' => $LOCALE_LINKS['messages']['add_link_success'],
                    'id' => $linkId,
                ];
            }
        }

        return $returnArr;
    }

    /** Удаление ссылки из диска */
    public function deleteLink(int $objId): array
    {
        $LOCALE_LINKS = $this->getLOCALE()['links'];

        $returnArr = [];

        $linkResultData = DB->select(
            tableName: 'library',
            criteria: [
                'id' => $objId,
            ],
            oneResult: true,
        );

        if ($linkResultData['creator_id'] === CURRENT_USER->id()) {
            DB->delete(
                tableName: 'library',
                criteria: [
                    'id' => $objId,
                ],
            );

            RightsHelper::deleteRights('{child}', null, null, '{file}', $objId);

            $returnArr = [
                'response' => 'success',
                'response_text' => $LOCALE_LINKS['messages']['remove_link_success'],
            ];
        }

        return $returnArr;
    }

    /** Загрузка списка папок и файлов в разделе диска */
    public function loadDisk(int|string $objId, string $objType, string $subObjType = ''): array
    {
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);
        $LOCALE_EVENTLIST = LocaleHelper::getLocale(['eventlist', 'global']);
        $LOCALE_TASKLIST = LocaleHelper::getLocale(['tasklist', 'global']);
        $LOCALE_SEARCH = LocaleHelper::getLocale(['search', 'global']);
        $LOCALE_DISK = $LOCALE_GLOBAL['disk'];

        $returnArr = [];

        $subObjType = DataHelper::clearBraces($subObjType);

        $actionSuccess = false;
        $responseData = [
            'folder' => [],
            'file' => [],
            'sub_obj' => [],
            'hash' => '',
            'path' => '',
        ];

        if (DataHelper::clearBraces($objType) === 'full_search') {
            $responseData['hash'] = 'obj_type:' . DataHelper::clearBraces($objType) . ':obj_id:' . $objId;
            $responseData['path'] = '<button class="nonimportant folder_top" obj_type="' . DataHelper::addBraces($objType) .
                '" obj_id="' . $objId . '" external="true">' . $LOCALE_SEARCH['title'] . '</button>';

            if (mb_strlen((string) $objId) >= 3) {
                $searchResults = [];

                // определяем все объекты, к которым подключен пользователь
                $projects = RightsHelper::findByRights(null, '{project}');
                $communities = RightsHelper::findByRights(null, '{community}');
                $tasks = RightsHelper::findByRights(null, '{task}');
                $events = RightsHelper::findByRights(null, '{event}');

                if (!$tasks) {
                    $tasks = [];
                }

                if (!$events) {
                    $events = [];
                }

                // находим все задачи и события, к которым приложен файл с подходящим названием
                $result = DB->select(
                    tableName: 'task_and_event',
                    criteria: [
                        ['attachments', '%' . $objId . '%', [OperandEnum::LIKE]],
                    ],
                );

                foreach ($result as $objectData) {
                    if (in_array($objectData['id'], $tasks) || in_array($objectData['id'], $events)) {
                        preg_match_all('#{([^:]+):([^}:]+)}#', $objectData['attachments'], $matches);

                        foreach ($matches[0] as $key => $value) {
                            if (mb_stripos(DataHelper::escapeOutput($matches[1][$key], EscapeModeEnum::plainHTML), $objId) !== false) {
                                $searchResults[] = [
                                    'type' => '{file}',
                                    'subtype' => '{file}',
                                    'id' => '0',
                                    'link' => ABSOLUTE_PATH . '/' . $_ENV['UPLOADS'][FileHelper::getUploadNumByType('{task}')]['path'] . $matches[2][$key],
                                    'name' => DataHelper::escapeOutput($matches[1][$key], EscapeModeEnum::plainHTML),
                                    'editable' => $objectData['creator_id'] === CURRENT_USER->id(),
                                    'preview' => FileHelper::checkForPreview($matches[2][$key]),
                                    'avatar' => (FileHelper::getFileTypeByExtension($matches[2][$key]) === 'image' ?
                                        ABSOLUTE_PATH . '/thumbnails/' . $_ENV['UPLOADS'][FileHelper::getUploadNumByType('{task}')]['path'] . $matches[2][$key] :
                                        ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_file.png'),
                                    'created_at' => date('d.m.Y H:i', $objectData['created_at']),
                                    'size' => FileHelper::getFileSize(
                                        INNER_PATH . $_ENV['UPLOADS'][FileHelper::getUploadNumByType('{task}')]['path'] . $matches[2][$key],
                                    ),
                                ];
                            }
                        }
                    }
                }

                // находим все файлы, ссылки и папки с подходящим названием
                $result = DB->query(
                    "SELECT l.*, r.obj_type_from, r.obj_type_to, r.obj_id_to, r.obj_id_from FROM library l LEFT JOIN relation r ON r.obj_id_from=l.id AND r.type='{child}' AND (r.obj_type_from='{file}' OR r.obj_type_from='{folder}' OR r.obj_type_from='{link}') WHERE r.obj_id_to IS NOT NULL AND l.path LIKE :path",
                    [
                        ['path', '%' . $objId . '%'],
                    ],
                );

                foreach ($result as $objectData) {
                    $allowToSee = false;

                    if ($objectData['obj_type_to'] === '{folder}') {
                        // если это подпапка или файл в подпапке
                        $checkData = $this->checkFolderAccess($objectData['obj_type_to'], $objectData['obj_id_to'], true, true);

                        if ($checkData['allow']) {
                            $allowToSee = true;
                        }
                    } elseif (
                        ($objectData['obj_type_to'] === '{task}' && in_array($objectData['obj_id_to'], $tasks))
                        || ($objectData['obj_type_to'] === '{event}' && in_array($objectData['obj_id_to'], $events))
                        || ($objectData['obj_type_to'] === '{project}' && in_array($objectData['obj_id_to'], $projects))
                        || ($objectData['obj_type_to'] === '{community}' && in_array($objectData['obj_id_to'], $communities))
                    ) {
                        $allowToSee = true;
                    }

                    if ($allowToSee) {
                        if ($objectData['obj_type_from'] === '{folder}') {
                            if (mb_stripos(DataHelper::escapeOutput($objectData['path']), $objId) !== false) {
                                $searchResults[] = [
                                    'type' => '{folder}',
                                    'subtype' => '{folder}',
                                    'id' => $objectData['id'],
                                    'link' => ABSOLUTE_PATH . DataHelper::clearBraces($objectData['obj_type_to']) . '/' . $objectData['obj_id_to'] . '/',
                                    'name' => DataHelper::escapeOutput($objectData['path']),
                                    'editable' => $objectData['creator_id'] === CURRENT_USER->id(),
                                    'preview' => false,
                                    'avatar' => ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_folder.png',
                                    'created_at' => date('d.m.Y H:i', $objectData['created_at']),
                                ];
                            }
                        } else {
                            preg_match_all('#{([^:]+):([^}:]+)}#', $objectData['path'], $matches);

                            foreach ($matches[0] as $key => $value) {
                                if (mb_stripos(DataHelper::escapeOutput($matches[1][$key], EscapeModeEnum::plainHTML), $objId) !== false) {
                                    $searchResults[] = [
                                        'type' => '{file}',
                                        'subtype' => '{file}',
                                        'id' => $objectData['id'],
                                        'link' => ABSOLUTE_PATH . '/' . $_ENV['UPLOADS'][FileHelper::getUploadNumByType($objectData['obj_type_to'])]['path'] .
                                            $matches[2][$key],
                                        'name' => DataHelper::escapeOutput($matches[1][$key], EscapeModeEnum::plainHTML),
                                        'editable' => $objectData['creator_id'] === CURRENT_USER->id(),
                                        'preview' => FileHelper::checkForPreview($matches[2][$key]),
                                        'avatar' => (FileHelper::getFileTypeByExtension($matches[2][$key]) === 'image' ?
                                            ABSOLUTE_PATH . '/thumbnails/' . $_ENV['UPLOADS'][FileHelper::getUploadNumByType($objectData['obj_type_to'])]['path'] .
                                            $matches[2][$key] :
                                            ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_file.png'),
                                        'created_at' => date('d.m.Y H:i', $objectData['created_at']),
                                        'size' => FileHelper::getFileSize(
                                            INNER_PATH . $_ENV['UPLOADS'][FileHelper::getUploadNumByType($objectData['obj_type_to'])]['path'] .
                                                $matches[2][$key],
                                        ),
                                    ];
                                }
                            }

                            preg_match_all('#{external:([^:]+):([^}]+)}#', $objectData['path'], $matches);

                            foreach ($matches[0] as $key => $value) {
                                if (mb_stripos(DataHelper::escapeOutput($matches[1][$key], EscapeModeEnum::plainHTML), $objId) !== false) {
                                    $searchResults[] = [
                                        'type' => '{file}',
                                        'subtype' => '{link}',
                                        'id' => $objectData['id'],
                                        'link' => $matches[2][$key],
                                        'name' => DataHelper::escapeOutput($matches[1][$key], EscapeModeEnum::plainHTML),
                                        'editable' => $objectData['creator_id'] === CURRENT_USER->id(),
                                        'preview' => false,
                                        'avatar' => ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_link.png',
                                        'created_at' => date('d.m.Y H:i', $objectData['created_at']),
                                    ];
                                }
                            }
                        }
                    }
                }

                foreach ($searchResults as $value) {
                    $responseData[DataHelper::clearBraces($value['type'])][] = [
                        'type' => DataHelper::addBraces($value['subtype']),
                        'subtype' => DataHelper::addBraces($value['subtype']),
                        'id' => $value['id'],
                        'link' => $value['link'],
                        'name' => $value['name'],
                        'editable' => $value['editable'],
                        'preview' => $value['preview'],
                        'avatar' => ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_' . DataHelper::clearBraces($value['subtype']) . '.png',
                        'created_at' => $value['created_at'],
                        'size' => $value['size'],
                    ];
                }

                // если нашли хоть что-нибудь, то $actionSuccess=true
                if (count($searchResults) > 0) {
                    $actionSuccess = true;
                }
            } else {
                $returnArr = [
                    'response' => 'error',
                    'response_text' => TextHelper::mb_ucfirst($LOCALE_GLOBAL['nothing_found']),
                ];
            }
        } elseif (DataHelper::clearBraces($objType) === 'top_level') {
            $foldersData = [];

            $projects = RightsHelper::findByRights(null, '{project}');
            $communities = RightsHelper::findByRights(null, '{community}');

            if ($projects) {
                $foldersData = DB->findObjectsByIds($projects, 'project');

                foreach ($foldersData as $key => $objectData) {
                    $foldersData[$key]['type'] = 'project';
                }
            }

            if ($communities) {
                $communitiesData = DB->findObjectsByIds($communities, 'community');

                foreach ($communitiesData as $key => $objectData) {
                    $communitiesData[$key]['type'] = 'community';
                }
                $foldersData = array_merge($foldersData, $communitiesData);
            }

            // ищем личные задачи, не привязанные ни к чему
            $result = DB->query(
                "SELECT DISTINCT te.* FROM task_and_event te LEFT JOIN relation r ON te.id=r.obj_id_from AND r.obj_type_to='{project}' AND r.obj_type_from='{task}' AND r.type='{child}' LEFT JOIN relation r2 ON te.id=r2.obj_id_to AND r2.obj_type_from='{user}' AND r2.obj_type_to='{task}' AND r2.obj_id_from=:obj_id_from LEFT JOIN relation r3 ON te.id=r3.obj_id_to AND (r3.obj_type_from='{file}' OR r3.obj_type_from='{folder}' OR r3.obj_type_from='{link}') AND r3.obj_type_to='{task}' WHERE r.obj_id_to IS NULL AND (r2.type='{member}' OR te.creator_id=:creator_id) AND (r3.obj_id_from IS NOT NULL OR te.attachments!='')",
                [
                    ['obj_id_from', CURRENT_USER->id()],
                    ['creator_id', CURRENT_USER->id()],
                ],
            );

            foreach ($result as $objectData) {
                $foldersData[] = array_merge($objectData, ['type' => 'task']);
            }

            foreach ($foldersData as $folderData) {
                if ($folderData['id'] !== '') {
                    $responseData['folder'][] = [
                        'type' => DataHelper::addBraces($folderData['type']),
                        'id' => $folderData['id'],
                        'link' => ABSOLUTE_PATH . '/' . $folderData['type'] . '/' . $folderData['id'] . '/',
                        'name' => DataHelper::escapeOutput($folderData['name']),
                        'editable' => $folderData['creator_id'] === CURRENT_USER->id(),
                        'preview' => false,
                        'avatar' => (
                            FileHelper::getImagePath($folderData['attachments'], 9) ??
                            ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_' . $folderData['type'] . '.png'
                        ),
                        'created_at' => date('d.m.Y H:i', $folderData['created_at']),
                    ];
                }
            }

            $actionSuccess = true;
        } else {
            $checkData = $this->checkFolderAccess($objType, $objId, true, true);

            if ($checkData['allow']) {
                $checkData['parent_obj_type'] = DataHelper::clearBraces($checkData['parent_obj_type']);
                $uploadType = FileHelper::getUploadNumByType($checkData['parent_obj_type']);

                // если это папка, определяем спектр возможностей пользователя по работе с ней
                if ($checkData['can_edit']) {
                    $userIds = [];
                    $userIdsSort = [];

                    /** @var UserService $userService */
                    $userService = CMSVCHelper::getService('user');

                    foreach ($checkData['users_list'] as $value) {
                        $userData = $userService->get($value);

                        if ($userData->id->getAsInt()) {
                            $image = $userService->photoUrl($userData);
                            $userIds[$userData->id->getAsInt()] = [
                                'user_id' => $userData->id->getAsInt(),
                                'fio' => $userService->showNameExtended($userData, true),
                                'photo' => $image,
                            ];
                            $userIdsSort[] = $userService->showNameExtended($userData, true);
                        }
                    }
                    array_multisort($userIdsSort, SORT_ASC, $userIds);

                    $responseData['commands'] = [
                        'user_list' => $userIds,
                        'locked' => $checkData['users_with_absolute_access_list'],
                        'selected' => (count($checkData['users_with_access_list']) > 0 ?
                            array_unique(array_merge($checkData['users_with_access_list'], $checkData['users_with_absolute_access_list'])) :
                            $checkData['users_list']),
                    ];
                }
                $responseData['commands']['edit'] = ($checkData['can_edit'] ? 'true' : 'false');
                $responseData['commands']['delete'] = ($checkData['can_edit'] ? 'true' : 'false');

                $parentTable = DataHelper::clearBraces($objType);

                if ($parentTable === 'task' || $parentTable === 'event') {
                    $parentTable = 'task_and_event';
                } elseif ($parentTable === 'folder') {
                    $parentTable = 'library';
                }
                $parentData = DB->findObjectById($objId, $parentTable);

                if ($checkData['path'] === '') {
                    $checkData['path'] = '<button class="nonimportant folder_top" obj_type="' . DataHelper::addBraces($objType) . '" obj_id="' . $objId .
                        '" external="true">' . DataHelper::escapeOutput($parentData['name']) . '</button>';
                }
                $responseData['path'] = $checkData['path'];

                if ($subObjType === 'tasks') {
                    $responseData['path'] .= '<button class="nonimportant folder_top" obj_type="' . DataHelper::addBraces($objType) . '" obj_id="' . $objId .
                        '" sub_obj_type="tasks" external="true">' . $LOCALE_TASKLIST['title'] . '</button>';
                } elseif ($subObjType === 'events') {
                    $responseData['path'] .= '<button class="nonimportant folder_top" obj_type="' . DataHelper::addBraces($objType) . '" obj_id="' . $objId .
                        '" sub_obj_type="events" external="true">' . $LOCALE_EVENTLIST['title'] . '</button>';
                }

                if ($subObjType === 'tasks' || $subObjType === 'events') {
                    $subObjType = $subObjType === 'tasks' ? 'task' : 'event';

                    $result = DB->query(
                        "SELECT DISTINCT te.* FROM task_and_event te LEFT JOIN relation r ON te.id=r.obj_id_from WHERE r.obj_type_from=:obj_type_from AND r.type='{child}' AND r.obj_type_to=:obj_type_to AND ((r.obj_id_to=:obj_id_to AND te.attachments!='') OR (r.obj_id_from IN (SELECT obj_id_to FROM relation WHERE obj_type_from='{file}' AND type='{child}' AND obj_type_to=:sub_obj_type_to AND obj_id_to IN (SELECT obj_id_from FROM relation WHERE obj_type_from=:sub_obj_type_from AND type='{child}' AND obj_type_to=:obj_type_to AND obj_id_to=:obj_id_to)))) ORDER BY te.name",
                        [
                            ['obj_type_from', DataHelper::addBraces($subObjType)],
                            ['obj_type_to', DataHelper::addBraces($objType)],
                            ['obj_id_to', $objId],
                            ['sub_obj_type_to', DataHelper::addBraces($subObjType)],
                            ['sub_obj_type_from', DataHelper::addBraces($subObjType)],
                        ],
                    );

                    foreach ($result as $objectData) {
                        if (RightsHelper::checkAnyRights(DataHelper::addBraces($subObjType), $objectData['id'])) {
                            $responseData['folder'][] = [
                                'type' => DataHelper::addBraces($subObjType),
                                'id' => $objectData['id'],
                                'link' => ABSOLUTE_PATH . '/' . DataHelper::clearBraces($subObjType) . '/' . $objectData['id'] . '/',
                                'name' => DataHelper::escapeOutput($objectData['name']),
                                'editable' => $objectData['creator_id'] === CURRENT_USER->id(),
                                'preview' => false,
                                'avatar' => ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_' . DataHelper::clearBraces($subObjType) . '.png',
                                'created_at' => date('d.m.Y H:i', $objectData['created_at']),
                            ];
                        }
                    }

                    $responseData['hash'] = 'obj_type:' . DataHelper::clearBraces($objType) . ':obj_id:' . $objId . ':sub_obj_type:' . $subObjType . 's';
                } else {
                    $result = DB->query(
                        "SELECT l.*, r.obj_type_from AS file_type FROM library l LEFT JOIN relation r ON l.id=r.obj_id_from WHERE r.obj_type_from='{folder}' AND r.type='{child}' AND r.obj_type_to=:obj_type_to AND r.obj_id_to=:obj_id_to ORDER BY l.path",
                        [
                            ['obj_type_to', DataHelper::addBraces($objType)],
                            ['obj_id_to', $objId],
                        ],
                    );

                    foreach ($result as $libraryFileData) {
                        $responseData['folder'][] = [
                            'type' => DataHelper::addBraces($libraryFileData['file_type']),
                            'subtype' => DataHelper::addBraces($libraryFileData['file_type']),
                            'id' => $libraryFileData['id'],
                            'users_with_access_list' => $checkData['users_with_access_list'],
                            'link' => ABSOLUTE_PATH . '/' . DataHelper::clearBraces($objType) . '/' . $objId . '/',
                            'name' => DataHelper::escapeOutput($libraryFileData['path']),
                            'editable' => $libraryFileData['creator_id'] === CURRENT_USER->id(),
                            'preview' => false,
                            'avatar' => ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_' . DataHelper::clearBraces($libraryFileData['file_type']) . '.png',
                            'created_at' => date('d.m.Y H:i', $libraryFileData['created_at']),
                        ];
                    }

                    $result = DB->query(
                        "SELECT l.*, r.obj_type_from AS file_type FROM library l LEFT JOIN relation r ON l.id=r.obj_id_from WHERE (r.obj_type_from='{file}' OR r.obj_type_from='{link}') AND r.type='{child}' AND r.obj_type_to=:obj_type_to AND r.obj_id_to=:obj_id_to ORDER BY l.created_at DESC",
                        [
                            ['obj_type_to', DataHelper::addBraces($objType)],
                            ['obj_id_to', $objId],
                        ],
                    );

                    foreach ($result as $libraryFileData) {
                        preg_match_all('#{external:([^:]+):([^}]+)}#', $libraryFileData['path'], $matches);

                        foreach ($matches[0] as $key => $value) {
                            $responseData['file'][] = [
                                'type' => DataHelper::addBraces($libraryFileData['file_type']),
                                'subtype' => '{link}',
                                'id' => $libraryFileData['id'],
                                'link' => $matches[2][$key],
                                'name' => DataHelper::escapeOutput($matches[1][$key], EscapeModeEnum::plainHTML),
                                'editable' => $libraryFileData['creator_id'] === CURRENT_USER->id(),
                                'preview' => false,
                                'avatar' => ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_link.png',
                                'created_at' => date('d.m.Y H:i', $libraryFileData['created_at']),
                            ];
                        }

                        preg_match_all('#{([^:]+):([^}:]+)}#', $libraryFileData['path'], $matches);

                        foreach ($matches[0] as $key => $value) {
                            $responseData['file'][] = [
                                'type' => DataHelper::addBraces($libraryFileData['file_type']),
                                'subtype' => '{file}',
                                'id' => $libraryFileData['id'],
                                'link' => ABSOLUTE_PATH . '/' . $_ENV['UPLOADS'][$uploadType]['path'] . $matches[2][$key],
                                'name' => DataHelper::escapeOutput($matches[1][$key], EscapeModeEnum::plainHTML),
                                'editable' => $libraryFileData['creator_id'] === CURRENT_USER->id(),
                                'preview' => FileHelper::checkForPreview($matches[2][$key]),
                                'avatar' => (FileHelper::getFileTypeByExtension($matches[2][$key]) === 'image' ?
                                    ABSOLUTE_PATH . '/thumbnails/' . $_ENV['UPLOADS'][$uploadType]['path'] . $matches[2][$key] :
                                    ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_file.png'),
                                'created_at' => date('d.m.Y H:i', $libraryFileData['created_at']),
                                'size' => FileHelper::getFileSize(INNER_PATH . $_ENV['UPLOADS'][$uploadType]['path'] . $matches[2][$key]),
                            ];
                        }
                    }

                    if (DataHelper::clearBraces($objType) === 'project') {
                        $responseData['sub_obj'][] = [
                            'type' => 'tasks',
                            'name' => $LOCALE_TASKLIST['title'],
                            'avatar' => ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_task.svg',
                            'created_at' => date('d.m.Y H:i', $parentData['created_at']),
                        ];
                    }

                    if (DataHelper::clearBraces($objType) === 'project' || DataHelper::clearBraces($objType) === 'community') {
                        $responseData['sub_obj'][] = [
                            'type' => 'events',
                            'name' => $LOCALE_EVENTLIST['title'],
                            'avatar' => ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_event.svg',
                            'created_at' => date('d.m.Y H:i', $parentData['created_at']),
                        ];
                    }

                    $responseData['hash'] = 'obj_type:' . DataHelper::clearBraces($objType) . ':obj_id:' . $objId;

                    if (DataHelper::inArrayAny([DataHelper::clearBraces($objType), $checkData['parent_obj_type']], ['task', 'event'])) {
                        if (in_array(DataHelper::clearBraces($objType), ['task', 'event'])) {
                            $objectData = DB->findObjectById($objId, 'task_and_event');

                            if ($objectData['attachments'] !== '') {
                                preg_match_all('#{([^:]+):([^}:]+)}#', $objectData['attachments'], $matches);

                                foreach ($matches[0] as $key => $value) {
                                    $responseData['file'][] = [
                                        'type' => '{file}',
                                        'subtype' => '{file}',
                                        'id' => '0',
                                        'link' => ABSOLUTE_PATH . '/' . $_ENV['UPLOADS'][$uploadType]['path'] . $matches[2][$key],
                                        'name' => DataHelper::escapeOutput($matches[1][$key], EscapeModeEnum::plainHTML),
                                        'editable' => $objectData['creator_id'] === CURRENT_USER->id(),
                                        'preview' => FileHelper::checkForPreview($matches[2][$key]),
                                        'avatar' => (FileHelper::getFileTypeByExtension($matches[2][$key]) === 'image' ?
                                            ABSOLUTE_PATH . '/thumbnails/' . $_ENV['UPLOADS'][$uploadType]['path'] . $matches[2][$key] :
                                            ABSOLUTE_PATH . $_ENV['DESIGN_PATH'] . 'no_avatar_file.png'),
                                        'created_at' => date('d.m.Y H:i', $objectData['created_at']),
                                        'size' => FileHelper::getFileSize(INNER_PATH . $_ENV['UPLOADS'][$uploadType]['path'] . $matches[2][$key]),
                                    ];
                                }
                            }
                        }

                        if (in_array($checkData['parent_obj_type'], ['task', 'event'])) {
                            $objType = DataHelper::addBraces($checkData['parent_obj_type']);
                            $objId = $checkData['parent_obj_id'];
                        }

                        $parentObjType = '';
                        $parentObjId = '';
                        $checkProject = RightsHelper::findOneByRights(
                            '{child}',
                            '{project}',
                            null,
                            DataHelper::addBraces($objType),
                            $objId,
                        );

                        if ($checkProject) {
                            $parentObjType = 'project';
                            $parentObjId = $checkProject;
                        } else {
                            $checkCommunity = RightsHelper::findOneByRights(
                                '{child}',
                                '{community}',
                                null,
                                DataHelper::addBraces($objType),
                                $objId,
                            );

                            if ($checkCommunity) {
                                $parentObjType = 'community';
                                $parentObjId = $checkCommunity;
                            }
                        }

                        if ($parentObjType !== '') {
                            $parentObjName = DB->findObjectById($parentObjId, $parentObjType);
                            $prePath = '<button class="nonimportant folder_top" obj_type="' . DataHelper::addBraces($parentObjType) .
                                '" obj_id="' . $parentObjId . '" external="true">' . DataHelper::escapeOutput($parentObjName['name']) .
                                '</button><button class="nonimportant folder_top" obj_type="' . DataHelper::addBraces($parentObjType) .
                                '" obj_id="' . $parentObjId . '" sub_obj_type="' . DataHelper::clearBraces($objType) . 's" external="true">' .
                                (DataHelper::addBraces($objType) === '{tasklist}' ? $LOCALE_TASKLIST['title'] : $LOCALE_EVENTLIST['title']) . '</button>';
                            $responseData['path'] = $prePath . $responseData['path'];
                        }
                    }
                }
            }

            $actionSuccess = true;
        }

        if ($actionSuccess) {
            $foldersDataSort = [];
            $filesDataSort = [];
            $subObjDataSort = [];

            if (count($responseData['folder']) > 0) {
                foreach ($responseData['folder'] as $key => $folderData) {
                    $foldersDataSort[$key] = mb_strtolower(str_replace(['"', '«'], '', DataHelper::escapeOutput($folderData['name'])));
                }
                array_multisort($foldersDataSort, SORT_ASC, $responseData['folder']);
            }

            if (count($responseData['file']) > 0) {
                foreach ($responseData['file'] as $key => $fileData) {
                    $filesDataSort[$key] = mb_strtolower(str_replace(['"', '«'], '', DataHelper::escapeOutput($fileData['name'])));
                }
                array_multisort($filesDataSort, SORT_ASC, $responseData['file']);
            }

            if (count($responseData['sub_obj']) > 0) {
                foreach ($responseData['sub_obj'] as $key => $subObjData) {
                    $subObjDataSort[$key] = mb_strtolower(str_replace(['"', '«'], '', DataHelper::escapeOutput($subObjData['name'])));
                }
                array_multisort($subObjDataSort, SORT_ASC, $responseData['sub_obj']);
            }

            $responseData['path'] = '<button class="nonimportant folder_top" obj_type="{top_level}">' . $LOCALE_DISK['title'] . '</button>'
                . $responseData['path'];
            $responseData['path'] = str_replace('folder_', 'disk_folder_', $responseData['path']);

            if (REQUEST_TYPE->isApiRequest()) {
                unset($responseData['hash']);

                preg_match_all('#<button class="nonimportant([^>]+)>([^<]+)</#', $responseData['path'], $pathMatches);
                unset($responseData['path']);
                $path = [];

                foreach ($pathMatches[1] as $key => $value) {
                    unset($objType);
                    unset($objId);
                    unset($subObjType);

                    preg_match('#obj_type="([^"]+)"#', $value, $objType);
                    preg_match('#obj_id="([^"]+)"#', $value, $objId);
                    preg_match('#sub_obj_type="([^"]+)"#', $value, $subObjType);
                    $path[] = [
                        'obj_type' => $objType[1] ?? '{folder}',
                        'obj_id' => $objId[1],
                        'sub_obj_type' => $subObjType[1],
                        'name' => $pathMatches[2][$key],
                    ];
                }
                $responseData['path'] = $path;
            }

            $returnArr = ['response' => 'success', 'response_data' => $responseData];
        } elseif (count($returnArr) === 0) {
            $returnArr = [
                'response' => 'error',
                'response_code' => 'nothing_found',
                'response_text' => TextHelper::mb_ucfirst($LOCALE_GLOBAL['nothing_found']),
            ];
        }

        return $returnArr;
    }

    /** Загрузка списка папок и файлов в группе / проекте / задаче / событии */
    public function loadLibrary(int $objId, string $objType, bool $external = false): array
    {
        $LOCALE = LocaleHelper::getLocale(['global']);
        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);

        $returnArr = [];

        $objType = DataHelper::clearBraces($objType);

        $checkData = $this->checkFolderAccess($objType, $objId, $external);

        if ($checkData['allow']) {
            $checkData['parent_obj_type'] = DataHelper::clearBraces($checkData['parent_obj_type']);
            $uploadType = match (DataHelper::clearBraces($checkData['parent_obj_type'])) {
                'task', 'event' => 3,
                'project' => 4,
                'community' => 5,
                default => 1,
            };

            $text = '<div class="' . $objType . '_library">';

            if ($checkData['path'] !== '') {
                $text .= '<span class="links small gray">' . $checkData['path'] . '</span>';
            }

            $foundSomeFiles = false;

            $i = 0;
            $result = DB->query(
                "SELECT l.*, r.obj_type_from AS file_type FROM library l LEFT JOIN relation r ON l.id=r.obj_id_from WHERE r.obj_type_from='{folder}' AND r.type='{child}' AND r.obj_type_to=:obj_type_to AND r.obj_id_to=:obj_id_to ORDER BY l.path",
                [
                    ['obj_type_to', DataHelper::addBraces($objType)],
                    ['obj_id_to', $objId],
                ],
            );

            foreach ($result as $libraryFileData) {
                $uploadedData = $this->drawExtendedLibraryFiles(
                    $libraryFileData,
                    $uploadType,
                    'path',
                    $external,
                );

                if ($uploadedData !== '') {
                    ++$i;
                }

                if ($i === 5) {
                    $text .= '<a class="show_hidden">' . $LOCALE['show_hidden'] . '</a><div class="hidden">';
                }
                $text .= $uploadedData;
                $foundSomeFiles = true;
            }

            $result = DB->query(
                "SELECT l.*, r.obj_type_from AS file_type FROM library l LEFT JOIN relation r ON l.id=r.obj_id_from WHERE r.obj_type_from='{file}' AND r.type='{child}' AND r.obj_type_to=:obj_type_to AND r.obj_id_to=:obj_id_to ORDER BY l.created_at DESC",
                [
                    ['obj_type_to', DataHelper::addBraces($objType)],
                    ['obj_id_to', $objId],
                ],
            );

            foreach ($result as $libraryFileData) {
                $uploadedData = $this->drawExtendedLibraryFiles(
                    $libraryFileData,
                    $uploadType,
                    'path',
                    $external,
                );

                if ($uploadedData !== '') {
                    ++$i;
                }

                if ($i === 5) {
                    $text .= '<a class="show_hidden">' . $LOCALE['show_hidden'] . '</a><div class="hidden">';
                }
                $text .= $uploadedData;
                $foundSomeFiles = true;
            }

            if ($external && DataHelper::clearBraces($objType) !== 'folder') {
                $parentTable = DataHelper::clearBraces($objType);

                if (in_array($parentTable, ['task', 'event'])) {
                    $parentTable = 'task_and_event';
                }
                $parentData = DB->findObjectById($objId, $parentTable);

                if ($parentData['attachments'] !== '') {
                    $uploadedData = $this->drawExtendedLibraryFiles(
                        $parentData,
                        $uploadType,
                        'attachments',
                        true,
                    );

                    if ($uploadedData !== '') {
                        ++$i;
                    }

                    if ($i === 5) {
                        $text .= '<a class="show_hidden">' . $LOCALE['show_hidden'] . '</a><div class="hidden">';
                    }
                    $text .= $uploadedData;
                    $foundSomeFiles = true;
                }
            }

            if ($i > 4) {
                $text .= '</div>';
            }

            if (!$foundSomeFiles && $external) {
                $text .= '<div class="uploaded_file nothing_found">' . $LOCALE['nothing_found'] . '</div>';
            }

            $text .= '</div>';

            if (!$external) {
                $text .= '<button class="main" id="' . $objType . '_library_link_wrapper" obj_type="' . DataHelper::addBraces($objType) .
                    '" obj_id="' . $objId . '">' . $LOCALE_FRAYM['functions']['add_link'] .
                    '</button><button class="nonimportant" id="' . $objType . '_library_create_folder_wrapper" obj_type="' . DataHelper::addBraces($objType) .
                    '" obj_id="' . $objId . '">' . ($objType === 'folder' ?
                        $LOCALE_FRAYM['functions']['create_subfolder'] :
                        $LOCALE_FRAYM['functions']['create_folder']) .
                    '</button>';

                if (DataHelper::clearBraces($objType) === 'folder' && $checkData['can_edit'] && count($checkData['users_list']) > 0) {
                    $text .= '<button class="nonimportant" id="' . $objType . '_library_show_rights_wrapper">' . ($checkData['has_rights_set'] ? $LOCALE_FRAYM['functions']['access_specified'] : $LOCALE_FRAYM['functions']['access_to_all']) . '</button>';
                    $text .= '<div id="folder_users_list">';

                    /** @var UserService $userService */
                    $userService = CMSVCHelper::getService('user');

                    $userIds = [];
                    $images = [];
                    $userIdsSort = [];

                    foreach ($checkData['users_list'] as $value) {
                        $userData = $userService->get($value);
                        $userIds[] = [$userData->id->getAsInt(), $userService->showNameExtended($userData, true)];
                        $image = $userService->photoLink($userData, 30);
                        $images[] = [$userData->id->getAsInt(), $image];
                        $userIdsSort[] = $userService->showNameExtended($userData, true);
                    }
                    $userImagesSort = $userIdsSort;
                    array_multisort($userIdsSort, SORT_ASC, $userIds);
                    array_multisort($userImagesSort, SORT_ASC, $images);

                    $defaultValue = count($checkData['users_with_access_list']) > 0 ?
                        array_unique(array_merge($checkData['users_with_access_list'], $checkData['users_with_absolute_access_list'])) :
                        $checkData['users_list'];

                    $usersList = new Item\Multiselect();
                    $attribute = new Attribute\Multiselect(
                        defaultValue: $defaultValue,
                        values: $userIds,
                        locked: $checkData['users_with_absolute_access_list'],
                        images: $images,
                        search: true,
                    );
                    $usersList->setAttribute($attribute);

                    $text .= '<div class="fieldvalue" id="div_users_list">' . $usersList->asHTML(true) . '</div><div class="clear"></div>
			<button class="main" id="' . $objType . '_library_set_rights_wrapper" obj_id="' . $objId . '">' . $LOCALE_FRAYM['dynamiccreate']['saveCapitalized'] . '</button>
			</div>';
                }
            }

            $returnArr = ['response' => 'success', 'response_text' => $text];
        }

        return $returnArr;
    }

    /** Перемещение файла в папку */
    public function moveFileToFolder(int $fileId, int $folderId, bool $parentObj): array
    {
        $returnArr = [];

        $checkDataFolderTo = $this->checkFolderAccess('{folder}', $folderId);
        $libraryFileData = DB->select(
            tableName: 'library',
            criteria: [
                'id' => $fileId,
                'creator_id' => CURRENT_USER->id(),
            ],
            oneResult: true,
        );

        if ($checkDataFolderTo['allow'] && $libraryFileData['id'] !== '') {
            $objData = DB->select(
                tableName: 'relation',
                criteria: [
                    'obj_type_from' => '{file}',
                    'type' => '{child}',
                    'obj_id_from' => $fileId,
                ],
                oneResult: true,
                fieldsSet: [
                    'obj_type_to',
                    'obj_id_to',
                ],
            );
            $checkDataFile = $this->checkFolderAccess($objData['obj_type_to'], $objData['obj_id_to']);

            if ($checkDataFile['allow']) {
                RightsHelper::deleteRights('{child}', null, null, '{file}', $fileId);

                if ($parentObj) {
                    $parentObjData = DB->select(
                        tableName: 'relation',
                        criteria: [
                            'obj_type_from' => '{folder}',
                            'type' => '{child}',
                            'obj_id_from' => $folderId,
                        ],
                        oneResult: true,
                        fieldsSet: [
                            'obj_type_to',
                            'obj_id_to',
                        ],
                    );
                    RightsHelper::addRights(
                        '{child}',
                        $parentObjData['obj_type_to'],
                        $parentObjData['obj_id_to'],
                        '{file}',
                        $fileId,
                    );
                } else {
                    RightsHelper::addRights('{child}', '{folder}', $folderId, '{file}', $fileId);
                }
                $returnArr = ['response' => 'success'];
            }
        }

        return $returnArr;
    }

    /** Изменение прав доступа к папке */
    public function changeFolderRights(int $objId, array $requestUsersList): array
    {
        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);

        $checkData = $this->checkFolderAccess('{folder}', $objId);

        if ($checkData['can_edit']) {
            $usersList = [];

            foreach ($requestUsersList as $value) {
                $usersList[] = $value;
            }
            RightsHelper::deleteRights('{has_access}', '{folder}', $objId, '{user}', 0);
            $sameUsersAsParent = true;

            foreach ($checkData['users_list'] as $value) {
                if (!in_array($value, $usersList)) {
                    $sameUsersAsParent = false;
                }
            }

            if ($sameUsersAsParent && count($usersList) === count($checkData['users_list'])) {
                $returnArr = [
                    'response' => 'success',
                    'response_text' => $LOCALE_FRAYM['rights']['messages']['change_rights_success'],
                    'button_text' => $LOCALE_FRAYM['functions']['access_to_all'],
                ];
            } else {
                foreach ($usersList as $value) {
                    if (in_array($value, $checkData['users_list'])) {
                        RightsHelper::addRights('{has_access}', '{folder}', $objId, '{user}', $value);
                    }
                }
                $returnArr = [
                    'response' => 'success',
                    'response_text' => $LOCALE_FRAYM['rights']['messages']['change_rights_success'],
                    'button_text' => $LOCALE_FRAYM['functions']['access_specified'],
                ];
            }
        } else {
            $returnArr = [
                'response' => 'error',
                'response_code' => 'change_rights_fail',
                'response_text' => $LOCALE_FRAYM['rights']['messages']['change_rights_fail'],
            ];
        }

        return $returnArr;
    }

    /** Отрисовка набора файлов в развернутой библиотеке */
    public function drawExtendedLibraryFiles(
        array $libraryFileData,
        int $uploadType,
        string $row = 'path',
        bool $blockDelete = false,
    ): string {
        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $LOCALE_FRAYM = LocaleHelper::getLocale(['fraym']);

        $libraryHtml = '';

        if ($libraryFileData['file_type'] === '{folder}') {
            $checkData = $this->checkFolderAccess('{folder}', $libraryFileData['id']);

            if ($checkData['allow']) {
                $libraryHtml .= '<div class="uploaded_file folder" obj_id="' . $libraryFileData['id'] . '"' . ($blockDelete ? ' external="true"' : '') . '>';

                if ($libraryFileData['creator_id'] === CURRENT_USER->id() && !$blockDelete) {
                    $libraryHtml .= '<a class="trash careful action_request" title="' . $LOCALE_FRAYM['classes']['file']['delete'] . '" action_request="file/delete_folder" obj_id="' . $libraryFileData['id'] . '"></a><a class="edit_file" title="' . $LOCALE_FRAYM['classes']['file']['edit'] . '"></a>';
                }
                $libraryHtml .= '<a class="bold_link">' . DataHelper::escapeOutput($libraryFileData[$row]) . '</a><span class="uploaded_file_info">' .
                    $userService->showNameExtended($userService->get($libraryFileData['creator_id']), true, true) . ' | ' .
                    DateHelper::showDateTime($libraryFileData['updated_at']) . '</span>';

                if (trim(DataHelper::escapeOutput($libraryFileData['description'] ?? '')) !== '') {
                    $libraryHtml .= '<span class="uploaded_file_description">' . trim(
                        DataHelper::escapeOutput($libraryFileData['description'], EscapeModeEnum::forHTMLforceNewLines),
                    ) . '</span>';
                }
                $libraryHtml .= '</div>';
            }
        } else {
            preg_match_all('#{([^:]+):([^}:]+)}#', $libraryFileData[$row], $matches);

            foreach ($matches[0] as $key => $value) {
                $libraryHtml .= '<div class="uploaded_file">';

                if ($libraryFileData['creator_id'] === CURRENT_USER->id() && !$blockDelete) {
                    $libraryHtml .= '<a class="trash careful file_delete" title="' . $LOCALE_FRAYM['classes']['file']['delete'] . '" href="'
                        . ABSOLUTE_PATH . $_ENV['UPLOADS_PATH'] . 'files/?attachments=' . $matches[2][$key] . '&type=' . $uploadType . '" post_action="delete_library_file" post_action_id="' . $matches[1][$key] . ':' . $matches[2][$key] . '"></a><a class="edit_file" title="' . $LOCALE_FRAYM['classes']['file']['edit'] . '"></a>';
                }
                $libraryHtml .= '<a href="' . ABSOLUTE_PATH . '/' . $_ENV['UPLOADS'][$uploadType]['path'] . $matches[2][$key] . '" target="_blank" class="bold_link">' .
                    DataHelper::escapeOutput($matches[1][$key], EscapeModeEnum::plainHTML) . '</a><span class="bold_link_size"> ' .
                    FileHelper::getFileSize(INNER_PATH . $_ENV['UPLOADS'][$uploadType]['path'] . $matches[2][$key]) . '</span><span class="uploaded_file_info">' .
                    $userService->showNameExtended($userService->get($libraryFileData['creator_id']), true, true) . ' | ' .
                    DateHelper::showDateTime($libraryFileData['updated_at']) . '</span>';

                if (trim(DataHelper::escapeOutput($libraryFileData['description'])) !== '') {
                    $libraryHtml .= '<span class="uploaded_file_description">' . trim(
                        DataHelper::escapeOutput($libraryFileData['description'], EscapeModeEnum::forHTMLforceNewLines),
                    ) . '</span>';
                }
                $libraryHtml .= '</div>';
            }
            preg_match_all('#{external:([^:]+):([^}]+)}#', $libraryFileData[$row], $matches);

            foreach ($matches[0] as $key => $value) {
                $libraryHtml .= '<div class="uploaded_file">';

                if ($libraryFileData['creator_id'] === CURRENT_USER->id() && !$blockDelete) {
                    $libraryHtml .= '<a class="trash careful action_request" title="' . $LOCALE_FRAYM['classes']['file']['delete'] . '" action_request="file/delete_link" obj_id="' . $libraryFileData['id'] . '"></a><a class="edit_file" title="' . $LOCALE_FRAYM['classes']['file']['edit'] . '"></a>';
                }
                $libraryHtml .= '<a href="' . $matches[2][$key] . '" target="_blank" class="bold_link">' .
                    DataHelper::escapeOutput($matches[1][$key], EscapeModeEnum::plainHTML) . '</a><span class="uploaded_file_info">' .
                    $userService->showNameExtended($userService->get($libraryFileData['creator_id']), true, true) . ' | ' .
                    DateHelper::showDateTime($libraryFileData['updated_at']) . '</span>';

                if (trim(DataHelper::escapeOutput($libraryFileData['description']) ?? '') !== '') {
                    $libraryHtml .= '<span class="uploaded_file_description">' . trim(DataHelper::escapeOutput($libraryFileData['description'])) . '</span>';
                }
                $libraryHtml .= '</div>';
            }
        }

        return $libraryHtml;
    }
}
