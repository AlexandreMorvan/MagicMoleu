<?php

namespace App\Repository;

use App\Entity\CardPrinting;
use App\Entity\OracleCard;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CardPrinting>
 */
class CardPrintingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardPrinting::class);
    }

    /**
     * Toutes les impressions d'une carte avec leur édition, de la plus récente à la plus ancienne.
     *
     * @return list<CardPrinting>
     */
    public function findForCard(OracleCard $card): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.set', 's')
            ->addSelect('s')
            ->where('p.oracleCard = :card')
            // L'identifiant est un UUID binaire : son type doit être précisé explicitement
            ->setParameter('card', $card->getId(), UuidType::NAME)
            ->orderBy('p.releasedAt', 'DESC')
            ->addOrderBy('s.name', 'ASC')
            ->addOrderBy('p.collectorNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Impression utilisée par défaut pour une carte (celle de son image), à défaut la plus récente.
     */
    public function findDefaultFor(OracleCard $card): ?CardPrinting
    {
        if ($id = $card->getDefaultPrintingId()) {
            $printing = $this->find($id);
            if ($printing) {
                return $printing;
            }
        }

        return $this->createQueryBuilder('p')
            ->where('p.oracleCard = :card')
            ->setParameter('card', $card->getId(), UuidType::NAME)
            ->orderBy('p.releasedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneBySetAndNumber(string $setCode, string $collectorNumber): ?CardPrinting
    {
        return $this->createQueryBuilder('p')
            ->join('p.set', 's')
            ->where('s.code = :code')
            ->andWhere('p.collectorNumber = :number')
            ->setParameter('code', mb_strtolower($setCode))
            ->setParameter('number', $collectorNumber)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
