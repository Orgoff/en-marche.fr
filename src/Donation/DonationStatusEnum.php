<?php

namespace AppBundle\Donation;

use MyCLabs\Enum\Enum;

class DonationStatusEnum extends Enum
{
    public const WAITING_CONFIRMATION = 'waiting_confirmation';
    public const SUBSCRIPTION_IN_PROGRESS = 'subscription_in_progress';
    public const REFUNDED = 'refunded';
    public const CANCELED = 'canceled';
    public const FINISHED = 'finished';
    public const ERROR = 'error';
}
