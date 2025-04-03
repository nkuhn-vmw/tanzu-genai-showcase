<?php

namespace App\Repository;

use App\Entity\CompetitorAnalysis;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompetitorAnalysis>
 *
 * @method CompetitorAnalysis|null find($id, $lockMode = null, $lockVersion = null)
 * @method CompetitorAnalysis|null findOneBy(array $criteria, array $orderBy = null)
 * @method CompetitorAnalysis[]    findAll()
 * @method CompetitorAnalysis[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CompetitorAnalysisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompetitorAnalysis::class);
    }

    public function save(CompetitorAnalysis $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CompetitorAnalysis $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find competitors for a specific company
     */
    public function findByCompany(int $companyId): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.company = :companyId')
            ->setParameter('companyId', $companyId)
            ->orderBy('c.competitorName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find competitors in a specific industry
     */
    public function findCompetitorsInIndustry(string $industry): array
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->join('c.company', 'company')
            ->where('company.industry = :industry')
            ->setParameter('industry', $industry)
            ->orderBy('c.competitorName', 'ASC');

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Find competitor analysis by competitor name
     */
    public function findByCompetitorName(string $competitorName): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.competitorName LIKE :competitorName')
            ->setParameter('competitorName', '%' . $competitorName . '%')
            ->orderBy('c.competitorName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recently updated competitor analyses
     */
    public function findRecentlyUpdated(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.updatedAt IS NOT NULL')
            ->orderBy('c.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
