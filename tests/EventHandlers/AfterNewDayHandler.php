<?php
declare(strict_types=1);

namespace LotGD\Module\NewDay\Tests\EventHandlers;

use LotGD\Core\Game;
use LotGD\Core\EventHandler;
use LotGD\Core\Events\EventContext;
use LotGD\Core\Models\Scene;

class AfterNewDayHandler implements EventHandler
{
    public static function handleEvent(Game $g, EventContext $context): EventContext {
        $viewpoint = $context->getDataField("viewpoint");
        $character = $g->getCharacter();

        if ($character->getHealth() > 0) {
            $viewpoint->addDescriptionParagraph("You regenerate your health.");
            $g->getCharacter()->setHealth($g->getCharacter()->getMaxHealth());
        } else {
            $viewpoint->addDescriptionParagraph("You have been getting revived. Your health has replenished, but you feel a bit tired.");
            $g->getCharacter()->setHealth($g->getCharacter()->getMaxHealth() - 1);
        }

        return $context;
    }
}