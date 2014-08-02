<?php
require __DIR__ . '/lib/dibi/dibi.php';
require __DIR__ . '/lib/tracy/tracy.php';
require __DIR__ . '/../src/dibiorm.php';
require __DIR__ . '/User.php';
require __DIR__ . '/UserLog.php';
require __DIR__ . '/UserDetail.php';
require __DIR__ . '/debug.php';

\Tracy\Debugger::enable();

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

$entityManager = new \doublemcz\dibiorm\Manager($parameters, NULL);

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
//f($users, 1);


/*** ONE TO MANY RELATION **/
/** @var \Entities\User $user */
$user = $entityManager->find('User', 1);
$detail = $user->getDetail();
echo $detail->note;