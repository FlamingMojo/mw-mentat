<?php

namespace MediaWiki\Extension\Mentat;

use ForeignTitle;
use ImagePage;
use LocalFile;
use ManualLogEntry;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Context\RequestContext;
use MediaWiki\Hook\AfterImportPageHook;
use MediaWiki\Hook\ArticleMergeCompleteHook;
use MediaWiki\Hook\ArticleRevisionVisibilitySetHook;
use MediaWiki\Hook\BlockIpCompleteHook;
use MediaWiki\Hook\FileDeleteCompleteHook;
use MediaWiki\Hook\FileUndeleteCompleteHook;
use MediaWiki\Hook\PageMoveCompleteHook;
use MediaWiki\Hook\UnblockUserCompleteHook;
use MediaWiki\Hook\UploadCompleteHook;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Hook\ArticleProtectCompleteHook;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;
use MediaWiki\Page\Hook\PageUndeleteCompleteHook;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Parser\Parser;
use MediaWiki\Permissions\Authority;
use MediaWiki\RenameUser\Hook\RenameUserCompleteHook;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\Title\Title;
use MediaWiki\User\Hook\UserGroupsChangedHook;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupMembership;
use MediaWiki\User\UserIdentity;
use UploadBase;
use WikiFilePage;
use WikiPage;

/**
 * Hooks for the Mentat extension
 *
 * @file
 * @ingroup Extensions
 */
