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
}
