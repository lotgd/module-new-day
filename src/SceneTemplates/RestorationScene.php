<?php
declare(strict_types=1);

namespace LotGD\Module\NewDay\SceneTemplates;

use LotGD\Core\Models\Scene;
use LotGD\Core\Models\SceneTemplate;
use LotGD\Core\SceneTemplates\SceneTemplateInterface;
use LotGD\Module\NewDay\Module;

class RestorationScene implements SceneTemplateInterface
{
    public static function getNavigationEvent(): string
    {
        return Module::SceneRestoration;
    }

    public static function getScaffold(): Scene
    {
        return new Scene(
            title: "Continue",
            description: "You should not be able to see this text if everything works, "
                ."this scene should restore your viewpoint.",
            template: new SceneTemplate(class: self::class, module: Module::Module),
        );
    }
}