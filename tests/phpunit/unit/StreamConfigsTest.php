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
			'topic_prefixes' => [ 'eqiad.', 'codfw.' ],
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
				null,
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
				null,
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
				null,
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
				null,
				[
					'nonya' => [
						'stream' => 'nonya',
						'schema_title' => 'mediawiki/nonya',
						'sample_rate' => 0.5,
						'EventServiceName' => 'eventgate-analytics',
						'topics' => [ 'nonya' ],
					],
					'mediawiki.job.workworkwork' => [
						'stream' => '/^mediawiki\.job\..+/',
						'schema_title' => 'mediawiki/job',
						'sample_rate' => 0.8,
						'EventServiceName' => 'eventgate-main',
						'topics' => [ 'mediawiki.job.workworkwork' ],
					]
				],
				'get by regex streams with all settings'
			],

			[
				null,
				true,
				null,
				[
					'nonya' => [
						'stream' => 'nonya',
						'schema_title' => 'mediawiki/nonya',
						'sample_rate' => 0.5,
						'EventServiceName' => 'eventgate-analytics',
						'topics' => [ 'nonya' ],
					],
					'test.event' => [
						'stream' => 'test.event',
						'schema_title' => 'test/event',
						'sample_rate' => 1.0,
						'EventServiceName' => 'eventgate-main',
						'topic_prefixes' => [ 'eqiad.', 'codfw.' ],
						'topics' => [ 'eqiad.test.event', 'codfw.test.event' ],
					],
					// Since we aren't asking for a specific stream,
					// we will get this config keyed by its regex stream,
					// pattern rather than a specific stream name.
					'/^mediawiki\.job\..+/' => [
						'stream' => '/^mediawiki\.job\..+/',
						'schema_title' => 'mediawiki/job',
						'sample_rate' => 0.8,
						'EventServiceName' => 'eventgate-main',
						'topics' => [ '/^mediawiki\.job\..+/' ],
					],
				],
				'get all streams with all settings'
			],

			[
				[ 'unconfigured-stream-name' ],
				false,
				null,
				[],
				'get an unconfigured stream name'
			],

			[
				null,
				true,
				[
					'EventServiceName' => 'eventgate-main',
				],
				[
					'test.event' => [
						'stream' => 'test.event',
						'schema_title' => 'test/event',
						'sample_rate' => 1.0,
						'EventServiceName' => 'eventgate-main',
						'topic_prefixes' => [ 'eqiad.', 'codfw.' ],
						'topics' => [ 'eqiad.test.event', 'codfw.test.event' ],
					],
					// Since we aren't asking for any specific streams,
					// we will get this config keyed by its regex stream,
					// pattern rather than a specific stream name.
					'/^mediawiki\.job\..+/' => [
						'stream' => '/^mediawiki\.job\..+/',
						'schema_title' => 'mediawiki/job',
						'sample_rate' => 0.8,
						'EventServiceName' => 'eventgate-main',
						'topics' => [ '/^mediawiki\.job\..+/' ],
					]
				],
				'get all streams that have matching constraints'
			],

			[
				[ 'mediawiki.job.workworkwork' ],
				true,
				[
					'EventServiceName' => 'eventgate-main',
				],
				[
					'mediawiki.job.workworkwork' => [
						'stream' => '/^mediawiki\.job\..+/',
						'schema_title' => 'mediawiki/job',
						'sample_rate' => 0.8,
						'EventServiceName' => 'eventgate-main',
						'topics' => [ 'mediawiki.job.workworkwork' ],
					]
				],
				'get all streams that have matching stream names and constraints'
			],

			[
				[ 'nonya', 'mediawiki.job.workworkwork' ],
				true,
				null,
				[
					'nonya' => [
						'stream' => 'nonya',
						'schema_title' => 'mediawiki/nonya',
						'sample_rate' => 0.5,
						'EventServiceName' => 'eventgate-analytics',
						'topics' => [ 'nonya' ],
					],
					'mediawiki.job.workworkwork' => [
						'stream' => '/^mediawiki\.job\..+/',
						'schema_title' => 'mediawiki/job',
						'sample_rate' => 0.8,
						'EventServiceName' => 'eventgate-main',
						'topics' => [ 'mediawiki.job.workworkwork' ],
					]
				],
				'get by regex streams with all settings with topic prefixes'
			],
		];
	}

	/**
	 * @dataProvider streamConfigsGetProvider
	 */
	public function testGet( $targetStreams, $allSettings, $constraints, $expected, $message ) {
		$result = $this->streamConfigs->get( $targetStreams, $allSettings, $constraints );
		$this->assertEquals( $expected, $result, $message );
	}

}
