<?php

use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\User\UserIdentity;

/**
 * Hooks for the Mentat extension
 *
 * @file
 * @ingroup Extensions
 */
class MentatHooks {
	/**
	 * Called when a page is created or edited
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageSaveComplete
	 */
	public static function onPageSaveComplete( WikiPage $wikiPage, UserIdentity $userIdentity, string $summary, int $flags, RevisionRecord $revision, EditResult $editResult ) {
		$hookName = 'PageContentSaveComplete';
        $user = MediaWikiServices::getInstance()->getUserFactory()->newFromUserIdentity( $userIdentity );
		$isNew = $editResult->isNew();
		$msgKey = 'mentat-msg-page-edited';
		if ( $isNew ) { // is a new page
			$msgKey = 'mentat-msg-page-created';
		}
		$revisionInfo = MentatUtils::createRevisionInfo( $revision );
		$userInfo = MentatUtils::createUserInfo( $user );
        $pageInfo = [
            'message_key' => $msgKey,
            'title' => $wikiPage->getTitle(),
            'url' => $wikiPage->getTitle()->getFullURL( '', false, PROTO_CANONICAL ),
            'summary' => $summary,
            'revision' => $revisionInfo
        ];
		$payload = MentatUtils::buildRequest($hookName, $userInfo, $pageInfo);
		MentatUtils::handleMentat($hookName, $payload);
		
		return true;
	}

