<?php

namespace MediaWiki\Extension\EventStreamConfig;

use MediaWiki\Config\ServiceOptions;
use Psr\Log\LoggerInterface;
use Wikimedia\Assert\Assert;

/**
 * Functions to aid in exporting event stream configs.  These configs should be set
 * in Global MW config to allow for more dynamic configuration of event stream settings
 * e.g. sample rates or destination_event_service.
 *
 * Some terms:
 * - StreamConfigs    - List of individual Stream Configs
 * - A StreamConfig   - an object of event stream settings
 * - A stream setting - an individual setting for an stream, e.g. 'schema_title'.
 *
 * See also:
 * - https://phabricator.wikimedia.org/T205319
 * - https://phabricator.wikimedia.org/T233634
 *
 * This expects that 'EventStreams' is set in MW Config to an associative array of stream
 * configs keyed by stream name or regex pattern. Each stream config entry should look something
 * like:
 *
 * "my.event.stream-name" => [
 *      "schema_title" => "my/event/schema",
 *      "sample" => [
 *          "rate" => 0.8,
 *          "unit" => "session",
 *      ],
 *      "destination_event_service" => "eventgate-analytics-external",
 *      ...
 * 	],
 *
 * If the stream is associated with a regex pattern, the functions here will match requested
 * target streams against that pattern.
 */
class StreamConfigs {
	/**
	 * Name of the main config key(s) for stream configuration.
	 * @var array
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'EventStreams',
		'EventStreamsDefaultSettings'
	];

	/**
	 * Associative array of StreamConfigs keyed by stream name/pattern
	 * @var array
	 */
	private $streamConfigs = [];

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	private $logger;

	/**
	 * Constructs a new StreamConfigs instance initialized
	 * from wgEventStreams and wgEventStreamsDefaultSettings
	 *
	 * @param ServiceOptions $options
	 * @param LoggerInterface $logger
	 */
	public function __construct( ServiceOptions $options, LoggerInterface $logger ) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );

		$streamConfigs = $options->get( 'EventStreams' );
		Assert::parameterType( 'array', $streamConfigs, 'EventStreams' );

		$defaultSettings = $options->get( 'EventStreamsDefaultSettings' );
		Assert::parameterType( 'array', $defaultSettings, 'EventStreamsDefaultSettings' );

		foreach ( $streamConfigs as $key => $config ) {
			// Backwards compatibility for when stream configs used to be an integer indexed array.
			$stream = is_int( $key ) && isset( $config[StreamConfig::STREAM_SETTING] ) ?
				$config[StreamConfig::STREAM_SETTING] :
				$key;
			$this->streamConfigs[ $stream ] = new StreamConfig( $stream, $config, $defaultSettings );
		}

		$this->logger = $logger;
	}

	/**
	 * Looks for target stream names and returns matched stream configs keyed by stream name.
	 *
	 * @param array|null $targetStreams
	 *     List of stream names. If not provided, all stream configs will be returned.
	 * @param bool $includeAllSettings
	 *     If $includeAllSettings is false, only setting keys that match those in
	 *     StreamConfig::SETTINGS_FOR_EXPORT will be returned.
	 * @param array|null $settingsConstraints
	 *     If given, returned stream config entries will be filtered for those that
	 *     have these settings.
	 *
	 * @return array
	 */
	public function get(
		array $targetStreams = null,
		$includeAllSettings = false,
		array $settingsConstraints = null
	): array {
		$result = [];
		foreach ( $this->selectByStreams( $targetStreams ) as $stream => $streamConfigEntry ) {
			if (
				!$settingsConstraints ||
				$streamConfigEntry->matchesSettings( $settingsConstraints )
			) {
				$result[$stream] = $streamConfigEntry->toArray( $includeAllSettings, $stream );
			}
		}
		return $result;
	}

	/**
	 * Filter for stream names that match streams in $targetStreamNames.
	 *
	 * @param array|null $targetStreams
	 *     If not provided, all $streamConfigs will be returned, keyed by 'stream'.
	 *
	 * @return StreamConfig[]
	 */
	private function selectByStreams( array $targetStreams = null ): array {
		// If no $targetStreams were specified, then assume all are desired.
		if ( $targetStreams === null ) {
			$this->logger->debug( 'Selecting all stream configs.' );
			return $this->streamConfigs;
		}
		$groupedStreamConfigs = [];
		$this->logger->debug(
			'Selecting stream configs for target streams: {streams}',
			[ 'streams' => implode( " ", $targetStreams ) ]
		);
		foreach ( $targetStreams as $stream ) {
			// Find the config for this $stream.
			// configured stream names can be exact streams or regexes.
			// $stream will be matched against either.
			$streamConfig = $this->findByStream( $stream );

			if ( $streamConfig === null ) {
				$this->logger->warning(
					"Stream '$stream' does not match any `stream` in stream config"
				);
			} else {
				// Else include the settings in the stream config result..
				$groupedStreamConfigs[$stream] = $streamConfig;
			}
		}
		return $groupedStreamConfigs;
	}

	/**
	 * Given a $stream name to get, this matches $stream against
	 * `stream` in $streamConfigs and returns the first found StreamConfig object.
	 * If no match is found, returns null.
	 *
	 * @param string $stream
	 * @return StreamConfig|null
	 */
	private function findByStream( $stream ) {
		// If a stream config is defined for the exact stream name provided, return it.
		if ( isset( $this->streamConfigs[$stream] ) ) {
			return $this->streamConfigs[$stream];
		}

		// If no exact match is found, iterate over $streamConfigs and return the config
		// for the first pattern found that matches that matches the provided stream name.
		foreach ( $this->streamConfigs as $config ) {
			if ( $config->matches( $stream ) ) {
				return $config;
			}
		}

		return null;
	}

}
