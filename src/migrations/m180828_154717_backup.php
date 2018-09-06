<?php

use yii\db\Migration;

/**
 * Class m180828_154717_backup
 */
class m180828_154717_backup extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
		$this->createTable('backup', [
			'id' => $this->primaryKey(),
			'timestamp' => $this->integer(),
			'path' => $this->string(),
			'files' => $this->text(),
			'dump' => $this->text(),
			'size' => $this->bigInteger(),
			'comment' => $this->string()
		]);
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $this->dropTable('backup');
    }
}
