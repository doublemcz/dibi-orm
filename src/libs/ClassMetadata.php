<?php

namespace Doublemcz\Dibiorm;

use Nette\InvalidArgumentException;

class ClassMetadata
{
	const JOIN_ONE_TO_ONE = 'oneToOne';
	const JOIN_ONE_TO_MANY = 'oneToMany';
	const JOIN_MANY_TO_MANY = 'manyToMany';

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
	protected $oneToManyRelations = array();
	/** @var array */
	protected $oneToOneRelations = array();
	/** @var array */
	protected $manyToManyRelations = array();
	/** @var array */
	protected $propertyReflections = array();
	/** @var bool */
	protected $hasBeforeCreateEvent = FALSE;
	/** @var bool */
	protected $hasBeforeUpdateEvent = FALSE;

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
		$this->findEvents($className);
		if (empty($this->columns)) {
			throw new DocParsingException('You have to specify at least one column. Did you forget to specify @column in some class property? ', $className);
		}

		if (empty($this->primaryKey)) {
			throw new DocParsingException('You have to specify primary key. Did you forget to specify @primaryKey?');
		}
	}

	/**
	 * @param string $entityName
	 * @return array
	 * @throws DocParsingException
	 */
	private function getTableAttributes($entityName)
	{
		$reflection = new \ReflectionClass($entityName);
		$doc = $reflection->getDocComment();

		return $this->parseDoc($doc);
	}

	/**
	 * @param string $entityName
	 * @throws DocParsingException
	 */
	private function findColumns($entityName)
	{
		$reflection = new \ReflectionClass($entityName);
		foreach ($reflection->getProperties() as $property) {
			$propertyReflection = new \ReflectionProperty($entityName, $property->getName());
			$this->propertyReflections[$property->getName()] = $propertyReflection;

			$docLineParameters = $this->parseDoc($propertyReflection->getDocComment());
			if (array_key_exists('column', $docLineParameters)) {
				$this->columns[$property->getName()] = array();
			}

			if (array_key_exists('primaryKey', $docLineParameters)) {
				$this->primaryKey[] = $property->getName();
			}

			if (array_key_exists('autoIncrement', $docLineParameters)) {
				$this->autoIncrementFieldName = $property->getName();
			}

			$this->findRelations($property, $docLineParameters);
		}
	}

	/**
	 * @param \ReflectionProperty $property
	 * @param array $docLineParameters Parameters that are in brackets after @join key @join(column="foo")
	 */
	protected function findRelations(\ReflectionProperty $property, $docLineParameters)
	{
		if (array_key_exists(self::JOIN_ONE_TO_ONE, $docLineParameters)) {
			$relation = self::JOIN_ONE_TO_ONE;
		} else if (array_key_exists(self::JOIN_ONE_TO_MANY, $docLineParameters)) {
			$relation = self::JOIN_ONE_TO_MANY;
		} else if (array_key_exists(self::JOIN_MANY_TO_MANY, $docLineParameters)) {
			$relation = self::JOIN_MANY_TO_MANY;
		} else {
			$relation = FALSE;
		}

		if ($relation) {
			$this->validateJoinParameters($property, $docLineParameters, $relation);
			$joinParameters = array(
				'relation' => $relation,
				'property' => $property->getName(),
				'entity' => $docLineParameters[$relation]['entity'],
				'join' => array_key_exists('join', $docLineParameters) ? $docLineParameters['join'] : NULL,
				'staticJoin' => !empty($docLineParameters['staticJoin']) ? $docLineParameters['staticJoin'] : array(),
				'joiningTable' => !empty($docLineParameters['manyToMany']['joiningTable']) ? $docLineParameters['manyToMany']['joiningTable'] : NULL,
				'joinPrimary' => array_key_exists('joinPrimary', $docLineParameters) ? $docLineParameters['joinPrimary'] : NULL,
				'joinSecondary' => array_key_exists('joinSecondary', $docLineParameters) ? $docLineParameters['joinSecondary'] : NULL,
			);

			$relationProperty = $relation . 'Relations';
			$this->{$relationProperty}[$property->getName()] = $joinParameters;
		}
	}

	/**
	 * @param \ReflectionProperty $property
	 * @param array $docLineParameters Parameters that are in brackets after @join key @join(column="foo")
	 * @param string $relation i.e. oneToMany, oneToOne...
	 * @throws DocParsingException
	 */
	private function validateJoinParameters(\ReflectionProperty $property, $docLineParameters, $relation)
	{
		if (empty($docLineParameters[$relation]['entity'])) {
			throw new DocParsingException(
				sprintf(
					'You set property "%s" as "%s" but the entity attribute is missing. You have to specify entity attribute like this @%s(entity="EntityName"). Class %s.',
					$property->getName(), $relation, $relation, $property->class
				)
			);
		}

		switch ($relation) {
			case self::JOIN_ONE_TO_ONE :
			case self::JOIN_ONE_TO_MANY :
				if (!array_key_exists('join', $docLineParameters)) {
					$message = sprintf(
						'You set property "%s" as "%s" but no join is specified. Did you forget to set @join? Class %s.',
						$property->getName(), $relation, $property->class
					);
					throw new DocParsingException($message);
				}

				if (empty($docLineParameters['join']['column']) || empty($docLineParameters['join']['referenceColumn'])) {
					$message = sprintf(
						'You must set column and referenceColumn in @join parameters. Property %s. Class %s.',
						$property->name,
						$property->class
					);
					throw new DocParsingException($message);
				}

				break;
			case self::JOIN_MANY_TO_MANY :
				if (!array_key_exists('joinPrimary', $docLineParameters) || !array_key_exists('joinSecondary', $docLineParameters)) {
					$message = sprintf(
						'You set property %s as @manyToMany but join is set incorrectly.
						You must set @joinPrimary and @joinSecondary. Class %s.',
						$property->getName(), $property->class
					);
					throw new DocParsingException($message);
				}

				foreach (array('joinPrimary', 'joinSecondary') as $join) {
					if (empty($docLineParameters[$join]['column']) || empty($docLineParameters[$join]['referenceColumn'])) {
						$message = sprintf(
							'You must set column and referenceColumn in %s parameters. Property %s. Class %s.',
							$join,
							$property->name,
							$property->class
						);

						throw new DocParsingException($message);
					}
				}

				break;
			default:
				throw new InvalidArgumentException(sprintf('Relation %s is unknown', $relation));
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
			preg_match('~\s+\*\s@([a-zA-Z0-9]+)(.+)?~', $line, $matches);
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
			// Parses string parameter="value", parameter='value'
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
		return $this->oneToOneRelations;
	}

	/**
	 * @return array
	 */
	public function getRelationsOneToMany()
	{
		return $this->oneToManyRelations;
	}

	/**
	 * @return array
	 */
	public function getRelationsManyToMany()
	{
		return $this->manyToManyRelations;
	}

	/**
	 * @return \ReflectionProperty[]
	 */
	public function getPropertyReflections()
	{
		return $this->propertyReflections;
	}

	/**
	 * @param string $propertyName
	 * @return \ReflectionProperty
	 */
	public function getPropertyReflection($propertyName)
	{
		return $this->propertyReflections[$propertyName];
	}

	/**
	 * @param string $className
	 */
	private function findEvents($className)
	{
		$this->hasBeforeCreateEvent = method_exists($className, "beforeCreateEvent");
		$this->hasBeforeUpdateEvent = method_exists($className, "beforeUpdateEvent");
	}

	/**
	 * @return bool
	 */
	public function hasBeforeCreateEvent()
	{
		return $this->hasBeforeCreateEvent;
	}

	/**
	 * @return bool
	 */
	public function hasBeforeUpdateEvent()
	{
		return $this->hasBeforeUpdateEvent;
	}

	/**
	 * @return string
	 */
	public function getEntityName()
	{
		return substr($this->className, strrpos($this->className, '/') + 1);
	}

	/**
	 * @return array
	 */
	public function getColumns()
	{
		$columns = array_keys($this->getProperties());
		foreach ($this->getRelationsOneToOne() as $relation) {
			$columns[] = $relation['join']['column'];
		}

		return $columns;
	}
}