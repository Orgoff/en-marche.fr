<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="AppBundle\Repository\TransactionRepository")
 */
class Transaction
{
    /**
     * @var int
     *
     * @ORM\Column(type="integer", options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string|null
     *
     * @ORM\Column(length=100, nullable=true)
     */
    private $payboxResultCode;

    /**
     * @var string|null
     *
     * @ORM\Column(length=100, nullable=true)
     */
    private $payboxAuthorizationCode;

    /**
     * @var array|null
     *
     * @ORM\Column(type="json_array", nullable=true)
     */
    private $payboxPayload;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime")
     */
    private $payboxDateTime;

    /**
     * @var string
     *
     * @ORM\Column(unique=true)
     */
    private $payboxTransactionId;

    /**
     * @var string|null
     *
     * @ORM\Column(nullable=true)
     */
    private $payboxSubscriptionId;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @var Donation
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Donation", inversedBy="transactions")
     */
    private $donation;

    public function __construct(Donation $donation, array $payboxPayload)
    {
        $this->donation = $donation;
        $this->createdAt = new \DateTime();
        $this->payboxPayload = $payboxPayload;
        [
            'result' => $this->payboxResultCode,
            'transaction' => $this->payboxTransactionId,
            'date' => $date,
            'time' => $time,
        ] = $payboxPayload;

        $this->payboxDateTime = \DateTime::createFromFormat(
            'Y/m/d H:i:s',
            substr($date, 4, 4).'/'.substr($date, 2, 2).'/'.substr($date, 0, 2).' '.$time
        );

        if (isset($payboxPayload['authorization'])) {
            $this->payboxAuthorizationCode = $payboxPayload['authorization'];
        }

        if (isset($payboxPayload['subscription']) && $subscription = $payboxPayload['subscription']) {
            $this->payboxSubscriptionId = $subscription;
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPayboxPayloadAsJson(): string
    {
        return json_encode($this->payboxPayload, JSON_PRETTY_PRINT);
    }

    public function getPayboxResultCode(): ?string
    {
        return $this->payboxResultCode;
    }

    public function getPayboxAuthorizationCode(): ?string
    {
        return $this->payboxAuthorizationCode;
    }

    public function getPayboxPayload(): ?array
    {
        return $this->payboxPayload;
    }

    public function getDonation(): Donation
    {
        return $this->donation;
    }

    public function getPayboxDateTime(): \DateTime
    {
        return $this->payboxDateTime;
    }

    public function getPayboxTransactionId(): string
    {
        return $this->payboxTransactionId;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getPayboxSubscriptionId(): ?string
    {
        return $this->payboxSubscriptionId;
    }

    public function isSuccess(): bool
    {
        return '00000' === $this->payboxResultCode;
    }
}
