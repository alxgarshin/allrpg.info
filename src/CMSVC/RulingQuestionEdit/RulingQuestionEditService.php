<?php

declare(strict_types=1);

namespace App\CMSVC\RulingQuestionEdit;

use App\CMSVC\Ruling\RulingService;
use App\CMSVC\User\UserService;
use Fraym\BaseObject\{BaseService, Controller};
use Fraym\Enum\{ActEnum, OperandEnum};
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper};

/** @extends BaseService<RulingQuestionEditModel> */
#[Controller(RulingQuestionEditController::class)]
class RulingQuestionEditService extends BaseService
{
    private ?array $showIfValues = null;

    public function getObjHelper1Default(): string
    {
        if ($this->getAct() === ActEnum::edit && DataHelper::getId() > 0) {
            $LOCALE = LocaleHelper::getLocale(['fraym']);

            return '<a href="' . ABSOLUTE_PATH . '/ruling_edit/ruling_question_id=' . DataHelper::getId() . '" target="_blank">' . $LOCALE['functions']['open_in_a_new_window'] . '</a>';
        }

        return '';
    }

    public function getSortId(): array
    {
        /** @var RulingService $rulingService */
        $rulingService = CMSVCHelper::getService('ruling');

        return $rulingService->getSortingTree();
    }

    public function getShowIfValues(string $kind = KIND): array
    {
        if (is_null($this->showIfValues)) {
            $showIfValues = [];
            $params = [];

            if ($kind === 'ruling_question_edit' && DataHelper::getId() > 0) {
                $params[] = ['id', DataHelper::getId(), [OperandEnum::NOT_EQUAL]];
            }
            $rulingQuestions = DB->select(
                'ruling_question',
                $params,
            );

            foreach ($rulingQuestions as $rulingQuestion) {
                $rulingQuestion['field_values'] = DataHelper::escapeOutput($rulingQuestion['field_values']);
                preg_match_all('#\[(\d+)\]\[([^\]]+)\]#', $rulingQuestion['field_values'], $matches);

                foreach ($matches[1] as $key => $value) {
                    $showIfValues[] = [
                        $rulingQuestion['id'] . ':' . $value,
                        '<a href="' . ABSOLUTE_PATH . '/ruling_question_edit/' . $rulingQuestion['id'] . '/" target="_blank"><b>' . DataHelper::escapeOutput($rulingQuestion['field_name']) . '</b></a>: ' . $matches[2][$key],
                    ];
                }
            }

            $this->showIfValues = $showIfValues;
        }

        return $this->showIfValues;
    }

    public function checkRights(): bool
    {
        /** @var UserService $userService */
        $userService = CMSVCHelper::getService('user');

        return $userService->isRulingAdmin();
    }

    public function checkRightsDelete(): bool
    {
        return CURRENT_USER->isAdmin();
    }
}
