<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\EventStreamConfig\StreamConfigs;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [
	'EventStreamConfig.StreamConfigs' => static function ( MediaWikiServices $services ): StreamConfigs {
		$options = new ServiceOptions(
			StreamConfigs::CONSTRUCTOR_OPTIONS, $services->getMainConfig()
		);
		$logger = LoggerFactory::getInstance( 'EventStreamConfig' );
		return new StreamConfigs( $options, $logger );
	}
];
