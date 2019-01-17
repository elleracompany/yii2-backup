<?php

namespace ellera\backup\controllers;

use Yii;
use ellera\backup\Module;
use ellera\backup\models\Backup;
use yii\console\Controller;
use yii\helpers\Console;

class DeleteController extends Controller
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
	 * Delete database record and files of backup based on ID
	 *
	 * @param int $id
	 *
	 * @throws \Throwable
	 * @throws \yii\db\StaleObjectException
	 */
	public function actionIndex(int $id) : void
	{
		$backup = Backup::findOne($id);
		if($backup)
		{
			if($backup->delete()) $this->stdout("\n  Backup with ID {$id} deleted.\n\n", Console::FG_GREEN);
			else $this->stdout("\n  Backup with ID {$id} deleted.\n\n", Console::FG_GREEN);
			exit(0);
		}
		else
		{
			$this->stdout("\n  Backup {$id} was not found\n\n", Console::FG_RED);
			exit(1);
		}
	}
}