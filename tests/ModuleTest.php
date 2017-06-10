<?php
declare(strict_types=1);

namespace LotGD\Module\NewDay\Tests;


use LotGD\Core\Game;
use LotGD\Core\Models\Character;
use LotGD\Core\Models\Module as ModuleModel;

use LotGD\Module\NewDay\Module;

class ModuleTest extends ModuleTestCase
{
    protected $dataset = "module";


    // TODO for LotGD staff: this test assumes the schema in their yaml file
    // reflects all columns in the core's models of characters, scenes and modules.
    // This is pretty fragile since every time we add a column, everyone's tests
    // will break.
    public function testUnregister()
    {
        Module::onUnregister($this->g, $this->moduleModel);
        $m = $this->getEntityManager()->getRepository(ModuleModel::class)->find(self::Library);
        $m->delete($this->getEntityManager());

        // Assert that databases are the same before and after.
        // TODO for module author: update list of tables below to include the
        // tables you modify during registration/unregistration.
        $tableList = [
            'characters', 'scenes', 'modules', 'scene_connections', "module_properties"
        ];

        $after = $this->getConnection()->createDataSet($tableList);
        $before = $this->getDataSet();

        foreach($tableList as $table) {
            $this->assertSame($before->getTable($table)->getRowCount(), $after->getTable($table)->getRowCount());
        }

        // Since tearDown() contains an onUnregister() call, this also tests
        // double-unregistering, which should be properly supported by modules.
    }

    public function testHandleUnknownEvent()
    {
        // Always good to test a non-existing event just to make sure nothing happens :).
        // Always good to test a non-existing event just to make sure nothing happens :).
        $context = new \LotGD\Core\Events\EventContext(
            "e/lotgd/tests/unknown-event",
            "none",
            \LotGD\Core\Events\EventContextData::create([])
        );

        // The Module expects to have a character present, so we need to set one.
        $game = $this->g;
        $character = $this->getEntityManager()->getRepository(Character::class)->findById(1)[0];
        $game->setCharacter($character);

        Module::handleEvent($this->g, $context);
    }

    public function testNavigateToEvent()
    {
        /** @var $game Game */
        $game = $this->g;
        $character = $this->getEntityManager()->getRepository(Character::class)->findById(1)[0];
        $game->setCharacter($character);
        $v = $game->getViewpoint();

        // Assert new day happened
        $this->assertSame("It is a new day!", $v->getTitle());

        // Change viewpoint by taking an action - assert restored scene.
        $action = $v->getActionGroups()[0]->getActions()[0];
        $game->takeAction($action->getId());
        $this->assertSame("Village", $v->getTitle());

        // Change viewpoint again - this time, no new day should be happening
        $action = $v->getActionGroups()[0]->getActions()[0];
        $game->takeAction($action->getId());
        $this->assertSame("Another Scene", $v->getTitle());
    }
}
