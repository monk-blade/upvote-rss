<?php

// Get environment variables from .env file if it exists
if (file_exists('.env')) {
  $_ENV = parse_ini_file('.env');
}


// Prevent PHP warnings and deprecation notices
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);


// Set defaults
const UPVOTE_RSS_VERSION = '1.0.6';
const DEFAULT_PLATFORM = 'lemmy';
const DEFAULT_SUBREDDIT = 'technology';
const DEFAULT_HACKER_NEWS_INSTANCE = 'news.ycombinator.com';
const DEFAULT_HACKER_NEWS_COMMUNITY = 'beststories';
const DEFAULT_LEMMY_INSTANCE = 'lemmy.world';
const DEFAULT_LEMMY_COMMUNITY = 'Technology';
const DEFAULT_MBIN_INSTANCE = 'fedia.io';
const DEFAULT_MBIN_COMMUNITY = 'random';


// Debug
$debug = $_SERVER["DEBUG"] ?? $_ENV["DEBUG"] ?? false;
if ($debug) {
  ini_set('display_errors', '0');
}


// Maximum execution time
$max_execution_time = $_SERVER["MAX_EXECUTION_TIME"] ?? $_ENV["MAX_EXECUTION_TIME"] ?? 60;
define('MAX_EXECUTION_TIME', $max_execution_time);
if (
  !empty(MAX_EXECUTION_TIME)
  && is_numeric(MAX_EXECUTION_TIME)
  && MAX_EXECUTION_TIME > 0
) {
  ini_set('max_execution_time', MAX_EXECUTION_TIME);
}


// Timezone
$timezone = $_SERVER["TZ"] ?? $_ENV["TZ"] ?? 'Europe/London';
date_default_timezone_set($timezone);


// Variables
$instance = null;
$community = !empty($_GET["community"]) ? strip_tags(trim($_GET["community"])) : null;
$score_filter_available = true;
$threshold_filter_available = true;
$average_posts_per_day_filter_available = true;
$filter_type = 'averagePostsPerDay';
$filter_value = null;


// Demo mode
$demo_mode = false;
if (isset($_SERVER["DEMO_MODE"]) && $_SERVER["DEMO_MODE"] == true) {
  $demo_mode = true;
} elseif (isset($_ENV["DEMO_MODE"]) && $_ENV["DEMO_MODE"] == true) {
  $demo_mode = true;
}
define('DEMO_MODE', $demo_mode);


// Platform
$platform = !empty($_GET["platform"]) ? strip_tags(trim($_GET["platform"])) : DEFAULT_PLATFORM;
if (DEMO_MODE && $platform == 'reddit') $platform = DEFAULT_PLATFORM;
if (!empty($_GET["subreddit"])) $platform = 'reddit';
define('PLATFORM', $platform);


// Auth
$encryption_key = hash('sha256', date('Y-m-d', strtotime('-1 month')));
define('ENCRYPTION_KEY', $encryption_key);
define('CIPHERING', "AES-128-CTR");
define('IV_LENGTH', openssl_cipher_iv_length(CIPHERING));
$encryption_iv = substr(hash('sha256', date('Y-m-d', strtotime('+1 month'))), 0, 16);
define('ENCRYPTION_IV', $encryption_iv);
define('ENCRYPTION_OPTIONS', 0);


// Reddit
// Username
$reddit_user = $_SERVER["REDDIT_USER"] ?? $_ENV["REDDIT_USER"] ?? null;
$reddit_user = !empty($_GET["reddit_user"]) ? strip_tags(trim($_GET["reddit_user"])) : $reddit_user;
define('REDDIT_USER', $reddit_user);
// Client ID
$reddit_client_id = $_SERVER["REDDIT_CLIENT_ID"] ?? $_ENV["REDDIT_CLIENT_ID"] ?? null;
$reddit_client_id = !empty($_GET["reddit_client_id"]) ? strip_tags(trim($_GET["reddit_client_id"])) : $reddit_client_id;
define('REDDIT_CLIENT_ID', $reddit_client_id);
$client_id_encrypted = null;
if (REDDIT_CLIENT_ID !== null) {
  $client_id_encrypted = openssl_encrypt(REDDIT_CLIENT_ID, CIPHERING, ENCRYPTION_KEY, ENCRYPTION_OPTIONS, ENCRYPTION_IV);
  $client_id_encrypted = base64_encode($client_id_encrypted);
}
define('REDDIT_CLIENT_ID_ENCRYPTED', $client_id_encrypted);
// Client Secret
$reddit_client_secret = $_SERVER["REDDIT_CLIENT_SECRET"] ?? $_ENV["REDDIT_CLIENT_SECRET"] ?? null;
$reddit_client_secret = !empty($_GET["reddit_client_secret"]) ? strip_tags(trim($_GET["reddit_client_secret"])) : $reddit_client_secret;
define('REDDIT_CLIENT_SECRET', $reddit_client_secret);
// Reddit domain
define('REDDIT_DEFAULT_DOMAIN', 'www.reddit.com');
define('REDDIT_DEFAULT_DOMAIN_OVERRIDE', 'old.reddit.com');
$reddit_domain = $_GET["redditDomain"] ?? REDDIT_DEFAULT_DOMAIN;
define('REDDIT_DOMAIN', $reddit_domain);
// Subreddit
$subreddit = DEFAULT_SUBREDDIT;
if (!empty($_GET["subreddit"])) {
  $subreddit = strip_tags(trim($_GET["subreddit"]));
  $instance = 'reddit.com';
  $community = '';
}
define('SUBREDDIT', $subreddit);


