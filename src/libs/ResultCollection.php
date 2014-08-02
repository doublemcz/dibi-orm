<?php

namespace doublemcz\dibiorm;

class ResultCollection implements \Iterator, \Countable, \ArrayAccess
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
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Whether a offset exists
	 * @link http://php.net/manual/en/arrayaccess.offsetexists.php
	 * @param mixed $offset <p>
	 * An offset to check for.
	 * </p>
	 * @return boolean true on success or false on failure.
	 * </p>
	 * <p>
	 * The return value will be casted to boolean if non-boolean was returned.
	 */
	public function offsetExists($offset)
	{
		$this->fetchData();
		return array_key_exists($offset, $this->data);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Offset to retrieve
	 * @link http://php.net/manual/en/arrayaccess.offsetget.php
	 * @param mixed $offset <p>
	 * The offset to retrieve.
	 * </p>
	 * @return mixed Can return all value types.
	 */
	public function offsetGet($offset)
	{
		$this->fetchData();
		return $this->data[$offset];
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Offset to set
	 * @link http://php.net/manual/en/arrayaccess.offsetset.php
	 * @param mixed $offset <p>
	 * The offset to assign the value to.
	 * </p>
	 * @param mixed $value <p>
	 * The value to set.
	 * </p>
	 * @return void
	 */
	public function offsetSet($offset, $value)
	{
		$this->fetchData();
		$this->data[$offset] = $value;
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Offset to unset
	 * @link http://php.net/manual/en/arrayaccess.offsetunset.php
	 * @param mixed $offset <p>
	 * The offset to unset.
	 * </p>
	 * @return void
	 */
	public function offsetUnset($offset)
	{
		$this->fetchData();
		unset($this->data[$offset]);
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