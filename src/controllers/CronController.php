<?php

namespace ellera\backup\controllers;

use ellera\backup\Module;
use yii\console\Controller;

class CronController extends Controller
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
		// Cron Controller
	}
}