<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Handoff\Notice;

use Odiseo\SuluSiteAssistantBundle\Entity\HandoffInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Mails the configured recipients. The visitor's email goes as Reply-To, so the team answers them directly. */
final class EmailHandoffNotice implements HandoffNoticeInterface
{
    /** @param list<string> $recipients */
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly array $recipients,
        private readonly ?string $from = null,
        private readonly ?string $adminUrl = null,
        private readonly string $template = '@OdiseoSuluSiteAssistant/email/handoff_notice.html.twig',
        private readonly string $locale = 'en',
    ) {
    }

    public function send(HandoffInterface $handoff): void
    {
        if ([] === $this->recipients) {
            throw new \RuntimeException(\sprintf('Handoff %s: no recipient is configured for the email notice.', $handoff->getReference()));
        }

        $email = (new TemplatedEmail())
            ->to(...$this->recipients)
            ->subject($this->translator->trans('odiseo_sulu_site_assistant.email.handoff.subject', [
                '%reference%' => $handoff->getReference(),
                '%topic%' => $this->translator->trans('odiseo_site_assistant.handoff_topic.' . $handoff->getTopic(), [], 'admin', $this->locale),
            ], 'messages', $this->locale))
            ->htmlTemplate($this->template)
            ->locale($this->locale)
            ->context([
                'handoff' => $handoff,
                'handoff_url' => null === $this->adminUrl || null === $handoff->getId() ? null : \sprintf('%s/#/site-assistant/handoffs/%d', \rtrim($this->adminUrl, '/'), $handoff->getId()),
                'notice_locale' => $this->locale,
            ]);

        if (null !== $this->from && '' !== $this->from) {
            $email->from(Address::create($this->from));
        }
        if (null !== $handoff->getContactEmail()) {
            $email->replyTo(new Address($handoff->getContactEmail(), $handoff->getContactName() ?? ''));
        }

        $this->mailer->send($email);
    }
}
