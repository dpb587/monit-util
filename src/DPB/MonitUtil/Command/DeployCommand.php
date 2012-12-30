<?php

namespace DPB\MonitUtil\Command;

use DPB\MonitUtil\ServiceFactory;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Process\Process;

class DeployCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('deploy')
            ->setDefinition(array(
                new InputArgument('service', InputArgument::REQUIRED, 'A specific service name to deploy.'),
            ))
            ->setDescription('Deploy an application service.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $runDir = $input->getOption('run-dir');

        $output->write('Loading application service profile...');

        if (!file_exists($runDir . '/' . $input->getArgument('service') . '/profile.yml')) {
            $output->writeln('<error>failure</error>');
            $output->writeln('  The application service profile does not exist.');

            return 1;
        }

        $service = Yaml::parse(file_get_contents($runDir . '/' . $input->getArgument('service') . '/profile.yml'));

        $output->writeln('<info>success</info>');


        $output->write('Checking current status...');

        $initialStatus = $this->doCheckStatus($input, $output);

        if (is_int($initialStatus)) {
            return $initialStatus;
        }

        $output->writeln('<info>success</info>');

        if ($initialStatus == 'Not monitored') {
            $output->write('Starting monitoring...');

            $p = new Process('monit -c control monitor ' . escapeshellarg($input->getArgument('service')), $runDir . '/monit');
            $p->run();

            if (!$p->isSuccessful()) {
                $output->writeln('<error>failure</error>');
                $output->writeln('  ' . str_replace("\n", "\n  ", $p->getErrorOutput()));
    
                return 1;
            }

            $output->writeln('<info>success</info>');

            $initialStatus = $this->doWaitForStatus($input, $output, array('Running', 'Does not exist'));
        }

        $output->write('Checking prior version...');

        if (is_file($service['env']['relative_dir'])) {
            $serviceOld = Yaml::parse($service['relative_dir'] . '/profile.yml');

            $output->writeln('<info>' . $service['absolute_id'] . '</info>');

            if ($serviceOld['absolute_id'] == $service['absolute_id']) {
                throw new \RuntimeException('Already running version!');
            }

            if (file_exists($service['absolute_dir'] . '/bin/migrate')) {
                // custom
            } else {
                $output->writeln('Stopping prior version...');

                $p = new Process('monit -c control stop ' . escapeshellarg($input->getArgument('service')), $runDir . '/monit');
                $p->run();

                if (!$p->isSuccessful()) {
                    $output->writeln('<error>failure</error>');
                    $output->writeln('  ' . str_replace("\n", "\n  ", $p->getErrorOutput()));
        
                    return 1;
                }

                $output->writeln('<info>success</info>');

                $this->doWaitForStatus($input, $output, array('Not monitored'));
            }
        } else {
            $output->writeln('<info>unavailable</info>');
        }

        if ($initialStatus != 'Running') {
            $output->write('Starting service...');

            $p = new Process('monit -c control start ' . escapeshellarg($input->getArgument('service')), $runDir . '/monit');
            $p->run();

            if (!$p->isSuccessful()) {
                $output->writeln('<error>failure</error>');
                $output->writeln('  ' . str_replace("\n", "\n  ", $p->getErrorOutput()));
    
                return 1;
            }

            $output->writeln('<info>success</info>');

            $this->doWaitForStatus($input, $output, array('Running'));
        }

        $output->write('Storing version...');

        $p = new Process('ln -s ' . escapeshellarg($service['env']['absolute_id']) . ' ' . escapeshellarg($service['env']['relative_id']), $runDir);
        $p->run();

        if (!$p->isSuccessful()) {
            $output->writeln('<error>failure</error>');
            $output->writeln('  ' . str_replace("\n", "\n  ", $p->getErrorOutput()));

            return 1;
        }

        $output->writeln('<info>success</info>');
    }

    protected function doCheckStatus(InputInterface $input, OutputInterface $output)
    {
        $p = new Process('monit -c control summary', $input->getOption('run-dir') . '/monit');
        $p->run();

        if (!$p->isSuccessful()) {
            $output->writeln('<error>failure</error>');
            $output->writeln('  ' . str_replace("\n", "\n  ", $p->getErrorOutput()));

            return 1;
        }

        if (!preg_match('/^Process \'' . preg_quote($input->getArgument('service'), '/') . '\'\s+(.*)$/m', $p->getOutput(), $match)) {
            $output->writeln('<error>failure</error>');
            $output->writeln('  The service does not appear to exist.');

            return 1;
        }

        return $match[1];
    }

    protected function doWaitForStatus(InputInterface $input, OutputInterface $output, array $expect)
    {
        $status = null;

        $output->write('Waiting for status update...');

        do {
            $newstatus = $this->doCheckStatus($input, $output);

            if ($newstatus != $status) {
                $status = $newstatus;
                $output->write("\n" . ' - <info>' . $newstatus . '</info>');

                if (in_array($status, $expect)) {
                    $output->writeln('');

                    return $status;
                }
            }

            sleep(5);

            $p = new Process('/bin/kill -s USR1 $(/bin/cat pid)', $input->getOption('run-dir') . '/monit');
            $p->run();
        } while (true);
    }
}
