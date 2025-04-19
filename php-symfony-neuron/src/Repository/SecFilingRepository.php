<?php

namespace App\Repository;

use App\Entity\SecFiling;
use App\Entity\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method SecFiling|null find($id, $lockMode = null, $lockVersion = null)
 * @method SecFiling|null findOneBy(array $criteria, array $orderBy = null)
 * @method SecFiling[]    findAll()
 * @method SecFiling[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SecFilingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SecFiling::class);
    }

    /**
     * Find recent filings for a company
     *
     * @param Company $company The company
     * @param string $formType Optional form type filter
     * @param int $limit Maximum number of results
     * @return SecFiling[] Returns an array of SecFiling objects
     */
    public function findRecentFilingsByCompany(Company $company, ?string $formType = null, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('f')
            ->andWhere('f.company = :company')
            ->setParameter('company', $company)
            ->orderBy('f.filingDate', 'DESC')
            ->setMaxResults($limit);
        
        if ($formType) {
            $qb->andWhere('f.formType = :formType')
               ->setParameter('formType', $formType);
        }
        
        return $qb->getQuery()->getResult();
    }
    
    /**
     * Find the latest 10-K report for a company
     *
     * @param Company $company The company
     * @return SecFiling|null Returns a SecFiling object or null
     */
    public function findLatest10K(Company $company): ?SecFiling
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.company = :company')
            ->andWhere('f.formType = :formType')
            ->setParameter('company', $company)
            ->setParameter('formType', '10-K')
            ->orderBy('f.filingDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
    
    /**
     * Find the latest filing by form type for a company
     *
     * @param Company $company The company
     * @param string $formType The SEC form type
     * @return SecFiling|null Returns a SecFiling object or null
     */
    public function findLatestByFormType(Company $company, string $formType): ?SecFiling
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.company = :company')
            ->andWhere('f.formType = :formType')
            ->setParameter('company', $company)
            ->setParameter('formType', $formType)
            ->orderBy('f.filingDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
    
    /**
     * Find filings by fiscal year
     *
     * @param Company $company The company
     * @param string $fiscalYear The fiscal year
     * @return SecFiling[] Returns an array of SecFiling objects
     */
    public function findByFiscalYear(Company $company, string $fiscalYear): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.company = :company')
            ->andWhere('f.fiscalYear = :fiscalYear')
            ->setParameter('company', $company)
            ->setParameter('fiscalYear', $fiscalYear)
            ->orderBy('f.filingDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Find filings by accession number
     *
     * @param string $accessionNumber The SEC accession number
     * @return SecFiling|null Returns a SecFiling object or null
     */
    public function findByAccessionNumber(string $accessionNumber): ?SecFiling
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.accessionNumber = :accessionNumber')
            ->setParameter('accessionNumber', $accessionNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }
    
    /**
     * Find filings that need processing (downloaded but not yet processed)
     *
     * @param int $limit Maximum number of filings to process
     * @return SecFiling[] Returns an array of SecFiling objects
     */
    public function findUnprocessedFilings(int $limit = 10): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.content IS NOT NULL')
            ->andWhere('f.isProcessed = :isProcessed')
            ->setParameter('isProcessed', false)
            ->orderBy('f.filingDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
