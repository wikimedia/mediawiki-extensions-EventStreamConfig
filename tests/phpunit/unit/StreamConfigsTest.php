<?php

namespace MediaWiki\Extension\EventStreamConfig;

use MediaWiki\Config\ServiceOptions;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;

/**
 * @covers \MediaWiki\Extension\EventStreamConfig\StreamConfigs
 * @group EventStreamConfig
 */
class StreamConfigsTest extends MediaWikiUnitTestCase {

	private const STREAM_CONFIGS_FIXTURE = [
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

	public function setUp() : void {
		parent::setUp();
		$options = new ServiceOptions(
			StreamConfigs::CONSTRUCTOR_OPTIONS,
			[ 'EventStreams' => self::STREAM_CONFIGS_FIXTURE ]
		);
		$logger = new NullLogger();

		// Use $this->streamConfigs in (most) tests below.
		$this->streamConfigs = new StreamConfigs( $options, $logger );
	}

	public function streamConfigsGetProvider() {
		return [

			[
				[ 'nonya' ],
				false,
				[
					'nonya' => [
						'sample_rate' => 0.5,
					]
				],
				'get by specific stream'
			],

			[
				[ 'nonya', 'test.event' ],
				false,
				[
					'nonya' => [
						'sample_rate' => 0.5,
					],
					'test.event' => [
						'sample_rate' => 1.0,
					]
				],
				'get by specific streams'
			],

			[
				[ 'nonya', 'mediawiki.job.A', 'mediawiki.job.B' ],
				false,
				[
					'nonya' => [
						'sample_rate' => 0.5,
					],
					'mediawiki.job.A' => [
						'sample_rate' => 0.8,
					],
					'mediawiki.job.B' => [
						'sample_rate' => 0.8,
					]
				],
				'get by regex streams'
			],

			[
				[ 'nonya', 'mediawiki.job.workworkwork' ],
				true,
				[
					'nonya' => [
						'stream' => 'nonya',
						'schema_title' => 'mediawiki/nonya',
						'sample_rate' => 0.5,
						'EventServiceName' => 'eventgate-analytics',
					],
					'mediawiki.job.workworkwork' => [
						'stream' => '/^mediawiki\.job\..+/',
						'schema_title' => 'mediawiki/job',
						'sample_rate' => 0.8,
						'EventServiceName' => 'eventgate-main',
					]
				],
				'get by regex streams with all settings'
			],

			[
				null,
				true,
				[
					'nonya' => [
						'stream' => 'nonya',
						'schema_title' => 'mediawiki/nonya',
						'sample_rate' => 0.5,
						'EventServiceName' => 'eventgate-analytics',
					],
					'test.event' => [
						'stream' => 'test.event',
						'schema_title' => 'test/event',
						'sample_rate' => 1.0,
						'EventServiceName' => 'eventgate-main',
					],
					// Since we aren't asking for a specific stream,
					// we will get this config keyed by its regex stream,
					// pattern rather than a specific stream name.
					'/^mediawiki\.job\..+/' => [
						'stream' => '/^mediawiki\.job\..+/',
						'schema_title' => 'mediawiki/job',
						'sample_rate' => 0.8,
						'EventServiceName' => 'eventgate-main',
					]
				],
				'get all streams with all settings'
			],

			[
				[ 'unconfigured-stream-name' ],
				false,
				[],
				'get an unconfigured stream name'
			],
		];
	}

	/**
	 * @dataProvider streamConfigsGetProvider
	 */
	public function testGet( $targetStreams, $allSettings, $expected, $message ) {
		$result = $this->streamConfigs->get( $targetStreams, $allSettings );
		$this->assertEquals( $expected, $result, $message );
	}
}
