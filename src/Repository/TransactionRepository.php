<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    public function findByPayboxTransactionId(string $transactionId): ?Transaction
    {
        return $this->createQueryBuilder('transaction')
            ->where('transaction.payboxTransactionId = :transactionId')
            ->setParameters([
                'transactionId' => $transactionId,
            ])
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * @return Transaction[]
     */
    public function findAllTransactionSuccess(string $emailAddress, string $ordered = 'ASC'): array
    {
        return $this->createQueryBuilder('transaction')
            ->innerJoin('transaction.donation', 'donation')
            ->andWhere('donation.emailAddress = :email')
            ->andWhere('transaction.payboxResultCode = :resultCode')
            ->setParameters([
                'resultCode' => '00000',
                'email' => $emailAddress,
            ])
            ->orderBy('transaction.payboxDateTime', $ordered)
            ->getQuery()
            ->getResult()
        ;
    }
}
