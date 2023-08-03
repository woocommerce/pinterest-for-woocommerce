<?php

class Event {
	private $id;

	private $time;

	private $name;

	public function __construct( $id, $time, $name ) {
		$this->id   = $id;
		$this->time = $time;
		$this->name = $name;
	}
}
