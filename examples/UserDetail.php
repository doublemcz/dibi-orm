<?php

namespace Entities;

/**
 * @table (name="users_details")
 */
class UserDetail {
	/**
	 * @primaryKey
	 * @autoIncrement
	 * @column(type="int")
	 * @var int
	 */
	public $id;
//
//	/**
//	 * @oneToOne(entity="User")
//	 * @var User
//	 */
//	public $user;

	/**
	 * @column
	 * @var string
	 */
	public $note;
}