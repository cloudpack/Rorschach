<?php

namespace Rorschach\Command;

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
                'saikou',
                's',
                InputOption::VALUE_NONE,
                'display saikou messages.'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $targets = $this->fetchTargets($input->getOption('file'));
        $binds = $this->fetchBinds($input->getOption('bind'));

        $fs = new Filesystem();

        $hasError = false;
        foreach ($targets as $target) {
            if (!$fs->exists($target)) {
                $output->writeln("<error>File not found:: {$target} has been skipped.</error>");
            }

            $yaml = file_get_contents($target);

            // {{ }} to (( ))
            $precompiled = Parser::precompile($yaml);

            // bind option vars
            $compiled = Parser::compile($precompiled, $binds);
            $setting = Parser::parse($compiled);

            foreach ($setting['pre-request'] as $request) {
                $response = (new Request($setting, $request))->request();
                $binds = array_merge($binds, Request::getBindParams($response, $request['bind']));
            }

            // bind vars after pre-requests
            $compiled = Parser::compile($precompiled, $binds);
            $setting = Parser::parse($compiled);

            $hasError = false;
            foreach ($setting['request'] as $request) {
                $line = "<comment>{$request['method']} {$request['url']}</comment>";
                $output->writeln($line);

                $response = (new Request($setting, $request))->request();

                foreach ($request['expect'] as $type => $expect) {
                    switch ($type) {
                        case 'code':
                            $result = (new Assert\StatusCode($response, $expect))->assert();
                            $output->writeln($this->buildMessage($type, $expect, $result));
                            if (! $result) {
                                $hasError = true;
                            }
                            break;
                        case 'has':
                            foreach ($expect as $col) {
                                $result = (new Assert\HasProperty($response, $col))->assert();
                                $output->writeln($this->buildMessage($type, $col, $result));
                                if (! $result) {
                                    $hasError = true;
                                }
                            }
                            break;
                        case 'type':
                            foreach ($expect as $col => $val) {
                                $result = (new Assert\Type($response, $col, $val))->assert();
                                $output->writeln($this->buildMessage($type, $val, count($result) === 0));
                                if (count($result) > 0) {
                                    $hasError = true;
                                }
                            }
                            break;
                        case 'value':
                            foreach ($expect as $col => $val) {
                                $result = (new Assert\Value($response, $col, $val))->assert();
                                $output->writeln($this->buildMessage($type, $val, $result));
                                if (! $result) {
                                    $hasError = true;
                                }
                            }
                            break;
                        case 'redirect':
                            $result = (new Assert\Redirect($response, $expect))->assert();
                            $output->writeln($this->buildMessage($type, $expect, $result));
                            if (! $result) {
                                $hasError = true;
                            }
                            break;
                        default:
                            throw new \Exception('Unknown expect type given.');
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
}