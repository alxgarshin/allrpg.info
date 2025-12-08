<?php

declare(strict_types=1);

namespace App\CMSVC\ArticlesEdit;

use App\CMSVC\ArticlesEdit\ArticlesEditPage\ArticlesEditPageModel;
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Entity\{PreChange, PreCreate};
use Fraym\Helper\{DataHelper, ResponseHelper};
use Generator;

/** @extends BaseService<ArticlesEditModel|ArticlesEditPageModel> */
#[Controller(ArticlesEditController::class)]
#[PreCreate]
#[PreChange]
class ArticlesEditService extends BaseService
{
    public function checkValidData(): void
    {
        $LOCALE = $this->LOCALE['messages'];

        if (
            $this->entity->name === CMSVC
            && $_REQUEST['parent'][0] === 0
            && $_REQUEST['attachments'][0] === ''
        ) {
            ResponseHelper::responseOneBlock('error', $LOCALE['alias_for_section_not_filled'], ['attachments[0]']);
        }

        if (
            $this->entity->name === CMSVC
            && $_REQUEST['attachments'][0] !== ''
            && preg_match('#[^a-z]#', $_REQUEST['attachments'][0])
        ) {
            ResponseHelper::responseOneBlock('error', $LOCALE['alias_latin_only'], ['attachments[0]']);
        }
    }

    public function preCreate(): void
    {
        $this->checkValidData();
    }

    public function preChange(): void
    {
        $this->checkValidData();
    }

    public function getParentValues(): array
    {
        return DB->getTreeOfItems(
            true,
            'article',
            'parent',
            null,
            " AND content='{menu}'" . (DataHelper::getId() > 0 ? ' AND id!=' . DataHelper::getId() : ''),
            'code',
            1,
            'id',
            'name',
            1000000,
        );
    }

    public function getLinDefault(): ?string
    {
        if (DataHelper::getId() > 0) {
            $parentItem = ['attachments' => ''];

            $item = DB->select('article', ['id' => DataHelper::getId()], true);

            if ($item['parent'] > 0) {
                $parentItem = DB->select('article', ['id' => $item['parent']], true);

                while ($parentItem['parent'] > 0) {
                    $parentItem = DB->select('article', ['id' => $parentItem['parent']], true);
                }
            }

            return $parentItem['attachments'] !== '' ? '<a href="' . ABSOLUTE_PATH . '/' . $parentItem['attachments'] . '/' . DataHelper::getId() . '/" target="_blank">' .
                ABSOLUTE_PATH . '/' . $parentItem['attachments'] . '/' . DataHelper::getId() . '/</a>' : '-';
        }

        return null;
    }

    public function getParentValuesForPage(): array
    {
        return DB->getTreeOfItems(
            false,
            'article',
            'parent',
            null,
            " AND content='{menu}'",
            'code',
            1,
            'id',
            'name',
            1000000,
        );
    }

    public function getTagsValues(): array
    {
        return DB->getTreeOfItems(
            false,
            'tag',
            'parent',
            null,
            '',
            'name asc',
            0,
            'id',
            'name',
            3,
        );
    }

    public function getTagsLocked(): Generator
    {
        return DB->getArrayOfItems("tag WHERE content='{menu}' ORDER BY name", 'id');
    }

    public function getTagsMultiselectCreatorCreatorId(): int|string
    {
        return CURRENT_USER->id();
    }

    public function getTagsMultiselectCreatorUpdatedAt(): int|string
    {
        return time();
    }

    public function getTagsMultiselectCreatorCreatedAt(): int|string
    {
        return time();
    }
}
