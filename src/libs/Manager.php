<?php

namespace doublemcz\dibiorm;

class Manager
{
	const FLAG_INSTANCE_INSERT = 1;
	const FLAG_INSTANCE_DELETE = 2;
	const FLAG_INSTANCE_UPDATE = 3;

	/** @var \DibiConnection */
	protected $dibiConnection;
	/** @var string */
	protected $entityNamespace = NULL;
	/** @var array */
	protected $managedClasses = array();

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
	 * @throws \RuntimeException
	 * @return mixed
	 */
	public function find($entityName, $id)
	{
		$this->handleConnection();
		$entityAttributes = $this->createEntityAttributes($entityName);
		$args = func_get_args();
		unset($args[0]);
		if (count($entityAttributes->getPrimaryKey()) != count(array_values($args))) {
			throw new \RuntimeException('You try to find and entity with full primary key. Did you forget to specify an another value as an argument?');
		}

		$primaryKey = array_combine($entityAttributes->getPrimaryKey(), array_values($args));
		$data = $this->dibiConnection->select(array_keys($entityAttributes->getProperties()))
			->from($entityAttributes->getTable())
			->where($primaryKey)
			->fetch();

		$instance = $this->createFlatClass($entityAttributes, $data);
		if ($instance) {
			$this->registerClass($instance, $entityAttributes, self::FLAG_INSTANCE_UPDATE);
		}

		return $instance;
	}

	/**
	 * @param object $entity
	 * @throws \RuntimeException
	 */
	public function persist($entity)
	{
		if (!is_object($entity)) {
			throw new \RuntimeException('Given value is not an object');
		}

		$entityAttributes = $this->createEntityAttributes($entity);
		$this->registerClass($entity, $entityAttributes, self::FLAG_INSTANCE_INSERT);
	}

	public function flush($instance = NULL)
	{
		if ($instance) {
			$classContainer = $this->getInstanceFromManagedClasses($instance);
			$this->processInstanceChanges(
				$instance,
				$classContainer['flag'],
				!empty($classContainer['valueHash'])
					? $classContainer['valueHash']
					: NULL
			);
		} else {
			foreach ($this->managedClasses as $class) {
				$this->processInstanceChanges($class['instance'], $class['flag'], $class['valueHash']);
			}
		}
	}

	private function getInstanceFromManagedClasses($instance)
	{
		$entityAttributes = $this->createEntityAttributes($instance);
		$classKey = $this->buildClassKey($instance, $entityAttributes);
		if (!array_key_exists($classKey, $this->managedClasses)) {
			throw new \RuntimeException('You try to get instance flag of class that is not managed');
		}

		return $this->managedClasses[$classKey];
	}

	/**
	 * @param object $instance
	 * @param int $flag
	 * @param null $valueHash
	 * @return mixed
	 * @throws \RuntimeException
	 */
	private function processInstanceChanges($instance, $flag, $valueHash = NULL)
	{
		$entityAttributes = $this->createEntityAttributes($instance);
		switch ($flag) {
			case self::FLAG_INSTANCE_INSERT :
				return $this->insertItem($instance, $entityAttributes);
			case self::FLAG_INSTANCE_DELETE :
				return $this->deleteItem($instance, $entityAttributes);
			case self::FLAG_INSTANCE_UPDATE :
				return $this->updateItem($instance, $entityAttributes, $valueHash);
			default:
				throw new \RuntimeException(sprintf('Unknown flag action. Given %s' . $flag ? : ' NULL'));
		}
	}

	/**
	 * @param object $instance
	 * @param EntityAttributes $entityAttributes
	 * @throws \RuntimeException
	 * @return \DibiResult|int
	 */
	private function insertItem($instance, EntityAttributes $entityAttributes)
	{
		$values = $this->getInstanceValueMap($instance, $entityAttributes);
		$insertId = $this->dibiConnection->insert($entityAttributes->getTable(), $values)->execute(\dibi::IDENTIFIER);
		if ($entityAttributes->getAutoIncrementFieldName()) {
			if (!$insertId) {
				throw new \RuntimeException('Entity has set autoIncrement flag but no incremented values was returned from DB.');
			}

			$this->setPropertyValue($instance, $entityAttributes->getAutoIncrementFieldName(), $insertId);
		}

		$classKey = $this->buildClassKey($instance, $entityAttributes);
		$this->managedClasses[$classKey]['flag'] = self::FLAG_INSTANCE_UPDATE;
		$this->managedClasses[$classKey]['valueHash'] = $this->getInstanceValuesHash($insertId, $entityAttributes);

		return $insertId;
	}

	private function deleteItem($instance, EntityAttributes $entityAttributes)
	{
		throw new \Exception('Not implemented');
	}

