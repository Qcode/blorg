<?php

require_once 'SwatDB/SwatDBDataObject.php';
require_once 'Swat/SwatString.php';
require_once 'Blorg/dataobjects/BlorgAuthor.php';
require_once 'Blorg/dataobjects/BlorgPost.php';
require_once 'Blorg/BlorgCommentParser.php';

/**
 * A comment on a Blörg Post
 *
 * @package   Blörg
 * @copyright 2008 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class BlorgComment extends SwatDBDataObject
{
	// {{{ constants

	const STATUS_PENDING     = 0;
	const STATUS_PUBLISHED   = 1;
	const STATUS_UNPUBLISHED = 2;

	// }}}
	// {{{ public properties

	/**
	 * Unique Identifier
	 *
	 * @var integer
	 */
	public $id;

	/**
	 * Fullname of person commenting
	 *
	 * @var string
	 */
	public $fullname;

	/**
	 * Link to display with the comment
	 *
	 * @var string
	 */
	public $link;

	/**
	 * Email address of the person commenting
	 *
	 * @var string
	 */
	public $email;

	/**
	 * The body of this comment
	 *
	 * @var string
	 */
	public $bodytext;

	/**
	 * Visibility status
	 *
	 * Set using class contstants:
	 * STATUS_PENDING - waiting on moderation
	 * STATUS_PUBLISHED - comment published on site
	 * STATUS_UNPUBLISHED - not shown on the site
	 *
	 * @var integer
	 */
	public $status;

	/**
	 * Whether or not this comment is spam
	 *
	 * @var boolean
	 */
	public $spam = false;

	/**
	 * IP Address of the person commenting
	 *
	 * @var string
	 */
	public $ip_address;

	/**
	 * User agent of the HTTP client used to comment
	 *
	 * @var string
	 */
	public $user_agent;

	/**
	 * Date this comment was created
	 *
	 * @var Date
	 */
	public $createdate;

	// }}}
	// {{{ public static function getStatusTitle()

	public static function getStatusTitle($status)
	{
		switch ($status) {
		case self::STATUS_PENDING :
			$title = Blorg::_('Pending Approval');
			break;

		case self::STATUS_PUBLISHED :
			$title = Blorg::_('Shown on Site');
			break;

		case self::STATUS_UNPUBLISHED :
			$title = Blorg::_('Not Approved');
			break;

		default:
			$title = Blorg::_('Unknown Status');
			break;
		}

		return $title;
	}

	// }}}
	// {{{ public static function getBodytextXhtml()

	public static function getBodytextXhtml($bodytext)
	{
		$bodytext = BlorgCommentParser::parse($bodytext);
		$bodytext = str_replace("\r\n", "\n", $bodytext);
		$bodytext = str_replace("\r",   "\n", $bodytext);
		$bodytext = preg_replace('/[\x0a\s]*\n\n[\x0a\s]*/s', '</p><p>',
			$bodytext);

		$bodytext = preg_replace('/[\x0a\s]*\n[\x0a\s]*/s', '<br />',
			$bodytext);

		$bodytext = '<p>'.$bodytext.'</p>';

		return $bodytext;
	}

	// }}}
	// {{{ public function load()

	/**
	 * Loads this comment
	 *
	 * @param integer $id the database id of this comment.
	 * @param SiteInstance $instance optional. The instance to load the comment
	 *                                in. If the application does not use
	 *                                instances, this should be null. If
	 *                                unspecified, the instance is not checked.
	 *
	 * @return boolean true if this comment and false if it was not.
	 */
	public function load($id, SiteInstance $instance = null)
	{
		$this->checkDB();

		$loaded = false;
		$row = null;
		if ($this->table !== null && $this->id_field !== null) {
			$id_field = new SwatDBField($this->id_field, 'integer');

			$sql = sprintf('select %1$s.* from %1$s
				inner join BlorgPost on %1$s.post = BlorgPost.id
				where %1$s.%2$s = %3$s',
				$this->table,
				$id_field->name,
				$this->db->quote($id, $id_field->type));

			$instance_id  = ($instance === null) ? null : $instance->id;
			if ($instance_id !== null) {
				$sql.=sprintf(' and instance %s %s',
					SwatDB::equalityOperator($instance_id),
					$this->db->quote($instance_id, 'integer'));
			}

			$rs = SwatDB::query($this->db, $sql, null);
			$row = $rs->fetchRow(MDB2_FETCHMODE_ASSOC);
		}

		if ($row !== null) {
			$this->initFromRow($row);
			$this->generatePropertyHashes();
			$loaded = true;
		}

		return $loaded;
	}

	// }}}
	// {{{ public function setDatabase()

	/**
	 * Sets the database driver for this data-object
	 *
	 * The database is automatically set for all recordable sub-objects of this
	 * data-object.
	 *
	 * Overridden in BlorgComment to prevent infinite recursion between posts
	 * and comments.
	 *
	 * @param MDB2_Driver_Common $db the database driver to use for this
	 *                                data-object.
	 */
	public function setDatabase(MDB2_Driver_Common $db)
	{
		$this->db = $db;
		$serializable_sub_data_objects = $this->getSerializableSubDataObjects();
		foreach ($serializable_sub_data_objects as $key) {
			if ($this->hasSubDataObject($key) && $key !== 'post') {
				$object = $this->getSubDataObject($key);
				if ($object instanceof SwatDBRecordable) {
					$object->setDatabase($db);
				}
			}
		}
	}

	// }}}
	// {{{ protected function init()

	protected function init()
	{
		$this->registerDateProperty('createdate');

		$this->registerInternalProperty('post',
			SwatDBClassMap::get('BlorgPost'));

		$this->registerInternalProperty('author',
			SwatDBClassMap::get('BlorgAuthor'));

		$this->table = 'BlorgComment';
		$this->id_field = 'integer:id';
	}

	// }}}
	// {{{ protected function getSerializableSubDataObjects()

	protected function getSerializableSubDataObjects()
	{
		return array(
			'post',
			'author',
		);
	}

	// }}}
}

?>
