<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Infrastructure\Persistence\Doctrine\HoldRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Marks active holds with expires_at in the past as Expired (HoldStatus::Expired).
 * Run periodically (e.g. cron) to free reserved amounts for overdue holds.
 */
#[AsCommand(
    name: 'app:expire-holds',
    description: 'Mark overdue active holds as Expired',
)]
final class ExpireHoldsCommand extends Command
{
    public function __construct(
        private readonly HoldRepository $holdRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = $this->holdRepository->markExpiredOverdue();
        $io->success(sprintf('Marked %d hold(s) as expired.', $count));

        return Command::SUCCESS;
    }
}
