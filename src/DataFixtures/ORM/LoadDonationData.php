<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\Donation\PayboxPaymentSubscription;
use AppBundle\Entity\Adherent;
use AppBundle\Entity\Donation;
use Cocur\Slugify\Slugify;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;

class LoadDonationData extends Fixture
{
    private $slugify;

    public function __construct()
    {
        $this->slugify = Slugify::create();
    }

    public function load(ObjectManager $manager)
    {
        /** @var Adherent $adherent1 */
        $adherent1 = $this->getReference('adherent-4');
        /** @var Adherent $adherent2 */
        $adherent2 = $this->getReference('adherent-3');

        $donation0 = $this->create($adherent1, 50.);
        $donation1 = $this->create($adherent2, 50.);
        $donation2 = $this->create($adherent2, 40.);
        $donation3 = $this->create($adherent2, 60., PayboxPaymentSubscription::UNLIMITED);
        $donation4 = $this->create($adherent2, 100., PayboxPaymentSubscription::UNLIMITED);

        $donation3->stopSubscription();

        $this->setDonateAt($donation2, '-1 day');
        $this->setDonateAt($donation3, '-100 day');
        $this->setDonateAt($donation4, '-50 day');

        $manager->persist($donation0);
        $manager->persist($donation1);
        $manager->persist($donation2);
        $manager->persist($donation3);
        $manager->persist($donation4);

        /** @var Adherent $adherent1 */
        $adherent1 = $this->getReference('adherent-1');

        $donationNormal = $this->create($adherent1);
        $donationMonthly = $this->create($adherent1, 42., PayboxPaymentSubscription::UNLIMITED);

        $manager->persist($donationNormal);
        $manager->persist($donationMonthly);

        $manager->flush();
    }

    public function create(Adherent $adherent, float $amount = 50.0, int $duration = PayboxPaymentSubscription::NONE): Donation
    {
        $donation = new Donation(
            $uuid = Uuid::uuid4(),
            $uuid->toString().'_'.$this->slugify->slugify($adherent->getFullName()),
            $amount * 100,
            $adherent->getGender(),
            $adherent->getFirstName(),
            $adherent->getLastName(),
            $adherent->getEmailAddress(),
            $adherent->getPostAddress(),
            $adherent->getPhone(),
            '127.0.0.1',
            $duration
        );

        $donation->processPayload([
            'result' => '00000',
            'authorization' => 'test',
            'subscription' => $duration ? Uuid::uuid1()->toString() : null,
            'transaction' => Uuid::uuid4()->toString(),
            'date' => '02022018',
            'time' => '15:22:33',
        ]);

        return $donation;
    }

    public function setDonateAt(Donation $donation, string $modifier): void
    {
        $reflectDonation = new \ReflectionObject($transaction = $donation->getTransactions()->first());
        $reflectDonationAt = $reflectDonation->getProperty('payboxDateTime');
        $reflectDonationAt->setAccessible(true);
        $reflectDonationAt->setValue($transaction, new \DateTime($modifier));
        $reflectDonationAt->setAccessible(false);
    }

    public function getDependencies()
    {
        return [
            LoadAdherentData::class,
        ];
    }
}
