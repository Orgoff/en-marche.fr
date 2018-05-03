<?php

namespace AppBundle\Membership;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class MembershipUtils
{
    private const NEW_ADHERENT_UUID = 'membership.new_adherent_uuid';

    private $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    public function setSessionNewAdherentUuid(string $uuid): void
    {
        $this->session->set(self::NEW_ADHERENT_UUID, $uuid);
    }

    public function getSessionNewAdherentUuid(): ?string
    {
        return $this->session->get(self::NEW_ADHERENT_UUID);
    }

    public function hasSessionNewAdherentUuid(): bool
    {
        return $this->session->has(self::NEW_ADHERENT_UUID);
    }

    public function clearSessionNewAdherentUuid(): void
    {
        $this->session->remove(self::NEW_ADHERENT_UUID);
    }
}
