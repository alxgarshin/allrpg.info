<?php

declare(strict_types=1);

namespace App\CMSVC\Ruling;

use App\CMSVC\RulingItemEdit\{RulingItemEditModel, RulingItemEditService};
use App\CMSVC\RulingTagEdit\RulingTagEditService;
use App\CMSVC\User\UserService;
use App\Helper\UniversalHelper;
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Element\{Attribute, Item};
use Fraym\Enum\{EscapeModeEnum, OperandEnum};
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper};
use Generator;

#[Controller(RulingController::class)]
class RulingService extends BaseService
{
    private array $alreadyPresentItems = [];
    private array $alreadyPresentItemsIds = [];
    private array $selectedValues = [];
    private array $sortingTree = [];
    private array $checkedQuestions = [];

    public function preView(bool $viewAll = false, int $rulingTagId = 0, string $searchString = '', bool $fillform = false): array
    {
        /** @var RulingItemEditService $rulingItemEditService */
        $rulingItemEditService = CMSVCHelper::getService('rulingItemEdit');

        $listOfTags = $rulingItemEditService->rulingTagIdsValues;

        if ($rulingTagId > 0) {
            $viewAll = true;
            $childTagSelectString = '';
            $childTagIds = [];
            $currentTagLevel = false;

            if ($listOfTags) {
                foreach ($listOfTags as $key => $tagData) {
                    if ($currentTagLevel !== false) {
                        if ($tagData[2] > $currentTagLevel) {
                            $childTagIds[] = $tagData[0];
                        } else {
                            break;
                        }
                    }

                    if ($tagData[0] === $rulingTagId) {
                        $currentTagLevel = $tagData[2];
                    }
                }
            }

            if ($childTagIds) {
                foreach ($childTagIds as $childTagId) {
                    $childTagSelectString .= ' OR ruling_tag_ids LIKE "%-' . $childTagId . '-%"';
                }
            }

            $rulingItems = $rulingItemEditService->arraysToModels(
                DB->query(
                    'SELECT * FROM ruling_item WHERE ruling_tag_ids LIKE "%-' . $rulingTagId . '-%"' . $childTagSelectString . ' ORDER BY name',
                    [],
                ),
            );
        } elseif (mb_strlen(trim($searchString)) > 3) {
            $rulingItems = $rulingItemEditService->arraysToModels(
                DB->query(
                    'SELECT * FROM ruling_item WHERE (name LIKE "%' . $searchString . '%" OR content LIKE "%' . $searchString . '%") ORDER BY name',
                    [],
                ),
            );
        } elseif ($viewAll) {
            $rulingItems = $rulingItemEditService->getAll(null, false, ['updated_at DESC', 'name']);
        } else {
            $rulingItems = $rulingItemEditService->getAll(null, false, ['updated_at DESC', 'name'], 10);
        }

        $sortingTreeRehashed = $this->getSortingTree(true);

        /** @var Generator<int|string, Item\Select|Item\Multiselect> */
        $rulingQuestions = DataHelper::virtualStructure(
            'SELECT * FROM ruling_question ORDER BY FIELD(id, ' . ($sortingTreeRehashed ? implode(',', $sortingTreeRehashed) : '0') . '), code, field_name',
            [],
            'field_',
            [
                'code',
                'show_if',
            ],
        );

        $rulingQuestions = iterator_to_array($rulingQuestions);

        foreach ($rulingQuestions as $key => $rulingQuestionData) {
            if ($rulingQuestionData instanceof Item\Select) {
                $field = new Item\Multiselect();
                $field->name = $rulingQuestionData->name;
                $field->shownName = $rulingQuestionData->shownName;
                $fieldAttribute = new Attribute\Multiselect(
                    defaultValue: $rulingQuestionData->getAttribute()->defaultValue,
                    one: true,
                    values: $rulingQuestionData->getAttribute()->values,
                    additionalData: $rulingQuestionData->getAttribute()->additionalData,
                );
                $field->setAttribute($fieldAttribute);
                $rulingQuestions[$key] = $rulingQuestionData = $field;
            } else {
                $rulingQuestionData->getAttribute()->one = false;
            }

            if ($fillform) {
                if ($rulingQuestionData->getAttribute()->one) {
                    if ($_REQUEST[$rulingQuestionData->name][0] ?? false) {
                        $rulingQuestionData->getAttribute()->defaultValue = $_REQUEST[$rulingQuestionData->name][0];
                    }
                } else {
                    $defArray = [];

                    foreach ($_REQUEST[$rulingQuestionData->name][0] ?? [] as $defKey => $value) {
                        if ($value === 'on') {
                            $defArray[] = $defKey;
                        }
                    }
                    $rulingQuestionData->getAttribute()->defaultValue = $defArray;
                }
            }
        }

        $showHideFieldsScript = '<script>';

        foreach ($rulingQuestions as $rulingQuestionData) {
            $allShowIfs = json_decode($rulingQuestionData->getAttribute()->additionalData['show_if'], true);

            if (!is_null($allShowIfs) && count($allShowIfs) > 0) {
                $showHideItemData = [
                    'name' => $rulingQuestionData->name . '[0]',
                    'dependencies' => [],
                ];

                foreach ($allShowIfs as $showIfValue) {
                    $showIfOneGroup = [];

                    foreach ($showIfValue as $showIfLinksData => $ignore) {
                        // $showIfLinksData - ссылка на вопрос и ответ в формате: 2:6
                        unset($matches);
                        preg_match('#(\d+):(\d+)#', $showIfLinksData, $matches);

                        $showIfOneGroup[] = [
                            'type' => 'multiselect',
                            'name' => 'virtual' . $matches[1] . '[0]',
                            'value' => $matches[2],
                        ];
                    }

                    $showHideItemData['dependencies'][] = $showIfOneGroup;
                }

                $showHideFieldsScript .= '
    dynamicFieldsList.push(' . DataHelper::jsonFixedEncode($showHideItemData) . ');';
            }
        }

        $showHideFieldsScript .= '
</script>';

        return [$rulingQuestions, $showHideFieldsScript, $rulingItems];
    }