// Hacker News
if (PLATFORM == 'hacker-news') {
  $instance = DEFAULT_HACKER_NEWS_INSTANCE;
  $threshold_filter_available = false;
  if (empty($community)) {
    if (!empty($_GET["type"])) $community = strip_tags(trim($_GET["type"]));
    else $community = DEFAULT_HACKER_NEWS_COMMUNITY;
  }
}


// Lemmy
if (PLATFORM == 'lemmy') {
  $instance = !empty($_GET["instance"]) ? strip_tags(trim($_GET["instance"])) : DEFAULT_LEMMY_INSTANCE;
  $community = !empty($_GET["community"]) ? strip_tags(trim($_GET["community"])) : DEFAULT_LEMMY_COMMUNITY;
}


// Mbin
if (PLATFORM == 'mbin') {
  $instance = !empty($_GET["instance"]) ? strip_tags(trim($_GET["instance"])) : DEFAULT_MBIN_INSTANCE;
  $community = !empty($_GET["community"]) ? strip_tags(trim($_GET["community"])) : DEFAULT_MBIN_COMMUNITY;
}


// Instance
define('INSTANCE', $instance);


// Community
define('COMMUNITY', $community);


// Filters Available
define('SCORE_FILTER_AVAILABLE', $score_filter_available);
define('THRESHOLD_FILTER_AVAILABLE', $threshold_filter_available);
define('AVERAGE_POSTS_PER_DAY_FILTER_AVAILABLE', $average_posts_per_day_filter_available);


// Score
$score = 100;
if (!empty($_GET["score"])) {
  $filter_type = 'score';
  $score = strip_tags(trim($_GET["score"]));
  $filter_value = $score;
}
define('SCORE', $score);


// Treshold
$percentage = 100;
if (!empty($_GET["threshold"])) {
  $filter_type = 'threshold';
  $percentage = strip_tags(trim($_GET["threshold"]));
  $filter_value = $percentage;
}
define('PERCENTAGE', $percentage);


// Posts per day
$average_posts_per_day = 3;
if (isset($_GET["averagePostsPerDay"])) {
  $filter_type = 'averagePostsPerDay';
  $average_posts_per_day = strip_tags(trim($_GET["averagePostsPerDay"]));
  $filter_value = $average_posts_per_day;
}
define('POSTS_PER_DAY', $average_posts_per_day);


// Filter type
define('FILTER_TYPE', $filter_type);


// Filter value
define('FILTER_VALUE', $filter_value);


// Show score
$show_score = false;
if (isset($_GET["showScore"])) $show_score = true;
define('SHOW_SCORE', $show_score);


// Include content
$include_content = true;
if (isset($_GET["content"]) && $_GET["content"] == 0) {
  $include_content = false;
}
define('INCLUDE_CONTENT', $include_content);


// OpenAI API
$openai_api_key = $_SERVER["OPENAI_API_KEY"] ?? $_ENV["OPENAI_API_KEY"] ?? null;
define('OPENAI_API_KEY', $openai_api_key);
$openai_api_model = $_SERVER["OPENAI_API_MODEL"] ?? $_ENV["OPENAI_API_MODEL"] ?? 'gpt-4o-mini';
define('OPENAI_API_MODEL', $openai_api_model);


// Ollama API
$ollama_url = $_SERVER["OLLAMA_URL"] ?? $_ENV["OLLAMA_URL"] ?? null;
define('OLLAMA_URL', $ollama_url);
$ollama_model = $_SERVER["OLLAMA_MODEL"] ?? $_ENV["OLLAMA_MODEL"] ?? null;
define('OLLAMA_MODEL', $ollama_model);


// Summary enabled
$summary_enabled = false;
if (OPENAI_API_KEY !== null) $summary_enabled = true;
if (OLLAMA_URL !== null && OLLAMA_MODEL !== null) $summary_enabled = true;
define('SUMMARY_ENABLED', $summary_enabled);


// Summary
$include_summary = false;
if (isset($_GET["summary"])) $include_summary = true;
define('INCLUDE_SUMMARY', $include_summary);


// Summary prompt
$summary_system_prompt = "You are web article summarizer. Use the following pieces of retrieved context to answer the question. Do not answer from your own knowledge base. If the answer isn't present in the knowledge base, refrain from providing an answer based on your own knowledge. Instead, say nothing. Output should be limited to one paragraph with a maximum of three sentences, and keep the answer concise. Always complete the last sentence. Do not hallucinate or make up information.";
$summary_system_prompt = $_SERVER["SUMMARY_SYSTEM_PROMPT"] ?? $_ENV["SUMMARY_SYSTEM_PROMPT"] ?? $summary_system_prompt;
define('SUMMARY_SYSTEM_PROMPT', $summary_system_prompt);


