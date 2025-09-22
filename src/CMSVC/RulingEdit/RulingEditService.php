<?php

declare(strict_types=1);

namespace App\CMSVC\RulingEdit;

use App\CMSVC\Ruling\RulingService;
use Fraym\BaseObject\{BaseService, Controller, DependencyInjection};
use Fraym\Helper\DataHelper;

#[Controller(RulingEditController::class)]
class RulingEditService extends BaseService
{
    #[DependencyInjection]
    public RulingService $rulingService;
    private array $childrensList = [];

    private array $neededObjectsList = [];

    public function prepareData(): array
    {
        $selectedNodeType = '';
        $selectedNodeId = 0;

        $nodes = [];
        $links = [];
        $breakOn = 20;

        $questionsValues = [];

        $rulingQuestionId = (int) ($_REQUEST['ruling_question_id'] ?? false);
        $rulingItemId = (int) ($_REQUEST['ruling_item_id'] ?? false);

        $questionsNoShowIf = DB->query("SELECT * FROM ruling_question WHERE show_if->'$[0]' IS NULL", []);

        foreach ($questionsNoShowIf as $questionNoShowIf) {
            $questionsValues[$questionNoShowIf['id']]['name'] = DataHelper::escapeOutput($questionNoShowIf['field_name']);

            unset($matches);
            preg_match_all('#\[(\d+)]\[([^]]+)]#', DataHelper::escapeOutput($questionNoShowIf['field_values']), $matches);

            foreach ($matches[1] as $key => $value) {
                $questionsValues[$questionNoShowIf['id']][$value] = $matches[2][$key];
            }

            $nodes[] = [
                'id' => $questionNoShowIf['id'],
                'type' => 'question',
                'href' => '/ruling_question_edit/' . $questionNoShowIf['id'] . '/',
                'attr' => [
                    'label' => $this->rulingService->breakStringToArray(DataHelper::escapeOutput($questionNoShowIf['field_name']), $breakOn),
                ],
                'style' => [
                    'size' => 200,
                    'shape' => 'rectangle',
                    'fill' => '#3d4f5a',
                    'stroke' => '#3d4f5a',
                    'strokeWidth' => 4,
                    'dashed' => false,
                    'opacity' => 1,
                    'label' => [
                        'fill' => 'transparent',
                        'stroke' => 'transparent',
                    ],
                ],
            ];
        }

        $questions = DB->query("SELECT * FROM ruling_question WHERE show_if->'$[0]' IS NOT NULL", []);

        foreach ($questions as $question) {
            $questionsValues[$question['id']]['name'] = DataHelper::escapeOutput($question['field_name']);

            unset($matches);
            preg_match_all('#\[(\d+)]\[([^]]+)]#', DataHelper::escapeOutput($question['field_values']), $matches);

            foreach ($matches[1] as $key => $value) {
                $questionsValues[$question['id']][$value] = $matches[2][$key];
            }

            $nodes[] = [
                'id' => $question['id'],
                'type' => 'question',
                'href' => '/ruling_question_edit/' . $question['id'] . '/',
                'attr' => [
                    'label' => $this->rulingService->breakStringToArray(DataHelper::escapeOutput($question['field_name']), $breakOn),
                ],
                'style' => [
                    'size' => 200,
                    'shape' => 'rectangle',
                    'fill' => '#3d4f5a',
                    'stroke' => '#3d4f5a',
                    'strokeWidth' => 4,
                    'dashed' => false,
                    'opacity' => 1,
                    'label' => [
                        'fill' => 'transparent',
                        'stroke' => 'transparent',
                    ],
                ],
            ];

            /* парсим зависимости и превращаем их в линки */
            $allShowIfs = json_decode($question['show_if'], true);

            foreach ($allShowIfs as $showIfKey => $showIfValue) {
                if (count($showIfValue) > 1) {
                    // создаем маленький шарик, в который будут приходить множественные линки, чтобы понимать, как они объединяются, и линк от него
                    $nodes[] = [
                        'id' => 'question' . $question['id'] . ':' . $showIfKey,
                        'type' => 'ifs_aggregator',
                        'attr' => ['label' => []],
                        'style' => [
                            'size' => 10,
                            'shape' => 'circle',
                            'fill' => 'transparent',
                            'stroke' => '#e9f0f4',
                            'strokeWidth' => 1,
                            'dashed' => false,
                            'opacity' => 1,
                        ],
                    ];

                    $links[] = [
                        'source' => [
                            'id' => 'question' . $question['id'] . ':' . $showIfKey,
                            'type' => 'ifs_aggregator',
                        ],
                        'target' => [
                            'id' => $question['id'],
                            'type' => 'question',
                        ],
                        'attr' => ['label' => ''],
                        'etype' => 'question' . $question['id'] . ':' . $showIfKey . ':0',
                        'style' => [
                            'stroke' => '#3d4f5a',
                            'strokeWidth' => 1,
                            'dashed' => false,
                        ],
                        'directed' => true,
                    ];
                }

                $i = 1;

                foreach ($showIfValue as $showIfLinksData => $ignore) {
                    // $showIfLinksData - ссылка на вопрос и ответ в формате: 2:6
                    unset($matches);
                    preg_match('#(\d+):(\d+)#', $showIfLinksData, $matches);

                    $links[] = [
                        'source' => [
                            'id' => $matches[1],
                            'type' => 'question',
                        ],
                        'target' => [
                            'id' => count(
                                $showIfValue,
                            ) > 1 ? 'question' . $question['id'] . ':' . $showIfKey : $question['id'],
                            'type' => count($showIfValue) > 1 ? 'ifs_aggregator' : 'question',
                        ],
                        'attr' => [
                            'label' => $questionsValues[$matches[1]][$matches[2]],
                        ],
                        'etype' => 'question' . $question['id'] . ':' . $showIfKey . ':' . $i,
                        'style' => [
                            'stroke' => '#3d4f5a',
                            'strokeWidth' => 1,
                            'dashed' => false,
                        ],
                        'directed' => true,
                    ];
                    ++$i;

                    $this->childrensList['questions'][$matches[1]]['to_questions'][] = $question['id'];
                }
            }
        }

        $items = DB->select('ruling_item', []);

        foreach ($items as $item) {
            $nodes[] = [
                'id' => $item['id'],
                'type' => 'item',
                'href' => '/ruling_item_edit/' . $item['id'] . '/',
                'attr' => [
                    'label' => $this->rulingService->breakStringToArray(DataHelper::escapeOutput($item['name']), $breakOn),
                ],
                'style' => [
                    'size' => 200,
                    'shape' => 'rectangle',
                    'fill' => '#e9f0f4',
                    'stroke' => '#e9f0f4',
                    'strokeWidth' => 4,
                    'dashed' => false,
                    'opacity' => 1,
                    'label' => [
                        'fill' => 'transparent',
                        'stroke' => 'transparent',
                    ],
                ],
            ];

            /* парсим зависимости и превращаем их в линки */
            $allShowIfs = json_decode($item['show_if'], true);

            foreach ($allShowIfs as $showIfKey => $showIfValue) {
                if (count($showIfValue) > 1) {
                    // создаем маленький шарик, в который будут приходить множественные линки, чтобы понимать, как они объединяются, и линк от него
                    $nodes[] = [
                        'id' => 'item' . $item['id'] . ':' . $showIfKey,
                        'type' => 'ifs_aggregator',
                        'attr' => ['label' => []],
                        'style' => [
                            'size' => 10,
                            'shape' => 'circle',
                            'fill' => 'transparent',
                            'stroke' => '#e9f0f4',
                            'strokeWidth' => 1,
                            'dashed' => false,
                            'opacity' => 1,
                        ],
                    ];

                    $links[] = [
                        'source' => [
                            'id' => 'item' . $item['id'] . ':' . $showIfKey,
                            'type' => 'ifs_aggregator',
                        ],
                        'target' => [
                            'id' => $item['id'],
                            'type' => 'item',
                        ],
                        'attr' => ['label' => ''],
                        'etype' => 'item' . $item['id'] . ':' . $showIfKey . ':0',
                        'style' => [
                            'stroke' => '#3d4f5a',
                            'strokeWidth' => 1,
                            'dashed' => false,
                        ],
                        'directed' => true,
                    ];
                }

                $i = 1;

                foreach ($showIfValue as $showIfLinksData => $ignore) {
                    // $showIfLinksData - ссылка на вопрос и ответ в формате: 2:6
                    unset($matches);
                    preg_match('#(\d+):(\d+)#', $showIfLinksData, $matches);

                    $links[] = [
                        'source' => [
                            'id' => $matches[1],
                            'type' => 'question',
                        ],
                        'target' => [
                            'id' => count($showIfValue) > 1 ? 'item' . $item['id'] . ':' . $showIfKey : $item['id'],
                            'type' => count($showIfValue) > 1 ? 'ifs_aggregator' : 'item',
                        ],
                        'attr' => [
                            'label' => $questionsValues[$matches[1]][$matches[2]],
                        ],
                        'etype' => 'item' . $item['id'] . ':' . $showIfKey . ':' . $i,
                        'style' => [
                            'stroke' => '#3d4f5a',
                            'strokeWidth' => 1,
                            'dashed' => false,
                        ],
                        'directed' => true,
                    ];
                    ++$i;

                    $this->childrensList['questions'][$matches[1]]['to_items'][] = $item['id'];
                }
            }

            /* парсим контент и проверяем на зависимости ссылками внутри моделей */
            preg_match_all('#{.+?"item"[^}]+}#', DataHelper::escapeOutput($item['content']), $matches);

            foreach ($matches[0] as $showIfKey => $match) {
                // каждый $match - это ссылка внутри текущей модели на какую-то другую. Ссылка становится активной, если на определенный набор вопросов даны определенные ответы. Может быть один вопрос и один ответ, но чаще - много вопросов с одним ответом или один вопрос со многими ответами.
                $data = json_decode($match, true);

                if (is_array($data['if']) && count($data['if']) > 0 && is_numeric($data['item'])) {
                    // проверяем на наличие всех указанных признаков
                    $allValuesPresent = true;

                    foreach ($data['if'] as $questionData) {
                        if (!is_array($questionData)) {
                            $allValuesPresent = false;
                        }
                    }

                    if ($allValuesPresent) {
                        $manyQuestionsOrAnswers = false;

                        if (count($data['if']) > 1) {
                            $manyQuestionsOrAnswers = true;
                        } else {
                            foreach ($data['if'] as $checkData) {
                                if (count($checkData) > 1) {
                                    $manyQuestionsOrAnswers = true;
                                }
                            }
                        }

                        if ($manyQuestionsOrAnswers) {
                            // если много вопросов или много ответов, то создаем маленький шарик, в который будут приходить множественные линки, чтобы понимать, как они объединяются, и линк от него к модели-цели
                            $nodes[] = [
                                'id' => 'outsideItem' . $item['id'] . ':' . $showIfKey,
                                'type' => 'ifs_aggregator',
                                'attr' => ['label' => []],
                                'style' => [
                                    'size' => 10,
                                    'shape' => 'circle',
                                    'fill' => 'transparent',
                                    'stroke' => '#e9f0f4',
                                    'strokeWidth' => 1,
                                    'dashed' => true,
                                    'opacity' => 1,
                                ],
                            ];

                            $links[] = [
                                'source' => [
                                    'id' => 'outsideItem' . $item['id'] . ':' . $showIfKey,
                                    'type' => 'ifs_aggregator',
                                ],
                                'target' => [
                                    'id' => (int) $data['item'],
                                    'type' => 'item',
                                ],
                                'attr' => ['label' => ''],
                                'etype' => 'outsideItem' . $item['id'] . ':' . $showIfKey . ':0',
                                'style' => [
                                    'stroke' => '#3d4f5a',
                                    'strokeWidth' => 1,
                                    'dashed' => true,
                                ],
                                'directed' => true,
                            ];
                        }

                        foreach ($data['if'] as $showIfQuestionId => $showIfQuestionAnswers) {
                            foreach ($showIfQuestionAnswers as $showIfQuestionAnswerId => $showIfQuestionAnswer) {
                                $links[] = [
                                    'source' => [
                                        'id' => $item['id'],
                                        'type' => 'item',
                                    ],
                                    'target' => [
                                        'id' => $manyQuestionsOrAnswers ? 'outsideItem' . $item['id'] . ':' . $showIfKey : (int) $data['item'],
                                        'type' => $manyQuestionsOrAnswers ? 'ifs_aggregator' : 'item',
                                    ],
                                    'attr' => [
                                        'label' => $questionsValues[(int) $showIfQuestionId]['name'] . ' = ' . $questionsValues[(int) $showIfQuestionId][(int) $showIfQuestionAnswer],
                                    ],
                                    'etype' => 'outsideItem' . $item['id'] . ':' . $showIfKey . ':' . (int) $showIfQuestionId . ':' . (int) $showIfQuestionAnswer,
                                    'style' => [
                                        'stroke' => '#3d4f5a',
                                        'strokeWidth' => 1,
                                        'dashed' => true,
                                    ],
                                    'directed' => true,
                                ];

                                $this->childrensList['items'][$item['id']]['to_items'][] = (int) $data['item'];
                            }
                        }
                    }
                }
            }
        }

        /* если установлен конкретный вопрос или модель, то выкидываем все лишние объекты из выборки */
        if ($rulingQuestionId > 0 || $rulingItemId > 0) {
            $this->neededObjectsList = [
                'questions' => [],
                'ifs_aggregators' => [],
                'links' => [],
            ];

            /* сначала устанавливаем все зависимые модели */
            if ($rulingItemId > 0) {
                $this->neededObjectsList['items'] = $this->rehashChildrenListTree($rulingItemId, 'item');
            } else {
                $this->neededObjectsList['items'] = $this->rehashChildrenListTree($rulingQuestionId);
            }

            /* если это выборка по вопросу, то добавляем всю лесенку от вопроса вниз */
            if ($rulingQuestionId > 0) {
                $this->makeTreeOfChildQuestions($this->childrensList['questions'][$rulingQuestionId]['to_questions']);
            }

            /* оставляем также вопросы, ведущие к данным моделям / вопросам на один шаг от них */
            foreach ($this->neededObjectsList['items'] as $neededItemId) {
                foreach ($this->childrensList['questions'] as $questionId => $questionData) {
                    if (!in_array($questionId, $this->neededObjectsList['questions'])) {
                        if (in_array($neededItemId, $questionData['to_items'])) {
                            $this->neededObjectsList['questions'][] = $questionId;
                        }
                    }
                }
            }

            if ($rulingQuestionId > 0) {
                foreach ($this->childrensList['questions'] as $questionId => $questionData) {
                    if (!in_array($questionId, $this->neededObjectsList['questions'])) {
                        if (in_array($rulingQuestionId, $questionData['to_questions'])) {
                            $this->neededObjectsList['questions'][] = $questionId;
                        }
                    }
                }
            }

            /* добавляем все нужные link'и */
            foreach ($links as $linkId => $linkData) {
                $ifsAggregatorType = false;

                if (in_array(
                    $linkData['source']['id'],
                    $this->neededObjectsList[$linkData['source']['type'] . 's'],
                ) && in_array($linkData['target']['id'], $this->neededObjectsList[$linkData['target']['type'] . 's'])) {
                    if (!in_array($linkId, $this->neededObjectsList['links'])) {
                        $this->neededObjectsList['links'][] = $linkId;
                    }
                } elseif (in_array(
                    $linkData['source']['id'],
                    $this->neededObjectsList[$linkData['source']['type'] . 's'],
                ) && $linkData['target']['type'] === 'ifs_aggregator') {
                    $ifsAggregatorType = 'target';
                } elseif (in_array(
                    $linkData['target']['id'],
                    $this->neededObjectsList[$linkData['target']['type'] . 's'],
                ) && $linkData['source']['type'] === 'ifs_aggregator') {
                    $ifsAggregatorType = 'source';
                }

                if ($ifsAggregatorType) {
                    /* проверяем, что от ifs_aggregator ссылка идет к нужному нам объекту, и добавляем его, если это так */
                    $ifsAggregatorId = $linkData[$ifsAggregatorType]['id'];
                    $invertedIfsAggregatorType = ($ifsAggregatorType === 'source' ? 'target' : 'source');

                    foreach ($links as $linkId2 => $linkData2) {
                        if ($linkData2[$invertedIfsAggregatorType]['type'] === 'ifs_aggregator' && $linkData2[$invertedIfsAggregatorType]['id'] === $ifsAggregatorId && in_array(
                            $linkData2[$ifsAggregatorType]['id'],
                            $this->neededObjectsList[$linkData2[$ifsAggregatorType]['type'] . 's'],
                        )) {
                            if (!in_array($linkId, $this->neededObjectsList['links'])) {
                                $this->neededObjectsList['links'][] = $linkId;
                            }

                            if (!in_array($linkId2, $this->neededObjectsList['links'])) {
                                $this->neededObjectsList['links'][] = $linkId2;
                            }

                            if (!in_array($ifsAggregatorId, $this->neededObjectsList['ifs_aggregators'])) {
                                $this->neededObjectsList['ifs_aggregators'][] = $ifsAggregatorId;
                            }
                        }
                    }
                }
            }

            foreach ($nodes as $nodeId => $nodeData) {
                if (($nodeData['type'] === 'item' && !in_array(
                    $nodeData['id'],
                    $this->neededObjectsList['items'],
                )) || ($nodeData['type'] === 'question' && !in_array(
                    $nodeData['id'],
                    $this->neededObjectsList['questions'],
                )) || ($nodeData['type'] === 'ifs_aggregator' && !in_array(
                    $nodeData['id'],
                    $this->neededObjectsList['ifs_aggregators'],
                ))) {
                    unset($nodes[$nodeId]);
                } elseif (($rulingQuestionId > 0 && $nodeData['type'] === 'question' && $nodeData['id'] === $rulingQuestionId) || ($rulingItemId > 0 && $nodeData['type'] === 'item' && $nodeData['id'] === $rulingItemId)) {
                    $selectedNodeType = $nodeData['type'];
                    $selectedNodeId = $nodeData['id'];
                }
            }
            sort($nodes);

            foreach ($links as $linkId => $linkData) {
                if (!in_array($linkId, $this->neededObjectsList['links'])) {
                    unset($links[$linkId]);
                }
            }
            sort($links);
        }

        return [$nodes, $links, $selectedNodeType, $selectedNodeId];
    }

