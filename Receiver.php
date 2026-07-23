<?php namespace Wc1c\Main\Schemas\Productscleanercml;

defined('ABSPATH') || exit;

use Wc1c\Main\Exceptions\RuntimeException;
use Wc1c\Main\Traits\SingletonTrait;
use Wc1c\Main\Traits\UtilityTrait;

/**
 * Receiver
 *
 * @package Wc1c\Main\Schemas\Productscleanercml
 */
final class Receiver
{
	use SingletonTrait;
	use UtilityTrait;

	/**
	 * @var Core Schema core
	 */
	protected $core;

	/**
	 * @return void
	 */
	public function initHandler()
	{
        if('standard' === $this->core()->getOptions('directory_clean_mode', 'standard'))
        {
            add_action('wc1c_schema_productscleanercml_catalog_handler_init', [$this, 'handlerCatalogDirectoryClean'], 10, 0);
        }

		add_action('wc1c_receiver_' . $this->core()->getId(), [$this, 'handler'], 10, 0);
		add_action('wc1c_schema_productscleanercml_catalog_handler_checkauth', [$this, 'handlerCheckauth'], 10, 0);
		add_action('wc1c_schema_productscleanercml_catalog_handler_init', [$this, 'handlerCatalogModeInit'], 10, 0);
		add_action('wc1c_schema_productscleanercml_catalog_handler_file', [$this, 'handlerCatalogModeFile'], 10, 0);
		add_action('wc1c_schema_productscleanercml_catalog_handler_import', [$this, 'handlerCatalogModeImport'], 10, 0);
		add_action('wc1c_schema_productscleanercml_catalog_handler_deactivate', [$this, 'handlerCatalogModeDeactivate'], 10, 0);
		add_action('wc1c_schema_productscleanercml_catalog_handler_complete', [$this, 'handlerCatalogModeComplete'], 10, 0);
	}

	/**
	 * @return Core
	 */
	public function core(): Core
    {
		return $this->core;
	}

	/**
	 * @param Core $core
	 */
	public function setCore(Core $core)
	{
		$this->core = $core;
	}

	/**
	 * Handler
	 */
	public function handler()
	{
		$this->core()->log()->info(esc_html__('Received new request for Receiver.', 'wc1c-maincore'));

		$mode = '';
		$type = '';

		if(wc1c()->getVar($_GET['get_param'], '') !== '' || wc1c()->getVar($_GET['get_param?type'], '') !== '')
		{
			$output = [];
			if(isset($_GET['get_param']))
			{
				$get_param = ltrim(sanitize_text_field($_GET['get_param']), '?');
				parse_str($get_param, $output);
			}

			if(array_key_exists('mode', $output))
			{
				$mode = sanitize_key($output['mode']);
			}
			elseif(isset($_GET['mode']))
			{
				$mode = sanitize_key($_GET['mode']);
			}

			if(array_key_exists('type', $output))
			{
				$type = $output['type'];
			}
			elseif(isset($_GET['type']))
			{
				$type = sanitize_key($_GET['type']);
			}

			if($type === '')
			{
				$type = sanitize_key($_GET['get_param?type']);
			}
		}

		$this->core()->log()->debug(esc_html__('Received request params.', 'wc1c-maincore'), ['type' => $type, 'mode=' => $mode]);

		if($type === 'catalog' && $mode !== '')
		{
			do_action('wc1c_schema_productscleanercml_catalog_handler', $mode, $this);

			switch($mode)
			{
				case 'checkauth':
					do_action('wc1c_schema_productscleanercml_catalog_handler_checkauth', $this);
					break;
				case 'init':
					$this->handlerCheckauthKey(true);
					do_action('wc1c_schema_productscleanercml_catalog_handler_init', $this);
					break;
				case 'file':
					$this->handlerCheckauthKey(true);
					do_action('wc1c_schema_productscleanercml_catalog_handler_file', $this);
					break;
				case 'import':
					$this->handlerCheckauthKey(true);
					do_action('wc1c_schema_productscleanercml_catalog_handler_import', $this);
					break;
				case 'deactivate':
					$this->handlerCheckauthKey(true);
					do_action('wc1c_schema_productscleanercml_catalog_handler_deactivate', $this);
					break;
				case 'complete':
					$this->handlerCheckauthKey(true);
					do_action('wc1c_schema_productscleanercml_catalog_handler_complete', $this);
					break;
				default:
					do_action('wc1c_schema_productscleanercml_catalog_handler_none', $mode, $this);
					$this->sendResponseByType('failure', esc_html__('Catalog: mode not found.', 'wc1c-maincore'));
			}
		}

		do_action('wc1c_schema_productscleanercml_handler_none', $mode, $this);

		$response_description = esc_html__('Schema: action not found.', 'wc1c-maincore');
		$this->core()->log()->warning($response_description);
		$this->sendResponseByType('failure', $response_description);
	}