// Summary temperature
$summary_temperature = $_SERVER["SUMMARY_TEMPERATURE"] ?? $_ENV["SUMMARY_TEMPERATURE"] ?? 0.4;
define('SUMMARY_TEMPERATURE', $summary_temperature);


// Comments
$include_comments = false;
$comments = 0;
if (!empty($_GET["comments"])) {
  $include_comments = true;
  $comments = strip_tags(trim($_GET["comments"]));
}
define('INCLUDE_COMMENTS', $include_comments);
define('COMMENTS', $comments);


// NSFW
$filter_nsfw = false;
$blur_nsfw = false;
if (isset($_GET["filterNSFW"])) $filter_nsfw = true;
if (isset($_GET["blurNSFW"])) $blur_nsfw = true;
define('FILTER_NSFW', $filter_nsfw);
define('BLUR_NSFW', $blur_nsfw);


// Filter old posts
$filter_old_posts = false;
$post_cutoff_days = 0;
if (isset($_GET["filterOldPosts"]) && $_GET["filterOldPosts"]) {
  $filter_old_posts = true;
  $post_cutoff_days = strip_tags(trim($_GET["filterOldPosts"]));
  $post_cutoff_days = is_numeric($_GET["filterOldPosts"]) ? $_GET["filterOldPosts"] : 0;
}
define('FILTER_OLD_POSTS', $filter_old_posts);
define('POST_CUTOFF_DAYS', $post_cutoff_days);


// Browserless
$browserless_url = $_SERVER["BROWSERLESS_URL"] ?? $_ENV["BROWSERLESS_URL"] ?? '';
$browserless_url = rtrim($browserless_url, '/');
$browserless_token = $_SERVER["BROWSERLESS_TOKEN"] ?? $_ENV["BROWSERLESS_TOKEN"] ?? '';
define('BROWSERLESS_URL', $browserless_url);
define('BROWSERLESS_TOKEN', $browserless_token);


// Readability-JS
$readability_js_url = $_SERVER["READABILITY_JS_URL"] ?? $_ENV["READABILITY_JS_URL"] ?? '';
define('READABILITY_JS_URL', $readability_js_url);


// Mercury Parser URL, e.g. https://mercuryparser.example.com
$mercury_url = $_SERVER["MERCURY_URL"] ?? $_ENV["MERCURY_URL"] ?? null;
define('MERCURY_URL', $mercury_url);


// Cache expiration times
define("PROGRESS_EXPIRATION", 5);                     // 5 seconds
define("AUTH_EXPIRATION", 60 * 59);                   // 1 hour
define("ABOUT_EXPIRATION", 60 * 59 * 24 * 7);         // 1 week
define("HOT_POSTS_EXPIRATION", 60 * 59);              // 1 hour
define("TOP_POSTS_EXPIRATION", 60 * 59);              // 1 hour
define("TOP_DAILY_POSTS_EXPIRATION", 60 * 59);        // 1 hour
define("TOP_MONTHLY_POSTS_EXPIRATION", 60 * 59 * 24); // 1 day
define("COMMENTS_EXPIRATION", 60 * 59 * 4);           // 4 hours
define("GALLERY_EXPIRATION", 60 * 59 * 24 * 7);       // 1 week
define("RSS_EXPIRATION", 60 * 59);                    // 1 hour
define("WEBPAGE_EXPIRATION", 60 * 59 * 24 * 7);       // 1 week
define("IMAGE_EXPIRATION", 60 * 59 * 24 * 30);        // 1 week


$clear_webpages_with_cache = $_SERVER["CLEAR_WEBPAGES_WITH_CACHE"] ?? $_ENV["CLEAR_WEBPAGES_WITH_CACHE"] ?? true;
define('CLEAR_WEBPAGES_WITH_CACHE', $clear_webpages_with_cache);


// Redis
$redis_host = $_SERVER["REDIS_HOST"] ?? $_ENV["REDIS_HOST"] ?? null;
$redis_port = $_SERVER["REDIS_PORT"] ?? $_ENV["REDIS_PORT"] ?? 6379;
$redis_available = false;
define('REDIS_HOST', $redis_host);
define('REDIS_PORT', $redis_port);
if (!empty(REDIS_HOST) && !empty(REDIS_PORT)) {
  $client = new Predis\Client(array(
    'scheme'   => 'tcp',
    'host'     => REDIS_HOST,
    'port'     => REDIS_PORT,
    'timeout' => 1
  ));
  try {
    $client->connect();
    $redis_available = true;
  } catch (Exception $e) {
    $redis_available = false;
  }
}
define('REDIS', $redis_available);


// Progress caching
$include_progress = !empty($include_progress) ? $include_progress : false;
define('INCLUDE_PROGRESS', $include_progress);


