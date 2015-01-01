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
	/** @var string */
	protected $proxiesPath;
	/** @var object */
	protected $cacheStorage;

	public function __construct($parameters, $cacheStorage)
	{
		if ($parameters['database'] instanceof \DibiConnection) {
			$this->dibiConnection = $parameters['database'];
		} elseif (is_array($parameters['database'])) {
			$this->dibiConnection = new \DibiConnection($parameters['database']);
		} else {
			throw new \InvalidArgumentException('You must pass a DibiConnection or array with db parameters in "database" parameter.');
		}

		if (empty($parameters['proxiesPath']) || (!is_dir($parameters['proxiesPath']) && !mkdir($parameters['proxiesPath'], 0777, TRUE))) {
			throw new MissingArgumentException('You have to set valid proxy path. It\'s parameter proxiesPath');
		} else {
			$this->proxiesPath = $parameters['proxiesPath'];
		}

		if (!empty($parameters['entityNamespace'])) {
			$this->entityNamespace = $parameters['entityNamespace'];
		}

		$this->autoLoadProxies();
		$this->cacheStorage = $cacheStorage;
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
		$entityAttributes = $this->createClassMetadata($entityName);
		$args = func_get_args();
		unset($args[0]);
		if (count($entityAttributes->getPrimaryKey()) != count(array_values($args))) {
			throw new \RuntimeException('You are trying to find and entity with full primary key. Did you forget to specify an another value as an argument?');
		}

		$primaryKey = array_combine($entityAttributes->getPrimaryKey(), array_values($args));
		$data = $this->dibiConnection->select(array_keys($entityAttributes->getProperties()))
			->from($entityAttributes->getTable())
			->where($primaryKey)
			->fetch();

		$instance = DataHelperLoader::CreateFlatClass($this, $entityAttributes, $data);
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
			throw new \RuntimeException('Given value is not an object.');
		}

		$entityAttributes = $this->createClassMetadata($entity);
		$this->registerClass($entity, $entityAttributes, self::FLAG_INSTANCE_INSERT);
	}

	/**
	 * @param object $entity
	 * @throws \RuntimeException
	 */
	public function delete($entity)
	{
		if (!is_object($entity)) {
			throw new \RuntimeException('Given value is not an object');
		}

		$classKey = $this->getEntityClassHashKey($entity);
		if (FALSE === array_key_exists($classKey, $this->managedClasses)) {
			throw new \RuntimeException('You are trying to delete an entity that is not persisted. Did you fetch it from database?');
		}

		$this->managedClasses[$classKey]['flag'] = self::FLAG_INSTANCE_DELETE;
	}

	/**
	 * @param object $instance
	 * @param ClassMetadata $classMetadata
	 * @return string
	 */
	public function getEntityClassHashKey($instance, ClassMetadata $classMetadata = NULL)
	{
		if (!$classMetadata) {
			$classMetadata = $this->createClassMetadata($instance);
		}

		$primaryKey = $this->buildPrimaryKey($instance, $classMetadata);

		return md5(get_class($instance) . serialize($primaryKey));
	}

	/**
	 * @param object $instance
	 */
	public function flush($instance = NULL)
	{
		if ($instance) {
			$classContainer = $this->getInstanceFromManagedClasses($instance);
			$this->processInstanceChanges(
				$instance,
				$classContainer['flag'],
				!empty($classContainer['valueHash']) ? $classContainer['valueHash'] : NULL
			);
		} else {
			foreach ($this->managedClasses as $class) {
				$this->processInstanceChanges($class['instance'], $class['flag'], $class['valueHash']);
			}
		}
	}

	/**
	 * @param string $entityName And identifier like 'User', 'Article', etc...
	 * @return RepositoryManager
	 */
	public function getRepository($entityName)
	{
		return new RepositoryManager($this, $this->createClassMetadata($entityName));
	}

	/**
	 * @param object $instance
	 * @return array
	 * @throws \RuntimeException
	 */
	private function getInstanceFromManagedClasses($instance)
	{
		$newItemClassKey = spl_object_hash($instance);
		$classKey = $this->getEntityClassHashKey($instance);
		if (!array_key_exists($newItemClassKey, $this->managedClasses)
			&& !array_key_exists($classKey, $this->managedClasses)
		) {
			throw new \RuntimeException('You try to get instance of a class that is not managed');
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
		$entityAttributes = $this->createClassMetadata($instance);
		switch ($flag) {
			case self::FLAG_INSTANCE_INSERT :
				return $this->insertItem($instance, $entityAttributes);
			case self::FLAG_INSTANCE_DELETE :
				return $this->deleteItem($instance, $entityAttributes);
			case self::FLAG_INSTANCE_UPDATE :
				return $this->updateItem($instance, $entityAttributes, $valueHash);
			default:
				throw new \RuntimeException(sprintf('Unknown flag action. Given %s' . $flag ?: ' NULL'));
		}
	}

	/**
	 * @param object $instance
	 * @param ClassMetadata $classMetadata
	 * @return \DibiResult|int
	 */
	private function deleteItem($instance, ClassMetadata $classMetadata)
	{
		$affectedRows = $this->dibiConnection
			->delete($classMetadata->getTable())
			->where($this->buildPrimaryKey($instance, $classMetadata))
			->execute(\dibi::AFFECTED_ROWS);

		$classKey = $this->getEntityClassHashKey($instance);
		unset($this->managedClasses[$classKey]);

		return $affectedRows;
	}

	/**
	 * @param object $instance
	 * @param ClassMetadata $entityAttributes
	 * @throws \RuntimeException
	 * @return \DibiResult|int
	 */
	private function insertItem($instance, ClassMetadata $entityAttributes)
	{
		if ($entityAttributes->hasBeforeCreateEvent()) {
			$instance->beforeCreateEvent($this);
		}

		$values = $this->getInstanceValueMap($instance, $entityAttributes);
		$insertId = $this->dibiConnection->insert($entityAttributes->getTable(), $values)->execute(\dibi::IDENTIFIER);
		if ($entityAttributes->getAutoIncrementFieldName()) {
			if (!$insertId) {
				throw new \RuntimeException('Entity has set autoIncrement flag but no incremented values was returned from DB.');
			}

			DataHelperLoader::setPropertyValue($instance, $entityAttributes->getAutoIncrementFieldName(), $insertId);
		}

		// Unset origin class hash and set new one by primary key
		$hash = spl_object_hash($instance);
		if (array_key_exists($hash, $this->managedClasses)) {
			unset($this->managedClasses[$hash]);
		}

		$classKey = $this->getEntityClassHashKey($instance, $entityAttributes);
		$this->managedClasses[$classKey]['instance'] = $instance;
		$this->managedClasses[$classKey]['flag'] = self::FLAG_INSTANCE_UPDATE;
		$this->managedClasses[$classKey]['valueHash'] = $this->getInstanceValuesHash($instance, $entityAttributes);

		return $insertId;
	}

	/**
	 * @param object $instance
	 * @param ClassMetadata $entityAttributes
	 * @param string $originValueHash
	 * @return bool
	 */
	private function updateItem($instance, ClassMetadata $entityAttributes, $originValueHash)
	{
		if ($originValueHash == $this->getInstanceValuesHash($instance, $entityAttributes)) {
			return FALSE;
		}

		if ($entityAttributes->hasBeforeUpdateEvent()) {
			$instance->beforeUpdateEvent($this);
		}

		$values = $this->getInstanceValueMap($instance, $entityAttributes);

		return $this->dibiConnection->update($entityAttributes->getTable(), $values)->where($this->buildPrimaryKey($instance, $entityAttributes))->execute(\dibi::AFFECTED_ROWS) == 1;
	}

	/**
	 * @param object $instance
	 * @param ClassMetadata $entityAttributes
	 * @return array
	 */
	private function getInstanceValueMap($instance, ClassMetadata $entityAttributes)
	{
		$values = array();
		foreach (array_keys($entityAttributes->getProperties()) as $propertyName) {
			$value = DataHelperLoader::getPropertyValue($instance, $propertyName);
			$values[$propertyName] = $this->translateValue($value);;
		}

		return $values;
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	private function translateValue($value)
	{
		if (is_object($value)) {
			if (get_class($value) == "DateTime" || is_subclass_of($value, 'DateTime')) {
				$value = $value->format('Y-m-d H:i:s');
			} else {
				// Try to translate into string
				$value = (string)$value;
			}
		}

		return $value;
	}

	/**
	 * @param object $instance
	 * @param ClassMetadata $entityAttributes
	 * @param int $flag
	 */
	public function registerClass($instance, ClassMetadata $entityAttributes, $flag)
	{
		if ($flag == self::FLAG_INSTANCE_INSERT) {
			$hashedKey = spl_object_hash($instance);
		} else {
			$hashedKey = $this->getEntityClassHashKey($instance);
		}

		$this->managedClasses[$hashedKey] = array(
			'instance' => $instance,
			'valueHash' => $this->getInstanceValuesHash($instance, $entityAttributes),
			'flag' => $flag,
		);
	}

	/**
	 * @param object $instance
	 * @param ClassMetadata $entityAttributes
	 * @return string
	 */
	protected function getInstanceValuesHash($instance, ClassMetadata $entityAttributes)
	{
		$values = array();
		foreach (array_keys($entityAttributes->getProperties()) as $propertyName) {
			$value = DataHelperLoader::getPropertyValue($instance, $propertyName);
			$values[] = $this->translateValue($value);
		}

		return md5(serialize($values));
	}

	/**
	 * @param object $instance
	 * @param ClassMetadata $entityAttributes
	 * @return array
	 */
	protected function buildPrimaryKey($instance, ClassMetadata $entityAttributes)
	{
		$primaryKey = $entityAttributes->getPrimaryKey();
		$values = array();
		foreach ($primaryKey as $propertyName) {
			$values[] = DataHelperLoader::getPropertyValue($instance, $propertyName);
		}

		return array_combine($entityAttributes->getPrimaryKey(), $values);
	}

	/**
	 * Returns instance of EntityAttributes based on given argument
	 *
	 * @param string|object $entityName Can be name of the class of instance itself
	 * @return ClassMetadata
	 */
	public function createClassMetadata($entityName)
	{
		if (is_object($entityName)) {
			$interfaces = class_implements($entityName);
			if (in_array('doublemcz\dibiorm\IProxy', $interfaces)) {
				$className = get_class($entityName->getClassInstance());
			} else {
				$className = get_class($entityName);
			}
		} else {
			$className = $this->getEntityClassName($entityName);
		}

		// TODO add memory storage for code run

		if ($this->cacheStorage) {
			return $this->cacheStorage->load($className, function () use ($className) {
				return new ClassMetadata($className);
			});
		}


		return new ClassMetadata($className);
	}

	public function createProxy($className)
	{
		if (!class_exists($className)) {
			throw new ClassNotFoundException('You have to pass valid class name');
		}
	}

	/**
	 * @param string|object $entityName
	 * @return string
	 */
	public function getEntityClassName($entityName)
	{
		$className = $this->entityNamespace
			? ($this->entityNamespace . '\\' . $entityName)
			: $entityName;

		return $className;
	}

	private function autoLoadProxies()
	{
		if ($handle = opendir($this->proxiesPath)) {
			while (FALSE !== ($entry = readdir($handle))) {
				if (FALSE !== strpos($entry, '.php')) {
					require_once($this->proxiesPath . DIRECTORY_SEPARATOR . $entry);
				}
			}

			closedir($handle);
		}
	}

	public function loadProxy(IProxy $proxy)
	{
		$joiningColumns = $proxy->getJoiningMap();
		if (empty($joiningColumns)) {
			throw new \RuntimeException('Joining columns cannot be empty.');
		}

		$entityAttributes = $this->createClassMetadata($proxy);
		$data = $this->dibiConnection->select(array_keys($entityAttributes->getProperties()))
			->from($entityAttributes->getTable())
			->where($joiningColumns)
			->fetch();

		if (!empty($data)) {
			DataHelperLoader::loadClass($this, $proxy->getClassInstance(), $data, $entityAttributes);
		}
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

	/**
	 * @return string
	 */
	public function getProxiesPath()
	{
		return $this->proxiesPath;
	}
}