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

class InstallCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('install')
            ->setDefinition(array(
                new InputArgument('service', InputArgument::REQUIRED, 'A specific service name to install (or "all").'),
            ))
            ->setDescription('Install the application services.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $srcDir = $input->getOption('src-dir');
        $runDir = $input->getOption('run-dir');
        $service = $input->getArgument('service');

        if (!is_dir($srcDir . '/monit')) {
            throw new \InvalidArgumentException('Application does not have a "monit" directory.');
        }

        if (!is_dir($runDir . '/monit')) {
            mkdir($runDir . '/monit', 0700, true);
        }

        $services = new ServiceFactory($input->getOption('src-dir'), $input->getOption('run-dir'), $input->getOption('prefix'));

        $found = false;

        $dh = opendir($srcDir . '/monit');

        while (false !== $fn = readdir($dh)) {
            if ('.' == $fn[0]) {
                continue;
            } elseif (!is_dir($srcDir . '/monit/' . $fn)) {
                continue;
            } elseif (('all' !== $input->getArgument('service')) && ($service != $fn)) {
                continue;
            }

            $output->write('Installing ' . $fn . '...');

            if (is_dir($services[$fn]['env']['absolute_dir'])) {
                $output->writeln('<info>exists</info>');

                continue;
            }

            $this->installService($runDir, $services, $services[$fn]);

            $output->writeln('<comment>' . $services[$fn]['env']['absolute_id'] . '</comment>');
        }

        closedir($dh);
    }

    protected function installService($runDir, ServiceFactory $services, $service)
    {
        mkdir($service['env']['absolute_dir']);

        $this->installServiceDir($services, $service, $service['env']['src_dir'], $service['env']['absolute_dir']);

        file_put_contents(
            $service['env']['absolute_dir'] . '/profile.yml',
            Yaml::dump(
                array(
                    'config' => $service['config'],
                    'env' => $service['env'],
                )
            )
        );

        if (file_exists($service['env']['absolute_dir'] . '/monit.cfg')) {
            rename($service['env']['absolute_dir'] . '/monit.cfg', $runDir . '/' . $service['env']['absolute_id'] . '.monit');
        }
    }

    protected function installServiceDir(ServiceFactory $services, $service, $src, $env)
    {
        $dh = opendir($src);

        while (false !== $fn = readdir($dh)) {
            if ('.' == $fn || '..' == $fn) {
                continue;
            }

            $stat = stat($src . '/' . $fn);

            if (is_dir($src . '/' . $fn)) {
                mkdir($env . '/' . $fn);
                chmod($env . '/' . $fn, $stat['mode']);

                $this->installServiceDir($services, $service, $src . '/' . $fn, $env . '/' . $fn);
            } else {
                if ('.twig' == substr($fn, -5)) {
                    file_put_contents(
                        $env . '/' . substr($fn, 0, -5),
                        $services->twig->render(
                            file_get_contents($src . '/' . $fn),
                            $service
                        )
                    );
                    chmod($env . '/' . substr($fn, 0, -5), $stat['mode']);
                } else {
                    copy($src . '/' . $fn, $env . '/' . $fn);
                    chmod($env . '/' . $fn, $stat['mode']);
                }
            }
        }

        closedir($dh);
    }
}
