<?php

require_once 'Admin/exceptions/AdminNotFoundException.php';
require_once 'Admin/pages/AdminDBEdit.php';
require_once 'Site/exceptions/SiteInvalidImageException.php';
require_once 'Blorg/BlorgWeblogsDotComPinger.php';
require_once 'Blorg/dataobjects/BlorgPost.php';
require_once 'Blorg/dataobjects/BlorgFile.php';
require_once 'Blorg/dataobjects/BlorgFileImage.php';

/**
 * Page for editing Posts
 *
 * @package   Blörg
 * @copyright 2008 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class BlorgPostEdit extends AdminDBEdit
{
	// {{{ protected properties

	/**
	 * @var BlorgPost
	 */
	protected $post;

	protected $ui_xml = 'Blorg/admin/components/Post/edit.xml';

	// }}}

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();

		$this->ui->loadFromXML($this->ui_xml);
		$this->initPost();
		$this->initReplyStatuses();

		if ($this->id === null) {
			$this->ui->getWidget('shortname_field')->visible = false;
			$this->ui->getWidget('publish_date_field')->visible = false;
		} else {
			$publish_date = $this->ui->getWidget('publish_date');
			$publish_date->display_time_zone = $this->app->default_time_zone;
			$publish_date->display_parts  = SwatDateEntry::YEAR |
				SwatDateEntry::MONTH | SwatDateEntry::DAY |
				SwatDateEntry::CALENDAR | SwatDateEntry::TIME;
		}

		$instance_id = $this->app->getInstanceId();
		$tag_where_clause = sprintf('instance %s %s',
			SwatDB::equalityOperator($instance_id),
			$this->app->db->quote($instance_id, 'integer'));

		$tag_options = SwatDB::getOptionArray($this->app->db, 'BlorgTag',
			'title', 'id', 'title', $tag_where_clause);

		if (count($tag_options)) {
			$tag_list = $this->ui->getWidget('tags');
			$tag_list->addOptionsByArray($tag_options);
		} else
			$this->ui->getWidget('tag_field')->visible = false;

		$form = $this->ui->getWidget('edit_form');
		if ($this->id === null && $form->getHiddenField('unique_id') === null)
			$form->addHiddenField('unique_id', uniqid());

		$this->ui->getWidget('file_replicator')->replicators =
			$this->getFileReplicators();
	}

	// }}}
	// {{{ protected function initPost()

	protected function initPost()
	{
		$this->post = new BlorgPost();
		$this->post->setDatabase($this->app->db);

		if ($this->id !== null && !$this->post->load($this->id))
			throw new AdminNotFoundException(
				sprintf(Blorg::_('Post with id ‘%s’ not found.'), $this->id));
	}

	// }}}
	// {{{ protected function initReplyStatuses()

	protected function initReplyStatuses()
	{
		$status = $this->ui->getWidget('reply_status');

		// open
		$option = new SwatOption(BlorgPost::REPLY_STATUS_OPEN,
			BlorgPost::getReplyStatusTitle(BlorgPost::REPLY_STATUS_OPEN));

		$status->addOption($option);
		$status->addContextNote($option, Blorg::_(
			'Replies can be added by anyone and are immediately visible on '.
			'this post.'));

		// moderated
		$option = new SwatOption(BlorgPost::REPLY_STATUS_MODERATED,
			BlorgPost::getReplyStatusTitle(BlorgPost::REPLY_STATUS_MODERATED));

		$status->addOption($option);
		$status->addContextNote($option, Blorg::_(
			'Replies can be added by anyone but must be approved by a site '.
			'author before being visible on this post.'));

		// locked
		$option = new SwatOption(BlorgPost::REPLY_STATUS_LOCKED,
			BlorgPost::getReplyStatusTitle(BlorgPost::REPLY_STATUS_LOCKED));

		$status->addOption($option);
		$status->addContextNote($option, Blorg::_(
			'Replies can only be added by an author. Existing replies are '.
			'still visible on this post.'));

		// closed
		$option = new SwatOption(BlorgPost::REPLY_STATUS_CLOSED,
			BlorgPost::getReplyStatusTitle(BlorgPost::REPLY_STATUS_CLOSED));

		$status->addOption($option);
		$status->addContextNote($option, Blorg::_(
			'Replies can only be added by an author. No replies are visible '.
			'on this post.'));

		if ($this->id === null) {
			// TODO: default status to config default
		}
	}

	// }}}
	// {{{ protected function getFileReplicators()

	protected function getFileReplicators()
	{
		// TODO:
//		$sql = 'select * from BlorgFile
//			where post %s %s and form_id %s %s';

		$replicators = array();
		foreach ($this->post->files as $file) {
			$replicators[$file->id] = null;
		}

		return $replicators;
	}

	// }}}

	// process phase
	// {{{ protected function processInternal()

	protected function processInternal()
	{
		$form = $this->ui->getWidget('edit_form');

//		$view = $this->ui->getWidget('upload_file_view');
//		$input_row = $view->getRow('input_row');
//		$count = $this->uploadFiles($input_row);

/*		if ($count > 0) {
			$message = new SwatMessage(
				Blorg::ngettext('One file has been attached to this post.',
					sprintf('%s files have been attached to this post',
						$count),
					$count));

			$this->ui->getWidget('message_display')->add($message);
		}
*/
		$upload_file_button = $this->ui->getWidget('upload_button');

		if ($upload_file_button->hasBeenClicked()) {
			$this->ui->getWidget('bodytext_field')->display_messages = false;
			$this->ui->getWidget('publish_date_field')->display_messages = false;
		} else {
			parent::processInternal();
		}
	}

	// }}}
	// {{{ protected function uploadFiles()

	protected function uploadFiles(SwatTableViewInputRow $input_row)
	{
		$form = $this->ui->getWidget('edit_form');
		$replicators = $input_row->getReplicators();
		$now = new SwatDate();
		$now->toUTC();
		$count = 0;

		foreach ($replicators as $replicator_id) {
			if (!$input_row->rowHasMessage($replicator_id)) {
				$file = $input_row->getWidget('file', $replicator_id);
				if (!$file->isUploaded())
					continue;

				$description = $input_row->getWidget('description',
					$replicator_id)->value;

				$show = $input_row->getWidget('show', $replicator_id)->value;

				$class_name = SwatDBClassMap::get('BlorgFile');
				$blorg_file = new $class_name();
				$blorg_file->setDatabase($this->app->db);
				$unique_id = $form->getHiddenField('unique_id');

				if ($this->id === null)
					$blorg_file->form_unique_id = $unique_id;
				else
					$blorg_file->post = $this->id;

				$blorg_file->description = $description;
				$blorg_file->show = $show;
				$blorg_file->filename = $file->getUniqueFileName('../files');
				$blorg_file->mime_type = $file->getMimeType();
				$blorg_file->filesize = $file->getSize();
				$blorg_file->createdate = $now;

				if (array_shift(explode('/',
					$blorg_file->mime_type)) == 'image') {
					$blorg_file->image = $this->createImage($file);
				}

				$blorg_file->save();

				$file->saveFile('../files', $blorg_file->filename);
				$count++;
			}
		}

		return $count;
	}

	// }}}
	// {{{ protected function createImage()

	protected function createImage(SwatFileEntry $file)
	{
		$class_name = SwatDBClassMap::get('BlorgFileImage');
		$image = new $class_name();
		$image->setDatabase($this->app->db);
		$image->setFileBase('../');

		try {
			$image->process($file->getTempFileName());
		} catch (SiteInvalidImageException $e) {
			$image = null;
		}

		return $image;
	}

	// }}}
	// {{{ protected function validate()

	protected function validate()
	{
		$shortname = $this->ui->getWidget('shortname')->value;

		if ($this->id === null && $shortname === null) {
			$title_value = strlen($this->ui->getWidget('title')->value) ?
				$this->ui->getWidget('title')->value :
				$this->ui->getWidget('bodytext')->value;

			$shortname = $this->generateShortname($title_value);

			$this->ui->getWidget('shortname')->value = $shortname;

		} elseif (!$this->validateShortname($shortname)) {
			$message = new SwatMessage(
				Blorg::_('Shortname already exists and must be unique.'),
				SwatMessage::ERROR);

			$this->ui->getWidget('shortname')->addMessage($message);
		}
	}

	// }}}
	// {{{ protected function validateShortname()

	protected function validateShortname($shortname)
	{
		if ($this->post->id === null) {
			$publish_date = new SwatDate();
			$publish_date->toUTC();
		} else {
			$publish_date = $this->ui->getWidget('publish_date')->value;
		}

		$publish_date->setTZ($this->app->default_time_zone);
		$instance_id = $this->app->getInstanceId();

		$sql = 'select shortname from BlorgPost
			where shortname = %s and instance %s %s and id %s %s
			and date_trunc(\'month\', convertTZ(publish_date, %s)) =
				date_trunc(\'month\', timestamp %s)';

		$sql = sprintf($sql,
			$this->app->db->quote($shortname, 'text'),
			SwatDB::equalityOperator($instance_id),
			$this->app->db->quote($instance_id, 'integer'),
			SwatDB::equalityOperator($this->id, true),
			$this->app->db->quote($this->id, 'integer'),
			$this->app->db->quote($publish_date->tz->getId(), 'text'),
			$this->app->db->quote($publish_date->getDate(), 'date'));

		$query = SwatDB::query($this->app->db, $sql);

		return (count($query) == 0);
	}

	// }}}
	// {{{ protected function saveDBData()

	protected function saveDBData()
	{
		$values = $this->ui->getValues(array(
			'title',
			'shortname',
			'bodytext',
			'extended_bodytext',
			'reply_status',
			'enabled',
			'publish_date',
		));

		$this->post->title             = $values['title'];
		$this->post->shortname         = $values['shortname'];
		$this->post->bodytext          = $values['bodytext'];
		$this->post->extended_bodytext = $values['extended_bodytext'];
		$this->post->reply_status      = $values['reply_status'];
		$this->post->enabled           = $values['enabled'];

		$now = new SwatDate();
		$now->toUTC();
		$id = $this->id;

		if ($id === null) {
			$this->post->createdate   = $now;
			$this->post->publish_date = $now;
			$this->post->instance     = $this->app->getInstanceId();
			// TODO: Fix up author support
			// $this->post->author     = $this->app->session->getUserID();
		} else {
			$this->post->modified_date = $now;
			$this->post->publish_date  = $values['publish_date'];

			if ($this->post->publish_date !== null) {
				$this->post->publish_date->setTZ($this->app->default_time_zone);
				$this->post->publish_date->toUTC();
			}
		}

		$this->post->save();

		$tag_list = $this->ui->getWidget('tags');
		SwatDB::updateBinding($this->app->db, 'BlorgPostTagBinding',
			'post', $this->post->id, 'tag', $tag_list->values,
			'BlorgTag', 'id');

		if ($id === null) {
			$form = $this->ui->getWidget('edit_form');
			$unique_id = $form->getHiddenField('unique_id');
			$sql = sprintf('update BlorgFile set post = %s,
				form_unique_id = null
				where form_unique_id = %s',
				$this->app->db->quote($this->post->id, 'integer'),
				$this->app->db->quote($unique_id, 'text'));

			SwatDB::exec($this->app->db, $sql);
		}

		$message = new SwatMessage(sprintf(Blorg::_('“%s” has been saved.'),
			$this->post->getTitle()));

		if ($this->post->enabled) {
			$this->pingWebLogsDotCom();
			$message->secondary_content = Blorg::_(
				'Weblogs.com has been notified of updated content.');
		}

		$this->app->messages->add($message);
	}

	// }}}
	// {{{ protected function pingWeblogsDotCom()

	protected function pingWeblogsDotCom()
	{
		try {
			$base_href = $this->app->getFrontendBaseHref().
				$this->app->config->blorg->path;

			$pinger = new BlorgWeblogsDotComPinger($this->app, $this->post,
				$base_href);

			$pinger->ping();
		} catch (Exception $e) {
			// ignore ping errors
		}
	}

	// }}}

	// build phase
	// {{{ protected function buildInternal()

	protected function buildInternal()
	{
		parent::buildInternal();
		$this->buildFiles();
	}

	// }}}
	// {{{ protected function loadDBData()

	protected function loadDBData()
	{
		$this->ui->setValues(get_object_vars($this->post));

		if ($this->post->publish_date !== null) {
			$publish_date = new SwatDate($this->post->publish_date);
			$publish_date->convertTZ($this->app->default_time_zone);
			$this->ui->getWidget('publish_date')->value = $publish_date;
		}

		$tag_list = $this->ui->getWidget('tags');
		$tag_list->values = SwatDB::queryColumn($this->app->db,
				'BlorgPostTagBinding', 'tag', 'post',
				$this->id);
	}

	// }}}
	// {{{ protected function buildNavBar()

	protected function buildNavBar()
	{
		parent::buildNavBar();

		if ($this->post->id !== null) {
			$entry = $this->layout->navbar->popEntry();
			$this->layout->navbar->createEntry($this->post->getTitle(),
				sprintf('Post/Details?id=%s', $this->post->id));

			$this->layout->navbar->addEntry($entry);
		}
	}

	// }}}
	// {{{ protected function buildFiles()

	protected function buildFiles()
	{
		$files = $this->post->files;
		$replicator = $this->ui->getWidget('file_replicator');
		foreach ($this->getFileReplicators() as $key => $null) {
			$file = $files[$key];

			// file title
			$title = $this->getFileTitle($file);
			$file_title = $replicator->getWidget('file_title', $key);
			$file_title->title = $title;
			$file_title->link = $file->getRelativeUri('../');

			// file icon
			$icon = $this->getFileIcon($file);
			$file_icon = $replicator->getWidget('file_icon', $key);
			$file_icon->image  = $icon['image'];
			$file_icon->width  = $icon['width'];
			$file_icon->height = $icon['height'];

			// markup
			$markup = $this->getFileMarkup($file);
			$file_markup = $replicator->getWidget('file_markup', $key);
			$file_markup->value = $markup;
		}
	}

	// }}}
	// {{{ protected function getFileTitle()

	protected function getFileTitle(BlorgFile $file)
	{
		if (strlen($file->filename) > 20) {
			$filename = SwatString::ellipsizeRight($file->filename, 20);

			$position = strrpos($file->filename, '.');
			if ($position !== false) {
				$extension = substr($file->filename, $position + 1);
				$filename.= '&nbsp;'.$extension;
			}
		} else {
			$filename = $file->filename;
		}

		if ($file->description === null) {
			$title = sprintf('%s %s',
				$filename,
				SwatString::byteFormat($file->filesize));
		} else {
			$description = SwatString::ellipsizeRight($file->description, 20);
			$title = sprintf('%s (%s) %s',
				$description,
				$filename,
				SwatString::byteFormat($file->filesize));
		}

		return $title;
	}

	// }}}
	// {{{ protected function getFileIcon()

	protected function getFileIcon(BlorgFile $file)
	{
		$icon = array();

		if ($file->image === null) {
			$icon['width']  = 48;
			$icon['height'] = 48;

			if       (strncmp('image',       $file->mime_type, 5 ) == 0) {
				$icon['image'] = 'packages/blorg/images/image.png';
			} elseif (strncmp('audio',       $file->mime_type, 5 ) == 0) {
				$icon['image'] = 'packages/blorg/images/audio.png';
			} elseif (strncmp('video',       $file->mime_type, 5 ) == 0) {
				$icon['image'] = 'packages/blorg/images/video.png';
			} elseif (strncmp('text',        $file->mime_type, 4 ) == 0) {
				$icon['image'] = 'packages/blorg/images/text.png';
			} elseif (strncmp('application', $file->mime_type, 11) == 0) {
				$icon['image'] = 'packages/blorg/images/package.png';
			} else {
				// TODO:
			}
		} else {
			$icon['width']  = $file->image->getWidth('pinky');
			$icon['height'] = $file->image->getHeight('pinky');
			$icon['image']  = $file->image->getUri('pinky', '../');
		}

		return $icon;
	}

	// }}}
	// {{{ protected function getFileMarkup()

	protected function getFileMarkup(BlorgFile $file)
	{
		if ($file->image === null) {
			$uri = $file->getRelativeUri();
			$description = ($file->description === null) ?
				$file->filename : $file->description;

			$markup = sprintf('<a class="file" href="%s">%s</a>',
				$uri, $description);
		} else {
			$uri = $file->image->getUri('original');
			$img = $file->image->getImgTag('thumb');
			$img->title = $file->description;
			$markup = sprintf('<a class="file" href="%s">%s</a>',
				$uri, $img);
		}

		return $markup;
	}

	// }}}

	// finalize phase
	// {{{ public function finalize()

	public function finalize()
	{
		parent::finalize();
		$this->layout->addHtmlHeadEntry(new SwatStyleSheetHtmlHeadEntry(
			'packages/blorg/admin/styles/blorg-post-edit-page.css',
			Store::PACKAGE_ID));
	}

	// }}}
}

?>
