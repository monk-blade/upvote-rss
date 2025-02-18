<?php

// MonoLog
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

class CustomLogger extends Logger {
	public function __construct($name = "upvote-rss") {
		parent::__construct($name);

		// Custom log format
		$output = "%datetime% | %level_name% > %message%";
		$output .= " %context% %extra%\n";
		$dateFormat = "Y-m-d g:i:sa";
		$formatter = new LineFormatter($output, $dateFormat, true, true);
		$formatter->ignoreEmptyContextAndExtra(true);

		// Stream handler to output logs to stdout
		$stream_handler = new StreamHandler("php://stdout", Logger::DEBUG);
		$stream_handler->setFormatter($formatter);
		$this->pushHandler($stream_handler);

		// Rotating file handler to rotate logs every 7 days
		$rotating_handler = new RotatingFileHandler(__DIR__ . '/../logs/upvote-rss.log', 7, Logger::DEBUG);
		$rotating_handler->setFormatter($formatter);
		$this->pushHandler($rotating_handler);
	}
}
