<?php

namespace Rundeck;

use GuzzleHttp\Cookie\SessionCookieJar;
use Rundeck\Api as api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class RundeckClient
{

	protected $client;
	protected $cookie;
	protected $majorMinorVersion;

	public function __construct($url, $user, $pass)
	{
		$this->client = new Client([
			'base_uri' => $url
		]);

		$response = $this->client->request('POST','/j_security_check', [
			'allow_redirects' => false,
			'form_params' => [
				'j_username' => $user,
				'j_password' => $pass
			]
		]);

		$setCookie = new \GuzzleHttp\Cookie\SetCookie();
		$setCookie2  = $setCookie->fromString($response->getHeaders()['Set-Cookie'][0]);
		$setCookie2->setDomain('.');
		$this->cookie = new \GuzzleHttp\Cookie\CookieJar();
		$this->cookie ->setCookie($setCookie2);


		$resp = $this->client->get("/api/1/system/info", [
			'cookies' => $this->cookie
		]);

		//$val = ;
		//	$test = $val->getContents();
		//	$version = explode('.', (string)$resp->getBody()->getContents();//xml()->system->rundeck->version);
		$version = (string)(simplexml_load_string($resp->getBody()->getContents())->system->rundeck->version);
		$version = explode('.', $version);
		$this->majorMinorVersion = (int)($version[0] . $version[1]);
	}

	public function getHttpClient()
	{
		return $this->client;
	}

	public function getProjects()
	{
		$resp = $this->client->get('/api/1/projects', [
			'cookies' => $this->cookie
		]);

		$data = $this->decodeResponse($resp);

		return (new api\ProjectApiMapper)->getAllFromEncoded($data['projects']['project']);
	}

	public function getJobs($projectName, $jobId = null, $groupName = null, $filterName = null)
	{
		$jobPath = $groupPath = $filterPath = '';
		if ($jobId) {
			$jobPath .= '&idlist=' . $jobId;
		}
		if ($groupName) {
			$groupPath .= '&groupPath=' . $groupName;
		}
		if ($filterName) {
			$filterPath .= '&jobExactFilter=' . $filterName;
		}
		$resp = $this->client->get("/api/14/project/$projectName/jobs?a$jobPath$groupPath$filterPath", [
			'cookies' => $this->cookie
		]);

		$data = $this->decodeResponse($resp);

		if(!isset($data['job'])) {
			return false;
		}
		if(isset($data['job']['@attributes'])){
			$data['job'] = array($data['job']);
		}

		return (new api\JobApiMapper)->getAllFromEncoded($data['job']);
	}

	public function disableScheduledJob($jobId)
	{
		try {
			$response = $this->client->post("/api/14/job/$jobId/schedule/disable", [
				'cookies' => $this->cookie
			]);
			$data = $this->decodeResponse($response);
			return $data;
		} catch (ClientException $e) {
			// ignore, assume it's a 400 and the project already exists
		}
	}

	public function enableScheduledJob($jobId)
	{
		try {
			$response = $this->client->post("/api/14/job/$jobId/schedule/enable", [
				'cookies' => $this->cookie
			]);
			$data = $this->decodeResponse($response);
			return $data;
		} catch (ClientException $e) {
			// ignore, assume it's a 400 and the project already exists
		}
	}

	public function runJob($id)
	{
		$resp = $this->client->get("/api/1/job/$id/run", [
			'cookies' => $this->cookie
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

	public function JobInfo($id)
	{
		$resp = $this->client->get("/api/1/job/$id", [
			'cookies' => $this->cookie
		]);

		$data = $this->decodeResponse($resp);

		if(!isset($data['job'])) {
			return false;
		}
		return $data['job'];
	}

	public function getJobsExecutions($jobId, $status = null, $offset = 0, $max = null)
	{
		$offsetPath = $maxPath = $statusPath = '';
		if ($offset) {
			$offsetPath .= '&offset=' . $offset;
		}
		if ($max) {
			$maxPath .= '&max=' . $max;
		}
		if ($status) {
			$statusPath .= '&status=' . $status;
		}

		$resp = $this->client->get("/api/1/job/$jobId/executions?a$offsetPath$maxPath$statusPath", [
			'cookies' => $this->cookie
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

	public function getJobsExecStatus($execId)
	{
		$resp = $this->client->get("/api/5/execution/$execId/output", [
			'cookies' => $this->cookie
		]);
		$data = $this->decodeResponse($resp);
		return $data;
	}

	public function createProject($name)
	{
		if ($this->majorMinorVersion < 21) {
			throw new \Exception('Unsupported feature. Upgrade to Rundeck 2.1.+');
		}

		try {
			$this->client->post('/api/11/projects', [
				'cookies' => $this->cookie,
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
				'cookies' => $this->cookie
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
			'cookies' => $this->cookie
		])->xml()->asXML();
	}

	public function importJobs($projectName, $xml,$mode = 'create')
	{
		try {
			$response = $this->client->request('POST', "/api/14/project/$projectName/jobs/import?dupeOption=$mode", [
					'cookies' => $this->cookie,
					'headers' => [	'Content-Type' => 'application/xml'],
					'form_params' => [trim($xml)]
				]
			);
			$data = $this->decodeResponse($response);
			return $data;
		} catch (ClientException $e) {
			$response = $e->getResponse();
			$responseBodyAsString = $response->getBody()->getContents();
		}
	}

	private function decodeResponse($resp)
	{
		$xml = simplexml_load_string($resp->getBody()->getContents());
		$json = json_encode($xml);

		return json_decode($json, true);
	}

}