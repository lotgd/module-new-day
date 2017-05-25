<?php
declare(strict_types=1);

namespace LotGD\Module\NewDay;

use DateTime;
use LotGD\Core\Game;
use LotGD\Core\Events\EventContext;
use LotGD\Core\Models\Scene;
use LotGD\Core\Models\Viewpoint;
use LotGD\Core\Module as ModuleInterface;
use LotGD\Core\Models\Module as ModuleModel;

const MODULE = "lotgd/module-new-day";

class Module implements ModuleInterface {
    const Module = MODULE;
    const SceneNewDay = MODULE . "/newDay#!noNewDay!";
    const SceneRestoration = MODULE ."/restoration#!noNewDay!";
    const ModulePropertySceneId = MODULE ."/sceneIds";
    const CharacterPropertyLastNewDay = MODULE . "/lastNewDay";
    const CharacterPropertyViewpointSnapshot = MODULE . "/viewpointSnapshot";

    public static function handleEvent(Game $g, EventContext $context): EventContext
    {
        $subscription = "h/lotgd/core/navigate-to";
        $event = $context->getEvent();

        if ($event === $subscription . "/" . self::SceneNewDay) {
            return self::handleNavigationToNewDay($g, $context);
        } elseif ($event === $subscription . "/" . self::SceneRestoration) {
            return self::handleNavigationToRestorationPoint($g, $context);
        } elseif (substr($event, 0, strlen($subscription)) === $subscription and strpos($event, "!noNewDay!") === false) {
            return self::handleNavigationToAny($g, $context);
        }

        return $context;
    }

    /**
     * Handles the navigation to a new day which is usually happening in a redirection context.
     * @param Game $g
     * @param EventContext $context
     * @return EventContext
     */
    private static function handleNavigationToNewDay(Game $g, EventContext $context): EventContext
    {
        // do everything for the new day.
        $g->getCharacter()->setProperty(self::CharacterPropertyLastNewDay, new DateTime());

        return $context;
    }

    /**
     * Handles the navigation to a restoration point, usually by taking a direct action from the new day.
     * @param Game $g
     * @param EventContext $context
     * @return EventContext
     */
    private static function handleNavigationToRestorationPoint(Game $g, EventContext $context): EventContext
    {
        // restore the old viewpoint
        $context->getDataField("viewpoint")->changeFromSnapshot(
            $g->getCharacter()->getProperty(self::CharacterPropertyViewpointSnapshot)
        );

        return $context;
    }

    /**
     * Tries to catch *every* navigation and redirects to a new day.
     * @param Game $g
     * @param EventContext $context
     * @return EventContext
     */
    private static function handleNavigationToAny(Game $g, EventContext $context): EventContext
    {
        $lastNewDay = $g->getCharacter()->getProperty(self::CharacterPropertyLastNewDay);

        if ($lastNewDay === null or $g->getTimeKeeper()->isNewDay($lastNewDay)) {
            $viewpointSnapshot = $context->getDataField("viewpoint")->getSnapshot();
            $g->getCharacter()->setProperty(self::CharacterPropertyViewpointSnapshot, $viewpointSnapshot);

            // Set new scene - good would be to have module context here, too.
            $context->setDataField(
                "redirect",
                $g->getEntityManager()->getRepository(Scene::class)->findOneBy(["template" => self::SceneNewDay])
            );
        }

        return $context;
    }

    public static function onRegister(Game $g, ModuleModel $module)
    {
        // Register new day scene and "restoration" scene.
        $sceneIds = $module->getProperty(self::ModulePropertySceneId);

        if ($sceneIds === null) {
            [$newDayScene, $restorationScene] = self::getScenes();

            $g->getEntityManager()->persist($newDayScene);
            $g->getEntityManager()->persist($restorationScene);
            $g->getEntityManager()->flush();

            $module->setProperty(self::ModulePropertySceneId, [
                self::SceneNewDay => $newDayScene->getId(),
                self::SceneRestoration => $restorationScene->getId()
            ]);

            // logging
            $g->getLogger()->addNotice(sprintf(
                "%s: Adds scenes (newday: %s, restoration: %s)",
                self::Module,
                $newDayScene->getId(),
                $restorationScene->getId()
            ));
        }
    }

    public static function onUnregister(Game $g, ModuleModel $module)
    {
        // Unregister them again.
        $sceneIds = $module->getProperty(self::ModulePropertySceneId);

        if ($sceneIds !== null) {
            // delete village
            $g->getEntityManager()->getRepository(Scene::class)->find($sceneIds[self::SceneNewDay])->delete($g->getEntityManager());
            $g->getEntityManager()->getRepository(Scene::class)->find($sceneIds[self::SceneRestoration])->delete($g->getEntityManager());

            // set property to null
            $module->setProperty(self::ModulePropertySceneId, null);
        }
    }

    protected static function getScenes(): array
    {
        $newDayScene = Scene::create([
            "template" => self::SceneNewDay,
            "title" => "It is a new day!",
            "description" => "You open your eyes to discover that a new day has been bestowed upon you. "
                ."You feel refreshed enough to take on the world!"
        ]);

        $restorationScene = Scene::create([
            "template" => self::SceneRestoration,
            "title" => "Continue",
            "description" => "You should not be able to see this text if everything works."
        ]);

        $newDayScene->connect($restorationScene, Scene::Unidirectional);

        return [$newDayScene, $restorationScene];
    }
}
