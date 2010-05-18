<?php

require_once 'Services/Twitter.php';
require_once 'Swat/SwatDate.php';
require_once 'Site/gadgets/SiteGadget.php';

/**
 * Displays recent twitter updates
 *
 * Available settings are:
 *
 * - <kbd>string  username</kbd>    - the Twitter username for which to display
 *                                    updates.
 * - <kbd>integer max_updates</kbd> - the number of updates to display.
 *
 * @package   Blörg
 * @copyright 2009-2010 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class BlorgTwitterGadget extends SiteGadget
{
	// {{{ constants

	/**
	 * The amount of time in minutes we wait before we update the cache
	 *
	 * @var integer the amount of time in minutes
	 */
	const UPDATE_THRESHOLD = 5;

	/**
	 * The amount of time in minutes we wait before we try updating the cache
	 * again if the cache failed to update
	 *
	 * @var integer the amount of time in minutes
	 */
	const UPDATE_RETRY_THRESHOLD = 2;

	/**
	 * The name of the cache that stores the timeline's xml
	 *
	 * @var string the name of the cache
	 */
	const CACHE_NAME = 'timeline';

	/**
	 * The endpoint for all links display with this gadget
	 *
	 * @var string the endpoint for all links
	 */
	const URI_ENDPOINT = 'http://twitter.com';

	// }}}
	// {{{ protected properties

	/**
	 * An object used to access the Twitter API
	 *
	 * @var Services_Twitter the API object
	 */
	protected $twitter;

	/**
	 * A SimpleXMLElement object that contains the current twitter timeline
	 *
	 * @var SimpleXMLElement the current user timeline
	 */
	protected $timeline;

	/**
	 * The current date and time
	 *
	 * @var SwatDate the current date and time
	 */
	protected $now;

	// }}}
	// {{{ public function init()

	public function init()
	{
		$request = new HTTP_Request2();
		$request->setConfig(array(
			'connect_timeout' => 1,
			'timeout'         => 3,
		));

		$this->twitter = new Services_Twitter(null, null,
			array('format' => Services_Twitter::OUTPUT_XML));

		$this->twitter->setRequest($request);

		$this->now = new SwatDate();
		$this->now->toUTC();

		$this->initTimeline();
	}

	// }}}
	// {{{ public function display()

	public function display()
	{
		$this->displayTitle();
		if ($this->hasTimeline()) {
			$this->displayContent();
		} else {
			$this->displayUnavailable();
		}
		$this->displayFooter();
	}

	// }}}
	// {{{ protected function displayContent()

	protected function displayContent()
	{
		$span_tag = new SwatHtmlTag('span');
		$a_tag = new SwatHtmlTag('a');

		echo '<ul>';

		for ($i = 0; $i < $this->getValue('max_updates') &&
			count($this->timeline->status) > $i; $i++) {

			$status = $this->timeline->status[$i];
			$create_date = new SwatDate(strtotime($status->created_at),
				DATE_FORMAT_UNIXTIME);

			echo '<li>';
			$a_tag->href = sprintf('%s/%s/status/%s', self::URI_ENDPOINT,
				$this->getValue('username'), $status->id);

			$a_tag->setContent($status->text);
			$span_tag->setContent(sprintf('(around %s ago)',
				$create_date->getHumanReadableDateDiff()));

			$a_tag->display();
			echo ' ';
			$span_tag->display();
			echo '</li>';
		}

		echo '</ul>';
	}

	// }}}
	// {{{ protected function displayUnavailable()

	protected function displayUnavailable()
	{
		echo Blorg::_('Twitter updates are currently unavailable.');
	}

	// }}}
	// {{{ protected function displayFooter()

	protected function displayFooter()
	{
		if ($this->hasTimeline()) {
			$real_name = $this->timeline->status[0]->user->name;
		} else {
			$real_name = $this->getValue('username');
		}

		$footer = new SwatHtmlTag('div');
		$footer->class = 'site-gadget-footer';

		$a_tag = new SwatHtmlTag('a');
		$a_tag->href = self::URI_ENDPOINT.'/'.$this->getValue('username');
		$a_tag->setContent($real_name);

		$footer->setContent(sprintf(Blorg::_('Follow %s on Twitter'), $a_tag),
			'text/xml');

		$footer->display();
	}

	// }}}
	// {{{ protected function hasTimeline()

	protected function hasTimeline()
	{
		return ($this->timeline !== null);
	}

	// }}}
	// {{{ protected function initTimeline()

	/**
	 * Initializes the user timeline
	 *
	 * First checks if there is an unexpired timeline in the cache. If no
	 * unexpired timeline is found query Twitter for a new timeline. Finally
	 * if Twitter is unable to provide a new timeline return either the
	 * expired timeline from the cache or null if an expired update does not
	 * exist.
	 */
	protected function initTimeline()
	{
		$timeline = null;
		$last_update = null;

		if ($this->hasCache(self::CACHE_NAME)) {
			$last_update = $this->getCacheLastUpdateDate(self::CACHE_NAME);
			$last_update->addMinutes(self::UPDATE_THRESHOLD);
		}

		// update the cache
		if ($last_update === null || $this->now->after($last_update)) {
			try {
				$params = array('id' => $this->getValue('username'));
				$timeline = $this->twitter->statuses->user_timeline($params);
				$this->updateCacheValue(self::CACHE_NAME, $timeline->asXML());
			} catch (Services_Twitter_Exception $e) {
				$regexp = '/^Request timed out after [0-9]+ second\(s\)$/u';
				if (preg_match($regexp, $e->getMessage()) === 1) {
					// on timeout, update the cache timeout so we rate-limit
					// retries
					if ($this->hasCache(self::CACHE_NAME)) {
						$date = clone $this->now;
						$date->addMinutes(self::UPDATE_RETRY_THRESHOLD -
							self::UPDATE_THRESHOLD);

						$xml_string = $this->getCacheValue(self::CACHE_NAME);
						$timeline = simplexml_load_string($xml_string);
						$this->updateCacheValue(
							self::CACHE_NAME, $xml_string, $date);
					}
				} else {
					throw $e;
				}
			}
		} else {
			$xml_string = $this->getCacheValue(self::CACHE_NAME);
			$timeline = simplexml_load_string($xml_string);
		}

		$this->timeline = $timeline;
	}

	// }}}
	// {{{ protected function define()

	protected function define()
	{
		$this->defineDefaultTitle(Blorg::_('Twitter Updates'));
		$this->defineSetting('username', Blorg::_('User Name'), 'string');
		$this->defineSetting('max_updates',
			Blorg::_('Number of updates to Display'), 'integer', 5);

		$this->defineDescription(Blorg::_('Lists recent updates from Twitter'));
	}

	// }}}
}

?>
