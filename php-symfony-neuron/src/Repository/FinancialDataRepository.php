<?php

namespace App\Repository;

use App\Entity\FinancialData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FinancialData>
 *
 * @method FinancialData|null find($id, $lockMode = null, $lockVersion = null)
 * @method FinancialData|null findOneBy(array $criteria, array $orderBy = null)
 * @method FinancialData[]    findAll()
 * @method FinancialData[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FinancialDataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FinancialData::class);
    }

    public function save(FinancialData $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FinancialData $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find financial data by company and report type
     */
    public function findByCompanyAndReportType(int $companyId, string $reportType): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.company = :companyId')
            ->andWhere('f.reportType = :reportType')
            ->setParameter('companyId', $companyId)
            ->setParameter('reportType', $reportType)
            ->orderBy('f.reportDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find latest financial data by company
     */
    public function findLatestByCompany(int $companyId, int $limit = 4): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.company = :companyId')
            ->setParameter('companyId', $companyId)
            ->orderBy('f.reportDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get quarterly financial data for a specific year
     */
    public function findQuarterlyDataByYear(int $companyId, int $year): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.company = :companyId')
            ->andWhere('f.reportType = :reportType')
            ->andWhere('YEAR(f.reportDate) = :year')
            ->setParameter('companyId', $companyId)
            ->setParameter('reportType', '10-Q')
            ->setParameter('year', $year)
            ->orderBy('f.reportDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get annual financial data for a range of years
     */
    public function findAnnualDataByYearRange(int $companyId, int $startYear, int $endYear): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.company = :companyId')
            ->andWhere('f.reportType = :reportType')
            ->andWhere('YEAR(f.reportDate) >= :startYear')
            ->andWhere('YEAR(f.reportDate) <= :endYear')
            ->setParameter('companyId', $companyId)
            ->setParameter('reportType', '10-K')
            ->setParameter('startYear', $startYear)
            ->setParameter('endYear', $endYear)
            ->orderBy('f.reportDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
