<?php

declare(strict_types=1);

namespace App\CMSVC\Event;

use App\CMSVC\Conversation\ConversationService;
use App\CMSVC\Trait\UserServiceTrait;
use App\Helper\{MessageHelper, RightsHelper};
use DateTimeImmutable;
use Fraym\BaseObject\{BaseModel, BaseService, Controller};
use Fraym\Entity\{PostChange, PostCreate, PostDelete, PreChange};
use Fraym\Enum\{ActEnum, EscapeModeEnum};
use Fraym\Helper\{CMSVCHelper, DataHelper, LocaleHelper};

/** @extends BaseService<EventModel> */
#[PostCreate]
#[PreChange]
#[PostChange]
#[PostDelete]
#[Controller(EventController::class)]
class EventService extends BaseService
{
    use UserServiceTrait;

    private ?int $objId = null;
    private ?string $objType = null;
    private ?array $messageData = null;
    private ?EventModel $savedEventData = null;

    public function postCreate(array $successfulResultsIds): void
    {
        $objId = $this->getObjId();
        $objType = $this->getObjType();

        foreach ($successfulResultsIds as $successfulResultsId) {
            RightsHelper::addRights('{admin}', '{event}', $successfulResultsId);
            RightsHelper::addRights('{member}', '{event}', $successfulResultsId);

            if (!is_null($objId) && !is_null($objType)) {
                RightsHelper::addRights('{child}', '{' . $objType . '}', $objId, '{event}', $successfulResultsId);
                $this->entity->fraymActionRedirectPath = ABSOLUTE_PATH . '/' . $objType . '/' . $objId . '/';
            }

            if (isset($_REQUEST['user_id'][0])) {
                /** @var ConversationService $conversationService */
                $conversationService = CMSVCHelper::getService('conversation');

                foreach ($_REQUEST['user_id'][0] as $key => $value) {
                    if ($value === 'on' && $key !== CURRENT_USER->id()) {
                        $conversationService->sendInvitation('{event}', $successfulResultsId, $key);
                    }
                }
            }

            if ($this->getMessageIdDefault()) {
                RightsHelper::addRights(
                    '{child}',
                    '{conversation_message}',
                    $this->getMessageIdDefault(),
                    '{event}',
                    $successfulResultsId,
                );
            }
        }
    }

    public function preChange(): void
    {
        $this->savedEventData = $this->get(DataHelper::getId());
    }

    public function postChange(array $successfulResultsIds): void
    {
        $LOCALE = $this->LOCALE;
        $LOCALE_GLOBAL = LocaleHelper::getLocale(['global']);

        foreach ($successfulResultsIds as $id) {
            $bosses = $this->getBosses();
            $members = $this->getMembers();

            if (isset($_REQUEST['user_id'][0])) {
                /** @var ConversationService $conversationService */
                $conversationService = CMSVCHelper::getService('conversation');

                foreach ($_REQUEST['user_id'][0] as $key => $value) {
                    if ($value === 'on' && $key !== CURRENT_USER->id() && !in_array($key, $bosses)) {
                        if (!in_array($key, $members)) {
                            $conversationService->sendInvitation('{event}', $id, $key);
                        }
                    }
                }
            }

            foreach ($members as $key => $value) {
                if ($_REQUEST['user_id'][0][$value] !== 'on' && $value !== CURRENT_USER->id() && !in_array($value, $bosses)) {
                    RightsHelper::deleteRights('{member}', '{event}', $id, '{user}', $value);
                    unset($members[$key]);
                }
            }

            $savedEventData = $this->savedEventData;
            $changedEventData = $this->get($id, null, null, true);
            $userData = $this->getUserService()->get(CURRENT_USER->id());
            $message = sprintf(
                $LOCALE['event_change_message_1'],
                $this->getUserService()->showName($userData),
                $userData->gender->get() === 2 ? 'а' : '',
                ABSOLUTE_PATH . '/',
                $savedEventData->id->getAsInt(),
                DataHelper::escapeOutput($savedEventData->name->get()),
                ($savedEventData->date_from->getAsUsualDateTime() !== date('Y-m-d H:i', strtotime($_REQUEST['date_from'][0])) ? '<i>' : '') .
                    $changedEventData->date_from->getAsUsualDateTime() .
                    ($savedEventData->date_from->getAsUsualDateTime() !== date('Y-m-d H:i', strtotime($_REQUEST['date_from'][0])) ? '</i>' : ''),
                ($savedEventData->date_to->getAsUsualDateTime() !== date('Y-m-d H:i', strtotime($_REQUEST['date_to'][0])) ? '<i>' : '') .
                    $changedEventData->date_to->getAsUsualDateTime() .
                    ($savedEventData->date_to->getAsUsualDateTime() !== date('Y-m-d H:i', strtotime($_REQUEST['date_to'][0])) ? '</i>' : ''),
            );
            $message .= DataHelper::escapeOutput($changedEventData->description->get(), EscapeModeEnum::forHTMLforceNewLines);

            MessageHelper::prepareEmails($members, [
                'author_name' => $LOCALE_GLOBAL['sitename'],
                'author_email' => $LOCALE_GLOBAL['admin_mail'],
                'name' => $LOCALE['event_change_message_2'],
                'content' => $message,
                'obj_type' => 'event',
                'obj_id' => $id,
            ]);
        }
    }

