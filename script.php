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
	function preflight ($type, $parent)
	{
		if (!(version_compare(PHP_VERSION, '5.6.0') >= 0)) {
			JFactory::getApplication()->enqueueMessage(JText::_('PLG_RADICALFORM_WRONG_PHP'), 'error');
			return false;
		}

		jimport('joomla.version');
		// and now we check Joomla version
		$jversion = new JVersion();

		if (!$jversion->isCompatible('3.7'))
		{
			JFactory::getApplication()->enqueueMessage(JText::_('PLG_RADICALFORM_WRONG_JOOMLA'), 'error');
			return false;
		}
	}

	function uniqidReal($lenght = 23) {
		if (function_exists("random_bytes")) {
			$bytes = random_bytes(ceil($lenght / 2));
		} elseif (function_exists("openssl_random_pseudo_bytes")) {
			$bytes = openssl_random_pseudo_bytes(ceil($lenght / 2));
		} else {
			throw new Exception("no cryptographically secure random function available");
		}
		return substr(bin2hex($bytes), 0, $lenght);
	}


	function postflight( $type, $parent )
	{

				$db = JFactory::getDbo();
				$query = $db->getQuery( true );
				$query->update( '#__extensions' )->set( 'enabled=1' )->where( 'type=' . $db->q( 'plugin' ) )->where( 'element=' . $db->q( 'radicalform' ) );
				$db->setQuery( $query )->execute();

				try
				{
					// Get the params for the radicalform plugin
					$params = $db->setQuery(
						$db->getQuery(true)
							->select($db->quoteName('params'))
							->from($db->quoteName('#__extensions'))
							->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
							->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
							->where($db->quoteName('element') . ' = ' . $db->quote('radicalform'))
					)->loadResult();
				}
				catch (Exception $e)
				{
					echo JText::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()) . '<br />';
		
					return false;
				}
		
				$params = json_decode($params, true);
		
				// set the directory to safe place

				if (!isset($params['uploadstorage']))
				{
					$params['uploadstorage'] =  JPATH_ROOT.'/images/radicalform'.$this->uniqidReal();
					mkdir($params['uploadstorage']);
				}

				// change bytes to megabytes from previous version of plugin
				if (isset($params['maxfile']) && ($params['maxfile'] > 10000))
				{
					// here we think that this number is bytes
					$params['maxfile'] = (int) ($params['maxfile'] / 1048576);
				}

				// if we update from previous versions  -
				if($type == "update")
				{
					if (!isset($params['attachfiles']))
					{
						$params['attachfiles'] = 1;
					}

				}

				if (!isset($params['downloadpath']) || (trim($params['downloadpath']) == "") )
				{
					$params['downloadpath'] =  "rfA".$this->uniqidReal(3);
				}

				$params = json_encode($params);
		
				$query = $db->getQuery(true)
					->update($db->quoteName('#__extensions'))
					->set($db->quoteName('params') . ' = ' . $db->quote($params))
					->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
					->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
					->where($db->quoteName('element') . ' = ' . $db->quote('radicalform'));
		
				try
				{
					$db->setQuery($query)->execute();
				}
				catch (Exception $e)
				{
					echo JText::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()) . '<br />';
		
					return false;
				}
						
				
				JFactory::getApplication()->enqueueMessage(JText::_('PLG_RADICALFORM_WELCOME_MESSAGE'), 'notice');
	}
}
