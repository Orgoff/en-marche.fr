<?php

namespace AppBundle\Donation;

use AppBundle\Entity\Donation;
use AppBundle\Mailer\MailerService;
use AppBundle\Mailer\Message\DonationMessage;
use AppBundle\Repository\TransactionRepository;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TransactionCallbackHandler
{
    private $router;
    private $entityManager;
    private $mailer;
    private $donationRequestUtils;
    /**
     * @var TransactionRepository
     */
    private $transactionRepository;

    public function __construct(UrlGeneratorInterface $router, ObjectManager $entityManager, MailerService $mailer, DonationRequestUtils $donationRequestUtils, TransactionRepository $transactionRepository)
    {
        $this->router = $router;
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
        $this->donationRequestUtils = $donationRequestUtils;
        $this->transactionRepository = $transactionRepository;
    }

    public function handle(string $uuid, Request $request, string $callbackToken): Response
    {
        $donation = $this->entityManager->getRepository(Donation::class)->findOneByUuid($uuid);

        if (!$donation) {
            return new RedirectResponse($this->router->generate('donation_index'));
        }

        $payload = $this->donationRequestUtils->extractPayboxResultFromCallBack($request, $callbackToken);

        if (!$transaction = $this->transactionRepository->findByPayboxTransactionId($payload['transaction'])) {
            $transaction = $donation->processPayload($payload);

            $this->entityManager->flush();

            $campaignExpired = (bool) $request->attributes->get('_campaign_expired', false);
            if (!$campaignExpired && $transaction->isSuccess()) {
                $this->mailer->sendMessage(DonationMessage::createFromDonation($transaction));
            }
        }

        return new RedirectResponse($this->router->generate(
            'donation_result',
            $this->donationRequestUtils->createCallbackStatus($transaction)
        ));
    }
}
