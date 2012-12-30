<?php

namespace DPB\MonitUtil\Command;

use DPB\MonitUtil\ServiceFactory;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class InitCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('init')
            ->setDescription('Create a sample monit control file.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $runDir = $input->getOption('run-dir');

        if (!is_dir($runDir)) {
            $output->write('Creating runtime directory...');

            mkdir($runDir, 0700, true);

            $output->writeln('<info>success</info>');
        }

        if (!is_dir($runDir . '/monit')) {
            $output->write('Creating monit directory...');

            mkdir($runDir . '/monit', 0700, true);

            $output->writeln('<info>success</info>');
        }

        if (!file_exists($runDir . '/monit/control')) {
            $output->write('Creating control file...');

            $control = array();

            $control[] = 'set daemon 30';
            $control[] = 'set logfile ' . $runDir . '/monit/log';
            $control[] = 'set idfile ' . $runDir . '/monit/id';
            $control[] = 'set pidfile ' . $runDir . '/monit/pid';
            $control[] = 'set statefile ' . $runDir . '/monit/state';
            $control[] = 'set httpd port 4000 and use the address 127.0.0.1';
            $control[] = '    allow localhost';
            $control[] = '';
            $control[] = 'include ' . $runDir . '/*.monit';

            file_put_contents($runDir . '/monit/control', implode("\n", $control) . "\n");
            chmod($runDir . '/monit/control', 0600);

            $output->writeln('<info>success</info>');
        }
    }
}
