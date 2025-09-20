<?php
namespace AdvancedOddsComparison;

// exit if accessed directly
defined('ABSPATH') || exit;

class BookmakerManager {
	/**
	 * Array of Bookmaker objects
	 * @var Bookmaker[]
	 */
	private array $bookmakers;

	/**
	 * Cache key for bookmakers data
	 * @var string
	 */
	private string $cache_key = 'abo_bookmakers';

	/**
	 * Cache expiration time in seconds
	 * @var int
	 */
	private int $cache_expiration = 3600;

	/**
	 * Constructor - loads bookmakers from storage
	 */
	public function __construct() {
		$this->load_bookmakers();
	}

	/**
	 * Load bookmakers from cache or WP options
	 */
	private function load_bookmakers() {
		// first check the cache
		$bookmakers = wp_cache_get($this->cache_key);
		if(false !== $bookmakers) {
			$this->bookmakers = $bookmakers;
			return;
		}

		// otherwise, get the bookmaker posts
		$this->bookmakers = [];
		$bookmakers = get_posts([
			'post_type' => POST_TYPE__BOOKMAKER,
			'posts_per_page' => -1,
			'order' => 'ASC',
			'orderby' => 'menu_order title'
		]);
		if(!empty($bookmakers)) {
			foreach($bookmakers as $item) {
				$bookmaker = Bookmaker::from_post($item);
				$this->bookmakers[$bookmaker->get_slug()] = $bookmaker;
			}
			// cache the loaded bookmakers
			wp_cache_set($this->cache_key, $this->bookmakers, '', $this->cache_expiration);
		}
	}

	/**
	 * Check if the bookmaker exists
	 * @param string $slug
	 * @return bool
	 */
	public function maybe_bookmaker_exists(string $slug): bool {
		return isset($this->bookmakers[$slug]);
	}

	/**
	 * Get all bookmakers.
	 *
	 * @return Bookmaker[]
	 */
	public function get_all_bookmakers(): array {
		return $this->bookmakers;
	}

	/**
	 * Flush all cache related to bookmakers and odds
	 */
	public function flush_cache() {
		// Clear bookmakers cache
		wp_cache_delete($this->cache_key);

		// Reload bookmakers
		$this->load_bookmakers();
	}
}
