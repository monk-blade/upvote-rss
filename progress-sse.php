<?php

// Security checks to prevent external access
// Check if request comes from the same origin
$allowed_referers = [
	$_SERVER['HTTP_HOST'] ?? '',
	'localhost',
	'127.0.0.1'
];

$referer = $_SERVER['HTTP_REFERER'] ?? '';
$referer_host = '';
if (!empty($referer)) {
	$parsed_referer = parse_url($referer);
	$referer_host = $parsed_referer['host'] ?? '';
}

$is_valid_referer = false;
foreach ($allowed_referers as $allowed) {
	if (!empty($allowed) && ($referer_host === $allowed || strpos($referer_host, $allowed) !== false)) {
		$is_valid_referer = true;
		break;
	}
}

// Reject external requests
if (empty($referer) || !$is_valid_referer) {
	header('HTTP/1.0 403 Forbidden');
	exit('Access denied: External access not allowed');
}

// Simple rate limiting per IP
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rate_limit_file = sys_get_temp_dir() . '/sse_rate_limit_' . md5($client_ip);
$current_time = time();
$rate_limit_window = 60; // 1 minute window
$max_requests = 10; // Max 10 connections per minute

if (file_exists($rate_limit_file)) {
	$rate_data = json_decode(file_get_contents($rate_limit_file), true);
	if ($rate_data && ($current_time - $rate_data['timestamp']) < $rate_limit_window) {
		if ($rate_data['count'] >= $max_requests) {
			header('HTTP/1.0 429 Too Many Requests');
			exit('Rate limit exceeded');
		}
		$rate_data['count']++;
	} else {
		$rate_data = ['timestamp' => $current_time, 'count' => 1];
	}
} else {
	$rate_data = ['timestamp' => $current_time, 'count' => 1];
}
file_put_contents($rate_limit_file, json_encode($rate_data));

// Set headers for SSE and configure output buffering immediately after security checks
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Disable all output buffering and ensure immediate output
while (ob_get_level()) {
	ob_end_clean();
}

// Disable automatic output buffering
ini_set('output_buffering', 'off');
ini_set('implicit_flush', true);

// Ensure immediate output
ob_implicit_flush(true);

// Setup
include 'app.php';

// Function to send SSE data
function sendSSE($data, $event = 'message') {
	echo "event: $event\n";
	echo "data: " . json_encode($data) . "\n\n";

	// Force immediate output
	if (ob_get_level()) {
		ob_flush();
	}
	flush();
}

// Get query parameters
$platform = $_GET['platform'] ?? null;
$instance = $_GET['instance'] ?? null;
$community = $_GET['community'] ?? null;
$subreddit = $_GET['subreddit'] ?? null;

if ($platform == 'reddit') {
	$community = $subreddit;
}

if (empty($platform)) {
	sendSSE(['error' => 'Missing platform parameter'], 'error');
	exit;
}

if (empty($community)) {
	sendSSE(['error' => 'Missing community parameter'], 'error');
	exit;
}

$progress_cache_object_key = "progress_" . str_replace('-', '', $platform) . "_" . $community;
$progress_cache_directory = "progress";
$last_progress = -1;
$max_iterations = 200; // Maximum 200 iterations (20 seconds at 100ms intervals)
$iteration = 0;

// Send initial connection confirmation
sendSSE(['connected' => true, 'timestamp' => time()], 'connect');

// Initial fetch of progress data
$progress_data = cache()->get($progress_cache_object_key, $progress_cache_directory);

if (!empty($progress_data) &&
		isset($progress_data['current']) &&
		isset($progress_data['total']) &&
		$progress_data['total'] > 0
	) {
	$progress = $progress_data['current'] / $progress_data['total'];
	$progress = floor($progress * 100);
} else {
	$progress = 0;
}

// Remove progress data from cache when complete
if ($progress >= 100) {
	cache()->delete($progress_cache_object_key, $progress_cache_directory);
	sendSSE(['progress' => 100, 'completed' => true, 'timestamp' => time()], 'complete');
	exit;
}

$data = [
	'progress' => $progress,
	'cacheSize' => cache()->getTotalCacheSize(),
	'timestamp' => time()
];
sendSSE($data, 'progress');
$last_progress = $progress;

// Main SSE loop
while ($iteration < $max_iterations) {
	$progress_data = cache()->get($progress_cache_object_key, $progress_cache_directory);

	if (!empty($progress_data) &&
		isset($progress_data['current']) &&
		isset($progress_data['total']) &&
		$progress_data['total'] > 0
	) {
		$progress = $progress_data['current'] / $progress_data['total'];
		$progress = floor($progress * 100);
	} else {
		$progress = 0;
	}

	// If progress reset to 0 after being greater than 0, treat as complete
	if ($progress === 0 && $last_progress > 0) {
		cache()->delete($progress_cache_object_key, $progress_cache_directory);
		sendSSE(['progress' => 100, 'completed' => true, 'timestamp' => time()], 'complete');
		exit;
	}

	// Send update if progress changed
	if ($progress != $last_progress) {
		$data = [
			'progress' => $progress,
			'cacheSize' => cache()->getTotalCacheSize(),
			'timestamp' => time()
		];

		sendSSE($data, 'progress');
		$last_progress = $progress;

		// If progress reaches 100, send completion and exit
		if ($progress >= 100) {
			// Remove progress data from cache when complete
			cache()->delete($progress_cache_object_key, $progress_cache_directory);
			sendSSE(['progress' => 100, 'completed' => true, 'timestamp' => time()], 'complete');
			exit;
		}
	}

	// Check if client disconnected
	if (connection_aborted()) {
		cache()->delete($progress_cache_object_key, $progress_cache_directory);
		break;
	}

	// Wait 100ms before next check (faster polling)
	usleep(100000);
	$iteration++;
}

// Send final message if we reached max iterations
if ($iteration >= $max_iterations) {
	sendSSE(['timeout' => true, 'message' => 'Progress monitoring timed out'], 'timeout');
}

exit;
