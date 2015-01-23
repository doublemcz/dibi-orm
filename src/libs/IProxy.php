<?php
namespace doublemcz\dibiorm;

interface IProxy
{
	public function getRelationClass();
	public function getKey();
}