    public function preGenerate(): array
    {
        $linkToGeneration = '';
        $selectedValues = [];

        foreach ($_REQUEST as $possibleDataKey => $possibleData) {
            if (isset($possibleData[0]) && preg_match('#virtual(\d+)#', $possibleDataKey, $match)) {
                if (is_array($possibleData[0])) {
                    foreach ($possibleData[0] as $key => $possibleDataValue) {
                        if ($possibleDataValue === 'on') {
                            $selectedValues[$match[1]][] = (int) $key;
                            $linkToGeneration .= '&' . $possibleDataKey . '[0][' . $key . ']=on';
                        }
                    }
                } elseif ($possibleData[0] !== '') {
                    $selectedValues[$match[1]][] = (int) $possibleData[0];
                    $linkToGeneration .= '&' . $possibleDataKey . '[0]=' . (int) $possibleData[0];
                }
            }
        }
        $filledFormLink = ABSOLUTE_PATH . '/' . KIND . '/action=fillform' . $linkToGeneration;
        $linkToGeneration = ABSOLUTE_PATH . '/' . KIND . '/action=generate' . $linkToGeneration;

        $this->selectedValues = $selectedValues;
        $this->sortingTree = $this->getSortingTreeWithShowIf();

        return [$filledFormLink, $linkToGeneration];
    }

    public function getAuthors(RulingItemEditModel $objData): array
    {
        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        $LOCALE_PUBLICATION = LocaleHelper::getLocale(['publication', 'global']);

        $authorResult = [];
        $authorsArray = $objData->author->get();

        if (count($authorsArray) > 0) {
            foreach ($authorsArray as $author) {
                if ($author && (int) trim($author) > 0) {
                    $authorResult[] = $userService->showName($userService->get((int) trim($author)), true);
                }
            }
        }

        if (count($authorResult) === 0) {
            $authorResult[] = $LOCALE_PUBLICATION['author_not_provided'];
        }

        return $authorResult;
    }

    public function getTags(RulingItemEditModel $objData): string
    {
        /** @var RulingItemEditService $rulingItemEditService */
        $rulingItemEditService = CMSVCHelper::getService('rulingItemEdit');

        $tagsArray = $objData->ruling_tag_ids->get();

        foreach ($tagsArray as $key => $value) {
            if ($value === '') {
                unset($tagsArray[$key]);
            }
        }
        $tagResult = [];

        if (count($tagsArray) > 0) {
            $listOfTags = $rulingItemEditService->getRulingTagIdsValues();

            foreach ($tagsArray as $tagItemId) {
                foreach ($listOfTags as $tagKey => $listOfTagItem) {
                    if ($listOfTagItem[0] === $tagItemId) {
                        $tagResult[] = $this->createRulingTagPath($tagKey, $listOfTags, true);
                        break;
                    }
                }
            }
        }
        $tags = implode('', $tagResult);

        return $tags;
    }

