<?php

// Reject request unless it came from AJAX
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
	header('HTTP/1.0 403 Forbidden');
	exit;
}

// Setup
$include_progress = true;
include 'app.php';
$log = new \CustomLogger;

// Get $_POST data
$data = json_decode(file_get_contents('php://input'), true);

// Run clearCache if requested
if ($data['clearCache'] ?? false) {
	if (REDIS) {
		$keys = $client->keys('upvote_rss*');
		foreach ($keys as $key) {
			if (CLEAR_WEBPAGES_WITH_CACHE || strpos($key, 'upvote_rss:webpages') === false) {
				$client->del($key);
			}
		}
	}
	$exclude = [];
	if (!CLEAR_WEBPAGES_WITH_CACHE) {
		$exclude[] = 'webpages';
	}
	deleteDirectoryContents('cache', $exclude);
	if (function_exists('opcache_reset')) {
		opcache_reset();
	}
	$log_message = 'Cache cleared';
	if (!CLEAR_WEBPAGES_WITH_CACHE) {
		$log_message .= ' (excluding webpages)';
	}
	$log->info($log_message);

	header('Content-Type: application/json');
	echo json_encode(array(
		"cacheSize" => getCacheSize(),
	));
	exit;
}

// Run getProgress if requested
if ($data['getProgress'] ?? false) {
	$platform  = $data['platform'] ? str_replace('-', '', $data['platform']) : null;
	$instance  = $data['instance'] ?? null;
	$community = $data['community'] ?? null;
	$subreddit = $data['subreddit'] ?? null;
	if ($platform == 'reddit') {
		$community = $subreddit;
	}
	$progress_cache_object_key = "progress_" . $platform . "_" . $community;
	$progress_cache_directory  = "cache/progress/";
	$progress                  = cacheGet($progress_cache_object_key, $progress_cache_directory);
	if (empty($progress)) {
		$progress = [
			"current" => 1,
			"total"   => 100,
		];
	}
	$progress = $progress['current'] / $progress['total'];
	$progress = floor($progress * 100);
	$progress = min($progress, 99);
	header('Content-Type: application/json');
	echo json_encode(array(
		"progress" => $progress,
	));
	exit;
}

// Run getSubreddits if requested
if ($data['getSubreddits'] ?? false) {
	$subreddit = $data['subreddit'] ?? null;
	if (
		empty($subreddit) ||
		!is_string($subreddit) ||
		strlen($subreddit) < 3
	) {
		header('Content-Type: application/json');
		echo json_encode(array(
			"subreddits" => [],
		));
		exit;
	}
	try {
		$reddit_auth = new \Auth\Reddit();
		$auth_token = $reddit_auth->getToken();
	} catch (\Exception $e) {
		header('Content-Type: application/json');
		echo json_encode(array(
			"error" => $e->getMessage(),
		));
		exit;
	}
	$curl_response = curlURL(
		"https://oauth.reddit.com/api/search_subreddits.json?query=$subreddit",
		[
			CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $auth_token),
			CURLOPT_USERAGENT => 'web:Upvote RSS:' . UPVOTE_RSS_VERSION . ' (by /u/' . REDDIT_USER . ')',
			CURLOPT_POST => 1,
		]
	);
	$subreddits = json_decode($curl_response, true);
	if (!empty($subreddits['error'])) {
		returnJSONerror($subreddits['error']);
	}
	header('Content-Type: application/json');
	if (empty($subreddits['subreddits'])) {
		echo json_encode(array(
			"subreddits" => [],
		));
		exit;
	}
	echo json_encode(array(
		"subreddits" => $subreddits['subreddits'],
	));
	exit;
}

