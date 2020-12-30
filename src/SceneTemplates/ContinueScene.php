<?php
declare(strict_types=1);

namespace LotGD\Module\NewDay\SceneTemplates;

use LotGD\Core\SceneTemplates\SceneTemplateInterface;
use LotGD\Module\NewDay\Module;

class ContinueScene implements SceneTemplateInterface
{
    public static function getNavigationEvent(): string
    {
        return Module::SceneContinue;
    }
}