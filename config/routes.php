<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2010, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

use lithium\net\http\Router;
use lithium\core\Environment;
use lithium\action\Response;
use photoblog\models\Photo;

Router::connect('/photos/view/{:id:[0-9a-f]{24}}.jpg', array(), function($request) {
	return new Response(array(
		'headers' => array('Content-type' => 'image/jpeg'),
		'body' => Photo::first($request->id)->file->getBytes()
	));
});

/**
 * Here, we are connecting '/' (base path) to controller called 'Pages',
 * its action called 'view', and we pass a param to select the view file
 * to use (in this case, /app/views/pages/home.html.php)...
 */
Router::connect('/', 'Photos::index');

/**
 * ...and connect the rest of 'Pages' controller's urls.
 */
Router::connect('/pages/{:args}', array('controller' => 'pages', 'action' => 'view'));

/**
 * Connect the testing routes.
 */
if (!Environment::is('production')) {
	Router::connect('/test/{:args}', array('controller' => '\lithium\test\Controller'));
	Router::connect('/test', array('controller' => '\lithium\test\Controller'));
}

/**
 * Finally, connect the default routes.
 */
Router::connect('/{:controller}/{:action}/{:id:[0-9a-f]{24}}.{:type}', array('id' => null));
Router::connect('/{:controller}/{:action}/{:id:[0-9a-f]{24}}');
Router::connect('/{:controller}/{:action}/{:args}');

?>