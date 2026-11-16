<?php

namespace App\Repository;

use App\Entity\CardCollection;
use App\Entity\CollectionCard;
use App\Entity\OracleCard;
use App\Entity\User;
use SortDirection;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CardCollection>
 */
class CardCollectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardCollection::class);
    }

    /**
     * Collection avec ses cartes, leurs impressions, éditions, données oracle et noms français, en une requête.
     */
    public function findWithCards(int $id): ?CardCollection
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.cards', 'cc')->addSelect('cc')
            ->leftJoin('cc.printing', 'p')->addSelect('p')
            ->leftJoin('p.set', 's')->addSelect('s')
            ->leftJoin('p.oracleCard', 'o')->addSelect('o')
            // Seule la traduction française est chargée (affichage et recherche par nom français)
            ->leftJoin('o.localizations', 'l', 'WITH', 'l.lang = :lang')->addSelect('l')
            ->where('c.id = :id')
            ->setParameter('id', $id)
            ->setParameter('lang', 'fr')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Collections d'un utilisateur avec leurs cartes, impressions et éditions (vignettes : nombre, valeur, statistiques).
     *
     * @return list<CardCollection>
     */
    public function findByOwnerWithCards(User $owner): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.cards', 'cc')->addSelect('cc')
            ->leftJoin('cc.printing', 'p')->addSelect('p')
            ->leftJoin('p.set', 's')->addSelect('s')
            ->leftJoin('p.oracleCard', 'o')->addSelect('o')
            ->where('c.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('c.updatedAt', SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<CardCollection>
     */
    public function findByOwner(User $owner): array
    {
        return $this->findBy(['owner' => $owner], ['updatedAt' => 'DESC']);
    }

    /**
     * Nombre d'exemplaires possédés par carte (toutes collections et impressions confondues).
     *
     * @return array<string, int> identifiant de la carte oracle (RFC 4122) => quantité
     */
    public function countOwnedByCard(User $owner): array
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(p.oracleCard) AS card', 'SUM(cc.quantity) AS quantity')
            ->from(CollectionCard::class, 'cc')
            ->join('cc.printing', 'p')
            ->join('cc.collection', 'c')
            ->where('c.owner = :owner')
            ->setParameter('owner', $owner)
            ->groupBy('p.oracleCard')
            ->getQuery()
            ->getScalarResult();

        $owned = [];
        foreach ($rows as $row) {
            $owned[self::uuid($row['card'])] = (int) $row['quantity'];
        }

        return $owned;
    }

    /**
     * IDENTITY() renvoie la valeur brute de la colonne BINARY(16).
     */
    private static function uuid(string $value): string
    {
        return (16 === \strlen($value) ? Uuid::fromBinary($value) : Uuid::fromString($value))->toRfc4122();
    }

    /**
     * Exemplaires d'une carte (toutes impressions confondues) possédés par un utilisateur, par collection.
     *
     * @return list<array{id: int, name: string, quantity: int}>
     */
    public function countCopiesOf(User $owner, OracleCard $card): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('c.id', 'c.name', 'SUM(cc.quantity) AS quantity')
            ->from(CollectionCard::class, 'cc')
            ->join('cc.collection', 'c')
            ->join('cc.printing', 'p')
            ->where('c.owner = :owner')
            ->andWhere('p.oracleCard = :card')
            ->setParameter('owner', $owner)
            ->setParameter('card', $card->getId(), UuidType::NAME)
            ->groupBy('c.id')
            ->orderBy('c.name', SortDirection::Ascending)
            ->getQuery()
            ->getArrayResult();
    }
}
