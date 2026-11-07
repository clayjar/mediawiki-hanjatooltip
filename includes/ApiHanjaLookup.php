<?php

namespace MediaWiki\Extension\HanjaTooltip;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiResult;
use mysqli;
use mysqli_sql_exception;
use mysqli_stmt;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\StringDef;

class ApiHanjaLookup extends ApiBase {

	public function execute() {
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
			$this->getMain()->setCacheMode( 'public' );
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
}
