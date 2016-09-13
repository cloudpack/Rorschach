<?php

namespace Rorschach\Command;

use Rorschach\Resource\Entity;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;

class RorschachCommand extends Command
{
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
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $targets = $this->fetchTargets($input->getOption('file'));
        $binds = $input->getOption('bind');
        $fs = new Filesystem();

        foreach ($targets as $target) {
            if (!$fs->exists($target)) {
                $output->writeln("<error>File not found:: {$target} has been skipped.</error>");
            }

            $Entity = new Entity(file_get_contents($target), $binds);
            $resources = $Entity
                ->compile()
                ->initialize()
                ->preRequest()
                ->compile()
                ->getResources();
            foreach ($resources as $resource) {
                $line = "<comment>{$resource['method']} {$resource['url']}</comment>";
                $output->writeln($line);

                $response = $Entity->request($resource);
                foreach ($resource['expect'] as $type => $value) {
                    switch ($type) {
                        case 'type':
                            foreach ($value as $col => $expect) {
                                $result = $Entity->assert($response, $type, [
                                    'col' => $col,
                                    'expect' => $expect,
                                ]);
                                $info = $this->buildMessage($type, $expect, $result);
                                $output->writeln($info);
                            }
                            break;
                        case 'has':
                            foreach ($value as $expect) {
                                $result = $Entity->assert($response, $type, $expect);
                                $info = $this->buildMessage($type, $expect, $result);
                                $output->writeln($info);
                            }
                            break;
                        default:
                            $result = $Entity->assert($response, $type, $value);
                            $info = $this->buildMessage($type, $value, $result);
                            $output->writeln($info);
                            break;
                    }
                }
            }
        }
        $output->write('finished');

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