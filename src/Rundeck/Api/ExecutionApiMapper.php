<?php

namespace Rundeck\Api;

class ExecutionApiMapper {

	public function getFromEncoded(array $params) {
		return (new Execution())
			->setId((string)$params['@attributes']['id'])
			->setStatus((string)$params['@attributes']['status'])
			->setJob($params['job'])
			->setDescription(is_string($params['description']) ? $params['description'] : '')
			->setDateStarted($params['date-started'])
			->setDateEnded($params['date-ended'])
			;
	}

	public function getAllFromEncoded(array $encs) {
		$data = [];
		foreach ($encs as $enc) {
			$data[] = $this->getFromEncoded($enc);
		}
		return $data;
	}

}

// Rundeck API response for job
// 
// {
//   "name" : "Some Name"
//   "project" : "test"
//   "description" : "Lorem Ipsum"
// }