<?php

namespace doublemcz\dibiorm;

class DataHelperLoader {
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
		foreach ($entityAttributes->getProperties() as $property => $columnAttributes) {
			if (empty($data->$property)) {
				continue;
			}

			self::setPropertyValue($instance, $property, $data->$property);
		}

		self::handleRelations($manager, $instance, $entityAttributes);

		return $instance;
	}

	/**
	 * @param Manager $manager
	 * @param object $instance
	 * @param EntityAttributes $entityAttributes
	 */
	public static function handleRelations($manager, $instance, EntityAttributes $entityAttributes)
	{
		foreach ($entityAttributes->getRelationsOneToMany() as $propertyName => $relation) {
			$targetPropertyAttributes = new EntityAttributes($manager->getEntityClassName($relation['entity']));
			self::setPropertyValue($instance, $propertyName, new ResultCollection($manager, $targetPropertyAttributes));
		}
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
			$reflection->setValue($instance, $value);
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