    public function drawRulingTagsCloud(int $rulingTagId = 0): string
    {
        /** @var RulingItemEditService $rulingItemEditService */
        $rulingItemEditService = CMSVCHelper::getService('rulingItemEdit');

        /** @var RulingTagEditService $rulingTagEditService */
        $rulingTagEditService = CMSVCHelper::getService('rulingTagEdit');

        $RESPONSE_DATA = '';

        $listOfTags = $rulingItemEditService->rulingTagIdsValues;
        $rulingItemsCount = DB->count('ruling_item');

        $parentTagId = 0;

        if ($rulingTagId > 0) {
            $rulingTagData = $rulingTagEditService->get($rulingTagId);
            $parentTagId = $rulingTagData->parent->get();
        }

        if ($parentTagId > 0) {
            $RESPONSE_DATA .= '<div class="tags_cloud_tag"><a href="' . ABSOLUTE_PATH . '/' . KIND . '/ruling_tag=' . $parentTagId . '">&larr;</a></div>';
        }

        if ($rulingTagId > 0) {
            $rulingTags = $rulingTagEditService->getAll(['id' => $rulingTagId]);
        } else {
            $rulingTags = $rulingTagEditService->getAll(['show_in_cloud' => 1], false, ['name']);
        }

        foreach ($rulingTags as $rulingTagData) {
            $childTagSelectString = '';
            $childTagIds = [];
            $currentTagLevel = false;

            if ($listOfTags) {
                foreach ($listOfTags as $tagData) {
                    if ($currentTagLevel !== false) {
                        if ($tagData[2] > $currentTagLevel) {
                            $childTagIds[] = $tagData[0];
                        } else {
                            break;
                        }
                    }

                    if ($tagData[0] === $rulingTagData->id->getAsInt()) {
                        $currentTagLevel = $tagData[2];
                    }
                }
            }

            foreach ($childTagIds as $childTagId) {
                $childTagSelectString .= ' OR ruling_tag_ids LIKE "%-' . $childTagId . '-%"';
            }

            $taggedRulingItemsCount = DB->query('SELECT COUNT(id) FROM ruling_item WHERE ruling_tag_ids LIKE "%-' . $rulingTagData->id->getAsInt() . '-%"' . $childTagSelectString, [], true)[0];

            if ((int) $taggedRulingItemsCount > 0) {
                $delta = ceil($taggedRulingItemsCount / $rulingItemsCount * 100);
            } else {
                $delta = 0;
            }
            $RESPONSE_DATA .= '<div class="tags_cloud_tag" style="font-size: ' . (90 + $delta) . '%;"><a href="' . ABSOLUTE_PATH . '/' . KIND . '/ruling_tag=' . $rulingTagData->id->getAsInt() . '">' . DataHelper::escapeOutput($rulingTagData->name->get()) . '</a></div>';

            if ($rulingTagId > 0 && count($childTagIds) > 0) {
                // если выбран тег, выводим всех его наследников
                $childTags = $rulingTagEditService->getAll(['id' => $childTagIds], false, ['name']);

                foreach ($childTags as $childTag) {
                    $taggedRulingItemsCount = DB->count('ruling_item', [['ruling_tag_ids', '%-' . $childTag->id->getAsInt() . '-%', [OperandEnum::LIKE]]]);

                    if ($taggedRulingItemsCount > 0) {
                        $delta = ceil($taggedRulingItemsCount / $rulingItemsCount * 100);
                    } else {
                        $delta = 0;
                    }
                    $RESPONSE_DATA .= '<div class="tags_cloud_tag" style="font-size: ' . (90 + $delta) . '%;"><a href="' . ABSOLUTE_PATH . '/' . KIND . '/ruling_tag=' . $childTag->id->getAsInt() . '">' . DataHelper::escapeOutput($childTag->name->get()) . '</a></div>';
                }
            }
        }

        return $RESPONSE_DATA;
    }

