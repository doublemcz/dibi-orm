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

$entityManager = new \doublemcz\dibiorm\Manager($parameters);
```

### Usage in Nette

Put this section into services.neon

```neon
services:
	entityManager: doublemcz\dibiorm\Manager(%entityManager%)
		
parameters:
	entityManager:
		database: 
			host: localhost
			username: userName
			password: password
			database: database
      entityNamespace: App\Entities
      proxiesPath: '%tempDir%/proxies'
      storage: @cacheStorage
```
