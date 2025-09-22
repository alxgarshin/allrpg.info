<?php

declare(strict_types=1);

namespace App\CMSVC\NewsEdit;

use DateTimeImmutable;
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Entity\{PreChange, PreCreate};
use Fraym\Helper\ResponseHelper;
use Generator;

/** @extends BaseService<NewsEditModel> */
#[PreCreate]
#[PreChange]
#[Controller(NewsEditController::class)]
class NewsEditService extends BaseService
{
    public function preCreate(): void
    {
        $this->preChangeCheck();
    }

    public function preChange(): void
    {
        $this->preChangeCheck();
    }

    public function preChangeCheck(): void
    {
        $LOCALE = $this->getLOCALE();

        if ($_REQUEST['quote'] ?? false) {
            if ($_REQUEST['quote'][0] !== '' && $_REQUEST['attachments'][0][0] === '') {
                ResponseHelper::responseOneBlock('error', $LOCALE['no_quote_if_no_img'], ['attachments[0]', 'quote[0]']);
            }
        }
    }

    public function getShowDateDefault(): DateTimeImmutable
    {
        return new DateTimeImmutable('today 00:00');
    }

    public function getTagsValues(): array
    {
        return DB->getTreeOfItems(
            false,
            'tag',
            'parent',
            null,
            '',
            'name',
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

    public function getTagsMultiselectCreatorCreatorId(): ?int
    {
        return CURRENT_USER->id();
    }

    public function getTagsMultiselectCreatorCreatedUpdatedAt(): int
    {
        return time();
    }

    public function checkRights(): bool
    {
        return CURRENT_USER->isAdmin() || CURRENT_USER->checkAllRights('news');
    }
}
