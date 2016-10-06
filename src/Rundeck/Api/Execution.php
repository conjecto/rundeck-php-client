<?php

namespace Rundeck\Api;

class Execution {

	private $id;
	private $status;
	private $job;
	private $description;
	private $dateStarted;
	private $dateEnded;

	public function getId() {
		return $this->id;
	}

	public function getStatus()
	{
		return $this->status;
	}

	public function getJob() {
		return $this->job;
	}
	public function getDescription() {
		return $this->description;
	}

	public function getDateStarted()
	{
		return $this->dateStarted;
	}

	public function getDateEnded()
	{
		return $this->dateEnded;
	}

	public function setId($id) {
		$this->id = $id;
		return $this;
	}
	public function setStatus($status)
	{
		$this->status = $status;
		return $this;
	}
	public function setJob($project) {
		$this->project = $project;
		return $this;
	}
	public function setDescription($description) {
		$this->description = $description;
		return $this;
	}
	public function setDateStarted($dateStarted)
	{
		$this->dateStarted = $dateStarted;
		return $this;
	}
	public function setDateEnded($dateEnded)
	{
		$this->dateEnded = $dateEnded;
		return $this;
	}
}