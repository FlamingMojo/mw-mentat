<?php

namespace MediaWiki\Extension\Mentat;

use MediaWiki\MediaWikiServices;

return [
	'Mentat.MentatMessageSender' => static function ( MediaWikiServices $services ): mentatMessageSender {
		return new MentatMessageSender(
			$services->getHttpRequestFactory()
		);
	}
];
