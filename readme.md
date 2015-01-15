# Dibi ORM

Dibi ORM is lightweight ORM solution based on Dibi. Works closely with Nette Framework. 
ORM logic comes from Doctrine 2 but is very simplified. Focus is also on performance.

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
	'storage' => new Nette\Caching\Storages\FileStorage('temp');,
);

$databaseManager = new \doublemcz\dibiorm\Manager($parameters);
```

#### Usage in Nette

Put this section into services.neon

```neon
extensions:
	dibi: Dibi\Bridges\Nette\DibiExtension22
	
services:
	databaseManager: doublemcz\dibiorm\Manager(%entityManager%)
		
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

#### Get an Entity by ID
Find a user with ID = 1
```php
$databaseManager->find('User', 1);
```

If user has more columns in primary key, you can pass it in order you defined the key at the entity
```php
$user = $databaseManager->find('AnEntityName', 'foo', 'bar');
```

#### Find an Entity by propety
We can find an Entity by property e-mail
```php
$user = $databaseManager->findOneBy('User', array('email' => 'email@domain.com'));
```

#### Get entities in table
```php
$user = $databaseManager->findBy('User');
```
You can filter by where
```
$users = $databaseManager->findBy('User', array('role' => 'admin'));
```

#### Insert entity to database
```php
$user = new User();
$user->name = 'Martin';
$databaseManager->persist($user);
$databaseManager->flush();
```

#### Update entity
When you load an entity from repository then the entity is automatically managed by Manager. It means that if you make a change and flush changes over Manager a SQL query is automatically execute.

```
$user = $database->find('User', 1);
$user->note = 'An updated note on user 1';
$databaseManager->flush();
```
