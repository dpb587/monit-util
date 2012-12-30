<?php

namespace DPB\MonitUtil\Console;

use DPB\MonitUtil\Command;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('dpb/monit-util', '0.3.0');

        $def = $this->getDefinition();
        $def->addOption(new InputOption('src-dir', null, InputOption::VALUE_REQUIRED, 'Source directory.', getcwd()));
        $def->addOption(new InputOption('run-dir', null, InputOption::VALUE_REQUIRED, 'Runtime directory.', '/home/' . exec('whoami') . '/monit'));
        $def->addOption(new InputOption('define', 'd', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Define global parameters.', null));
        $def->addOption(new InputOption('prefix', '', InputOption::VALUE_REQUIRED, 'A prefix for application services.', exec('whoami') . '-'));
    }

    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->registerCommands();

        return parent::doRun($input, $output);
    }

    protected function registerCommands()
    {
        $this->add(new Command\DescribeCommand());
        $this->add(new Command\InstallCommand());
        $this->add(new Command\InitCommand());
        $this->add(new Command\DeployCommand());
    }
}