	/**
	 * Request for a successful catalog upload
	 *
	 * @return void
	 */
	public function handlerCatalogModeComplete()
	{
		$this->sendResponseByType('success');
	}

	/**
	 * Request to deactivate old items
	 *
	 * @return void
	 */
	public function handlerCatalogModeDeactivate()
	{
		$this->sendResponseByType('success');
	}

	/**
	 * Send response by type
	 *
	 * @param string $type
	 * @param string $description
	 */
	public function sendResponseByType(string $type = 'failure', $description = '')
	{
		if(has_filter('wc1c_schema_productscleanercml_receiver_send_response_type'))
		{
			$type = apply_filters('wc1c_schema_productscleanercml_receiver_send_response_type', $type, $this);
		}

		if(has_filter('wc1c_schema_productscleanercml_receiver_send_response_by_type_description'))
		{
			$description = apply_filters('wc1c_schema_productscleanercml_receiver_send_response_by_type_description', $description, $this, $type);
		}

		$this->core()->log()->info(esc_html__('In 1C was send a response of the type:', 'wc1c-maincore') . ' ' . $type);

		$headers= [];
		$headers['Content-Type'] = 'Content-Type: text/plain; charset=utf-8';

		if(has_filter('wc1c_schema_productscleanercml_receiver_send_response_by_type_headers'))
		{
			$headers = apply_filters('wc1c_schema_productscleanercml_receiver_send_response_by_type_headers', $headers, $this, $type);
		}

		$this->core()->log()->debug(esc_html__('Headers for response.', 'wc1c-maincore'), ['context' => $headers]);

		foreach($headers as $header)
		{
			header($header);
		}

		switch($type)
		{
			case 'success':
				echo 'success' . PHP_EOL;
				break;
			case 'progress':
				echo 'progress' . PHP_EOL;
				break;
			default:
				echo 'failure' . PHP_EOL;
		}

		if($description !== '')
		{
			printf('%s', wp_kses_post($description));
		}
		exit;
	}

