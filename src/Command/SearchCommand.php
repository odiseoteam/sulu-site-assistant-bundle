<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Command;

use Odiseo\AiConversationalAgentBundle\Support\Scalar;
use Odiseo\SuluSiteAssistantBundle\Content\ContentIndexInterface;
use Odiseo\SuluSiteAssistantBundle\Site\Webspaces;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** What the assistant finds for a query, or how it reads one page: for checking the index. */
#[AsCommand(name: 'site-assistant:search', description: 'Search the assistant\'s index, or show one page as the assistant reads it')]
final class SearchCommand extends Command
{
    public function __construct(
        private readonly ContentIndexInterface $index,
        private readonly Webspaces $webspaces,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('query', InputArgument::OPTIONAL, 'Words to search for', '')
            ->addOption('locale', 'l', InputOption::VALUE_REQUIRED, 'Locale; the webspace default when omitted')
            ->addOption('webspace', 'w', InputOption::VALUE_REQUIRED, 'Webspace; the configured one when omitted')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum results', '6')
            ->addOption('show', null, InputOption::VALUE_REQUIRED, 'A content_id to print section by section');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $show = $input->getOption('show');
        if (\is_string($show)) {
            $document = $this->index->get($show);
            if (null === $document) {
                $io->error(\sprintf('No page with content_id %s.', $show));

                return self::FAILURE;
            }
            $io->title($document->title);
            $io->text([\sprintf('%s · %s · %s', $document->templateKey, $document->locale, $document->url), $document->summary]);
            foreach ($document->sections as $section) {
                $io->section($section->heading ?? '—');
                $io->text($section->text);
            }

            return self::SUCCESS;
        }

        $webspace = $input->getOption('webspace');
        $webspace = \is_string($webspace) ? $webspace : $this->webspaces->default();
        $locale = $input->getOption('locale');
        $locale = \is_string($locale) ? $locale : $this->webspaces->defaultLocale($webspace);
        $query = Scalar::string($input->getArgument('query'));
        $hits = $this->index->search($query, $locale, $webspace, [], \max(1, Scalar::int($input->getOption('limit'), 6)));

        $io->table(['content_id', 'template', 'title', 'passage'], \array_map(static fn ($hit): array => [
            $hit->document->id,
            $hit->document->templateKey,
            $hit->document->title,
            \mb_substr($hit->passage ?? $hit->document->summary, 0, 90),
        ], $hits));

        return self::SUCCESS;
    }
}
