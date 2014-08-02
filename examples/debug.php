<?php
function f($what, $notDie = FALSE)
{
	dump($what);
	if (!$notDie) {
		exit;
	}
}