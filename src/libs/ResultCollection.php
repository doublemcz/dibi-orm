<?php

namespace doublemcz\dibiorm;

class ResultCollection implements \Iterator, \Countable, \ArrayAccess
{
	/** @var object */
	private $parent;
	/** @var Manager */
	protected $manager;
	/** @var null|array */
	protected $data = NULL;
	/** @var int */
	protected $position = 0;
	/** @var ClassMetadata */
	protected $classMetadata;
	/** @var array */
	protected $joinParameters;

	/**
	 * @param object $parent
	 * @param Manager $manager
	 * @param ClassMetadata $entityAttributes
	 * @param array $joinParameters
	 */
	public function __construct($parent, Manager $manager, ClassMetadata $entityAttributes, $joinParameters = array())
	{
		$this->parent = $parent;
		$this->manager = $manager;
		$this->classMetadata = $entityAttributes;
		$this->joinParameters = $joinParameters;
	}

	protected function fetchData()
	{
		if (!is_null($this->data)) {
			return;
		}

		$result = $this->getData();
		$this->data = array();
		if (!empty($result)) {
			foreach ($result as $rowData) {
				$this->data[] = DataHelperLoader::createFlatClass($this->manager, $this->classMetadata, $rowData);
			}
		}
	}

	/**
	 * @return array
	 */
	private function getData()
	{
		$columns = $this->prefixColumnsForFetch(array_keys($this->classMetadata->getProperties()));
		switch ($this->joinParameters['relation']) {
			case ClassMetadata::JOIN_ONE_TO_MANY:
				$where = sprintf("a.%s = %%s",$this->joinParameters['join']['referenceColumn']);
				return $this->manager->getDibiConnection()->select($columns)
					->from($this->classMetadata->getTable())->as('a')
					->where($where, DataHelperLoader::getPropertyValue($this->parent, $this->joinParameters['join']['column']))
					->fetchAll();
				break;
			case ClassMetadata::JOIN_MANY_TO_MANY:
				$targetClassMetadata = $this->manager->createClassMetadata($this->joinParameters['entity']);
				return $this->manager->getDibiConnection()->select($columns)
					->from($targetClassMetadata->getTable())->as('a')
					->innerJoin($this->joinParameters['joiningTable'])->as('b')
					->on(sprintf(
						'a.%s = b.%s',
						$this->joinParameters['joinSecondary']['referenceColumn'],
						$this->joinParameters['joinSecondary']['column']
					))->and(
						'b.' . $this->joinParameters['joinPrimary']['referenceColumn'] . ' = %s',
						DataHelperLoader::getPropertyValue($this->parent,  $this->joinParameters['joinPrimary']['column'])
					)
					->fetchAll();
				break;
			default:
				throw new \InvalidArgumentException(sprintf('Invalid join specified %s', $this->joinParameters['relation']));
		}
	}

	/**
	 * @param array $columns
	 * @param string $prefix
	 * @return array
	 */
	private function prefixColumnsForFetch($columns, $prefix = 'a')
	{
		foreach ($columns as $key => $column) {
			$columns[$key] = $prefix . '.' . $column;
		}

		return $columns;
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