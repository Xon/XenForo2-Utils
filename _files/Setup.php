<?php

namespace Example;

use SV\Utils\InstallerHelper;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
use XF\Entity\User;

class Setup extends AbstractSetup
{
    use InstallerHelper;
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    /**
     * Creates add-on tables.
     */
    public function installStep1()
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->createTable($tableName, $callback);
            $sm->alterTable($tableName, $callback);
        }
    }

    /**
     * Alters core tables.
     */
    public function installStep2()
    {
        $sm = $this->schemaManager();

        foreach ($this->getAlterTables() as $tableName => $callback)
        {
            $sm->alterTable($tableName, $callback);
        }
    }

    public function upgrade2000000Step1()
    {
        $this->installStep1();
    }

    public function upgrade2000000Step2()
    {
        $this->installStep2();
    }

    /**
     * Drops add-on tables.
     */
    public function uninstallStep1()
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->dropTable($tableName);
        }
    }

    /**
     * Drops columns from core tables.
     */
    public function uninstallStep2()
    {
        $sm = $this->schemaManager();

        foreach ($this->getRemoveAlterTables() as $tableName => $callback)
        {
            if ($sm->tableExists($tableName))
            {
                $sm->alterTable($tableName, $callback);
            }
        }
    }

    /**
     * @return array
     */
    protected function getTables()
    {
        $tables = [];

        //$tables['xf_example'] = function ($table) {
        //    /** @var Create|Alter $table */
        //    $this->addOrChangeColumn($table, 'example_id', 'int');
        //    $this->addOrChangeColumn($table, 'exampleCol', 'int');
        //    $table->addPrimaryKey('example_id');
        //};

        return $tables;
    }

    /**
     * @return array
     */
    protected function getAlterTables()
    {
        $tables = [];

        //$tables['xf_thread'] = function (Alter $table) {
        //    $this->addOrChangeColumn($table, 'exampleCol', 'int')->nullable(true)->setDefault(null);
        //};

        return $tables;
    }

    protected function getRemoveAlterTables()
    {
        $tables = [];

        //$tables['xf_forum'] = function (Alter $table) {
        //    $table->dropColumns(['exampleCol']);
        //};

        return $tables;
    }

}
