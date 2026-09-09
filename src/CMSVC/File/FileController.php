<?php

declare(strict_types=1);

namespace App\CMSVC\File;

use Fraym\BaseObject\{ApiAction, ApiParam, BaseController, CMSVC, IsAccessible};
use Fraym\Enum\{ApiParamSourceEnum, ApiParamTypeEnum};
use Fraym\Interface\Response;

/** @extends BaseController<FileService> */
#[IsAccessible]
#[CMSVC(
    service: FileService::class,
)]
class FileController extends BaseController
{
    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('name', ApiParamTypeEnum::string, obligatory: true, default: ''),
        new ApiParam('description', ApiParamTypeEnum::string, default: ''),
    ])]
    public function editFileOrFolderName(): ?Response
    {
        return $this->asArray(
            $this->service->editFileOrFolderName(
                OBJ_ID,
                OBJ_TYPE,
                $this->param('name'),
                $this->param('description'),
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('name', ApiParamTypeEnum::string, obligatory: true, default: ''),
        new ApiParam('name_shown', ApiParamTypeEnum::string, obligatory: true, default: ''),
    ])]
    public function newLibraryFile(): ?Response
    {
        return $this->asArray(
            $this->service->newLibraryFile(
                OBJ_ID,
                OBJ_TYPE,
                $this->param('name'),
                $this->param('name_shown'),
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
    ])]
    public function deleteLibraryFile(): ?Response
    {
        return $this->asArray($this->service->deleteLibraryFile(OBJ_ID));
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
    ])]
    public function deleteConversationFile(): ?Response
    {
        return $this->asArray($this->service->deleteConversationFile(OBJ_ID));
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('name', ApiParamTypeEnum::string, obligatory: true, default: ''),
    ])]
    public function createFolder(): ?Response
    {
        return $this->asArray(
            $this->service->createFolder(
                OBJ_ID,
                OBJ_TYPE,
                $this->param('name'),
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function deleteFolder(): ?Response
    {
        return $this->asArray($this->service->deleteFolder(OBJ_ID));
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('name', ApiParamTypeEnum::string, obligatory: true, default: ''),
        new ApiParam('link', ApiParamTypeEnum::string, obligatory: true, default: ''),
    ])]
    public function addLink(): ?Response
    {
        return $this->asArray(
            $this->service->addLink(
                OBJ_ID,
                OBJ_TYPE,
                $this->param('name'),
                $this->param('link'),
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
    ])]
    public function deleteLink(): ?Response
    {
        return $this->asArray($this->service->deleteLink(OBJ_ID));
    }

    #[ApiAction(params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('sub_obj_type', ApiParamTypeEnum::string, default: ''),
    ])]
    public function loadDisk(): ?Response
    {
        return $this->asArray(
            $this->service->loadDisk(
                OBJ_ID,
                OBJ_TYPE,
                $this->param('sub_obj_type'),
            ),
        );
    }

    #[ApiAction(params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('obj_type', ApiParamTypeEnum::string, obligatory: true, default: '', source: ApiParamSourceEnum::global),
        new ApiParam('external', ApiParamTypeEnum::bool, default: false),
    ])]
    public function loadLibrary(): ?Response
    {
        return $this->asArray(
            $this->service->loadLibrary(
                (int) OBJ_ID,
                OBJ_TYPE,
                $this->param('external'),
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('file_id', ApiParamTypeEnum::int, obligatory: true, default: 0),
        new ApiParam('folder_id', ApiParamTypeEnum::int, obligatory: true, default: 0),
        new ApiParam('parent_obj', ApiParamTypeEnum::bool, default: false),
    ])]
    public function moveFileToFolder(): ?Response
    {
        return $this->asArray(
            $this->service->moveFileToFolder(
                $this->param('file_id'),
                $this->param('folder_id'),
                $this->param('parent_obj'),
            ),
        );
    }

    #[ApiAction(mutating: true, params: [
        new ApiParam('obj_id', ApiParamTypeEnum::int, obligatory: true, default: 0, source: ApiParamSourceEnum::global),
        new ApiParam('users_list', ApiParamTypeEnum::array, obligatory: true, default: []),
    ])]
    public function changeFolderRights(): ?Response
    {
        return $this->asArray(
            $this->service->changeFolderRights(
                OBJ_ID,
                $this->param('users_list'),
            ),
        );
    }
}
