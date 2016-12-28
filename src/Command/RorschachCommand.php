<?php

namespace Rorschach\Command;

use Dotenv\Dotenv;
use Rorschach\Parser;
use Rorschach\Request;
use Rorschach\Assert;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;

class RorschachCommand extends Command
{
    /**
     * Command configure
     */
    protected function configure()
    {
        $this
            ->setName('inspect')
            ->setDescription('inspect api')
            ->addOption(
                'file',
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'test file path.'
            )
            ->addOption(
                'bind',
                'b',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'binding parameter.'
            )
            ->addOption(
                'env-file',
                null,
                InputOption::VALUE_OPTIONAL,
                'file of environment variables.'
            )
            ->addOption(
                'dir',
                'd',
                InputOption::VALUE_OPTIONAL,
                'test files dir.'
            )
            ->addOption(
                'saikou',
                's',
                InputOption::VALUE_NONE,
                'display saikou messages.'
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_NONE,
                'display output.'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('output')) {
            $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
        }

        $this->loadDotEnv($input->getOption('env-file'));

        if ($input->getOption('dir')) {
            $targets = $this->fetchDirTargets($input->getOption('dir'));
        } else {
            $targets = $this->fetchTargets($input->getOption('file'));
        }

        $inputBinds = $this->fetchBinds($input->getOption('bind'));

        $fs = new Filesystem();

        $hasError = false;
        foreach ($targets as $target) {
            if (!$fs->exists($target)) {
                $output->writeln("<error>File not found:: {$target} has been skipped.</error>");
            }

            $yaml = file_get_contents($target);

            // {{ }} to (( ))
            $precompiled = Parser::precompile($yaml);

            $binds = $this->createEnvBinds($precompiled);
            $binds = array_merge($binds, $inputBinds);

            // bind option vars
            $compiled = Parser::compile($precompiled, $binds);
            $setting = Parser::parse($compiled);

            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
                $output->writeln('<info>pre-request</info>');
            }

            foreach ($setting['pre-request'] as $request) {
                $response = (new Request($setting, $request))->request();
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $line = "<comment>{$request['method']} {$request['url']}</comment>";
                    $output->writeln($line);
                    $output->writeln($response->getStatusCode());
                    $output->writeln((string)$response->getBody());
                }

                $binds = array_merge($binds, Request::getBindParams($response, $request['bind']));
            }

            // bind vars after pre-requests
            $compiled = Parser::compile($precompiled, $binds);
            $setting = Parser::parse($compiled);

            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $vars = Parser::searchVars($compiled);
                foreach ($vars as $var) {
                    $output->writeln('<error>unbound variable: '.$var.'</error>');
                }
            }

            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
                $output->writeln('<info>request</info>');
            }

            foreach ($setting['request'] as $request) {
                $line = "<comment>{$request['method']} {$request['url']}</comment>";
                $output->writeln($line);

                $response = (new Request($setting, $request))->request();
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $output->writeln($response->getStatusCode());
                    $output->writeln((string)$response->getBody());
                }

                foreach ($request['expect'] as $type => $expect) {
                    $result = false;
                    switch ($type) {
                        case 'code':
                            $result = (new Assert\StatusCode($response, $expect))->assert();
                            $output->writeln($this->buildMessage($type, $expect, $result));
                            break;
                        case 'has':
                            foreach ($expect as $col) {
                                $result = (new Assert\HasProperty($response, $col))->assert();
                                $output->writeln($this->buildMessage($type, $col, $result));
                            }
                            break;
                        case 'type':
                            $errResults = [];
                            foreach ($expect as $col => $val) {
                                $assertResult = (new Assert\Type($response, $col, $val))->assert();
                                $output->writeln($this->buildMessage($type, "$col:$val", count($assertResult) === 0));
                                if (!empty($assertResult)) {
                                    $errResults[] = $assertResult;
                                }
                            }
                            if (empty($errResults)) {
                                $result = true;
                            }
                            break;
                        case 'value':
                            foreach ($expect as $col => $val) {
                                $result = (new Assert\Value($response, $col, $val))->assert();
                                $output->writeln($this->buildMessage($type, $val, $result));
                            }
                            break;
                        case 'redirect':
                            $result = (new Assert\Redirect($response, $expect))->assert();
                            $output->writeln($this->buildMessage($type, $expect, $result));
                            break;
                        default:
                            throw new \Exception('Unknown expect type given.');
                    }

                    if (!$result) {
                        $hasError = true;
                    }
                }
            }
        }

        if ($input->getOption('saikou')) {
            if ($hasError) {
                $output->write("Don't care!! Try again!!😊 \n");
            } else {
                $output->write("Congrats!!🍻 \n");
            }
        } else {
            $output->write('finished');
        }
    }

    /**
     * load file of environment variables
     *
     * @param $filename
     */
    private function loadDotEnv($filename)
    {
        if ($filename) {
            $dotenv = new Dotenv(getcwd(), $filename);
            $dotenv->load();
        } else {
            $dotenv = new Dotenv(getcwd());
            $dotenv->load();
        }
    }

    /**
     * fetch target files.
     *
     * @param string $files
     * @return array
     */
    private function fetchTargets($files)
    {
        if ($files) {
            return $files;
        }

        $targets = [];
        $finder = new Finder();
        $finder->files()
            ->in(__DIR__ . '/../../../..')
            ->name('test*.yml');
        foreach ($finder as $file) {
            $targets[] = $file->getRealPath();
        }

        return $targets;
    }

    /**
     * fetch target dir in files.
     *
     * @param  string $dir
     * @return array
     */
    private function fetchDirTargets($dir)
    {
        $targetDir = '';
        // 相対パス
        if (substr($dir, 0, 1) == '.') {
            $targetDir = __DIR__ . '/../../../../../' . $dir;
            // 絶対パス
        } else {
            $targetDir = $dir;
        }

        $targets = [];
        $finder = new Finder();
        $finder->files()
            ->in($targetDir)
            ->name('test*.yml');
        foreach ($finder as $file) {
            $targets[] = $file->getRealPath();
        }

        return $targets;
    }

    /**
     * fetch option --bind params.
     * @param $binds
     * @return array
     */
    private function fetchBinds($binds)
    {
        $params = [];
        if (count($binds) > 0) {
            foreach ($binds as $bind) {
                $bind = json_decode($bind, true);
                $params = array_merge($params, $bind);
            }
        }

        return $params;
    }

    /**
     * build test result message.
     *
     * @param $type
     * @param $value
     * @param $result
     * @return string
     */
    private function buildMessage($type, $value, $result)
    {
        if ($result) {
            $tag = 'question';
            $info = 'PASSED.';
        } else {
            $tag = 'error';
            $info = 'FAILED.';
        }
        return "\t<{$tag}>[{$type}]\t{$info}\t{$value}</{$tag}>";
    }

    /**
     * create binds from environment variables
     *
     * @param $raw
     * @return array
     */
    private function createEnvBinds($raw)
    {
        $result = [];
        $vars = Parser::searchVars($raw);
        foreach ($vars as $var) {
            $bind = getenv($var);
            if ($bind) {
                $result[$var] = $bind;
            }
        }
        return $result;
    }
}
