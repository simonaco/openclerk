<?php

/**
 * Various batch-related helper functions.
 */
define('BATCH_SCRIPT', true);

function require_batch_key() {
	global $argv;
	if (!(isset($argv) && $argv[1] == get_site_config("automated_key")) && require_get("key") != get_site_config("automated_key"))
		throw new Exception("Invalid key");
}

function batch_header($page_name, $page_id) {
	if (require_get("key", false)) {
		// we're running from a web browser
		require(__DIR__ . "/../layout/templates.php");
		$options = array();
		if (require_get("refresh", false)) {
			$options["refresh"] = require_get("refresh");
		}
		page_header($page_name, $page_id, $options);
	}
}

function batch_footer() {
	if (require_get("key", false)) {
		// we're running from a web browser
		// include page gen times etc
		page_footer();
	} else {
		// we are running from the CLI
		// we still need to calculate performance metrics
		performance_metrics_page_end();
	}
}

class JobException extends Exception { }
function crypto_log($log) {
	echo "\n<li>$log</li>";
	// flush();
}
class ExternalAPIException extends Exception { } // expected exceptions
class EmptyResponseException extends ExternalAPIException { } // expected exception; allows us to handle e.g BitMinter
class CloudFlareException extends ExternalAPIException { } // expected exception; TODO implement some code to handle CloudFlare blocking
class IncapsulaException extends ExternalAPIException { } // expected exception; TODO implement some code to handle Incapsula blocking

function crypto_wrap_url($url) {
	// remove API keys etc
	$url_clean = $url;
	$url_clean = preg_replace('#key=([^&]{3})[^&]+#im', 'key=\\1...', $url_clean);
	$url_clean = preg_replace('#hash=([^&]{3})[^&]+#im', 'hash=\\1...', $url_clean);
	crypto_log("Requesting <a href=\"" . htmlspecialchars($url_clean) . "\">" . htmlspecialchars($url_clean) . "</a>...");
	return $url;
}

if (get_site_config('timed_curl')) {
	/**
	 * All times are stored in ms.
	 */
	$global_timed_curl = array(
		'time' => 0,
		'count' => 0,
		'urls' => array(),
	);
}

/**
 * Wraps {@link #file_get_contents()} with timeout information etc.
 * May throw a {@link ExternalAPIException} if something unexpected occured.
 */
function crypto_get_contents($url, $options = array()) {
	if (get_site_config('timed_curl')) {
		$curl_start = microtime(true);
	}

	// normally file_get_contents is OK, but if URLs are down etc, the timeout has no value and we can just stall here forever
	// this also means we don't have to enable OpenSSL on windows for file_get_contents('https://...'), which is just a bit of a mess
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; Openclerk PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_TIMEOUT, get_site_config('get_contents_timeout') /* in sec */);	// defaults to infinite
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, get_site_config('get_contents_timeout') /* in sec */);	// defaults to 300s
	// curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
	// curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_ENCODING, "gzip,deflate");			// enable gzip decompression if necessary
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	foreach ($options as $key => $value) {
		curl_setopt($ch, $key, $value);
	}

	// run the query
	$res = curl_exec($ch);

	// store performance metrics
	if (get_site_config('timed_curl')) {
		global $global_timed_curl;
		$global_timed_curl['count']++;
		$global_timed_curl['time'] += (microtime(true) - $curl_start) * 1000;
		if (!isset($global_timed_curl['urls'][$url])) {
			$global_timed_curl['urls'][$url] = array('count' => 0, 'time' => 0);
		}
		$global_timed_curl['urls'][$url]['count']++;
		$global_timed_curl['urls'][$url]['time'] += (microtime(true) - $curl_start) * 1000;
	}

	if ($res === false) throw new ExternalAPIException('Could not get reply: '.curl_error($ch));
	crypto_check_response($res);

	return $res;
}

/**
 * @throws a {@link CloudFlareException} or {@link IncapsulaException} if the given
 * 		remote response suggests something about CloudFlare or Incapsula.
 * @throws an {@link ExternalAPIException} if the response suggests something else that was unexpected
 */
function crypto_check_response($string, $message = false) {
	if (strpos($string, 'DDoS protection by CloudFlare') !== false) {
		throw new CloudFlareException('Throttled by CloudFlare' . ($message ? " $message" : ""));
	}
	if (strpos($string, 'CloudFlare') !== false) {
		if (strpos($string, 'The origin web server timed out responding to this request.') !== false) {
			throw new CloudFlareException('Cloudflare reported: The origin web server timed out responding to this request.');
		}
	}
	if (strpos($string, 'Incapsula incident') !== false) {
		throw new IncapsulaException('Blocked by Incapsula' . ($message ? " $message" : ""));
	}
	if (strpos($string, '_Incapsula_Resource') !== false) {
		throw new IncapsulaException('Throttled by Incapsula' . ($message ? " $message" : ""));
	}
	if (strpos(strtolower($string), '301 moved permanently') !== false) {
		throw new ExternalAPIException("API location has been moved permanently" . ($message ? " $message" : ""));
	}
	if (strpos($string, "Access denied for user '") !== false) {
		throw new ExternalAPIException("Remote database host returned 'Access denied'" . ($message ? " $message" : ""));
	}
	if (strpos(strtolower($string), "502 bad gateway") !== false) {
		throw new ExternalAPIException("Bad gateway" . ($message ? " $message" : ""));
	}
	if (strpos(strtolower($string), "connection timed out") !== false) {
		throw new ExternalAPIException("Connection timed out" . ($message ? " $message" : ""));
	}
}

/**
 * Try to decode a JSON string, or try and work out why it failed to decode but throw an exception
 * if it was not a valid JSON string.
 *
 * @param empty_is_ok if true, then don't bail if the returned JSON is an empty array
 */
function crypto_json_decode($string, $message = false, $empty_array_is_ok = false) {
	$json = json_decode($string, true);
	if (!$json) {
		if ($empty_array_is_ok && is_array($json)) {
			// the result is an empty array
			return $json;
		}
		crypto_log(htmlspecialchars($string));
		crypto_check_response($string);
		if (substr($string, 0, 1) == "<") {
			throw new ExternalAPIException("Unexpectedly received HTML instead of JSON" . ($message ? " $message" : ""));
		}
		if (strpos(strtolower($string), "invalid key") !== false) {
			throw new ExternalAPIException("Invalid key" . ($message ? " $message" : ""));
		}
		if (strpos(strtolower($string), "bad api key") !== false) {
			throw new ExternalAPIException("Bad API key" . ($message ? " $message" : ""));
		}
		if (strpos(strtolower($string), "access denied") !== false) {
			throw new ExternalAPIException("Access denied" . ($message ? " $message" : ""));
		}
		if (strpos(strtolower($string), "parameter error") !== false) {
			// for 796 Exchange
			throw new ExternalAPIException("Parameter error" . ($message ? " $message" : ""));
		}
		if (!$string) {
			throw new EmptyResponseException('Response was empty' . ($message ? " $message" : ""));
		}
		throw new ExternalAPIException('Invalid data received' . ($message ? " $message" : ""));
	}
	return $json;
}

class WrappedJobException extends Exception {
	public $job_id;
	public $cause;
	public function __construct($cause, $job_id) {
		parent::__construct($cause->getMessage());
		$this->cause = $cause;
		$this->job_id = $job_id;
	}
	public function getCause() {
		return $this->cause;
	}
	public function getJobId() {
		return $this->job_id;
	}
}
