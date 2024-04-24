<?php

namespace CubaDevOps\CustomHtml\Command;

use Doctrine\Migrations\Tools\Console\Command\DoctrineCommand;
use PhpToken;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tools;

if (!defined('_PS_VERSION_')) {
    exit;
}

class MigrateCommand extends DoctrineCommand
{
    protected static $defaultName = 'customhtml:migrate';
    /**
     * @var string
     */
    private $migration_path;

    protected function configure(): void
    {
        $this->migration_path = dirname(__DIR__).'/migrations/';
        // The name of the command (the part after "./bin/console")
        $this->setName('customhtml:migrate')
            ->setDescription('Setup the migrations')
            ->addArgument('operation', InputArgument::OPTIONAL, 'up | down', 'up');
        parent::configure();
    }

    /**
     * @throws ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        switch ($input->getArgument('operation')) {
            case 'down':
                // rollback
                $response = $this->executeMigrations('down');
                break;
            default:
                $response = $this->executeMigrations();
        }

        $output->write($response);
        $output->write('Migrations was executed!');
    }

    private function executeMigrations($action = 'up')
    {
        $files = Tools::scandir($this->migration_path);
        $resp = [];
        foreach ($files as $file) {
            $class = $this->getAbsoluteClassNameFromFile($this->migration_path.$file);
            if (class_exists($class)) {
                $resp[] = call_user_func([$class, $action]);
            } else {
                $resp[] = "class $class wasn't load";
            }
        }

        return implode(' | ', $resp);
    }

    /**
     * @param string $file_path
     */
    private function getAbsoluteClassNameFromFile($file_path): string
    {
        $tokens = PhpToken::tokenize(file_get_contents($file_path));
        $namespace = [];
        foreach ($tokens as $index => $token) {
            if ($token->is(T_NAMESPACE) && $tokens[$index + 2]->is(T_STRING)) {
                for ($i = $index + 2; !$tokens[$i]->is(T_WHITESPACE); ++$i) {
                    if (';' === $tokens[$i]->text) {
                        continue;
                    }
                    $namespace[] = $tokens[$i]->text;
                }
                unset($tokens);

                return implode('', $namespace).'\\'.pathinfo($file_path, PATHINFO_FILENAME);
            }
        }

        return '\\'.pathinfo($file_path, PATHINFO_FILENAME);
    }
}
