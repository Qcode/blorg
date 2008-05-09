<?php

require_once 'Site/pages/SiteXMLRPCServer.php';
require_once 'Site/layouts/SiteXMLRPCServerLayout.php';
require_once 'SwatDB/SwatDB.php';
require_once 'Blorg/dataobjects/BlorgFile.php';

/**
 * Performs actions on files via AJAX
 *
 * @package   Blörg
 * @copyright 2008 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class BlorgPostFileAjaxServer extends SiteXMLRPCServer
{
	// {{{ public function attach()

	/**
	 * Marks a file as attached
	 *
	 * @param integer $file_id the id of the file to mark as attached.
	 *
	 * @return boolean true.
	 */
	public function attach($file_id)
	{
		$sql = sprintf('update BlorgFile set show = %s where id = %s',
			$this->app->db->quote(true, 'boolean'),
			$this->app->db->quote($file_id, 'integer'));

		SwatDB::exec($this->app->db, $sql);

		return true;
	}

	// }}}
	// {{{ public function detach()

	/**
	 * Marks a file as not attached
	 *
	 * @param integer $file_id the id of the file to mark as not attached.
	 *
	 * @return boolean true.
	 */
	public function detach($file_id)
	{
		$sql = sprintf('update BlorgFile set show = %s where id = %s',
			$this->app->db->quote(false, 'boolean'),
			$this->app->db->quote($file_id, 'integer'));

		SwatDB::exec($this->app->db, $sql);

		return true;
	}

	// }}}
	// {{{ public function delete()

	/**
	 * Deletes a file
	 *
	 * @param integer $file_id the id of the file to delete.
	 *
	 * @return boolean true.
	 */
	public function delete($file_id)
	{
		$class_name = SwatDBClassMap::get('BlorgFile');
		$file = new $class_name();
		$file->setDatabase($this->app->db);
		$file->setFileBase('../');
		if ($file->load($id)) {
			$file->delete();
		}

		return true;
	}

	// }}}
}

?>
