<?php

namespace SilverStripe\Maintain\Command\GitHub;

use DateTime;
use SilverStripe\Maintain\Api\GitHub;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Helper command to show the current status of the GitHub API rate limit
 */
class RateLimit extends Command
{
    /**
     * @var GitHub
     */
    protected $github;

    /**
     * @param GitHub $github
     */
    public function __construct(GitHub $github)
    {
        parent::__construct();

        $this->github = $github;
    }

    protected function configure()
    {
        $this
            ->setName('github:ratelimit')
            ->setDescription('Shows the current status of the GitHub API rate limiting');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var \Github\Api\RateLimit $rateLimitApi */
        $rateLimitApi = $this->github->getClient()->rateLimit();

        $result = $rateLimitApi->getRateLimits();

        if (empty($result['resources']['core'])) {
            $output->writeln('<error>Failed to get rate limiting data!</error>');
        }
        $data = $result['resources']['core'];

        $output->writeln('Limit: <comment>' . $data['limit'] . '</comment>');

        $remainingFormat = $data['remaining'] > 0 ? 'info' : 'error';
        $output->writeln('Remaining: <' . $remainingFormat . '>' . $data['remaining'] . '</' . $remainingFormat . '>');

        $now = new DateTime();
        $resetDate = new DateTime();
        $resetDate->setTimestamp($data['reset']);
        $output->writeln('Resets in: <comment>' . $resetDate->diff($now)->i . ' mins</comment>');
    }
}
