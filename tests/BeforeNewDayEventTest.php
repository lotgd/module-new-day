<?php
declare(strict_types=1);

namespace LotGD\Module\NewDay\Tests;

use Doctrine\Common\Util\Debug;
use LotGD\Core\Game;
use LotGD\Core\Models\Character;
use LotGD\Core\Models\EventSubscription;


class BeforeNewDayEventTest extends ModuleTestCase
{
    protected $dataset = "before-new-day";

    public function testIfRedirectInNewDayWorks()
    {
        /** @var Game $game */
        $game = $this->g;
        $character = $this->getEntityManager()->getRepository(Character::class)->findById(2)[0];
        $game->setCharacter($character);

        define("USE_DEBUGGING", true);
        $v = $game->getViewpoint();

        // The scene should lead to a new day, except that EventHandlers/BeforeNewDayHandler should interfere
        // and redirect to "Another Scene" instead of newDay.
        $this->assertSame("Another Scene", $v->getTitle());
    }
}