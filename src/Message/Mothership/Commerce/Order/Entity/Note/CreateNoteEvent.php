<?php

namespace Message\Mothership\Commerce\Order\Entity\Note;

use Message\Cog\Event\Event;

class CreateNoteEvent extends Event
{
	protected $_note;
	protected $_order;

	public function __construct(Note $note, Order $order = null)
	{
		$this->_note  = $note;
		$this->_order = $order ?: $note->order;
	}

	public function getNote()
	{
		return $this->_note;
	}

	public function getOrder()
	{
		return $this->_order;
	}
}