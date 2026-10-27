<?php

namespace App\Twig\Components;

use App\Entity\OracleCard;
use App\Entity\User;
use App\Enum\Color;
use App\Repository\OracleCardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

/**
 * Choix de l'avatar parmi les planeswalkers (illustration recadrée Scryfall), avec recherche et filtre de couleur.
 */
#[AsLiveComponent]
class AvatarPicker extends AbstractController
{
    use DefaultActionTrait;

    private const int PAGE_SIZE = 48;

    #[LiveProp(writable: true, onUpdated: 'resetLimit')]
    public string $query = '';

    /** @var list<string> */
    #[LiveProp(writable: true, onUpdated: 'resetLimit')]
    public array $colors = [];

    #[LiveProp]
    public int $limit = self::PAGE_SIZE;

    /** @var list<OracleCard>|null */
    private ?array $candidates = null;

    public function __construct(
        private readonly OracleCardRepository $cards,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return list<OracleCard>
     */
    public function getCandidates(): array
    {
        // Une carte de plus que la limite est chargée pour savoir s'il en reste
        return $this->candidates ??= $this->cards->findAvatarCandidates($this->query, Color::maskFrom($this->colors), $this->limit + 1);
    }

    public function hasMore(): bool
    {
        return \count($this->getCandidates()) > $this->limit;
    }

    /**
     * @return list<Color>
     */
    #[ExposeInTemplate]
    public function getColorChoices(): array
    {
        return Color::cases();
    }

    public function resetLimit(): void
    {
        $this->limit = self::PAGE_SIZE;
    }

    #[LiveAction]
    public function more(): void
    {
        $this->limit += self::PAGE_SIZE;
    }

    /**
     * Enregistre l'avatar et recharge la page : il apparaît aussi dans le menu.
     */
    #[LiveAction]
    public function choose(#[LiveArg] string $cardId): Response
    {
        $user = $this->getAccountUser();
        $card = Uuid::isValid($cardId) ? $this->cards->find(Uuid::fromString($cardId)) : null;
        if (null !== $card && str_contains($card->getTypeLine(), 'Planeswalker')) {
            $user->setAvatarCard($card);
            $this->em->flush();
            $this->addFlash('success', \sprintf('Avatar : %s.', $card->getName()));
        }

        return $this->redirect($this->generateUrl('app_account').'#avatar');
    }

    #[LiveAction]
    public function clear(): Response
    {
        $this->getAccountUser()->setAvatarCard(null);
        $this->em->flush();
        $this->addFlash('success', 'Avatar retiré : ton initiale est affichée à la place.');

        return $this->redirect($this->generateUrl('app_account').'#avatar');
    }

    private function getAccountUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
