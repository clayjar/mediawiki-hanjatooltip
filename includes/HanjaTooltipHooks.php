<?php

namespace MediaWiki\Extension\HanjaTooltip;

use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Parser\Hook\ParserAfterTidyHook;

class HanjaTooltipHooks implements ParserAfterTidyHook, BeforePageDisplayHook {

	/**
	 * Wrap each Han ideograph that sits outside links and embedded blocks.
	 *
	 * @param Parser $parser
	 * @param string &$text
	 */
	public function onParserAfterTidy( $parser, &$text ) {
		$text = self::tagHtml( $text );
	}

	/**
	 * Load the tooltip stylesheet in the head and the script module.
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$out->addModuleStyles( 'ext.hanjaTooltip.styles' );
		$out->addModules( 'ext.hanjaTooltip' );
	}

	/**
	 * Return HTML with one span per Han ideograph outside protected regions.
	 */
	public static function tagHtml( string $text ): string {
		$slots = [];
		$protected = self::shield( $text, $slots );
		if ( $protected === null ) {
			return $text;
		}

		$tagged = preg_replace_callback(
			'/(<[^>]+>)|(\p{Han})/u',
			static function ( array $matches ): string {
				if ( $matches[1] !== '' ) {
					return $matches[1];
				}
				$char = htmlspecialchars( $matches[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
				return '<span class="hanja-word" data-char="' . $char . '">' . $char . '</span>';
			},
			$protected
		);
		if ( $tagged === null ) {
			return $text;
		}

		$tokens = array_keys( $slots );
		for ( $i = count( $tokens ) - 1; $i >= 0; $i-- ) {
			$token = $tokens[$i];
			$tagged = str_replace( $token, $slots[$token], $tagged );
		}
		return $tagged;
	}

	/**
	 * Replace protected regions with placeholders.
	 *
	 * @param array<string,string> &$slots
	 */
	private static function shield( string $text, array &$slots ): ?string {
		$patterns = [
			'/<script\b[^>]*>.*?<\/script>/isu',
			'/<style\b[^>]*>.*?<\/style>/isu',
			'/<textarea\b[^>]*>.*?<\/textarea>/isu',
			'/<a\b[^>]*>.*?<\/a>/isu',
		];
		foreach ( $patterns as $pattern ) {
			$text = preg_replace_callback(
				$pattern,
				static function ( array $matches ) use ( &$slots ): string {
					$token = "\x01HANJA" . count( $slots ) . "\x01";
					$slots[$token] = $matches[0];
					return $token;
				},
				$text
			);
			if ( $text === null ) {
				return null;
			}
		}
		return $text;
	}
}
