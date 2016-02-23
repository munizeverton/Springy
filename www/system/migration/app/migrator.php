<?php
/** \file
 *  Springy
 *
 *  \brief     Script da classe de acesso a banco de dados.
 *  \copyright Copyright (c) 2007-2016 Fernando Val
 *  \author    Fernando Val - fernando.val@gmail.com
 *  \warning   Este arquivo � parte integrante do framework e n�o pode ser omitido
 *  \version   0.3.6
 *  \ingroup   framework
 */
namespace Springy;

class Migrator extends DB
{
    const VERSION = '0.3.6';
    const MSG_INFORMATION = 0;
    const MSG_WARNING = 1;
    const MSG_ERROR = 2;

    const DIR_UP = 1;
    const DIR_DOWN = -1;

    const CS_ERROR = "\033[31m";
    const CS_INFORMATION = "\033[1;37m";
    const CS_RESET = "\033[0m";
    const CS_SUCCESS = "\033[1;32m";
    const CS_WARNING = "\033[1;33m";

    private $mgPath = '';
    private $revPath = '';
    private $revFile = '';
    private $command = null;
    private $target = null;
    private $parameter = null;
    private $error = false;
    private $currentRevision = []; // Legacy
    private $controlTable = '';
    private $mustByApplied = [];

    /**
     *  \brief Initiate the class.
     */
    public function __construct()
    {
        $this->mgPath = $GLOBALS['SYSTEM']['MIGRATION_PATH'];
        $this->revPath = $this->mgPath.DS.'revisions'.DS;
        $this->revFile = $this->revPath.'current'; // Legacy contral file

        $this->disableReportError();

        parent::__construct();
    }

    /**
     *  \brief Run the migrator.
     */
    public function run()
    {
        ob_end_flush();
        $this->output([
            'FVAL PHP Framework for Web Applications'                         => self::MSG_INFORMATION,
            'Database Migration Tool v'.self::VERSION                         => self::MSG_INFORMATION,
            '---------------------------------------'                         => self::MSG_INFORMATION,
            'Application: '.Kernel::systemName().' v'.Kernel::systemVersion() => self::MSG_INFORMATION,
            ''                                                                => self::MSG_INFORMATION,
        ]);

        // Checks the configuration
        $this->controlTable = Configuration::get('db', '_migration.table_name') ?: '_database_version_control';

        // Checks existence of the control table
        $this->checkControlTable();
        $this->checkCurrentRevision();

        $this->getArguments();

        if ($this->command == 'status') {
            $this->showCurrentStatus();
        } elseif ($this->command == 'migrate') {
            $this->migrate();
        } elseif ($this->command == 'rollback') {
            $this->revert();
        } elseif ($this->command == 'help') {
            $this->showHelp();
        } else {
            $this->output('Invalid command!', self::MSG_WARNING);
        }

        $this->output('');
        $this->output('Done!');
        exit(0);
    }

    /**
     *  \brief Checks the existence of the control table.
     */
    private function checkControlTable()
    {
        if ($this->execute('SELECT migration_at FROM '.$this->controlTable.' WHERE revision_number = 0')) {
            return true;
        }

        $command = 'CREATE TABLE '.$this->controlTable.'('.
            '  revision_number INT NOT NULL,'.
            '  script_file VARCHAR(255) NOT NULL,'.
            '  migration_at DATETIME NOT NULL,'.
            '  result_message VARCHAR(255),'.
            '  PRIMARY KEY (revision_number, script_file)'.
            ')';

        if ($this->execute($command)) {
            return true;
        }
        $this->systemAbort('Can not create control table ('.$this->statmentErrorCode().' : '.$this->statmentErrorInfo()[2].')');
    }

    /**
     *  \brief Checks the current release of the database.
     */
    private function checkCurrentRevision()
    {
        $command = 'SELECT migration_at FROM '.$this->controlTable.' WHERE revision_number = ? AND script_file = ?';
        $this->loadCurrentRevision(); // Legacy
        $revisions = $this->getRevisions();
        $this->output('Loading revisions ', self::MSG_INFORMATION, false);
        foreach ($revisions as $revision) {
            $files = $this->getRevisionFiles($revision, self::DIR_UP);
            $oldControl = null;
            foreach ($files as $file) {
                $this->output('.', self::MSG_INFORMATION, false);
                $this->execute($command, [$revision, $file]);
                if ($res = $this->fetchNext()) {
                    continue;
                }

                // Revision not applied yet? Check legacy.
                if (is_null($oldControl)) {
                    $oldControl = false;
                    foreach ($this->currentRevision as $start => $end) {
                        if ($revision >= $start && $revision <= $end) {
                            $oldControl = true;
                            break;
                        }
                    }
                }

                // Applied by legacy?
                if ($oldControl) {
                    $this->setMigrationApplied($revision, $file, 'Legacy old control method');
                    continue;
                }

                // Not applied yet.
                if (!isset($this->mustByApplied[$revision])) {
                    $this->mustByApplied[$revision] = [];
                }
                $this->mustByApplied[$revision][] = $file;
            }
        }
        $this->output(self::CS_SUCCESS.' [OK]', self::MSG_INFORMATION, true);

        if (file_exists($this->revFile)) {
            unlink($this->revFile);
        }
    }

