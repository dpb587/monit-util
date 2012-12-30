<?php

namespace DPB\MonitUtil\Command;

use DPB\MonitUtil\ServiceFactory;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class DescribeCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('describe')
            ->setDefinition(array(
                new InputArgument('name', InputArgument::OPTIONAL, 'A specific service name', null),
            ))
            ->setDescription('Describe the application or application service')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $srcDir = $input->getOption('src-dir');

        if (!is_dir($srcDir . '/monit')) {
            throw new \InvalidArgumentException('Application directory does not contain a "monit" directory.');
        }

        $services = new ServiceFactory($input->getOption('src-dir'), $input->getOption('run-dir'));

        $dh = opendir($srcDir . '/monit');

        while (false !== $fn = readdir($dh)) {
            if ('.' == $fn[0]) {
                continue;
            } elseif (!is_dir($srcDir . '/monit/' . $fn)) {
                continue;
            }

            $service = $services[$fn];

            $output->write('<info>' . $fn . '</info>');
            $output->write('/' . ($service['env']['version']['dev'] ? '<error>' : '<comment>') . substr($service['env']['version']['commit'], 0, 9) . ($service['env']['version']['dev'] ? '</error>' : '</comment>'));
            $output->writeln(str_repeat(' ', 91 - strlen($fn)) . $service['env']['version']['date']->format('Y-m-d H:i:s'));
            $output->writeln('  config:');
            $output->writeln('    ' . str_replace("\n", "\n    ", Yaml::dump($service['config'])));
        }

        closedir($dh);
    }

    protected function doDescribe(InputInterface $input, OutputInterface $output, $path)
    {

    }
}
