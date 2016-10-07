<?php

namespace Rundeck;

use Rundeck\Api as api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class RundeckClient
{

	protected $client;
	protected $jsession;
	protected $majorMinorVersion;

	public function __construct($url, $user, $pass)
	{
		$this->client = new Client([
			'base_url' => $url
		]);

		$response = $this->client->post('/j_security_check', [
			'allow_redirects' => false,
			'body' => [
				'j_username' => $user,
				'j_password' => $pass
			]
		]);

		$path = str_replace('JSESSIONID=', '', $response->getHeaders()['Set-Cookie'][0]);
		$this->jsession = str_replace(';Path=/', '', $path);

		$resp = $this->client->get("/api/1/system/info", [
			'cookies' => ['JSESSIONID' => $this->jsession]
		]);

		$version = explode('.', (string)$resp->xml()->system->rundeck->version);

		$this->majorMinorVersion = (int)($version[0] . $version[1]);
	}

	public function getHttpClient()
	{
		return $this->client;
	}

	public function getProjects()
	{
		$resp = $this->client->get('/api/1/projects', [
			'cookies' => ['JSESSIONID' => $this->jsession]
		]);

		$data = $this->decodeResponse($resp);

		return (new api\ProjectApiMapper)->getAllFromEncoded($data['projects']['project']);
	}

	public function getJobs($projectName, $jobId = null, $groupName = null)
	{
		$jobPath = $groupPath = '';
		if ($jobId) {
			$jobPath .= '&idlist=' . $jobId;
		}
		if ($groupName) {
			$groupPath .= '&groupPath=' . $groupName;
		}
		$resp = $this->client->get("/api/1/jobs?project=$projectName$jobPath$groupPath", [
			'cookies' => ['JSESSIONID' => $this->jsession]
		]);

		$data = $this->decodeResponse($resp);

		if(!isset($data['jobs']['job'])) {
			return false;
		}
		if(isset($data['jobs']['job']['@attributes'])){
			$data['jobs']['job'] = array($data['jobs']['job']);
		}

		return (new api\JobApiMapper)->getAllFromEncoded($data['jobs']['job']);
	}

	public function runJob($id)
	{
		$resp = $this->client->get("/api/1/job/$id/run", [
			'cookies' => ['JSESSIONID' => $this->jsession]
		]);

		$data = $this->decodeResponse($resp);

		if(!isset($data['executions']['execution']['job'])) {
			return false;
		}
		if(isset($data['executions']['execution']['job']['@attributes'])){
			$data['executions']['execution']['job'] = array($data['executions']['execution']['job']);
		}

		return (new api\JobApiMapper)->getAllFromEncoded($data['executions']['execution']['job']);
	}

	public function getJobsExecutions($jobId, $offset = 0, $max = null)
	{
		$offsetPath = $maxPath = '';
		if ($offset) {
			$offsetPath .= '&offset=' . $offset;
		}
		if ($max) {
			$maxPath .= '&max=' . $max;
		}

		$resp = $this->client->get("/api/1/job/$jobId/executions?a$offsetPath$maxPath", [
			'cookies' => ['JSESSIONID' => $this->jsession]
		]);

		$data = $this->decodeResponse($resp);

		if(!isset($data['executions']['execution'])) {
			return false;
		}
		if(isset($data['executions']['execution']['@attributes'])){
			$data['executions']['execution'] = array($data['executions']['execution']);
		}

		return (new api\ExecutionApiMapper)->getAllFromEncoded($data['executions']['execution']);
	}

	public function createProject($name)
	{
		if ($this->majorMinorVersion < 21) {
			throw new \Exception('Unsupported feature. Upgrade to Rundeck 2.1.+');
		}

		try {
			$this->client->post('/api/11/projects', [
				'cookies' => ['JSESSIONID' => $this->jsession],
				'json'    => ["name" => $name],
			]);
		} catch (ClientException $e) {
			// ignore, assume it's a 409 and the project already exists
		}
	}

	public function deleteProject($name)
	{
		if ($this->majorMinorVersion < 21) {
			throw new \Exception('Unsupported feature. Upgrade to Rundeck 2.1.+');
		}

		try {
			$this->client->delete("/api/11/project/$name", [
				'cookies' => ['JSESSIONID' => $this->jsession]
			]);
		} catch (ClientException $e) {
			// ignore, assume it's a 404 and the project already exists
		}
	}

	public function exportJobs($projectName, $jobId = null)
	{
		$jobPath = '';
		if ($jobId) {
			$jobPath .= '&idlist=' . $jobId;
		}
		return $this->client->get("/api/10/jobs/export?project=$projectName$jobPath", [
			'cookies' => ['JSESSIONID' => $this->jsession]
		])->xml()->asXML();
	}

	public function importJobs($projectName, $xml)
	{
		try {
			$response = $this->client->post("/api/10/jobs/import", [
				'cookies' => ['JSESSIONID' => $this->jsession],
				'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
				'body' => [
					'project' => $projectName,
					'uuidOption' => 'preserve',
					'xmlBatch' => $xml,
				],
			]);
			$data = $this->decodeResponse($response);
			return $data;
		} catch (ClientException $e) {
			// ignore, assume it's a 400 and the project already exists
		}
	}

	private function decodeResponse($resp)
	{
		$xml = simplexml_load_string($resp->xml()->asXml());
		$json = json_encode($xml);

		return json_decode($json, true);
	}

}