    /** Вывод краткого варианта части правил */
    public function showRulingItemShort(RulingItemEditModel $rulingItem, bool $controlButtons = false): string
    {
        $LOCALE = LocaleHelper::getLocale(['publication', 'global']);

        $authors = $this->getAuthors($rulingItem);
        $tags = $this->getTags($rulingItem);

        $result = '<div class="publication"><div class="publication_header"><a href="' . ABSOLUTE_PATH . '/ruling/' . $rulingItem->id->getAsInt() . '/">' . DataHelper::escapeOutput($rulingItem->name->get()) . '</a></div> <div class="publication_authors"><span class="gray">' .
            (count($authors) > 1 ? $LOCALE['authors'] : $LOCALE['author']) . ':</span> ' . implode(', ', $authors) . '</div>';

        if ($controlButtons) {
            $result .= '<div class="publication_buttons"><div class="publication_buttons_edit"><a href="' . ABSOLUTE_PATH . '/ruling_item_edit/' . $rulingItem->id->getAsInt() . '/">' . $LOCALE['edit'] . '</a></div></div>';
        }
        $result .= '<div class="publication_annotation">' .
            mb_substr(strip_tags(preg_replace('#{.*"(item|html)"[^}]*}#', '', DataHelper::escapeOutput($rulingItem->content->get(), EscapeModeEnum::plainHTML))), 0, 255) . '&#8230;</div>
	<div class="publication_tags gray">' . $tags . '</div>
	' . UniversalHelper::drawImportant('{ruling_item}', $rulingItem->id->getAsInt()) . ' ' .
            UniversalHelper::drawMessagesButton('{ruling_item_wall}', $rulingItem->id->getAsInt()) . '
	<div class="clear"></div>
	</div>';

        return $result;
    }

    /** Формирование хлебных крошек для тегов Рулёжки */
    public function createRulingTagPath(int $groupKey, array $rulingTags, bool $href, int $minLevel = 0): string
    {
        $path = '';

        if (isset($rulingTags[$groupKey])) {
            $theLevel = $rulingTags[$groupKey][2];

            if ($theLevel > $minLevel) {
                $parentKey = $groupKey - 1;
                $prevGroupData = $rulingTags[$parentKey];

                while ($prevGroupData[2] !== $theLevel - 1) {
                    --$parentKey;
                    $prevGroupData = $rulingTags[$parentKey];
                }
                // $path = ($href ? '<a href="' . ABSOLUTE_PATH . '/ruling/ruling_tag=' . $prevGroupData[0] . '">' : '') . $prevGroupData[1] . ($href ? '</a>' : '') . ' &rarr; ';
            }
            $path .= ($href ? '<a href="' . ABSOLUTE_PATH . '/ruling/ruling_tag=' . $rulingTags[$groupKey][0] . '">' : '') .
                DataHelper::escapeOutput($rulingTags[$groupKey][1]) . ($href ? '</a>' : '');
        }

        return $path;
    }

    /** Разбитие длинных строк на массив строк (например, лейблов длинных на графиках) */
    public function breakStringToArray(string $str, int $breakOn): array
    {
        /* находим все пробелы */
        $array = [];
        $str = trim($str);

        $lastPos = 0;
        $maxPos = 0;
        $positions = [];

        while (($lastPos = mb_strpos($str, ' ', $lastPos)) !== false) {
            $positions[] = $lastPos;
            $maxPos = $lastPos;
            $lastPos = $lastPos + mb_strlen(' ');
        }

        $strLength = mb_strlen($str);

        if ($maxPos === 0 || $strLength <= $breakOn) {
            $array = [$str];
        } elseif ($maxPos < $breakOn || count($positions) === 1) {
            $array[] = trim(mb_substr($str, 0, $maxPos));
            $array[] = trim(mb_substr($str, $maxPos, $strLength));
        } else {
            foreach ($positions as $posKey => $position) {
                if (($position <= $breakOn && ($positions[$posKey + 1] ?? 0) > $breakOn) || ($position <= $breakOn && !($positions[$posKey + 1] ?? false))) {
                    if ($strLength > $breakOn + 1) {
                        $array[] = trim(mb_substr($str, 0, $position));
                        $nextStr = trim(mb_substr($str, $position, $strLength));

                        if (mb_strlen($nextStr) > $breakOn) {
                            $array = array_merge($array, self::breakStringToArray($nextStr, $breakOn));
                        } else {
                            $array[] = $nextStr;
                        }
                    } else {
                        $array = [$str];
                    }
                    break;
                }
            }
        }

        return $array;
    }