	/**
	 * @param object $instance
	 * @param EntityAttributes $entityAttributes
	 * @param string $valueHash
	 * @return bool
	 */
	private function updateItem($instance, EntityAttributes $entityAttributes, $valueHash)
	{
		if ($valueHash == $this->getInstanceValuesHash($instance, $entityAttributes)) {
			return FALSE;
		}

		$values = $this->getInstanceValueMap($instance, $entityAttributes);

		return $this->dibiConnection->update($entityAttributes->getTable(), $values)->execute(\dibi::AFFECTED_ROWS) == 1;
	}

	private function getInstanceValueMap($instance, EntityAttributes $entityAttributes)
	{
		$values = array();
		foreach (array_keys($entityAttributes->getProperties()) as $propertyName) {
			$values[$propertyName] = (string)$this->getPropertyValue($instance, $propertyName);
		}

		return $values;
	}

	/**
	 * @param EntityAttributes $entityAttributes
	 * @param \DibiRow|FALSE $data
	 * @return mixed|NULL
	 */
	protected function createFlatClass(EntityAttributes $entityAttributes, $data)
	{
		if (empty($data)) {
			return NULL;
		}

		$className = $entityAttributes->getClassName();
		$instance = new $className;
		foreach ($entityAttributes->getProperties() as $property => $columnAttributes) {
			if (empty($data->$property)) {
				continue;
			}

			$this->setPropertyValue($instance, $property, $data->$property);
		}

		return $instance;
	}

	protected function registerClass($instance, EntityAttributes $entityAttributes, $flag)
	{
		$hashedKey = $this->buildClassKey($instance, $entityAttributes);
		if (array_key_exists($hashedKey, $this->managedClasses)) {
			throw new \RuntimeException('Given class has been already registered.');
		}

		$this->managedClasses[$hashedKey] = array(
			'instance' => $instance,
			'valueHash' => $this->getInstanceValuesHash($instance, $entityAttributes),
			'flag' => $flag,
		);
	}

	/**
	 * @param object $instance
	 * @param EntityAttributes $entityAttributes
	 * @return string
	 */
	private function buildClassKey($instance, EntityAttributes $entityAttributes)
	{
		$key = $this->buildPrimaryKey($instance, $entityAttributes);
		return md5($entityAttributes->getClassName() . '|' . serialize($key));
	}

	/**
	 * @param object $instance
	 * @param EntityAttributes $entityAttributes
	 * @return string
	 */
	protected function getInstanceValuesHash($instance, EntityAttributes $entityAttributes)
	{
		$values = array();
		foreach ($entityAttributes->getProperties() as $property => $parameters) {
			$values[] = (string)$this->getPropertyValue($instance, $property);
		}

		return md5(serialize($values));
	}

	/**
	 * @param object $instance
	 * @param EntityAttributes $entityAttributes
	 * @return array
	 */
	protected function buildPrimaryKey($instance, EntityAttributes $entityAttributes)
	{
		$primaryKey = $entityAttributes->getPrimaryKey();
		$values = array();
		foreach ($primaryKey as $propertyName) {
			$values[] = $this->getPropertyValue($instance, $propertyName);
		}

		return array_combine($entityAttributes->getPrimaryKey(), $values);
	}

	/**
	 * @param object $instance
	 * @param string $propertyName
	 * @return mixed
	 */
	private function getPropertyValue($instance, $propertyName)
	{
		$reflection = new \ReflectionProperty($instance, $propertyName);
		if (!$reflection->isPublic()) {
			$reflection->setAccessible(TRUE);
			$value = $reflection->getValue($instance);
			$reflection->setAccessible(FALSE);
		} else {
			$value = $reflection->getValue($instance);
		}

		return $value;
	}

	/**
	 * @param object $instance
	 * @param string $property
	 * @param mixed $value
	 */
	private function setPropertyValue($instance, $property, $value)
	{
		$reflection = new \ReflectionProperty($instance, $property);
		if (!$reflection->isPublic()) {
			$reflection->setAccessible(TRUE);
			$reflection->setValue($value);
			$reflection->setAccessible(FALSE);
		} else {
			$reflection->setValue($instance, $value);
		}
	}

	/**
	 * Returns instance of EntityAttributes based on given argument
	 *
	 * @param string|object $entityName Can be name of the class of instance itself
	 * @return EntityAttributes
	 */
	protected function createEntityAttributes($entityName)
	{
		if (is_object($entityName)) {
			$className = get_class($entityName);
		} else {
			$className = $this->entityNamespace
				? ($this->entityNamespace . '\\' . $entityName)
				: $entityName;
		}

		return new EntityAttributes($className);
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