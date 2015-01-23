<?php

namespace doublemcz\dibiorm\Examples\Entities;

/**
 * @table (name="cities")
 */
class City {
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
	public $name;

	/**
	 * @column
	 * @var int
	 */
	public $population;
}