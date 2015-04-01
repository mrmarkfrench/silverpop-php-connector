# silverpop-php-connector

A connector SDK library for applications integrating with Silverpop, including the Universal Behavior API.

## Latest Version

The latest version is 1.2.2, and can be found in the version_1 branch. If you'd prefer the latest ongoing development version, it's in the master branch.

## Installation

You can install using [composer](#composer) or from [source](#source). 

### Composer

If you don't have Composer [install](http://getcomposer.org/doc/00-intro.md#installation) it:
```
$ curl -s https://getcomposer.org/installer | php
```
Add this to your `composer.json`: 
```
{
	"require": {
		"mrmarkfrench/silverpop-php-connector": "*"
	}
}
```
Refresh your dependencies:

	$ php composer.phar update
	

Then make sure to `require` the autoloader:
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');
...
```
### Source

Download the silverpop-php-connector source:
```
$ git clone https://github.com/mrmarkfrench/silverpop-php-connector
```
And then `require` all bootstrap files:
```php
<?php
require_once "vendor/autoload.php";
...
```
## Quickstart
```
curl -s http://getcomposer.org/installer | php

echo '{
	"require": {
		"mrmarkfrench/silverpop-php-connector": "*"
	}
}' > composer.json

php composer.phar install

curl https://raw.githubusercontent.com/mrmarkfrench/silverpop-php-connector/master/apiTest.php > apiTest.php

# Replace indicated values with your own credentials
echo '[silverpop]
baseUrl       = "http://api.pilot.silverpop.com"
client_id     = "YOUR_CLIENT_ID"
client_secret = "YOUR_CLIENT_SECRET"
refresh_token = "YOUR_REFRESH_TOKEN"
username      = "YOUR_USERNAME"
password      = "YOUR_PASSWORD"
notify_email  = "EMAIL_ADDRESS_TO_NOTIFY"
[sftp_config]
sftpUrl       = "YOUR_SFTP_URL.silverpop.com"
mail_from     = "YOUR_EMAIL_FROM"
mail_to       = "SEND_EMAIL_TO"
mail_cc       = ""
mail_bcc      = ""' > authData.ini

php apiTest.php
```

## Contributions and Adding Functionality

The SDK currently supports only a subset of the API endpoints offered by Silverpop. New endpoints are added as the primary author's projects need them, but you are welcome to add your own! If your project requires an endpoint not yet supported, please feel free to fork this repository and add functions as necessary. The existing functions should give you a solid framework to build on, and your pull requests are welcome.

1. Fork it
2. Create your feature branch (`git checkout -b my-new-feature`)
3. Commit your changes (`git commit -am 'Add some feature'`)
4. Push to the branch (`git push origin my-new-feature`)
5. Create new Pull Request
