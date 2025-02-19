<?php


/**
 * Get cached data
 * @param string $cache_object_key The key of the cached object
 * @param string $cache_directory The directory where the cache is stored
 * @return mixed The cached object
 */
function cacheGet($cache_object_key, $cache_directory = 'cache/')
{
	// Use Redis if available
	if (REDIS) {
		$client = new Predis\Client('tcp://' . REDIS_HOST . ':' . REDIS_PORT);
		$cache_directory = str_replace($_SERVER['DOCUMENT_ROOT'], '', $cache_directory);
		$key = 'upvote_rss' . str_replace(['cache/', '/'], ':', $cache_directory) . ':' . $cache_object_key;
		$key = str_replace(['.'], '_', $key);
		$key = str_replace('::', ':', $key);
		if ($client->exists($key)) return unserialize($client->get($key));
		else return null;
	} else {
		@include $cache_directory . urlencode($cache_object_key);
		return isset($val) ? $val : false;
	}
}


/**
 * Set cached data
 * @param string $cache_object_key The key of the cached object
 * @param mixed $cache_object_value The value of the cached object
 * @param string $cache_directory The directory where the cache is stored
 * @param int $cache_expiration The expiration time of the cache in seconds
 */
function cacheSet($cache_object_key, $cache_object_value, $cache_directory = 'cache/', $cache_expiration = 0)
{
	if (empty($cache_object_key) || empty($cache_object_value)) {
		return;
	}
	$log = new \CustomLogger;
	// Use Redis if available
	if (REDIS) {
		$client = new Predis\Client('tcp://' . REDIS_HOST . ':' . REDIS_PORT);
		// remove document root from cache directory
		$cache_directory = str_replace($_SERVER['DOCUMENT_ROOT'], '', $cache_directory);
		$key = 'upvote_rss' . str_replace(['cache/', '/'], ':', $cache_directory) . ':' . $cache_object_key;
		$key = str_replace(['.'], '_', $key);
		$key = str_replace('::', ':', $key);
		$client->set($key, serialize($cache_object_value), 'EX', $cache_expiration);
		return;
	} else {
		if (!is_dir($cache_directory)) {
			try {
				mkdir($cache_directory, 0755, true);
			} catch (\Exception $e) {
				$log->error("Failed to create cache directory: " . $e->getMessage());
				return;
			}
		}
		$cache_object_value = var_export($cache_object_value, true);
		$cache_directory = realpath($cache_directory) . '/';
		$file_path = $cache_directory . urlencode($cache_object_key);
		if (file_put_contents($file_path, '<?php $val = ' . $cache_object_value . ';', LOCK_SH) === false) {
			$log->error("Failed to write cache file: $file_path");
		}
	}
}


/**
 * Delete cached data
 * @param string $cache_object_key The key of the cached object
 * @param string $cache_directory The directory where the cache is stored
 */
function cacheDelete($cache_object_key, $cache_directory = 'cache/')
{
	// Use Redis if available
	if (REDIS) {
		$client = new Predis\Client('tcp://' . REDIS_HOST . ':' . REDIS_PORT);
		$cache_directory = str_replace($_SERVER['DOCUMENT_ROOT'], '', $cache_directory);
		$key = 'upvote_rss' . str_replace(['cache/', '/'], ':', $cache_directory) . ':' . $cache_object_key;
		$key = str_replace(['.'], '_', $key);
		$key = str_replace('::', ':', $key);
		$client->del($key);
		return;
	} else {
		$cache_file = $cache_directory . urlencode($cache_object_key);
		if (file_exists($cache_file)) unlink($cache_file);
	}
}


/**
 * Dump and die
 * @param mixed $data The data to dump
 */
function dd($data)
{
	echo '<pre>';
	die(var_dump($data));
	echo '</pre>';
}


/**
 * Write to log file
 * @param string $log_message The message to write to the log file
 */
function writeLog($log_message)
{
	if (is_array($log_message)) {
		$log_message = print_r($log_message, true);
	}
	if (is_object($log_message)) {
		$log_message = json_encode($log_message);
	}
	$log_file = fopen('debug.log', 'a');
	$log_message = date('Y-m-d H:i:s') . ' ' . $log_message;
	fwrite($log_file, $log_message . "\n");
	fclose($log_file);
}


/**
 * Return a JSON array
 * @param array $array The array to return as JSON
 */
function returnJSONarray($array)
{
	header('Content-Type: application/json');
	echo json_encode($array);
	exit;
}


/**
 * Return a JSON error message
 * @param string $message The error message to return as JSON
 */
function returnJSONerror($message)
{
	header('Content-Type: application/json');
	echo json_encode(array(
		'error' => $message
	));
	exit;
}


/**
 * Get the HTTP protocol
 * @return string The HTTP protocol
 */
