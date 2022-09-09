<?php

namespace MediaWiki\Extension\EventStreamConfig;

use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\EventStreamConfig\StreamConfigs
 * @group EventStreamConfig
 */
class StreamConfigsIntegrationTest extends MediaWikiIntegrationTestCase {

	private const STREAM_CONFIGS_FIXTURE = [
		'nonya' => [
			'stream' => 'nonya',
			'schema_title' => 'mediawiki/nonya',
			'sample' => [
				'rate' => 0.5,
				'unit' => 'session',
			],
			'destination_event_service' => 'eventgate-analytics',
		],
		'test.event' => [
			'stream' => 'test.event',
			'schema_title' => 'test/event',
			'sample' => [
				'rate' => 1.0,
				'unit' => 'session',
			],
			'destination_event_service' => 'eventgate-main',
			'topic_prefixes' => [ 'eqiad.', 'codfw.' ],
		],
		'/^mediawiki\.job\..+/' => [
			'stream' => '/^mediawiki\.job\..+/',
			'schema_title' => 'mediawiki/job',
			'sample' => [
				'rate' => 0.8,
				'unit' => 'session',
			],
			'destination_event_service' => 'eventgate-main',
		],
	];

	/**
	 * @covers \MediaWiki\Extension\EventStreamConfig\StreamConfigs::__construct()
	 */
	public function testMediaWikiServiceIntegration() {
		$this->setMwGlobals( [
			'wgEventStreams' => self::STREAM_CONFIGS_FIXTURE,
			'wgEventStreamsDefaultSettings' => [
				'topic_prefixes' => [
					'eqiad.'
				],
			],
		] );

		$streamConfigs = MediaWikiServices::getInstance()->getService(
			'EventStreamConfig.StreamConfigs'
		);

		$expected = [
			'nonya' => [
				'stream' => 'nonya',
				'schema_title' => 'mediawiki/nonya',
				'sample' => [
					'rate' => 0.5,
					'unit' => 'session',
				],
				'destination_event_service' => 'eventgate-analytics',
				'topic_prefixes' => [
					'eqiad.',
				],
				'topics' => [
					'eqiad.nonya',
				],
			],
		];
		$result = $streamConfigs->get( [ 'nonya' ] );
		$this->assertEquals( $expected, $result );
	}
}
