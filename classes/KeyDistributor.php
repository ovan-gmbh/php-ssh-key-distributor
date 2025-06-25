<?php

use Symfony\Component\Yaml\Yaml;
use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;

class KeyDistributor
{
	protected
		$connection,
		$user;

	const
		ESCAPE_CHAR = "\033",
		COLOR_RED = '[31m',
		COLOR_GREEN = '[32m',
		COLOR_LIGHT_GRAY = '[37m',
		COLOR_LIGHT_YELLOW = '[93m',
		COLOR_GRAY = '[90m',
		COLOR_CLOSE_CHAR = '[0m',
		CHAR_OK = '✔',
		CHAR_FAIL = '✗',
		TIMEOUT = 10;

	static
		$title = [
			'______________________________[ make SSH key distribution easier ]______________________________',
			'',
			' _____ _____ _____    _____ _____ _____ _____         ____  _     _       _ _       _   ___',
			'|  _  |  |  |  _  |  |   __|   __|  |  |  |  |___ _ _|    \|_|___| |_ ___|_| |_ _ _| |_|   |___',
			'|   __|     |   __|  |__   |__   |     |    -| -_| | |  |  | |_ -|  _|  _| | . | | |  _| | |  _|',
			'|__|  |__|__|__|     |_____|_____|__|__|__|__|___|_  |____/|_|___|_| |_| |_|___|___|_| |___|_|',
			'                                                 |___|',
			'_______________________________________________________________________________[ -h for help ]__',
			'',
		],
		$colors = [
			self::COLOR_GRAY,
			self::COLOR_GRAY,
			self::COLOR_LIGHT_GRAY,
			self::COLOR_LIGHT_GRAY,
			self::COLOR_LIGHT_GRAY,
			self::COLOR_LIGHT_GRAY,
			self::COLOR_LIGHT_GRAY,
			self::COLOR_GRAY,
			self::COLOR_GRAY,
		],
		// call with `-`
		$short_options = [
			// without value
			'h' => 'Short for `help`',
			'd' => 'Short for `dry-run`',
			// with value
			'i:' => 'Short for `identity`',
			's:' => 'Short for `servers`',
			'k:' => 'Short for `keys`',
			'o:' => 'Short for `only-server`',
		],
		// call with `--`
		$long_options = [
			// without value
			'help' => 'This help screen :)',
			'dry-run' => 'To verify your configuration without actually distributing those changes',
			// with value
			'identity:' => 'Custom path to identity file, default `id_rsa`',
			'servers:' => 'Custom path to servers file, default `servers.yml`',
			'keys:' => 'Custom path to keys file, default `keys.yml`',
			'only-server:' => 'Distribute to one server only. Key is the field `comment` from the servers file.',
		];

