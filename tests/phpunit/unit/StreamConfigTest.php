<?php

namespace MediaWiki\Extension\EventStreamConfig;

use InvalidArgumentException;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\EventStreamConfig\StreamConfig
 * @group EventStreamConfig
 */
class StreamConfigTest extends MediaWikiUnitTestCase {

	/**
	 * @covers MediaWiki\Extension\EventStreamConfig\StreamConfig::stream()
	 */
	public function testStream() {
		$settings = [
			'stream' => 'nonya',
			'schema_title' => 'mediawiki/nonya',
			'sample_rate' => 0.5,
			'EventServiceName' => 'eventgate-analytics',
		];

		$streamConfig = new StreamConfig( $settings );
		$this->assertEquals( 'nonya', $streamConfig->stream() );
	}

	/**
	 * @covers MediaWiki\Extension\EventStreamConfig\StreamConfig::toArray()
	 */
	public function testToArray() {
		$settings = [
			'stream' => 'nonya',
			'schema_title' => 'mediawiki/nonya',
			'sample_rate' => 0.5,
			'EventServiceName' => 'eventgate-analytics',
		];

		$expected = [
			'sample_rate' => 0.5,
		];

		$streamConfig = new StreamConfig( $settings );
		$this->assertEquals( $expected, $streamConfig->toArray() );
	}

	/**
	 * @covers MediaWiki\Extension\EventStreamConfig\StreamConfig::toArray()
	 */
	public function testToArrayAllSettings() {
		$settings = [
			'stream' => 'nonya',
			'schema_title' => 'mediawiki/nonya',
			'sample_rate' => 0.5,
			'EventServiceName' => 'eventgate-analytics',
		];

		$expected = $settings;

		$streamConfig = new StreamConfig( $settings );
		$this->assertEquals( $expected, $streamConfig->toArray( true ) );
	}

	/**
	 * @covers MediaWiki\Extension\EventStreamConfig\StreamConfig::matches()
	 */
	public function testMatchesString() {
		$settings = [
			'stream' => 'nonya',
			'schema_title' => 'mediawiki/nonya',
			'sample_rate' => 0.5,
			'EventServiceName' => 'eventgate-analytics',
		];

		$streamConfig = new StreamConfig( $settings );
		$this->assertTrue( $streamConfig->matches( 'nonya' ) );
	}

	/**
	 * @covers MediaWiki\Extension\EventStreamConfig\StreamConfig::matches()
	 */
	public function testMatchesRegex() {
		$settings = [
			'stream' => '/^mediawiki\.job\..+/',
			'schema_title' => 'mediawiki/job',
			'sample_rate' => 0.8,
			'EventServiceName' => 'eventgate-main',
		];

		$streamConfig = new StreamConfig( $settings );
		$this->assertTrue( (bool)$streamConfig->matches( 'mediawiki.job.workworkwork' ) );
	}

	/**
	 * @covers MediaWiki\Extension\EventStreamConfig\StreamConfig::matches()
	 */
	public function testGivenRegexStreamDoesNotMatch() {
		$settings = [
			'stream' => '/^mediawiki\.job\..+/',
			'schema_title' => 'mediawiki/job',
			'sample_rate' => 0.8,
			'EventServiceName' => 'eventgate-main',
		];

		$streamConfig = new StreamConfig( $settings );
		// Since the stream setting should be recognized as a regex, string equivalence
		// should not be used to match the incoming target stream name, and
		// preg_match( $regex, $regex ) will be false.
		$this->assertFalse( (bool)$streamConfig->matches( '/^mediawiki\.job\..+/' ) );
	}

	/**
	 * @covers MediaWiki\Extension\EventStreamConfig\StreamConfig::__construct()
	 */
	public function testMissingStreamName() {
		$settings = [
			'schema_title' => 'mediawiki/nonya',
			'sample_rate' => 0.5,
			'EventServiceName' => 'eventgate-analytics',
		];
		$this->expectException( InvalidArgumentException::class );
		new StreamConfig( $settings );
	}

	/**
	 * @covers MediaWiki\Extension\EventStreamConfig\StreamConfig::__construct()
	 */
	public function testWrongStreamNameType() {
		$settings = [
			'stream' => 10.0,
			'schema_title' => 'mediawiki/nonya',
			'sample_rate' => 0.5,
			'EventServiceName' => 'eventgate-analytics',
		];
		$this->expectException( InvalidArgumentException::class );
		new StreamConfig( $settings );
	}

	/**
	 * @covers MediaWiki\Extension\EventStreamConfig\StreamConfig::__construct()
	 */
	public function testInvalidStreamNameRegex() {
		$settings = [
			'stream' => '/nonya/BADREGEX',
			'schema_title' => 'mediawiki/nonya',
			'sample_rate' => 0.5,
			'EventServiceName' => 'eventgate-analytics',
		];
		$this->expectException( InvalidArgumentException::class );
		new StreamConfig( $settings );
	}
}
