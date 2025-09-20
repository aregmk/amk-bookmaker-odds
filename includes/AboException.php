<?php
namespace AdvancedOddsComparison;

// exit if accessed directly
defined('ABSPATH') || exit;

class AboException extends \Exception {
	protected string $scraping_message;

	public function __construct(string $message = '', int $code = 0, \Throwable $previous = null, string $scraping_message = '') {
		parent::__construct($message, $code, $previous);
		$this->scraping_message = $scraping_message;
	}

	public function get_scraping_message(): string {
		return $this->scraping_message;
	}
}
