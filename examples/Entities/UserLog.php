<?php

namespace Entities;

/**
 * @table (name="users_log")
 */
class UserLog {
	/**
	 * @primaryKey
	 * @autoIncrement
	 * @column(type="int")
	 * @var int
	 */
	public $id;

	/**
	 * @column
	 * @var string
	 */
	public $text;

	/**
	 * @column(type="datetime")
	 * @var \DateTime
	 */
	public $createdAt;
}