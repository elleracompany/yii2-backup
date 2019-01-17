<?php

namespace ellera\backup\components;


class Methods
{
	/**
	 * Method invoked before a backup is created
	 * If it returns != true the execution is stopped
	 *
	 * @return bool
	 */
	public function beforeCreate() : bool
	{
		return true;
	}

	/**
	 * Method invoked after a backup is created
	 * @return bool
	 */
	public function afterCreate() : bool
	{
		return true;
	}

	/**
	 * Method invoked before a backup is restored
	 * If it returns != true the execution is stopped
	 *
	 * @return bool
	 */
	public function beforeRestore() : bool
	{
		return true;
	}

	/**
	 * Method invoked after a backup is restored
	 * @return bool
	 */
	public function afterRestore() : bool
	{
		return true;
	}
}