<?php

namespace doublemcz\dibiorm;

class DataHelperLoader
{
	/**
	 * @param Manager $manager
	 * @param EntityAttributes $entityAttributes
	 * @param \DibiRow|FALSE $data
	 * @return mixed|NULL
	 */
	public static function createFlatClass(Manager $manager, EntityAttributes $entityAttributes, $data)
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
	 * @param EntityAttributes $entityAttributes
	 */
	public static function loadClass(Manager $manager, $instance, $data, EntityAttributes $entityAttributes)
	{
		foreach ($entityAttributes->getProperties() as $property => $columnAttributes) {
			if (empty($data->$property)) {
				continue;
			}

			self::setPropertyValue($instance, $property, $data->$property);
		}

		self::handleRelations($manager, $instance, $entityAttributes);
	}

	/**
	 * @param Manager $manager
	 * @param object $instance
	 * @param EntityAttributes $entityAttributes
	 */
	public static function handleRelations($manager, $instance, EntityAttributes $entityAttributes)
	{
		foreach ($entityAttributes->getRelationsOneToMany() as $propertyName => $relation) {
			$targetEntityAttributes = new EntityAttributes($manager->getEntityClassName($relation['entity']));
			self::setPropertyValue($instance, $propertyName, new ResultCollection($manager, $targetEntityAttributes));
		}

		foreach ($entityAttributes->getRelationsOneToOne() as $propertyName => $relation) {
			$targetEntityAttributes = new EntityAttributes($manager->getEntityClassName($relation['entity']));
			$proxyClass = self::createProxyClass($manager, $targetEntityAttributes);
			self::setPropertyValue($instance, $propertyName, $proxyClass);
			$joinMap = array();

			$joinMap[$relation['join']['referenceColumn']] = self::getPropertyValue($instance, $relation['join']['column']);
			if (!empty($relation['staticJoin'])) {
				$joinMap[$relation['staticJoin']['column']] = $relation['staticJoin']['value'];
			}

			self::setPropertyValue($proxyClass, 'joiningMap', $joinMap);
		}
	}

	/**
	 * @param Manager $manager
	 * @param EntityAttributes $targetEntityAttributes
	 */
	public static function createProxyClass(Manager $manager, EntityAttributes $targetEntityAttributes)
	{
		$proxyPath = sprintf(
			'%s\%s.php',
			$manager->getProxiesPath(),
			str_replace('\\', '', $targetEntityAttributes->getClassName())
		);

		if (!is_file($proxyPath)) {
			self::createProxyClassFile($proxyPath, $targetEntityAttributes);
		}

		$proxyClassName = sprintf('doublemcz\dibiorm\proxies\%s', $targetEntityAttributes->getClassName());

		return new $proxyClassName($manager);
	}

	public static function createProxyClassFile($proxyPath, EntityAttributes $targetEntityAttributes)
	{
		$classReflection = new \ReflectionClass($targetEntityAttributes->getClassName());
		$replaces = array(
			'CLASS_NAMESPACE' => $classReflection->getNamespaceName(),
			'CLASS_NAME' => $classReflection->getShortName(),
			'ORIGIN_CLASS_NAME' => $classReflection->getName(),
		);
		$proxyFileContent = file_get_contents(__DIR__ . '/Proxy.template');
		foreach ($replaces as $key => $value) {
			$proxyFileContent = str_replace('##' . $key . '##', $value, $proxyFileContent);
		}

		file_put_contents($proxyPath, $proxyFileContent);
		require_once($proxyPath);
	}

	/**
	 * @param object $instance
	 * @param string $property
	 * @param mixed $value
	 */
	public static function setPropertyValue($instance, $property, $value)
	{
		$reflection = new \ReflectionProperty($instance, $property);
		if (!$reflection->isPublic()) {
			$reflection->setAccessible(TRUE);
			$reflection->setValue($instance, $value);
			$reflection->setAccessible(FALSE);
		} else {
			$instance->{$property} = $value;
		}
	}

	/**
	 * @param object $instance
	 * @param string $propertyName
	 * @return mixed
	 */
	public static function getPropertyValue($instance, $propertyName)
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
}