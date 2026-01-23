# Mentat (mw-mentat)
MediaWiki extension for sending notifications to the Wiki Mentat system.

## Requirements
- **MediaWiki**: This extension aims to always support the [latest LTS release](https://www.mediawiki.org/wiki/Version_lifecycle).
  - The minimum MediaWiki version supported is v1.40.0
- **[cURL](https://www.php.net/curl)** (likely installed already).

## Installation

1. Clone this repository to your MediaWiki installation's `extensions` folder using `git clone https://github.com/FlamingMojo/mw-mentat.git`
2. Modify your `LocalSettings.php` file and add:

```php
// Load the extension
wfLoadExtension( 'Mentat' );
```

## Configuration
This extension can be configured using the `LocalSettings.php` file in your MediaWiki installation.
By default, no additional configuration is needed - webhooks will be sent to the main Wiki Mentat system, 
hosted by FlamingMojo and tying directly into the Wiki Mentat Discord Bot.

Both this extension and the Wiki Mentat system are open source, it is possible to host your own instance and create your 
own Discord Bot. Keep in mind this would require significant knowledge of server app hosting.

| Variable | Type | Description                | Default |
| --- | --- |----------------------------| --- |
| `$wgDiscordWebhookURL` | string/array | Mentat system webhook URLs | `https://mentat.wiki/webhook`

## Hooks used
- `PageSaveComplete` - New edits to pages and page creations
- `PageDeleteComplete` - Page deletions
- `PageUndeleteComplete` - Page restorations
- `ArticleRevisionVisibilitySet` - Revision visibility changes
- `ArticleProtectComplete` - Page protections
- `PageMoveComplete` - Page moves
- `LocalUserCreated` - User registrations
- `BlockIpComplete` - User blocked
- `UnblockUserComplete` - User unblocked
- `UserGroupsChanged` - User rights changed
- `UploadComplete` - File was uploaded
- `FileDeleteComplete` - File revision was deleted
- `FileUndeleteComplete` - File revision was restored
- `AfterImportPage` - Page was imported
- `ArticleMergeComplete` - Article histories were merged
- `RenameUserComplete` - Rename was completed

### [Approved Revs](https://www.mediawiki.org/wiki/Extension:Approved_Revs)
- `ApprovedRevsRevisionApproved` - Revision was approved
- `ApprovedRevsFileRevisionApproved` - File revision was approved
- `ApprovedRevsRevisionUnapproved` - Revision was unapproved
- `ApprovedRevsFileRevisionUnapproved` - File revision was unapproved


## License
This extension is available under the MIT license. You can [see here](LICENSE) for more information.

This extension was inspired by Jak Taylor's [mw-discord](https://github.com/jayktaylor/mw-discord) extension.
