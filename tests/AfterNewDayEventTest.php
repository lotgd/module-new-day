<?php
declare(strict_types=1);

namespace LotGD\Module\NewDay\Tests;

use Doctrine\Common\Util\Debug;
use LotGD\Core\Models\Character;


class AfterNewDayEventTest extends ModuleTestCase
{
    protected $dataset = "after-new-day";

    public function testIfAfterNewDayGetsOnlyExecutedOnce()
    {
        /** @var $game Game */
        $game = $this->g;
        $character = $this->getEntityManager()->getRepository(Character::class)->find("10000000-0000-0000-0000-000000000003");
        $game->setCharacter($character);
        $v = $game->getViewpoint();

        # Debug::dump($v);
        $this->assertSame($character->getMaxHealth()-1, $character->getHealth());
    }
}