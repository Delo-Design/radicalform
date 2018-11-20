<?php
// No direct access
defined( '_JEXEC' ) or die;
/**
 *
 * @package       Joomla.Plugin
 * @subpackage    System.Radicalform
 * @since         3.5+
 * @author        Progreccor
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

class plgSystemRadicalformInstallerScript
{
	function postflight( $type, $parent )
	{
		if ((version_compare(PHP_VERSION, '5.6.0') >= 0)) {

			jimport('joomla.version');
			// and now we check Joomla version
			$jversion = new JVersion();

			if ($jversion->isCompatible('3.7'))
			{
				$db = JFactory::getDbo();
				$query = $db->getQuery( true );
				$query->update( '#__extensions' )->set( 'enabled=1' )->where( 'type=' . $db->q( 'plugin' ) )->where( 'element=' . $db->q( 'radicalform' ) );
				$db->setQuery( $query )->execute();

				JFactory::getApplication()->enqueueMessage(JText::_('PLG_RADICALFORM_WELCOME_MESSAGE'), 'notice');
			}
			else
			{
				JFactory::getApplication()->enqueueMessage(JText::_('PLG_RADICALFORM_WRONG_JOOMLA'), 'error');
			}




		}
		else
		{
			JFactory::getApplication()->enqueueMessage(JText::_('PLG_RADICALFORM_WRONG_PHP'), 'error');
		}

	}
}
