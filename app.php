<?php

// Config
include 'config.php';

// Classes
include 'classes/auth.php';
include 'classes/community.php';
include 'classes/post.php';
include 'classes/rss.php';
include 'classes/webpage-analyzer.php';
include 'classes/parser.php';

// Functions
include 'functions.php';

// Cache
include 'cache.php';

// Prevent PHP warnings and deprecation notices
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