// Run getPosts if requested
if ($data['getPosts'] ?? false) {
	$platform              = $data['platform'] ?? null;
	$subreddit             = $data['subreddit'] ?? null;
	$instance              = $data['instance'] ?? null;
	$community             = $data['community'] ?? null;
	$community_type        = $data['communityType'] ?? null;
	$filter_nsfw           = $data['filterNSFW'] ?? false;
	$blur_nsfw             = $data['blurNSFW'] ?? false;
	$filter_old_posts 		 = $data['filterOldPosts'] ?? false;
	$post_cutoff_days 		 = $data['postCutoffDays'] ?? 0;
	$filter_type           = $data['filterType'] ?? 'score';
	$score                 = $data['score'];
	$threshold             = $data['threshold'];
	$averagePostsPerDay    = $data['averagePostsPerDay'];
	$filter_value          = $data[$filter_type];

	// Check if platform is set
	if (empty($platform)) {
		returnJSONerror('No platform set');
	}

	// switch case for platform
	switch ($platform) {
		case 'reddit':
			$community = new Community\Reddit($subreddit);
			break;
		case 'hacker-news':
			$community = new Community\HackerNews($community);
			$instance  = 'news.ycombinator.com';
			break;
		case 'lemmy':
			$instance  = $data['instance'] ?? DEFAULT_LEMMY_INSTANCE;
			$community = $data['community'] ?? DEFAULT_LEMMY_COMMUNITY;
			$community = new Community\Lemmy($community, $instance);
			break;
		case 'lobsters':
			$community = new Community\Lobsters($community, $community_type);
			$instance  = 'lobste.rs';
			break;
		case 'mbin':
			$instance  = $data['instance'] ?? DEFAULT_MBIN_INSTANCE;
			$community = $data['community'] ?? DEFAULT_MBIN_COMMUNITY;
			$community = new Community\Mbin($community, $instance);
			break;
		default:
			returnJSONerror('Invalid platform');
	}

	// Check auth status
	if ($platform === 'reddit') {
		try {
			$reddit_auth = new \Auth\Reddit();
			$auth_token = $reddit_auth->getToken();
		} catch (\Exception $e) {
			returnJSONerror($e->getMessage());
		}
	}

	// Check if instance is valid
	if (!$community->is_instance_valid) {
		$message = "Invalid instance.";
		if ($platform === 'lemmy' || $platform === 'mbin') {
			$message = "\"$instance\" it either unavailable or doesn't appear to be a valid " . ucfirst($platform) . " instance.";
		}
		returnJSONerror($message);
	}
	// Check if community is private
	if ($platform == 'reddit' && $community->is_private) {
		$message = "\"$community->slug\" is a private subreddit.";
		returnJSONerror($message);
	}
	// Check if community is valid
	if (!$community->is_community_valid) {
		$message = "Invalid $platform community.";
		if ($platform === 'reddit') {
			$message = "\"$community->slug\" doesn't appear to be a valid subreddit.";
		} elseif ($platform === 'lobsters' && $community_type === 'tag') {
			$message = "\"$community->slug\" doesn't appear to be a valid Lobsters tag.";
		} elseif ($platform === 'lobsters' && $community_type === 'category') {
			$message = "\"$community->slug\" doesn't appear to be a valid Lobsters category.";
		} elseif ($platform === 'lobsters' && $community_type === 'all') {
			$message = "\"$community->slug\" doesn't appear to be a valid Lobsters community.";
		} elseif (
			$platform === 'lemmy' || $platform === 'mbin'
		) {
			$message = "\"$community->slug\" doesn't appear to be a valid community at \"$instance\".";
		}
		returnJSONerror($message);
	} else {
		// Return posts
		$posts = $community->getFilteredPostsByValue($filter_type, $filter_value, $filter_nsfw, $blur_nsfw, $filter_old_posts, $post_cutoff_days);
		if (!empty($posts['error'])) {
			returnJSONerror($posts['error']);
		}
		$filtered_posts = [];
		if (!empty($posts)) {
			foreach ($posts as $post) {
				if (!empty($post->score)) {
					$post->score_formatted = formatScore($post->score);
					$time_unix             = !empty($post->time) ? timeElapsedString(normalizeTimestamp($post->time)) : null;
					$post->relative_date   = $time_unix;
					$post->time_rfc_822    = !empty($post->time) ? gmdate(DATE_RFC2822, normalizeTimestamp($post->time)) : null;
					$filtered_posts[] = $post;
				}
			}
		}
		$cache_size = getCacheSize();
		header('Content-Type: application/json');
		echo json_encode(array(
			'filtered_posts'        => $filtered_posts,
			'community_slug'        => $community->slug,
			'community_instance'    => $community->instance,
			'community_url'         => $community->url,
			'community_icon'        => $community->icon,
			'community_banner'      => $community->banner_image,
			'community_description' => $community->description,
			'community_subscribers' => $community->subscribers,
			'community_nsfw'        => $community->nsfw,
			'community_created'     => $community->created,
			'community_title'       => $community->title,
			'community_name'        => $community->name,
			'platform_icon'         => $community->platform_icon,
			'cacheSize'             => $cache_size,
		));
		exit;
	}
}
