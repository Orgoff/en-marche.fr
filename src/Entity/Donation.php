<?php

namespace AppBundle\Entity;

use Algolia\AlgoliaSearchBundle\Mapping\Annotation as Algolia;
use AppBundle\Donation\DonationStatusEnum;
use AppBundle\Donation\PayboxPaymentSubscription;
use AppBundle\Geocoder\GeoPointInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use libphonenumber\PhoneNumber;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Table(name="donations")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\DonationRepository")
 *
 * @Algolia\Index(autoIndex=false)
 */
class Donation implements GeoPointInterface
{
    use EntityIdentityTrait;
    use EntityCrudTrait;
    use EntityPostAddressTrait;
    use EntityPersonNameTrait;

    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     */
    private $amount;

    /**
     * @var int
     *
     * @ORM\Column(type="smallint", options={"default": 0})
     */
    private $duration;

    /**
     * @var string
     *
     * @ORM\Column(length=6)
     */
    private $gender;

    /**
     * @var string
     *
     * @ORM\Column
     */
    private $emailAddress;

    /**
     * @var string
     *
     * @ORM\Column(type="phone_number", nullable=true)
     */
    private $phone;

    /**
     * @var string
     *
     * @ORM\Column(length=50, nullable=true)
     */
    private $clientIp;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $subscriptionEndedAt;

    /**
     * @var string
     *
     * @ORM\Column(length=25)
     */
    private $status;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime")
     * @Gedmo\Timestampable(on="update")
     */
    private $updatedAt;

    /**
     * @var string
     *
     * @ORM\Column(nullable=true)
     */
    private $payboxOrderRef;

    /**
     * @var Collection|Transaction[]
     *
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Transaction", mappedBy="donation", cascade={"persist"})
     */
    private $transactions;

    public function __construct(
        UuidInterface $uuid,
        string $payboxOrderRef,
        int $amount,
        string $gender,
        string $firstName,
        string $lastName,
        string $emailAddress,
        PostAddress $postAddress,
        ?PhoneNumber $phone,
        string $clientIp,
        int $duration = PayboxPaymentSubscription::NONE
    ) {
        $this->uuid = $uuid;
        $this->amount = $amount;
        $this->gender = $gender;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->emailAddress = $emailAddress;
        $this->postAddress = $postAddress;
        $this->phone = $phone;
        $this->clientIp = $clientIp;
        $this->createdAt = new \DateTime();
        $this->duration = $duration;
        $this->status = DonationStatusEnum::WAITING_CONFIRMATION;
        $this->payboxOrderRef = $payboxOrderRef;
        $this->transactions = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->lastName.' '.$this->firstName.' ('.($this->amount / 100).' €)';
    }

    public function processPayload(array $payboxPayload): Transaction
    {
        $transaction = new Transaction($this, $payboxPayload);

        $this->transactions[] = $transaction;
        if ($transaction->isSuccess()) {
            $this->status = DonationStatusEnum::FINISHED;
            if (PayboxPaymentSubscription::NONE !== $this->duration) {
                $this->status = DonationStatusEnum::SUBSCRIPTION_IN_PROGRESS;
            }
        } else {
            $this->status = DonationStatusEnum::ERROR;
        }

        return $transaction;
    }

    public function stopSubscription(): void
    {
        $this->setSubscriptionEndedAt(new \DateTime());
        $this->status = DonationStatusEnum::CANCELED;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getDuration(): int
    {
        return $this->duration;
    }

    public function hasSubscription(): bool
    {
        return PayboxPaymentSubscription::NONE !== $this->duration;
    }

    public function hasUnlimitedSubscription(): bool
    {
        return PayboxPaymentSubscription::UNLIMITED === $this->duration;
    }

    public function getAmountInEuros()
    {
        return (float) $this->amount / 100;
    }

    public function getGender(): string
    {
        return $this->gender;
    }

    public function getEmailAddress(): string
    {
        return $this->emailAddress;
    }

    public function getPhone(): PhoneNumber
    {
        return $this->phone;
    }

    public function getClientIp(): ?string
    {
        return $this->clientIp;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getRetryPayload(): array
    {
        $payload = [
            'ge' => $this->gender,
            'ln' => $this->lastName,
            'fn' => $this->firstName,
            'em' => urlencode($this->emailAddress),
            'co' => $this->getCountry(),
            'pc' => $this->getPostalCode(),
            'ci' => $this->getCityName(),
            'ad' => urlencode($this->getAddress()),
        ];

        if ($this->phone) {
            $payload['phc'] = $this->phone->getCountryCode();
            $payload['phn'] = $this->phone->getNationalNumber();
        }

        return $payload;
    }

    public function getSubscriptionEndedAt(): ?\DateTime
    {
        return $this->subscriptionEndedAt;
    }

    public function setSubscriptionEndedAt(?\DateTime $subscriptionEndedAt): void
    {
        $this->subscriptionEndedAt = $subscriptionEndedAt;
    }

    public function nextDonationAt(\DateTime $fromDay = null): \DateTime
    {
        if (!$this->hasSubscription()) {
            throw new \LogicException('Donation without subscription can\'t have next donation date.');
        }

        if (!$fromDay) {
            $fromDay = new \DateTime();
        }

        $donationDate = clone $this->createdAt;

        return $donationDate->modify(
            sprintf('+%d months', $donationDate->diff($fromDay)->m + 1)
        );
    }

    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isError(): bool
    {
        return DonationStatusEnum::ERROR === $this->getStatus();
    }

    public function getPayboxOrderRef(): string
    {
        return $this->payboxOrderRef;
    }

    public function getPayboxOrderRefWithSuffix(): string
    {
        return $this->payboxOrderRef.PayboxPaymentSubscription::getCommandSuffix($this->amount, $this->duration);
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
