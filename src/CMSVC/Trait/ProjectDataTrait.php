<?php

declare(strict_types=1);

namespace App\CMSVC\Trait;

use App\CMSVC\Project\{ProjectModel, ProjectService};
use App\Helper\RightsHelper;
use Fraym\Enum\{ActEnum, ActionEnum};
use Fraym\Helper\{CMSVCHelper, CookieHelper, DataHelper};

/** Данные проекта в сервисах */
trait ProjectDataTrait
{
    private ?int $activatedProjectId = null;
    private ?ProjectModel $projectData = null;

    public function getProjectData(?int $projectId = null): ?ProjectModel
    {
        if (is_null($this->projectData)) {
            if (is_null($projectId)) {
                if (in_array(KIND, ['myapplication', 'ingame'])) {
                    if (DataHelper::getId()) {
                        $applicationData = DB->findObjectById(DataHelper::getId(), 'project_application');
                        $this->activatedProjectId = $applicationData['project_id'];
                    } elseif (KIND === 'ingame' && CookieHelper::getCookie('ingame_application_id')) {
                        $applicationData = DB->findObjectById(CookieHelper::getCookie('ingame_application_id'), 'project_application');
                        $this->activatedProjectId = $applicationData['project_id'];
                    } elseif ($this->act === ActEnum::add && ($_REQUEST['project_id'] ?? false)) {
                        $this->activatedProjectId = (int) $_REQUEST['project_id'];
                    } elseif (ACTION === ActionEnum::create && ($_REQUEST['project_id'][0] ?? false)) {
                        $this->activatedProjectId = (int) $_REQUEST['project_id'][0];
                    }
                } else {
                    if (is_null($this->activatedProjectId)) {
                        $this->activatedProjectId = RightsHelper::getActivatedProjectId();
                    }
                }
            } else {
                $this->activatedProjectId = $projectId;
            }

            if ((int) $this->activatedProjectId > 0) {
                /** @var ProjectService */
                $projectService = CMSVCHelper::getService('project');
                $this->projectData = $projectService->get($this->activatedProjectId);
            }
        }

        return $this->projectData;
    }

    public function getProjectId(): ?int
    {
        $projectId = $this->getProjectData()?->id->get();

        return $projectId ? (int) $projectId : null;
    }

    public function getActivatedProjectId(): ?int
    {
        if (is_null($this->activatedProjectId)) {
            $this->getProjectData();
        }

        return $this->activatedProjectId;
    }

    public function checkRightsRestrict(): string
    {
        return 'project_id=' . $this->getActivatedProjectId();
    }
}
