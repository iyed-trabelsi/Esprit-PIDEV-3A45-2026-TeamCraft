<?php
namespace App\Command;

use App\Service\AiSchedulingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:test-ai')]
class TestAiCommand extends Command
{
    public function __construct(private AiSchedulingService $aiService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Calling AI service...');
        $result = $this->aiService->generateOptimalEvent();
        $output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return Command::SUCCESS;
    }
}
