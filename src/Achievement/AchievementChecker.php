<?php

namespace App\Achievement;

use App\Deck\DeckValidator;
use App\Entity\User;
use App\Entity\UserAchievement;
use App\Enum\Achievement;
use App\Repository\DeckRepository;
use App\Repository\UserAchievementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Débloque les succès mérités par un utilisateur et le prévient par une notification.
 * Peut être appelé autant de fois que nécessaire : un succès n'est jamais débloqué deux fois.
 */
class AchievementChecker
{
    public function __construct(
        private readonly UserAchievementRepository $achievements,
        private readonly DeckRepository $decks,
        private readonly DeckValidator $validator,
        private readonly NotificationCenter $notifications,
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @return list<Achievement> succès débloqués par cet appel
     */
    public function check(User $user, ?\DateTimeImmutable $now = null): array
    {
        $alreadyUnlocked = $this->achievements->findUnlockedBy($user);
        $stats = new UserStats($user, $this->decks, $this->validator, $now ?? new \DateTimeImmutable());

        $unlocked = [];
        foreach (Achievement::cases() as $achievement) {
            if (isset($alreadyUnlocked[$achievement->value]) || !$achievement->isEarned($stats)) {
                continue;
            }

            $this->em->persist(new UserAchievement($user, $achievement));
            $this->notifications->notify(
                $user,
                'Succès débloqué : '.$achievement->label(),
                $achievement->description(),
                $this->urlGenerator->generate('app_profile', ['_fragment' => 'succes']),
                $achievement->symbol(),
                $achievement->rarity()->value,
            );
            $unlocked[] = $achievement;
        }

        if ($unlocked) {
            $this->em->flush();
        }

        return $unlocked;
    }
}