	/**
	 * @return array
	 */
    public function getCredentialsByServer(): array
    {
        $credentials = ['login' => '', 'password' => ''];

        if (!isset($_SERVER['PHP_AUTH_USER']))
        {
            $remote_user = '';

            if(isset($_SERVER['REMOTE_USER']))
            {
                $remote_user = sanitize_text_field($_SERVER['REMOTE_USER']);
            }
            elseif(isset($_SERVER['REDIRECT_REMOTE_USER']))
            {
                $remote_user = sanitize_text_field($_SERVER['REDIRECT_REMOTE_USER']);
            }
            elseif(isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']))
            {
                $remote_user = sanitize_text_field($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
            }

            if (empty($remote_user))
            {
                $this->core()->log('schemas')->critical(esc_html__('Server in CGI mode. Auth headers not detected.', 'wc1c-maincore'),
                    ['lines' => "RewriteEngine On\nRewriteCond %{HTTP:Authorization} ^(.*)\nRewriteRule ^(.*) - [E=HTTP_AUTHORIZATION:%1]"]
                );

                $this->core()->configuration()->setStatus('error');
                $this->core()->configuration()->save();
                $this->sendResponseByType('failure', esc_html__('Not specified the user. Check the server settings.', 'wc1c-maincore'));
            }

            $str_tmp = base64_decode(substr($remote_user, 6));
            if ($str_tmp && strpos($str_tmp, ':') !== false)
            {
                list($user_login, $user_password) = explode(':', $str_tmp, 2);
                $credentials['login'] = trim($user_login);
                $credentials['password'] = (string) $user_password;

                $this->core()->log()->debug(esc_html__('Credentials extracted from CGI headers.', 'wc1c-maincore'), ['login' => $credentials['login'], 'password_length' => strlen($credentials['password'])]);
            }

            return $credentials;
        }

        $credentials['login'] = sanitize_text_field($_SERVER['PHP_AUTH_USER']);
        $credentials['password'] = isset($_SERVER['PHP_AUTH_PW']) ? sanitize_text_field(wp_unslash($_SERVER['PHP_AUTH_PW'])) : '';

        $this->core()->log()->debug(esc_html__('Credentials extracted from PHP_AUTH headers.', 'wc1c-maincore'), ['login' => $credentials['login'], 'password_length' => strlen($credentials['password'])]);

        return $credentials;
    }

	/**
	 * Checkauth
	 */
	public function handlerCheckauth()
	{
		$credentials = $this->getCredentialsByServer();
		$validator = false;

		if(has_filter('wc1c_schema_productscleanercml_handler_checkauth_validate'))
		{
			$validator = apply_filters('wc1c_schema_productscleanercml_handler_checkauth_validate', $credentials);
		}

        if(true !== $validator)
        {
            $stored_login = (string) $this->core()->getOptions('user_login', '');
            $stored_password = (string) $this->core()->getOptions('user_password', '');

            if (!hash_equals($stored_login, $credentials['login']))
            {
                $this->core()->log()->notice(esc_html__('Not a valid username.', 'wc1c-maincore'));
                $this->sendResponseByType('failure', esc_html__('Not a valid username.', 'wc1c-maincore'));
            }

            if (!hash_equals($stored_password, $credentials['password']))
            {
                $this->core()->log()->notice(esc_html__('Not a valid user password.', 'wc1c-maincore'));
                $this->sendResponseByType('failure', esc_html__('Not a valid user password.', 'wc1c-maincore'));
            }
        }

		$lines = [];

		$session_name = session_name();

		if(session_status() === PHP_SESSION_NONE && defined('WC1C_RECEIVER_REQUEST') && WC1C_RECEIVER_REQUEST)
		{
			$this->core()->log()->debug(esc_html__('PHP session none, start new PHP session.', 'wc1c-maincore'));
			session_start();
		}

		$session_id = session_id();

		$this->core()->configuration()->addMetaData('session_name', maybe_serialize($session_name), true);
		$this->core()->configuration()->addMetaData('session_id', maybe_serialize($session_id), true);
		$this->core()->configuration()->saveMetaData();

		$this->core()->log()->debug(esc_html__('Request authorization from 1C successfully completed.', 'wc1c-maincore'), ['session_name' => $session_name, 'session_id' => $session_id]);

		$lines['success'] = 'success' . PHP_EOL;
		$lines['session_name'] = $session_name . PHP_EOL;
		$lines['session_id'] = $session_id . PHP_EOL;

		$lines['bitrix_sessid'] = 'sessid=' . $session_id . PHP_EOL;
		$lines['timestamp'] = 'timestamp=' . time() . PHP_EOL;

		if(has_filter('wc1c_schema_productscleanercml_handler_checkauth_lines'))
		{
			$lines = apply_filters('wc1c_schema_productscleanercml_handler_checkauth_lines', $lines);
		}

		$this->core()->log()->debug(esc_html__('Print lines for 1C.', 'wc1c-maincore'), ['data' => $lines]);

		foreach($lines as $line)
		{
			printf('%s', wp_kses_post($line));
		}
		die();
	}

	/**
	 * Authorization key verification
	 *
	 * @param bool $send_response
	 *
	 * @return bool
     */
	public function handlerCheckauthKey(bool $send_response = false): bool
    {
		if(!isset($_GET['lazysign']))
		{
            if('yes' === $this->core()->getOptions('browser_debug', 'no'))
            {
                return true;
            }

			$warning = esc_html__('Authorization key verification failed. 1C did not send the name of the lazy signature.', 'wc1c-maincore');
			$this->core()->log()->warning($warning);

			if($send_response)
			{
				$this->sendResponseByType('failure', $warning);
			}

			return false;
		}

		$lazy_sign = sanitize_text_field($_GET['lazysign']);
		$lazy_sign_store = sanitize_text_field($this->core()->configuration()->getMeta('receiver_lazy_sign'));

		if($lazy_sign_store !== $lazy_sign)
		{
			$warning = esc_html__('Authorization key verification failed. 1C sent an incorrect lazy signature.', 'wc1c-maincore');
			$this->core()->log()->warning($warning);

			if($send_response)
			{
				$this->sendResponseByType('failure', $warning);
			}

			return false;
		}

		$session_name = sanitize_text_field($this->core()->configuration()->getMeta('session_name'));

		if(!isset($_COOKIE[$session_name]))
		{
			$warning = esc_html__('Authorization key verification failed. 1C sent an empty session name.', 'wc1c-maincore');
			$this->core()->log()->warning($warning);

			if($send_response)
			{
				$this->sendResponseByType('failure', $warning);
			}

			return false;
		}

		$session_id = sanitize_text_field($this->core()->configuration()->getMeta('session_id'));

		if($_COOKIE[$session_name] !== $session_id)
		{
			$warning = esc_html__('Authorization check failed - session id differs from the original.', 'wc1c-maincore');

            $this->core()->log()->warning
            (
                $warning,
                [
                    'client_session_id' => isset($_COOKIE[$session_name]) ? sanitize_text_field(wp_unslash($_COOKIE[$session_name])) : '',
                    'server_session_id' => $session_id,
                ]
            );

			if($send_response)
			{
				$this->sendResponseByType('failure', $warning);
			}

			return false;
		}

		if(session_status() === PHP_SESSION_NONE && defined('WC1C_RECEIVER_REQUEST') && WC1C_RECEIVER_REQUEST)
		{
			session_id($session_id);

			$this->core()->log()->info(esc_html__('PHP session none, restart PHP session.', 'wc1c-maincore'), ['session_id' => $session_id]);
			session_start();
		}

		return true;
	}

    /**
     * Cleaning the directory for temporary files.
     *
     * @return void
     */
    public function handlerCatalogDirectoryClean()
    {
        $directory = $this->core()->getUploadDirectory();

        $this->core()->log()->info(esc_html__('Cleaning the directory for temporary files.', 'wc1c-maincore'), ['directory' => $directory]);

        wc1c()->filesystem()->ensureDirectoryExists($directory);

        if(wc1c()->filesystem()->cleanDirectory($directory))
        {
            $this->core()->log()->info(esc_html__('The directory for temporary files was successfully cleared of old files.', 'wc1c-maincore'), ['directory' => $this->core()->getUploadDirectory()]);
        }
        else
        {
            $error = esc_html__('Failed to clear the temp directory of old files.', 'wc1c-maincore');

            $this->core()->log()->error($error, ['directory' => $directory]);
            $this->sendResponseByType('failure', $error);
        }
    }

	/**
	 * Init
	 */
	public function handlerCatalogModeInit()
	{
		$this->core()->log()->info(esc_html__('Initialization of receiving requests from 1C.', 'wc1c-maincore'));

		if(has_filter('wc1c_schema_productscleanercml_handler_catalog_mode_init_session'))
		{
			$_SESSION = apply_filters('wc1c_schema_productscleanercml_handler_catalog_mode_init_session', $_SESSION, $this);
		}

		$this->core()->log()->debug(esc_html__('Session for receiving requests.', 'wc1c-maincore'), ['session'=> $_SESSION]);

        $directory = $this->core()->getUploadDirectory();

        $this->core()->log()->info(esc_html__('Check the directory for temporary files.', 'wc1c-maincore'), ['directory' => $directory]);

        wc1c()->filesystem()->ensureDirectoryExists($directory);

        if(!wc1c()->filesystem()->isDirectory($directory))
        {
            $error = esc_html__('Failed to check the temp directory.', 'wc1c-maincore');

            $this->core()->log()->error($error, ['directory' => $directory]);

            $this->sendResponseByType('failure', $error);
        }
        else
        {
            $ht_name = $directory . '/.htaccess';
            if(!file_exists($ht_name))
            {
                $htaccess_content = "Options -Indexes\n" .
                    "<IfModule mod_authz_core.c>\n" .
                    "    Require all denied\n" .
                    "</IfModule>\n" .
                    "<IfModule !mod_authz_core.c>\n" .
                    "    Deny from all\n" .
                    "</IfModule>\n";

                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
                $fp = fopen($ht_name, 'wb');
                if($fp)
                {
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
                    fwrite($fp, $htaccess_content);
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
                    fclose($fp);
                }
            }
        }

		$data['zip'] = 'zip=no' . PHP_EOL;

		$max_size = $this->utilityConvertFileSize(wc1c()->environment()->get('php_post_max_size'));
		$max_wc1c = $this->utilityConvertFileSize(wc1c()->settings('main')->get('php_post_max_size'));
		$max_configuration = $this->utilityConvertFileSize($this->core()->getOptions('php_post_max_size'));

		$this->core()->log()->debug(esc_html__('The maximum size of accepted files from 1C is assigned:', 'wc1c-maincore') . ' ' . size_format($max_size));

		if($max_wc1c && $max_wc1c < $max_size)
		{
			$max_size = $max_wc1c;
			$this->core()->log()->debug(esc_html__('Based on the global settings of WC1C, the size of received files has been reduced from 1C to:', 'wc1c-maincore') . ' ' . size_format($max_size));
		}

		if($max_configuration && $max_configuration < $max_size)
		{
			$max_size = $max_configuration;
			$this->core()->log()->debug(esc_html__('Based on the configuration settings of WC1C, the size of received files has been reduced from 1C to:', 'wc1c-maincore') . ' ' . size_format($max_size));
		}

		$data['file_limit'] = 'file_limit=' . $max_size . PHP_EOL;

		if(has_filter('wc1c_schema_productscleanercml_handler_catalog_mode_init_data'))
		{
			$data = apply_filters('wc1c_schema_productscleanercml_handler_catalog_mode_init_data', $data, $this);
		}

		$this->core()->log()->debug(esc_html__('Print lines for 1C.', 'wc1c-maincore'), ['data' => $data]);

		foreach($data as $line_id => $line)
		{
			printf('%s', wp_kses_post($line));
		}
		exit;
	}

	/**
	 * Uploading files from 1C to a local directory
	 *
	 * @return void
     */
    public function handlerCatalogModeFile()
    {
        $upload_directory = $this->core()->getUploadDirectory() . DIRECTORY_SEPARATOR;

        if(has_filter('wc1c_schema_productscleanercml_handler_catalog_mode_file_directory'))
        {
            $upload_directory = apply_filters('wc1c_schema_productscleanercml_handler_catalog_mode_file_directory', $upload_directory);
        }

        $upload_directory = wp_normalize_path($upload_directory);

        wc1c()->filesystem()->ensureDirectoryExists($upload_directory);

        if(!wc1c()->filesystem()->exists($upload_directory))
        {
            $response_description = esc_html__('Directory is unavailable:', 'wc1c-maincore') . ' ' . $upload_directory;

            $this->core()->log()->error($response_description, ['directory' => $upload_directory]);
            $this->sendResponseByType('failure', $response_description);
        }

        $filename = wc1c()->getVar($_GET['filename'], '');

        if(has_filter('wc1c_schema_productscleanercml_handler_catalog_mode_file_filename'))
        {
            $filename = apply_filters('wc1c_schema_productscleanercml_handler_catalog_mode_file_filename', $filename);
        }

        if(empty($filename))
        {
            $response_description = esc_html__('Filename is empty.', 'wc1c-maincore');

            $this->core()->log()->error($response_description);
            $this->sendResponseByType('failure', $response_description);
        }

        if(strlen($filename) > 255)
        {
            $this->core()->log()->error(esc_html__('Filename is too long.', 'wc1c-maincore'), ['length' => strlen($filename)]);
            $this->sendResponseByType('failure', esc_html__('Filename is too long.', 'wc1c-maincore'));
        }

        if (strpos($filename, '..') !== false ||
            strpos($filename, './') !== false ||
            strpos($filename, '/.') !== false ||
            strpos($filename, '\\') !== false)
        {
            $this->core()->log()->error(esc_html__('Invalid filename: directory traversal detected.', 'wc1c-maincore'), ['filename' => $filename]);
            $this->sendResponseByType('failure', __('Invalid filename.', 'wc1c-maincore'));
        }

        if (in_array(strtolower($filename), ['.htaccess', '.htpasswd', 'web.config', 'php.ini'], true))
        {
            $this->core()->log()->error(esc_html__('Forbidden filename.', 'wc1c-maincore'), ['filename' => $filename]);
            $this->sendResponseByType('failure', esc_html__('Forbidden filename.', 'wc1c-maincore'));
        }

        $extension = wc1c()->filesystem()->extension($filename);

        if (empty($extension))
        {
            $this->core()->log()->error(esc_html__('File has no extension.', 'wc1c-maincore'), ['filename' => $filename]);
            $this->sendResponseByType('failure', esc_html__('File has no extension.', 'wc1c-maincore'));
        }

        $allowed_mimes = get_allowed_mime_types();
        $cml_mimes =
        [
            'xml'  => 'text/xml',
            'cml'  => 'text/xml',
            'zip'  => 'application/zip',
            'gz'   => 'application/gzip',
        ];

        // WordPress MIME + CommerceML MIME
        $allowed_mimes = array_merge($cml_mimes, $allowed_mimes);

        /**
         * Фильтр разрешенных MIME-типов для загрузки файлов через CommerceML
         *
         * Позволяет сторонним плагинам расширять список разрешенных расширений.
         *
         * @param array  $allowed_mimes Массив разрешенных MIME-типов [расширение => mime-type]
         * @param string $filename      Имя загружаемого файла
         * @param Core   $this->core()  Ядро схемы
         */
        $allowed_mimes = apply_filters
        (
            'wc1c_schema_productscleanercml_allowed_upload_mimes',
            $allowed_mimes,
            $filename,
            $this->core()
        );

        $is_allowed = false;
        $matched_mime = '';

        foreach ($allowed_mimes as $extensions => $mime)
        {
            // Ключ может содержать несколько расширений через | (например, 'jpg|jpeg|jpe')
            $exts = array_map('trim', explode('|', $extensions));
            if (in_array($extension, $exts, true)) {
                $is_allowed = true;
                $matched_mime = $mime;
                break;
            }
        }

        if (!$is_allowed)
        {
            $this->core()->log()->error
            (
                esc_html__('Invalid file extension. This type of file is not allowed for upload.', 'wc1c-maincore'),
                [
                    'filename'        => $filename,
                    'extension'       => $extension,
                    'allowed_count'   => count($allowed_mimes),
                ]
            );
            $this->sendResponseByType('failure', esc_html__('Invalid file extension. This type of file is not allowed for upload.', 'wc1c-maincore'));
        }

        $this->core()->log()->debug
        (
            esc_html__('File extension is allowed.', 'wc1c-maincore'),
            [
                'filename'  => $filename,
                'extension' => $extension,
                'mime_type' => $matched_mime,
            ]
        );

        $upload_file_path = wp_normalize_path($upload_directory . $filename);

        $this->core()->log()->info(esc_html__('Saving data to a file named:', 'wc1c-maincore') . ' ' . $filename, ['file_path' => $upload_file_path]);

        wc1c()->filesystem()->ensureDirectoryExists(dirname($upload_file_path));

        if(strpos($filename, 'import_files') !== false)
        {
            $response_description = esc_html__('The data is successfully delivery.', 'wc1c-maincore');

            $this->core()->log()->info($response_description,);
            $this->sendResponseByType('success', $response_description);
        }

        if(!wc1c()->filesystem()->isWritable($upload_directory))
        {
            $response_description = esc_html__('Directory is unavailable for write.', 'wc1c-maincore');
            $this->core()->log()->error($response_description, ['directory' => $upload_directory]);
            $this->sendResponseByType('failure', $response_description);
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $input_stream = fopen('php://input', 'rb');
        if(!$input_stream)
        {
            $response_description = esc_html__('Failed to open input stream. The request contains no data to write to the file.', 'wc1c-maincore');

            $this->core()->log()->error($response_description);
            $this->sendResponseByType('failure', $response_description);
        }

        $file_mode = wc1c()->filesystem()->exists($upload_file_path) ? 'ab' : 'wb';

        if(wc1c()->filesystem()->exists($upload_file_path))
        {
            $this->core()->log()->info(esc_html__('The file exists. Write a data to the end of an existing file.', 'wc1c-maincore'));
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $output_stream = fopen($upload_file_path, $file_mode);
        if(!$output_stream)
        {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
            fclose($input_stream);

            $response_description = esc_html__('Failed to open output file for writing.', 'wc1c-maincore');

            $this->core()->log()->error($response_description, ['file_path' => $upload_file_path]);
            $this->sendResponseByType('failure', $response_description);
        }

        $chunk_size = 8192; // 8 KB
        $total_size = 0;
        $chunks_count = 0;

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_feof
        while(!feof($input_stream))
        {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
            $chunk = fread($input_stream, $chunk_size);
            if($chunk === false)
            {
                break;
            }

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
            $written = fwrite($output_stream, $chunk);
            if($written === false)
            {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
                fclose($input_stream);
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
                fclose($output_stream);

                $response_description = esc_html__('Failed to write data to file.', 'wc1c-maincore');

                $this->core()->log()->error($response_description, ['file_path' => $upload_file_path]);
                $this->sendResponseByType('failure', $response_description);
            }

            $total_size += $written;
            $chunks_count++;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose($input_stream);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose($output_stream);

        if($total_size === 0)
        {
            $response_description = esc_html__('The request contains no data to write to the file. Retry the upload.', 'wc1c-maincore');

            $this->core()->log()->error($response_description);
            $this->sendResponseByType('failure', $response_description);
        }

        wc1c()->filesystem()->chmod($upload_file_path, 0755);

        $response_description = esc_html__('The data is successfully written to a file. Recorded data size:', 'wc1c-maincore') . ' ' . size_format($total_size);

        $this->core()->log()->info($response_description,
        [
            'file_size' => $total_size,
            'chunks_count' => $chunks_count,
            'memory_peak' => size_format(memory_get_peak_usage())
        ]);

        $this->sendResponseByType('success', $response_description);
    }

	/**
	 * Catalog import
	 */
	public function handlerCatalogModeImport()
	{
		$this->core()->log()->info(esc_html__('On request from 1C - started importing data from a file.', 'wc1c-maincore'));

		$filename = wc1c()->getVar($_GET['filename'], '');

		if($filename === '')
		{
			$response_description = esc_html__('1C sent an empty file name for data import.', 'wc1c-maincore');

            $this->core()->log()->warning($response_description);
			$this->sendResponseByType('failure', $response_description);
		}

		$file = wp_normalize_path($this->core()->getUploadDirectory() . DIRECTORY_SEPARATOR . $filename);

		if(!wc1c()->filesystem()->exists($file))
		{
			$response_description = esc_html__('File for import is not exists.', 'wc1c-maincore');

            $this->core()->log()->error($response_description);
			$this->sendResponseByType('success', $response_description);
		}

		try
		{
			$result_file_processing = $this->core()->fileProcessing($file);

			if($result_file_processing)
			{
				$response_description = esc_html__('Import of data from file completed successfully.', 'wc1c-maincore');

                $this->core()->log()->info($response_description, ['file_name' => $filename, 'file_path' => $file]);
				$this->sendResponseByType('success', $response_description);
			}
		}
		catch(\Throwable $e)
		{
			$response_description = esc_html__('Importing data from a file ended with an error:', 'wc1c-maincore') . ' ' . esc_html($e->getMessage());

            $this->core()->log()->error($response_description, ['exception' => $e]);
			$this->sendResponseByType('failure', $response_description);
		}

		$response_description = esc_html__('Importing data from a file ended with an error.', 'wc1c-maincore');

        $this->core()->log()->error($response_description);
		$this->sendResponseByType('failure', $response_description);
	}
}