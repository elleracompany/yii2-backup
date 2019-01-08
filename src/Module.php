<?php

namespace ellera\backup;

use Yii;
use ellera\backup\models\Backup;

class Module extends \yii\base\Module
{
	/**
	 * Controller namespace
	 * @var string
	 */
	public $controllerNamespace;

	/**
	 * Backup path.
	 * If the directory does not exist, the module will try to create it in init()
	 * @var string
	 */
	public $path = '@app/_backup';

	/**
	 * List of folders to backup
	 * @var array
	 */
	public $folders = [];

	/**
	 * List of databases to backup
	 * @var array
	 */
	public $databases = ['db'];

	/**
	 * List of databases to backup
	 * @var array
	 */
	public $pagesize = 10;

	/**
	 * List of servers for redundant scp uploads
	 * @var array
	 */
	public $servers = [];

	/**
	 * Database table name
	 * @var string
	 */
	public $table = 'backup';

	/**
	 * @var string timestamp for the backup
	 */
	public $timestamp;

	/**
	 * @var array of zipped files, for database record
	 */
	public $zipped_files = [];

	/**
	 * @var array of dumped databases, for database record
	 */
	public $dumped_databases = [];

	/**
	 * Initiate the module
	 */
	public function init()
	{
		$path = Yii::getAlias($this->path);
		if (!is_dir($path) && !mkdir($path,0777))
			die("Unable to create backup folder in $path. \nCheck permissions and try again.");
		$this->timestamp = time();
		parent::init();
	}

	/**
	 * Register the module with the application
	 * if it's a console instance.
	 *
	 * @param $app
	 */
	public function bootstrap($app)
	{
		if ($app instanceof \yii\console\Application) {
			$this->controllerNamespace = 'ellera\backup\controllers';
		}
	}

	/**
	 * Calculate folder size
	 *
	 * @param $dir
	 *
	 * @return int
	 */
	public function folderSize($dir)
	{
		// https://gist.github.com/eusonlito/5099936

		$size = 0;
		foreach (glob(rtrim($dir, '/').'/*', GLOB_NOSORT) as $each) {
			$size += is_file($each) ? filesize($each) : $this->folderSize($each);
		}
		return $size;
	}

	public function createBackup($comment)
	{
		$path = Yii::getAlias($this->path.DIRECTORY_SEPARATOR.$this->timestamp);
		if (!is_dir($path) && !mkdir($path,0777))
			die("Unable to create backup folder in $path. \nCheck permissions and try again.");

		$raw_comment = explode('=', $comment);
		$comment = end($raw_comment );
		echo "    Starting backup...\n";
		if(!empty($this->folders))
		{
			echo "    - Zipping folders...\n";

			foreach ($this->folders as $name => $folder) {
				$filename = $this->zipFolder($name, $folder, $path);
				$this->zipped_files[$name] = [
					'src' => $filename,
					'dst' => Yii::getAlias($folder)
				];
				echo "    - - $name\n";
			}

			echo "    - Folders zipped.\n";
		}

		if(!empty($this->databases))
		{
			echo "    - Dumping databases...\n";
			foreach ($this->databases as $database)
			{
				echo "    - - $database\n";
				$this->dumped_databases[$database] = $this->dumpDatabase($database, $path);
			}

			echo "    - Databases dumped.\n";
		}
		$backup = new Backup();
		$backup->timestamp = $this->timestamp;
		$backup->path = $path;
		$backup->comment = $comment;
		$backup->files = serialize($this->zipped_files);
		$backup->dump = serialize($this->dumped_databases);
		$backup->size = $this->folderSize($path);
		if(!empty($this->servers))
		{
			echo "    - Uplading to backup-servers...\n";
			foreach ( $this->servers as $server )
			{
				echo "    - - ".$server['host']."\n";
				exec('ssh '.$server['user'].'@'.$server['host'].' \'mkdir -p '.$server['path'].DIRECTORY_SEPARATOR.$this->timestamp.'\'');
				foreach ($this->zipped_files as $name => $file) exec('scp '.$file['src'].' '.$server['user'].'@'.$server['host'].':'.$server['path'].DIRECTORY_SEPARATOR.$this->timestamp);
				foreach ($this->dumped_databases as $database) exec('scp '.$database.' '.$server['user'].'@'.$server['host'].':'.$server['path'].DIRECTORY_SEPARATOR.$this->timestamp);
			}
			echo "    - Upload complete.\n";
		}
		echo "    Backup complete (".$this->formatBytes($backup->size).")\n";
		return $backup;
	}

