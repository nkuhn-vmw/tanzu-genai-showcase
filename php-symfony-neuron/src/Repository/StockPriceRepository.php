<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\StockPrice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockPrice>
 */
class StockPriceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockPrice::class);
    }

    /**
     * Find the most recent stock price for a company
     *
     * @param Company $company The company
     * @param string $period The period (daily, weekly, monthly)
     * @return StockPrice|null The most recent stock price or null if not found
     */
    public function findMostRecent(Company $company, string $period = 'daily'): ?StockPrice
    {
        return $this->createQueryBuilder('sp')
            ->andWhere('sp.company = :company')
            ->andWhere('sp.period = :period')
            ->setParameter('company', $company)
            ->setParameter('period', $period)
            ->orderBy('sp.date', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find stock prices for a company within a date range
     *
     * @param Company $company The company
     * @param \DateTimeInterface $startDate The start date
     * @param \DateTimeInterface|null $endDate The end date (defaults to current date)
     * @param string $period The period (daily, weekly, monthly)
     * @return StockPrice[] Returns an array of StockPrice objects
     */
    public function findByDateRange(
        Company $company,
        \DateTimeInterface $startDate,
        ?\DateTimeInterface $endDate = null,
        string $period = 'daily'
    ): array {
        if ($endDate === null) {
            $endDate = new \DateTime();
        }

        return $this->createQueryBuilder('sp')
            ->andWhere('sp.company = :company')
            ->andWhere('sp.date >= :startDate')
            ->andWhere('sp.date <= :endDate')
            ->andWhere('sp.period = :period')
            ->setParameter('company', $company)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('period', $period)
            ->orderBy('sp.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if stock prices exist for a specific date
     *
     * @param Company $company The company
     * @param \DateTimeInterface $date The date to check
     * @param string $period The period (daily, weekly, monthly)
     * @return bool Whether stock prices exist for this date
     */
    public function existsForDate(Company $company, \DateTimeInterface $date, string $period = 'daily'): bool
    {
        $count = $this->createQueryBuilder('sp')
            ->select('COUNT(sp.id)')
            ->andWhere('sp.company = :company')
            ->andWhere('sp.date = :date')
            ->andWhere('sp.period = :period')
            ->setParameter('company', $company)
            ->setParameter('date', $date)
            ->setParameter('period', $period)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Get monthly average closing prices for a company over a date range
     *
     * @param Company $company The company
     * @param \DateTimeInterface $startDate The start date
     * @param \DateTimeInterface|null $endDate The end date (defaults to current date)
     * @return array Array of monthly average prices with year-month keys
     */
    public function getMonthlyAverages(
        Company $company,
        \DateTimeInterface $startDate,
        ?\DateTimeInterface $endDate = null
    ): array {
        if ($endDate === null) {
            $endDate = new \DateTime();
        }

        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT 
                YEAR(sp.date) as year,
                MONTH(sp.date) as month,
                AVG(sp.close) as average_close
            FROM 
                stock_price sp
            WHERE 
                sp.company_id = :company_id
                AND sp.date >= :start_date
                AND sp.date <= :end_date
                AND sp.period = :period
            GROUP BY 
                YEAR(sp.date), MONTH(sp.date)
            ORDER BY 
                YEAR(sp.date) ASC, MONTH(sp.date) ASC
        ';

        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery([
            'company_id' => $company->getId(),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'period' => 'daily'
        ]);

        $results = [];
        foreach ($resultSet->fetchAllAssociative() as $row) {
            $yearMonth = sprintf('%04d-%02d', $row['year'], $row['month']);
            $results[$yearMonth] = (float) $row['average_close'];
        }

        return $results;
    }
}
