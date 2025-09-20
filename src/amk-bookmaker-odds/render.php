<?php
$attributes = $block->parsed_block['attrs'];
$scrape_service = $GLOBALS['abo']->get_scrape_service();
$data = $scrape_service->scrape_odds($attributes['blockId'], $attributes['url'], $attributes['bookmakers']);

$result_columns = '';
foreach($data['results'] as $idx => $item) {
	if(empty($item['odds'])) continue;

	$odds = '';
	foreach($item['odds'] as $odd) {
		$odds .= '<p class="td">'.$odd.'</p>';
	}

	$link_before = $link_after = '';
	if(!empty($item['link'])) {
		$link_before = '<a href="'.esc_url($item['link']).'" target="_blank" rel="noopener noreferrer">';
		$link_after = '</a>';
	}

	$result_columns .= '<div class="col col-'.$idx.'">'.
		'<p class="th has-img">'.
			$link_before.
			'<img src="'.$item['img'].'" alt="'.$item['title'].'">'.
			$link_after.
		'</p>'.
		$odds.
	'</div>';
}
?>
<section class="sec-abo-block">
	<div class="wrapper">
		<h2 class="heading"><?php echo $data['heading'] ?? __('Bookmaker Odds', 'amk_bookmaker_odds'); ?></h2>
		<div class="meta">
			<?php if(!empty($data['last_updated'])) { ?>
				<p class="date"><?php echo $data['last_updated']; ?></p>
			<?php } ?>
		</div>

		<div class="table">
			<div class="col first-col">
				<p class="th">&nbsp;</p>
				<p class="td"><?php echo $data['home_team']; ?></p>
				<p class="td"><?php _e('Draw', 'amk_bookmaker_odds'); ?></p>
				<p class="td"><?php echo $data['away_team']; ?></p>
			</div>

			<?php echo $result_columns; ?>
		</div>
	</div>
</section>
