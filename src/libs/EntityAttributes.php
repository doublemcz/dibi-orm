<?php

namespace doublemcz\dibiorm;

class EntityAttributes
{
	/** @var string */
	protected $className;
	/** @var string */
	protected $table;
	/** @var array */
	protected $columns;
	/** @var array */
	protected $primaryKey;
	/** @var string */
	protected $entityNamespace;
	/** @var string */
	protected $autoIncrementFieldName;
	/** @var array */
	protected $oneToMany = array();
	/** @var array */
	protected $oneToOne = array();

	/**
	 * @param string $className
	 * @throws DocParsingException
	 * @throws ClassNotFoundException
	 */
	public function __construct($className)
	{
		if (!class_exists($className)) {
			throw new ClassNotFoundException(sprintf('Class "%s" not found.', $className));
		}

		$this->className = $className;
		$tableAttributes = $this->getTableAttributes($className);
		if (empty($tableAttributes['table']) || empty($tableAttributes['table']['name'])) {
			throw new DocParsingException(sprintf('Table name is missing for entity %s. Did you forget @table(name="something")?', $className));
		}

		$this->table = $tableAttributes['table']['name'];
		$this->className = $className;
		$this->findColumns($className);
		if (empty($this->columns)) {
			throw new DocParsingException('You have to specify at least one column. Did you forget to specify @column in some class property? ', $className);
		}

		if (empty($this->primaryKey)) {
			throw new DocParsingException('You have to specify primary key. Did you forget to specify @primaryKey?');
		}
	}

	private function getTableAttributes($entityName)
	{
		$reflection = new \ReflectionClass($entityName);
		$doc = $reflection->getDocComment();

		return $this->parseDoc($doc);
	}

	private function findColumns($entityName)
	{
		$reflection = new \ReflectionClass($entityName);
		foreach ($reflection->getProperties() as $property) {
			$propertyReflection = new \ReflectionProperty($reflection->getName(), $property->getName());
			$attributeProperties = $this->parseDoc($propertyReflection->getDocComment());
			if (array_key_exists('column', $attributeProperties)) {
				$this->columns[$property->getName()] = array();
			}

			if (array_key_exists('primaryKey', $attributeProperties)) {
				$this->primaryKey[] = $property->getName();
			}

			if (array_key_exists('autoIncrement', $attributeProperties)) {
				$this->autoIncrementFieldName = $property->getName();
			}

			$this->findRelations($property, $attributeProperties);
		}
	}

	/**
	 * @param \ReflectionProperty $property
	 * @param $attributeProperties
	 */
	protected function findRelations(\ReflectionProperty $property, $attributeProperties)
	{
		$this->handleOneToXRelations($property, $attributeProperties);
	}

	/**
	 * @param \ReflectionProperty $property
	 * @param $attributeProperties
	 * @throws DocParsingException
	 */
	protected function handleOneToXRelations(\ReflectionProperty $property, $attributeProperties)
	{
		$oneToX = array_key_exists('oneToMany', $attributeProperties)
			? $attributeProperties['oneToMany']
			: array_key_exists('oneToOne', $attributeProperties)
				? $attributeProperties['oneToOne']
				: FALSE;

		if ($oneToX) {
			if (empty($attributeProperties[$oneToX]['entity'])) {
				throw new DocParsingException(
					sprintf(
						'You set property "%s" as %s but the entity attribute is missing. You have to specify entity attribute like this @%s(entity="EntityName").',
						$property->getName(), $oneToX, $oneToX
					)
				);
			}

			if (!array_key_exists('join', $attributeProperties)) {
				throw new DocParsingException(
					sprintf('You set property "%s" as %s but no join is specified. Did you forget to set @join?', $property->getName(), $oneToX)
				);
			}

			$this->$oneToX[$property->getName()] = array(
				'property' => $property->getName(),
				'entity' => $attributeProperties[$oneToX]['entity'],
				'join' => $attributeProperties['join'],
				'staticJoin' => !empty($attributeProperties['staticJoin']) ? $attributeProperties['staticJoin'] : array(),
			);
		}
	}

	/**
	 * @param string $docRaw
	 * @return array
	 * @throws DocParsingException
	 */
	protected function parseDoc($docRaw)
	{
		if (empty($docRaw)) {
			throw new DocParsingException('Class PHP Doc is empty.');
		}

		$result = array();
		$lines = explode("\n", $docRaw);
		foreach ($lines as $line) {
			preg_match('~\s+\*\s@([a-zA-Z]+)(.+)?~', $line, $matches);
			if (!empty($matches)) {
				if (!empty($matches[2])) {
					$result[$matches[1]] = $this->parseDocParameters(trim($matches[2]));
				} else {
					$result[$matches[1]] = NULL;
				}
			}
		}

		return $result;
	}

	/**
	 * @param $docParametersString
	 * @return array|string
	 */
	protected function parseDocParameters($docParametersString)
	{
		if (0 === strpos($docParametersString, '(')) {
			// Cut parentheses
			$docParametersString = preg_replace('~\((.+?)\)~', '$1', $docParametersString);
			preg_match_all('~(.+?)=[\'"](.+?)[\'"](,\s?)?~', $docParametersString, $matches);
			$result = array();
			if (!empty($matches[1])) {
				foreach ($matches[1] as $key => $match) {
					$result[$match] = $matches[2][$key];
				}
			}

			return $result;
		} else {
			return $docParametersString;
		}
	}

	/**
	 * @return array
	 */
	public function getPrimaryKey()
	{
		return $this->primaryKey;
	}

	/**
	 * @return string
	 */
	public function getTable()
	{
		return $this->table;
	}

	/**
	 * @return array
	 */
	public function getProperties()
	{
		return $this->columns;
	}

	/**
	 * @return string
	 */
	public function getClassName()
	{
		return $this->className;
	}

	/**
	 * @return string
	 */
	public function getAutoIncrementFieldName()
	{
		return $this->autoIncrementFieldName;
	}

	/**
	 * @return array
	 */
	public function getRelationsOneToOne()
	{
		return $this->oneToOne;
	}

	/**
	 * @return array
	 */
	public function getRelationsOneToMany()
	{
		return $this->oneToMany;
	}
}