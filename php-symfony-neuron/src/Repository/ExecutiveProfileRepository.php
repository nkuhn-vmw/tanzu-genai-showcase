<?php

namespace App\Repository;

use App\Entity\ExecutiveProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExecutiveProfile>
 *
 * @method ExecutiveProfile|null find($id, $lockMode = null, $lockVersion = null)
 * @method ExecutiveProfile|null findOneBy(array $criteria, array $orderBy = null)
 * @method ExecutiveProfile[]    findAll()
 * @method ExecutiveProfile[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ExecutiveProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExecutiveProfile::class);
    }

    public function save(ExecutiveProfile $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ExecutiveProfile $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find executives by company
     */
    public function findByCompany(int $companyId): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.company = :companyId')
            ->setParameter('companyId', $companyId)
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find executives by title (e.g., CEO, CFO, CTO)
     */
    public function findByTitle(string $title): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.title LIKE :title')
            ->setParameter('title', '%' . $title . '%')
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find executives by name
     */
    public function findByName(string $name): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.name LIKE :name')
            ->setParameter('name', '%' . $name . '%')
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find executives with experience at specific companies
     */
    public function findByPreviousCompany(string $companyName): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.previousCompanies LIKE :companyName')
            ->setParameter('companyName', '%' . $companyName . '%')
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
