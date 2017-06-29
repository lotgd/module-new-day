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
    const SceneNewDay = MODULE . "/newDay";
    const SceneRestoration = MODULE ."/restoration";
    const SceneContinue = MODULE . "/continue";
    const ModulePropertySceneId = MODULE ."/sceneIds";
    const CharacterPropertyIgnoreCatchAll = MODULE . "/ignoreCatchAll";
    const CharacterPropertyLastNewDay = MODULE . "/lastNewDay";
    const CharacterPropertyNewDayPosition = MODULE . "/position";
    const CharacterPropertyViewpointSnapshot = MODULE . "/viewpointSnapshot";
    const HookBeforeNewDay = "h/" . MODULE . "/before";
    const HookAfterNewDay = "h/" . MODULE . "/after";

    const PositionNone = 0;
    const PositionBeforeNewDay = 1;
    const PositionAfterNewDay = 2;

    public static function handleEvent(Game $g, EventContext $context): EventContext
    {
        $subscription = "h/lotgd/core/navigate-to";
        $event = $context->getEvent();

        $position = $g->getCharacter()->getProperty(self::CharacterPropertyNewDayPosition, self::PositionNone);

        if ($event === $subscription . "/" . self::SceneContinue) {
            $skip = false;
            $g->getCharacter()->setProperty(self::CharacterPropertyIgnoreCatchAll, false);
        } else {
            $skip = $g->getCharacter()->getProperty(self::CharacterPropertyIgnoreCatchAll, false);
        }

        if ($skip) {
            return $context;
        }

        if ($position === 0 and substr($event, 0, strlen($subscription)) === $subscription) {
            $context = self::handleNavigationToAny($g, $context);

            // We must fetch the position again since it could change within handleNavigationToAny, but doesn't need to.
            $position = $g->getCharacter()->getProperty(self::CharacterPropertyNewDayPosition, self::PositionNone);
        }

        if ($position === 1) {
            $hookData = $g->getEventManager()->publish(
                self::HookBeforeNewDay,
                new EventNewDayData(["redirect" => 0])
            );

            $redirect = $hookData->get("redirect");
            if ($redirect === 0) {
                $redirect = $g->getEntityManager()->getRepository(Scene::class)->findOneBy(["template" => self::SceneNewDay]);
            } else {
                $g->getCharacter()->setProperty(self::CharacterPropertyIgnoreCatchAll, true);
            }

            // redirects either to the new day or to a different target (like race selection)
            $context->setDataField("redirect", $redirect);

            if ($event === $subscription . "/" . self::SceneNewDay) {
                $context = self::handleNavigationToNewDay($g, $context);
            }
        }

        if ($position === 2) {
            if ($event === $subscription . "/" . self::SceneRestoration) {
                $context = self::handleNavigationToRestorationPoint($g, $context);
            }
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
        $g->getCharacter()->setProperty(self::CharacterPropertyNewDayPosition, self::PositionAfterNewDay);

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

        $g->getCharacter()->setProperty(self::CharacterPropertyViewpointSnapshot, null);
        $g->getCharacter()->setProperty(self::CharacterPropertyNewDayPosition, self::PositionNone);

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
            $g->getCharacter()->setProperty(self::CharacterPropertyNewDayPosition, self::PositionBeforeNewDay);
        }

        return $context;
    }

    public static function onRegister(Game $g, ModuleModel $module)
    {
        // Register new day scene and "restoration" scene.
        $sceneIds = $module->getProperty(self::ModulePropertySceneId);

        if ($sceneIds === null) {
            [$newDayScene, $restorationScene, $continueScene] = self::getScenes();

            $g->getEntityManager()->persist($newDayScene);
            $g->getEntityManager()->persist($restorationScene);
            $g->getEntityManager()->persist($continueScene);
            $g->getEntityManager()->flush();

            $module->setProperty(self::ModulePropertySceneId, [
                self::SceneNewDay => $newDayScene->getId(),
                self::SceneRestoration => $restorationScene->getId(),
                self::SceneContinue => $continueScene->getId()
            ]);

            // logging
            $g->getLogger()->addNotice(sprintf(
                "%s: Adds scenes (newday: %s, restoration: %s)",
                self::Module,
                $newDayScene->getId(),
                $restorationScene->getId(),
                $continueScene->getId()
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
            $g->getEntityManager()->getRepository(Scene::class)->find($sceneIds[self::SceneContinue])->delete($g->getEntityManager());

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
            "title" => "Restoration",
            "description" => "You should not be able to see this text if everything works, this scene should restore your viewpoint."
        ]);

        $continueScene = Scene::create([
            "template" => self::SceneContinue,
            "title" => "Continue",
            "description" => "You should not be able to see this text if everything works, this is for internal work only."
        ]);

        $newDayScene->connect($restorationScene, Scene::Unidirectional);

        return [$newDayScene, $restorationScene, $continueScene];
    }
}
