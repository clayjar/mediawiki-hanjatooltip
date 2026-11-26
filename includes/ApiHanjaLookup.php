<?php

namespace MediaWiki\Extension\HanjaTooltip;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiResult;
use MediaWiki\MediaWikiServices;
use mysqli;
use mysqli_sql_exception;
use mysqli_stmt;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\StringDef;

class ApiHanjaLookup extends ApiBase {

	public function execute() {
		if ( !$this->callerIsThisWiki() ) {
			$this->dieWithError( 'apierror-hanjatooltip-foreign', 'foreignorigin' );
		}

		$char = $this->extractRequestParams()['char'];
		if ( mb_strlen( $char, 'UTF-8' ) !== 1 ) {
			$this->dieWithError( 'apierror-hanjatooltip-notonechar', 'badchar' );
		}

		$config = $this->getConfig();
		$user = (string)$config->get( 'HanjaTooltipDBuser' );
		$database = (string)$config->get( 'HanjaTooltipDBname' );
		if ( $user === '' || $database === '' ) {
			$this->dieWithError( 'apierror-hanjatooltip-config', 'noconfig' );
		}

		$previous = mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
		$db = null;
		$stmt = null;
		try {
			$db = new mysqli(
				(string)$config->get( 'HanjaTooltipDBserver' ),
				$user,
				(string)$config->get( 'HanjaTooltipDBpassword' ),
				$database,
				(int)$config->get( 'HanjaTooltipDBport' )
			);
			$db->set_charset( 'utf8mb4' );
			$stmt = $db->prepare( 'SELECT definition FROM hanja WHERE hanja = ?' );
			$stmt->bind_param( 's', $char );
			$stmt->execute();
			$definition = null;
			$stmt->bind_result( $definition );
			$found = $stmt->fetch() === true;
			// A shared cache would serve this response without repeating the origin check.
			$this->getMain()->setCacheMode( 'private' );
			$this->getMain()->setCacheMaxAge( 3600 );
			$this->getResult()->addValue( null, $this->getModuleName(), [
				'char' => $char,
				'definition' => $found ? (string)$definition : '',
				'found' => $found,
				ApiResult::META_BC_BOOLS => [ 'found' ],
			] );
		} catch ( mysqli_sql_exception ) {
			$this->dieWithError( 'apierror-hanjatooltip-dberror', 'dberror' );
		} finally {
			if ( $stmt instanceof mysqli_stmt ) {
				$stmt->close();
			}
			if ( $db instanceof mysqli ) {
				$db->close();
			}
			mysqli_report( $previous );
		}
	}

	public function getAllowedParams() {
		return [
			'char' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				StringDef::PARAM_MAX_CHARS => 1,
			],
		];
	}

	protected function getExamplesMessages() {
		return [
			'action=hanja&char=韓' => 'apihelp-hanja-example-1',
		];
	}

	public function isReadMode() {
		return true;
	}

	/**
	 * True when the caller is a page on this wiki.
	 *
	 * Browsers set Sec-Fetch-Site and will not let another site claim same-origin.
	 * When that header is absent, Origin or Referer must match this wiki.
	 */
	private function callerIsThisWiki(): bool {
		$fetchSite = $this->getRequest()->getHeader( 'Sec-Fetch-Site' );
		if ( is_string( $fetchSite ) ) {
			return strcasecmp( trim( $fetchSite ), 'same-origin' ) === 0;
		}

		$origin = $this->getRequest()->getHeader( 'Origin' );
		if ( is_string( $origin ) && $origin !== '' ) {
			return strcasecmp( $origin, 'null' ) !== 0 && $this->matchesWiki( $origin );
		}

		$referer = $this->getRequest()->getHeader( 'Referer' );
		return is_string( $referer ) && $this->matchesWiki( $referer );
	}

	private function matchesWiki( string $url ): bool {
		$caller = $this->originOf( $url );
		if ( $caller === null ) {
			return false;
		}
		return in_array( $caller, $this->wikiOrigins(), true );
	}

	/**
	 * Origins that belong to the wiki serving this request.
	 *
	 * @return list<string>
	 */
	private function wikiOrigins(): array {
		$origins = [];
		$requestOrigin = $this->requestOrigin();
		if ( $requestOrigin !== null ) {
			$origins[] = $requestOrigin;
		}
		$canonical = $this->originOf(
			MediaWikiServices::getInstance()->getUrlUtils()->getCanonicalServer()
		);
		if ( $canonical !== null ) {
			$origins[] = $canonical;
		}
		return array_values( array_unique( $origins ) );
	}

	private function requestOrigin(): ?string {
		$host = $this->getRequest()->getHeader( 'HOST' );
		if ( !is_string( $host ) || trim( $host ) === '' ) {
			return null;
		}
		return $this->originOf( $this->getRequest()->getProtocol() . '://' . trim( $host ) );
	}

	private function originOf( string $url ): ?string {
		$bits = parse_url( $url );
		if ( !is_array( $bits ) || empty( $bits['scheme'] ) || empty( $bits['host'] ) ) {
			return null;
		}
		if ( isset( $bits['user'] ) || isset( $bits['pass'] ) ) {
			return null;
		}
		return $this->formatOrigin(
			$bits['scheme'],
			$bits['host'],
			isset( $bits['port'] ) ? (int)$bits['port'] : null
		);
	}

	private function formatOrigin( string $scheme, string $host, ?int $port ): string {
		$scheme = strtolower( $scheme );
		$host = strtolower( $host );
		if ( str_contains( $host, ':' ) ) {
			$host = '[' . $host . ']';
		}
		$origin = $scheme . '://' . $host;
		$defaultPort = $scheme === 'https' ? 443 : ( $scheme === 'http' ? 80 : null );
		if ( $port !== null && $port !== $defaultPort ) {
			$origin .= ':' . $port;
		}
		return $origin;
	}
}
