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
	 * @manyToMany(entity="UserLog", joiningTable="joining_table")
	 * @joinPrimary(column="id", referenceColumn="userId")
	 * @joinSecondary(column="userLogId", referenceColumn="id")
	 * @var UserLog[]
	 */
	protected $joiningTableUserLog;

	/**
	 * @oneToMany(entity="UserLog")
	 * @join(column="id", referenceColumn="userId")
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
	 * @column(type="string")
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
	public function getJoiningTableUserLog()
	{
		return $this->joiningTableUserLog;
	}

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