    public function postDelete(array $successfulResultsIds): void
    {
        $this->entity->fraymActionRedirectPath = ABSOLUTE_PATH . '/' . $this->getObjType() . '/' . $this->getObjId() . '/';

        foreach ($successfulResultsIds as $id) {
            $id = DataHelper::getId();
            RightsHelper::deleteRights(null, '{event}', $id, '{user}', 0);
            RightsHelper::deleteRights(null, '{task}', $id, '{file}');
            RightsHelper::deleteRights(null, '{task}', $id, '{conversation}');
            RightsHelper::deleteRights(null, null, null, '{event}', $id);
        }
    }

    public function isEventAdmin(int $eventId): bool
    {
        return RightsHelper::checkRights('{admin}', '{event}', $eventId);
    }

    public function hasEventAccess(int $eventId): bool
    {
        return $this->isEventAdmin($eventId) || RightsHelper::checkAnyRights('{event}', $eventId);
    }

    public function hasEventParentAccess(): bool
    {
        if (is_null($this->getObjId())) {
            return false;
        }

        return RightsHelper::checkAnyRights($this->getObjType(), $this->getObjId());
    }

    public function hasAccessToChilds(): bool
    {
        $parentData = $this->getParentData();

        return ($parentData['access_to_childs'] ?? false) === 1;
    }

    public function getParentData(): ?array
    {
        if (is_null($this->getObjId())) {
            return null;
        }

        return DB->findObjectById($this->getObjId(), $this->getObjType());
    }

    public function getBosses(): ?array
    {
        return RightsHelper::findByRights(['{admin}', '{responsible}'], '{event}', DataHelper::getId(), '{user}', false);
    }

    public function getMembers(): ?array
    {
        return RightsHelper::findByRights(null, '{event}', DataHelper::getId(), '{user}', false);
    }

    public function getObjType(): ?string
    {
        if (is_null($this->objType)) {
            /** Инициируем objId сначала, потому что от нее может напрямую зависеть objType */
            $this->getObjId();
            /** @var ?string */
            $objType = $this->objType;

            if (is_null($objType)) {
                $requestObjType = $_REQUEST['obj_type'] ?? null;
                $objType = is_array($requestObjType) ? $requestObjType[0] ?? $requestObjType : $requestObjType;

                if ($objType === '') {
                    $objType = null;
                }

                if (DataHelper::getId() > 0 && is_null($objType)) {
                    $objType = 'project';
                }

                $this->objType = $objType;
            }
        }

        return $this->objType;
    }

    public function getObjId(): int|string|null
    {
        if (is_null($this->objId)) {
            $requestObjId = $_REQUEST['obj_id'] ?? null;
            $objId = is_array($requestObjId) ? $requestObjId[0] ?? $requestObjId : $requestObjId;

            if ($objId === '') {
                $objId = null;
            }

            if (!is_null($objId)) {
                $objId = (int) $objId;
            }

            if (DataHelper::getId() > 0 && ($this->act === ActEnum::edit || $this->act === ActEnum::view)) {
                $objId = RightsHelper::findOneByRights('{child}', '{project}', null, '{event}', DataHelper::getId());

                if (!is_null($objId)) {
                    $this->objType = 'project';
                } else {
                    $objId = RightsHelper::findOneByRights('{child}', '{community}', null, '{event}', DataHelper::getId());

                    if (!is_null($objId)) {
                        $this->objType = 'community';
                    }
                }
            }

            $this->objId = $objId;
        }

        return $this->objId;
    }

