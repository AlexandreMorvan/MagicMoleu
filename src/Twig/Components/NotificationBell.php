<?php

namespace App\Twig\Components;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Cloche du menu : nombre de notifications non lues et dernières notifications.
 * Se rafraîchit quand un autre composant émet « notifications:refresh ».
 */
#[AsLiveComponent]
class NotificationBell
{
    use DefaultActionTrait;

    public function __construct(
        private readonly Security $security,
        private readonly NotificationRepository $notifications,
    ) {
    }

    public function getUnreadCount(): int
    {
        return $this->user() ? $this->notifications->countUnreadFor($this->user()) : 0;
    }

    /**
     * @return list<Notification>
     */
    public function getLatest(): array
    {
        return $this->user() ? $this->notifications->findLatestFor($this->user(), 6) : [];
    }

    #[LiveAction]
    public function markAllRead(): void
    {
        if ($this->user()) {
            $this->notifications->markAllReadFor($this->user());
        }
    }

    #[LiveListener('notifications:refresh')]
    public function refresh(): void
    {
        // Le simple fait d'écouter l'événement provoque un nouveau rendu
    }

    private function user(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }
}
