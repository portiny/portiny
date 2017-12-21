<?php

namespace Portiny\Doctrine\Behaviour;

use Doctrine\ORM\Mapping as ORM;

/**
 * Adds support for PostgreSQL strategy of generating id.
 */
trait Identifier
{

	/**
	 * @ORM\Id
	 * @ORM\Column(type="integer")
	 * @ORM\GeneratedValue(strategy="IDENTITY")
	 * @var int
	 */
	private $id;


	/**
	 * @return int
	 */
	final public function getId()
	{
		return $this->id;
	}


	public function __clone()
	{
		$this->id = NULL;
	}

}
