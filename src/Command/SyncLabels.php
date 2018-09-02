<?php

namespace SilverStripe\Maintain\Command;

use Exception;
use Github\Api\Issue\Labels;
use Github\Client;
use SilverStripe\Maintain\Loader\SupportedModuleLoader;
use SilverStripe\Maintain\Loader\TemplateLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use UnexpectedValueException;

class SyncLabels extends Command
{
    /**
     * @var SupportedModuleLoader
     */
    protected $supportedModuleLoader;

    /**
     * @var TemplateLoader
     */
    protected $templateLoader;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @param SupportedModuleLoader $moduleLoader
     * @param TemplateLoader $templateLoader
     */
    public function __construct(SupportedModuleLoader $supportedModuleLoader, TemplateLoader $templateLoader)
    {
        parent::__construct();

        $this->supportedModuleLoader = $supportedModuleLoader;
        $this->templateLoader = $templateLoader;
    }

    protected function configure()
    {
        $this
            ->setName('sync:labels')
            ->setDescription('Syncs GitHub labels to all supported modules');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        // Validate GitHub token
        if (!getenv('GITHUB_ACCESS_TOKEN')) {
            $io->error('GITHUB_ACCESS_TOKEN is not available in the environment!');
            return;
        }

        // Loading data and confirming steps with user
        $io->section('Loading supported modules');
        $modules = $this->supportedModuleLoader->getModules();
        if (!$this->confirmModules($io, $modules)) {
            return;
        }

        $labelConfig = $this->loadLabelConfig();
        if (!$this->confirmLabels($io, $labelConfig)) {
            return;
        }

        // Proceed with synchronisation
        $result = ['success' => 0, 'error' => 0];
        $current = 0;
        $total = count($modules);
        foreach ($modules as $githubSlug) {
            $io->text(++$current . '/' . $total . ': Processing <comment>' . $githubSlug . '</comment>...');

            // Set the progress bar: total is the number of label operations to make
            $io->progressStart(array_sum(array_map('count', $labelConfig)));

            try {
                $this->syncLabelsToModule($githubSlug, $labelConfig, $io);
                $result['success']++;
            } catch (Exception $ex) {
                $io->error('Error updating ' . $githubSlug . ': ' . $ex->getMessage());
                $result['error']++;
            }

            $io->progressFinish();
        }
        $result['total'] = array_sum($result);

        $io->success('Finished! ' . $result['success'] . '/' . $result['total'] . ' succeeded.');
    }

    /**
     * Show a summary of the modules that are going to be processed and ask the user for confirmation before
     * proceeding.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param array $modules
     */
    protected function confirmModules(SymfonyStyle $io, array $modules)
    {
        // Show summary table
        $rows = [];
        foreach ($modules as $github) {
            $rows[] = [$github];
        }
        $io->table(['GitHub repo'], $rows);

        return $io->confirm('Continue with sync?', true);
    }

    /**
     * Show a summary of the labels that are going to be synchronised and ask for confirmation before proceeding
     *
     * @param SymfonyStyle $io
     * @param array $labelData
     */
    protected function confirmLabels(SymfonyStyle $io, array $labels)
    {
        $io->text('The following labels will be pushed to each repository:');
        // Default labels
        $rows = [];
        foreach ($labels['default_labels'] as $label => $hexCode) {
            $rows[] = [$label, $hexCode];
        }
        $io->table(['Label', 'Hex code'], $rows);

        // Renaming
        $io->text('The following labels will be renamed:');
        foreach ($labels['rename_labels'] as $old => $new) {
            $io->writeln(' * <comment>' . $old . '</comment> => <info>' . $new . '</info>');
        }
        $io->newLine();

        // Removing
        $io->text('The following labels will be deleted:');
        foreach ($labels['remove_labels'] as $label) {
            $io->writeln(' * <error>' . $label . '</error>');
        }
        $io->newLine();

        return $io->confirm('Continue with sync?', true);
    }

    /**
     * @return array
     * @throws UnexpectedValueException
     */
    protected function loadLabelConfig()
    {
        $data = $this->templateLoader->get('labels.json');
        $parsedData = json_decode($data, true);

        if (empty($parsedData)) {
            throw new UnexpectedValueException('labels.json data could not be loaded!');
        }

        return $parsedData;
    }

    /**
     * Given a GitHub repository slug and an array of labels, synchronise them to GitHub
     *
     * @param string $githubSlug
     * @param array $labelConfig
     * @param SymfonyStyle $io
     */
    protected function syncLabelsToModule($githubSlug, array $labelConfig, SymfonyStyle $io)
    {
        /** @var Labels $labelsApi */
        $labelsApi = $this->getClient()->api('issue')->labels();

        list ($organisation, $repository) = explode('/', $githubSlug);

        // Rename labels
        foreach ($labelConfig['rename_labels'] as $oldLabel => $newLabel) {
            $io->progressAdvance();

            // Assign white as a placeholder, it'll be updated further down
            try {
                $labelsApi->update($organisation, $repository, $oldLabel, $newLabel, 'FFFFFF');
            } catch (Exception $ex) {
                if (strpos($ex->getMessage(), 'Not Found') === false) {
                    // Only log messages that aren't "Not Found", which come when the labels don't exist
                    $io->error($ex->getMessage());
                }
            }
        }

        // (Create and) update colours on labels
        foreach ($labelConfig['default_labels'] as $label => $hexCode) {
            $io->progressAdvance();

            try {
                $exists = $labelsApi->show($organisation, $repository, $label);
            } catch (Exception $ex) {
                $exists = false;
            }

            if ($exists) {
                // Existing, update
                $labelsApi->update($organisation, $repository, $label, $label, $hexCode);
                continue;
            }
            // Create new label
            $labelsApi->create($organisation, $repository, [
                'name' => $label,
                'color' => $hexCode,
            ]);
        }

        // Delete labels
        foreach ($labelConfig['remove_labels'] as $label) {
            $io->progressAdvance();

            try {
                $labelsApi->deleteLabel($organisation, $repository, $label);
            } catch (Exception $ex) {
                if (strpos($ex->getMessage(), 'Not Found') === false) {
                    // Only log messages that aren't "Not Found", which come when the labels don't exist
                    $io->error($ex->getMessage());
                }
            }
        }
    }

    /**
     * @return Client
     */
    protected function getClient()
    {
        if (!$this->client) {
            $this->client = new Client();
            $this->client->authenticate(getenv('GITHUB_ACCESS_TOKEN'), null, Client::AUTH_HTTP_TOKEN);
        }
        return $this->client;
    }
}
