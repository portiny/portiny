<?php declare(strict_types = 1);

namespace Portiny\Doctrine\Behaviour;

use Doctrine\ORM\Mapping as ORM;

trait Identifier
{
	/**
	 * @ORM\Id
	 * @ORM\Column(type="integer")
	 * @ORM\GeneratedValue(strategy="IDENTITY")
	 * @var int
	 */
	private $id;


	public function __clone()
	{
		$this->id = null;
	}


	/**
	 * @return int
	 */
	final public function getId()
	{
		return $this->id;
	}

}
