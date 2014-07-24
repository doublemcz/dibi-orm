<?php

namespace doublemcz\dibiorm;

class ResultCollection implements \Iterator, \Countable
{
	/** @var Manager */
	protected $manager;
	/** @var null|array */
	protected $data = NULL;
	/** @var int */
	protected $position = 0;
	/** @var EntityAttributes */
	protected $entityAttributes;
	/** @var array */
	protected $joinParameters;

	/**
	 * @param Manager $manager
	 * @param EntityAttributes $entityAttributes
	 * @param array $joinParameters
	 */
	public function __construct(Manager $manager, EntityAttributes $entityAttributes, $joinParameters = array())
	{
		$this->manager = $manager;
		$this->entityAttributes = $entityAttributes;
		$this->joinParameters = $joinParameters;
	}

	protected function fetchData()
	{
		if (!is_null($this->data)) {
			return;
		}

		$result = $this->manager->getDibiConnection()->select(array_keys($this->entityAttributes->getProperties()))
			->from($this->entityAttributes->getTable())
			->where($this->joinParameters)
			->fetchAll();

		$this->data = array();
		if (!empty($result)) {
			foreach ($result as $rowData) {
				$this->data[] = DataHelperLoader::createFlatClass($this->manager, $this->entityAttributes, $rowData);
			}
		}
	}

	/**
	 * @return int
	 */
	public function count()
	{
		$this->fetchData();
		return count($this->data);
	}

	public function rewind()
	{
		$this->position = 0;
	}

	public function current()
	{
		$this->fetchData();
		return $this->data[$this->position];
	}

	public function key()
	{
		return $this->position;
	}

	public function next()
	{
		$this->fetchData();
		++$this->position;
	}

	public function valid()
	{
		$this->fetchData();
		return isset($this->data[$this->position]);
	}
}