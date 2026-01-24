<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;

class MentatUtils {
	/**
	 * Creates userInfo array for MediaWiki User object
	 */
	public static function createUserInfo( $user ) {
		if ( $user instanceof UserIdentity ) {
			// If we were passed a UserIdentity object, get the relevant user.
			$user = MediaWikiServices::getInstance()->getUserFactory()->newFromUserIdentity( $user );
		}

		if ( $user instanceof User ) {
			$isAnon = $user->isAnon();
			$contribs = Title::newFromText( "Special:Contributions/" . $user );
			$userName = strval( $user );
			$userPage = ($isAnon ? $contribs : $user->getUserPage() )->getFullURL( '', false, PROTO_CANONICAL );
			$userTalk = $user->getTalkPage()->getFullURL( '', false, PROTO_CANONICAL );
			$userContribs = $contribs->getFullURL( '', false, PROTO_CANONICAL );
			$info = [
			    'name' => $userName,
			    'page' => $userPage,
			    'talk' => $userTalk,
			    'contribs' => $userContribs,
			    'bot' => $user->isBot()
			];
		} else {
			// If we were given a string, handle this differently.
            $info = [
                'name' => $userName,
                'page' => '',
                'talk' => '',
                'contribs' => '',
                'bot' => false
            ];
		}
		return $info;
	}
	
	/**
	 * Creates array for a specific Revision object
	 */
	public static function createRevisionInfo( $revision ) {
		$diff = Title::newFromLinkTarget( $revision->getPageAsLinkTarget() )
		    ->getFullURL([ 'diff' => 'prev', 'oldid' => $revision->getId() ], false, PROTO_CANONICAL );
		$minor = $revision->isMinor();
		$size = $revision->getSize();
		$parentId = $revision->getParentId();
		if ( $parentId ) {
			$parent = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionById( $parentId );
			if ( $parent ) {
				$size = $revision->getSize() - $parent->getSize();
			}
		}
        $info = [
            'diff' => $diff,
            'size' => $size,
            'minor' => $minor
        ];
		return $info;
	}
	
	public static function buildRequest($hook, $userInfo, $pageInfo ) {
	    global $wgServer;

		return [
            'wiki' => $wgServer,
            'user' => $userInfo,
            'page' => $pageInfo,
            'hook' => $hook
        ];
	}
	
	/**
	 * Handles sending a webhook to Mentat using cURL
	 */
	public static function handleMentat ($hookName, $msg) {
		global $wgMentatWebhookURL, $wgMentatEmojis, $wgMentatUseEmojis, $wgMentatPrependTimestamp, $wgMentatUseFileGetContents;

		if ( !$wgMentatWebhookURL ) {
			// There's nothing in here, so we won't do anything
			return false;
		}

		$urls = [];

		if ( is_array( $wgMentatWebhookURL ) ) {
			$urls = array_merge($urls, $wgMentatWebhookURL);
		} else if ( is_string($wgMentatWebhookURL) ) {
			$urls[] = $wgMentatWebhookURL;
		} else {
			wfDebugLog( 'Mentat', 'The value of $wgMentatWebhookURL is not valid and therefore no webhooks could be sent.' );
			return false;
		}

		DeferredUpdates::addCallableUpdate( function() use ( $msg, $urls, $wgMentatUseFileGetContents ) {
			$user_agent = 'mw-mentat/1.0 (github.com/FlamingMojo)';
			$json = json_encode($msg);

			if ( $wgMentatUseFileGetContents ) {
				// They want to use file_get_contents
				foreach ($urls as &$value) {
					$contextOpts = [
						'http' => [
							'header' => 'Content-Type: application/x-www-form-urlencoded',
							'method' => 'POST', // Send as a POST request
							'user_agent' => $user_agent, // Add a unique user agent
							'content' => $json, // Send the JSON in the POST request
							'ignore_errors' => true // If the call fails, let's not do anything with it
						]
					];

					$context = stream_context_create( $contextOpts );
					$result = file_get_contents( $value, false, $context );
				}
			} else {
				// By default, we use cURL
				// Set up cURL multi handlers
				$c_handlers = [];
				$result = [];
				$mh = curl_multi_init();

				foreach ($urls as &$value) {
					$c_handlers[$value] = curl_init( $value );
					curl_setopt( $c_handlers[$value], CURLOPT_POST, 1 ); // Send as a POST request
					curl_setopt( $c_handlers[$value], CURLOPT_POSTFIELDS, $json ); // Send the JSON in the POST request
					curl_setopt( $c_handlers[$value], CURLOPT_FOLLOWLOCATION, 1 );
					curl_setopt( $c_handlers[$value], CURLOPT_HEADER, 0 );
					curl_setopt( $c_handlers[$value], CURLOPT_RETURNTRANSFER, 1 );
					curl_setopt( $c_handlers[$value], CURLOPT_CONNECTTIMEOUT, 10 ); // Add a timeout for connecting to the site
					curl_setopt( $c_handlers[$value], CURLOPT_TIMEOUT, 10 ); // Do not allow cURL to run for a long time
					curl_setopt( $c_handlers[$value], CURLOPT_USERAGENT, $user_agent ); // Add a unique user agent
					curl_setopt( $c_handlers[$value], CURLOPT_HTTPHEADER, array(
						'Content-Type: application/json'
					));
					curl_multi_add_handle( $mh, $c_handlers[$value] );
				}

				$running = null;
				do {
					curl_multi_exec($mh, $running);
				} while ($running);

				// Remove all handlers and then close the multi handler
				foreach($c_handlers as $k => $ch) {
					$result[$k] = curl_multi_getcontent($ch);
					wfDebugLog( 'Mentat', 'Result of cURL was: ' . $result[$k] );
					curl_multi_remove_handle($mh, $ch);
				}

				curl_multi_close($mh);
			}
		} );

		return true;
	}
}

?>
