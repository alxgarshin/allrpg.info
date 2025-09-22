<?php

declare(strict_types=1);

namespace App\CMSVC\File;

use Fraym\BaseObject\{BaseController, CMSVC, IsAccessible};
use Fraym\Interface\Response;

/** @extends BaseController<FileService> */
#[IsAccessible]
#[CMSVC(
    service: FileService::class,
)]
class FileController extends BaseController
{
    public function editFileOrFolderName(): ?Response
    {
        return $this->asArray(
            $this->getService()->editFileOrFolderName(
                OBJ_ID,
                OBJ_TYPE,
                $_REQUEST['name'] ?? '',
                $_REQUEST['description'] ?? '',
            ),
        );
    }

    public function newLibraryFile(): ?Response
    {
        return $this->asArray(
            $this->getService()->newLibraryFile(
                OBJ_ID,
                OBJ_TYPE,
                $_REQUEST['name'] ?? '',
                $_REQUEST['name_shown'] ?? '',
            ),
        );
    }

    public function deleteLibraryFile(): ?Response
    {
        return $this->asArray($this->getService()->deleteLibraryFile(OBJ_ID));
    }

    public function deleteConversationFile(): ?Response
    {
        return $this->asArray($this->getService()->deleteConversationFile(OBJ_ID));
    }

    public function createFolder(): ?Response
    {
        return $this->asArray(
            $this->getService()->createFolder(
                OBJ_ID,
                OBJ_TYPE,
                $_REQUEST['name'] ?? '',
            ),
        );
    }

    public function deleteFolder(): ?Response
    {
        return $this->asArray($this->getService()->deleteFolder(OBJ_ID));
    }

    public function addLink(): ?Response
    {
        return $this->asArray(
            $this->getService()->addLink(
                OBJ_ID,
                OBJ_TYPE,
                $_REQUEST['name'] ?? '',
                $_REQUEST['link'] ?? '',
            ),
        );
    }

    public function deleteLink(): ?Response
    {
        return $this->asArray($this->getService()->deleteLink(OBJ_ID));
    }

    public function loadDisk(): ?Response
    {
        return $this->asArray(
            $this->getService()->loadDisk(
                OBJ_ID,
                OBJ_TYPE,
                $_REQUEST['sub_obj_type'] ?? '',
            ),
        );
    }

    public function loadLibrary(): ?Response
    {
        return $this->asArray(
            $this->getService()->loadLibrary(
                (int) OBJ_ID,
                OBJ_TYPE,
                ($_REQUEST['external'] ?? '') === 'true',
            ),
        );
    }

    public function moveFileToFolder(): ?Response
    {
        return $this->asArray(
            $this->getService()->moveFileToFolder(
                (int) ($_REQUEST['file_id'] ?? 0),
                (int) ($_REQUEST['folder_id'] ?? 0),
                ($_REQUEST['parent_obj'] ?? '') === 'true',
            ),
        );
    }

    public function changeFolderRights(): ?Response
    {
        return $this->asArray(
            $this->getService()->changeFolderRights(
                OBJ_ID,
                $_REQUEST['users_list'] ?? [],
            ),
        );
    }
}
