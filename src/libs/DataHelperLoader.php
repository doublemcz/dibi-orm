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
	 * @param ClassMetadata $entityAttributes
	 */
	public static function loadClass(Manager $manager, $instance, $data, ClassMetadata $entityAttributes)
	{
		foreach ($entityAttributes->getProperties() as $property => $columnAttributes) {
			if (!array_key_exists($property, $data)) {
				continue;
			}

			$entityAttributes->getPropertyReflection($property)->setValue($instance, $data[$property]);
		}

		self::handleRelations($manager, $instance, $entityAttributes);
	}

	/**
	 * @param Manager $manager
	 * @param object $instance
	 * @param ClassMetadata $entityAttributes
	 */
	public static function handleRelations($manager, $instance, ClassMetadata $entityAttributes)
	{
		foreach ($entityAttributes->getRelationsOneToMany() as $propertyName => $relation) {
			$targetEntityAttributes = $manager->createClassMetadata($relation['entity']);
			self::setPropertyValue(
				$instance,
				$propertyName,
				new ResultCollection($manager, $targetEntityAttributes)
			);
		}

		foreach ($entityAttributes->getRelationsOneToOne() as $propertyName => $relation) {
			$targetEntityAttributes = $manager->createClassMetadata($relation['entity']);
			$proxyClass = self::createProxyClass($manager, $targetEntityAttributes);
			self::setPropertyValue(
				$instance,
				$propertyName,
				$proxyClass,
				$targetEntityAttributes->getPropertyReflection($propertyName)
			);
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
	 * @param ClassMetadata $targetEntityAttributes
	 */
	public static function createProxyClass(Manager $manager, ClassMetadata $targetEntityAttributes)
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
		$entityClassName = $targetEntityAttributes->getClassName();

		return new $proxyClassName($manager, new $entityClassName());
	}

	public static function createProxyClassFile($proxyPath, ClassMetadata $targetEntityAttributes)
	{
		$classReflection = new \ReflectionClass($targetEntityAttributes->getClassName());
		$replaces = array(
			'CLASS_NAMESPACE' => $classReflection->getNamespaceName(),
			'CLASS_NAME' => $classReflection->getShortName(),
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
	 * @param \ReflectionProperty $propertyReflection
	 */
	public static function setPropertyValue($instance, $property, $value, \ReflectionProperty $propertyReflection = NULL)
	{
		if (!$propertyReflection)
			$propertyReflection = new \ReflectionProperty($instance, $property);

		if (!$propertyReflection->isPublic()) {
			$propertyReflection->setAccessible(true);
			$propertyReflection->setValue($instance, $value);
			$propertyReflection->setAccessible(false);
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
		$reflection = new \ReflectionProperty($instance, $propertyName);
		if (!$reflection->isPublic()) {
			$reflection->setAccessible(TRUE);
			$value = $reflection->getValue($instance);
		} else {
			$value = $reflection->getValue($instance);
		}

		return $value;
	}
}