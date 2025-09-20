<?php
namespace AdvancedOddsComparison;

// exit if accessed directly
defined('ABSPATH') || exit;

use DOMDocument;
use DOMXPath;

class ScrapeService {
	/**
	 * User agent for scraping request
	 * @var string|null
	 */
	private string|null $user_agent;

	/**
	 * Timeout for scraping request
	 * @var int
	 */
	private int $timeout;

	/**
	 * Cache key for scraping request
	 * @var string
	 */
	private string $cache_key = 'abo_scrape_data';

	/**
	 * Cache expiration (seconds) for scraping request
	 * @var int
	 */
	private int $cache_expiration = 3600;

	public function __construct(string|null $user_agent = null, int $timeout = 30) {
		$this->user_agent = $user_agent ?: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
		$this->timeout = $timeout;
	}

	/**
	 * Fetch URL content with proper headers and error handling.
	 *
	 * @param string $url
	 * @return string
	 * @throws AboException
	 */
	private function fetch_url(string $url): string {
		$response = wp_remote_get($url, [
			'timeout' => $this->timeout,
			'headers' => [
				'User-Agent' => $this->user_agent,
				'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
				'Accept-Language' => 'en-US,en;q=0.5',
				'Connection' => 'keep-alive',
				'Upgrade-Insecure-Requests' => '1'
			]
		]);
		if(is_wp_error($response)) throw new AboException('HTTP request failed: '.$response->get_error_message());

		$response_code = wp_remote_retrieve_response_code($response);
		if(200 !== $response_code) throw new AboException('HTTP error: ' . $response_code);

		return wp_remote_retrieve_body($response);
	}

	/**
	 * Parse HTML to extract odds data.
	 *
	 * @param string $html
	 * @param \WP_Post[] $bookmakers
	 * @return array
	 */
	private function parse_html_oddschecker_com(string $html, array $bookmakers) {
		$dom = new DOMDocument();
		@$dom->loadHTML($html);
		$xpath = new DOMXPath($dom);

		$results = [];
		$data = $xpath->query("//div[@id='scrollable-container-3577624893']");
		if(!$data) return $results;

		$data = $data->item(0);
		foreach($bookmakers as $item) {
			$bookmaker_short_slug = $xpath->query('(//a[@title="'.$item->post_title.'"]/@data-bk)[1]', $data);
			if(empty($bookmaker_short_slug) || empty($bookmaker_short_slug->item(0))) continue;

			$bookmaker_odds = $xpath->query('//button[@data-bk="'.$bookmaker_short_slug->item(0)->nodeValue.'"]', $data);
			$results[$item->post_name] = [
				'title' => $item->post_title,
				'short_slug' => $bookmaker_short_slug->item(0)->nodeValue,
				'img' => get_the_post_thumbnail_url($item, 'medium'),
				'link' => get_post_meta($item->ID, META__BOOKMAKER_LINK, true),
				'odds' => []
			];
			if(!empty($bookmaker_odds)) {
				foreach($bookmaker_odds as $odd) {
					$results[$item->post_name]['odds'][] = $odd->textContent;
				}
			}
		}

		$last_updated = $xpath->query('//p[@class="LastUpdated_l1omwxtm"]', $data)->item(0)->textContent;
		$heading = $xpath->query('//h1', $data)->item(0)->textContent;
		preg_match('/(.+?)\svs\s(.+?)\s-\sBetting\sOdds/', $heading, $matches);
		$results = [
			'heading' => $heading,
			'home_team' => $matches[1] ?? '',
			'away_team' => $matches[2] ?? '',
			'last_updated' => $last_updated,
			'results' => $results,
		];

		return $results;
	}

	/**
	 * Scrape odds from OddsChecker for a specific bookmaker.
	 *
	 * @param string $block_id
	 * @param string $url
	 * @param array $bookmakers
	 * @return mixed
	 */
	public function scrape_odds(string $block_id, string $url, array $bookmakers) {
		$results = [];

		try {
			$cache_key = "{$this->cache_key}_$block_id";
			$results = wp_cache_get($cache_key);
			//$results = get_option($cache_key, []);

			if(empty($results)) {
				$data = $this->fetch_url($url);
				$bookmakers = get_posts([
					'post_type' => POST_TYPE__BOOKMAKER,
					'posts_per_page' => -1,
					'post__in' => $bookmakers
				]);
				$results = $this->parse_html_oddschecker_com($data, $bookmakers);
				if(!empty($results)) {
					wp_cache_set($cache_key, $results, '', $this->cache_expiration);
					//update_option($cache_key, $results, false);
				}
			}
		}
		catch(AboException $e) {
			error_log(sprintf('Failed to scrape odds for this url: %s. %s', $url, $e->getMessage()));
		}
		catch(\Exception $e) {
			error_log(sprintf('Generic exception: %s', $e->getMessage()));
		}

		return $results;
	}
}