function getProtocol() {
	$protocol = 'http://';
	if (
		isset($_SERVER['HTTPS']) &&
		($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) ||
		isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
		$_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'
	) {
		$protocol = 'https://';
	}
	return $protocol;
}


/**
 * Clean a URL by removing some query parameters
 * @param string $url The URL to clean
 * @return string The cleaned URL
 */
function cleanURL($url)
{
	if (empty($url)) return;
	// Remove utm_* query parameters
	$url = preg_replace("/&?utm_(.*?)\=[^&]+/", "", "$url");
	// Remove the following query parameters: guccounter, guce_referrer, guce_referrer_sig, fbclid, gclid, gclsrc, gclaw
	$url = preg_replace("/&?(guccounter|guce_referrer|guce_referrer_sig|fbclid|gclid|gclsrc|gclaw)\=[^&]+/", "", "$url");
	$url = trim($url);
	$url = htmlspecialchars($url);
	return $url;
}


/**
 * Return a tidied string of HTML
 * @param string $string The HTML string to be tidied
 * @return string The tidied HTML string
 */
function tidy($string) : string {
	if (!extension_loaded('tidy')) return $string ?? '';
	$tidy_configuration = [
		'clean' => true,
		'output-xhtml' => true,
		'show-body-only' => true,
		'wrap' => 0,
		'drop-proprietary-attributes' => true,
		'logical-emphasis' => true
	];
	$tidy = new tidy;
	$tidy->parseString($string, $tidy_configuration, 'utf8');
	$tidy->cleanRepair();
	$output = str_replace(
		"\n",
		"",
		tidy_get_output($tidy)
	);
	return $output;
}


/**
 * cURL a URL
 * @param string $url The URL to cURL
 * @param array $options Additional cURL options
 * @return mixed The cURL response
 */
function curlURL($url, $options = [])
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 5);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	if (!empty($options)) {
		foreach ($options as $option => $value) {
			curl_setopt($ch, $option, $value);
		}
	}
	if (!array_key_exists(CURLOPT_USERAGENT, $options)) {
		curl_setopt($ch, CURLOPT_USERAGENT, 'Upvote RSS:' . UPVOTE_RSS_VERSION . ' (+https://www.upvote-rss.com)');
	}
	$data = curl_exec($ch);
	curl_close($ch);
	if (
		strpos($data, '403 Forbidden') !== false ||
		strpos($data, 'Access Denied') !== false ||
		strpos($data, 'Please enable JS and disable any ad blocker') !== false
	) {
		$user_agents = [
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3',
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:129.0) Gecko/20100101 Firefox/129.0'
		];
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_USERAGENT, $user_agents[array_rand($user_agents)]);
		if (!empty($options)) {
			foreach ($options as $option => $value) {
				curl_setopt($ch, $option, $value);
			}
		}
		$data = curl_exec($ch);
		curl_close($ch);
	}
	return $data;
}


/**
 * Return a resized image URL
 * @param string  $url The URL to get the domain from
 * @param int     $max_width The maximum width of the image
 * @param int     $max_height The maximum height of the image
 * @return array  The resized image URL, width, and height
 */
