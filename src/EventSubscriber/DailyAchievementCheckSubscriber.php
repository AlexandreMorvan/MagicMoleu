<?php

namespace App\EventSubscriber;

use App\Achievement\AchievementChecker;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Vérifie les succès liés au temps (anniversaires d'inscription) une fois par jour et par session.
 * Les succès liés aux decks sont vérifiés au moment où les decks changent.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: -10)] // après le pare-feu (priorité 8)
class DailyAchievementCheckSubscriber
{
    private const string SESSION_KEY = 'achievements.checked_on';

    public function __construct(
        private readonly Security $security,
        private readonly AchievementChecker $achievements,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $user = $this->security->getUser();
        if (!$event->isMainRequest() || !$user instanceof User || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        $today = date('Y-m-d');
        if ($session->get(self::SESSION_KEY) === $today) {
            return;
        }

        $session->set(self::SESSION_KEY, $today);
        $this->achievements->check($user);
    }
}