class MentatHooks implements
	PageSaveCompleteHook,
	PageDeleteCompleteHook,
	ArticleRevisionVisibilitySetHook,
	ArticleProtectCompleteHook,
	PageMoveCompleteHook,
	LocalUserCreatedHook,
	BlockIpCompleteHook,
	UnblockUserCompleteHook,
	UserGroupsChangedHook,
	UploadCompleteHook,
	FileDeleteCompleteHook,
	FileUndeleteCompleteHook,
	AfterImportPageHook,
	ArticleMergeCompleteHook,
	RenameUserCompleteHook,
	PageUndeleteCompleteHook
{
	/**
	 * @param WikiPage $wikiPage
	 * @param UserIdentity $userIdentity
	 * @param string $summary
	 * @param int $flags
	 * @param RevisionRecord $revisionRecord
	 * @param EditResult $editResult
	 * @return bool
	 */
	public static function onPageSaveComplete(
		$wikiPage,
		$userIdentity,
		$summary,
		$flags,
		$revisionRecord,
		$editResult
	): bool {
		$hookName = 'PageSaveComplete';
		$user = MediaWikiServices::getInstance()->getUserFactory()->newFromUserIdentity( $userIdentity );
		$isNew = $editResult->isNew();
		$msgKey = 'mentat-msg-page-edited';
		if ( $isNew ) {
			$msgKey = 'mentat-msg-page-created';
		}
        $userInfo = MentatUtils::createUserInfo( $user );
        $pageInfo = [
            'message_key' => $msgKey,
            'title' => $wikiPage->getTitle(),
            'url' => $wikiPage->getTitle()->getFullURL( '', false, PROTO_CANONICAL ),
            'summary' => $summary,
            'revision' => MentatUtils::createRevisionInfo( $revisionRecord )
        ];

		MentatMessageSender::sendToMentat( $hookName, $userInfo, $pageInfo );
		return true;
	}

	public static function onPageDeleteComplete(
		ProperPageIdentity $page,
		Authority $deleter,
		string $reason,
		int $pageID,
		RevisionRecord $deletedRev,
		ManualLogEntry $logEntry,
		int $archivedRevisionCount
	): bool {
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

        MentatMessageSender::sendToMentat( $hookName, $userInfo, $pageInfo );
        return true;
	}

	public static function onPageUndeleteComplete(
		ProperPageIdentity $page,
		Authority $restorer,
		string $reason,
		RevisionRecord $restoredRev,
		ManualLogEntry $logEntry,
		int $restoredRevisionCount,
		bool $created,
		array $restoredPageIds
	): void {
		$hookName = 'PageUndeleteComplete';
		$user = RequestContext::getMain()->getUser();
		$title = MediaWikiServices::getInstance()->getTitleFactory()->newFromPageIdentity( $page );
        $userInfo = MentatUtils::createUserInfo( $user );
        $pageInfo = [
            'message_key' => 'mentat-msg-page-undeleted',
            'title' => $title,
            'url' => $title->getFullURL( '', false, PROTO_CANONICAL ),
            'reason' => $reason,
            'created' => $created,
            'revision' => MentatUtils::createRevisionInfo( $revisionRecord )
        ];

        MentatMessageSender::sendToMentat( $hookName, $userInfo, $pageInfo );
	}

	/**
	 * @param Title $title
	 * @param int[] $ids
	 * @param array $visibilityChangeMap
	 * @return bool
	 */
	public static function onArticleRevisionVisibilitySet( $title, $ids, $visibilityChangeMap ): bool {
		$hookName = 'ArticleRevisionVisibilitySet';
		$user = RequestContext::getMain()->getUser();
        $userInfo = MentatUtils::createUserInfo( $user );
        $pageInfo = [
            'message_key' => 'mentat-msg-rev-visibility-changed',
            'title' => $title,
            'url' => $title->getFullURL( '', false, PROTO_CANONICAL ),
            'visibility_changes' => count( $visibilityChangeMap )
        ];

        MentatMessageSender::sendToMentat( $hookName, $userInfo, $pageInfo );
		return true;
	}

	/**
	 * @param WikiPage $wikiPage
	 * @param User $user
	 * @param array $protect
	 * @param string $reason
	 * @return bool
	 */
	public static function onArticleProtectComplete( $wikiPage, $user, $protect, $reason ): bool {
		$hookName = 'ArticleProtectComplete';
        $user = RequestContext::getMain()->getUser();
        $userInfo = MentatUtils::createUserInfo( $user );
        $pageInfo = [
            'message_key' => 'mentat-msg-page-protect',
            'title' => wikiPage->getTitle(),
            'url' => wikiPage->getTitle()->getFullURL( '', false, PROTO_CANONICAL ),
            'reason' => $reason,
            'protect' => $protect
        ];

        MentatMessageSender::sendToMentat( $hookName, $userInfo, $pageInfo );
		return true;
	}

	/**
	 * @param LinkTarget $old
	 * @param LinkTarget $new
	 * @param UserIdentity $userIdentity
	 * @param int $pageid
	 * @param int $redirid
	 * @param string $reason
	 * @param RevisionRecord $revision
	 * @return bool
	 */
	public static function onPageMoveComplete(
		$old,
		$new,
		$userIdentity,
		$pageid,
		$redirid,
		$reason,
		$revision
	): bool {
		global $wgMentatNoBots;
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

        MentatMessageSender::sendToMentat( $hookName, $userInfo, $pageInfo );
		return true;
	}

	/**
	 * @param User $user
	 * @param bool $autocreated
	 * @return bool
	 */
	public static function onLocalUserCreated( $user, $autocreated ): bool {
		$hookName = 'LocalUserCreated';
        $userInfo = MentatUtils::createUserInfo( $user );
        $pageInfo = [
            'message_key' => 'mentat-msg-user-registered'
        ];

        MentatMessageSender::sendToMentat( $hookName, $userInfo, $pageInfo );
		return true;
	}

	/**
	 * @param DatabaseBlock $block
	 * @param User $user
	 * @param ?DatabaseBlock $priorBlock
	 * @return bool
	 */
	public static function onBlockIpComplete( $block, $user, $priorBlock ): bool {
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
            'taget' => $targetInfo
        ];

        MentatMessageSender::sendToMentat( $hookName, $userInfo, $pageInfo );
		return true;
	}

	/**
	 * @param DatabaseBlock $block
	 * @param User $user
	 * @return bool
	 */
	public static function onUnblockUserComplete( $block, $user ): bool {
		$hookName = 'UnblockUserComplete';
		$target = $block->getTargetUserIdentity();
		if ( $target === null ) {
			$target = $block->getTargetName();
		}
        $userInfo = MentatUtils::createUserInfo( $user );
        $targetInfo = MentatUtils::createUserInfo( $target );
        $pageInfo = [
            'message_key' => 'mentat-msg-user-unblock',
            'taget' => $targetInfo
        ];

        MentatMessageSender::sendToMentat( $hookName, $userInfo, $pageInfo );
		return true;
	}

	/**
	 * @param User|UserIdentity $user
	 * @param string[] $added
	 * @param string[] $removed
	 * @param User|false $performer
	 * @param string|false $reason
	 * @param UserGroupMembership[] $oldUGMs
	 * @param UserGroupMembership[] $newUGMs
	 * @return bool
	 */
	public static function onUserGroupsChanged( $user, $added, $removed, $performer, $reason, $oldUGMs, $newUGMs ): bool {
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

        MentatMessageSender::sendToMentat( $hookName, $userInfo, $pageInfo );
		return true;
	}

	/**
	 * @param UploadBase $uploadBase
	 * @return bool
	 */
	public static function onUploadComplete( $uploadBase ): bool {
		$hookName = 'UploadComplete';
		$lf = $uploadBase->getLocalFile();
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

        MentatMessageSender::sendToMentat( $hookName, $userInfo, $pageInfo );
		return true;
	}

	/**
	 * @param LocalFile $file
	 * @param string|null $oldimage
	 * @param WikiFilePage|null $article
	 * @param User $user
	 * @param string $reason
	 * @return bool
	 */
	public static function onFileDeleteComplete( $file, $oldimage, $article, $user, $reason ): bool {
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

        MentatMessageSender::sendToMentat( $hookName, $userInfo, $pageInfo );
		return true;
	}

	/**
	 * @param Title $title
	 * @param int[] $fileVersions
	 * @param User $user
	 * @param string $reason
	 * @return bool
	 */
	public static function onFileUndeleteComplete( $title, $fileVersions, $user, $reason ): bool {
		$hookName = 'FileUndeleteComplete';
        $userInfo = MentatUtils::createUserInfo( $user );
        $pageInfo = [
            'message_key' => 'mentat-msg-file-undelete',
            'reason' => $reason,
            'title' => $title,
            'url' => $title->getFullURL( '', false, PROTO_CANONICAL )
        ];

        MentatMessageSender::sendToMentat( $hookName, $userInfo, $pageInfo );
		return true;
	}

	/**
	 * @param Title $title
	 * @param ForeignTitle $foreignTitle
	 * @param int $revCount
	 * @param int $sRevCount
	 * @param array $pageInfo
	 * @return bool
	 */
	public static function onAfterImportPage( $title, $foreignTitle, $revCount, $sRevCount, $pageInfo ): bool {
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

        MentatMessageSender::sendToMentat( $hookName, $userInfo, $pageInfo );
		return true;
	}

	/**
	 * @param Title $targetTitle
	 * @param Title $destTitle
	 * @return bool
	 */
	public static function onArticleMergeComplete( $targetTitle, $destTitle ): bool {
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

        MentatMessageSender::sendToMentat( $hookName, $userInfo, $pageInfo );
		return true;
	}

	/**
	 * @param null $output
	 * @param Title $title
	 * @param int $rev_id
	 * @param mixed $content
	 * @return bool
	 * @see https://github.com/wikimedia/mediawiki-extensions-ApprovedRevs/blob/REL1_45/includes/ApprovedRevs.php#L602
	 */
	public static function onApprovedRevsRevisionApproved( $output, $title, $rev_id, $content ): bool {
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

        MentatMessageSender::sendToMentat( $hookName, $userInfo, $pageInfo );
		return true;
	}

	/**
	 * @param null $output
	 * @param Title $title
	 * @param mixed $content
	 * @return bool
	 * @see https://github.com/wikimedia/mediawiki-extensions-ApprovedRevs/blob/REL1_45/includes/ApprovedRevs.php#L712
	 */
	public static function onApprovedRevsRevisionUnapproved( $output, $title, $content ): bool {
		$hookName = 'ApprovedRevsRevisionUnapproved';
		$user = RequestContext::getMain()->getUser();
		$userInfo = MentatUtils::createUserInfo( $user );
        $pageInfo = [
            'message_key' => 'mentat-msg-ext-approvedrevs-unapproved',
            'title' => $title,
            'url' => $title->getFullURL( '', false, PROTO_CANONICAL )
        ];
        MentatMessageSender::sendToMentat( $hookName, $userInfo, $pageInfo );
		return true;
	}

	/**
	 * @param Parser $parser
	 * @param Title $title
	 * @param string $timestamp
	 * @param string $sha1
	 * @return bool
	 * @see https://github.com/wikimedia/mediawiki-extensions-ApprovedRevs/blob/REL1_45/includes/ApprovedRevs.php#L827
	 */
	public static function onApprovedRevsFileRevisionApproved( $parser, $title, $timestamp, $sha1 ): bool {
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
        MentatMessageSender::sendToMentat( $hookName, $userInfo, $pageInfo );
		return true;
	}

	/**
	 * @param Parser $parser
	 * @param Title $title
	 * @return bool
	 * @see https://github.com/wikimedia/mediawiki-extensions-ApprovedRevs/blob/REL1_45/includes/ApprovedRevs.php#L865
	 */
	public static function onApprovedRevsFileRevisionUnapproved( $parser, $title ): bool {
		$hookName = 'ApprovedRevsFileRevisionUnapproved';
		$user = RequestContext::getMain()->getUser();
        $userInfo = MentatUtils::createUserInfo( $user );
        $pageInfo = [
            'message_key' => 'mentat-msg-ext-approvedrevs-unapproved-file',
            'title' => $title,
            'url' => $title->getFullURL( '', false, PROTO_CANONICAL )
        ];
        MentatMessageSender::sendToMentat( $hookName, $userInfo, $pageInfo );
		return true;
	}

	/**
	 * @param int $uid
	 * @param string $old
	 * @param string $new
	 * @return void
	 */
	public static function onRenameUserComplete( $uid, $old, $new ): void {
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
        MentatMessageSender::sendToMentat( $hookName, $userInfo, $pageInfo );
	}
}
