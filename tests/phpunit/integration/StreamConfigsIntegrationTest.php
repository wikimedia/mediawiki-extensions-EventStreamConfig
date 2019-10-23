<?php

namespace MediaWiki\Extension\EventStreamConfig;

use MediaWikiIntegrationTestCase;
use MediaWiki\MediaWikiServices;

/**
 * @covers \MediaWiki\Extension\EventStreamConfig\StreamConfigs
 * @group EventStreamConfig
 */
class StreamConfigsIntegrationTest extends MediaWikiIntegrationTestCase {

	const STREAM_CONFIGS_FIXTURE = [
		[
			'stream' => 'nonya',
			'schema_title' => 'mediawiki/nonya',
			'sample_rate' => 0.5,
			'EventServiceName' => 'eventgate-analytics',
		],
		[
			'stream' => 'test.event',
			'schema_title' => 'test/event',
			'sample_rate' => 1.0,
			'EventServiceName' => 'eventgate-main',
		],
		[
			'stream' => '/^mediawiki\.job\..+/',
			'schema_title' => 'mediawiki/job',
			'sample_rate' => 0.8,
			'EventServiceName' => 'eventgate-main',
		],
	];

	/**
	 * @covers MediaWiki\Extension\EventStreamConfig\StreamConfigs::__construct()
	 */
	public function testMediaWikiServiceIntegration() {
		$this->setMwGlobals( [
			'wgEventStreams' => self::STREAM_CONFIGS_FIXTURE
		] );

		$streamConfigs = MediaWikiServices::getInstance()->getService(
			'EventStreamConfig.StreamConfigs'
		);

		$expected = [
			'nonya' => [
				'sample_rate' => 0.5,
			]
		];
		$result = $streamConfigs->get( [ 'nonya' ] );
		$this->assertEquals( $expected, $result );
	}
}