    /**
     *  \brief Set migration file as applied.
     */
    private function setMigrationApplied($revision, $file, $result)
    {
        $command = 'INSERT INTO '.$this->controlTable.' (revision_number, script_file, migration_at, result_message) VALUES (?, ?, ?, ?)';
        if (!$this->execute($command, [$revision, $file, date('Y-m-d H:n:s'), $result])) {
            $this->systemAbort('Control error ('.$this->statmentErrorCode().' : '.$this->statmentErrorInfo()[2].')');
        }
    }

    /**
     *  \brief Get arguments passed to the program.
     */
    private function getArguments()
    {
        $args = getopt('hsmr', ['help', 'revision:']);

        if ($this->validateArgument($args, ['m'], true)) {
            $this->command = 'migrate';
        }
        if ($this->validateArgument($args, ['r'], true)) {
            $this->command = 'rollback';
        }
        if ($this->validateArgument($args, ['s'], true)) {
            $this->command = 'status';
        }
        if ($this->validateArgument($args, ['h', 'help'], true)) {
            $this->command = 'help';
        }
        if ($this->validateArgument($args, ['revision'])) {
            $this->target = $this->parameter;
            $this->parameter = null;
        }
    }

    /**
     *  \brief Verify if two or more incompatible arguments was passed.
     */
    private function validateArgument($arguments, $list, $isExclusive = false)
    {
        $count = 0;
        foreach ($list as $arg) {
            if (isset($arguments[$arg])) {
                if ($isExclusive && isset($this->command)) {
                    $this->systemAbort([
                        'Syntax error!'                                            => self::MSG_ERROR,
                        'You cannot execute two or concurrent commands at a time.' => self::MSG_INFORMATION,
                    ]);
                }
                $count++;

                if ($arguments[$arg] !== false) {
                    $this->parameter = $arguments[$arg];
                }
            }

            if ($count > 1) {
                $this->systemAbort([
                    'Syntax error!'                                                 => self::MSG_ERROR,
                    'Please, use only short or long form of a parameter, not both.' => self::MSG_INFORMATION,
                ]);
            }
        }

        return $count > 0;
    }

    /**
     *  \brief Show help instructions.
     */
    private function showHelp()
    {
        $this->output('');
        $this->output('Usage: migration.php COMMAND [OPTIONS]');
        $this->output('');
        $this->output('List of commands:');
        $this->output('  -h, --help     show this help instructions. D\'oh!');
        $this->output('  -m             start the migtration process.');
        $this->output('  -r             start the rollback process.');
        $this->output('  -s             show the current status of revisions.');
        $this->output('');
        $this->output('List of argument options:');
        $this->output('  --revision N   set the target revision number to \'N\'.');
        $this->output('                 If this option is omitted, all revisions will be applied');
        $this->output('                 on migration process or all revisions applied will be');
        $this->output('                 reverted on rollback process.');
    }

    /**
     *  \brief Show current status.
     */
    private function showCurrentStatus()
    {
        $this->output('');
        if (empty($this->mustByApplied)) {
            $this->output(self::CS_INFORMATION.'The database is up to date. No revisions to be applied.');

            return;
        }

        $this->output(self::CS_WARNING.count($this->mustByApplied).self::CS_RESET.' revision'.(count($this->mustByApplied) > 1 ? 's' : '').' to be applied');
    }

