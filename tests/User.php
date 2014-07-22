<?php

namespace Entities;

/**
 * @table (name="users")
 */
class User {
	/**
	 * @primaryKey
	 * @autoIncrement
	 * @column(type="int")
	 * @var int
	 */
	public $id;

	/**
	 * @column(type="string", length="50")
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