<?php

require_once 'SwatDB/SwatDBClassMap.php';
require_once 'Site/pages/SitePage.php';
require_once 'Site/exceptions/SiteNotFoundException.php';

/**
 * Displays an index of all months with posts in a given year
 *
 * @package   Blörg
 * @copyright 2008 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class BlorgYearArchivePage extends SitePage
{
	// {{{ protected properties

	/**
	 * Array of integers containing the months of the specified year that
	 * contain posts
	 *
	 * @var array
	 */
	protected $months = array();

	// }}}
	// {{{ public function __construct()

	/**
	 * Creates a new post page
	 *
	 * @param SiteWebApplication $app the application.
	 * @param SiteLayout $layout
	 * @param integer $year
	 */
	public function __construct(SiteWebApplication $app, SiteLayout $layout,
		$year)
	{
		parent::__construct($app, $layout);
		$this->initMonths($year);
	}

	// }}}
	// {{{ public function build()

	public function build()
	{
		ob_start();
		foreach ($this->months as $month) {
			echo $month;
		}
		$this->layout->data->content = ob_get_clean();
	}

	// }}}
	// {{{ protected function initMonths()

	protected function initMonths($year)
	{
		// Date parsed from URL is in locale time.
		$date = new SwatDate();
		$date->setTZ($this->app->default_time_zone);
		$date->setYear($year);
		$date->setMonth(1);
		$date->setDay(1);
		$date->setHour(0);
		$date->setMinute(0);
		$date->setSecond(0);

		$instance_id = $this->app->instance->getId();

		$sql = sprintf('select post_date from BlorgPost
			where date_trunc(\'year\', convertTZ(createdate, %s)) =
				date_trunc(\'year\', timestamp %s) and
				instance %s %s
				and enabled = true
			order by post_date desc',
			$this->app->db->quote($date->tz->getId(), 'text'),
			$this->app->db->quote($date->getDate(), 'date'),
			SwatDB::equalityOperator($instance_id),
			$this->app->db->quote($instance_id, 'integer'));

		$rs = SwatDB::query($this->app->db, $sql, null);
		while ($date = $rs->fetchOne()) {
			$date = new SwatDate($date);
			$this->months[] = $date->getMonth();
		}
	}

	// }}}
}

?>
