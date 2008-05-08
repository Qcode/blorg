<?php

require_once 'Site/pages/SitePage.php';
require_once 'Blorg/dataobjects/BlorgPostWrapper.php';
require_once 'XML/Atom/Feed.php';
require_once 'XML/Atom/Entry.php';
require_once 'XML/Atom/Link.php';

/**
 * Displays an Atom feed of all recent posts in reverse chronological order
 *
 * The constant MAX_POSTS determines how many posts are displayed in the feed.
 *
 * @package   Blörg
 * @copyright 2008 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class BlorgAtomPage extends SitePage
{
	// {{{ class constants

	const MAX_POSTS = 10;

	// }}}
	// {{{ protected properties

	/**
	 * @var BlorgPostWrapper
	 */
	protected $posts;

	/**
	 * @var XML_Atom_Feed
	 */
	protected $feed;

	// }}}
	// {{{ public function __construct()

	public function __construct(SiteApplication $app, SiteLayout $layout = null)
	{
		$layout = new SiteLayout($app, 'Blorg/layouts/xhtml/atom.php');

		parent::__construct($app, $layout);

		$this->initPosts();
	}

	// }}}
	// {{{ protected function initPosts()

	protected function initPosts()
	{
		$instance_id = $this->app->getInstanceId();

		$sql = sprintf('select * from BlorgPost
			where instance %s %s
				and enabled = true
			order by publish_date desc limit %s',
			SwatDB::equalityOperator($instance_id),
			$this->app->db->quote($instance_id, 'integer'),
			$this->app->db->quote(self::MAX_POSTS, 'integer'));

		$wrapper = SwatDBClassMap::get('BlorgPostWrapper');
		$this->posts = SwatDB::query($this->app->db, $sql, $wrapper);
	}

	// }}}

	// build phase
	// {{{ public function build()

	public function build()
	{
		$this->buildAtomFeed();

		$this->layout->startCapture('content');
		$this->displayAtomFeed();
		$this->layout->endCapture();
	}

	// }}}
	// {{{ protected function buildAtomFeed()

	protected function buildAtomFeed()
	{
		$site_base_href  = $this->app->getBaseHref();
		$blorg_base_href = $site_base_href.$this->app->config->blorg->path;

		$this->feed = new XML_Atom_Feed($blorg_base_href,
			$this->app->config->site->title);

		$this->feed->addLink($site_base_href.$this->source, 'self',
			'application/atom+xml');

		$this->feed->addLink($blorg_base_href, 'alternate', 'text/html');

		$this->feed->setGenerator('Blörg');
		$this->feed->setBase($site_base_href);

		$this->feed->setLogo($site_base_href.'images/elements/title-atom.png');
		$this->feed->setIcon($site_base_href.'favicon.ico');

		foreach ($this->posts as $post) {
			$path = $blorg_base_href.'archive';

			$date = clone $post->publish_date;
			$date->convertTZ($this->app->default_time_zone);
			$year = $date->getYear();
			$month_name = BlorgPageFactory::$month_names[$date->getMonth()];

			$post_uri = sprintf('%s/%s/%s/%s',
				$path,
				$year,
				$month_name,
				$post->shortname);

			$entry = new XML_Atom_Entry($post_uri, $post->title,
				$post->publish_date);

			if (strlen($post->extended_bodytext) > 0) {
				$full_bodytext = $post->bodytext.$post->extended_bodytext;
				$entry->setSummary($post->bodytext, 'html');
				$entry->setContent($full_bodytext, 'html');
			} else {
				$entry->setContent($post->bodytext, 'html');
			}

			foreach ($post->tags as $tag) {
				$entry->addCategory($tag->shortname, $blorg_base_href,
					$tag->title);
			}

			$entry->addLink($post_uri, 'alternate', 'text/html');

			foreach ($post->getVisibleFiles() as $file) {
				$link = new XML_Atom_Link(
					$site_base_href.$file->getRelativeUri(),
					'enclosure',
					$file->mime_type);

				$link->setTitle($file->getDescription());
				$link->setLength($file->filesize);
				$entry->addLink($link);
			}

			if ($post->author->show) {
				$author_uri = $blorg_base_href.'author/'.
					$post->author->shortname;
			} else {
				$author_uri = '';
			}

			$entry->addAuthor($post->author->name, $author_uri,
				$post->author->email);

			$visible_reply_count = count($post->getVisibleReplies());
			if ($post->reply_status == BlorgPost::REPLY_STATUS_OPEN ||
				$post->reply_status == BlorgPost::REPLY_STATUS_MODERATED ||
				($post->reply_status == BlorgPost::REPLY_STATUS_LOCKED &&
				$visible_reply_count > 0)) {
				$entry->addLink($post_uri.'#replies', 'replies', 'text/html');
			}

			$this->feed->addEntry($entry);
		}
	}

	// }}}
	// {{{ protected function displayAtomFeed()

	protected function displayAtomFeed()
	{
		echo $this->feed;
	}

	// }}}
}

?>
