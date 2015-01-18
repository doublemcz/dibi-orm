<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/../src/dibiorm.php';
require __DIR__ . '/Entities/User.php';
require __DIR__ . '/Entities/UserLog.php';
require __DIR__ . '/Entities/UserDetail.php';

Tracy\Debugger::enable();

$storage = new Nette\Caching\Storages\FileStorage('temp');
$storage = new Nette\Caching\Storages\MemoryStorage();

$parameters = array(
	'database' => array(
		'host' => 'localhost',
		'username' => 'root',
		'password' => '',
		'database' => 'dibiorm',
		'driver' => 'mysqli',
	),
	'entityNamespace' => 'doublemcz\dibiorm\Examples\Entities',
	'proxiesPath' => __DIR__ . '/temp',
	'storage' => $storage,
);

$entityManager = new \doublemcz\dibiorm\Manager($parameters);

/**** ADD NEW USER **/
//$user = new \doublemcz\dibiorm\Examples\Entities\User();
//$user->fullname = 'Test';
//$user->birthDate = new DateTime('1988-08-03 15:00');
//$entityManager->persist($user);
//$entityManager->flush();

/**** FIND USER AND CHANGE HIM ****/
/** @var \doublemcz\dibiorm\Examples\Entities\User $user */
//$user = $entityManager->find('User', 1);
//$user->fullname = 'Test';
//$entityManager->flush();

/**** FIND ONE BY ***/
//$user = $entityManager->findOneBy('User', array('fullname' => 'test'));
//dump($user);

/**** FIND USERS **/
//$users = $entityManager->getRepository('User')->findBy();
//dump($users);

/*** ONE TO ONE RELATION **/
/** @var \doublemcz\dibiorm\Examples\Entities\User $user */
//$user = $entityManager->find('User', 1);
//$detail = $user->getDetail();
//dump($detail->note);

/*** ONE TO MANY RELATION **/
/** @var \doublemcz\dibiorm\Examples\Entities\User $user */
//$user = $entityManager->find('User', 1);
//$userLog = $user->getUserLog();
//dump($userLog[0]);


/*** JOINING TABLE */
//$user = $entityManager->find('User', 1);
//$userLog = $user->getJoiningTableUserLog();
//dump($userLog[0]);

/**** Speed test - loop over 1 000 records. ****/
//for ($idx = 1; $idx < 1000; $idx++) {
//	$user = $entityManager->find('User', $idx);
//}
//dump($entityManager);

echo "done";