<?php

class Nest
{
	public $debug;
	private $username;
	private $password;
	private $cookieFile;

	public function __construct($username, $password, $debug = false)
	{
		// Set the properties
		$this->debug	 = $debug;
		$this->username	 = $username;
		$this->password	 = $password;
		$this->useragent = 'Nest/1.1.0.10 CFNetwork/548.0.4';
		$this->cookieFile = tempnam('/tmp', 'nest-cookie');

		// Login
		$response = $this->curlPost('https://home.nest.com/user/login', 'username=' . urlencode($username) . '&password=' . urlencode($password));

		if (($json = json_decode($response)) === false)
			throw new Exception('Unable to connect to Nest');

		// Stash information needed to make subsequence requests
		$this->access_token = $json->access_token;
		$this->user_id = $json->userid;
		$this->transport_url = $json->urls->transport_url;
	}

	public function house_state_set($state)
	{
		switch ($state)
		{
			case 'away':
				$away = true;
				break;
			case 'home':
				$away = false;
				break;
			default:
				throw new Exception('Invalid state given: "' . $state . '"');
		}
		
		$status = $this->status_get();

		$structure_id = $status->user->{$this->user_id}->structures[0];
		$payload = json_encode(array('away_timestamp' => time(), 'away' => $away, 'away_setter' => 0));
		return $this->curlPost($this->transport_url . '/v2/put/' . $structure_id, $payload);
	}

	public function temperature_set($temp)
	{
		$status = $this->status_get();

		$structure = $status->user->{$this->user_id}->structures[0];
		list (,$structure_id) = explode('.', $structure);
		$device = $status->structure->{$structure_id}->devices[0];
		list (,$device_serial) = explode('.', $device);
		$temperature_scale = $status->device->{$device_serial}->temperature_scale;

		if ($temperature_scale == "F")
		{
			$target_temp_celsius = (($temp - 32) / 1.8);
		}
		else
		{
			$target_temp_celsius = $temp;
		}
		
		$payload = json_encode(array('target_change_pending' => true, 'target_temperature' => $target_temp_celsius));
		return $this->curlPost($this->transport_url . '/v2/put/shared.' . $device_serial, $payload);
	}

	public function status_get()
	{
		$response = $this->curlGet($this->transport_url . '/v2/mobile/user.' . $this->user_id);
		
		if (($json = json_decode($response)) === false)
			throw new Exception('Unable to gather the status from Nest');

		return $json;
	}

	private function curlGet($url, $referer = null, $headers = null)
	{

		$headers[] = 'Authorization: Basic ' . $this->access_token;
		$headers[] = 'X-nl-user-id:' . $this->user_id;
		$headers[] = 'X-nl-protocol-version: 1';
		
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->useragent);
		if(!is_null($referer)) curl_setopt($ch, CURLOPT_REFERER, $referer);
		if(!is_null($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		// curl_setopt($ch, CURLOPT_VERBOSE, true);

		$html = curl_exec($ch);

		if(curl_errno($ch) != 0)
		{
			throw new Exception("Error during GET of '$url': " . curl_error($ch));
		}

		$this->lastURL = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
		$this->lastStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		return $html;
	}

	private function curlPost($url, $post_vars = '', $referer = null)
	{
		if (isset($this->access_token)) $headers[] = 'Authorization: Basic ' . $this->access_token;
		if (isset($this->user_id)) $headers[] = 'X-nl-user-id:' . $this->user_id;
		$headers[] = 'X-nl-protocol-version: 1';

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->useragent);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vars);
		if(!is_null($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		// curl_setopt($ch, CURLOPT_VERBOSE, true);

		$html = curl_exec($ch);

		$this->lastURL = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
		$this->lastStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		return $html;
	}
}
