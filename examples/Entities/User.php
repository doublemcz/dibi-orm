<?php

namespace doublemcz\dibiorm\Examples\Entities;
use doublemcz\dibiorm\Manager;

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
	 * @oneToMany(entity="UserLog")
	 * @join(column="id", referenceColumn="userId")
	 * @staticJoin(column="type", value="error")
	 * @var User
	 */
	protected $userLog;

	/**
	 * @oneToOne(entity="UserDetail")
	 * @join(column="id", referenceColumn="userId")
	 * @var UserDetail
	 */
	protected $detail;

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

	/**
	 * @return UserLog[]
	 */
	public function getUserLog()
	{
		return $this->userLog;
	}

	/**
	 * @return UserDetail
	 */
	public function getDetail()
	{
		return $this->detail;
	}

	/**
	 * @param Manager $manager
	 */
	public function beforeCreateEvent(Manager $manager)
	{
		$this->createdAt = new \DateTime();
	}

	public function beforeUpdateEvent(Manager $manager)
	{
		$this->updatedAt = new \DateTime();
	}
}