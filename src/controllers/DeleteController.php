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
	 * @param $id
	 */
	public function actionIndex($id)
	{
		$backup = Backup::findOne($id);
		if($backup)
		{
			$this->module->deleteDir($backup->path);
			$backup->delete();
			$this->stdout("Backup with ID {$id} deleted.\n", Console::FG_GREEN);
		}
		else $this->stdout("Could not find backup with ID {$id}.\n", Console::FG_RED);
	}
}