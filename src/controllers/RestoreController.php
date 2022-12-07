<?php

namespace ellera\backup\controllers;

use Yii;
use ellera\backup\Module;
use ellera\backup\models\Backup;
use yii\console\Controller;
use yii\helpers\Console;

class RestoreController extends Controller
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
	 * Restore database and file-locations from backup with ID
	 *
	 * @param int $id
	 *
	 * @throws \Throwable
	 * @throws \yii\base\InvalidConfigException
	 * @throws \yii\db\StaleObjectException
	 */
	public function actionIndex(int $id) : void
	{
		$backup = Backup::findOne($id);
		if(!$backup)
		{
			$this->stdout("\n  Error encountered when trying to restore:");
			$this->stdout("\n  Backup {$id} was not found\n\n", Console::FG_RED);
			exit(1);
		}
		if(!$backup->filesExist())
		{
			$this->stdout("\n  Error encountered when trying to restore:");
			$this->stdout("\n  Backup files for {$id} was not found\n\n", Console::FG_RED);
			exit(1);
		}
		$this->stdout("\nBackup {$id} - ".Yii::$app->formatter->asDatetime($backup->timestamp)."\n\n");

		if(!empty(unserialize($backup->files))) {
			$this->stdout( "[-] Files:\n", Console::FG_YELLOW );
			foreach ( unserialize( $backup->files ) as $name => $path ) {
				$this->stdout( "    - {$name} ({$path['dst']})\n", Console::FG_YELLOW );
			}
		}

		if(!empty(unserialize($backup->dump)))
		{
			$this->stdout("\n[-] Databases:\n", Console::FG_YELLOW);
			foreach(unserialize($backup->dump) as $name => $path) {
				$this->stdout("    - {$name} ({$path})\n", Console::FG_YELLOW);
			}
		}

		$this->stdout("\n[!] This backup is ".$this->module->timeSince($backup->timestamp)." old\n");

		echo "\nRestore from this backup? (yes|no) [no]: ";

		$answer = trim(fgets(STDIN));

		if(strtolower($answer) != 'y' && strtolower($answer) != 'yes')
		{
			echo "\n  Exiting...\n\n";
			exit(0);
		}

		if(!$this->module->methods_class_instance->beforeRestore())
		{
			$this->stdout("\n\n  [!] beforeRestore() returned false.\n", Console::FG_RED);
			echo "\n  Exiting...\n\n";
			exit(1);
		}

		echo "\n  Creating pre-restore backup...\n\n";
		$pre = $this->module->createBackup("Auto backup of pre-restore state");

		if(!empty(unserialize($backup->dump))) {

			echo "\n  Restoring databases...\n\n";

			foreach ( unserialize( $backup->dump ) as $name => $path ) {
				$database = Yii::$app->$name->createCommand("SELECT DATABASE()")->queryScalar();
				$length = strlen($database);
				// https://stackoverflow.com/questions/2050581/how-to-delete-mysql-database-through-shell-command
				$this->stdout("    DROPPING {$database} ", Console::FG_YELLOW );
				$this->stdout(str_repeat(".",30-$length), Console::FG_YELLOW );
				$this->dropDatabase($name,$database);
				$this->stdout(" done\n", Console::FG_GREEN );

				$this->stdout("    CREATING {$database} ", Console::FG_YELLOW );
				$this->stdout(str_repeat(".",30-$length), Console::FG_YELLOW );
				$this->createDatabase($name,$database);
				$this->stdout(" done\n", Console::FG_GREEN );

				$this->stdout("    IMPORTING {$database} ", Console::FG_YELLOW );
				$this->stdout(str_repeat(".",29-$length), Console::FG_YELLOW );
				$this->importDatabase($name,$database,$path);
				$this->stdout(" done\n", Console::FG_GREEN );
			}
		}

		if(!empty(unserialize($backup->files))) {

			echo "\n  Restoring files...\n\n";

			foreach ( unserialize( $backup->files ) as $name => $path ) {

				$length = strlen($name);

				$this->stdout("    Unzipping {$name} ", Console::FG_YELLOW );
				$this->stdout(str_repeat(".",29-$length), Console::FG_YELLOW );
				exec("unzip -o {$path['src']} -d {$path['dst']}");
				$this->stdout(" done\n", Console::FG_GREEN );
			}
		}

		$backup->id = null;
		$backup->isNewRecord = true;
		$backup->save();
		$pre->save();

		if(!$this->module->methods_class_instance->afterRestore())
		{
			$this->stdout("\n\n  [!] afterRestore() returned false. The system might still be in maintenance mode.\n", Console::FG_RED);
		}

		$cleanup = $this->module->cleanUp(false);
		if($cleanup > 0) $this->stdout("\n  Removed {$cleanup} backups with missing files.\n\n", Console::FG_YELLOW );

		$this->stdout("\n  System restored.\n\n", Console::FG_GREEN );

		exit(0);
	}

    protected function dropDatabase(string $name, string $database) : void
    {
        exec(sprintf(
            'mysql --user=%s  --password=%s --host=%s -e "DROP DATABASE %s" 2> %s',
            Yii::$app->$name->username,
            Yii::$app->$name->password,
            $this->module->getHost($name),
            $database,
            Yii::getAlias($this->module->pathLog)
        ));
    }

    protected function createDatabase(string $name, string $database) : void
    {
        exec(sprintf(
            'mysql --user=%s  --password=%s --host=%s -e "CREATE DATABASE  %s" 2> %s',
            Yii::$app->$name->username,
            Yii::$app->$name->password,
            $this->module->getHost($name),
            $database,
            Yii::getAlias($this->module->pathLog)
        ));
    }

    protected function importDatabase(string $name, string $database, string $path) : void
    {
        exec(sprintf(
            'mysql --user=%s  --password=%s --host=%s %s < %s 2> %s',
            Yii::$app->$name->username,
            Yii::$app->$name->password,
            $this->module->getHost($name),
            $database,
            $path,
            Yii::getAlias($this->module->pathLog)
        ));
    }
}
