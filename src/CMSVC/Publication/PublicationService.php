<?php

declare(strict_types=1);

namespace App\CMSVC\Publication;

use App\CMSVC\PublicationsEdit\{PublicationsEditModel, PublicationsEditService};
use App\CMSVC\Trait\UserServiceTrait;
use App\CMSVC\User\UserModel;
use App\Helper\UniversalHelper;
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Enum\OperandEnum;
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper};

/** @extends BaseService<PublicationsEditModel> */
#[Controller(PublicationController::class)]
class PublicationService extends BaseService
{
    use UserServiceTrait;

    public array $publicationsIds = [];
    private array $importantCounters = [];
    private array $messagesCounters = [];
    private array $userNames = [];

    /** Выборка публикаций на основе запроса */
    public function getPublications(): array
    {
        /** @var PublicationsEditService */
        $publicationsEditService = CMSVCHelper::getService('publications_edit');

        if ($_REQUEST['tag'] ?? false) {
            $publicationsData = $publicationsEditService->getAll(
                [
                    'active' => 1,
                    ['tags', '%-' . $_REQUEST['tag'] . '-%', [OperandEnum::LIKE]],
                ],
                false,
                ['name'],
            );
        } elseif ($_REQUEST['search'] ?? false) {
            /** @var PublicationsEditModel[] $publicationsData */
            $publicationsData = $publicationsEditService->arraysToModels(
                DB->query(
                    'SELECT * FROM publication WHERE active=:active AND (name LIKE :input1 OR annotation LIKE :input2 OR content LIKE :input3) ORDER BY name',
                    [
                        ['active', 1],
                        ['input1', '%' . $_REQUEST['search'] . '%'],
                        ['input2', '%' . $_REQUEST['search'] . '%'],
                        ['input3', '%' . $_REQUEST['search'] . '%'],
                    ],
                ),
            );
        } else {
            $publicationsData = $publicationsEditService->getAll(
                [
                    'active' => 1,
                ],
                false,
                ['updated_at DESC'],
            );
        }

        $usersIdsToPreload = [1];
        $publicationsData = iterator_to_array($publicationsData);

        foreach ($publicationsData as $publicationData) {
            $this->publicationsIds[] = $publicationData->id->getAsInt();
            $usersIdsToPreload[] = $publicationData->creator_id->getAsInt();

            $authorData = $publicationData->author->get();

            if (count($authorData) > 0) {
                foreach ($authorData as $author) {
                    if ($author) {
                        $authorId = (int) trim($author);

                        if ($authorId > 0) {
                            $usersIdsToPreload[] = $authorId;
                        }
                    }
                }
            }
        }
        $this->getUserService()->getAll(['id' => $usersIdsToPreload]);

        return $publicationsData;
    }

    /** Создание облака тегов */
    public function drawTagsCloud(): string
    {
        $RESPONSE_DATA = '';

        $allPublicationsCount = DB->count('publication', ['active' => 1]);

        $tagsData = DB->query(
            'SELECT t.id, t.name, count(p.id) as publications_count FROM tag AS t LEFT JOIN publication AS p ON p.tags LIKE CONCAT("%-",t.id,"-%") AND p.active=:active GROUP BY t.id ORDER BY t.name',
            [
                ['active', 1],
            ],
        );

        foreach ($tagsData as $tagData) {
            $delta = $tagData['publications_count'] > 0 ? ceil($tagData['publications_count'] / $allPublicationsCount * 100) : 0;
            $RESPONSE_DATA .= '<div class="tags_cloud_tag" style="font-size: ' . (90 + $delta) . '%;"><a href="' . ABSOLUTE_PATH . '/publication/tag=' . $tagData['id'] . '">' . DataHelper::escapeOutput($tagData['name']) . '</a></div>';
        }

        return $RESPONSE_DATA;
    }

    /** Получение списка авторов публикации */
    public function getAuthors(PublicationsEditModel $publicationData): string
    {
        $authorData = $publicationData->author->get();

        $authors = '';

        if (count($authorData) > 0) {
            $authorResult = [];

            foreach ($authorData as $author) {
                if ($author) {
                    $authorId = (int) trim($author);

                    if ($authorId > 0) {
                        if ($this->userNames[$authorId] ?? false) {
                            $authorResult[] = $this->userNames[$authorId];
                        } else {
                            $authorResult[] = $this->userNames[$authorId] = $this->getUserService()->showName($this->getUserService()->get($authorId), true);
                        }
                    }
                }
            }
            $authors = implode(', ', $authorResult);
        }

        if ($authors === '' && count($authorData) > 0) {
            $authors = DataHelper::escapeOutput(implode(', ', $authorData));
        }

        return $authors;
    }

    /** Получение списка тегов публикации */
    public function getTags(PublicationsEditModel $publicationData): string
    {
        $tagsData = $publicationData->tags->get();

        foreach ($tagsData as $key => $value) {
            if ($value === '') {
                unset($tagsData[$key]);
            }
        }
        $tagsResult = [];

        if (count($tagsData) > 0) {
            $tagsValues = $publicationData->tags->getValues();

            foreach ($tagsData as $tagData) {
                $tagsResult[] = '<a href="' . ABSOLUTE_PATH . '/publication/tag=' . $tagData . '#tabs-2">' . DataHelper::escapeOutput($tagsValues[$tagData][1]) . '</a>';
            }
        }

        return implode('', $tagsResult);
    }

