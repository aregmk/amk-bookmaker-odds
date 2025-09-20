<?php
/**
 * Plugin Name: AMK Bookmaker Odds
 * Description: Creates a block to display bookmaker odds. OddsChecker.com and OddsPortal.com has very strong scraping security, so we just used betmonitor.com for easy access.
 * Version: 0.1.0
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * Author: AMK
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: amk_bookmaker_odds
 *
 * @package CreateBlock
 */

namespace AdvancedOddsComparison;

// exit if accessed directly
defined('ABSPATH') || exit;

// constants
define('ADVANCED_ODDS_COMPARISON_VERSION', '1.0.0');
define('ADVANCED_ODDS_COMPARISON_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ADVANCED_ODDS_COMPARISON_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ADVANCED_ODDS_COMPARISON_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('POST_TYPE__BOOKMAKER', 'bookmaker');
define('META__BOOKMAKER_LINK', 'meta_link');
define('META__BOOKMAKER_SCRAPING_CONFIG', 'meta_scraping_config');

// autoloader for classes
spl_autoload_register(function($class_name) {
	$namespace = 'AdvancedOddsComparison\\';

	if(strpos($class_name, $namespace) === 0) {
		$class_name = str_replace($namespace, '', $class_name);
		$class_file = ADVANCED_ODDS_COMPARISON_PLUGIN_DIR. 'includes/'. str_replace('\\', '/', $class_name). '.php';
		if(file_exists($class_file)) require_once $class_file;
	}
});

// main class
final class AdvancedOddsComparison {
	/**
	 * Plugin instance
	 * @var AdvancedOddsComparison
	 */
	private static AdvancedOddsComparison $instance;

	/**
	 * Get plugin instance
	 * @return AdvancedOddsComparison
	 */
	public static function get_instance(): AdvancedOddsComparison {
		if(empty(self::$instance)) self::$instance = new self(new BookmakerManager(), new ScrapeService());
		return self::$instance;
	}

	/**
	 * Bookmaker manager instance
	 */
	private BookmakerManager $bookmaker_manager;

	/**
	 * Scrape service instance
	 */
	private ScrapeService $scrape_service;

	/**
	 * Constructor
	 *
	 * @param BookmakerManager $bookmaker_manager
	 * @param ScrapeService $scrape_service
	 */
	private function __construct(BookmakerManager $bookmaker_manager, ScrapeService $scrape_service) {
		// initialize managers and services
		$this->bookmaker_manager = $bookmaker_manager;
		$this->scrape_service = $scrape_service;

		// allow dependency injection for testing
		do_action('advanced_odds_comparison_construct', $this);

		register_activation_hook(__FILE__, [$this, 'activate']);
		register_deactivation_hook(__FILE__, [$this, 'deactivate']);
		add_action('plugins_loaded', [$this, 'plugin_loaded']);
		add_action('init', [$this, 'init']);
		add_action('init', [$this, 'init_blocks']);
		add_filter('save_post_bookmaker', [$this, 'save_bookmaker_metadata'], 10, 3);
		add_filter('save_post', [$this, 'save_post'], 10, 3);
		add_filter('upload_mimes', function($mimes) {
			$mimes['svg'] = 'image/svg+xml';
			return $mimes;
		});
	}

	/**
	 * Get Scrape Service instance.
	 *
	 * @return ScrapeService
	 */
	public function get_scrape_service(): ScrapeService {
		return $this->scrape_service;
	}

	/**
	 * Plugin activation tasks
	 */
	public function activate() {
		// save plugin version to DB
		if(!get_option('advanced_odds_comparison_version')) {
			update_option('advanced_odds_comparison_version', ADVANCED_ODDS_COMPARISON_VERSION);
		}

		// schedule cron job for updating odds blocks
		if(!wp_next_scheduled('advanced_odds_comparison_update_odds_blocks')) {
			wp_schedule_event(time(), 'hourly', 'advanced_odds_comparison_update_odds_blocks');
		}
	}

	/**
	 * Plugin deactivation tasks
	 */
	public function deactivate() {
		// clear scheduled cron job
		wp_clear_scheduled_hook('advanced_odds_comparison_update_odds_blocks');
	}

	/**
	 * Initialize the plugin
	 */
	public function plugin_loaded() {
		try {
			// hook the odds update process
			add_action('advanced_odds_comparison_update_odds_blocks', [$this, 'update_odds_data']);

			// hook for manual update via AJAX
			add_action('wp_ajax_update_odds_manual', [$this, 'ajax_update_odds']);

		}
		catch(\Exception $e) {
			// Log error and display admin notice
			error_log('AMK Bookmaker Odds Plugin failed to initialize: '.$e->getMessage());
			add_action('admin_notices', function() use ($e) {
				echo '<div class="notice notice-error"><p>'.
					sprintf(__('AMK Bookmaker Odds Plugin Error: %s', 'amk_bookmaker_odds'), esc_html($e->getMessage())).
				'</p></div>';
			});
		}
	}

	/**
	 * Load plugin textdomain for internationalization
	 */
	public function init() {
		load_plugin_textdomain('amk_bookmaker_odds', false, dirname(ADVANCED_ODDS_COMPARISON_PLUGIN_BASENAME).'/languages');

		register_post_type(POST_TYPE__BOOKMAKER, [
			'labels' => [
				'name'          => _x('Bookmakers', 'post type general name', 'amk_bookmaker_odds'),
				'singular_name' => _x('Bookmaker', 'post type singular name', 'amk_bookmaker_odds'),
				'add_new'       => __('Add New Bookmaker', 'amk_bookmaker_odds'),
				'add_new_item'  => __('Add New Bookmaker', 'amk_bookmaker_odds'),
				'edit_item'     => __('Edit Bookmaker', 'amk_bookmaker_odds'),
				'view_item'     => __('View Bookmaker', 'amk_bookmaker_odds'),
				'search_items'  => __('Search Bookmakers', 'amk_bookmaker_odds')
			],
			'public' => false,
			'show_ui' => true,
			'show_in_rest' => true,
			'has_archive' => false,
			'supports' => [
				'title',
				'thumbnail',
				'custom-fields',
				//'revisions'
			]
		]);
	}

	/**
	 * Register the block using a `blocks-manifest.php` file, which improves the performance of block type registration.
	 * Behind the scenes, it also registers all assets so they can be enqueued through the block editor in the corresponding context.
	 *
	 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
	 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
	 */
	public function init_blocks() {
		/**
		 * Registers the block(s) metadata from the `blocks-manifest.php` and registers the block type(s)
		 * based on the registered block metadata.
		 * Added in WordPress 6.8 to simplify the block metadata registration process added in WordPress 6.7.
		 *
		 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
		 */
		if(function_exists('wp_register_block_types_from_metadata_collection')) {
			wp_register_block_types_from_metadata_collection( __DIR__.'/build', __DIR__.'/build/blocks-manifest.php' );
			return;
		}

		/**
		 * Registers the block(s) metadata from the `blocks-manifest.php` file.
		 * Added to WordPress 6.7 to improve the performance of block type registration.
		 *
		 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
		 */
		if(function_exists('wp_register_block_metadata_collection')) {
			wp_register_block_metadata_collection( __DIR__.'/build', __DIR__.'/build/blocks-manifest.php' );
		}
		/**
		 * Registers the block type(s) in the `blocks-manifest.php` file.
		 *
		 * @see https://developer.wordpress.org/reference/functions/register_block_type/
		 */
		$manifest_data = require __DIR__.'/build/blocks-manifest.php';
		foreach(array_keys($manifest_data) as $block_type ) register_block_type(__DIR__."/build/{$block_type}");
	}

	/**
	 * Intercept when a bookmaker post is being saved/updated.
	 *
	 * @param int $post_id
	 * @param \WP_Post $post
	 * @param bool $update
	 * @return void
	 */
	public function save_bookmaker_metadata(int $post_id, \WP_Post $post, bool $update) {
		// add bookmaker default metadata
		if(!$update) {
			if(!metadata_exists($post->post_type, $post_id, META__BOOKMAKER_LINK)) {
				update_post_meta($post_id, META__BOOKMAKER_LINK, '');
			}
			if(!metadata_exists($post->post_type, $post_id, META__BOOKMAKER_SCRAPING_CONFIG)) {
				update_post_meta($post_id, META__BOOKMAKER_SCRAPING_CONFIG, serialize([]));
			}
		}
	}

	/**
	 * Intercept when a post is being saved/updated.
	 *
	 * @param int $post_id
	 * @param \WP_Post $post
	 * @param bool $update
	 * @return void
	 */
	public function save_post(int $post_id, \WP_Post $post, bool $update) {
		$blocks = parse_blocks($post->post_content);
		if(empty($blocks)) return;

		foreach($blocks as $item) {
			// add/remove ABO block to a collection for later usage in CRON job
			if('create-block/amk-bookmaker-odds' === $item['blockName']) {
				if(empty($item['attrs']['blockId'])) continue;

				$abo_blocks = get_option('abo_blocks_for_cron', []);

				if(empty($item['attrs']['url']) || empty($item['attrs']['bookmakers'])) {
					if(empty($abo_blocks)) continue;
					foreach($abo_blocks as $idx => $value) {
						if($item['attrs']['blockId'] === $value) unset($abo_blocks[$idx]);
					}
					update_option('abo_blocks_for_cron', array_map('sanitize_text_field', $abo_blocks), false);
				}
				elseif(!in_array($item['attrs']['blockId'], $abo_blocks)) {
					$abo_blocks[] = $item['attrs']['blockId'];
					update_option('abo_blocks_for_cron', array_map('sanitize_text_field', $abo_blocks), false);
				}
			}
		}
	}

	/**
	 * Update odds data for all bookmakers
	 */
	public function update_odds_data() {
		try {
			$abo_blocks = get_option('abo_blocks_for_cron', []);
			if(empty($abo_blocks)) return;


			// Log successful update
			error_log('Odds data updated successfully for '.count($abo_blocks).' ABO blocks.');

			return $abo_blocks;
		}
		catch(\Exception $e) {
			error_log('Failed to update odds data: '.$e->getMessage());
			return false;
		}
	}

	/**
	 * Handle AJAX request for manual odds update
	 */
	public function ajax_update_odds() {
		if(!current_user_can('manage_options')) {
			wp_die(__('Unauthorized', 'amk_bookmaker_odds'));
		}
		check_ajax_referer('update_odds_nonce', 'nonce');

		$result = $this->update_odds_data();
		if(false !== $result) {
			wp_send_json_success([
				'message' => __('Odds updated successfully', 'amk_bookmaker_odds'),
				'bookmakers' => array_keys($result)
			]);
		}
		else {
			wp_send_json_error([
				'message' => __('Failed to update odds', 'amk_bookmaker_odds')
			]);
		}
	}
}

// init
$GLOBALS['abo'] = AdvancedOddsComparison::get_instance();
