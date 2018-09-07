<?php

namespace ellera\backup\components;


class Methods
{
	/**
	 * @return bool
	 */
	public function beforeRestore()
	{
		return true;
	}

	/**
	 * @return bool
	 */
	public function afterRestore()
	{
		return true;
	}

	/**
	 * @return bool
	 */
	public function beforeCreate()
	{
		return true;
	}

	/**
	 * @return bool
	 */
	public function afterCreate()
	{
		return true;
	}
}