    /**
     *  \brief Execute migrations.
     */
    private function migrate()
    {
        // Get target revision
        if (is_null($this->target)) {
            $target = -1;
        } elseif (!is_numeric($this->target)) {
            $this->systemAbort([
                'Syntax error!'            => self::MSG_ERROR,
                'Invalid revision number.' => self::MSG_INFORMATION,
            ]);
        } else {
            $target = (int) $this->target;
        }

        $this->output('');
        $this->output(self::CS_INFORMATION.'Starting migration proccess.');

        if (empty($this->mustByApplied)) {
            $this->showCurrentStatus();

            return;
        }

        foreach ($this->mustByApplied as $revision => $files) {
            // Hit target?
            if ($revision > $target && $target >= 0) {
                return;
            }

            $this->output('');
            $this->output('Applying revision #'.self::CS_INFORMATION.$revision);

            // No files? Oops!
            if (empty($files)) {
                $this->output('Nothing to do at revision #'.self::INFORMATION.$revision, self::MSG_WARNING);
                continue;
            }

            $error = false;
            foreach ($files as $file) {
                $this->output('  Running script '.self::CS_INFORMATION.$file, self::MSG_INFORMATION, false);

                if (!$this->runFile($this->getScriptsPath($revision, self::DIR_UP).DS.$file)) {
                    $this->output(self::CS_ERROR.' [ERR]');
                    if (is_array($this->error)) {
                        $this->output($this->error[2], self::MSG_ERROR);
                    } else {
                        $this->output($this->error, self::MSG_ERROR);
                    }
                    $error = true;
                    break;
                }

                $this->output(self::CS_SUCCESS.' [OK]');
                $this->setMigrationApplied($revision, $file, $this->affectedRows().' affected rows');
            }

            if ($error) {
                $this->systemAbort('Revision has errors!');
            }
            $this->output('  Revision #'.self::CS_INFORMATION.$revision.self::CS_RESET.' sucessfully applied.');
        }
    }

    /**
     *  \brief Execute migrations.
     */
    private function revert()
    {
        $command = 'SELECT DISTINCT revision_number FROM '.$this->controlTable.' ORDER BY revision_number DESC';
        if (!$this->execute($command)) {
            $this->systemAbort('Can not read control table ('.$this->statmentErrorCode().' : '.$this->statmentErrorInfo()[2].')');
        }

        $revisions = $this->fetchAll();
        if (empty($revisions)) {
            $this->output('There is no revisions to be rolled back.', self::MSG_WARNING);
            $this->systemAbort();
        }

        // Get target revision
        if (is_null($this->target)) {
            $target = 0;
        } elseif (!is_numeric($this->target)) {
            $this->systemAbort([
                'Syntax error!'            => self::MSG_ERROR,
                'Invalid revision number.' => self::MSG_INFORMATION,
            ]);
        } else {
            $target = (int) $this->target;
        }

        $this->output('');
        $this->output(self::CS_INFORMATION.'Starting rollback proccess until revision #'.self::CS_WARNING.$target);

        foreach ($revisions as $revision) {
            // Hit target?
            if ((int) $revision['revision_number'] <= $target) {
                break;
            }

            $this->output('');
            $this->output('Applying rollback of revision #'.self::CS_INFORMATION.$revision['revision_number']);

            $command = 'SELECT script_file FROM '.$this->controlTable.' WHERE revision_number = ? ORDER BY script_file DESC';
            if (!$this->execute($command, [$revision['revision_number']])) {
                $this->systemAbort('Can not read control table ('.$this->statmentErrorCode().' : '.$this->statmentErrorInfo()[2].')');
            }

            $files = $this->fetchAll();

            $error = false;
            foreach ($files as $file) {
                $this->output('  Running rollback script '.self::CS_INFORMATION.$file['script_file'], self::MSG_INFORMATION, false);
                $script = $this->getScriptsPath($revision['revision_number'], self::DIR_DOWN).DS.$file['script_file'];
                if (!is_file($script)) {
                    $file->output(self::CS_ERROR.' [ERR] - rollback script file not found!');
                    $error = true;
                    break;
                }

                if (!$this->runFile($script)) {
                    $this->output(self::CS_ERROR.' [ERR]');
                    if (is_array($this->error)) {
                        $this->output($this->error[2], self::MSG_ERROR);
                    } else {
                        $this->output($this->error, self::MSG_ERROR);
                    }
                    $error = true;
                    break;
                }

                $this->output(self::CS_SUCCESS.' [OK]');

                $command = 'DELETE FROM '.$this->controlTable.' WHERE revision_number = ? AND script_file = ?';
                if (!$this->execute($command, [$revision['revision_number'], $file['script_file']])) {
                    $this->systemAbort('Can not delete from control table ('.$this->statmentErrorCode().' : '.$this->statmentErrorInfo()[2].')');
                }
            }

            if ($error) {
                $this->systemAbort('Rollback fail!');
            }
            $this->output('  Rollback of revision #'.self::CS_INFORMATION.$revision['revision_number'].self::CS_RESET.' sucessfully applied.');
        }
    }

