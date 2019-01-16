<?php

namespace ellera\backup\models;

use Yii;
use ellera\backup\Module;

/**
 * This is the model class for table "backup".
 *
 * @property int $id
 * @property int $timestamp
 * @property string $path
 * @property string $files
 * @property string $dump
 * @property int $size
 * @property string $comment
 */
class Backup extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
	    $module = Module::getInstance();
        return $module->table;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['timestamp', 'size'], 'integer'],
            [['files'], 'string'],
            [['path', 'dump', 'comment'], 'string', 'max' => 255],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'timestamp' => 'Timestamp',
            'path' => 'Path',
            'files' => 'Files',
            'dump' => 'Dump',
            'size' => 'Size',
            'comment' => 'Comment',
        ];
    }

	/**
	 * Check if the backup files still exist in the
	 * file system.
	 *
	 * @return bool
	 */
    public function filesExist() : bool
	{
		if(isset($this->path) && file_exists($this->path)) return true;
		return false;
	}

	public function delete()
	{
		$result = parent::delete();
		if($result) $this->deleteDir($this->path);
		return $result;
	}

	/**
	 * Delete folder and contents
	 *
	 * @param string	$dirPath
	 *
	 * @return bool
	 */
	private function deleteDir(string $dirPath) : bool
	{
		if (! is_dir($dirPath)) {
			return true;
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
		return rmdir($dirPath);
	}
}
