<?php

namespace ellera\backup;

use ellera\backup\components\Methods;
use Yii;
use ellera\backup\models\Backup;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;

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
     * Backup save log path
     * @var string
     */
    public $pathLog = '@runtime/_backup';

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
	 * @var string datetime format for cron logs
	 */
	public $datetime_format = 'Y-m-d H:i:s';

	/**
	 * @var string Method class for extendable methods
	 */
	public $methods_class = 'ellera\backup\components\Methods';

	/**
	 * @var Methods Class Instance
	 */
	public $methods_class_instance;

	/**
	 * @var array|bool Automated Cleanup
	 *
	 *   [
	 *		'daily' 	=> true,
	 *   	'weekly' 	=> true,
	 *		'monthly'	=> true,
	 *      'yearly' 	=> true
	 *   ]
	 */
	public $automated_cleanup = false;

	/**
	 * Initiate the module
	 * @throws InvalidConfigException
	 */
	public function init()
	{
		$path = Yii::getAlias($this->path);
		$this->methods_class_instance = new $this->methods_class;
		if($this->automated_cleanup && is_array($this->automated_cleanup))
		{
			$valid = ['daily', 'weekly', 'monthly', 'yearly'];
			foreach ($this->automated_cleanup as $key => $value) {
				if(!in_array($key, $valid) || !is_bool($value)) throw new InvalidConfigException('Invalid config for $automated_cleanup');
			}
		}
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
	 * @param string 	$dir
	 *
	 * @return int
	 */
	public function folderSize(string $dir) : int
	{
		// https://gist.github.com/eusonlito/5099936

		$size = 0;
		foreach (glob(rtrim($dir, '/').'/*', GLOB_NOSORT) as $each) {
			$size += is_file($each) ? filesize($each) : $this->folderSize($each);
		}
		return $size;
	}

	/**
	 * @param string	$comment
	 * @param bool 		$verbose
	 *
	 * @return Backup
	 */
	public function createBackup(string $comment, bool $verbose = true) : Backup
	{
		$path = Yii::getAlias($this->path.DIRECTORY_SEPARATOR.$this->timestamp);
		if (!is_dir($path) && !mkdir($path,0777))
			die("Unable to create backup folder in $path. \nCheck permissions and try again.");

		$raw_comment = explode('=', $comment);
		$comment = end($raw_comment );
		if($verbose) echo "    Starting backup...\n";
		if(!empty($this->folders))
		{
			if($verbose) echo "    - Zipping folders...\n";

			foreach ($this->folders as $name => $folder) {
				$filename = $this->zipFolder($name, $folder, $path);
				$this->zipped_files[$name] = [
					'src' => $filename,
					'dst' => Yii::getAlias($folder)
				];
				if($verbose) echo "    - - $name\n";
			}

			if($verbose) echo "    - Folders zipped.\n";
		}

		if(!empty($this->databases))
		{
			if($verbose) echo "    - Dumping databases...\n";
			foreach ($this->databases as $database)
			{
				if($verbose) echo "    - - $database\n";
				$this->dumped_databases[$database] = $this->dumpDatabase($database, $path);
			}

			if($verbose) echo "    - Databases dumped.\n";
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
			if($verbose) echo "    - Uplading to backup-servers...\n";
			foreach ( $this->servers as $server )
			{
				if($verbose) echo "    - - ".$server['host']."\n";
				exec('ssh '.$server['user'].'@'.$server['host'].' \'mkdir -p '.$server['path'].DIRECTORY_SEPARATOR.$this->timestamp.'\'');
				foreach ($this->zipped_files as $name => $file) exec('scp '.$file['src'].' '.$server['user'].'@'.$server['host'].':'.$server['path'].DIRECTORY_SEPARATOR.$this->timestamp);
				foreach ($this->dumped_databases as $database) exec('scp '.$database.' '.$server['user'].'@'.$server['host'].':'.$server['path'].DIRECTORY_SEPARATOR.$this->timestamp);
			}
			if($verbose) echo "    - Upload complete.\n";
		}
		if($verbose) echo "    Backup complete (".$this->formatBytes($backup->size).")\n";
		else echo '['.date($this->datetime_format)."] Automated Backup Created (".$this->formatBytes($backup->size).")\n";
		return $backup;
	}

	/**
	 * Format bytes to human readable form
	 *
	 * @param int 	$bytes
	 * @param int 	$precision
	 *
	 * @return string
	 */
	public function formatBytes(int $bytes, int $precision = 2) : string
	{
		$units = array('B', 'KB', 'MB', 'GB', 'TB');

		$bytes = max($bytes, 0);
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, count($units) - 1);

		$bytes /= pow(1024, $pow);

		return round($bytes, $precision) . ' ' . $units[$pow];
	}

	/**
	 * Return human readable form of elapsed time between $time and now.
	 *
	 * @param int 	$time
	 *
	 * @return string
	 */
	public function timeSince(int $time) : string
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
		return 'Not Defined';
	}

	/**
	 * Cleans up old and deleted backups.
	 *
	 * @param bool $verbose
	 *
	 * @throws \Throwable
	 * @throws \yii\db\StaleObjectException
	 */
	public function cleanUp(bool $verbose = true) : void
	{
		$missing_files = 0;
		/* @var $backups Backup[] */
		$backups = Backup::find()->all();
		foreach ($backups as $backup) if(!$backup->filesExist()) {
			if($backup->delete()) {
				if($verbose) echo "   [*] Deleted backup with ID {$backup->id}: Missing files\n";
				$missing_files++;
			}
		}

		if($verbose) echo "Deleted {$missing_files} backups with missing files\n";

		if($this->automated_cleanup !== false) {
			// Get all backups timestamps and IDs
			$timestamps_to_id = ArrayHelper::map((new \yii\db\Query())
				->select(['id', 'timestamp'])
				->from($this->table)
				->all(),'timestamp','id');

			// Array of only timestamps
			$timestamps = array_keys($timestamps_to_id);

			// Daily CleanUp
			if(array_key_exists('daily', $this->automated_cleanup) && $this->automated_cleanup['daily'] === true) {
				$reference_time = new \DateTime();
				// $reference_time->setDate($reference_time->format('Y'), 1, 1); // Needed for yearly/weekly/monthly
				$reference_time->setTime(0,0,0);
				$reference_time->modify('-1 day');
				$reference_time_start = clone $reference_time;
				$reference_time_start->modify('-1 day');

				$end = $reference_time->getTimestamp();
				$start = $reference_time_start->getTimestamp();
				$removable = array_filter(
					$timestamps,
					function ($value) use($start, $end) {
						return ($value > $start && $value < $end);
					}
				);

				sort($removable);
				array_pop($removable);
				$daily = 0;
				foreach ($removable as $rem) {
					$backup = Backup::findOne($timestamps_to_id[$rem]);
					if($backup) {
						$result = $backup->delete();
						if($result) $daily += $result;
					}
				}

				if($verbose) echo "Deleted {$daily} backups from daily cleanup\n";
			}

			// Weekly CleanUp
			if(array_key_exists('weekly', $this->automated_cleanup) && $this->automated_cleanup['weekly'] === true) {
				$reference_time = new \DateTime();
				// $reference_time->setDate($reference_time->format('Y'), 1, 1); // Needed for yearly/weekly/monthly
				$reference_time->setTime(0,0,0);
				$reference_time->modify('last monday');
				$reference_time->modify('-1 week');
				$reference_time_start = clone $reference_time;
				$reference_time_start->modify('-1 week');

				$end = $reference_time->getTimestamp();
				$start = $reference_time_start->getTimestamp();
				$removable = array_filter(
					$timestamps,
					function ($value) use($start, $end) {
						return ($value > $start && $value < $end);
					}
				);

				sort($removable);
				array_pop($removable);
				$weekly = 0;
				foreach ($removable as $rem) {
					$backup = Backup::findOne($timestamps_to_id[$rem]);
					if($backup) {
						$result = $backup->delete();
						if($result) $weekly += $result;
					}
				}

				if($verbose) echo "Deleted {$weekly} backups from weekly cleanup\n";
			}

			// Monthly CleanUp
			if(array_key_exists('monthly', $this->automated_cleanup) && $this->automated_cleanup['monthly'] === true) {
				$reference_time = new \DateTime();
				$reference_time->setTime(0,0,0);
				$reference_time->setDate($reference_time->format('Y'), $reference_time->format('m'), 1);
				$reference_time->modify('-1 month');
				$reference_time_start = clone $reference_time;
				$reference_time_start->modify('-1 month');

				$end = $reference_time->getTimestamp();
				$start = $reference_time_start->getTimestamp();
				$removable = array_filter(
					$timestamps,
					function ($value) use($start, $end) {
						return ($value > $start && $value < $end);
					}
				);

				sort($removable);
				array_pop($removable);
				$monthly = 0;
				foreach ($removable as $rem) {
					$backup = Backup::findOne($timestamps_to_id[$rem]);
					if($backup) {
						$result = $backup->delete();
						if($result) $monthly += $result;
					}
				}

				if($verbose) echo "Deleted {$monthly} backups from monthly cleanup\n";
			}

			// Weekly CleanUp
			if(array_key_exists('weekly', $this->automated_cleanup) && $this->automated_cleanup['weekly'] === true) {
				$reference_time = new \DateTime();
				$reference_time->setTime(0,0,0);
				$reference_time->setDate($reference_time->format('Y'), 1, 1);
				$reference_time->modify('-1 year');
				$reference_time_start = clone $reference_time;
				$reference_time_start->modify('-1 year');

				$end = $reference_time->getTimestamp();
				$start = $reference_time_start->getTimestamp();
				$removable = array_filter(
					$timestamps,
					function ($value) use($start, $end) {
						return ($value > $start && $value < $end);
					}
				);

				sort($removable);
				array_pop($removable);
				$yearly = 0;
				foreach ($removable as $rem) {
					$backup = Backup::findOne($timestamps_to_id[$rem]);
					if($backup) {
						$result = $backup->delete();
						if($result) $yearly += $result;
					}
				}

				if($verbose) echo "Deleted {$yearly} backups from yearly cleanup\n";
			}
		}

	}

	/**
	 * Creates a zip-file containing the content of $folder
	 *
	 * @param string 	$name
	 * @param string 	$folder
	 * @param string 	$path
	 *
	 * @return string
	 */
	private function zipFolder(string $name, string $folder, string $path) : string
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
	 * Dumping database to file
	 *
	 * @param string 	$database_handle
	 * @param string 	$path
	 *
	 * @return string
	 */
    private function dumpDatabase(string $database_handle, string $path) : string
    {
        $database = Yii::$app->$database_handle->createCommand("SELECT DATABASE()")->queryScalar();
        $filePath = $path . DIRECTORY_SEPARATOR . $database . '.sql';

        exec(sprintf(
            'mysqldump --user=%s --password=%s --host=%s %s > %s 2> %s',
            Yii::$app->$database_handle->username,
            Yii::$app->$database_handle->password,
            $this->getHost($database_handle),
            $database,
            $filePath,
            Yii::getAlias($this->pathLog)
        ));

        return $filePath;
    }

    /**
     * @param string $database_handle
     *
     * @return string
     * @throws \Exception
     */
    public function getHost(string $database_handle) : string
    {
        preg_match_all(
            '/host=(.*);/m',
            Yii::$app->$database_handle->dsn,
            $matches,
            PREG_SET_ORDER,
            0
        );
        $host = $matches[0][1] ?? null;
        if ($host === null) {
            throw new \Exception('Не удалось определить host базы данных!');
        }

        return $host;
    }
}