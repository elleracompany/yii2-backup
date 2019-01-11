<?php

namespace ellera\backup\controllers;

use Yii;
use ellera\backup\Module;
use ellera\backup\models\Backup;
use yii\console\Controller;
use yii\helpers\Console;
use yii\data\Pagination;

class ListController extends Controller
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
	 * List stored backups
	 * Page defaults to 1
	 *
	 * @param int $page
	 *
	 * @throws \yii\base\InvalidConfigException
	 */
	public function actionIndex(int $page = 1) : void
	{
		$raw_page = explode('=', $page);
		$page = end($raw_page );
		$query = Backup::find();
		$countQuery = clone $query;
		$count = $countQuery->count();
		$pages = new Pagination([
			'totalCount' => $count,
			'pageSize' => $this->module->pagesize,
			'page' => $page -1
		]);
		/* @var $backups Backup[] */
		$backups = $query->offset($pages->offset)
		                 ->limit($pages->limit)
		                 ->orderBy('id DESC')
		                 ->all();
		$this->stdout("-----------------------------------------------------------------\n", Console::FG_YELLOW);
		$this->stdout("Page {$page} of {$pages->pageCount}, showing ".count($backups)." of ".$pages->totalCount." results\n");
		$this->stdout("-----------------------------------------------------------------\n\n", Console::FG_YELLOW);
		if(empty($backups)) $this->stdout("    No backups found...\n\n");
		foreach ($backups as $backup)
		{
			$this->stdout(" [{$backup->id}]\t".Yii::$app->formatter->asDatetime($backup->timestamp)."  \t\t\t{$this->module->formatBytes($backup->size)}\n",
				$backup->filesExist() ? Console::FG_GREY : Console::FG_RED);
			$this->stdout(" \t{$backup->comment}\n\n", Console::FG_YELLOW);
		}
		$size = $this->module->folderSize(Yii::getAlias($this->module->path));
		$this->stdout("-----------------------------------------------------------------\n", Console::FG_YELLOW);
		$this->stdout($pages->totalCount." Backups\t\t\t\tTotal size:\t".$this->module->formatBytes($size)."\n");
		$this->stdout("-----------------------------------------------------------------\n", Console::FG_YELLOW);
	}
}