    public function rehashChildrenListTree($objectId, $objectType = 'question')
    {
        $array = [];

        if ($objectType === 'question') {
            foreach ($this->childrensList['questions'][$objectId]['to_questions'] as $dependentQuestionId) {
                $array = array_merge($array, $this->rehashChildrenListTree($dependentQuestionId));
            }

            foreach ($this->childrensList['questions'][$objectId]['to_items'] as $dependentItemId) {
                $array = array_merge($array, $this->rehashChildrenListTree($dependentItemId, 'item'));
            }
        } elseif ($objectType === 'item') {
            $array[] = $objectId;

            foreach ($this->childrensList['items'][$objectId]['to_items'] as $dependentItemId) {
                $array = array_merge($array, $this->rehashChildrenListTree($dependentItemId, 'item'));
            }

            foreach ($this->childrensList['items'] as $parentItemKey => $parentItemData) {
                if (in_array($objectId, $parentItemData['to_items'])) {
                    $array[] = $parentItemKey;
                }
            }
        }

        return $array;
    }

    public function makeTreeOfChildQuestions($questionsArray): void
    {
        foreach ($questionsArray as $questionId) {
            if (!in_array($questionId, $this->neededObjectsList['questions'])) {
                $this->neededObjectsList['questions'][] = $questionId;
                $this->makeTreeOfChildQuestions($this->childrensList['questions'][$questionId]['to_questions']);
            }
        }
    }
}
