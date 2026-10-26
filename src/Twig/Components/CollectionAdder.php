<?php

namespace App\Twig\Components;

use App\Entity\CardCollection;
use App\Entity\CardPrinting;
use App\Entity\User;
use App\Repository\CardCollectionRepository;
use App\Repository\CardPrintingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Ajout de l'impression affichée dans la fiche carte à l'une de ses collections.
 *
 * Rendu en différé (loading="defer") : la fiche reste identique pour tous et garde son cache navigateur,
 * seul ce bloc dépend de l'utilisateur connecté.
 */
#[AsLiveComponent]
class CollectionAdder extends AbstractController
{
    use DefaultActionTrait;

    /** Identifiant de l'impression : non modifiable côté client (signé par LiveComponent). */
    #[LiveProp]
    public string $printingId;

    /** Collection cible ; 0 = nouvelle collection, null = choix par défaut (la dernière modifiée). */
    #[LiveProp(writable: true)]
    public ?int $collectionId = null;

    #[LiveProp(writable: true)]
    public string $newName = '';

    #[LiveProp(writable: true)]
    public int $quantity = 1;

    #[LiveProp(writable: true)]
    public bool $foil = false;

    #[LiveProp]
    public ?string $confirmation = null;

    public ?string $error = null;

    private ?CardPrinting $printing = null;

    /** @var list<CardCollection>|null */
    private ?array $collections = null;

    public function __construct(
        private readonly CardPrintingRepository $printings,
        private readonly CardCollectionRepository $collectionRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function getPrinting(): CardPrinting
    {
        return $this->printing ??= (Uuid::isValid($this->printingId) ? $this->printings->find(Uuid::fromString($this->printingId)) : null)
            ?? throw new NotFoundHttpException();
    }

    /**
     * @return list<CardCollection>
     */
    public function getCollections(): array
    {
        $user = $this->getUser();

        return $this->collections ??= $user instanceof User ? $this->collectionRepository->findByOwner($user) : [];
    }

    /**
     * @return list<array{id: int, name: string, quantity: int}>
     */
    public function getOwnedCopies(): array
    {
        $user = $this->getUser();

        return $user instanceof User ? $this->collectionRepository->countCopiesOf($user, $this->getPrinting()->getOracleCard()) : [];
    }

    /**
     * Collection sélectionnée : sans choix explicite, la dernière modifiée (ou une nouvelle s'il n'y en a pas).
     *
     * Résolue au rendu et non au montage : le montage a lieu dans la fiche carte, mise en cache pour tous.
     */
    public function getSelectedCollectionId(): int
    {
        return $this->collectionId ?? ($this->getCollections()[0] ?? null)?->getId() ?? 0;
    }

    public function mount(string $printingId): void
    {
        $this->printingId = $printingId;
        $this->foil = $this->getPrinting()->isFoilOnly();
    }

    #[LiveAction]
    public function add(): void
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        /** @var User $user */
        $user = $this->getUser();
        $printing = $this->getPrinting();

        $collectionId = $this->getSelectedCollectionId();
        if (0 === $collectionId) {
            $name = trim($this->newName);
            if ('' === $name) {
                $this->error = 'Donne un nom à ta nouvelle collection.';

                return;
            }
            $collection = (new CardCollection($user))->setName(mb_substr($name, 0, 100));
            $this->em->persist($collection);
        } else {
            $collection = array_find($this->getCollections(), static fn (CardCollection $c) => $c->getId() === $collectionId)
                ?? throw new NotFoundHttpException('Collection introuvable.');
        }

        $quantity = max(1, min(999, $this->quantity));
        // Le foil n'est possible que si l'impression existe en foil, et obligatoire si elle n'existe qu'ainsi
        $foil = $printing->isFoilOnly() || ($this->foil && $printing->hasFoil());
        $collection->addCard($printing, $quantity, $foil);
        $this->em->flush();

        $this->collectionId = $collection->getId();
        $this->newName = '';
        $this->quantity = 1;
        $this->collections = null;
        $this->confirmation = \sprintf('%d × %s%s ajouté%s à « %s ».', $quantity, $printing->getOracleCard()->getName(), $foil ? ' (foil)' : '', $quantity > 1 ? 's' : '', $collection->getName());
    }
}