function resizeImage($image_url, $max_width, $max_height) {
	// If the image URL is empty, return an empty array
	if (empty($image_url)) {
		return [];
	}
	$log = new \CustomLogger;
	// Get the image extension
	$image_extension = pathinfo($image_url, PATHINFO_EXTENSION);
	// If the image exists in the cache, return the URL of the cached image file as the first element of the array
	$cache_object_key = $image_url;
	$cache_object_key = str_replace('.' . $image_extension, '', $cache_object_key);
	$cache_object_key = str_replace(['http://', 'https://', 'www.'], '', $cache_object_key);
	$cache_object_key = str_replace(['/', '.'], '-', $cache_object_key);
	$cache_object_key = filter_var($cache_object_key, FILTER_SANITIZE_ENCODED);
	$cache_object_key = substr($cache_object_key, 0, 100) . '-' . $max_width . 'x' . $max_height;
	$cache_directory = 'cache/images/';
	$cache_file = $cache_directory . $cache_object_key . '.' . $image_extension;
	$request_uri = preg_replace('/\?.*/', '', $_SERVER['REQUEST_URI']);
	$url_to_cache_file = getProtocol() . $_SERVER['HTTP_HOST'] . $request_uri . $cache_file;
	if (file_exists($cache_file)) {
		$image_size = getimagesize($cache_file);
		return [
			$url_to_cache_file,
			$image_size[0],
			$image_size[1]
		];
	}
	// If the URL is not an image, return the original image URL
	$headers = get_headers($image_url, 1);
	if (empty($headers)) {
		return [$image_url, '', ''];
	}
	$content_type = $headers['Content-Type'] ?? $headers['content-type'] ?? '';
	if (
		!$content_type ||
		!preg_match('/image/', $content_type)
		) {
		return [$image_url, '', ''];
	}
	// Get image type from headers
	$image_type = $content_type;
	$image_type = explode('/', $image_type);
	$image_type = $image_type[1] ?? '';
	// If the image type is not supported, return the original image URL
	if (
		$image_type != 'png' &&
		$image_type != 'jpeg' &&
		$image_type != 'jpg' &&
		$image_type != 'webp' &&
		$image_type != 'gif'
	) {
		return [$image_url, '', ''];
	}
	$image_size = getimagesize($image_url);
	// If the image size is empty, return the original image URL
	if (empty($image_size)) {
		return [$image_url, '', ''];
	}
	$image_width = $image_size[0];
	$image_height = $image_size[1];
	// If the image is smaller than the max height and width, return the original image URL
	if ($image_width <= $max_width && $image_height <= $max_height) {
		return [$image_url, '', ''];
	}
	// If the GD library is not available, return the original image URL
	if (!function_exists('imagecreatefromstring')) {
		return [$image_url, '', ''];
	}
	$log->info("RSS feed channel image is too big: $image_url. Attempting to resize image.");
	// Get the image data
	$image_data = file_get_contents($image_url);
	// Create an image from the image data
	$image = imagecreatefromstring($image_data);
	// Calculate the new image dimensions
	if ($image_width > $image_height) {
		$new_width = $max_width;
		$new_height = $image_height * ($max_width / $image_width);
		$new_height = floor($new_height);
		if($new_height > $max_height) {
			$new_height = $max_height;
			$new_width = $image_width * ($max_height / $image_height);
			$new_width = floor($new_width);
		}
	} else {
		$new_height = $max_height;
		$new_width = $image_width * ($max_height / $image_height);
		$new_width = floor($new_width);
		if($new_width > $max_width) {
			$new_width = $max_width;
			$new_height = $image_height * ($max_width / $image_width);
			$new_height = floor($new_height);
		}
	}
	// Create a new image
	if ($image_extension == 'png') {
		$new_image = imagecreatetruecolor($new_width, $new_height);
		$background = imagecolorallocate($new_image, 255, 255, 255);
		imagecolortransparent($new_image, $background);
		imagealphablending($new_image, false);
		imagesavealpha($new_image, true);
	} elseif ($image_extension == 'gif') {
		$new_image = imagecreatetruecolor($new_width, $new_height);
		$background = imagecolorallocate($new_image, 255, 255, 255);
		imagecolortransparent($new_image, $background);
		imagealphablending($new_image, false);
		imagesavealpha($new_image, true);
	} else {
		$new_image = imagecreatetruecolor($new_width, $new_height);
	}
	// Resize the image
	imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $image_width, $image_height);
	// Create the cache directory if it does not exist
	if (!is_dir($cache_directory)) {
		try {
			mkdir($cache_directory, 0755, true);
		} catch (\Exception $e) {
			$log->error("Failed to create cache directory: " . $e->getMessage());
			return [$image_url, '', ''];
		}
	}
	// Save the new image to the cache
	if ($image_extension == 'png') {
		imagepng($new_image, $cache_file);
	}
	elseif (
		$image_extension == 'jpeg' ||
		$image_extension == 'jpg' ||
		$image_extension == 'webp'
	) {
		imagejpeg($new_image, $cache_file);
	}
	elseif ($image_extension == 'gif') {
		imagegif($new_image, $cache_file);
	}
	// Free up memory
	imagedestroy($image);
	imagedestroy($new_image);
	// Log that the image was resized
	$log->info("Successfully resized image: $image_url");
	// Return the URL of the new image
	return [
		$url_to_cache_file,
		$new_width,
		$new_height
	];
}


/**
 * Check if a remote file exists
 * @param string $url The URL to check
 * @return bool Whether the file exists
 */
function remote_file_exists($url = '')
{
	if(!$url) {
		return false;
	}
	$file_headers = @get_headers($url);
	return $file_headers && strpos($file_headers[0], '200') !== false;
}


/**
 * Get HTTP status code
 * @param string $url The URL to check
 * @return int The HTTP status code
 */
function getHttpStatus($url)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_NOBODY, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
	curl_exec($ch);
	$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	return $http_status ?? 0;
}


/**
 * Get remote file size in bytes
 * @param string $url The URL to check
 * @return int The file size in bytes
 */
function getRemoteFileSize($url)
{
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_NOBODY, true);
	curl_exec($ch);
	$fileSize = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
	curl_close($ch);
	return (int) $fileSize;
}


/**
 * Get estimated reading time in minutes
 * @param int $word_count The word count
 * @return int The estimated reading time in minutes
 */