	/**
	 * Called when a page is deleted
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageDeleteComplete
	 */
	public static function onPageDeleteComplete( MediaWiki\Page\ProperPageIdentity $page, MediaWiki\Permissions\Authority $deleter, string $reason, int $pageID, MediaWiki\Revision\RevisionRecord $deletedRev, ManualLogEntry $logEntry, int $archivedRevisionCount ) {
		$hookName = 'PageDeleteComplete';
		$user = MediaWikiServices::getInstance()->getUserFactory()->newFromUserIdentity( $deleter->getUser() );
		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $page );
        $userInfo = MentatUtils::createUserInfo( $user );
        $pageInfo = [
            'message_key' => 'mentat-msg-page-deleted',
            'title' => $page->getTitle(),
            'url' => $page->getTitle()->getFullURL( '', false, PROTO_CANONICAL ),
            'reason' => $reason,
            'archived_revisions' => $archivedRevisionCount
        ];
		$payload = MentatUtils::buildRequest($hookName, $userInfo, $pageInfo);
		MentatUtils::handleMentat($hookName, $payload);
		return true;
	}

	/**
	 * Called when a page's revisions are restored
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ArticleUndelete
	 */
	public static function onArticleUndelete( Title $title, $create, $comment, $oldPageId, $restoredPages ) {
		$hookName = 'PageUndeleteComplete';
		$user = RequestContext::getMain()->getUser();
        $userInfo = MentatUtils::createUserInfo( $user );
        $pageInfo = [
            'message_key' => 'mentat-msg-page-undeleted',
            'title' => $title,
            'url' => $title->getFullURL( '', false, PROTO_CANONICAL ),
            'comment' => $comment
        ];
		$payload = MentatUtils::buildRequest($hookName, $userInfo, $pageInfo);
		MentatUtils::handleMentat($hookName, $payload);
		return true;
	}

	/**
	 * Called after committing revision visibility changes to the database
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ArticleRevisionVisibilitySet
	 */
	public static function onArticleRevisionVisibilitySet( &$title, $ids, $visibilityChangeMap ) {
		$hookName = 'ArticleRevisionVisibilitySet';
		$user = RequestContext::getMain()->getUser();
        $userInfo = MentatUtils::createUserInfo( $user );
        $pageInfo = [
            'message_key' => 'mentat-msg-rev-visibility-changed',
            'title' => $title,
            'url' => $title->getFullURL( '', false, PROTO_CANONICAL ),
            'visibility_changes' => count( $visibilityChangeMap )
        ];
		$payload = MentatUtils::buildRequest($hookName, $userInfo, $pageInfo);
		MentatUtils::handleMentat($hookName, $payload);
		return true;
	}

	/**
	 * Called when a page is protected (or unprotected)
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ArticleProtectComplete
	 */
	public static function onArticleProtectComplete( &$article, &$user, $protect, $reason ) {
		$hookName = 'ArticleProtectComplete';
        $user = RequestContext::getMain()->getUser();
        $userInfo = MentatUtils::createUserInfo( $user );
        $pageInfo = [
            'message_key' => 'mentat-msg-page-protect',
            'title' => $article->getTitle(),
            'url' => $article->getTitle()->getFullURL( '', false, PROTO_CANONICAL ),
            'reason' => $reason,
            'protect' => $protect
        ];
		$payload = MentatUtils::buildRequest($hookName, $userInfo, $pageInfo);
		MentatUtils::handleMentat($hookName, $payload);
		return true;
	}

	/**
	 * Called when a page is moved
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageMoveComplete
	 */
	public static function onPageMoveComplete( LinkTarget $old, LinkTarget $new, UserIdentity $userIdentity, int $pageid, int $redirid, string $reason, RevisionRecord $revision ) {
		$hookName = 'PageMoveComplete';
		$user = MediaWikiServices::getInstance()->getUserFactory()->newFromUserIdentity( $userIdentity );
        $userInfo = MentatUtils::createUserInfo( $user );
        $pageInfo = [
            'message_key' => 'mentat-msg-page-moved',
            'old_title' => $old,
            'old_url' => Title::castFromLinkTarget( $old )->getFullURL( '', false, PROTO_CANONICAL ),
            'title' => $new,
            'url' => Title::castFromLinkTarget( $new )->getFullURL( '', false, PROTO_CANONICAL ),
            'reason' => $reason
        ];
		$payload = MentatUtils::buildRequest($hookName, $userInfo, $pageInfo);
		MentatUtils::handleMentat($hookName, $payload);
		return true;
	}

	/**
	 * Called when a user is created
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LocalUserCreated
	 */
	public static function onLocalUserCreated( $user, $autocreated ) {
		$hookName = 'LocalUserCreated';
        $userInfo = MentatUtils::createUserInfo( $user );
        $pageInfo = [
            'message_key' => 'mentat-msg-user-registered'
        ];
		$payload = MentatUtils::buildRequest($hookName, $userInfo, $pageInfo);
		MentatUtils::handleMentat($hookName, $payload);
		return true;
	}

	/**
	 * Called when a user is blocked
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/BlockIpComplete
	 */
	public static function onBlockIpComplete( Block $block, User $user ) {
		$hookName = 'BlockIpComplete';
		$expiry = $block->getExpiry();
		$expiryAsUnix = strtotime( $expiry );
		$target = $block->getTargetUserIdentity();
		if ( $target === null ) {
			$target = $block->getTargetName();
		}
		$msgName = $block->isSitewide() ? 'mentat-msg-user-block' : 'mentat-msg-user-block-partial';
        $userInfo = MentatUtils::createUserInfo( $user );
        $targetInfo = MentatUtils::createUserInfo( $target );
        $pageInfo = [
            'message_key' => $msgName,
            'reason' => $block->getReasonComment()->text,
            'expiry' => $expiry,
            'expiry_as_unix' => $expiryAsUnix,
            'target' => $targetInfo
        ];
		$payload = MentatUtils::buildRequest($hookName, $userInfo, $pageInfo);
		MentatUtils::handleMentat($hookName, $payload);
		return true;
	}

	/**
	 * Called when a user is unblocked
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UnblockUserComplete
	 */
	public static function onUnblockUserComplete( Block $block, User $user ) {
		$hookName = 'UnblockUserComplete';
		$target = $block->getTargetUserIdentity();
		if ( $target === null ) {
			$target = $block->getTargetName();
		}
        $userInfo = MentatUtils::createUserInfo( $user );
        $targetInfo = MentatUtils::createUserInfo( $target );
        $pageInfo = [
            'message_key' => 'mentat-msg-user-unblock',
            'target' => $targetInfo
        ];
		$payload = MentatUtils::buildRequest($hookName, $userInfo, $pageInfo);
		MentatUtils::handleMentat($hookName, $payload);
		return true;
	}

	/**
	 * Called when a user's rights are changed
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UserGroupsChanged
	 */
	public static function onUserGroupsChanged( User $user, array $added, array $removed, $performer, $reason ) {
		$hookName = 'UserGroupsChanged';

		if ( $performer === false ) {
			// Rights were changed by autopromotion, do nothing
			return true;
		}

        $userInfo = MentatUtils::createUserInfo( $user );
        $performerInfo = MentatUtils::createUserInfo( $performer );
        $pageInfo = [
            'message_key' => 'mentat-msg-user-groups-changed',
            'reason' => $reason,
            'added' => $added,
            'removed' => $removed,
            'performer' => $performerInfo
        ];
		$payload = MentatUtils::buildRequest($hookName, $userInfo, $pageInfo);
		MentatUtils::handleMentat($hookName, $payload);		
		return true;
	}

	/**
	 * Called when a file upload is complete
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UploadComplete
	 */
	public static function onUploadComplete( &$image ) {
		$hookName = 'UploadComplete';
		$lf = $image->getLocalFile();
		$user = MediaWikiServices::getInstance()->getUserFactory()->newFromUserIdentity( $lf->getUploader() );
		$comment = $lf->getDescription();
		$isNewRevision = count( $lf->getHistory() ) > 0;
        $userInfo = MentatUtils::createUserInfo( $user );
        $pageInfo = [
            'message_key' => 'mentat-msg-file-upload',
            'comment' => $comment,
            'new_revision' => $isNewRevision,
            'file_name' => $lf->getName(),
            'url' => $lf->getTitle()->getFullURL( '', false, PROTO_CANONICAL ),
            'width' => $lf->getWidth(),
            'height' => $lf->getHeight(),
            'mime_type' => $lf->getMimeType()
        ];
		$payload = MentatUtils::buildRequest($hookName, $userInfo, $pageInfo);
		MentatUtils::handleMentat($hookName, $payload);			
		return true;
	}

	/**
	 * Called when a file is deleted
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/FileDeleteComplete
	 */
	public static function onFileDeleteComplete( $file, $oldimage, $article, $user, $reason ) {
		$hookName = 'FileDeleteComplete';
		if ( $article ) {
			// Entire page was deleted, onArticleDeleteComplete will handle this
			return true;
		}
        $userInfo = MentatUtils::createUserInfo( $user );
        $pageInfo = [
            'message_key' => 'mentat-msg-file-delete',
            'reason' => $reason,
            'file_name' => $file->getName(),
            'url' => $file->getTitle()->getFullURL( '', false, PROTO_CANONICAL )
        ];
		$payload = MentatUtils::buildRequest($hookName, $userInfo, $pageInfo);
		MentatUtils::handleMentat($hookName, $payload);			
		return true;
	}

	/**
	 * Called when a file is restored
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/FileUndeleteComplete
	 */
	public static function onFileUndeleteComplete( $title, $fileVersions, $user, $reason ) {
		$hookName = 'FileUndeleteComplete';
        $userInfo = MentatUtils::createUserInfo( $user );
        $pageInfo = [
            'message_key' => 'mentat-msg-file-undelete',
            'reason' => $reason,
            'title' => $title,
            'url' => $title->getFullURL( '', false, PROTO_CANONICAL )
        ];
		$payload = MentatUtils::buildRequest($hookName, $userInfo, $pageInfo);
		MentatUtils::handleMentat($hookName, $payload);				
		return true;
	}

	/**
	 * Called when a page is imported
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/AfterImportPage
	 */
	public static function onAfterImportPage( $title, $origTitle, $revCount, $sRevCount, $pageInfo ) {
		$hookName = 'AfterImportPage';
		$user = RequestContext::getMain()->getUser();
        $userInfo = MentatUtils::createUserInfo( $user );
        $pageInfo = [
            'message_key' => 'mentat-msg-import',
            'reason' => $reason,
            'title' => $title,
            'url' => $title->getFullURL( '', false, PROTO_CANONICAL ),
            'rev_count' => $revCount,
            's_rev_count' => $sRevCount
        ];
		$payload = MentatUtils::buildRequest($hookName, $userInfo, $pageInfo);
		MentatUtils::handleMentat($hookName, $payload);				
		return true;
	}

	public static function onArticleMergeComplete( $targetTitle, $destTitle ) {
		$hookName = 'ArticleMergeComplete';
		$user = RequestContext::getMain()->getUser();
        $userInfo = MentatUtils::createUserInfo( $user );
        $pageInfo = [
            'message_key' => 'mentat-msg-page-merge',
            'reason' => $reason,
            'original_title' => $targetTitle,
            'original_url' => $targetTitle->getFullURL( '', false, PROTO_CANONICAL ),
            'title' => $destTitle,
            'url' => $destTitle->getFullURL( '', false, PROTO_CANONICAL )
        ];
		$payload = MentatUtils::buildRequest($hookName, $userInfo, $pageInfo);
		MentatUtils::handleMentat($hookName, $payload);				
		return true;
	}

	/**
	 * Called when a revision is approved (Approved Revs extension)
	 * @see https://github.com/wikimedia/mediawiki-extensions-ApprovedRevs/blob/REL1_34/includes/ApprovedRevs_body.php
	 */
	public static function onApprovedRevsRevisionApproved ( $output, $title, $rev_id, $content ) {
		$hookName = 'ApprovedRevsRevisionApproved';
		$user = RequestContext::getMain()->getUser();
		// Get the revision being approved here
		$rev = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionByTitle( $title, $rev_id );
		$revLink = $title->getFullURL( '', false, PROTO_CANONICAL );
		$revAuthor = $rev->getUser( RevisionRecord::RAW );
        $userInfo = MentatUtils::createUserInfo( $user );
        $revAuthorInfo = MentatUtils::createUserInfo( $revAuthor );
        $pageInfo = [
            'message_key' => 'mentat-msg-ext-approvedrevs-approved',
            'revision' => $rev_id,
            'revision_url' => $revLink,
            'revision_author' => $revAuthorInfo,
            'title' => $title,
            'url' => $title->getFullURL( '', false, PROTO_CANONICAL )
        ];
		$payload = MentatUtils::buildRequest($hookName, $userInfo, $pageInfo);
		MentatUtils::handleMentat($hookName, $payload);				
		return true;
	}

	/**
	 * Called when a revision is unapproved (Approved Revs extension)
	 * @see https://github.com/wikimedia/mediawiki-extensions-ApprovedRevs/blob/REL1_34/includes/ApprovedRevs_body.php
	 */
	public static function onApprovedRevsRevisionUnapproved ( $output, $title, $content ) {
		$hookName = 'ApprovedRevsRevisionUnapproved';
		$user = RequestContext::getMain()->getUser();
		$userInfo = MentatUtils::createUserInfo( $user );
        $pageInfo = [
            'message_key' => 'mentat-msg-ext-approvedrevs-unapproved',
            'title' => $title,
            'url' => $title->getFullURL( '', false, PROTO_CANONICAL )
        ];
		$payload = MentatUtils::buildRequest($hookName, $userInfo, $pageInfo);
		MentatUtils::handleMentat($hookName, $payload);				
		return true;
	}

	/**
	 * Called when a file is approved (Approved Revs extension)
	 * @see https://github.com/wikimedia/mediawiki-extensions-ApprovedRevs/blob/REL1_34/includes/ApprovedRevs_body.php
	 */
	public static function onApprovedRevsFileRevisionApproved ( $parser, $title, $timestamp, $sha1 ) {
		$hookName = 'ApprovedRevsFileRevisionApproved';
		$user = RequestContext::getMain()->getUser();
		$imagepage = ImagePage::newFromID( $title->getArticleID() );
		$displayedFile = $imagepage->getDisplayedFile();
		// getFullURL doesn't work quite the same on File classes
		$displayedFileUrl = $displayedFile->getCanonicalUrl();
		$uploader = MediaWikiServices::getInstance()->getUserFactory()->newFromUserIdentity(
			$displayedFile->getUploader() );
        $userInfo = MentatUtils::createUserInfo( $user );
        $uploaderInfo = MentatUtils::createUserInfo( $uploader );
        $pageInfo = [
            'message_key' => 'mentat-msg-ext-approvedrevs-approved-file',
            'title' => $title,
            'url' => $title->getFullURL( '', false, PROTO_CANONICAL ),
            'image_url' => $displayedFileUrl,
            'uploader' => $uploaderInfo
        ];
		$payload = MentatUtils::buildRequest($hookName, $userInfo, $pageInfo);
		MentatUtils::handleMentat($hookName, $payload);				
		return true;
	}

	/**
	 * Called when a file is unapproved (Approved Revs extension)
	 * @see https://github.com/wikimedia/mediawiki-extensions-ApprovedRevs/blob/REL1_34/includes/ApprovedRevs_body.php
	 */
	public static function onApprovedRevsFileRevisionUnapproved ( $parser, $title ) {
		$hookName = 'ApprovedRevsFileRevisionUnapproved';
		$user = RequestContext::getMain()->getUser();
        $userInfo = MentatUtils::createUserInfo( $user );
        $pageInfo = [
            'message_key' => 'mentat-msg-ext-approvedrevs-unapproved-file',
            'title' => $title,
            'url' => $title->getFullURL( '', false, PROTO_CANONICAL )
        ];
		$payload = MentatUtils::buildRequest($hookName, $userInfo, $pageInfo);
		MentatUtils::handleMentat($hookName, $payload);			
		return true;
	}

	/**
	 * Called when a user is renamed (Renameuser extension)
	 * @see https://github.com/wikimedia/mediawiki-extensions-Renameuser/blob/REL1_36/includes/RenameuserSQL.php
	 */
	public static function onRenameUserComplete ( $uid, $old, $new ) {
		$hookName = 'RenameUserComplete';
		$user = RequestContext::getMain()->getUser();
		$renamedUserAsTitle = MediaWikiServices::getInstance()->getUserFactory()->newFromName( $new )->getUserPage();
        $userInfo = MentatUtils::createUserInfo( $user );
        $pageInfo = [
            'message_key' => 'mentat-msg-user-rename',
            'old_username' => $old,
            'new_username' => $new,
            'title' => $renamedUserAsTitle,
            'url' => $renamedUserAsTitle->getFullURL( '', false, PROTO_CANONICAL )
        ];
		$payload = MentatUtils::buildRequest($hookName, $userInfo, $pageInfo);
		MentatUtils::handleMentat($hookName, $payload);					
		return true;
	}
}
