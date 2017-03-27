<?php
declare(strict_types=1);

use Monolog\Logger;
use Monolog\Handler\NullHandler;

use LotGD\Core\LibraryConfiguration;
use LotGD\Core\Configuration;
use LotGD\Core\Game;
use LotGD\Core\Models\Character;
use LotGD\Core\Models\Module as ModuleModel;
use LotGD\Core\Tests\ModelTestCase;

use LotGD\Module\NewDay\Module;

class ModuleTest extends ModelTestCase
{
    const Library = 'lotgd/module-new-day';
    const RootNamespace = "LotGD\\Module\\NewDay\\";

    private $g;
    private $moduleModel;

    protected function getDataSet(): \PHPUnit_Extensions_Database_DataSet_YamlDataSet
    {
        return new \PHPUnit_Extensions_Database_DataSet_YamlDataSet(implode(DIRECTORY_SEPARATOR, [__DIR__, 'datasets', 'module.yml']));
    }

    public function setUp()
    {
        parent::setUp();

        // Make an empty logger for these tests. Feel free to change this
        // to place log messages somewhere you can easily find them.
        $logger  = new Logger('test');
        $logger->pushHandler(new NullHandler());

        // Create a Game object for use in these tests.
        $this->g = new Game(new Configuration(getenv('LOTGD_TESTS_CONFIG_PATH')), $logger, $this->getEntityManager(), implode(DIRECTORY_SEPARATOR, [__DIR__, '..']));

        // Register and unregister before/after each test, since
        // handleEvent() calls may expect the module be registered (for example,
        // if they read properties from the model).
        $this->g->getModuleManager()->register($this->getConfigurationMock());
        $this->g->getEntityManager()->flush();
        $this->g->getEntityManager()->clear();
    }

    protected function getConfigurationMock()
    {
        $configurationMock = $this->createMock(LibraryConfiguration::class);
        $configurationMock->method("getName")->willReturn("lotgd/module-new-day");
        $configurationMock->method("getRootNamespace")->willReturn(self::RootNamespace);
        $configurationMock->method("getsubscriptionpatterns")->willReturnCallback(function(){
            $config = \Symfony\Component\Yaml\Yaml::parse(file_get_contents(__DIR__ . "/../lotgd.yml"));
            return $config["subscriptionPatterns"];
        });

        return $configurationMock;
    }

    public function tearDown()
    {
        parent::tearDown();

        try {
            $this->g->getModuleManager()->unregister($this->getConfigurationMock());
        } catch(Exception $e) {

        }
    }

    protected function getModuleModel()
    {
        return $this->getEntityManager()->getRepository(ModuleModel::class)->findOneBy(["library" => self::Library]);
    }

    // TODO for LotGD staff: this test assumes the schema in their yaml file
    // reflects all columns in the core's models of characters, scenes and modules.
    // This is pretty fragile since every time we add a column, everyone's tests
    // will break.
    public function testUnregister()
    {
        //Module::onUnregister($this->g, $this->getModuleModel());
        //$m = $this->getEntityManager()->getRepository(ModuleModel::class)->find(self::Library);
        //$m->delete($this->getEntityManager());
        $this->g->getModuleManager()->unregister($this->getConfigurationMock());

        // Assert that databases are the same before and after.
        // TODO for module author: update list of tables below to include the
        // tables you modify during registration/unregistration.
        $after = $this->getConnection()->createDataSet(['characters', 'scenes', 'scene_connections', 'modules', 'module_properties', 'event_subscriptions']);
        $before = $this->getDataSet();

        $this->assertDataSetsEqual($before, $after);

        // Since tearDown() contains an onUnregister() call, this also tests
        // double-unregistering, which should be properly supported by modules.
    }

    public function testHandleUnknownEvent()
    {
        // Always good to test a non-existing event just to make sure nothing happens :).
        $context = [];
        Module::handleEvent($this->g, 'e/lotgd/tests/unknown-event', $context);
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
