<?php

namespace ellera\backup\controllers;

use Yii;
use ellera\backup\Module;
use yii\console\Controller;
use yii\helpers\Console;


class CreateController extends Controller
{
	/**
	 * @var module instance
	 */
	public $module;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		$this->module = Module::getInstance();
		parent::init();
	}

	/**
	 * Create a new backup
	 *
	 * @param string $comment
	 */
	public function actionIndex(string $comment = "No comment") : void
	{

		echo "\n  Creating backup...\n\n";
		$this->createBackup($comment);
		$this->stdout("\n  Backup created.\n\n", Console::FG_GREEN);
	}

	/**
	 * Crate a new backup
	 * Non-interactive cron-job
	 *
	 * @param string $comment
	 *
	 * @throws \Throwable
	 * @throws \yii\db\StaleObjectException
	 */
	public function actionCron(string $comment = "Cron Job") : void
	{
		$this->createBackup($comment, false);
		$this->module->cleanUp(false);
	}

	/**
	 * Private method checking beforeCreate() and afterCreate()
	 *
	 * @param string $comment
	 * @param bool   $verbose
	 */
	private function createBackup(string $comment, bool $verbose = true) : void
	{
		if(!$this->module->methods_class_instance->beforeCreate())
		{
			$this->stdout("\n\n  [!] beforeCreate() returned false.\n", Console::FG_RED);
			echo "\n  Exiting...\n\n";
			exit(1);
		}
		$backup = $this->module->createBackup($comment, $verbose);
		if(!$backup->save()) $this->stdout("\n\n  [!] Backup record could not be save to database.\n", Console::FG_RED);
		if(!$this->module->methods_class_instance->afterCreate())
		{
			$this->stdout("\n\n  [!] afterCreate() returned false. The system might still be in backup mode.\n", Console::FG_RED);
		}

	}

	/**
	 * Create fake backups
	 * For cleanup testing purposes
	 *
	 * @param int $start
	 * @param int $interval
	 */
	public function actionFake(int $start, int $interval) : void
	{
		$this->module->timestamp = $start;
		while($this->module->timestamp < time()) {
			$this->module->timestamp += ($interval + rand(0,10));
			echo "\n  Creating backup...\n\n";
			$this->createBackup("Fake");
			$this->stdout("\n  Backup created.\n\n", Console::FG_GREEN);
		}
	}
}