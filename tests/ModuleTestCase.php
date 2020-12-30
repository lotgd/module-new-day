<?php
declare(strict_types=1);

namespace LotGD\Module\NewDay\Tests;


use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\ORM\Events as DoctrineEvents;
use LotGD\Core\Doctrine\EntityPostLoadEventListener;
use LotGD\Core\LibraryConfigurationManager;
use LotGD\Core\ModelExtender;
use LotGD\Core\Models\EventSubscription;
use Monolog\Handler\PHPConsoleHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\Handler\NullHandler;

use LotGD\Core\Configuration;
use LotGD\Core\Game;
use LotGD\Core\GameBuilder;
use LotGD\Core\Tests\ModelTestCase;
use LotGD\Core\Models\Module as ModuleModel;

use LotGD\Module\NewDay\Module;
use Symfony\Component\Yaml\Yaml;

class ModuleTestCase extends ModelTestCase
{
    const Library = 'lotgd/module-new-day';
    const RootNamespace = "LotGD\\Module\\NewDay\\";

    public $g;
    protected $moduleModel;

    protected function getDataSet(): array
    {
        return Yaml::parseFile(implode(DIRECTORY_SEPARATOR, [__DIR__, 'datasets', $this->dataset . '.yml']));
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();

        // Make an empty logger for these tests. Feel free to change this
        // to place log messages somewhere you can easily find them.
        $logger  = new Logger('test');
        #$logger->pushHandler(new StreamHandler('php://stdout'));
        $logger->pushHandler(new NullHandler());

        // Create a Game object for use in these tests.
        $this->g = (new GameBuilder())
            ->withConfiguration(new Configuration(getenv('LOTGD_TESTS_CONFIG_PATH')))
            ->withLogger($logger)
            ->withEntityManager($this->getEntityManager())
            ->withCwd(implode(DIRECTORY_SEPARATOR, [__DIR__, '..']))
            ->create();

        // Add Event listener to entity manager
        $dem = $this->getEntityManager()->getEventManager();
        $dem->addEventListener([DoctrineEvents::postLoad], new EntityPostLoadEventListener($this->g));

        // Run model extender
        AnnotationRegistry::registerLoader("class_exists");

        $modelExtender = new ModelExtender();
        $libraryConfigurationManager = new LibraryConfigurationManager($this->g->getComposerManager(), getcwd());

        foreach ($libraryConfigurationManager->getConfigurations() as $config) {
            $modelExtensions = $config->getSubKeyIfItExists(["modelExtensions"]);

            if ($modelExtensions) {
                $modelExtender->addMore($modelExtensions);
            }
        }

        // Register and unregister before/after each test, since
        // handleEvent() calls may expect the module be registered (for example,
        // if they read properties from the model).
        $this->moduleModel = new ModuleModel(self::Library);
        $this->moduleModel->save($this->getEntityManager());
        Module::onRegister($this->g, $this->moduleModel);

        $this->g->getEntityManager()->flush();
        $this->g->getEntityManager()->clear();
    }

    public function tearDown(): void
    {
        $this->g->getEntityManager()->flush();
        $this->g->getEntityManager()->clear();

        Module::onUnregister($this->g, $this->moduleModel);
        $m = $this->getEntityManager()->getRepository(ModuleModel::class)->find(self::Library);
        if ($m) {
            $m->delete($this->getEntityManager());
        }

        parent::tearDown();
    }
}