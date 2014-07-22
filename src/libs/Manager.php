<?php

namespace doublemcz\dibiorm;

class Manager {
	/** @var \DibiConnection */
	protected $dibiConnection;

	/** @var string */
	protected $entityNamespace = NULL;

	public function __construct($parameters, $cacheStorage)
	{
		$this->dibiConnection = new \DibiConnection($parameters['database']);
		if (!empty($parameters['entityNamespace'])) {
			$this->entityNamespace = $parameters['entityNamespace'];
		}
	}

	/**
	 * Finds an entity by given id. For multiple primary key you can pass next parameters by order definition in your entity.
	 *
	 * @param string $entityName
	 * @param mixed $id
	 * @return mixed
	 */
	public function find($entityName, $id)
	{
		$this->handleConnection();
		$entityAttributes = $this->createEntityAttributes($entityName);
		$args = func_get_args();
		unset($args[0]);
		$data = $this->dibiConnection->select(array_keys($entityAttributes->getColumns()))
			->from($entityAttributes->getTable())
			->where(array_combine($entityAttributes->getPrimaryKey(), array_values($args)))
			->fetch();

		return $this->createFlatClass($entityAttributes, $data);
	}

	protected function createEntityAttributes($entityName)
	{
		return new EntityAttributes(
			$this->entityNamespace
			? ($this->entityNamespace . '\\' . $entityName)
			: $entityName
		);
	}

	/**
	 * @param EntityAttributes $entityAttributes
	 * @param array $data
	 * @return mixed|NULL
	 */
	protected function createFlatClass(EntityAttributes $entityAttributes, $data)
	{
		if (empty($data)) {
			return NULL;
		}

		$className = $entityAttributes->getClassName();
		$instance = new $className;
		foreach ($entityAttributes->getColumns() as $column => $columnAttributes) {
			if (empty($data->$column)) {
				continue;
			}

			$reflection = new \ReflectionProperty($instance, $column);
			if (!$reflection->isPublic()) {
				$reflection->setAccessible(TRUE);
				$reflection->setValue($data->$column);
				$reflection->setAccessible(FALSE);
			} else {
				$reflection->setValue($instance, $data->$column);
			}
		}


		f($instance);
	}

	public function createQuery()
	{
		return new QueryBuilder($this);
	}

	/**
	 * @return \DibiConnection
	 */
	public function getDibiConnection()
	{
		return $this->dibiConnection;
	}

	private function handleConnection()
	{
		if (!$this->dibiConnection->isConnected()) {
			$this->dibiConnection->connect();
		}
	}

	/**
	 * @param string $namespace
	 */
	public function setEntityNamespace($namespace)
	{
		$this->entityNamespace = $namespace;
	}
}