    /**
     *  \brief Load the current revisions from control file.
     */
    private function loadCurrentRevision()
    {
        $this->currentRevision = [];

        // Check if revision control file exists and is writable
        if (file_exists($this->revFile)) {
            // return intval(file_get_contents($this->revFile));
            $revisions = file_get_contents($this->revFile);
            $aRevs = explode(',', $revisions);
            foreach ($aRevs as $range) {
                $aRange = explode('-', $range);
                if (count($aRange) == 1) {
                    $this->currentRevision[ intval($aRange[0]) ] = intval($aRange[0]);
                } else {
                    $this->currentRevision[ intval($aRange[0]) ] = intval($aRange[1]);
                }
            }

            return true;
        }

        return false;
    }

    /**
     *  \brief Get all revision directories.
     */
    private function getRevisions()
    {
        $return = [];

        foreach (new \DirectoryIterator($this->revPath) as $file) {
            if ($file->isDir() && !$file->isDot() && is_numeric($file->getBasename())) {
                $return[] = (int) $file->getBasename();
            }
        }

        sort($return, SORT_NUMERIC);

        return $return;
    }

    /**
     *  \brief Get script files from revision directory.
     */
    private function getRevisionFiles($revision, $direction)
    {
        $dir = $this->getScriptsPath($revision, $direction);

        if (!is_dir($dir)) {
            $this->systemAbort('Directory with '.($direction == self::DIR_UP ? 'MIGRATE' : 'ROLLBACK').' scripts for revision #'.$revision.' not found.');
        }

        $return = [];
        foreach (new \DirectoryIterator($dir) as $file) {
            if ($file->isFile() && pathinfo($file->getFilename(), PATHINFO_EXTENSION) == 'sql') {
                $return[] = $file->getBasename();
            }
        }

        if (self::DIR_UP) {
            sort($return, SORT_REGULAR);
        } else {
            rsort($return, SORT_REGULAR);
        }

        return $return;
    }

    /**
     *  \brief Get the path of revisions' scripts.
     */
    private function getScriptsPath($revision, $direction)
    {
        return $this->revPath.DS.$revision.DS.$this->getScriptsSubdir($direction);
    }

    /**
     *  \brief Get name of the scripts' subdirectory.
     */
    private function getScriptsSubdir($direction)
    {
        if ($direction == self::DIR_UP) {
            return 'migrate';
        } elseif ($direction == self::DIR_DOWN) {
            return 'rollback';
        }

        $this->systemAbort('Direction undefined');
    }

    /**
     *  \brief Run a revision file.
     */
    private function runFile($file)
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        switch ($extension) {
        case 'sql':
            $content = file_get_contents($file);
            if ($content === false) {
                $this->setError('Cannot open file '.self::CS_INFORMATION.$file.self::CS_RESET);

                return false;
            }

            try {
                if (!$this->execute($content)) {
                    $this->setError($this->statmentErrorInfo());

                    return false;
                }

                return true;
            } catch (Exception $e) {
                $this->setError('['.self::CS_ERROR.$e->getCode().self::CS_RESET.'] '.$e->getMessage().' in '.self::CS_WARNING.$file.self::CS_RESET);
            }
            break;
        }

        return false;
    }

    /**
     *  \brief Print a message to output device.
     */
    private function output($message, $type = 0, $lineBreak = true)
    {
        if (is_array($message)) {
            foreach ($message as $part => $type) {
                $this->output($part, $type);
            }
        } else {
            switch ($type) {
                case self::MSG_INFORMATION:
                    $msgTemplate = '%s';
                    break;
                case self::MSG_WARNING:
                    $msgTemplate = self::CS_WARNING.'WARNING:'.self::CS_RESET.' %s';
                    break;
                case self::MSG_ERROR:
                    $msgTemplate = self::CS_ERROR.'ERROR:'.self::CS_RESET.' %s';
                    break;
                default:
                    $msgTemplate = '%s';
            }

            printf($msgTemplate, $message);
            echo self::CS_RESET;

            if (!$lineBreak) {
                return;
            }
            echo "\n";
        }
    }

    /**
     *  \brief Set a sistem error message.
     */
    private function setError($error)
    {
        $this->error = $error;
    }

    private function systemAbort($message = false)
    {
        if ($message) {
            $this->output($message, self::MSG_ERROR);
        }

        $this->output('');
        $this->output('System aborted!');
        exit(1);
    }
}
