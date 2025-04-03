<?php

namespace App\Repository;

use App\Entity\ResearchReport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ResearchReport>
 *
 * @method ResearchReport|null find($id, $lockMode = null, $lockVersion = null)
 * @method ResearchReport|null findOneBy(array $criteria, array $orderBy = null)
 * @method ResearchReport[]    findAll()
 * @method ResearchReport[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ResearchReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResearchReport::class);
    }

    public function save(ResearchReport $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ResearchReport $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find reports by company
     */
    public function findByCompany(int $companyId): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.company = :companyId')
            ->setParameter('companyId', $companyId)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find reports by type
     */
    public function findByReportType(string $reportType): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.reportType = :reportType')
            ->setParameter('reportType', $reportType)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find reports containing a specific term in their title or content
     */
    public function findBySearchTerm(string $searchTerm): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.title LIKE :searchTerm')
            ->orWhere('r.executiveSummary LIKE :searchTerm')
            ->orWhere('r.companyOverview LIKE :searchTerm')
            ->orWhere('r.industryAnalysis LIKE :searchTerm')
            ->orWhere('r.financialAnalysis LIKE :searchTerm')
            ->orWhere('r.competitiveAnalysis LIKE :searchTerm')
            ->orWhere('r.swotAnalysis LIKE :searchTerm')
            ->orWhere('r.investmentHighlights LIKE :searchTerm')
            ->orWhere('r.risksAndChallenges LIKE :searchTerm')
            ->orWhere('r.conclusion LIKE :searchTerm')
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recently generated reports
     */
    public function findRecentReports(int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find reports by industry
     */
    public function findByIndustry(string $industry): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.company', 'company')
            ->where('company.industry = :industry')
            ->setParameter('industry', $industry)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
