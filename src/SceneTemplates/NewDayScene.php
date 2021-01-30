<?php
declare(strict_types=1);

namespace LotGD\Module\NewDay\SceneTemplates;

use LotGD\Core\Models\Scene;
use LotGD\Core\Models\SceneTemplate;
use LotGD\Core\SceneTemplates\SceneTemplateInterface;
use LotGD\Module\NewDay\Module;

class NewDayScene implements SceneTemplateInterface
{
    public static function getNavigationEvent(): string
    {
        return Module::SceneNewDay;
    }

    public static function getScaffold(): Scene
    {
        return new Scene(
            title: "It is a new day!",
            description: "You open your eyes to discover that a new day has been bestowed upon you. "
                ."You feel refreshed enough to take on the world!",
            template: new SceneTemplate(class: self::class, module: Module::Module),
        );
    }
}