	/**
	 * Format bytes to human readable form
	 *
	 * @param $bytes
	 * @param int $precision
	 *
	 * @return string
	 */
	public function formatBytes($bytes, $precision = 2) {
		$units = array('B', 'KB', 'MB', 'GB', 'TB');

		$bytes = max($bytes, 0);
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, count($units) - 1);

		$bytes /= pow(1024, $pow);

		return round($bytes, $precision) . ' ' . $units[$pow];
	}

	/**
	 * Delete folder and contents
	 *
	 * @param $dirPath
	 */
	public function deleteDir($dirPath) {
		if (! is_dir($dirPath)) {
			throw new \InvalidArgumentException("$dirPath must be a directory");
		}
		if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
			$dirPath .= '/';
		}
		$files = glob($dirPath . '*', GLOB_MARK);
		foreach ($files as $file) {
			if (is_dir($file)) {
				self::deleteDir($file);
			} else {
				unlink($file);
			}
		}
		rmdir($dirPath);
	}

	/**
	 * Return human readable form of elapsed time between $time and now.
	 *
	 * @param $time
	 *
	 * @return string
	 */
	public function timeSince ($time)
	{
		// https://stackoverflow.com/questions/2915864/php-how-to-find-the-time-elapsed-since-a-date-time#answer-2916189
		$time = time() - $time; // to get the time since that moment
		$time = ($time<1)? 1 : $time;
		$tokens = array (
			31536000 => 'year',
			2592000 => 'month',
			604800 => 'week',
			86400 => 'day',
			3600 => 'hour',
			60 => 'minute',
			1 => 'second'
		);

		foreach ($tokens as $unit => $text) {
			if ($time < $unit) continue;
			$numberOfUnits = floor($time / $unit);
			return $numberOfUnits.' '.$text.(($numberOfUnits>1)?'s':'');
		}

	}

	/**
	 * Creates a zip-file containing the content of $folder
	 *
	 * @param $name
	 * @param $folder
	 *
	 * @return string
	 */
	private function zipFolder($name, $folder, $path)
	{
		if($path)
		{
			// https://stackoverflow.com/questions/4914750/how-to-zip-a-whole-folder-using-php
			$filename = $path.DIRECTORY_SEPARATOR.$name;
			$zip = new \ZipArchive();
			$zip->open($filename, \ZipArchive::CREATE);

			$files = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator(Yii::getAlias($folder)),
				\RecursiveIteratorIterator::LEAVES_ONLY
			);

			foreach ($files as $name => $file)
			{
				// Skip directories (they would be added automatically)
				if (!$file->isDir())
				{
					// Get real and relative path for current file
					$filePath = $file->getRealPath();
					$relativePath = substr($filePath, strlen(Yii::getAlias($folder)) + 1);

					// Add current file to archive
					$zip->addFile($filePath, $relativePath);
				}
			}

			// Zip archive will be created only after closing object
			$zip->close();
			return $filename;
		}
	}

	/**
	 * Function invoked before restoring a backup
	 * Can be extended and used for putting the application in
	 * maintenance mode.
	 *
	 * If the function returns false, the restore function will abort
	 *
	 * @return bool
	 */
	public function beforeRestore(): bool
	{
		return true;
	}

	/**
	 * Function invoked after a backup is restored
	 * Should be used to reverse the actions in beforeRestore()
	 *
	 * If the function returns false, the operator will be notified
	 *
	 * @return bool
	 */
	public function afterRestore(): bool
	{
		return true;
	}

	/**
	 * Dumping database to file
	 *
	 * @return string
	 */
	private function dumpDatabase($database_handle, $path)
	{
		$database = Yii::$app->$database_handle->createCommand("SELECT DATABASE()")->queryScalar();
		// https://stackoverflow.com/questions/6750531/using-a-php-file-to-generate-a-mysql-dump
		exec('mysqldump --user='.Yii::$app->$database_handle->username.' --password='.Yii::$app->$database_handle->password.' --host=localhost '.$database.' > '.$path.DIRECTORY_SEPARATOR.$database.'.sql 2> /dev/null');
		return $path.DIRECTORY_SEPARATOR.$database.'.sql';
	}
}