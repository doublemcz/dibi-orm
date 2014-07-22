<?php
function f($what, $notDie = FALSE)
{
	echo '<pre>';
	print_r($what);
	echo '</pre>';

	if (!$notDie) {
		exit;
	}
}