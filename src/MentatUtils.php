<?php

namespace MediaWiki\Extension\Mentat;

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;

class MentatUtils {
	/**
	 * Creates userInfo array for MediaWiki User object
	 * @param User|UserIdentity $user
	 * @return array
	 */
	public static function createUserInfo( User|UserIdentity $user ): array {
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
	 * @param RevisionRecord $revision
	 * @return array
	 */
	public static function createRevisionInfo( RevisionRecord $revision ): array {
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
		return $info
	}
}
