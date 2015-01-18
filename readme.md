# Dibi ORM

Dibi ORM is lightweight ORM solution based on Dibi. ORM logic comes from Doctrine 2 but is very simplified. Focus is also on performance.

### Installation
I recommend you to install via Composer.

```
composer require doublemcz/dibi-orm
```

If you do not have Composer, download latest version from GitHub and require bootstrap file.
```php
require 'src/dibirom.php'
```

### Initialization
```php
$parameters = array(
	'database' => array(
		'host' => 'localhost',
		'username' => 'root',
		'password' => '',
		'database' => 'dibiorm',
		'driver' => 'mysqli',
	),
	'entityNamespace' => 'App\Entities',
	'proxiesPath' => __DIR__ . '/temp',
	'storage' => new Nette\Caching\Storages\FileStorage('temp'),
);

$databaseManager = new \doublemcz\dibiorm\Manager($parameters);
```

##### Usage in Nette

Put this section into services.neon

```neon
extensions:
	dibi: Dibi\Bridges\Nette\DibiExtension22
	
services:
	databaseManager: doublemcz\dibiorm\Manager(%databaseManager%)
		
parameters:
	databaseManager:
		database: 
			host: localhost
			username: userName
			password: password
			database: database
		entityNamespace: App\Entities
		proxiesPath: '%tempDir%/proxies'
		storage: @cacheStorage
```

It is also possible to pass DibiConnection to parameter 'database'
```neon
parameters:
	databaseManager:
		database: @dibiConnection
```


### Data handling


##### Get an Entity by ID
Find a user with ID = 1
```php
$databaseManager->find('User', 1);
```

If user has more columns in primary key, you can pass it in order you defined the key at the entity
```php
$user = $databaseManager->find('AnEntityName', 'foo', 'bar');
```

##### Find an Entity by propety
We can find an Entity by property e-mail
```php
$user = $databaseManager->findOneBy('User', array('email' => 'email@domain.com'));
```


##### Get entities in table
Find all users in table 'users'
```php
$users = $databaseManager->findBy('User');
```
You can filter and sort by array. We are trying to find Users in role 'admin' ordered by id desc.
```
$users = $databaseManager
	->findBy(
		'User', 
		array('role' => 'admin'),
		array('id' => 'DESC')
	);
```


##### Insert entity to database
```php
$user = new User();
$user->name = 'Martin';
$databaseManager->persist($user);
$databaseManager->flush();
```


##### Update entity
When you load an entity from repository then the entity is automatically managed by Manager. It means that if you make a change and flush changes over Manager a SQL query is automatically executed.

```
$user = $database->find('User', 1);
$user->note = 'An updated note on user 1';
$databaseManager->flush();
```


### Entity Settings
All settings are defined by PhpDoc. Every entity must have @table tag to specify the source table defined on class PhpDoc.
Every class property that has relation to database column must have tag @column.

##### Defining primary column
Every entity must have primary key. The definition is composed by @primaryKey and @column. If you want set id that was generated from database on create sql query then specify @autoIncrement tag.

##### Relations
Basic relation are defined by @oneToOne and @oneToMany tag. Both need a join specification tag defined as follow:
@join(column="id", referenceColumn="userId"). It says that it is joing column User.id to RelatedTable.userId column.

###### Real example:
```php
/**
 * @table (name="users")
 */
class User {
	/**
	 * @oneToMany(entity="UserLog")
	 * @join(column="id", referenceColumn="userId")
	 * @var User
	 */
	protected $userLog;
	
	/**
	 * @return UserLog[]
	 */
	public function getUserLog()
	{
		return  $this->userLog;
	}
}
```

###### Static join parameter
It is also possible to specify static join parameter to filter table by column. Here you can see static join that defines user.type = 'error'. Static join is possible only on @oneToOne and @oneToMany relations.
```php
/**
 * @table (name="users")
 */
class User {
	/**
	 * @oneToMany(entity="UserLog")
	 * @join(column="id", referenceColumn="userId")
	 * @staticJoin(column="type", value="error")
	 * @var User
	 */
	protected $errorLog;
	
	/**
	 * @return UserLog[]
	 */
	public function getErrorLog()
	{
		return  $this->errorLog;
	}
}
```

##### Relation @manyToMany
Relation many-to-many is used when your data are connected over relation table.

```php
/**
 * @manyToMany(entity="AnEntityName", joiningTable="joining_table")
 * @joinPrimary(column="id", referenceColumn="userId")
 * @joinSecondary(column="userLogId", referenceColumn="id")
 * @var AnEntityName[]
 */
protected $foo;
```

### Events
Manager has event handling based on methods included in the Class. We have Entity events at this moment:
 - beforeCreateEvent
 - beforeUpdateEvent

#### Examples of event usage. 
There you can see how we can update an entity **before** create or update sql is executed.
```php
/**
 * @param Manager $manager
 */
public function beforeCreateEvent(Manager $manager)
{
	$this->createdAt = new \DateTime();
}

public function beforeUpdateEvent(Manager $manager)
{
	$this->updatedAt = new \DateTime();
}
```

### Example of User entity definition
```php
<?php

namespace doublemcz\dibiorm\Examples\Entities;
use doublemcz\dibiorm\Manager;

/**
 * @table (name="users")
 */
class User {
	/**
	 * @primaryKey
	 * @autoIncrement
	 * @column
	 * @var int
	 */
	public $id;
	
	/**
	 * @oneToMany(entity="UserLog")
	 * @join(column="id", referenceColumn="userId")
	 * @var User
	 */
	protected $userLog;
		
	/**
	 * @oneToMany(entity="UserLog")
	 * @join(column="id", referenceColumn="userId")
	 * @staticJoin(column="type", value="error")
	 * @var User
	 */
	protected $userErrorLog;
	
	/**
	 * @oneToOne(entity="UserDetail")
	 * @join(column="id", referenceColumn="userId")
	 * @var UserDetail
	 */
	protected $detail;
	
	/**
	 * @column
	 * @var string
	 */
	public $fullname;
	
	/**
	 * @column
	 * @var \DateTime
	 */
	public $birthDate;
	
	/**
	 * @column
	 * @var \DateTime
	 */
	public $createdAt;
	
	/**
	 * @column
	 * @var \DateTime
	 */
	public $updatedAt;
	
	/**
	 * @return UserLog[]
	 */
	public function getUserLog()
	{
		return $this->userLog;
	}
	
	/**
	 * @return UserDetail
	 */
	public function getDetail()
	{
		return $this->detail;
	}
	
	/**
	 * @param Manager $manager
	 */
	public function beforeCreateEvent(Manager $manager)
	{
		$this->createdAt = new \DateTime();
	}
	
	public function beforeUpdateEvent(Manager $manager)
	{
		$this->updatedAt = new \DateTime();
	}
}
```
