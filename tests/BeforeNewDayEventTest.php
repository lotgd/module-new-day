<?php
declare(strict_types=1);

namespace LotGD\Module\NewDay\Tests;

use LotGD\Core\Models\Character;
use LotGD\Core\Tests\ModelTestCase;


class BeforeNewDayEventTest extends ModelTestCase
{
    use ModuleTestCase;

    const Library = 'lotgd/module-new-day';
    const RootNamespace = "LotGD\\Module\\NewDay\\";

    protected $g;
    protected $moduleModel;

    protected function getDataSet(): \PHPUnit_Extensions_Database_DataSet_YamlDataSet
    {
        return new \PHPUnit_Extensions_Database_DataSet_YamlDataSet(implode(DIRECTORY_SEPARATOR, [__DIR__, 'datasets', 'before-new-day.yml']));
    }

    public function testIfRedirectInNewDayWorks()
    {
        /** @var $game Game */
        $game = $this->g;
        $character = $this->getEntityManager()->getRepository(Character::class)->findById(1)[0];
        $game->setCharacter($character);
        $v = $game->getViewpoint();

        // The scene should lead to a new day, except that EventHandlers/BeforeNewDayHandler should interfere
        // and redirect to "Another Scene" instead of newDay.
        $this->assertSame("Another Scene", $v->getTitle());
    }
}