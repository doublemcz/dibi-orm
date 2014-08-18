<?php
require __DIR__ . '/composer/vendor/autoload.php';
\Tracy\Debugger::enable();

require __DIR__ . '/../src/dibiorm.php';
require __DIR__ . '/User.php';
require __DIR__ . '/UserLog.php';
require __DIR__ . '/UserDetail.php';

//$storage = new Nette\Caching\Storages\FileStorage('temp');
$storage = new Nette\Caching\Storages\MemoryStorage();
$cache = new Nette\Caching\Cache($storage);

$parameters = array(
	'database' => array(
		'host' => 'localhost',
		'username' => 'root',
		'password' => '',
		'database' => 'dibiorm',
		'driver' => 'mysqli',
	),
	'entityNamespace' => 'Entities',
	'proxiesPath' => __DIR__ . '/temp',
);

$entityManager = new \doublemcz\dibiorm\Manager($parameters, $cache);

/**** ADD NEW USER **/
//$user = new \Entities\User();
//$user->fullname = 'Test';
//$user->createdAt = '2014-01-01 00:01:01';
//$user->birthDate = '2014-01-01';
//$entityManager->persist($user);
//$entityManager->flush();


/**** FIND USER AND CHANGE HIM ****/
///** @var \Entities\User $user */
//$user = $entityManager->find('User', 1);
//$user->fullname = 'Test';
//$entityManager->flush();


/**** FIND USERS **/
//$users = $entityManager->getRepository('User')->findBy();
//dump($users);


/*** ONE TO MANY RELATION **/
/** @var \Entities\User $user */
//$user = $entityManager->find('User', 1);
//$userLog = $user->getUserLog();
//dump($userLog);


/*** ONE TO ONE RELATION **/
/** @var \Entities\User $user */
//$user = $entityManager->find('User', 1);
//$detail = $user->getDetail();
//dump($detail);

/**** Speed test - loop over 10 000 records. ****/
for ($idx = 1; $idx < 1000; $idx++) {
	$user = $entityManager->find('User', $idx);
}

//dump($entityManager);