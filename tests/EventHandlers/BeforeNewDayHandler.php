<?php
declare(strict_types=1);

namespace LotGD\Module\NewDay\Tests\EventHandlers;

use LotGD\Core\Game;
use LotGD\Core\EventHandler;
use LotGD\Core\Events\EventContext;
use LotGD\Core\Models\Scene;

class BeforeNewDayHandler implements EventHandler
{
    public static function handleEvent(Game $g, EventContext $context): EventContext {
        // Get a new scene
        $redirect = $g->getEntityManager()->getRepository(Scene::class)->findOneBy(["template" => "lotgd/test/another"]);
        $context->setDataField("redirect", $redirect);

        return $context;
    }
}