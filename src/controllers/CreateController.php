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
	public function actionIndex($comment = "No comment")
	{
		echo "\n  Creating backup...\n\n";
		$backup = $this->module->createBackup($comment);
		$backup->save();
		$this->stdout("\n  Backup created.\n\n", Console::FG_GREEN);
	}
}