    /** Получение отсортированных вопросов с указанием на то, какие вопросы наследуют текущему */
    public function getSortingTreeWithShowIf(): array
    {
        $sortingTree = $this->getSortingTree();

        foreach ($sortingTree as $key => $sortingItem) {
            $showIfs = DataHelper::jsonFixedDecode($sortingItem[1]);

            foreach ($showIfs as $showIf) {
                foreach ($showIf as $questionAndAnswerId => $ignore) {
                    if ($ignore === 'on') {
                        unset($matches);
                        preg_match('#(\d+):(\d+)#', $questionAndAnswerId, $matches);

                        if ($matches[1] > 0 && $matches[2] > 0) {
                            $questionId = $matches[1];
                            $answerId = $matches[2];
                            $sortingTree[$questionId]['childs'][$answerId][] = $key;
                        }
                    }
                }
            }
        }

        return $sortingTree;
    }

    /** Получение отсортированных вопросов: если hashed, то возвращается только список id */
    public function getSortingTree(bool $hashed = false): array
    {
        $sortingTree = $this->getSortingTreeByCode('', 1);
        $sortingTree = $sortingTree + $this->getSortingTreeByCode('|', 1);

        if ($hashed) {
            return array_keys($sortingTree);
        }

        return $sortingTree;
    }

    /** Создание правильного порядка сортировки вопросов в зависимости от их кода и положения в родительском вопросе в Рулёжке */
    public function getSortingTreeByCode(string $prefix, int $curId): array
    {
        $sortingTree = [];
        $rulingQuestion = DB->select(
            'ruling_question',
            [
                ['code', $prefix . $curId . '=0'],
            ],
            true,
        );

        if ($rulingQuestion) {
            preg_match_all('#\[(\d+)]\[([^]]+)]#', DataHelper::escapeOutput($rulingQuestion['field_values']), $matches);
            $sortingTree[$rulingQuestion['id']] = [
                $rulingQuestion['id'],
                $rulingQuestion['show_if'],
                'childs' => [],
            ];

            foreach ($matches[1] as $value) {
                $sortingTree[$rulingQuestion['id']]['childs'][$value] = [];
            }

            foreach ($matches[1] as $value) {
                $sortingTree = $sortingTree + self::getSortingTreeByCode($prefix . $curId . '=' . $value . '.', 1);
            }

            $sortingTree = $sortingTree + self::getSortingTreeByCode($prefix, $curId + 1);
        }

        return $sortingTree;
    }

    public function generateRulingText(): string
    {
        $RESPONSE_DATA = '';

        foreach ($this->sortingTree as $questionItem) {
            $this->recursiveGetItemsByQuestionAndChilds($questionItem);
        }

        foreach ($this->alreadyPresentItems as $itemToShow) {
            $RESPONSE_DATA .= '<h1>' . $itemToShow['name'] . '</h1>' . $itemToShow['content'];
        }

        return $RESPONSE_DATA;
    }

    /** Очистка текста части правил Рулёжки */
    public function clearRulingItemText(string $text): string
    {
        $text = DataHelper::escapeOutput($text, EscapeModeEnum::plainHTML);
        $text = str_replace(['<p>', '<div>'], '', $text);

        return str_replace(['</p>', '</div>'], '<br>', $text);
    }

    /** Замена управляющих кодов в частях правил Рулёжки на нужные части правил */
    public function generateRulingSet(string $text): string
    {
        $selectedValues = $this->selectedValues;

        /** @var RulingItemEditService $rulingItemEditService */
        $rulingItemEditService = CMSVCHelper::getService('rulingItemEdit');

        $innerRules = $this->getInnerRules($text);

        foreach ($innerRules as $innerRule) {
            $result = '';
            $data = $innerRule[1];

            if (($data['if'] ?? false) && is_array($data['if']) && count($data['if']) > 0) {
                // проверяем на наличие всех указанных признаков
                $allValuesPresent = true;

                foreach ($data['if'] as $showIfQuestionId => $showIfQuestionAnswers) {
                    foreach ($showIfQuestionAnswers as $showIfQuestionAnswer) {
                        if (!is_array($showIfQuestionAnswers) || !is_array($selectedValues[$showIfQuestionId] ?? false) || !in_array($showIfQuestionAnswer, $selectedValues[$showIfQuestionId] ?? [])) {
                            $allValuesPresent = false;
                        }
                    }
                }

                if ($allValuesPresent) {
                    if (is_numeric($data['item'] ?? null)) {
                        $rulingItem = $rulingItemEditService->get($data['item']);
                        $result = $this->clearRulingItemText($rulingItem->content->get());
                        $this->alreadyPresentItemsIds[] = $rulingItem->id->getAsInt();
                    } elseif ($data['html'] ?? false) {
                        $result = $data['html'];
                    }
                }
            }
            $text = preg_replace('#<p>' . preg_quote($innerRule[0]) . '</p>#', $result, $text);
            $text = preg_replace('#' . preg_quote($innerRule[0]) . '<br>#', $result, $text);
            $text = preg_replace('#' . preg_quote($innerRule[0]) . '#', $result, $text);
        }

        preg_match_all('#({.+?"(item|html)":)\s*"(.*?)"}#', $text, $matches);

        if (count($matches[0]) > 0) {
            $text = $this->generateRulingSet($text);
        }

        return $text;
    }

