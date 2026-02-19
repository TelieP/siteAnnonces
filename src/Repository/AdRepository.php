<?php

namespace App\Repository;

use App\Entity\Ad;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ad>
 */
class AdRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ad::class);
    }

   

public function findBySearch(?string $query, ?string $location, ?float $min, ?float $max , ?int $categoryId = null): array
{ // L'accolade doit être ici !
    $qb = $this->createQueryBuilder('a');

    if ($query) {
        $qb->andWhere('a.title LIKE :q OR a.description LIKE :q')
           ->setParameter('q', '%' . $query . '%');
    }

    if ($location) {
        $qb->andWhere('a.city LIKE :loc')
           ->setParameter('loc', '%' . $location . '%');
    }

    if ($min) {
        $qb->andWhere('a.price >= :min')
           ->setParameter('min', $min);
    }

    if ($max) {
        $qb->andWhere('a.price <= :max')
           ->setParameter('max', $max);
    }

    if ($categoryId) {
        $qb->andWhere('a.category = :catId')
           ->setParameter('catId', $categoryId);
    }

    return $qb->orderBy('a.createdAt', 'DESC')
              ->getQuery()
              ->getResult();
}


public function countAdsByDay(): array
{
    $data = [];
    $labels = [];

    for ($i = 6; $i >= 0; $i--) {
        $date = new \DateTimeImmutable("-$i days");
        $labels[] = $date->format('d/m');

        $start = $date->setTime(0, 0, 0);
        $end = $date->setTime(23, 59, 59);

        $count = $this->createQueryBuilder('a')
            ->select('count(a.id)')
            ->where('a.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        $data[] = $count;
    }

    return ['labels' => $labels, 'data' => $data];
}


    //    /**
    //     * @return Ad[] Returns an array of Ad objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Ad
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
