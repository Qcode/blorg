<?php

require_once 'SwatDB/SwatDBClassMap.php';
require_once 'Site/pages/SitePage.php';
require_once 'Site/exceptions/SiteNotFoundException.php';
require_once 'Blorg/BlorgPostFullView.php';
require_once 'Blorg/dataobjects/BlorgPost.php';

/**
 * Post page for Blörg
 *
 * Loads and displays a post and handles adding replies to a post.
 *
 * @package   Blörg
 * @copyright 2008 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class BlorgPostPage extends SitePage
{
	// {{{ protected properties

	/**
	 * @var BlorgPost
	 */
	protected $post;

	// }}}
	// {{{ public function __construct()

	/**
	 * Creates a new post page
	 *
	 * @param SiteWebApplication $app the application.
	 * @param SiteLayout $layout
	 * @param integer $year
	 * @param string $month_name
	 * @param string $shortname
	 */
	public function __construct(SiteWebApplication $app, SiteLayout $layout,
		$year, $month_name, $shortname)
	{
		parent::__construct($app, $layout);
		$this->initPost($year, $month_name, $shortname);
	}

	// }}}
	// {{{ public function build()

	public function build()
	{
		$this->buildNavBar();

		ob_start();
		$this->displayPost();
		$this->layout->data->content = ob_get_clean();
	}

	// }}}
	// {{{ protected function buildNavBar()

	protected function buildNavBar()
	{
		$base = 'news/'; // TODO

		$link = $base;
		$this->layout->navbar->addEntry(new SwatNavBarEntry('News', $link));

		$link = $base.'archive';
		$this->layout->navbar->addEntry(new SwatNavBarEntry('Archive', $link));

		$year = $this->post->post_date->getYear();
		$link.= '/'.$year;
		$this->layout->navbar->addEntry(new SwatNavBarEntry($year, $link));

		$month_names = array(
			1  => 'january',
			2  => 'february',
			3  => 'march',
			4  => 'april',
			5  => 'may',
			6  => 'june',
			7  => 'july',
			8  => 'august',
			9  => 'september',
			10 => 'october',
			11 => 'november',
			12 => 'december',
		);
		$month_title = $this->post->post_date->getMonthName();
		$month_name = $month_names[$this->post->post_date->getMonth()];
		$link.= '/'.$month_name;
		$this->layout->navbar->addEntry(
			new SwatNavBarEntry($month_title, $link));

		$link.= '/'.$this->post->shortname;
		$this->layout->navbar->addEntry(
			new SwatNavBarEntry($this->post->title, $link));
	}

	// }}}
	// {{{ protected function displayPost()

	protected function displayPost()
	{
		$view = new BlorgPostFullView($this->app, $this->post);
		$view->display();
	}

	// }}}
	// {{{ protected function initPost()

	protected function initPost($year, $month_name, $shortname)
	{
		$months_by_name = array(
			'january'   => 1,
			'february'  => 2,
			'march'     => 3,
			'april'     => 4,
			'may'       => 5,
			'june'      => 6,
			'july'      => 7,
			'august'    => 8,
			'september' => 9,
			'october'   => 10,
			'november'  => 11,
			'december'  => 12,
		);

		if (!array_key_exists($month_name, $months_by_name)) {
			throw new SiteNotFoundException('Post not found.');
		}

		// Date parsed from URL is in locale time.
		$date = new SwatDate();
		$date->setTZ($this->app->default_time_zone);
		$date->setYear($year);
		$date->setMonth($months_by_name[$month_name]);
		$date->setDay(1);
		$date->setHour(0);
		$date->setMinute(0);
		$date->setSecond(0);

		$class_name = SwatDBClassMap::get('BlorgPost');
		$this->post = new $class_name();
		$this->post->setDatabase($this->app->db);
		if (!$this->post->loadByDateAndShortname($date, $shortname,
			$this->app->instance->getInstance())) {
			throw new SiteNotFoundException('Post not found.');
		}

		if (!$this->post->enabled) {
			throw new SiteNotFoundException('Post not found.');
		}
	}

	// }}}
}

?>
