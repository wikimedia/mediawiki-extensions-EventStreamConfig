<?php

namespace MediaWiki\Extension\EventStreamConfig;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiResult;
use MediaWiki\MediaWikiServices;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Enables requesting allowed MediaWiki configs via the API.
 * Usage:
 *
 * Get stream config settings
 *   GET /w/api.php?format=json&action=streamconfigs
 *
 * Get stream config settings for specified streams
 *   GET /w/api.php?format=json&action=streamconfigs&streams=my-stream1|my-stream2
 */
class ApiStreamConfigs extends ApiBase {
	// 10 minutes
	private const CACHE_MAX_AGE = 600;

	/**
	 * API query param used to specify target streams to get from Config
	 */
	private const API_PARAM_STREAMS = 'streams';

	/**
	 * API query param used to restrict stream config entry results to
	 * those that have settings that match these constraints.
	 * This is expected to be given as a multi array of key=val pairs.  E.g.
	 *   constraints=event_service_name=eventgate-main|other_setting=other_value
	 * This would be parsed into
	 * @code
	 *   [
	 *       'event_service_name' => 'eventgate-main',
	 *       'other_setting' => 'other_value'
	 *   ]
	 * @endcode
	 *
	 * And be used to filter for stream config entries that have these settings.
	 */
	private const API_PARAM_CONSTRAINTS = 'constraints';

	/**
	 * API query param that limits the results to the stream config entries
	 * that match one or more of these constraints.
	 * Each item in the multi value is one alternative constraint. For example:
	 *   any_constraints=destination_event_service=eventgate-main|
	 *     producers[mediawiki_eventbus][event_service_name]=eventgate-main
	 * Without the line break, the API parses this value into:
	 * @code
	 *   [
	 *       [ 'destination_event_service' => 'eventgate-main' ],
	 *       [ 'producers' => [ 'mediawiki_eventbus' => [ 'event_service_name' => 'eventgate-main' ] ] ],
	 *   ]
	 * @endcode
	 *
	 * A stream config entry must match all API_PARAM_CONSTRAINTS and one or more
	 * API_PARAM_ANY_CONSTRAINTS.
	 */
	private const API_PARAM_ANY_CONSTRAINTS = 'any_constraints';

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->getMain()->setCacheMode( 'public' );
		$this->getMain()->setCacheMaxAge( self::CACHE_MAX_AGE );

		$targetStreams = $this->getParameter( self::API_PARAM_STREAMS );
		$constraints = $this->getParameter( self::API_PARAM_CONSTRAINTS );
		$anyConstraints = $this->getParameter( self::API_PARAM_ANY_CONSTRAINTS );

		$settingsConstraints = $constraints ? self::multiParamToAssocArray( $constraints ) : null;
		$anySettingsConstraints = $anyConstraints ? self::multiParamToListOfAssocArrays( $anyConstraints ) : null;

		$streamConfigs = MediaWikiServices::getInstance()->getService(
			'EventStreamConfig.StreamConfigs'
		);

		$result = $streamConfigs->get(
			$targetStreams,
			$settingsConstraints,
			$anySettingsConstraints
		);

		// Ensure that empty stream configs are serialized as objects ({}) and not as lists ([]).
		//
		// See https://phabricator.wikimedia.org/T259917 and
		// https://phabricator.wikimedia.org/T323032 for additional context.
		foreach ( $result as &$value ) {
			ApiResult::setArrayType( $value, 'assoc' );
		}

		ApiResult::setArrayType( $result, 'assoc' );

		$this->getResult()->addValue( null, "streams", $result );
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			self::API_PARAM_STREAMS => [
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_ISMULTI_LIMIT1 => 250,
			],
			self::API_PARAM_CONSTRAINTS => [
				ParamValidator::PARAM_ISMULTI => true,
			],
			self::API_PARAM_ANY_CONSTRAINTS => [
				ParamValidator::PARAM_ISMULTI => true,
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=streamconfigs&' . self::API_PARAM_STREAMS .
			'=mediawiki.page-view|mediawiki.button-click' =>
					'apihelp-streamconfigs-example-1',

			'action=streamconfigs&' . self::API_PARAM_STREAMS .
			'=mediawiki.button-click' =>
					'apihelp-streamconfigs-example-2',

			'action=streamconfigs&' . self::API_PARAM_STREAMS .
			'=mediawiki.button-click' .
			'&constraints=event_service_name=eventgate-main' =>
					'apihelp-streamconfigs-example-3',

			'action=streamconfigs&' . self::API_PARAM_ANY_CONSTRAINTS .
			'=destination_event_service=eventgate-main' .
			'|producers[mediawiki_eventbus][event_service_name]=eventgate-main' =>
					'apihelp-streamconfigs-example-4',
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Extension:EventStreamConfig';
	}

	/**
	 * @inheritDoc
	 */
	public function getCustomPrinter() {
		return new ApiFormatJsonVersion2( $this->getMain(), 'json' );
	}

	/**
	 * Parses a MULTI PARAM value into an assoc array.
	 *
	 * For example, the query string parameter "my_param=key1=val1|key2[key3]=val2" will be parsed into the following:
	 *
	 * ```
	 * [
	 *     'key1' => 'val1',
	 *     'key2' => [
	 *         'key3' => 'val2',
	 *     ],
	 * ]
	 * ```
	 *
	 * @param array $multiParamArray List of key=val string pairs
	 * @return array
	 */
	private static function multiParamToAssocArray( array $multiParamArray ): array {
		return array_merge_recursive( ...self::multiParamToListOfAssocArrays( $multiParamArray ) );
	}

	/**
	 * Parses a MULTI PARAM value into a list of assoc arrays.
	 * Each item stays a separate assoc array. This function does not merge the items.
	 *
	 * For example, this function parses the query string parameter
	 * "my_param=key1=val1|key2[key3]=val2" into:
	 *
	 * ```
	 * [
	 *     [ 'key1' => 'val1' ],
	 *     [ 'key2' => [ 'key3' => 'val2' ] ],
	 * ]
	 * ```
	 *
	 * @param array $multiParamArray List of key=val string pairs
	 * @return array[]
	 */
	private static function multiParamToListOfAssocArrays( array $multiParamArray ): array {
		// `wfCgiToArray( ... )` makes a closure that calls wfCgiToArray with the same arguments.
		// `...` is part of the syntax.
		// wfCgiToArray parses one query string into a nested array. It also URL-decodes each key and value.
		// For example, "key2[key3]=val2" becomes [ 'key2' => [ 'key3' => 'val2' ] ].
		return array_map( wfCgiToArray( ... ), $multiParamArray );
	}
}
