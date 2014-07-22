<?php

namespace Entities;

/**
 * @table (name="users")
 */
class User {
	/**
	 * @primaryKey
	 * @column(type="int")
	 * @var int
	 */
	public $id;

	/**
	 * @column
	 * @var string
	 */
	public $fullname;

	/**
	 * @column(type="datetime")
	 * @var \DateTime
	 */
	public $createdAt;

	/**
	 * @column(type="datetime")
	 * @var \DateTime
	 */
	public $updatedAt;

	/**
	 * @column(type="date")
	 * @var \DateTime
	 */
	public $birthDate;
}