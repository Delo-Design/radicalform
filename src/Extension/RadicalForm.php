<?php
/**
 * @package       Joomla.Plugin
 * @subpackage    System.Radicalform
 * @since         3.7+
 * @author        Progreccor
 * @copyright     Copyright 2020 Progreccor
 * @license       GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace RadicalForm\Plugin\System\RadicalForm\Extension;

defined('_JEXEC') or die;

use Exception;
use Throwable;
use DateTimeZone;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\String\StringHelper;

/**
 * Radicalform System Plugin
 */
class RadicalForm extends CMSPlugin
{
    /**
     * @var string
     */
    private $logPath;

    /**
     * @var boolean
     */
    protected $autoloadLanguage = true;

    /**
     * @var \Joomla\Database\DatabaseDriver
     */
    protected $db;

    /**
     * @var \Joomla\CMS\Application\CMSApplication
     */
    protected $app;

    /**
     * Max directory size for mail attachments
     * @var float
     */
    protected $maxDirSize;

    /**
     * Max storage time in days
     * @var integer
     */
    protected $maxStorageTime;

    /**
     * Max storage size in bytes
     * @var float
     */
    protected $maxStorageSize;

    /**
     * Subscribe to Joomla events.
     * Required for Joomla 4+ — without this, plugin methods are never called.
     *
     * @return  array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterRender'      => 'onAfterRender',
            'onAfterInitialise'  => 'onAfterInitialise',
            'onAjaxRadicalform'  => 'onAjaxRadicalform',
        ];
    }

    /**
     * Constructor
     *
     * @param   object  &$subject  The object to observe
     * @param   array   $config    An optional associative array of configuration settings.
     */
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);

        $this->maxDirSize = $this->params->get('maxfile', 20) * 1048576;
        $this->maxStorageSize = $this->params->get('maxstorage', 1000) * 1048576;
        $this->maxStorageTime = $this->params->get('maxtime', 30);

        $configObj = Factory::getConfig();
        $this->logPath = str_replace('\\', '/', $configObj->get('log_path')) . '/plg_system_radicalform.php';

        Log::addLogger(
            [
                'text_file' => 'plg_system_radicalform.php',
                'text_entry_format' => "{DATETIME}\t{CLIENTIP}\t{MESSAGE}\t{PRIORITY}"
            ],
            Log::ALL & ~Log::DEBUG,
            ['plg_system_radicalform']
        );

        // Fix storage directory if empty or non-existent
        if (empty($this->params->get('uploadstorage')) || !file_exists($this->params->get('uploadstorage'))) {
            $this->fixStorageDirectory();
        }
    }

    /**
     * Fix empty or non-existent storage directory
     */
    private function fixStorageDirectory()
    {
        BaseDatabaseModel::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_plugins/models/');
        Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_plugins/tables/');

        $plugin = PluginHelper::getPlugin('system', 'radicalform');
        $model  = BaseDatabaseModel::getInstance('Plugin', 'PluginsModel', ['ignore_request' => true]);
        $data   = $model->getItem($plugin->id);

        if (!$data) return;
        $data = (array) $data;
        $storage = $this->params->get('uploadstorage');

        if (empty($storage)) {
            $data['params']['uploadstorage'] = JPATH_ROOT . '/images/radicalform' . $this->uniqidReal();
            if (!is_dir($data['params']['uploadstorage'])) {
                mkdir($data['params']['uploadstorage'], 0755, true);
            }
        } else {
            $baseStorage = basename($storage);
            if (file_exists(JPATH_ROOT . '/images/' . $baseStorage)) {
                $data['params']['uploadstorage'] = JPATH_ROOT . '/images/' . $baseStorage;
            } else {
                $data['params']['uploadstorage'] = JPATH_ROOT . '/images/radicalform' . $this->uniqidReal();
                if (!is_dir($data['params']['uploadstorage'])) {
                    mkdir($data['params']['uploadstorage'], 0755, true);
                }
            }
        }

        $model->save($data);
    }

    /**
     * Generate unique ID
     */
    public function uniqidReal($length = 23)
    {
        try {
            $bytes = random_bytes((int) ceil($length / 2));
        } catch (Exception $e) {
            $bytes = openssl_random_pseudo_bytes((int) ceil($length / 2));
        }
        return substr(bin2hex($bytes), 0, $length);
    }

    /**
     * Make filename safe
     */
    public function makeSafe($file)
    {
        $file = rtrim($file, '.');
        $regex = ['#(\.){2,}#', '#[^A-Za-z0-9\.\_\-]#', '#^\.#'];
        $repl = ['.', '', ''];
        return trim(preg_replace($regex, $repl, $file));
    }

    /**
     * Parse size string to bytes
     */
    private function return_bytes($size_str)
    {
        switch (substr($size_str, -1)) {
            case 'M':
            case 'm':
                return (int) $size_str * 1048576;
            case 'K':
            case 'k':
                return (int) $size_str * 1024;
            default:
                return (int) $size_str;
        }
    }

    /**
     * Get CSV data
     */
    private function getCSV($file, $delimiter = ';')
    {
        $a = [];
        if (file_exists($file) && ($handle = fopen($file, 'r')) !== false) {
            while (($data = fgetcsv($handle, 200000, $delimiter)) !== false) {
                $a[] = $data;
            }
            fclose($handle);
        }
        return $a;
    }

    /**
     * Clear input array
     */
    private function clearInput(array $input)
    {
        $unset = [
            "rfTarget", "url", "reffer", "resolution", "pagetitle",
            "rfUserAgent", "rfFormID", "rfLatestNumber", "uniq",
            "needToSendFiles", "rf-time", "rf-duration",
            $this->app->getSession()->getFormToken()
        ];

        foreach ($unset as $key) {
            if (isset($input[$key])) unset($input[$key]);
        }

        return $input;
    }

    /**
     * Event onAfterRender
     */
    public function onAfterRender()
    {
        if ($this->app->isClient('administrator')) return;

        // Skip non-HTML responses (AJAX, raw, etc.)
        $format = $this->app->input->getCmd('format', 'html');
        if ($format !== 'html' && $format !== '') return;

        $tmpl = $this->app->input->getCmd('tmpl', '');
        if ($tmpl === 'component') return;

        $body = $this->app->getBody();

        // If the button class is not present, skip
        if (strpos($body, 'rf-button-send') === false) return;

        $scriptRelPath = 'media/plg_system_radicalform/js/script.min.js';
        $scriptFullPath = JPATH_SITE . '/' . $scriptRelPath;
        $mtime = file_exists($scriptFullPath) ? filemtime($scriptFullPath) : time();
        $scriptPath = Uri::root(true) . '/' . $scriptRelPath;

        $session = $this->app->getSession();
        $lifeTime = $session->getExpire();
        $refreshTime = $lifeTime <= 60 ? 45 : $lifeTime - 60;
        if ($refreshTime > 3600 || $refreshTime <= 0) $refreshTime = 3600;

        $jsParams = [
            'DangerClass'         => $this->params->get('dangerclass'),
            'ErrorFile'           => $this->params->get('errorfile'),
            'thisFilesWillBeSend' => Text::_('PLG_RADICALFORM_THIS_FILES_WILL_BE_SEND'),
            'waitingForUpload'    => $this->params->get('waitingupload'),
            'WaitMessage'         => $this->params->get('rfWaitMessage'),
            'ErrorMax'            => Text::_('PLG_RADICALFORM_FILE_TO_LARGE_THAN_PHP_INI_ALLOWS'),
            'MaxSize'             => min($this->return_bytes(ini_get('post_max_size')), $this->return_bytes(ini_get("upload_max_filesize"))),
            'Base'                => Uri::base(true),
            'AfterSend'           => $this->params->get('aftersend'),
            'Jivosite'            => $this->params->get('jivosite'),
            'Verbox'              => $this->params->get('verbox'),
            'Subject'             => $this->params->get('rfSubject'),
            'KeepAlive'           => $this->params->get('keepalive'),
            'TokenExpire'         => $refreshTime * 1000,
            'DeleteColor'         => $this->params->get('buttondeletecolor', "#fafafa"),
            'DeleteBackground'    => $this->params->get('buttondeletecolorbackground', "#f44336")
        ];

        if ($this->params->get('insertip')) {
            $jsParams['IP'] = json_encode(['ip' => $_SERVER['REMOTE_ADDR']]);
        }

        $js = $lnEnd . "<!-- Radical Form Scripts -->" . $lnEnd
            . "<script src=\"" . $scriptPath . "?$mtime\" async></script>" . $lnEnd
            . "<script>" . $lnEnd
            . "var RadicalForm=" . json_encode($jsParams) . ";" . $lnEnd;

        // Logic for rfCall functions
        $calls = ['rfCall_0' => '(here, needReturn)', 'rfCall_1' => '(rfMessage, here)', 'rfCall_2' => '(rfMessage, here)', 'rfCall_3' => '(rfMessage, here)'];
        foreach ($calls as $key => $args) {
            $code = trim($this->params->get($key, ''));
            if (!empty($code)) {
                $js .= "function {$key}{$args} { try { " . $code . " } catch (e) { console.error('Radical Form JS Code: ', e); } }; " . $lnEnd;
            }
        }

        if (!empty($this->params->get('rfCall_9on'))) {
            $js .= "function rfCall_9(rfMessage, here) { try { " . $this->params->get('rfCall_9') . " } catch (e) { console.error('Radical Form JS Code: ', e); } }; " . $lnEnd;
        } else {
            $fallback = $this->params->get('rfCall_2') ?: ($this->params->get('rfCall_1') ?: ($this->params->get('rfCall_3') ?: "alert(rfMessage);"));
            $js .= "function rfCall_9(rfMessage, here) { try { " . $fallback . " } catch (e) { console.error('Radical Form JS Code: ', e); } }; " . $lnEnd;
        }
        $js .= "</script>" . $lnEnd;

        // Use case-insensitive regex for </body> replacement
        if (preg_match('/<\/body>/i', $body)) {
            $body = preg_replace('/<\/body>/i', $js . "</body>", $body, 1);
        } else {
            // Fallback if </body> is missing (unlikely for a page with the button, but still)
            $body .= $js;
        }

        $this->app->setBody($body);
    }

    /**
     * Get directory size
     */
    public function getDirectorySize($path)
    {
        $fileSize = 0;
        if (!is_dir($path)) return 0;
        $dir = scandir($path);
        foreach ($dir as $file) {
            if ($file != '.' && $file != '..') {
                if (is_dir($path . '/' . $file)) $fileSize += $this->getDirectorySize($path . '/' . $file);
                else $fileSize += filesize($path . '/' . $file);
            }
        }
        return $fileSize;
    }

    /**
     * Process uploaded files
     */
    private function processUploadedFiles($files, $uniq)
    {
        $storage = $this->params->get('uploadstorage');
        $uploaddir = $storage . '/rf-' . $uniq;

        if (!file_exists($storage)) mkdir($storage, 0755, true);

        // Delete old folders
        $folders = Folder::folders($storage, "rf-*", false, true);
        $maxtime = (int) $this->params->get('maxtime', 30) * 86400;
        foreach ($folders as $folder) {
            $t2 = explode("-", basename($folder));
            $dtime = time() - intval($t2[1] / 1000);
            if ($dtime > $maxtime) Folder::delete($folder);
        }

        $output = [];
        if (!empty($files)) {
            if (!file_exists($uploaddir)) mkdir($uploaddir, 0755, true);
            $totalsize = $this->getDirectorySize($storage);
            $lang = Factory::getLanguage();

            foreach ($files as $key => $file) {
                if (empty($file['name'])) {
                    $output["error"] = Text::_('PLG_RADICALFORM_ERROR_WRONG_TYPE');
                    continue;
                }

                $mime = $this->mimetype($file['tmp_name']);
                if (strpos($mime, "text") === 0 || strpos($mime, "svg") !== false) {
                    $output["error"] = Text::_('PLG_RADICALFORM_ERROR_WRONG_TYPE');
                    continue;
                }

                if ($file['error']) {
                    $errors = [
                        1 => 'PLG_RADICALFORM_FILE_TO_LARGE_THAN_PHP_INI_ALLOWS',
                        2 => 'PLG_RADICALFORM_FILE_TO_LARGE_THAN_HTML_FORM_ALLOWS',
                        3 => 'PLG_RADICALFORM_ERROR_PARTIAL_UPLOAD'
                    ];
                    $output["error"] = Text::_($errors[$file['error']] ?? 'PLG_RADICALFORM_ERROR_UPLOAD');
                    continue;
                }

                if (($file['size'] + $totalsize) < $this->maxStorageSize) {
                    if (!file_exists($uploaddir . "/" . $key)) mkdir($uploaddir . "/" . $key, 0755, true);
                    $uploadedFileName = $this->makeSafe($lang->transliterate($file['name']));
                    if (File::upload($file['tmp_name'], $uploaddir . "/" . $key . "/" . $uploadedFileName)) {
                        $output["name"] = $uploadedFileName;
                        $output["key"] = $key;
                    } else {
                        $output["error"] = Text::_('PLG_RADICALFORM_ERROR_UPLOAD');
                    }
                } else {
                    $output["error"] = Text::_('PLG_RADICALFORM_TOO_MANY_UPLOADS');
                }
            }
        }
        return $output;
    }

    /**
     * Event onAfterInitialise
     */
    public function onAfterInitialise()
    {
        $uri = Uri::getInstance();
        $path = $uri->getPath();
        $root = Uri::root(true);
        $entry = $root . "/" . $this->params->get('downloadpath');

        if (preg_match('#' . preg_quote($entry, '#') . '#', $path)) {
            $folder = basename(dirname($path));
            $uniq = basename(dirname(dirname($path)));
            $filename = basename($path);
            $this->showImage($uniq, $folder, $filename);
        }
    }

    /**
     * Get Mime Type
     */
    private function mimetype($filepath)
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimetype = finfo_file($finfo, $filepath);
            finfo_close($finfo);
        } else {
            $mimetype = mime_content_type($filepath);
        }
        return $mimetype;
    }

    /**
     * Show image
     */
    private function showImage($uniq, $folder, $name)
    {
        $filepath = $this->params->get('uploadstorage') . DIRECTORY_SEPARATOR . "rf-" . $uniq . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $name;
        if (file_exists($filepath)) {
            $mimetype = explode('/', $this->mimetype($filepath));
            if (($mimetype[0] == "text") || (strpos($mimetype[1], "svg") !== false)) {
                $this->renderFileNotFound();
            } else {
                header("Content-Type: " . $this->mimetype($filepath));
                header('Expires: 0');
                header('Cache-Control: no-cache');
                header("Content-Length: " . (string) (filesize($filepath)));
                echo file_get_contents($filepath);
            }
        } else {
            $this->renderFileNotFound();
        }
        $this->app->close(200);
    }

    /**
     * Delete uploaded file
     */
    public function deleteUploadedFile($catalog, $name, $uniq)
    {
        $name = $this->makeSafe(basename($name));
        $catalog = $this->makeSafe(basename($catalog));
        $uniq = (int) $uniq;
        $filename = $this->params->get('uploadstorage') . '/rf-' . $uniq . "/" . $catalog . "/" . $name;
        if (file_exists($filename)) unlink($filename);
        return "ok";
    }

    /**
     * Render 404 image
     */
    public function renderFileNotFound()
    {
        $filenotfound = JPATH_ROOT . HTMLHelper::_('image', 'plg_system_radicalform/filenotfound.svg', '', null, true, 1);
        header('HTTP/1.1 404 Not Found');
        header("Content-Type: " . $this->mimetype($filenotfound));
        header('Expires: 0');
        header('Cache-Control: no-cache');
        header("Content-Length: " . (string) (filesize($filenotfound)));
        echo file_get_contents($filenotfound);
    }

    /**
     * AJAX Event Handler
     */
    public function onAjaxRadicalform()
    {
        $r = $this->app->input;
        $input = $r->post->getArray();
        $get = $r->get->getArray();
        $files = $r->files->getArray();
        
        $config = Factory::getConfig();
        $logPath = str_replace('\\', '/', $config->get('log_path'));
        $data = $this->getCSV($logPath . '/plg_system_radicalform.php', "\t");

        // Clean headers from log
        if (count($data) > 0) {
            for ($i = 0; $i < 6; $i++) {
                if (isset($data[$i]) && (count($data[$i]) < 4 || $data[$i][0][0] == '#')) unset($data[$i]);
            }
        }

        $latestNumber = 1;
        if (count($data) > 0) {
            $dataRev = array_reverse($data);
            $json = json_decode($dataRev[0][2], true);
            if (is_array($json) && isset($json['rfLatestNumber'])) {
                $latestNumber = $json['rfLatestNumber'] + 1;
            }
        }
        $input['rfLatestNumber'] = $latestNumber;

        // Custom actions
        if (isset($get['deletefile'], $get['catalog'], $get['uniq'])) return $this->deleteUploadedFile($get['catalog'], $get['deletefile'], $get['uniq']);
        if (isset($input['gettoken'])) return $this->app->getSession()->getFormToken();

        // CSV Export
        if (isset($get['admin']) && ($get['admin'] == 4 || $get['admin'] == 5)) {
            if ($this->app->isClient('administrator')) return $this->exportCSV($get['page'] ?? '0', $latestNumber);
        }

        // Telegram check
        if (isset($get['admin']) && $get['admin'] == 1) {
            if ($this->app->isClient('administrator')) return $this->checkTelegram();
        }

        // Clear log
        if (isset($get['admin']) && $get['admin'] == 2) {
            if ($this->app->isClient('administrator')) {
                $pagePrefix = (isset($get['page']) && $get['page'] != '0') ? $get['page'] . '.' : '';
                File::delete($logPath . '/' . $pagePrefix . 'plg_system_radicalform.php');
                if ($pagePrefix == '') {
                    Log::add(json_encode(['rfLatestNumber' => $latestNumber, 'message' => Text::_('PLG_RADICALFORM_CLEAR_HISTORY')]), Log::NOTICE, 'plg_system_radicalform');
                }
                return "ok";
            }
        }

        // Reset numbering
        if (isset($get['admin']) && $get['admin'] == 3) {
            if ($this->app->isClient('administrator')) {
                Log::add(json_encode(['rfLatestNumber' => 0, 'message' => Text::_('PLG_RADICALFORM_RESET_NUMBER')]), Log::NOTICE, 'plg_system_radicalform');
                return "ok";
            }
        }

        // Token check
        if (empty($input['uniq'])) return ["error" => Text::_('PLG_RADICALFORM_INVALID_TOKEN')];

        // File upload via AJAX
        if (isset($get['file']) && $get['file'] == 1) return $this->processUploadedFiles($files, (int) $input['uniq']);

        // Final form submission
        if (!$this->app->getSession()->checkToken()) {
            Log::add(json_encode(['rfLatestNumber' => $latestNumber, 'message' => Text::_('PLG_RADICALFORM_INVALID_TOKEN')]), Log::WARNING, 'plg_system_radicalform');
            return Text::_('PLG_RADICALFORM_INVALID_TOKEN');
        }

        return $this->processFormSubmission($input, $latestNumber);
    }

    /**
     * Check Telegram Updates
     */
    private function checkTelegram()
    {
        $qv = "https://api.telegram.org/bot" . $this->params->get('telegramtoken') . "/getUpdates";
        $ch = curl_init();
        if ($this->params->get('proxy')) {
            $proxy = $this->params->get('proxylogin') . ":" . $this->params->get('proxypassword') . "@" . $this->params->get('proxyaddress') . ":" . $this->params->get('proxyport');
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
        }
        curl_setopt($ch, CURLOPT_URL, $qv);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $output = json_decode($result, true);
        if (curl_getinfo($ch, CURLINFO_RESPONSE_CODE) != '200') return $output;
        curl_close($ch);

        $chatIDs = [];
        foreach ($output["result"] ?? [] as $chat) {
            $msg = $chat["message"] ?? [];
            $c = $msg["chat"] ?? [];
            $name = $c["username"] ?? "";
            if (isset($c["first_name"]) || isset($c["last_name"])) $name .= " (" . ($c["first_name"] ?? '') . " " . ($c["last_name"] ?? '') . ")";
            $chatIDs[] = ["name" => trim($name), "chatID" => $c["id"]];
        }
        return ["ok" => true, "chatids" => $chatIDs];
    }

    /**
     * Export CSV
     */
    private function exportCSV($pageParam, $latestNumber)
    {
        $config = Factory::getConfig();
        $site_offset = $config->get('offset');
        $jdate = Factory::getDate('now', $site_offset);
        $filename = "rfexport_" . $jdate->format('d-m-Y_H-i_s', true) . ".csv";

        header("Content-disposition: attachment; filename={$filename}");
        header("Content-Type: text/csv");
        echo "\xEF\xBB\xBF"; // BOM

        $headers = ["#", Text::_('PLG_RADICALFORM_HISTORY_TIME')];
        if ($this->params->get('showtarget')) $headers[] = Text::_('PLG_RADICALFORM_HISTORY_TARGET');
        if ($this->params->get('showformid')) $headers[] = Text::_('PLG_RADICALFORM_HISTORY_FORMID');
        $headers[] = Text::_('PLG_RADICALFORM_HISTORY_IP');
        $headers[] = Text::_('PLG_RADICALFORM_HISTORY_MESSAGE');
        if ($this->params->get('hiddeninfo')) $headers[] = Text::_('PLG_RADICALFORM_HISTORY_EXTRA');

        echo implode(';', $headers) . "\r\n";

        $logPath = str_replace('\\', '/', $config->get('log_path'));
        $page = ($pageParam == "0") ? "" : $pageParam . ".";
        $data = $this->getCSV($logPath . '/' . $page . 'plg_system_radicalform.php', "\t");
        foreach ($data as $k => $v) if (isset($v[0]) && $v[0][0] == '#') unset($data[$k]);
        $data = array_reverse($data);

        foreach ($data as $item) {
            if (count($item) < 3) continue;
            $json = json_decode($item[2], true);
            $d = Factory::getDate($item[0], $site_offset);
            
            $ln = $json["rfLatestNumber"] ?? '';
            unset($json["rfLatestNumber"]);

            $target = $this->params->get('showtarget') ? "\"" . ($json["rfTarget"] ?? "") . "\";" : "";
            unset($json["rfTarget"]);

            $formid = $this->params->get('showformid') ? "\"" . ($json["rfFormID"] ?? "") . "\";" : "";
            unset($json["rfFormID"]);

            $extra = "";
            if ($this->params->get('hiddeninfo')) {
                $e = [
                    Text::_('PLG_RADICALFORM_URL') . ($json["url"] ?? ""),
                    Text::_('PLG_RADICALFORM_REFFER') . ($json["reffer"] ?? ""),
                    Text::_('PLG_RADICALFORM_RESOLUTION') . ($json["resolution"] ?? ""),
                    Text::_('PLG_RADICALFORM_PAGETITLE') . ($json["pagetitle"] ?? ""),
                    Text::_('PLG_RADICALFORM_USERAGENT') . ($json["rfUserAgent"] ?? ""),
                    Text::_('PLG_RADICALFORM_USER_TIME') . ($json["rf-time"] ?? ""),
                    Text::sprintf('PLG_RADICALFORM_FORM_DURATION', $json["rf-duration"] ?? 0)
                ];
                $extra = "\"" . implode("\n", array_filter($e)) . "\";";
                unset($json["url"], $json["reffer"], $json["resolution"], $json["pagetitle"], $json["rfUserAgent"], $json["rf-time"], $json["rf-duration"]);
            }

            $msg = [];
            foreach ($json as $k => $v) $msg[] = Text::_($k) . ": " . (is_array($v) ? implode(", ", $v) : $v);
            $msgStr = "\"" . implode("\n", $msg) . "\"";

            echo "{$ln};\"" . $d->format('H:i:s', true) . "\n" . $d->format('d.m.Y', true) . "\";{$target}{$formid}{$item[1]};{$msgStr};{$extra}\r\n";
        }
        return '';
    }

    /**
     * Process Form Submission
     */
    private function processFormSubmission($input, $latestNumber)
    {
        $mailer = Factory::getMailer();
        $config = Factory::getConfig();
        $sender = [$config->get('mailfrom'), $config->get('fromname')];
        $mailer->setSender($sender);

        PluginHelper::importPlugin('radicalform');
        $this->params->set('uploaddir', $this->params->get('uploadstorage') . '/rf-' . (int) $input['uniq']);
        $this->params->set('rfLatestNumber', $latestNumber);

        $this->app->triggerEvent('onBeforeSendRadicalForm', [$this->clearInput($input), &$input, $this->params]);

        $subject = $input["rfSubject"] ?? $this->params->get('rfSubject');
        unset($input["rfSubject"]);

        // Replace placeholders in subject
        preg_match_all('/{(.*?)}/i', $subject, $matches, PREG_SET_ORDER);
        foreach ($matches ?? [] as $match) {
            if (isset($input[$match[1]])) {
                $val = is_array($input[$match[1]]) ? implode(", ", $input[$match[1]]) : $input[$match[1]];
                $subject = str_replace($match[0], $val, $subject);
            }
        }
        $mailer->setSubject($subject);

        $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $downloadPath = $this->params->get('downloadpath');
        $uniq = (int) $input['uniq'];

        if (isset($input["needToSendFiles"]) && $input["needToSendFiles"] == 1) {
            $storage = $this->params->get('uploadstorage');
            foreach (Folder::folders($storage . '/rf-' . $uniq) as $folder) {
                $files = Folder::files($storage . '/rf-' . $uniq . "/" . $folder, ".", false, true);
                foreach ($files as $file) {
                    $link = "{$url}/{$downloadPath}/{$uniq}/{$folder}/" . basename($file);
                    $input[$folder] = ($input[$folder] ?? '') . ($input[$folder] ? $this->params->get('delimiter', "<br />") : '') . $link;
                    if ($this->params->get('attachfiles', 0)) $mailer->addAttachment($file);
                }
            }
        }

        // Logging
        if (file_exists($this->logPath) && $this->params->get('maxlogfile') < filesize($this->logPath)) {
            File::delete($this->logPath);
            Log::add(json_encode(['rfLatestNumber' => $latestNumber, 'message' => Text::_('PLG_RADICALFORM_CLEAR_HISTORY_BY_MAX_LOG')]), Log::NOTICE, 'plg_system_radicalform');
            $latestNumber++;
        }
        $input['rfLatestNumber'] = $latestNumber;
        Log::add(json_encode(array_filter($input, function ($v) { return $v !== ''; })), Log::NOTICE, 'plg_system_radicalform');

        $target = $input["rfTarget"] ?? false;
        $input = $this->clearInput($input);

        // Build message
        $telegram = "<b>" . ($this->params->get('insertformid') ? ($input["rfFormID"] ?? $subject) : $subject) . "</b><br /><br />";
        $mainbody = "";
        foreach ($input as $key => $record) {
            $val = is_array($record) ? implode($this->params->get('glue'), $record) : $record;
            if ($key == "phone") {
                $mainbody .= "<p>" . Text::_($key) . ": <strong><a href='tel://" . $val . "'>" . $val . "</a></strong></p>";
            } else {
                $mainbody .= "<p>" . Text::_($key) . ": <strong>" . $val . "</strong></p>";
            }
            $telegram .= Text::_($key) . ": <b>" . $val . "</b><br />";
        }

        $header = $this->params->get('insertformid') ? "<h2>" . ($input["rfFormID"] ?? '') . "</h2>" : "";
        $footer = $this->params->get('extendedinfo') ? $this->buildFooter($latestNumber, $input, $_SERVER['REMOTE_ADDR']) : "";

        // Template logic
        $layoutPath = PluginHelper::getLayoutPath('system', 'radicalform');
        ob_start();
        include $layoutPath;
        $body = ob_get_clean();

        // Custom code execution
        $this->executeCustomCode($target);

        // Send Telegram
        $this->sendTelegram($target, $telegram);

        $textOutput = str_replace(["<b>", "</b>", "<br />"], ["", "", " \r\n"], $telegram);

        if ($this->params->get('emailon')) {
            $mailer->isHtml(true);
            $mailer->Encoding = 'base64';
            $mailer->setBody($body);
            
            $recipientOk = false;
            if ($target !== false) {
                foreach ((array) $this->params->get('emailalt') as $item) {
                    if ($target == $item->target) {
                        $mailer->addRecipient($item->email);
                        $recipientOk = true;
                    }
                }
            } else {
                $mailer->addRecipient($this->params->get('email'));
                if ($this->params->get('emailcc')) $mailer->addCc($this->params->get('emailcc'));
                if ($this->params->get('emailbcc')) $mailer->addBcc($this->params->get('emailbcc'));
                $recipientOk = true;
            }

            if ($this->params->get('replyto') && isset($input[$this->params->get('replyto')])) $mailer->addReplyTo($input[$this->params->get('replyto')]);

            if ($recipientOk) {
                $send = $mailer->Send();
                if ($send === false) return Text::_('PLG_RADICALFORM_MAIL_DISABLED');
                if ($send !== true) return $send->getMessage();
            }
        }

        return ['ok', $textOutput];
    }

    private function buildFooter($latestNumber, $input, $ip)
    {
        $f = "# <strong>" . $latestNumber . "</strong><br>";
        if (!empty($input["rfFormID"])) $f .= Text::_('PLG_RADICALFORM_FORMID') . "<strong>" . Text::_($input["rfFormID"]) . "</strong><br>";
        $f .= Text::_('PLG_RADICALFORM_IP_ADDRESS') . "<a href='http://whois.domaintools.com/{$ip}'><strong>{$ip}</strong></a><br>";
        $f .= Text::_('PLG_RADICALFORM_URL') . ($input["url"] ?? '') . "<br />";
        if (!empty($input["reffer"])) $f .= Text::_('PLG_RADICALFORM_REFFER') . "<a href='{$input["reffer"]}'>" . substr($input["reffer"], 0, 64) . "</a><br />";
        $f .= Text::_('PLG_RADICALFORM_PAGETITLE') . "<strong>" . htmlentities($input["pagetitle"] ?? '') . "</strong><br />";
        $f .= Text::_('PLG_RADICALFORM_USERAGENT') . "<strong>" . htmlentities($input["rfUserAgent"] ?? '') . "</strong><br />";
        $f .= Text::_('PLG_RADICALFORM_RESOLUTION') . "<strong>" . ($input["resolution"] ?? '') . "</strong><br />";
        $f .= Text::_('PLG_RADICALFORM_USER_TIME') . "<strong>" . ($input["rf-time"] ?? '') . "</strong><br />";
        $f .= Text::sprintf('PLG_RADICALFORM_FORM_DURATION', $input["rf-duration"] ?? 0);
        return $f;
    }

    private function executeCustomCode($target)
    {
        if (!$this->params->get('customcodeon')) return;
        foreach ((array) $this->params->get('customcodes') as $cc) {
            if (($target !== false && $cc->target == $target) || (empty(trim($cc->target ?? '')) && $target === false)) {
                $tpl = $this->app->getTemplate();
                $p = JPATH_THEMES . '/' . $tpl . '/html/plg_system_radicalform/' . $cc->layout;
                if (file_exists($p)) include $p;
            }
        }
    }

    private function sendTelegram($target, $telegram)
    {
        if (!$this->params->get('telegram')) return;
        $token = $this->params->get('telegramtoken');
        foreach ((array) $this->params->get('chatids') as $cid) {
            if (($target !== false && ($cid->target ?? '') == $target) || (empty(trim($cid->target ?? '')) && $target === false)) {
                $u = "https://api.telegram.org/bot{$token}/sendMessage?" . http_build_query([
                    'disable_web_page_preview' => true,
                    'chat_id' => $cid->chat_id,
                    'parse_mode' => 'HTML',
                    'text' => str_replace("<br />", "\r\n", $telegram)
                ]);
                $ch = curl_init($u);
                if ($this->params->get('proxy')) {
                    $proxy = $this->params->get('proxylogin') . ":" . $this->params->get('proxypassword') . "@" . $this->params->get('proxyaddress') . ":" . $this->params->get('proxyport');
                    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
                    curl_setopt($ch, CURLOPT_PROXY, $proxy);
                }
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                curl_close($ch);
            }
        }
    }
}
