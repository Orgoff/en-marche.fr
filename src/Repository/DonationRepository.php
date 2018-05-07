<?php

namespace AppBundle\Repository;

use AppBundle\Donation\DonationStatusEnum;
use AppBundle\Donation\PayboxPaymentSubscription;
use AppBundle\Entity\Donation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

class DonationRepository extends ServiceEntityRepository
{
    use UuidEntityRepositoryTrait {
        findOneByUuid as findOneByValidUuid;
    }

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Donation::class);
    }

    /**
     * {@inheritdoc}
     */
    public function findOneByUuid(string $uuid): ?Donation
    {
        return $this->findOneByValidUuid($uuid);
    }

    /**
     * @return Donation[]
     */
    public function findAllSubscribedDonationByEmail(string $email): array
    {
        return $this->createQueryBuilder('donation')
            ->andWhere('donation.emailAddress = :email')
            ->andWhere('donation.duration != :duration')
            ->andWhere('donation.status = :status')
            ->andWhere('donation.subscriptionEndedAt IS NULL')
            ->setParameters([
                'status' => DonationStatusEnum::SUBSCRIPTION_IN_PROGRESS,
                'email' => $email,
                'duration' => PayboxPaymentSubscription::NONE,
            ])
            ->orderBy('donation.createdAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
