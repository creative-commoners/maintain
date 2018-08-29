<?php

namespace SilverStripe\Maintain\Command;

use function array_combine;
use Exception;
use Github\Api\Issue\Labels;
use Github\Client;
use SilverStripe\Maintain\Utility\SupportedModuleLoader;
use SilverStripe\Maintain\Utility\TemplateLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
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
     * @param SupportedModuleLoader $moduleLoader
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
        foreach ($modules as $githubSlug => $composerName) {
            $io->text('Processing <comment>' . $composerName . '</comment>...');
            try {
                $this->syncLabelsToModule($githubSlug, $labelConfig);
                $result['success']++;
            } catch (Exception $ex) {
                $io->error('Error updating ' . $githubSlug . ': ' . $ex->getMessage());
                $result['error']++;
            }
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
        foreach ($modules as $github => $composer) {
            $rows[] = [$github, $composer];
        }
        $io->table(['GitHub', 'Module name'], $rows);

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
     */
    protected function syncLabelsToModule($githubSlug, array $labelConfig)
    {
        $client = new Client();
        $client->authenticate(getenv('GITHUB_ACCESS_TOKEN'), null, Client::AUTH_HTTP_TOKEN);
        /** @var Labels $labelsApi */
        $labelsApi = $client->api('issue')->labels();

        list ($organisation, $repository) = explode('/', $githubSlug);

        // Rename labels
        foreach ($labelConfig['rename_labels'] as $oldLabel => $newLabel) {
            // Assign white as a placeholder, it'll be updated further down
            try {
                $labelsApi->update($organisation, $repository, $oldLabel, $newLabel, 'FFFFFF');
            } catch (Exception $ex) {
                if (strpos($ex, 'Not Found') !== false) {
                    // noop, if the label doesn't exist we should ignore it
                    continue;
                }
                throw $ex;
            }
        }

        // (Create and) update colours on labels
        foreach ($labelConfig['default_labels'] as $label => $hexCode) {
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
            try {
                $labelsApi->deleteLabel($organisation, $repository, $label);
            } catch (Exception $ex) {
                if (strpos($ex, 'Not Found') !== false) {
                    // noop, if the label doesn't exist we should ignore it
                    continue;
                }
                throw $ex;
            }
        }
    }
}
