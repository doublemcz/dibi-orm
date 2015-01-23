<?php

namespace Doublemcz\Dibiorm;

class DataHelperLoader
{
	/**
	 * @param Manager $manager
	 * @param ClassMetadata $entityAttributes
	 * @param \DibiRow|FALSE $data
	 * @return mixed|NULL
	 */
	public static function createFlatClass(Manager $manager, ClassMetadata $entityAttributes, $data)
	{
		if (empty($data)) {
			return NULL;
		}

		$className = $entityAttributes->getClassName();
		$instance = new $className;
		self::loadClass($manager, $instance, $data, $entityAttributes);

		return $instance;
	}

	/**
	 * @param Manager $manager
	 * @param object $instance
	 * @param \DibiRow $data
	 * @param ClassMetadata $classMetadata
	 */
	public static function loadClass(Manager $manager, $instance, $data, ClassMetadata $classMetadata)
	{
		foreach ($classMetadata->getProperties() as $property => $columnAttributes) {
			if (!array_key_exists($property, $data)) {
				continue;
			}

			self::setPropertyValue($instance, $property, $data[$property]);
		}

		self::handleRelations($manager, $instance, $classMetadata, $data);
	}

	/**
	 * @param Manager $manager
	 * @param object $instance
	 * @param ClassMetadata $classMetadata
	 * @param \DibiRow $data
	 */
	public static function handleRelations($manager, $instance, ClassMetadata $classMetadata, $data)
	{
		self::handleRelationsOneToOne($manager, $instance, $classMetadata, $data);
		self::handleRelationsOneToMany($manager, $instance, $classMetadata);
		self::handleRelationsManyToMany($manager, $instance, $classMetadata);
	}

	/**
	 * @param ClassMetadata $classMetadata
	 * @param Manager $manager
	 * @param object $instance
	 * @param \DibiRow $data
	 */
	private static function handleRelationsOneToOne(Manager $manager, $instance, ClassMetadata $classMetadata, $data)
	{
		foreach ($classMetadata->getRelationsOneToOne() as $propertyName => $relationData) {
			$targetEntityAttributes = $manager->createClassMetadata($relationData['entity']);
			$proxyClass = self::createProxyClass($manager, $targetEntityAttributes, $instance, $propertyName);
			self::setPropertyValue($instance, $propertyName, $proxyClass);
			self::setPropertyValue($proxyClass, $relationData['join']['referenceColumn'], $data[$relationData['join']['column']]);
		}
	}

	/**
	 * @param Manager $manager
	 * @param object $instance
	 * @param ClassMetadata $classMetadata
	 */
	private static function handleRelationsOneToMany(Manager $manager, $instance, ClassMetadata $classMetadata)
	{
		foreach ($classMetadata->getRelationsOneToMany() as $propertyName => $relationData) {
			$targetEntityAttributes = $manager->createClassMetadata($relationData['entity']);
			self::setPropertyValue(
				$instance,
				$propertyName,
				new ResultCollection($instance, $manager, $targetEntityAttributes, $relationData)
			);
		}
	}

	/**
	 * @param Manager $manager
	 * @param object $instance
	 * @param ClassMetadata $classMetadata
	 */
	private static function handleRelationsManyToMany(Manager $manager, $instance, ClassMetadata $classMetadata)
	{
		foreach ($classMetadata->getRelationsManyToMany() as $propertyName => $relationData) {
			$targetEntityAttributes = $manager->createClassMetadata($relationData['entity']);
			self::setPropertyValue(
				$instance,
				$propertyName,
				new ResultCollection($instance, $manager, $targetEntityAttributes, $relationData)
			);
		}
	}

	/**
	 * @param Manager $manager
	 * @param ClassMetadata $classMetadata
	 * @param object $relationClassObject
	 * @param string $propertyName
	 * @return object
	 */
	public static function createProxyClass(Manager $manager, ClassMetadata $classMetadata, $relationClassObject, $propertyName)
	{
		$proxyPath = sprintf(
			'%s\%s.php',
			$manager->getProxiesPath(),
			str_replace('\\', '', $classMetadata->getClassName())
		);

		if (!is_file($proxyPath)) {
			self::createProxyClassFile($proxyPath, $classMetadata);
		}

		$proxyClassName = sprintf('doublemcz\dibiorm\proxies\%s', $classMetadata->getClassName());
		$proxyInstance = new $proxyClassName($manager, $relationClassObject, $propertyName);
		// Because __get cannot be used on public properties we must move them into array otherwise lazy loading on
		// @oneToOne is not working
		self::resetProxyProperties($proxyInstance, $classMetadata);

		return $proxyInstance;
	}

	public static function createProxyClassFile($proxyPath, ClassMetadata $classMetadata)
	{
		$classReflection = new \ReflectionClass($classMetadata->getClassName());
		$replaces = array(
			'CLASS_NAMESPACE' => $classReflection->getNamespaceName(),
			'CLASS_NAME' => $classReflection->getShortName(),
			'BASE_CLASS' => '\\' . $classReflection->getName(),
		);

		$proxyFileContent = file_get_contents(__DIR__ . '/Proxy.template');
		foreach ($replaces as $key => $value) {
			$proxyFileContent = str_replace('##' . $key . '##', $value, $proxyFileContent);
		}

		file_put_contents($proxyPath, $proxyFileContent);
		require_once($proxyPath);
	}

	/**
	 * @param object $proxyInstance
	 * @param ClassMetadata $classMetadata
	 */
	private static function resetProxyProperties($proxyInstance, ClassMetadata $classMetadata)
	{
		$properties = array();
		foreach (array_keys($classMetadata->getProperties()) as $propertyName) {
			unset($proxyInstance->$propertyName);
			$properties[$propertyName] = NULL;
		}

		self::setPropertyValue($proxyInstance, 'values', $properties);
	}

	/**
	 * @param object $instance
	 * @param string $property
	 * @param mixed $value
	 */
	public static function setPropertyValue($instance, $property, $value)
	{
		// TODO solve the bug when I get reflection from cache (one to one relation)
		$propertyReflection = new \ReflectionProperty(get_class($instance), $property);

		if (!$propertyReflection->isPublic()) {
			$propertyReflection->setAccessible(TRUE);
			$propertyReflection->setValue($instance, $value);
			$propertyReflection->setAccessible(FALSE);
		} else {
			$propertyReflection->setValue($instance, $value);
		}
	}

	/**
	 * @param object $instance
	 * @param string $propertyName
	 * @return mixed
	 */
	public static function getPropertyValue($instance, $propertyName)
	{
		$reflection = new \ReflectionProperty(get_class($instance), $propertyName);
		if (!$reflection->isPublic()) {
			$reflection->setAccessible(TRUE);
			$value = $reflection->getValue($instance);
		} else {
			$value = $reflection->getValue($instance);
		}

		return $value;
	}
}