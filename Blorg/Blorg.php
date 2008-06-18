<?php

require_once 'Swat/Swat.php';
require_once 'Swat/SwatHtmlHeadEntrySet.php';
require_once 'Swat/SwatLinkHtmlHeadEntry.php';
require_once 'Site/Site.php';
require_once 'Admin/Admin.php';
require_once 'Blorg/BlorgViewFactory.php';

/**
 * Container for package wide static methods
 *
 * @package   Blörg
 * @copyright 2008 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class Blorg
{
	// {{{ constants

	/**
	 * The package identifier
	 */
	const PACKAGE_ID = 'Blorg';

	const GETTEXT_DOMAIN = 'blorg';

	// }}}
	// {{{ public static function _()

	public static function _($message)
	{
		return Blorg::gettext($message);
	}

	// }}}
	// {{{ public static function gettext()

	public static function gettext($message)
	{
		return dgettext(Blorg::GETTEXT_DOMAIN, $message);
	}

	// }}}
	// {{{ public static function ngettext()

	public static function ngettext($singular_message,
		$plural_message, $number)
	{
		return dngettext(Blorg::GETTEXT_DOMAIN,
			$singular_message, $plural_message, $number);
	}

	// }}}
	// {{{ public static function setupGettext()

	public static function setupGettext()
	{
		bindtextdomain(Blorg::GETTEXT_DOMAIN, '@DATA-DIR@/Blorg/locale');
		bind_textdomain_codeset(Blorg::GETTEXT_DOMAIN, 'UTF-8');
	}

	// }}}
	// {{{ public static function getDependencies()

	/**
	 * Gets the packages this package depends on
	 *
	 * @return array an array of package IDs that this package depends on.
	 */
	public static function getDependencies()
	{
		return array(Swat::PACKAGE_ID, Site::PACKAGE_ID, Admin::PACKAGE_ID);
	}

	// }}}
	// {{{ public static function getConfigDefinitions()

	/**
	 * Gets configuration definitions used by the Blörg package
	 *
	 * Applications should add these definitions to their config module before
	 * loading the application configuration.
	 *
	 * This contains default configuration values which may be overridden in
	 * a loaded configuration file.
	 *
	 * @return array the configuration definitions used by this package.
	 *
	 * @see SiteConfigModule::addDefinitions()
	 */
	public static function getConfigDefinitions()
	{
		return array(
			// Optional path prefix for all Blörg content. If specified, this
			// must have a trailing slash. This is used to integrate Blörg
			// content into another site.
			'blorg.path'                   => '',

			// Optional Wordpress API key for Akismet spam filtering.
			'blorg.akismet_key'            => null,

			// Default post comment status. Valid config values are 'open',
			// 'moderated', 'locked' and 'closed'. Any other values are
			// interpreted as 'closed'.
			'blorg.default_comment_status' => 'closed',

			// Whether or not to show a list of recent posts by the author on
			// the author details page.
			'blorg.show_author_posts'      => false,

			// A header image for this blorg
			'blorg.header_image'            => null,

			// Optional tagline.
			'site.tagline'                 => null,

			// Optional ad placements. Values should contain the markup for
			// embedding the ad in the position.
			'blorg.ad_top'                 => null,
			'blorg.ad_bottom'              => null,
			'blorg.ad_post_content'        => null,
			'blorg.ad_post_comments'       => null,

			// Whether ads should only displayed when the page is linked to by
			// another website.
			'blorg.ad_referers_only'       => false,
		);
	}

	// }}}
	// {{{ public static function getHtmlHeadEntrySet()

	/**
	 * Gets site-wide HTML head entries for sites using Blörg
	 *
	 * Applications may add these head entries to their layout.
	 *
	 * @return SwatHtmlHeadEntrySet the HTML head entries used by Blörg.
	 */
	public static function getHtmlHeadEntrySet(SiteApplication $app)
	{
		$set = new SwatHtmlHeadEntrySet();

		$blorg_base_href = $app->config->blorg->path;

		$recent_posts = new SwatLinkHtmlHeadEntry(
			$blorg_base_href.'feed', 'alternate',
			'application/atom+xml', Blorg::_('Recent Posts'));

		$recent_comments = new SwatLinkHtmlHeadEntry(
			$blorg_base_href.'feed/comments', 'alternate',
			'application/atom+xml', Blorg::_('Recent Comments'));

		$set->addEntry($recent_posts);
		$set->addEntry($recent_comments);

		return $set;
	}

	// }}}
	// {{{ public static function displayAd()

	/**
	 * Display an ad
	 *
	 * If $config->blorg->ad_referers_only is true, the referer's domain is
	 * checked against the site's domain to ensure the page has been arrived at
	 * via another site.
	 *
	 * @param SiteApplication $app The current application
	 * @param string $ad_type The type of ad to display
	 */
	public static function displayAd(SiteApplication $app, $type)
	{
		$type_name = 'ad_'.$type;

		if ($app->config->blorg->$type_name != '' && (
			!$app->config->blorg->ad_referers_only ||
			substr($_SERVER['HTTP_REFERER'], 0,
				strlen($app->getBaseHref())) !=
					$app->getBaseHref())) {

				echo $app->config->blorg->$type_name;
		}
	}

	// }}}
	// {{{ private function __construct()

	/**
	 * Prevent instantiation of this static class
	 */
	private function __construct()
	{
	}

	// }}}
}

Blorg::setupGettext();
SwatUI::mapClassPrefixToPath('Blorg', 'Blorg');

SwatDBClassMap::addPath('Blorg/dataobjects');

BlorgViewFactory::addPath('Blorg/views');
BlorgViewFactory::registerView('post',    'BlorgPostView');
BlorgViewFactory::registerView('comment', 'BlorgCommentView');
BlorgViewFactory::registerView('author',  'BlorgAuthorView');

?>
