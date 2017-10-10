<?php

/*
 +--------------------------------------------------------------------------+
 | Zephir                                                                   |
 | Copyright (c) 2013-present Zephir (https://zephir-lang.com/)             |
 |                                                                          |
 | This source file is subject the MIT license, that is bundled with this   |
 | package in the file LICENSE, and is available through the world-wide-web |
 | at the following url: http://zephir-lang.com/license.html                |
 +--------------------------------------------------------------------------+
 */

namespace Zephir;

use Zephir\Commands\CommandAbstract;

/**
 * Zephir\Bootstrap
 *
 * Main compiler bootstrap
 *
 * @package Zephir
 */
class Bootstrap
{
    /**
     * @var CommandAbstract[]
     */
    protected static $commands = array();

    /**
     * Shows an exception opening the file and highlighting the wrong part
     *
     * @param \Exception $e
     * @param Config $config
     */
    protected static function showException(\Exception $e, Config $config = null)
    {
        echo get_class($e), ': ', $e->getMessage(), PHP_EOL;
        if (method_exists($e, 'getExtra')) {
            $extra = $e->getExtra();
            if (is_array($extra)) {
                if (isset($extra['file'])) {
                    echo PHP_EOL;
                    $lines = file($extra['file']);
                    if (isset($lines[$extra['line'] - 1])) {
                        $line = $lines[$extra['line'] - 1];
                        echo "\t", str_replace("\t", " ", $line);
                        if (($extra['char'] - 1) > 0) {
                            echo "\t", str_repeat("-", $extra['char'] - 1), "^", PHP_EOL;
                        }
                    }
                }
            }
        }
        echo PHP_EOL;

        if ($config && $config->get('verbose')) {
            echo 'at ', str_replace(ZEPHIRPATH, '', $e->getFile()), '(', $e->getLine(), ')', PHP_EOL;
            echo str_replace(ZEPHIRPATH, '', $e->getTraceAsString()), PHP_EOL;
        }

        exit(1);
    }


    /**
     * Returns the commands registered in the compiler
     *
     * @return CommandAbstract[]
     */
    public static function getCommands()
    {
        return self::$commands;
    }

    /**
     * Boots the compiler executing the specified action
     *
     * @param string $baseDir Base Zephir direcrory
     */
    public static function boot($baseDir = null)
    {
        $baseDir = realpath($baseDir?: dirname(__DIR__));

        try {
            /**
             * Global config
             */
            $config = Config::fromServer();

            /**
             * Global Logger
             */
            $logger = new Logger($config);

            if (isset($_SERVER['argv'][1])) {
                $action = $_SERVER['argv'][1];
            } else {
                $action = 'help';
            }

            /**
             * Register built-in commands
             * @var $item \DirectoryIterator
             */
            foreach (new \DirectoryIterator($baseDir . '/Library/Commands') as $item) {
                if (!$item->isDir()) {
                    $className = 'Zephir\\Commands\\' . str_replace('.php', '', $item->getBaseName());
                    $class = new \ReflectionClass($className);

                    if (!$class->isAbstract() && !$class->isInterface()) {
                        /**
                         * @var $command CommandAbstract
                         */
                        $command = new $className();

                        if (!$command instanceof CommandAbstract) {
                            throw new \Exception('Class ' . $class->name . ' must be instance of CommandAbstract');
                        }

                        self::$commands[$command->getCommand()] = $command;
                    }
                }
            }

            if (!isset(self::$commands[$action])) {
                $message = 'Unrecognized action "' . $action . '"';
                $metaphone = metaphone($action);
                foreach (self::$commands as $key => $command) {
                    if (metaphone($key) == $metaphone) {
                        $message .= PHP_EOL . PHP_EOL . 'Did you mean "' . $key . '"?';
                    }
                }

                throw new \Exception($message);
            }

            /**
             * Execute the command
             */
            self::$commands[$action]->execute($config, $logger);
        } catch (\Exception $e) {
            self::showException($e, isset($config) ? $config : null);
        }
    }
}
