<?php

namespace DPB\MonitUtil;

use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class ServiceFactory implements \ArrayAccess
{
    protected $srcDir;
    protected $runDir;
    protected $prefix;
    public $twig;
    protected $services;

    public function __construct($srcDir, $runDir, $prefix = '')
    {
        $this->srcDir = $srcDir;
        $this->runDir = $runDir;
        $this->prefix = $prefix;
        $this->twig = new \Twig_Environment(
            new \Twig_Loader_String(),
            array(
                'autoescape' => false,
                'strict_variables' => true,
            )
        );
    }

    public function offsetGet($offset)
    {
        if (!isset($this->services[$offset])) {
            $config = file_exists($this->srcDir . '/monit/config.yml') ? Yaml::parse(file_get_contents($this->srcDir . '/monit/config.yml')) : array();

            if (!isset($config['runtime'])) {
                $config['runtime'] = array();
            }

            if (!isset($config['runtime']['user'])) {
                $config['runtime']['user'] = exec('whoami');
            }

            if (!isset($config['runtime']['group'])) {
                $config['runtime']['group'] = exec('whoami');
            }

            $env = array(
                'src_dir' => $this->srcDir . '/monit/' . $offset,
                'relative_id' => null,
                'relative_dir' => null,
                'absolute_id' => null,
                'absolute_dir' => null,
                'version' => array(
                    'commit' => null,
                    'date' => null,
                    'dev' => false,
                ),
            );
    
            $p = new Process('git log -n1 -- .', $env['src_dir']);
            $p->run();
    
            preg_match('/^commit\s([0-9a-f]+)/m', $p->getOutput(), $envVersionCommit);
            preg_match('/^Date:\s+(.*)$/m', $p->getOutput(), $envVersionDate);
    
            if (!isset($envVersionCommit[1])) {
                $env['version']['dev'] = true;
    
                $p = new Process('git log -n1', $env['src_dir']);
                $p->run();
    
                preg_match('/^commit\s+([0-9a-f]+)$/m', $p->getOutput(), $envVersionCommit);
                preg_match('/^Date:\s+(.*)$/m', $p->getOutput(), $envVersionDate);
            } else {
                $p = new Process('git status -s -- .', $env['src_dir']);
                $p->run();
    
                if ($p->getOutput()) {
                    $env['version']['dev'] = true;
                }
            }
    
            $env['version']['commit'] = isset($envVersionCommit[1]) ? $envVersionCommit[1] : 'dev';
            $env['version']['date'] = isset($envVersionDate[1]) ? new \DateTime($envVersionDate[1]) : new \DateTime();
            $env['version']['date'] = $env['version']['date']->format('Y-m-d H:i:s');

            $env['relative_id'] = $this->prefix . basename($env['src_dir']);
            $env['relative_dir'] = $this->runDir . '/' . $env['relative_id'];

            $env['absolute_id'] = $env['relative_id'] . '-' . substr($env['version']['commit'], 0, 9) . ($env['version']['dev'] ? ('-' . date('YmdHis')) : '');
            $env['absolute_dir'] = $this->runDir . '/' . $env['absolute_id'];
    
            $config = array_merge(
                $config,
                Yaml::parse(
                    $this->twig->render(
                        @file_get_contents($env['src_dir'] . '/config.yml'),
                        array(
                            'config' => $config,
                            'env' => $env,
                            'depends' => $this,
                        )
                    )
                ) ?: array()
            );

            $this->services[$offset] = array(
                'config' => $config,
                'env' => $env,
            );
        }

        return array_merge(
            $this->services[$offset],
            array(
                'app' => array(
                    'absolute_dir' => $this->srcDir,
                ),
                'depends' => $this,
            )
        );
    }

    public function offsetExists($offset)
    {
        return is_dir($this->srcDir . '/monit/' . $offset);
    }

    public function offsetSet($offset, $value)
    {
        throw new \BadMethodCallException();
    }

    public function offsetUnset($offset)
    {
        throw new \BadMethodCallException();
    }
}
