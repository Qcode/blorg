<?php

require_once 'SwatDB/SwatDBClassMap.php';
require_once 'Swat/SwatPagination.php';
require_once 'Site/pages/SitePage.php';
require_once 'Site/exceptions/SiteNotFoundException.php';
require_once 'Blorg/BlorgViewFactory.php';
require_once 'Blorg/dataobjects/BlorgPostWrapper.php';

/**
 * Displays all recent posts in reverse chronological order
 *
 * The constant MAX_POSTS determines how many posts are displayed on the page.
 *
 * @package   Blörg
 * @copyright 2008 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class BlorgFrontPage extends SitePage
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
	 * @var integer
	 */
	protected $current_page = 1;

	// }}}
	// {{{ public function __construct()

	public function __construct(SiteApplication $app, SiteLayout $layout = null,
		$current_page = 1)
	{
		parent::__construct($app, $layout);
		$this->initPosts($current_page);
	}

	// }}}
	// {{{ protected function initPosts()

	protected function initPosts($current_page)
	{
		$instance_id = $this->app->getInstanceId();

		$sql = sprintf('select * from BlorgPost
			where instance %s %s
				and enabled = %s
			order by publish_date desc',
			SwatDB::equalityOperator($instance_id),
			$this->app->db->quote($instance_id, 'integer'),
			$this->app->db->quote(true, 'boolean'));

		$offset = ($current_page - 1) * self::MAX_POSTS;
		$this->app->db->setLimit(self::MAX_POSTS, $offset);

		$wrapper = SwatDBClassMap::get('BlorgPostWrapper');
		$this->posts = SwatDB::query($this->app->db, $sql, $wrapper);

		if (count($this->posts) == 0) {
			throw new SiteNotFoundException('Page not found.');
		}

		$this->current_page = $current_page;
	}

	// }}}

	// build phase
	// {{{ public function build()

	public function build()
	{
		$this->layout->startCapture('content');
		$this->displayPosts();
		$this->displayFooter();
		$this->layout->endCapture();
	}

	// }}}
	// {{{ protected function displayPosts()

	protected function displayPosts()
	{
		$view = BlorgViewFactory::build('post', $this->app);
		$view->setPartMode('extended_bodytext', BlorgView::MODE_SUMMARY);
		foreach ($this->posts as $post) {
			$view->display($post);
		}
	}

	// }}}
	// {{{ protected function displayFooter()

	protected function displayFooter()
	{
		echo '<div class="footer">';

		$path = $this->app->config->blorg->path;

		$instance_id = $this->app->getInstanceId();

		$sql = sprintf('select count(id) from BlorgPost
			where instance %s %s
				and enabled = %s',
			SwatDB::equalityOperator($instance_id),
			$this->app->db->quote($instance_id, 'integer'),
			$this->app->db->quote(true, 'boolean'));

		$post_count = SwatDB::queryOne($this->app->db, $sql, 'integer');

		$this->pager = new SwatPagination();
		$this->pager->display_parts ^= SwatPagination::POSITION;
		$this->pager->total_records = $post_count;
		$this->pager->page_size = self::MAX_POSTS;
		$this->pager->setCurrentPage($this->current_page);
		$this->pager->link = $path.'page%s';

		$this->pager->display();

		echo '<div class="results-message">';
		echo $this->pager->getResultsMessage(
			Blorg::_('post'), Blorg::_('posts'));

		echo '</div>';

		echo '<div class="archive-link">';
		$anchor_tag = new SwatHtmlTag('a');
		$anchor_tag->href = $path.'archive';
		$anchor_tag->setContent(Blorg::_('Archive'));
		$anchor_tag->display();
		echo '</div>';

		echo '</div>';
	}

	// }}}

	// finalize phase
	// {{{ public function finalize()

	public function finalize()
	{
		parent::finalize();
		$this->layout->addHtmlHeadEntry(new SwatStyleSheetHtmlHeadEntry(
			'packages/blorg/styles/blorg-front-page.css', Blorg::PACKAGE_ID));

		$this->layout->addHtmlHeadEntrySet(
			$this->pager->getHtmlHeadEntrySet());
	}

	// }}}
}

?>
