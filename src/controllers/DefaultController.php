<?php

namespace ellera\backup\controllers;

use ellera\backup\Module;
use yii\console\Controller;
use yii\helpers\Console;

class DefaultController extends Controller
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
	}

	/**
	 * List available commands
	 */
	public function actionIndex()
	{
		$this->stdout("Commands:\n");
		$this->stdout("--------------------------------------------------------------------\n", Console::FG_YELLOW);
		$this->stdout($this->module->id."\t\t\t\t\tThis list\n", Console::FG_YELLOW);
		$this->stdout($this->module->id."/create \"Your comment\"\t\tCreates a new backup\n", Console::FG_YELLOW);
		$this->stdout($this->module->id."/list \t\t\t\tLists all stored backups\n", Console::FG_YELLOW);
		$this->stdout($this->module->id."/delete # \t\t\tDeletes the backup with that ID\n", Console::FG_YELLOW);
		$this->stdout($this->module->id."/restore # \t\t\tRestores the backup with that ID\n", Console::FG_YELLOW);
		$this->stdout("--------------------------------------------------------------------\n", Console::FG_YELLOW);
	}
}