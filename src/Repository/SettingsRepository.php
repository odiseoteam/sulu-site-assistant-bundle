<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Odiseo\SuluSiteAssistantBundle\Entity\SettingsInterface;

/** The webspaces' saved settings, one row per webspace. */
class SettingsRepository
{
    /** @param class-string<SettingsInterface> $class */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $class,
    ) {
    }

    public function findOneByWebspace(string $webspaceKey): ?SettingsInterface
    {
        /** @var SettingsInterface|null */
        return $this->em->getRepository($this->class)->findOneBy(['webspaceKey' => $webspaceKey]);
    }

    /** @return list<SettingsInterface> */
    public function findAll(): array
    {
        /** @var list<SettingsInterface> */
        return $this->em->getRepository($this->class)->findAll();
    }

    /** A new row for the webspace, not persisted. */
    public function create(string $webspaceKey): SettingsInterface
    {
        $settings = new $this->class();
        $settings->setWebspaceKey($webspaceKey);

        return $settings;
    }

    /** @return list<int> the retention days the webspaces that set one keep */
    public function findMemoryRetentionDays(): array
    {
        /** @var list<int|string> $days */
        $days = $this->em->createQueryBuilder()
            ->select('s.memoryRetentionDays')
            ->from($this->class, 's')
            ->where('s.memoryRetentionDays IS NOT NULL')
            ->getQuery()
            ->getSingleColumnResult();

        return \array_map(intval(...), $days);
    }
}