    /** Получение json-массивов из текста модели в формате: 0 => изначальный текст (для корректной замены), 1 => успешно разобранный массив.
     * @return array<int, array{0: string, 1: array}>
     */
    public function getInnerRules(string $text, string $lookingForItems = 'item|html'): array
    {
        $result = [];

        preg_match_all('#({.+?"(' . $lookingForItems . ')":)\s*(.*?)}#', $text, $matches);

        foreach ($matches[1] as $key => $match1) {
            $itemOrHtmlData = $matches[3][$key];

            if ($matches[2][$key] === 'html') {
                $itemOrHtmlData = '"' . str_replace('"', '\"', mb_substr($matches[3][$key], 1, mb_strlen($matches[3][$key]) - 2)) . '"';
            }
            $match = preg_replace('#[\n\r]#', '', $match1 . $itemOrHtmlData . '}');
            $data = DataHelper::jsonFixedDecode($match, true);
            $result[] = [
                $matches[0][$key],
                $data,
            ];
        }

        return $result;
    }

    private function recursiveGetItemsByQuestionAndChilds(array $questionItem): void
    {
        $selectedValues = $this->selectedValues;

        if ($selectedValues[$questionItem[0]] ?? false) {
            $answersIds = $selectedValues[$questionItem[0]];

            foreach ($answersIds as $answerId) {
                if (!in_array($questionItem[0] . ':' . $answerId, $this->checkedQuestions)) {
                    $rulingItems = DB->query("SELECT * FROM ruling_item WHERE show_if->'$**.\"" . $questionItem[0] . ':' . $answerId . "\"' IS NOT NULL", []);
                    $this->checkedQuestions[] = $questionItem[0] . ':' . $answerId;

                    foreach ($rulingItems as $objData) {
                        if (!in_array($objData['id'], $this->alreadyPresentItemsIds)) {
                            // проверяем, есть ли совпадения по другим требуемым для вывода модели ответам
                            $showIfs = DataHelper::jsonFixedDecode($objData['show_if']);

                            foreach ($showIfs as $showIf) {
                                if (($showIf[$questionItem[0] . ':' . $answerId] ?? '') === 'on') {
                                    $foundFullGroup = true;

                                    foreach ($showIf as $showIfLinksData => $ignore) {
                                        // $showIfLinksData - ссылка на вопрос и ответ в формате: 2:6
                                        unset($matches);
                                        preg_match('#(\d+):(\d+)#', $showIfLinksData, $matches);

                                        if ($matches[1] > 0 && $matches[2] > 0 && is_array($selectedValues[$matches[1]])) {
                                            if (!in_array($matches[2], $selectedValues[$matches[1]])) {
                                                $foundFullGroup = false;
                                            }
                                        } elseif (!is_array($selectedValues[$matches[1]])) {
                                            $foundFullGroup = false;
                                        }
                                    }

                                    if ($foundFullGroup) {
                                        $this->alreadyPresentItems[$objData['id']] = [
                                            'name' => DataHelper::escapeOutput($objData['name']),
                                            'content' => $this->generateRulingSet($this->clearRulingItemText($objData['content'])),
                                        ];
                                        $this->alreadyPresentItemsIds[] = $objData['id'];
                                    }
                                }
                            }
                        }
                    }
                }

                foreach ($questionItem['childs'] as $answerId => $questionsData) {
                    foreach ($questionsData as $questionId) {
                        /** Да, в запросе выставлен ответ на вопрос, который дает доступ к какому-то другому вопросу, значит, переходим к анализу этого вопроса следующим */
                        if ($selectedValues[$questionItem[0]][$answerId] ?? false) {
                            $this->recursiveGetItemsByQuestionAndChilds($this->sortingTree[$questionId]);
                        }
                    }
                }
            }
        }
    }
}
