<?php

namespace doublemcz\dibiorm;

class RepositoryManager
{
	/** @var Manager */
	protected $manager;
	/** @var string */
	protected $entityClassName;
	/** @var ClassMetadata */
	protected $classMetadata;

	/**
	 * @param Manager $manager
	 * @param $classMetadata
	 */
	public function __construct(Manager $manager, ClassMetadata $classMetadata)
	{
		$this->manager = $manager;
		$this->classMetadata = $classMetadata;
	}

	/**
	 * @param array $where
	 * @param array $orderBy
	 * @return object|NULL
	 */
	public function findOneBy($where = array(), $orderBy = array())
	{
		return $this->manager->findOneBy($this->classMetadata->getEntityName(), $where, $orderBy);
	}

	/**
	 * @param mixed $id
	 * @return object|NULL
	 */
	public function find($id)
	{
		// TODO solve possibility to call with more column in primary key
		return $this->manager->find($this->classMetadata->getEntityName(), $id);
	}

	/**
	 * @param array $where
	 * @param array $orderBy
	 * @return array
	 */
	public function findBy($where = array(), $orderBy = array())
	{
		return $this->manager->findBy($this->classMetadata, $where, $orderBy);
	}
}