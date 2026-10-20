<?php

namespace App\Twig\Components;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Toasts des nouvelles notifications (succès débloqués…), chacune affichée une seule fois.
 * Placé hors de l'en-tête : son flou d'arrière-plan fausserait le positionnement « fixed ».
 */
#[AsLiveComponent]
class NotificationToasts
{
    use DefaultActionTrait;

    /** @var list<Notification>|null */
    private ?array $toasts = null;

    public function __construct(
        private readonly Security $security,
        private readonly NotificationRepository $notifications,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return list<Notification>
     */
    public function getToasts(): array
    {
        if (null === $this->toasts) {
            $user = $this->security->getUser();
            $this->toasts = $user instanceof User ? $this->notifications->findToToast($user) : [];
            array_walk($this->toasts, static fn (Notification $n) => $n->markToasted());
            if ($this->toasts) {
                $this->em->flush();
            }
        }

        return $this->toasts;
    }

    #[LiveListener('notifications:refresh')]
    public function refresh(): void
    {
        // Le simple fait d'écouter l'événement provoque un nouveau rendu
    }
}
