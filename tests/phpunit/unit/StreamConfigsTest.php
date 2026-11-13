<?php

namespace MediaWiki\Extension\EventStreamConfig;

use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use Wikimedia\Assert\ParameterElementTypeException;

/**
 * @covers \MediaWiki\Extension\EventStreamConfig\StreamConfigs
 * @group EventStreamConfig
 */
class StreamConfigsTest extends MediaWikiUnitTestCase {

	private const STREAM_CONFIGS_FIXTURE = [
		[
			// Stream configs in MW config used to be integer indexed.
			// This was changed to keyed by stream name in
			// https://phabricator.wikimedia.org/T277193.
			// Test that integer indexing still works, as long as
			// 'stream' setting is provided.
			'stream' => 'integer_indexed',
			'schema_title' => 'integer_indexed_schema',
			'destination_event_service' => 'eventgate-main',
		],
		'nonya' => [
			'stream' => 'nonya',
			'schema_title' => 'mediawiki/nonya',
			'sample' => [
				'rate' => 0.5,
			],
			'destination_event_service' => 'eventgate-analytics',
			// explicit topics
			'topics' => [ 'nonya_topic' ]
		],
		'eventlogging_Test' => [
			'stream' => 'eventlogging_Test',
			'schema_title' => 'analytics/legacy/test',
			'sample' => [
				'rate' => 1.0,
				'unit' => 'session',
			],
			'destination_event_service' => 'eventgate-analytics',
			// does not use topic prefixes, topic will be stream name
			'topic_prefixes' => null,
		],
		'test.event' => [
			'stream' => 'test.event',
			'schema_title' => 'test/event',
			'sample' => [
				'rate' => 1.0,
				'unit' => 'session',
			],
			'destination_event_service' => 'eventgate-main',
			// overridden topic prefixes, should not use defaults
			'topic_prefixes' => [ 'dc1.', 'dc2.' ],
		],
		'/^mediawiki\.job\..+/' => [
			'stream' => '/^mediawiki\.job\..+/',
			'schema_title' => 'mediawiki/job',
			'sample' => [
				'rate' => 0.8,
			],
			'destination_event_service' => 'eventgate-main',
		],
	];

	private const STREAM_CONFIG_DEFAULT_SETTINGS_FIXTURE = [
		'topic_prefixes' => [ 'eqiad.', 'codfw.' ]
	];

	private const ANY_CONSTRAINTS_STREAM_CONFIGS_FIXTURE = [
		'legacy_only' => [
			'schema_title' => 'schema_a',
			'destination_event_service' => 'eventgate-main',
		],
		'producer_only' => [
			'schema_title' => 'schema_b',
			'producers' => [
				'mediawiki_eventbus' => [
					'event_service_name' => 'eventgate-main',
				],
			],
		],
		'legacy_and_producer' => [
			'schema_title' => 'schema_a',
			'destination_event_service' => 'eventgate-main',
			'producers' => [
				'mediawiki_eventbus' => [
					'event_service_name' => 'eventgate-main',
				],
			],
		],
		'other_service' => [
			'schema_title' => 'schema_a',
			'destination_event_service' => 'eventgate-analytics',
			'producers' => [
				'mediawiki_eventbus' => [
					'event_service_name' => 'eventgate-analytics',
				],
			],
		],
	];

	private const EVENTGATE_MAIN_ANY_CONSTRAINTS = [
		[ 'destination_event_service' => 'eventgate-main' ],
		[ 'producers' => [ 'mediawiki_eventbus' => [ 'event_service_name' => 'eventgate-main' ] ] ],
	];

	private StreamConfigs $streamConfigs;

	public function setUp(): void {
		parent::setUp();

		$logger = new NullLogger();

		// Use $this->streamConfigs in (most) tests below.
		$this->streamConfigs = new StreamConfigs(
			self::STREAM_CONFIGS_FIXTURE,
			self::STREAM_CONFIG_DEFAULT_SETTINGS_FIXTURE,
			$logger
		);
	}

