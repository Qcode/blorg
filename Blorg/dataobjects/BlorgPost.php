<?php

require_once 'Swat/SwatDate.php';
require_once 'SwatDB/SwatDBDataObject.php';
require_once 'Blorg/dataobjects/BlorgCommentWrapper.php';
require_once 'Blorg/dataobjects/BlorgTagWrapper.php';
require_once 'Blorg/dataobjects/BlorgFileWrapper.php';
require_once 'Blorg/dataobjects/BlorgAuthor.php';

/**
 * A Blörg Post
 *
 * @package   Blörg
 * @copyright 2008 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class BlorgPost extends SwatDBDataObject
{
	// {{{ class constants

	/**
	 * New comments are allowed, and are automatically show on the site as long
	 * as they are not detected as spam.
	 */
	const COMMENT_STATUS_OPEN      = 0;

	/**
	 * New comments are allowed, but must be approved by an admin user before
	 * being shown.
	 */
	const COMMENT_STATUS_MODERATED = 1;

	/**
	 * No new comments are allowed, but exisiting comments are shown.
	 */
	const COMMENT_STATUS_LOCKED    = 2;

	/**
	 * No new comments are allowed, and existing comments are no longer shown.
	 */
	const COMMENT_STATUS_CLOSED    = 3;

	// }}}
	// {{{ public properties

	/**
	 * Unique identifier
	 *
	 * @var integer
	 */
	public $id;

	/**
	 * Post Title
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Post Shortname
	 *
	 * Auto-magically generated from title if it exists, otherwise generated
	 * from the start of the bodytext.  Can also be manually set.
	 *
	 * @var string
	 */
	public $shortname;

	/**
	 * Main Body of the Blorg Post
	 *
	 * @var string
	 */
	public $bodytext;

	/**
	 * Any Extended Body of the Blorg Post
	 *
	 * This is only displayed on the full post page.
	 *
	 * @var string
	 */
	public $extended_bodytext;

	/**
	 * Date the post was created.
	 *
	 * @var Date
	 */
	public $createdate;

	/**
	 * Last Modified Date of the post.
	 *
	 * @var Date
	 */
	public $modified_date;

	/**
	 * Date the post was published - used for display and ordering by date.
	 *
	 * @var Date
	 */
	public $publish_date;

	/**
	 * The status of comments on this post.
	 *
	 * @var integer
	 */
	public $comment_status;

	/**
	 * Whether or not the post is viewable on the site.
	 *
	 * @var boolean
	 */
	public $enabled;

	// }}}
	// {{{ public function loadByDateAndShortname()

	/**
	 * Loads a post by a date and the post's shortname
	 *
	 * @param SwatDate $date the date the <i>publish_date<i> of the post. Only
	 *                        the year and month fields are used for comparison.
	 * @param string $shortname the shortname of the post to load.
	 * @param SiteInstance $instance optional. The instance to load the post in.
	 *                               If the site does not use instances, this
	 *                               should be null.
	 *
	 * @return boolean true if this post was loaded from the given publish_date
	 *                 and shortname and false if it was not.
	 */
	public function loadByDateAndShortname(SwatDate $date, $shortname,
		SiteInstance $instance = null)
	{

		$this->checkDB();

		$loaded = false;
		$row = null;
		if ($this->table !== null) {
			$instance_id  = ($instance === null) ? null : $instance->id;

			$sql = sprintf('select * from %s
				where shortname = %s and
					date_trunc(\'month\',
						convertTZ(publish_date, %s)) =
					date_trunc(\'month\', timestamp %s) and
					instance %s %s',
				$this->table,
				$this->db->quote($shortname, 'text'),
				$this->db->quote($date->tz->getId(), 'text'),
				$this->db->quote($date->getDate(), 'date'),
				SwatDB::equalityOperator($instance_id),
				$this->db->quote($instance_id, 'integer'));

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
	// {{{ public static function getCommentStatusTitle()

	public static function getCommentStatusTitle($status)
	{
		switch ($status) {
		case self::COMMENT_STATUS_OPEN :
			$title = Blorg::_('Open');
			break;

		case self::COMMENT_STATUS_LOCKED :
			$title = Blorg::_('Locked');
			break;

		case self::COMMENT_STATUS_MODERATED :
			$title = Blorg::_('Moderated');
			break;

		case self::COMMENT_STATUS_CLOSED :
			$title = Blorg::_('Closed');
			break;

		default:
			$title = Blorg::_('Unknown Comment Status');
			break;
		}

		return $title;
	}

	// }}}
	// {{{ public static function getCommentStatuses()

	public static function getCommentStatuses()
	{
		return array(
			self::COMMENT_STATUS_OPEN =>
				self::getCommentStatusTitle(self::COMMENT_STATUS_OPEN),
			self::COMMENT_STATUS_MODERATED =>
				self::getCommentStatusTitle(self::COMMENT_STATUS_MODERATED),
			self::COMMENT_STATUS_LOCKED =>
				self::getCommentStatusTitle(self::COMMENT_STATUS_LOCKED),
			self::COMMENT_STATUS_CLOSED =>
				self::getCommentStatusTitle(self::COMMENT_STATUS_CLOSED),
		);
	}

	// }}}
	// {{{ public function getTitle()

	public function getTitle()
	{
		if ($this->title == '')
			return SwatString::ellipsizeRight(SwatString::condense(
				SwatString::toXHTML($this->bodytext)), 50, Blorg::_(' …'));
		else
			return $this->title;
	}

	// }}}
	// {{{ public function getCommentCount()

	public function getCommentCount()
	{
		if ($this->hasInternalValue('comment_count') &&
			$this->getInternalValue('comment_count') !== null) {
			$comment_count = $this->getInternalValue('comment_count');
		} else {
			$this->checkDB();

			$sql = sprintf('select comment_count
				from BlorgPostCommentCountView
				where post = %s',
				$this->db->quote($this->id, 'integer'));

			$comment_count = SwatDB::queryOne($this->db, $sql);
		}

		return $comment_count;
	}

	// }}}
	// {{{ public function getVisibleComments()

	public function getVisibleComments($limit = null, $offset = 0)
	{
		$this->checkDB();

		$sql = sprintf('select * from BlorgComment
			where post = %s and status = %s and spam = %s
			order by createdate',
			$this->db->quote($this->id, 'integer'),
			$this->db->quote(BlorgComment::STATUS_PUBLISHED, 'integer'),
			$this->db->quote(false, 'boolean'));

		$wrapper = SwatDBClassMap::get('BlorgCommentWrapper');

		if ($limit !== null) {
			$this->db->setLimit($limit, $offset);
		}

		$comments = SwatDB::query($this->db, $sql, $wrapper);

		// set post on comment objects so they don't have to query it again
		foreach ($comments as $comment) {
			$comment->post = $this;
		}

		return $comments;
	}

	// }}}
	// {{{ public function getVisibleCommentCount()

	public function getVisibleCommentCount()
	{
		if ($this->hasInternalValue('visible_comment_count') &&
			$this->getInternalValue('visible_comment_count') !== null) {
			$comment_count = $this->getInternalValue('visible_comment_count');
		} else {
			$this->checkDB();

			$sql = sprintf('select visible_comment_count
				from BlorgPostVisibleCommentCountView
				where post = %s',
				$this->db->quote($this->id, 'integer'));

			$comment_count = SwatDB::queryOne($this->db, $sql);
		}

		return $comment_count;
	}

	// }}}
	// {{{ public function hasVisibleCommentStatus()

	public function hasVisibleCommentStatus()
	{
		return ($this->comment_status == self::COMMENT_STATUS_OPEN ||
			$this->comment_status == self::COMMENT_STATUS_MODERATED ||
			($this->comment_status == self::COMMENT_STATUS_LOCKED &&
			$this->getVisibleCommentCount() > 0));
	}

	// }}}
	// {{{ public function getVisibleFiles()

	/**
	 * Gets visible files for this post
	 *
	 * @return BlorgFileWrapper
	 */
	public function getVisibleFiles()
	{
		$sql = 'select BlorgFile.*
			from BlorgFile
			where BlorgFile.post = %s and BlorgFile.visible = %s
			order by BlorgFile.createdate';

		$sql = sprintf($sql,
			$this->db->quote($this->id, 'integer'),
			$this->db->quote(true, 'boolean'));

		return SwatDB::query($this->db, $sql,
			SwatDBClassMap::get('BlorgFileWrapper'));
	}

	// }}}
	// {{{ public function addTagsByShortname()

	public function addTagsByShortname(array $tag_names,
		SiteInstance $instance, $clear_existing_tags = false)
	{
		$this->checkDB();

		$instance_id = ($instance === null) ? null : $instance->id;

		$sql = sprintf('delete from BlorgPostTagBinding where post = %s',
			$this->db->quote($this->id, 'integer'));

		if (!$clear_existing_tags)
			$sql.= sprintf(' and tag in (select id from
				BlorgTag where shortname in (%s) and instance %s %s)',
				$this->db->datatype->implodeArray($tag_names, 'text'),
				SwatDB::equalityOperator($instance_id),
				$this->db->quote($instance_id, 'integer'));

		SwatDB::exec($this->db, $sql);

		$sql = sprintf('insert into BlorgPostTagBinding
			(post, tag) select %1$s, id from BlorgTag
			where shortname in (%2$s) and BlorgTag.instance %3$s %4$s',
			$this->db->quote($this->id, 'integer'),
			$this->db->datatype->implodeArray($tag_names, 'text'),
			SwatDB::equalityOperator($instance_id),
			$this->db->quote($instance_id, 'integer'));

		SwatDB::exec($this->db, $sql);
	}

	// }}}
	// {{{ public function load()

	/**
	 * Loads this post
	 *
	 * @param integer $id the database id of this post.
	 * @param SiteInstance $instance optional. The instance to load the post in.
	 *                                If the application does not use instances,
	 *                                this should be null. If upsecified, the
	 *                                instance is not checked.
	 *
	 * @return boolean true if this post was loaded and false if it was not.
	 */
	public function load($id, SiteInstance $instance = null)
	{
		$this->checkDB();

		$loaded = false;
		$row = null;
		if ($this->table !== null && $this->id_field !== null) {
			$id_field = new SwatDBField($this->id_field, 'integer');

			$sql = sprintf('select * from %s where %s = %s',
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
	// {{{ protected function init()

	protected function init()
	{
		$this->registerDateProperty('createdate');
		$this->registerDateProperty('modified_date');
		$this->registerDateProperty('publish_date');

		$this->registerInternalProperty('author',
			SwatDBClassMap::get('BlorgAuthor'));

		$this->registerInternalProperty('instance',
			SwatDBClassMap::get('SiteInstance'));

		$this->registerInternalProperty('visible_comment_count');
		$this->registerInternalProperty('comment_count');

		$this->table = 'BlorgPost';
		$this->id_field = 'integer:id';
	}

	// }}}

	// loader methods
	// {{{ protected function loadComments()

	/**
	 * Loads comments for this post, this never includes spam
	 *
	 * @return BlorgCommentWrapper
	 */
	protected function loadComments()
	{
		$sql = 'select BlorgComment.*
			from BlorgComment
			where BlorgComment.post = %s and spam = %s
			order by createdate';

		$sql = sprintf($sql,
			$this->db->quote($this->id, 'integer'),
			$this->db->quote(false, 'boolean'));

		return SwatDB::query($this->db, $sql,
			SwatDBClassMap::get('BlorgCommentWrapper'));
	}

	// }}}
	// {{{ protected function loadTags()

	/**
	 * Loads tags for this post
	 *
	 * @return BlorgTagWrapper
	 */
	protected function loadTags()
	{
		$sql = 'select BlorgTag.*
			from BlorgTag
				inner join BlorgPostTagBinding on BlorgTag.id =
					BlorgPostTagBinding.tag
			where BlorgPostTagBinding.post = %s
			order by createdate';

		$sql = sprintf($sql, $this->db->quote($this->id, 'integer'));

		return SwatDB::query($this->db, $sql,
			SwatDBClassMap::get('BlorgTagWrapper'));
	}

	// }}}
	// {{{ protected function loadFiles()

	/**
	 * Loads files for this post
	 *
	 * @return BlorgFileWrapper
	 */
	protected function loadFiles()
	{
		$sql = 'select BlorgFile.*
			from BlorgFile
			where BlorgFile.post = %s
			order by BlorgFile.createdate';

		$sql = sprintf($sql, $this->db->quote($this->id, 'integer'));

		return SwatDB::query($this->db, $sql,
			SwatDBClassMap::get('BlorgFileWrapper'));
	}

	// }}}

	// saver methods
	// {{{ protected function saveComments()

	/**
	 * Automatically saves comments on this post when this post is saved
	 */
	protected function saveComments()
	{
		foreach ($this->comments as $comment)
			$comment->post = $this;

		$this->comments->setDatabase($this->db);
		$this->comments->save();
	}

	// }}}
}

?>