    public function getObjIdValues(): array
    {
        $objType = $this->getObjType();
        $result = [];

        if ($objType === 'project') {
            $projectsArray = RightsHelper::findByRights(null, '{project}');

            if ($projectsArray) {
                $projectsList = [];
                $projectsData = DB->select(
                    'project',
                    ['id' => $projectsArray],
                    false,
                    ['name'],
                );

                foreach ($projectsData as $projectData) {
                    $projectsList[] = [$projectData['id'], DataHelper::escapeOutput($projectData['name'])];
                }
                $result = $projectsList;

                $this->postModelInitVars['objType'] = 'project';
            }
        } elseif ($objType === 'community') {
            $communitiesArray = RightsHelper::findByRights(null, '{community}');

            if ($communitiesArray) {
                $communitiesList = [];
                $communitiesData = DB->select(
                    'community',
                    ['id' => $communitiesArray],
                    false,
                    ['name'],
                );

                foreach ($communitiesData as $communityData) {
                    $communitiesList[] = [$communityData['id'], DataHelper::escapeOutput($communityData['name'])];
                }
                $result = $communitiesList;
            }
        }

        return $result;
    }

    public function getUserIdDefault(): array
    {
        $result = [CURRENT_USER->id()];

        if (DataHelper::getId() > 0) {
            $result = $this->getMembers();
        } else {
            $messageData = $this->getMessageData();

            if ($messageData) {
                $messages = DB->select(
                    'conversation_message',
                    ['conversation_id' => $messageData['conversation_id']],
                );

                foreach ($messages as $message) {
                    $result[] = $message['creator_id'];
                }
                $result = array_unique($result);
            }
        }

        return $result;
    }

    public function getUserIdValues(): array
    {
        $result = [];

        $objId = $this->getObjId();
        $objType = $this->getObjType();

        if ($objId > 0 && in_array($objType, ['project', 'community'])) {
            $parentMembers = RightsHelper::findByRights(
                ['{admin}', '{moderator}', '{member}'],
                $objType,
                $objId,
                '{user}',
                false,
            );

            if (!is_null($parentMembers)) {
                $parentMembersData = [];

                foreach ($parentMembers as $memberId) {
                    $parentMembersData[] = [
                        $memberId,
                        $this->getUserService()->showName($this->getUserService()->get($memberId)),
                    ];
                }
                $parentMembersDataSort = [];

                foreach ($parentMembersData as $key => $row) {
                    $parentMembersDataSort[$key] = $row[1];
                }
                array_multisort($parentMembersDataSort, SORT_ASC, $parentMembersData);
                $result = $parentMembersData;
            }
        } elseif (is_null($this->getObjId())) {
            $result[] = [
                CURRENT_USER->id(),
                $this->getUserService()->showName($this->getUserService()->get(CURRENT_USER->id())),
            ];
        }

        return $result;
    }

    public function getUserIdLocked(): ?array
    {
        $result = [];

        if (DataHelper::getId() > 0) {
            $result = $this->getBosses();
        } else {
            $result = [CURRENT_USER->id()];
        }

        return $result;
    }

    public function getMessageData(): ?array
    {
        if (is_null($this->messageData)) {
            if (($_REQUEST['message_id'] ?? false) && $this->act === ActEnum::add) {
                $messageData = DB->findObjectById($_REQUEST['message_id'][0] ?? $_REQUEST['message_id'], 'conversation_message');

                if ($messageData) {
                    $this->messageData = $messageData;
                }
            }
        }

        return $this->messageData;
    }

    public function getMessageIdDefault(): ?int
    {
        $messageData = $this->getMessageData();

        if ($messageData) {
            return $messageData['id'];
        }

        return null;
    }

    public function getDescriptionDefault(): string
    {
        $messageData = $this->getMessageData();

        if ($messageData) {
            return DataHelper::escapeOutput($messageData['content']);
        }

        return '';
    }

    public function getDateFromDefault(): DateTimeImmutable
    {
        return new DateTimeImmutable('+1 hour');
    }

    public function getDateToDefault(): DateTimeImmutable
    {
        return new DateTimeImmutable('+2 hours');
    }

    public function checkViewRights(): bool
    {
        return CURRENT_USER->isLogged() && (is_null(DataHelper::getId()) || $this->hasEventAccess(DataHelper::getId()));
    }

    public function checkAddRights(): bool
    {
        if (is_null($this->getObjType()) && is_null($this->getObjId())) {
            return true;
        }

        return RightsHelper::checkAnyRights($this->getObjType(), $this->getObjId());
    }

    public function checkChangeRights(): bool
    {
        return RightsHelper::checkRights('{admin}', '{event}', DataHelper::getId());
    }

    public function postModelInit(BaseModel $model): BaseModel
    {
        if (($this->postModelInitVars['objType'] ?? false) === 'project') {
            $LOCALE = $this->LOCALE;

            if ($model->getElement('obj_id')) {
                $model->getElement('obj_id')->shownName = $LOCALE['project'];
            }
        }

        return $model;
    }
}
