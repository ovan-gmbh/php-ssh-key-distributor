# PHP SSHKeyDistributor

	______________________________[ make SSH key distribution easier ]______________________________
	
	 _____ _____ _____    _____ _____ _____ _____         ____  _     _       _ _       _   ___
	|  _  |  |  |  _  |  |   __|   __|  |  |  |  |___ _ _|    \|_|___| |_ ___|_| |_ _ _| |_|   |___
	|   __|     |   __|  |__   |__   |     |    -| -_| | |  |  | |_ -|  _|  _| | . | | |  _| | |  _|
	|__|  |__|__|__|     |_____|_____|__|__|__|__|___|_  |____/|_|___|_| |_| |_|___|___|_| |___|_|
	                                                 |___|
	___________________________________________________________________________________[ by OVAN ]__


Inspiration from SSHKeyDistribut0r https://github.com/thomai/SSHKeyDistribut0r

## Install

- Add/remove/update servers by editing `servers.yml` (see `example.servers.yml`)
- Add/remove/update users by editing `keys.yml` (see `example.keys.yml`)
- Copy the private key into repository: `cp ~/.ssh/id_rsa .`
- Copy the public key into repository: `cp ~/.ssh/id_rsa.pub .`
- Run `composer install` (see https://getcomposer.org/)

### DDEV
- Run `ddev start`
- Run `ddev composer install`

## Usage

- Run `php distribute.php -h` or `php distribute.php --help` to get the help screen
- Run `php distribute.php`
- To update e.g. only one project in a `servers.yml` file use `php distribute.php -o "My 2nd Server"` (use the `comment` property from the `servers.yml`)
