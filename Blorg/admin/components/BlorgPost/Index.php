<?php

require_once 'Admin/pages/AdminIndex.php';
require_once 'SwatDB/SwatDB.php';
require_once 'Swat/SwatTableStore.php';
require_once 'Swat/SwatDetailsStore.php';
require_once 'Blorg/dataobjects/BlorgPostWrapper.php';

/**
 * Index page for Posts
 *
 * @package   Blörg
 * @copyright 2008 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class BlorgBlorgPostIndex extends AdminIndex
{
	// {{{ protected properties

	protected $ui_xml = 'Blorg/admin/components/BlorgPost/index.xml';

	// }}}

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();

		$this->ui->loadFromXML($this->ui_xml);
	}

	// }}}

	// process phase
	// {{{ protected function processActions()

	public function processActions(SwatTableView $view, SwatActions $actions)
	{
		$num = count($view->getSelection());
		$message = null;

		switch ($actions->selected->id) {
		case 'delete':
			$this->app->replacePage('BlorgPost/Delete');
			$this->app->getPage()->setItems($view->getSelection());
			break;
		}

		if ($message !== null)
			$this->app->messages->add($message);
	}

	// }}}

	// build phase
	// {{{ protected function getTableModel()

	protected function getTableModel(SwatView $view)
	{
		$sql = 'select id, title, shortname, post_date, enabled, bodytext
			from BlorgPost order by post_date desc, title';

		$posts = SwatDB::query($this->app->db, $sql, 'BlorgPostWrapper');

		$store = new SwatTableStore();
		foreach ($posts as $post) {
			$ds = new SwatDetailsStore($post);
			$ds->title = $post->getTitle();
			$store->add($ds);
		}

		return $store;
	}

	// }}}
}

?>
