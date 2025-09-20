<?php
namespace AdvancedOddsComparison;

// exit if accessed directly
defined('ABSPATH') || exit;

class Bookmaker {
	private string $name;
	private string $slug;
	private string $link;
	private array $scraping_config;

	/**
	 * Creates Bookmaker instance from a given array
	 *
	 * @param $data
	 * @return Bookmaker
	 */
	public static function from_array($data) {
		return new self(
			$data['name'] ?? '',
			$data['slug'] ?? '',
			$data['link'] ?? '',
			$data['scraping_config'] ?? []
		);
	}

	/**
	 * Creates Bookmaker instance from a given post
	 *
	 * @param $data
	 * @return Bookmaker|false
	 */
	public static function from_post($data) {
		if(!($data instanceof \WP_Post)) return false;
		return new self(
			$data->post_title,
			$data->post_name,
			get_post_meta($data->ID, META__BOOKMAKER_LINK, true),
			get_post_meta($data->ID, META__BOOKMAKER_SCRAPING_CONFIG) ?? []
		);
	}

	public function __construct(
		string $name,
		string $slug,
		string $link,
		array $scraping_config = []
	) {
		$this->name = $name;
		$this->slug = $slug;
		$this->link = $link;
		$this->scraping_config = $scraping_config;
	}

	public function get_name() {
		return $this->name;
	}

	public function get_slug() {
		return $this->slug;
	}

	public function get_link() {
		return $this->link;
	}

	public function get_scraping_config() {
		return $this->scraping_config;
	}

	public function set_scraping_config($config) {
		$this->scraping_config = $config;
	}

	public function to_array() {
		return [
			'name' => $this->name,
			'slug' => $this->slug,
			'link' => $this->link,
			'scraping_config' => $this->scraping_config
		];
	}
}
