<?php
declare(strict_types=1);

namespace LotGD\Module\NewDay;

use DateTime;
use LotGD\Core\Game;
use LotGD\Core\Events\EventContext;
use LotGD\Core\Models\Scene;
use LotGD\Core\Models\SceneTemplate;
use LotGD\Core\Models\Viewpoint;
use LotGD\Core\Module as ModuleInterface;
use LotGD\Core\Models\Module as ModuleModel;
use LotGD\Module\NewDay\SceneTemplates\ContinueScene;
use LotGD\Module\NewDay\SceneTemplates\NewDayScene;
use LotGD\Module\NewDay\SceneTemplates\RestorationScene;

const MODULE = "lotgd/module-new-day";

class Module implements ModuleInterface {
    const Module = MODULE;
    const SceneNewDay = MODULE . "/newDay";
    const SceneRestoration = MODULE ."/restoration";
    const SceneContinue = MODULE . "/continue";
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
        $logger = $g->getLogger();
        $subscription = "h/lotgd/core/navigate-to";
        $event = $context->getEvent();

        $position = $g->getCharacter()->getProperty(self::CharacterPropertyNewDayPosition, self::PositionNone);
        $logger->debug("ModuleNewDay: New day position is $position");

        if ($event === $subscription . "/" . self::SceneContinue) {
            $skip = false;
            $g->getCharacter()->setProperty(self::CharacterPropertyIgnoreCatchAll, false);

            $logger->debug("ModuleNewDay: ContinueScene is accessed, will not skip for sure.");
        } else {
            $skip = $g->getCharacter()->getProperty(self::CharacterPropertyIgnoreCatchAll, false);

            $logger->debug("ModuleNewDay: Skip is " . ($skip ? "true" : "false"));
        }

        if ($skip) {
            return $context;
        }

        if ($position === 0 and str_starts_with($event, $subscription)) {
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
                $logger->debug("ModuleNewDay: No redirect");

                if ($event === $subscription . "/" . self::SceneNewDay) {
                    // We must not redirect if the current scene is already the new day - otherwise, the context below would have been called twice.
                    $context = self::handleNavigationToNewDay($g, $context);
                } else {
                    $redirect = $g->getEntityManager()->getRepository(Scene::class)->findOneBy(["template" => NewDayScene::class]);
                }
            } else {
                $logger->debug("ModuleNewDay: Redirect to " . ($redirect===0?$redirect->getId():0));

                $g->getCharacter()->setProperty(self::CharacterPropertyIgnoreCatchAll, true);
            }

            if ($redirect !== 0) {
                // redirects either to the new day or to a different target (like race selection)
                $context->setDataField("redirect", $redirect);
            }
        }

        if ($position === 2) {
            //if ($event === $subscription . "/" . self::SceneRestoration) {
                $context = self::handleNavigationToRestorationPoint($g, $context);
            //}
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
        $g->getCharacter()->setProperty(self::CharacterPropertyLastNewDay, new DateTime());
        $g->getCharacter()->setProperty(self::CharacterPropertyNewDayPosition, self::PositionAfterNewDay);

        $hookData = $g->getEventManager()->publish(
            self::HookAfterNewDay,
            new EventAfterNewDayData(["viewpoint" => $context->getDataField("viewpoint")])
        );

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
            $g->getEntityManager(),
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
            $reason = $lastNewDay === null ? "No last new day" : "TimeKeeper says its time";
            $g->getLogger()->debug("ModuleNewDay: Prepare new day. Reason: $reason");

            $viewpointSnapshot = $context->getDataField("viewpoint")->getSnapshot();
            $g->getCharacter()->setProperty(self::CharacterPropertyViewpointSnapshot, $viewpointSnapshot);
            $g->getCharacter()->setProperty(self::CharacterPropertyNewDayPosition, self::PositionBeforeNewDay);
        }

        return $context;
    }

    public static function onRegister(Game $g, ModuleModel $module)
    {
        [$newDayScene, $restorationScene, $continueScene] = self::getScenes();

        $em = $g->getEntityManager();

        $em->persist($newDayScene);
        $em->persist($newDayScene->getTemplate());
        $em->persist($restorationScene);
        $em->persist($restorationScene->getTemplate());
        $em->persist($continueScene);
        $em->persist($continueScene->getTemplate());

        // don't flush.

        // logging
        $g->getLogger()->notice(sprintf(
            "%s: Adds scenes (newday: %s, restoration: %s)",
            self::Module,
            $newDayScene->getId(),
            $restorationScene->getId(),
            $continueScene->getId()
        ));
    }

    public static function onUnregister(Game $g, ModuleModel $module)
    {
        // delete all scenes using "New Day" as the template (it is not user assignable)
        $scenes = $g->getEntityManager()->getRepository(Scene::class)->findBy([
            "template" => [
                NewDayScene::class,
                ContinueScene::class,
                RestorationScene::class,
            ]
        ]);

        $em = $g->getEntityManager();

        foreach ($scenes as $scene) {
            $template = $scene->getTemplate();

            // We must remove the template and the scene.
            $em->remove($template);
            $em->remove($scene);
        }

        $em->flush();
    }

    protected static function getScenes(): array
    {
        $newDayScene = NewDayScene::getScaffold();
        $restorationScene = RestorationScene::getScaffold();
        $continueScene = ContinueScene::getScaffold();

        $newDayScene->getTemplate()->setUserAssignable(false);
        $newDayScene->connect($restorationScene, Scene::Unidirectional);

        return [$newDayScene, $restorationScene, $continueScene];
    }
}
