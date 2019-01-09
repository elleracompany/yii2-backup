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
}