	public function __construct($args)
	{
		$options = getopt(implode('', array_keys(self::$short_options)), array_keys(self::$long_options));

		print "\n";
		if (exec('tput cols') >= strlen(self::$title[0]))
		{
			$i = 0;
			foreach (self::$title as $line)
			{
				print self::ESCAPE_CHAR . self::$colors[$i] . $line . self::ESCAPE_CHAR . self::COLOR_CLOSE_CHAR . "\n";
				$i++;
			}
		}
		else
		{
			print
				self::ESCAPE_CHAR . self::COLOR_GRAY . '=====================' . "\n" .
				self::ESCAPE_CHAR . self::COLOR_LIGHT_GRAY . 'PHP SSHKeyDistribut0r' .
				self::ESCAPE_CHAR . self::COLOR_GRAY . "\n" . '=====================' . self::ESCAPE_CHAR . self::COLOR_CLOSE_CHAR . "\n";
		}

		// check parameter `h`
		if (array_key_exists('h', $options) || array_key_exists('help', $options))
		{
			$this->printHelp();
			return;
		}

		// check parameters `s` or `servers`
		if (array_key_exists('s', $options))
		{
			$servers_file = $options['s'];
		}
		else if (array_key_exists('servers', $options))
		{
			$servers_file = $options['servers'];
		}
		else // default
		{
			$servers_file = 'servers.yml';
		}
		if (is_array($servers_file) || !file_exists($servers_file))
		{
			$this->printErrorMessage('Servers file `' . $servers_file . '` not found');
			return;
		}

		// check parameters `k` or `keys`
		if (array_key_exists('k', $options))
		{
			$keys_file = $options['k'];
		}
		else if (array_key_exists('keys', $options))
		{
			$keys_file = $options['keys'];
		}
		else // default
		{
			$keys_file = 'keys.yml';
		}
		if (is_array($keys_file) || !file_exists($keys_file))
		{
			$this->printErrorMessage('Keys file `' . $keys_file . '` not found');
			return;
		}

		// check parameters `i` or `identity`
		if (array_key_exists('i', $options))
		{
			$identity_file = $options['i'];
		}
		else if (array_key_exists('identity', $options))
		{
			$identity_file = $options['identity'];
		}
		else // default
		{
			$identity_file = 'id_rsa';
		}
		if (is_array($identity_file) || !file_exists($identity_file))
		{
			$this->printErrorMessage('Identity file `' . $identity_file . '` not found');
			return;
		}

		// check parameters `o` or `only-server`
		if (array_key_exists('o', $options))
		{
			$only_server = $options['o'];
		}
		else if (array_key_exists('only-server', $options))
		{
			$only_server = $options['only-server'];
		}
		else
		{
			$only_server = false;
		}

		$servers = Yaml::parseFile($servers_file);
		$keys = Yaml::parseFile($keys_file);
		$dry_run = array_key_exists('d', $options) || array_key_exists('dry-run', $options);
		if ($dry_run)
		{
			$this->printMessage('This is a dry run which will not write the `authorized_keys` file to the servers.');
		}

		foreach ($servers as $server)
		{
			if ($only_server !== false && $server['comment'] !== $only_server)
			{
				continue;
			}
			$server_keys = [];
			$server_users = [];
			if (array_key_exists('authorized_users', $server))
			{
				foreach ($server['authorized_users'] as $server_user)
				{
					$server_users[] = $server_user;
				}
				foreach ($server_users as $server_user)
				{
					if (array_key_exists('keys', $keys[$server_user]))
					{
						foreach ($keys[$server_user]['keys'] as $key)
						{
							if (!in_array($key, $server_keys))
							{
								$server_keys[] = $key;
							}
						}
					}
				}
			}
			$privateKey = PublicKeyLoader::loadPrivateKey(file_get_contents($identity_file));
			$sftp = new SFTP($server['ip'], $server['port']);
			$sftp->setTimeout(self::TIMEOUT);

			$success = null;
			if (!$sftp->login($server['user'], $privateKey))
			{
				$success = false;
			}

			if (!$dry_run && $success === null)
			{
				file_put_contents('authorized_keys', implode("\n", $server_keys) . "\n");
				try
				{
					$sftp->put('~/.ssh/authorized_keys', file_get_contents('authorized_keys'));
					$success = true;
				}
				catch (Exception $e)
				{
					$success = false;
				}

				unlink('authorized_keys');
			}
			else
			{
				$scp_client = $builder->buildClient();
				try
				{
					$result = $scp_client->exec(
						commandArguments: ['echo ', 'yes'],
						timeout: self::TIMEOUT,
					)->getOutput();
					$success = $result === 'yes' . "\n";
				}
				catch (Exception $e)
				{
					$success = false;
				}
			}

			printf(
				'%4$s%7$s %8$s@%2$s (%3$s) - %6$s%1$s%5$s',
				"\n", // 1
				$server['ip'], // 2
				$server['comment'], // 3
				self::ESCAPE_CHAR . ($success ? self::COLOR_GREEN : self::COLOR_RED), // 4
				self::ESCAPE_CHAR . self::COLOR_CLOSE_CHAR, // 5
				$success ? 'Access granted for: ' . implode(', ', $server['authorized_users']) : 'Error: Cannot connect to server.', // 6
				$success ? self::CHAR_OK : self::CHAR_FAIL, // 7
				$server['user'], // 8
			);
		}
	}

	private function printHelp()
	{
		print 'Usage:' . "\n";
		foreach (array_merge(self::$short_options, [null], self::$long_options) as $key => $value)
		{
			if ($value === null)
			{
				print "\n";
				continue;
			}
			$key = str_replace(':', '', $key);
			print (strlen($key) === 1 ? '-' : '--') . $key . ': ' . $value . "\n";
		}
		print "\n";
	}

	private function printMessage($msg)
	{
		print self::ESCAPE_CHAR . self::COLOR_LIGHT_YELLOW . self::CHAR_OK . ' ' . $msg . self::ESCAPE_CHAR . self::COLOR_CLOSE_CHAR . "\n";
	}

	private function printErrorMessage($msg)
	{
		print self::ESCAPE_CHAR . self::COLOR_LIGHT_GRAY . self::CHAR_FAIL . ' ' . $msg . self::ESCAPE_CHAR . self::COLOR_CLOSE_CHAR . "\n";
	}
}