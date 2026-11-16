<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Model;

/** How to reach the visitor. Stays in the code: the model only sees the masked phone. */
final readonly class HandoffContact
{
    public function __construct(
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $name = null,
    ) {
    }

    /** This contact, filling what it lacks from the other. */
    public function orElse(self $other): self
    {
        return new self($this->email ?? $other->email, $this->phone ?? $other->phone, $this->name ?? $other->name);
    }

    public function satisfies(ContactNeed $need): bool
    {
        return match ($need) {
            ContactNeed::None => true,
            ContactNeed::Any => null !== $this->email || null !== $this->phone,
            ContactNeed::Email => null !== $this->email,
            ContactNeed::Phone => null !== $this->phone,
        };
    }

    public function maskedPhone(): ?string
    {
        return null === $this->phone ? null : '****' . \substr($this->phone, -4);
    }
}
