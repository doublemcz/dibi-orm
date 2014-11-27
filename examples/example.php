<?php
require __DIR__ . '/vendor/autoload.php';
\Tracy\Debugger::enable();

require __DIR__ . '/../src/dibiorm.php';
require __DIR__ . '/Entities/User.php';
require __DIR__ . '/Entities/UserLog.php';
require __DIR__ . '/Entities/UserDetail.php';

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
//$user->birthDate = new DateTime('1988-08-03 15:00');
//$entityManager->persist($user);
//$entityManager->flush();

/**** FIND USER AND CHANGE HIM ****/
/** @var \Entities\User $user */
$user = $entityManager->find('User', 1);
$user->fullname = 'Test';
$entityManager->flush();

/**** FIND USERS **/
//$users = $entityManager->getRepository('User')->findBy();
//dump($users);


/*** ONE TO MANY RELATION **/
/** @var \Entities\User $user */
//$user = $entityManager->find('User', 1);
//$userLog = $user->getUserLog();
//dump($userLog);
//dump($userLog[0]);


/*** ONE TO ONE RELATION **/
/** @var \Entities\User $user */
//$user = $entityManager->find('User', 1);
//$detail = $user->getDetail();
//dump($detail->note);

///**** Speed test - loop over 1 000 records. ****/
//for ($idx = 1; $idx < 1000; $idx++) {
//	$user = $entityManager->find('User', $idx);
//}

//dump($entityManager);
echo "done";