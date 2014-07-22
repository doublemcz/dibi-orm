<?php

namespace doublemcz\dibiorm;

class QueryBuilder {
	/** @var Manager */
	protected $manager;
	/** @var \DibiFluent */
	private $query;

	public function __construct(Manager $manager)
	{
		$this->manager = $manager;
	}

	public function select($args)
	{
		$this->query = $this->manager->getDibiConnection()->select($args);
	}
}