<?php
// This file is generated. Do not modify it manually.
return array(
	'amk-bookmaker-odds' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'create-block/amk-bookmaker-odds',
		'version' => '0.1.0',
		'title' => 'AMK Bookmaker Odds',
		'category' => 'widgets',
		'icon' => 'star-filled',
		'description' => 'Creates a block to display bookmaker odds.',
		'example' => array(
			
		),
		'supports' => array(
			'html' => false
		),
		'attributes' => array(
			'blockId' => array(
				'type' => 'string',
				'default' => ''
			),
			'url' => array(
				'type' => 'string',
				'default' => ''
			),
			'bookmakers' => array(
				'type' => 'array',
				'default' => array(
					
				)
			)
		),
		'textdomain' => 'amk-bookmaker-odds',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'viewScript' => 'file:./view.js',
		'render' => 'file:./render.php'
	)
);