	public static function streamConfigsGetProvider() {
		return [

			[
				// targetStreams
				[ 'nonya' ],
				// constrains
				null,
				// expected
				[
					'nonya' => [
						'sample' => [
							'rate' => 0.5,
						],
						'stream' => 'nonya',
						'schema_title' => 'mediawiki/nonya',
						'destination_event_service' => 'eventgate-analytics',
						'topics' => [ 'nonya_topic' ],
						'topic_prefixes' => [ 'eqiad.', 'codfw.' ],
					],
				],
				// test message
				'get by specific stream'
			],

			[
				[ 'nonya', 'test.event' ],
				null,
				[
					'nonya' => [
						'sample' => [
							'rate' => 0.5,
						],
						'stream' => 'nonya',
						'schema_title' => 'mediawiki/nonya',
						'destination_event_service' => 'eventgate-analytics',
						'topic_prefixes' => [ 'eqiad.', 'codfw.' ],
						'topics' => [ 'nonya_topic' ],
					],
					'test.event' => [
						'sample' => [
							'rate' => 1.0,
							'unit' => 'session',
						],
						'stream' => 'test.event',
						'schema_title' => 'test/event',
						'destination_event_service' => 'eventgate-main',
						'topic_prefixes' => [ 'dc1.', 'dc2.' ],
						'topics' => [
							'dc1.test.event',
							'dc2.test.event',
						],
					]
				],
				'get by specific streams'
			],

			[
				[ 'nonya', 'mediawiki.job.A', 'mediawiki.job.B' ],
				null,
				[
					'nonya' => [
						'sample' => [
							'rate' => 0.5,
						],
						'stream' => 'nonya',
						'schema_title' => 'mediawiki/nonya',
						'destination_event_service' => 'eventgate-analytics',
						'topic_prefixes' => [ 'eqiad.', 'codfw.' ],
						'topics' => [ 'nonya_topic' ],
					],
					'mediawiki.job.A' => [
						'stream' => '/^mediawiki\.job\..+/',
						'schema_title' => 'mediawiki/job',
						'sample' => [
							'rate' => 0.8,
						],
						'destination_event_service' => 'eventgate-main',
						'topic_prefixes' => [ 'eqiad.', 'codfw.' ],
						'topics' => [
							'eqiad.mediawiki.job.A',
							'codfw.mediawiki.job.A',
						],
					],
					'mediawiki.job.B' => [
						'stream' => '/^mediawiki\.job\..+/',
						'schema_title' => 'mediawiki/job',
						'sample' => [
							'rate' => 0.8,
						],
						'destination_event_service' => 'eventgate-main',
						'topic_prefixes' => [ 'eqiad.', 'codfw.' ],
						'topics' => [
							'eqiad.mediawiki.job.B',
							'codfw.mediawiki.job.B',
						],
					],
				],
				'get by regex streams'
			],

			[
				[ 'nonya', 'mediawiki.job.workworkwork' ],
				null,
				[
					'nonya' => [
						'stream' => 'nonya',
						'schema_title' => 'mediawiki/nonya',
						'sample' => [
							'rate' => 0.5,
						],
						'destination_event_service' => 'eventgate-analytics',
						'topic_prefixes' => [ 'eqiad.', 'codfw.' ],
						'topics' => [ 'nonya_topic' ],
					],
					'mediawiki.job.workworkwork' => [
						'stream' => '/^mediawiki\.job\..+/',
						'schema_title' => 'mediawiki/job',
						'sample' => [
							'rate' => 0.8,
						],
						'destination_event_service' => 'eventgate-main',
						'topic_prefixes' => [ 'eqiad.', 'codfw.' ],
						'topics' => [
							'eqiad.mediawiki.job.workworkwork',
							'codfw.mediawiki.job.workworkwork'
						],
					]
				],
				'get by regex streams with all settings'
			],

			[
				null,
				null,
				[
					'integer_indexed' => [
						'stream' => 'integer_indexed',
						'schema_title' => 'integer_indexed_schema',
						'destination_event_service' => 'eventgate-main',
						'topic_prefixes' => [ 'eqiad.', 'codfw.' ],
						'topics' => [ 'eqiad.integer_indexed', 'codfw.integer_indexed' ],
					],
					'nonya' => [
						'stream' => 'nonya',
						'schema_title' => 'mediawiki/nonya',
						'sample' => [
							'rate' => 0.5,
						],
						'destination_event_service' => 'eventgate-analytics',
						'topic_prefixes' => [ 'eqiad.', 'codfw.' ],
						'topics' => [ 'nonya_topic' ],
					],
					'eventlogging_Test' => [
						'stream' => 'eventlogging_Test',
						'schema_title' => 'analytics/legacy/test',
						'sample' => [
							'rate' => 1.0,
							'unit' => 'session',
						],
						'destination_event_service' => 'eventgate-analytics',
						// does not use topic prefixes, topic will be stream name
						'topic_prefixes' => null,
						'topics' => [ 'eventlogging_Test' ],
					],
					'test.event' => [
						'stream' => 'test.event',
						'schema_title' => 'test/event',
						'sample' => [
							'rate' => 1.0,
							'unit' => 'session',
						],
						'destination_event_service' => 'eventgate-main',
						'topic_prefixes' => [ 'dc1.', 'dc2.' ],
						'topics' => [ 'dc1.test.event', 'dc2.test.event' ],
					],
					// Since we aren't asking for a specific stream,
					// we will get this config keyed by its regex stream,
					// pattern rather than a specific stream name.
					'/^mediawiki\.job\..+/' => [
						'stream' => '/^mediawiki\.job\..+/',
						'schema_title' => 'mediawiki/job',
						'sample' => [
							'rate' => 0.8,
						],
						'destination_event_service' => 'eventgate-main',
						'topic_prefixes' => [ 'eqiad.', 'codfw.' ],
						'topics' => [ '/^(eqiad\.|codfw\.)mediawiki\.job\..+/' ],
					],
				],
				'get all streams with all settings'
			],

			[
				[ 'unconfigured-stream-name' ],
				null,
				[],
				'get an unconfigured stream name'
			],

			[
				null,
				[
					'destination_event_service' => 'eventgate-main',
				],
				[
					'integer_indexed' => [
						'stream' => 'integer_indexed',
						'schema_title' => 'integer_indexed_schema',
						'destination_event_service' => 'eventgate-main',
						'topic_prefixes' => [ 'eqiad.', 'codfw.' ],
						'topics' => [ 'eqiad.integer_indexed', 'codfw.integer_indexed' ],
					],
					'test.event' => [
						'stream' => 'test.event',
						'schema_title' => 'test/event',
						'sample' => [
							'rate' => 1.0,
							'unit' => 'session',
						],
						'destination_event_service' => 'eventgate-main',
						'topic_prefixes' => [ 'dc1.', 'dc2.' ],
						'topics' => [ 'dc1.test.event', 'dc2.test.event' ],
					],
					// Since we aren't asking for any specific streams,
					// we will get this config keyed by its regex stream,
					// pattern rather than a specific stream name.
					'/^mediawiki\.job\..+/' => [
						'stream' => '/^mediawiki\.job\..+/',
						'schema_title' => 'mediawiki/job',
						'sample' => [
							'rate' => 0.8,
						],
						'destination_event_service' => 'eventgate-main',
						'topic_prefixes' => [ 'eqiad.', 'codfw.' ],
						'topics' => [ '/^(eqiad\.|codfw\.)mediawiki\.job\..+/' ],
					]
				],
				'get all streams that have matching constraints'
			],

			[
				[ 'mediawiki.job.workworkwork' ],
				[
					'destination_event_service' => 'eventgate-main',
				],
				[
					'mediawiki.job.workworkwork' => [
						'stream' => '/^mediawiki\.job\..+/',
						'schema_title' => 'mediawiki/job',
						'sample' => [
							'rate' => 0.8,
						],
						'destination_event_service' => 'eventgate-main',
						'topic_prefixes' => [ 'eqiad.', 'codfw.' ],
						'topics' => [
							'eqiad.mediawiki.job.workworkwork',
							'codfw.mediawiki.job.workworkwork'
						],
					]
				],
				'get all streams that have matching stream names and constraints'
			],

			[
				[ 'nonya', 'mediawiki.job.workworkwork' ],
				null,
				[
					'nonya' => [
						'stream' => 'nonya',
						'schema_title' => 'mediawiki/nonya',
						'sample' => [
							'rate' => 0.5,
						],
						'destination_event_service' => 'eventgate-analytics',
						'topic_prefixes' => [ 'eqiad.', 'codfw.' ],
						'topics' => [ 'nonya_topic' ],
					],
					'mediawiki.job.workworkwork' => [
						'stream' => '/^mediawiki\.job\..+/',
						'schema_title' => 'mediawiki/job',
						'sample' => [
							'rate' => 0.8,
						],
						'destination_event_service' => 'eventgate-main',
						'topic_prefixes' => [ 'eqiad.', 'codfw.' ],
						'topics' => [
							'eqiad.mediawiki.job.workworkwork',
							'codfw.mediawiki.job.workworkwork'
						],
					]
				],
				'get by regex streams with all settings with topic prefixes'
			],
		];
	}

