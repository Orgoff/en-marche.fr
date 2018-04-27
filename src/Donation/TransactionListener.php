<?php

namespace AppBundle\Donation;

use AppBundle\Entity\Donation;
use AppBundle\Mailer\MailerService;
use AppBundle\Mailer\Message\DonationMessage;
use Doctrine\Common\Persistence\ObjectManager;
use Lexik\Bundle\PayboxBundle\Event\PayboxResponseEvent;
use Symfony\Component\HttpFoundation\RequestStack;

class TransactionListener
{
    private $manager;
    private $mailer;
    private $requestStack;

    public function __construct(ObjectManager $manager, MailerService $mailer, RequestStack $requestStack)
    {
        $this->manager = $manager;
        $this->mailer = $mailer;
        $this->requestStack = $requestStack;
    }

    /**
     * Update the database for the given donation with the Paybox data.
     *
     * @param PayboxResponseEvent $event
     */
    public function onPayboxIpnResponse(PayboxResponseEvent $event): void
    {
        if (!$event->isVerified()) {
            return;
        }

        $payboxPayload = $event->getData();

        if (!isset($payboxPayload['id'])) {
            return;
        }

        $id = explode('_', $payboxPayload['id'])[0];
        $donation = $this->manager->getRepository(Donation::class)->findOneByUuid($id);

        if (!$donation) {
            return;
        }

        $transaction = $donation->processPayload($payboxPayload);

        $this->manager->persist($donation);
        $this->manager->flush();

        $campaignExpired = (bool) $this->requestStack->getCurrentRequest()->attributes->get('_campaign_expired', false);
        if (!$campaignExpired && $transaction->isSuccess()) {
            $this->mailer->sendMessage(DonationMessage::createFromDonation($transaction));
        }
    }
}