    /** Получение creator'а публикации */
    public function getCreator(PublicationsEditModel $publicationData): UserModel
    {
        if ($publicationData->creator_id->getAsInt() === 0) {
            $publicationData->creator_id->set('1');
        }

        return $this->getUserService()->get($publicationData->creator_id->getAsInt());
    }

    /** Подготовка значений для кнопки "Нравится" по всем публикациям сразу */
    public function prepareImportantCounters(): void
    {
        if (count($this->publicationsIds) > 0) {
            foreach ($this->publicationsIds as $publicationId) {
                $this->importantCounters[$publicationId] = [
                    'count' => 0,
                    'marked' => false,
                ];
            }

            $publicationsImportantCounts = DB->query(
                'SELECT obj_id_to AS publication_id, COUNT(obj_id_from) as important_count FROM relation WHERE type=:type AND obj_type_to=:obj_type_to AND obj_type_from=:obj_type_from AND obj_id_to IN (:obj_id_to) GROUP BY obj_id_to',
                [
                    ['type', '{important}'],
                    ['obj_type_to', '{publication}'],
                    ['obj_id_to', $this->publicationsIds],
                    ['obj_type_from', '{user}'],
                ],
            );

            foreach ($publicationsImportantCounts as $publicationsImportantCount) {
                $this->importantCounters[(int) $publicationsImportantCount['publication_id']]['count'] = $publicationsImportantCount['important_count'];
            }

            if (CURRENT_USER->id() > 0) {
                $publicationsUserChecked = DB->query(
                    'SELECT obj_id_to AS publication_id FROM relation WHERE type=:type AND obj_type_to=:obj_type_to AND obj_type_from=:obj_type_from AND obj_id_from=:obj_id_from AND obj_id_to IN (:obj_id_to)',
                    [
                        ['type', '{important}'],
                        ['obj_type_to', '{publication}'],
                        ['obj_id_to', $this->publicationsIds],
                        ['obj_type_from', '{user}'],
                        ['obj_id_from', CURRENT_USER->id()],
                    ],
                );

                foreach ($publicationsUserChecked as $publicationsUserCheckedItem) {
                    $this->importantCounters[(int) $publicationsUserCheckedItem['publication_id']]['marked'] = true;
                }
            }
        }
    }

    /** Подготовка значений для счетчика сообщений по всем публикациям сразу */
    public function prepareMessagesCounters(): void
    {
        if (count($this->publicationsIds) > 0) {
            foreach ($this->publicationsIds as $publicationId) {
                $this->messagesCounters[$publicationId] = 0;
            }

            $publicationsMessagesCounts = DB->query(
                'SELECT c.obj_id AS publication_id, COUNT(cm.id) AS cm_count FROM conversation_message cm INNER JOIN conversation c ON cm.conversation_id=c.id AND c.obj_type=:obj_type AND c.obj_id IN (:obj_ids) GROUP BY c.obj_id',
                [
                    ['obj_type', '{publication_wall}'],
                    ['obj_ids', $this->publicationsIds],
                ],
            );

            foreach ($publicationsMessagesCounts as $publicationMessagesCount) {
                $this->messagesCounters[(int) $publicationMessagesCount['publication_id']] = $publicationMessagesCount['cm_count'];
            }
        }
    }

    /** Вывод краткого варианта публикации */
    public function drawPublicationShort(PublicationsEditModel $publicationData): string
    {
        $LOCALE = LocaleHelper::getLocale(['publication', 'global']);
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);

        $authors = $this->getAuthors($publicationData);
        $tags = $this->getTags($publicationData);
        $creatorData = $this->getCreator($publicationData);
        $creatorId = $creatorData->id->getAsInt();

        if ($this->userNames[$creatorId] ?? false) {
            $creatorName = $this->userNames[$creatorId];
        } else {
            $creatorName = $this->userNames[$creatorId] = $this->getUserService()->showName($creatorData, true);
        }

        $result = '<div class="publication"><div class="publication_header"><a href="' . ABSOLUTE_PATH . '/publication/' . $publicationData->id->getAsInt() . '/">'
            . DataHelper::escapeOutput($publicationData->name->get()) . '</a></div>
	' . ($authors !== '' ? '<div class="publication_authors"><span class="gray">' . $LOCALE['author'] . ':</span> ' . $authors . '</div>' : '<div class="publication_authors"><span class="gray">' . $LOCALE_GLOBAL['published_by'] . ':</span> ' . $creatorName . '</div>');
        $result .= '<div class="publication_annotation">' . DataHelper::escapeOutput($publicationData->annotation->get()) . '</div>
	<div class="publication_tags">' . $tags . '</div>
	' . UniversalHelper::drawImportant('{publication}', $publicationData->id->getAsInt(), $this->importantCounters[$publicationData->id->getAsInt()]['marked'], $this->importantCounters[$publicationData->id->getAsInt()]['count']) . ' ' .
            UniversalHelper::drawMessagesButton('{publication_wall}', $publicationData->id->getAsInt(), $this->messagesCounters[$publicationData->id->getAsInt()] ?? 0) . '
	<div class="clear"></div>
	</div>';

        return $result;
    }
}
