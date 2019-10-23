<?php

namespace MediaWiki\Extension\EventStreamConfig;

use Wikimedia\Assert\Assert;

/**
 * Configuration of single stream's settings.
 */
class StreamConfig {

	/**
	 * Key of stream setting. Used to DRY references here.
	 * @var string
	 */
	private const STREAM_SETTING = 'stream';

	/**
	 * Blacklist of setting names that don't usually need to be included
	 * in config request results.  Not shipping irrelevant settings to
	 * client side saves on bytes transferred.
	 * @var array
	 */
	private const INTERNAL_SETTINGS = [
		self::STREAM_SETTING,
		'EventServiceName',
		'schema_title',
	];

	/**
	 * Wrapped array with stream config settings.  See $settings
	 * param doc for __construct().
	 * @var array
	 */
	private $settings;

	/**
	 * True if the stream setting is a regex, false otherwise.
	 * Used to avoid attempting to regex match with a non regex.
	 * @var bool
	 */
	private $streamIsRegex;

	/**
	 * @param array $settings
	 *        An array with stream config settings.
	 *        Must include a 'stream' setting with either the explicit stream
	 *        name, or a regex pattern (starting with '/') that will match
	 *        against stream names that this config should be used for.
	 *        Example:
	 *        [
	 *          "stream" => "my.event.stream-name",
	 *          "schema_title" => "my/event/schema",
	 *          "sample_rate" => 0.8,
	 *               "EventServiceName" => "eventgate-analytics-public",
	 *           ...
	 *        ]
	 */
	public function __construct( array $settings ) {
		self::validate( $settings );
		$this->settings = $settings;

		$this->streamIsRegex = self::isValidRegex( $this->stream() );
	}

	/**
	 * Gets the stream setting
	 * @return string
	 */
	public function stream() {
		return $this->settings[self::STREAM_SETTING];
	}

	/**
	 * Returns this StreamConfig as an array of settings.
	 *
	 * @param bool $includeAllSettings
	 *        If false, the settings in INTERNAL_SETTINGS
	 *        will be excluded.  Default: false.
	 * @return array
	 */
	public function toArray( $includeAllSettings = false ): array {
		$settings = $this->settings;

		if ( !$includeAllSettings ) {
			$settings = array_diff_key( $settings, array_flip( self::INTERNAL_SETTINGS ) );
		}

		return $settings;
	}

	/**
	 * True if this StreamConfig applies for $streamName, false otherwise.
	 *
	 * @param string $stream name of stream to match
	 * @return bool
	 */
	public function matches( $stream ) {
		return $this->streamIsRegex ?
			preg_match( $this->stream(), $stream ) :
			( $this->stream() === $stream );
	}

	/**
	 * Returns true if $string is a valid regex.
	 * It must start with '/' and preg_match must not return false.
	 *
	 * @param  string $string
	 * @return bool
	 */
	private static function isValidRegex( $string ) {
		// Temporarily disable errors/warnings when checking if valid regex.
		$errorLevel = error_reporting( E_ERROR );
		$isValid = mb_substr( $string, 0, 1 ) === '/' && preg_match( $string, null ) !== false;
		error_reporting( $errorLevel );
		return $isValid;
	}

	/**
	 * Validates that the stream config settings are valid.
	 *
	 * @param array $settings
	 * @throws \InvalidArgumentException if stream config settings are invalid
	 */
	private static function validate( array $settings ) {
		Assert::parameter(
			isset( $settings[self::STREAM_SETTING] ),
			self::STREAM_SETTING,
			self::STREAM_SETTING . ' not set in stream config entry ' .
				var_export( $settings, true )
		);
		$stream = $settings[self::STREAM_SETTING];

		Assert::parameterType( 'string', $stream, self::STREAM_SETTING );
		// If stream looks like a regex, make sure it is valid.
		// (Yes, isValidRegex also checks that string starts with '/', but here we want
		// to fail if the stream is not a valid regex.)
		if ( substr( $stream, 0, 1 ) === '/' ) {
			Assert::parameter(
				self::isValidRegex( $stream ), self::STREAM_SETTING, "Invalid regex '$stream'"
			);
		}
	}

}
