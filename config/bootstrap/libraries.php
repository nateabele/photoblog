<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2010, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

use lithium\core\Libraries;

/**
 * Add the Lithium core library.  This sets default paths and initializes the autoloader.  You
 * generally should not need to override any settings.
 */
Libraries::add('lithium');

/**
 * Add the application.  You can pass a `'path'` key here if this bootstrap file is outside of
 * your main application, but generally you should not need to change any settings.
 */
Libraries::add('photoblog', array('default' => true));

/**
 * Add some plugins
 */
Libraries::add('li3_geo');

?>