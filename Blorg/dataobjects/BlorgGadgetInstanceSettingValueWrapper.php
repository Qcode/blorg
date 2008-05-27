<?php

require_once 'SwatDB/SwatDBRecordsetWrapper.php';
require_once 'Blorg/dataobjects/BlorgGadgetInstanceSettingValue.php';

/**
 * A recordset wrapper for gadget instance setting values
 *
 * @package   Blörg
 * @copyright 2008 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       BlorgGadgetInstanceSettingValue
 */
class BlorgGadgetInstanceSettingValueWrapper extends SwatDBRecordsetWrapper
{
	// {{{ protected function init()

	protected function init()
	{
		parent::init();
		$this->row_wrapper_class =
			SwatDBClassMap::get('BlorgGadgetInstanceSettingValue');
	}

	// }}}
}

?>
