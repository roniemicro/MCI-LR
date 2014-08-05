<?php

/*
 * This file is part of the MCI project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mci\Bundle\LocationBundle\Command;

use Mci\Bundle\LocationBundle\Importer\FromExcel;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class ImportCommand extends ContainerAwareCommand
{
    /** @var  FromExcel */
    protected $importer;

    protected function configure()
    {
        $this
            ->setName('mci:import:lr')
            ->setDefinition(array(
                new InputOption('path', '', InputOption::VALUE_REQUIRED, 'Source path of excel file', 'https://sharedhealth.atlassian.net/wiki/download/attachments/524414/BBS_Geocode_list_upz.xlsx?version=1&modificationDate=1404127369912&api=v2'),
            ))
            ->setDescription('Import Location Registry data');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $question = $this->getQuestionHelper();

        if ($input->isInteractive()) {
            if (!$question->
                ask($input, $output, new ConfirmationQuestion('Do you confirm import [yes]?', TRUE))
            ) {
                $output->writeln('<error>Command aborted</error>');

                return 1;
            }
        }

        $importer = $this->getImporter($input);

        $importer
            ->setProgressHelper($this->getProgressHelper())
            ->setOutputInterface($output);

        $importer->importAll();

        $output->writeln('<info>Locations has been successfully imported.</info>');
    }


    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Welcome to the MCI Importer tool</info>');
        $output->writeln('');

        $question = $this->getQuestionHelper();
        $data = array();

        $options = $this->getDefinition()->getOptions();

        $data['path'] = $this->getInteractiveData($options['path'], $input, $output, $question);

        // summary
        $output->writeln(array(
            '',
            $this->getHelper('formatter')->formatBlock('Summary before importing', 'bg=blue;fg=white', TRUE),
            '',
            sprintf("We are going to use the following info to import:"),
            '',
        ));

        foreach ($data as $item => $value) {
            $output->writeln(sprintf('%s : "<info>%s</info>"', $item, $value));
        }
    }

    protected function getInteractiveData(InputOption $inputOption, InputInterface $input, OutputInterface $output, QuestionHelper $question)
    {
        $value = NULL;

        $option = $inputOption->getName();

        try {
            $value = $input->getOption($option) ? $input->getOption($option) : NULL;
        } catch (\Exception $error) {
            $output->writeln($question->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
        }

        $description = trim($inputOption->getDescription());
        $defaultValue = $inputOption->getDefault();

        if (NULL === $value || $value === $inputOption->getDefault()) {
            $value = $question
                ->ask($input,
                    $output,
                    new Question("$description [use default]:", $defaultValue)
                );

            $input->setOption($option, $value);
        }

        return $value;
    }


    /**
     * @return QuestionHelper
     */
    protected function getQuestionHelper()
    {
        return $this->getHelperSet()->get('question');;
    }

    /**
     * @return \Symfony\Component\Console\Helper\HelperInterface
     */
    protected function getProgressHelper()
    {
        return $this->getHelperSet()->get('progress');
    }


    /**
     * @param InputInterface $input
     *
     * @throws \Exception
     * @return FromExcel
     */
    protected function getImporter(InputInterface $input = NULL)
    {
        if (!$this->importer && $input) {
            $this->importer = $this->getContainer()->get('service.importer.from_excel');
            $this->importer->setConfiguration($this->getImporterConfig($input));
        }

        if (!$this->importer) {
            throw new \Exception('Need to set configuration parameter first');
        }

        return $this->importer;
    }

    /**
     * @param InputInterface $input
     *
     * @return array
     */
    protected function getImporterConfig(InputInterface $input)
    {
        return array(
            'path'          => $input->getOption('path'),
            'host'          => $this->getContainer()->getParameter('cassandra_host'),
            'keyspace'      => $this->getContainer()->getParameter('cassandra_keyspace'),
            'column_family' => $this->getContainer()->getParameter('cassandra_columnfamily'),
            'columns'       => $this->getContainer()->getParameter('cassandra_columns')
        );
    }
}