function getEstimatedReadingTime($word_count)
{
	if ($word_count && is_numeric($word_count)) {
		$reading_minutes = $word_count / 200;
		if ($word_count < 20) $reading_minutes = 0;
		elseif (0.3 <= $reading_minutes && $reading_minutes < 1) $reading_minutes = 1;
		elseif ($reading_minutes < 2) $reading_minutes = ceil($reading_minutes);
		else $reading_minutes = round($reading_minutes);
		return $reading_minutes;
	} else {
		return false;
	}
}


/**
 * Get directory size in bytes
 * @param string $dir The directory path
 * @return int The directory size in bytes
 * @link https://www.a2zwebhelp.com/folder-size-php
 */
function directorySize($dir)
{
	if (!is_dir($dir)) return 0;
	$count_size = 0;
	$count = 0;
	$directory_array = scandir($dir);
	foreach ($directory_array as $key => $filename) {
		if ($filename != ".." && $filename != ".") {
			if (is_dir($dir . "/" . $filename)) {
				$new_folder_size = directorySize($dir . "/" . $filename);
				$count_size = $count_size + $new_folder_size;
			} elseif (is_file($dir . "/" . $filename)) {
				$count_size = $count_size + filesize($dir . "/" . $filename);
				$count++;
			}
		}
	}
	return $count_size;
}


/**
 * Delete directory contents
 * @param string $src The directory path
 */
function deleteDirectoryContents($src, $exclude = [])
{
	if (!is_dir($src)) return;
	$directory = opendir($src);
	while (false !== ($file = readdir($directory))) {
		if (($file != '.') && ($file != '..') && !in_array($file, $exclude)) {
			$full = $src . '/' . $file;
			if (is_dir($full)) {
				deleteDirectoryContents($full, $exclude);
			} else {
				unlink($full);
			}
		}
	}
	closedir($directory);
}


/**
 * Normalize to a Unix timestamp
 * @param mixed $timestamp The timestamp to normalize
 * @return int The normalized timestamp
 */
function normalizeTimestamp($timestamp)
{
	if (!is_numeric($timestamp)) {
		$timestamp = strtotime($timestamp);
	}
	return $timestamp;
}


/**
 * Output how long ago a post was submitted
 * @param int $date The date the post was submitted
 * @return string The time elapsed string
 */
function timeElapsedString($date)
{
	$elapsed_time = time() - $date;
	if ($elapsed_time < 2) {
		return 'Just now';
	}
	$a = array(
		12 * 30 * 24 * 60 * 60  =>  'year',
		30 * 24 * 60 * 60       =>  'month',
		24 * 60 * 60            =>  'day',
		60 * 60                 =>  'hour',
		60                      =>  'minute',
		1                       =>  'second'
	);
	foreach ($a as $secs => $str) {
		$d = $elapsed_time / $secs;
		if ($d >= 1) {
			$r = round($d);
			return $r . ' ' . $str . ($r > 1 ? 's' : '') . ' ago';
		}
	}
}


/**
 * Sort an array of objects or arrays by a key or property
 * @param mixed  $a     The first object or array
 * @param mixed  $b     The second object or array
 * @param string $key   The key or property to sort by
 * @param string $order The order to sort by (asc or desc)
 * @return int          The sorted array
 */
function sortByKeyOrProperty($a, $b, $key, $order = 'asc') {
	if (is_array($a)) {
		if ($order == 'asc') {
			return $a[$key] <=> $b[$key];
		} else {
			return $b[$key] <=> $a[$key];
		}
	} elseif (is_object($a)) {
		if ($order == 'asc') {
			return $a->$key <=> $b->$key;
		} else {
			return $b->$key <=> $a->$key;
		}
	}
}


/**
 * Format score
 * @param int $score The score to format
 * @return string The formatted score
 */
function formatScore($score)
{
	if ($score >= 1000) {
		return round($score / 1000, 1) . "k";
	} else {
		return $score;
	}
}


/**
 * Format size in bytes
 * @param int $bytes The size in bytes
 * @return string The formatted size
 * @link https://www.a2zwebhelp.com/folder-size-php
 */
function formatByteSize($bytes)
{
	$kb = 1024;
	$mb = $kb * 1024;
	$gb = $mb * 1024;
	$tb = $gb * 1024;
	if (($bytes >= 0) && ($bytes < $kb)) {
		return $bytes . " B";
	} elseif (($bytes >= $kb) && ($bytes < $mb)) {
		return ceil($bytes / $kb) . " KB";
	} elseif (($bytes >= $mb) && ($bytes < $gb)) {
		return ceil($bytes / $mb) . " MB";
	} elseif (($bytes >= $gb) && ($bytes < $tb)) {
		return ceil($bytes / $gb) . " GB";
	} elseif ($bytes >= $tb) {
		return ceil($bytes / $tb) . " TB";
	} else {
		return $bytes . " B";
	}
}