	/**
	 * @dataProvider streamConfigsGetProvider
	 */
	public function testGet(
		$targetStreams,
		$constraints,
		$expected,
		$message
	) {
		$result = $this->streamConfigs->get( $targetStreams, $constraints );
		$this->assertEquals( $expected, $result, $message );
	}

	public static function provideGetAnySettingsConstraints() {
		yield 'any constraints only' => [
			'targetStreams' => null,
			'constraints' => null,
			'anyConstraints' => self::EVENTGATE_MAIN_ANY_CONSTRAINTS,
			'expectedStreams' => [ 'legacy_only', 'producer_only', 'legacy_and_producer' ],
		];

		yield 'any constraints and constraints' => [
			'targetStreams' => null,
			'constraints' => [ 'schema_title' => 'schema_a' ],
			'anyConstraints' => self::EVENTGATE_MAIN_ANY_CONSTRAINTS,
			'expectedStreams' => [ 'legacy_only', 'legacy_and_producer' ],
		];

		yield 'any constraints and target streams' => [
			'targetStreams' => [ 'producer_only', 'other_service' ],
			'constraints' => null,
			'anyConstraints' => self::EVENTGATE_MAIN_ANY_CONSTRAINTS,
			'expectedStreams' => [ 'producer_only' ],
		];

		yield 'any constraints with no match' => [
			'targetStreams' => null,
			'constraints' => null,
			'anyConstraints' => [
				[ 'destination_event_service' => 'eventgate-logging-external' ],
			],
			'expectedStreams' => [],
		];

		yield 'null any constraints do not filter' => [
			'targetStreams' => null,
			'constraints' => null,
			'anyConstraints' => null,
			'expectedStreams' => [ 'legacy_only', 'producer_only', 'legacy_and_producer', 'other_service' ],
		];

		yield 'empty any constraints do not filter' => [
			'targetStreams' => null,
			'constraints' => null,
			'anyConstraints' => [],
			'expectedStreams' => [ 'legacy_only', 'producer_only', 'legacy_and_producer', 'other_service' ],
		];
	}

	/**
	 * @dataProvider provideGetAnySettingsConstraints
	 */
	public function testGetAnySettingsConstraints(
		?array $targetStreams,
		?array $constraints,
		?array $anyConstraints,
		array $expectedStreams
	) {
		$streamConfigs = new StreamConfigs(
			self::ANY_CONSTRAINTS_STREAM_CONFIGS_FIXTURE,
			[],
			new NullLogger()
		);

		$result = $streamConfigs->get( $targetStreams, $constraints, $anyConstraints );
		$this->assertSame( $expectedStreams, array_keys( $result ) );
	}

	public function testGetAnySettingsConstraintsRejectsFlatConstraints() {
		// The third argument must be a list of constraint arrays. A flat array of constraints is not valid.
		$this->expectException( ParameterElementTypeException::class );

		$this->streamConfigs->get(
			null,
			null,
			[ 'destination_event_service' => 'eventgate-main' ]
		);
	}
}
