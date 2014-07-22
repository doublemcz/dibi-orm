<?php
include __DIR__ . '/lib/dibi/dibi.php';
include __DIR__ . '/../src/dibiorm.php';
include __DIR__ . '/User.php';


function f($what, $notDie = FALSE)
{
	echo '<pre>';
	print_r($what);
	echo '</pre>';

	if (!$notDie) {
		exit;
	}
}

$parameters = array(
	'database' => array(
		'host' => 'localhost',
		'username' => 'root',
		'password' => '',
		'database' => 'dibiorm',
		'driver' => 'mysqli',
	),
	'entityNamespace' => 'Entities',
);

$entityManager = new \doublemcz\dibiorm\Manager($parameters, NULL);
$user = $entityManager->find('User', 1);
f($user);