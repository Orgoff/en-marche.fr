<?php

namespace AppBundle\Entity;

interface ReferentTaggableEntity extends EntityPostAddressInterface
{
    public function addReferentTag(ReferentTag $referentTag): void;

    public function removeReferentTags(): void;
}
