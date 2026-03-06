<?php
/**
 * @package       Joomla.Plugin
 * @subpackage    System.Radicalform
 * @since         3.5+
 * @author        Progreccor
 * @license       GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Version;

/**
 * Installation script for Radicalform plugin
 */
class plgSystemRadicalformInstallerScript
{
	/**
	 * Minimum PHP version required
	 * Joomla 5 requires 8.1, Joomla 6 will likely require 8.2+
	 */
	protected $minPhp = '8.1.0';

	/**
	 * Minimum Joomla version required
	 */
	protected $minJoomla = '4.4';

	/**
	 * Preflight method
	 *
	 * @param   string  $type    The type of change (install, update or discover_install)
	 * @param   object  $parent  The class calling this method
	 *
	 * @return  boolean  True on success
	 */
	public function preflight($type, $parent)
	{
		// Check PHP version
		if (!version_compare(PHP_VERSION, $this->minPhp, '>=')) {
			Factory::getApplication()->enqueueMessage(
				Text::sprintf('PLG_RADICALFORM_WRONG_PHP', $this->minPhp),
				'error'
			);
			return false;
		}

		// Check Joomla version
		if (!(new Version())->isCompatible($this->minJoomla)) {
			Factory::getApplication()->enqueueMessage(
				Text::sprintf('PLG_RADICALFORM_WRONG_JOOMLA', $this->minJoomla),
				'error'
			);
			return false;
		}

		return true;
	}

	/**
	 * Generate a real unique ID
	 *
	 * @param   integer  $length  Length of the unique ID
	 *
	 * @return  string
	 */
	protected function uniqidReal($length = 23)
	{
		try {
			$bytes = random_bytes((int) ceil($length / 2));
		} catch (\Exception $e) {
			$bytes = openssl_random_pseudo_bytes((int) ceil($length / 2));
		}

		return substr(bin2hex($bytes), 0, $length);
	}

	/**
	 * Postflight method
	 *
	 * @param   string  $type    The type of change (install, update or discover_install)
	 * @param   object  $parent  The class calling this method
	 *
	 * @return  void
	 */
	public function postflight($type, $parent)
	{
		$db    = Factory::getDbo();
		$query = $db->getQuery(true);

		// Enable the plugin automatically
		$query->update($db->quoteName('#__extensions'))
			->set($db->quoteName('enabled') . ' = 1')
			->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
			->where($db->quoteName('element') . ' = ' . $db->quote('radicalform'));

		try {
			$db->setQuery($query)->execute();
		} catch (\Exception $e) {
			// Silent fail for enable if something is wrong with DB
		}

		// Handle parameters
		try {
			$query = $db->getQuery(true)
				->select($db->quoteName('params'))
				->from($db->quoteName('#__extensions'))
				->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
				->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
				->where($db->quoteName('element') . ' = ' . $db->quote('radicalform'));

			$paramsJson = $db->setQuery($query)->loadResult();
			$params     = json_decode($paramsJson, true) ?: [];
		} catch (\Exception $e) {
			Factory::getApplication()->enqueueMessage(
				Text::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()),
				'error'
			);
			return;
		}

		// Set the directory to safe place if not set
		if (empty($params['uploadstorage'])) {
			$params['uploadstorage'] = JPATH_ROOT . '/images/radicalform' . $this->uniqidReal();
			if (!is_dir($params['uploadstorage'])) {
				mkdir($params['uploadstorage'], 0755, true);
			}
		}

		// Change bytes to megabytes from previous version of plugin if needed
		if (isset($params['maxfile']) && $params['maxfile'] > 10000) {
			$params['maxfile'] = (int) ($params['maxfile'] / 1048576);
		}

		// Update from previous versions
		if ($type === 'update') {
			if (!isset($params['attachfiles'])) {
				$params['attachfiles'] = 1;
			}
		}

		if (empty($params['downloadpath']) || trim($params['downloadpath']) === '') {
			$params['downloadpath'] = 'rfA' . $this->uniqidReal(3);
		}

		$paramsEncoded = json_encode($params);

		$query = $db->getQuery(true)
			->update($db->quoteName('#__extensions'))
			->set($db->quoteName('params') . ' = ' . $db->quote($paramsEncoded))
			->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
			->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
			->where($db->quoteName('element') . ' = ' . $db->quote('radicalform'));

		try {
			$db->setQuery($query)->execute();
		} catch (\Exception $e) {
			Factory::getApplication()->enqueueMessage(
				Text::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()),
				'error'
			);
			return;
		}

		Factory::getApplication()->enqueueMessage(Text::_('PLG_RADICALFORM_WELCOME_MESSAGE'), 'notice');
	}
}

