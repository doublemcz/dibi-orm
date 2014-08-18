<?php

namespace doublemcz\dibiorm;

class RepositoryManager
{
	/** @var Manager */
	protected $manager;
	/** @var string */
	protected $entityClassName;
	/** @var ClassMetadata */
	protected $entityAttributes;

	/**
	 * @param Manager $manager
	 * @param $entityAttributes
	 */
	public function __construct(Manager $manager, ClassMetadata $entityAttributes)
	{
		$this->manager = $manager;
		$this->entityAttributes = $entityAttributes;
	}

	/**
	 * @param array $where
	 * @param array $orderBy
	 * @return array
	 */
	public function findBy($where = array(), $orderBy = array())
	{
		$fluent = $this->manager->getDibiConnection()->select(array_keys($this->entityAttributes->getProperties()))
			->from($this->entityAttributes->getTable());

		if (!empty($where)) {
			$fluent->where($where);
		}

		if (!empty($orderBy)) {
			$fluent->orderBy($orderBy);
		}

		$result = array();
		$sqlResult = $fluent->fetchAll();
		if (!empty($sqlResult)) {
			foreach ($sqlResult as $rowData) {
				$class = DataHelperLoader::createFlatClass($this, $this->entityAttributes, $rowData);
				$this->manager->registerClass($class, $this->entityAttributes, Manager::FLAG_INSTANCE_UPDATE);
				$result[] = $class;
			}
		}

		return $result;
	}

	/**
	 * @param array $where
	 * @param array $orderBy
	 * @return object|NULL
	 */
	public function findOneBy($where = array(), $orderBy = array())
	{
		$fluent = $this->manager->getDibiConnection()->select(array_keys($this->entityAttributes->getProperties()))
			->from($this->entityAttributes->getTable());

		if (!empty($where)) {
			$fluent->where($where);
		}

		if (!empty($orderBy)) {
			$fluent->orderBy($orderBy);
		}

		$sqlResult = $fluent->fetch();
		if (!empty($sqlResult)) {
			$class = DataHelperLoader::createFlatClass($this->manager, $this->entityAttributes, $sqlResult);
			$this->manager->registerClass($class, $this->entityAttributes, Manager::FLAG_INSTANCE_UPDATE);

			return $class;
		}

		return NULL;
	}
}