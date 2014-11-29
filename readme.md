## Building a Photo Blog with Lithium and MongoDB

This tutorial will demonstrate how to build a simple photo blog with the Lithium PHP framework, using [ MongoDB](http://mongodb.org/) and [ GridFS](http://www.mongodb.org/display/DOCS/GridFS) as a storage backend. GridFS is a protocol for storing filesystem objects in MongoDB collections. 

Additionally, this tutorial will explore Mongo's geospatial features, using [the `li3_geo` plugin](http://dev.lithify.me/li3_geo) to extract location data from photos and simplify the use of Mongo's geo-indexing and search.

This tutorial assumes you have Lithium and MongoDB installed (as well as the [Mongo PECL extension](http://pecl.php.net/package/mongo)), and that the `li3` command [is in your system path](http://lithify.me/docs/lithium/console).

### Setting up

To begin, open your system console and change to your web server's document root, then run the following:
{{{
li3 library extract photoblog
}}}

Then, by pointing your browser to `http://localhost/photoblog` (or wherever you extracted the new app), you should see something like the following:

![New application screen](http://img.skitch.com/20100617-byk75fr6h21pud3efhrxjqmwqb.jpg)

Next, we'll correct the errors noted in the startup screen. Run the following to set the correct permissions on the `resources/` directory:

{{{ chmod -R 777 resources/ }}}

Keep in mind, however, that this is just for local development. When deploying to production, you're going to want to tune your permissions to be more specific to what your application is doing. Follow the [principle of least privilege](http://en.wikipedia.org/wiki/Principle_of_least_privilege).

Finally, find the following in `config/bootstrap.php`:
{{{
/**
 * Include this file if your application uses a database connection.
 */
// require __DIR__ . '/bootstrap/connections.php';
}}}

Uncomment the `require` statement, and open `config/connections.php`. Delete any existing code, and add the following:
{{{ embed:config/bootstrap/connections.php::9-14 }}}

Note that as long as MongoDB is running on `localhost` on the default port, no other settings are required. Also note that the `photoblog` database need not be explicitly created, as Mongo will connect to it and create the database automatically.

When reloaded, our page should now look something like this:

![New application screen, updated](http://img.skitch.com/20100618-t1c9wn3aw3uebb65gmj9nf8gqk.jpg)

### Generating stub classes

Next, we'll create some application files in which to house our logic. In your console, change into the `photoblog` directory, and enter the following:
{{{
li3 create Photos
}}}

You should then see this output:

{{{
Photo created in app\models.
PhotosController created in app\controllers.
PhotoTest created in app\tests\cases\models.
PhotosControllerTest created in app\tests\cases\controllers.
}}}

You can then browse to `http://path/to/app/photos`, and assuming you have PHP configured to display errors, you should see an exception error message because Lithium was unable to find a template.

The URL requested is accessing the `index()` action of the `PhotosController` class, which looks like this:

{{{
$photos = Photo::all();
return compact('photos');
}}}

We're simply querying the `Photo` model for all photos, and sending that variable to the templating layer as the `$photos` variable.

### Building templates

To remove the error, go into the `views` directory, and create the file `views/photos/index.html.php`. Since there are no photos yet, add a simple check that displays a message, and a link to add a photo, as follows:

{{{ embed:views/photos/index.html.php::1-3 }}}

Refreshing the page shows our template with no photos. Clicking the link to add one will navigate us to `PhotosController::add()`, which will again render an error page, because we haven't created a template for it. Since we know our add and edit templates will be almost the same, we can save ourselves a bit of work and create one for both actions. First, create `views/photos/edit.html.php`
(leaving it empty for now is fine), then open `controllers/PhotosController.php` and look for the
`add()` method, which should look like
this:

{{{
public function add() {
	$photo = Photo::create();

	if (($this->request->data) && $photo->save($this->request->data)) {
		$this->redirect(array('Photos::view', 'args' => array($photo->id)));
	}
	return compact('photo');
}
}}}

Right before the `return` statement, add the following line:

{{{ embed:photoblog\controllers\PhotosController::add(6-6) }}}

This tells the templating layer to use the template for the `edit()` action, rather than looking for the default `add` template. Refreshing the page should show us a blank screen, with just the headers from the layout. Also, while we're in here, we can change the IDs to Mongo IDs in the calls to `redirect()`:

{{{ embed:photoblog\controllers\PhotosController::edit(7-7) }}}

Also, we can modify the matching patterns of the default routes in `config/routes.php` to accept MongoDB IDs instead of standard integers. At the bottom of the file are a series of catch-all routes that look like this:

{{{
Router::connect('/{:controller}/{:action}/{:id:[0-9]+}.{:type}', array('id' => null));
Router::connect('/{:controller}/{:action}/{:id:[0-9]+}');
Router::connect('/{:controller}/{:action}/{:args}');
}}}

Since route expressions are just regular expressions with a macro syntax for capture groups, we can just change the matching pattern for the `{:id}` group:

{{{ embed:config/routes.php::44-46 }}}

Getting back to our edit template, we start by creating a form, using the `$photo` object to bind to it. Binding `$photo` to the `Form` helper allows the `Form` helper to use `$photo` to automatically display certain things like error messages, and to make decisions about rendering certain input elements. Also, because we'll be using this form to upload photos, set the `'type'` to `'file'` in the `$options` parameter:

{{{
<?=$this->form->create($photo, array('type' => 'file')); ?>

<?=$this->form->end(); ?>
}}}

Next, we'll include some fields on the form: a title, and a lengthier description. Also, we'll include a photo upload field, but only when we're creating a new photo:

{{{
<?=$this->form->create($photo, array('type' => 'file')); ?>
	<?=$this->form->field('title'); ?>
	<?=$this->form->field('description'); ?>
	<?php if (!$photo->exists()) { ?>
		<?=$this->form->field('file', array('type' => 'file')); ?>
	<?php } ?>
	<?=$this->form->submit('Save'); ?>
<?=$this->form->end(); ?>
}}}

The `field()` method will render a form input, a corresponding `<label />`, and a wrapper `<div />`, as well as any validation errors, if the `$photo` object failed a save operation. For information on customizing the markup generated, see the [`Form` helper API](http://lithify.me/docs/lithium/template/helper/Form). The conditional check for `!$photo->exists()` allows us to use the template for both add and edit forms. When we refresh the page, we should see a form that looks about like this:


![Photo edit form](http://img.skitch.com/20100916-x1dhd993ag3qcnh9sg51nxw2gn.jpg)

Before we create any photos, we'll need to do a couple things. First, we need to make some configuration changes to the `Photo` model. We want our uploaded photos to be stored in GridFS. MongoDB uses a specific collection for GridFS storage called `fs.files`, and we need to tell the `Photo` model to use this, by adding the following line to the class definition:

{{{ embed:models/Photo.php::12-12 }}}

By convention, Lithium stores GridFS file data in the `file` field. Since that's the name of the form field we're using for the photo upload, [Lithium's `MongoDb` database adapter](http://lithify.me/docs/lithium/data/source/MongoDb) will handle it automatically.

Second, we'll implement some way to view them once they're saved. `PhotosController::view()` already implements the logic to get the photo from MongoDB, but there's no template for it. We'll go ahead and create `views/photos/view.html.php` and add the following code:

{{{ embed:views/photos/view.html.php::1-2 }}}

This displays the details about the photo, but it doesn't actually display it. For that, we need to call the `image()` helper method, and point it at a URL within our application:

{{{ embed:views/photos/view.html.php::9-9 }}}

If we look at the view page now, we'll see that this simply displays a broken image: we haven't wired our application up to serve it yet. There are a few different ways to do this in Lithium. The first approach we'll take demonstrates the best re-use of code, and the second way demonstrates how to tune requests to be very efficient, and shows how you can take control over Lithium's dispatch cycle.

### Configuring media handlers

Start by opening `config/bootstrap.php`, finding the `require` statement for `config/bootstrap/media.php` and uncommenting it. Looking at `media.php`, we can see that it already includes some code to handle media type conversions, and serving static assets from plugins. You can refer the comments for more information on each of these things, but for now we're going to add some code that tells our application how to serve JPEG images. One of the classes that `media.php` imports is `lithium\net\http\Media`. [Among other things](http://lithify.me/docs/lithium/net/http/Media), this class is responsible for mapping type names (typically file extensions) to HTTP content types, and maintaining instructions for type-conversion and content rendering. We can add the following at the bottom of `media.php` to tell it how to render JPEG content:

{{{ embed:config/bootstrap/media.php::58-60 }}}

Within this small block of code, there's quite a bit going on: the first parameter indicates the type name of the content we're mapping. Looking back at our routes, we can see that one of them has a `{:type}` key in the template string. This key has a special meaning in routing parameters, and [the `Controller` class](http://lithify.me/docs/lithium/action/Controller) uses it to tell `Media` how to render the content being passed to it.

The second parameter is the HTTP content-type that the content should be served with. Because the `Media` class works bi-directionally (it is used within in the framework to detect and decode as well as serve), This parameter can either be a string (a single content-type), or an array, if a single type name maps to multiple HTTP content-types. For example, you could pass `array('image/jpeg', 'image/jpg')`, then both content-types would be recognized as `jpg`. Because `'image/jpeg'` appears first in the list, it is considered "primary", and will be the content-type used whenever `jpg`-type content is served. In addtition to handler functions, types can also be assigned templating classes with template search paths. See the [API documentation for `Media::type()`](http://lithify.me/docs/lithium/net/http/Media::type) for more information.

The third parameter is an array of handler options that `Media` uses to encode and decode JPEG image data. Since we don't care about decoding, this configuration only contains encoding settings. The first setting, `'cast' => false`, tells `Media` _not_ to automatically convert the data from the controller into an array. By default, `'cast'` is set to `true` so that the controller data may be easily converted using internal PHP functions like `json_encode()`. (For example, the settings for the `json` type are `'cast' => true` and `'encode' => 'json_encode`. Because of `'cast'`, it doesn't require a custom `'encode'` function.)

Finally, the `'encode'` flag contains a custom function that exports the correct data for the type. This can be any valid PHP callable, including a class that implements [the `__invoke()` method](http://www.php.net/manual/en/language.oop5.magic.php#language.oop5.magic.invoke). This function takes the array of data returned from the controller as the first parameter (`$data`), and returns the body content of the response.

Within the handler itself, we can refer to the `$photo` object returned from `PhotosController::view()` as `$data['photo']`. Keep in mind that if we were writing this as a more general handler (i.e. one that would render JPEG images for _any_ controller, as opposed to just `PhotosController`), we'd want to designate a convention for how to choose which controller variable to render image data from.

When the `$photo` object is returned from the `MongoDb` adapter, the `file` field is [an instance of `MongoGridFSFile`](http://www.php.net/manual/en/class.mongogridfsfile.php), which contains methods for working with the file's data. We're using [the `getBytes()` method](http://www.php.net/manual/en/mongogridfsfile.getbytes.php) to return the raw bytes of the file from the handler function.

Under the hood, the `Media` class takes the handler's response, and assigns it to the body of [the `Response` object](http://lithify.me/docs/lithium/action/Response), and configures that object with the correct content type headers, which we previously set up in our call to `Media::type()`. The `Response` object is then returned to [the `Dispatcher`](http://lithify.me/docs/lithium/action/Dispatcher) which outputs it, thus completing the request/response cycle of the framework.

### Configuring route handlers

Now, when we go back to refresh the view page, the image is correctly displayed below the title and description. While this does what we want, it's not necessarily the most efficient way to do it. Suppose we've determined that our application will only ever serve image data from the `PhotosController`.

If that's the case, we don't need a generalized media handler, nor do we need to traverse through the entire framework dispatch cycle, since we're doing something very purpose-specific which doesn't require a lot of logic.

Instead, we'll add a route at the top of `config/routes.php`, which will intercept requests for photos, and attach a handler to it:

{{{ embed:config/routes.php::11-19 }}}

First, the `Photo` model is imported so that we can use it in the handler (note that if you're following this tutorial and used the console tooling to generate your classes, the correct namespace may be `app\models` instead).

Then, we create a route template that matches the same pattern as the one we're using to serve photos now. Because the route is only doing one thing and doesn't need to be generic, it doesn't include `{:action}`, `{:controller}` or `{:type}` keys; it only captures Mongo ID of the image to serve.

Finally, the third parameter is the _route handler_. It accepts an instance of [the `Request` class](http://lithify.me/docs/lithium/action/Request), and returns an instance of `Response`. Here, we're creating the `Response` by manually assigning the headers and body. For further optimization, we could manually assign caching headers and the like, so that each browser/client would only request the image once.

Using the `Request` object, we can get the value of the `{:id}` route parameter just like we would in the controller, using `$request->id`. Using the ID, we'll query the correct photo object, and just like in the media handler, use the `getBytes()` method of the `file` field.

Route handlers are a highly efficient way of dealing with certain types of requests, since they provides very direct control over the framework's dispatch cycle. When dispatching photos, the route we've defined is the first matching route, which causes the handler to immediately execute, bypassing any operations the framework would otherwise perform.

### Listing and linking

Now that we have a few photos saved and we're able to view them, let's go back to the listing page so we can see them all. Going back to `views/photos/index.html.php`, let's add the following:

{{{
<?php foreach ($photos as $photo): ?>
	<?=$this->html->link($photo->title, array(
		'controller' => 'photos', 'action' => 'view', 'id' => $photo->_id
	)); ?>
<?php endforeach ?>
}}}

Here, we're iterating over the photo objects and adding a link to the view page for each one. This works fine, but it'd be nice if we could see each photo on the listing page. Also, the URL syntax is a little verbose. Let's try to tighten that up a bit:

{{{ embed:views/photos/index.html.php::5-8 }}}

Just like the view page, we'll use the `image()` helper method to link to the image URL being served from our application, but we'll also pass it a width so that the image doesn't take over the whole page. Also, we can see that using the short-hand `'Controller::action'` syntax saves us a little bit of typing in the URL definition.

### Adding tagging

Since we want an easy way to organize our photos, we'll implement tagging. Fortunately, Lithium and Mongo make this pretty easy. First, we'll update our add/edit template to allow us to add tags to the photos in the first place. We'll add a new field called `tags` with a descriptive label explaining how to edit it, right above the Save button. The entire code listing for `views/photos/edit.html.php` should now appear as follows:

{{{ embed:views/photos/edit.html.php::1-10 }}}

Now that we can add tag data to photos, the next step is to update the `Photo` model to convert the comma-separated string to an array whenever a new photo is created or edited. To do this, we can override the `save()` method of the model, the signature for which is as follows:

{{{ embed:photoblog\models\Photo::save(0-0) }}}

This requires a bit of explanation: when writing code like `$photo = Photo::create()`, one might think `$photo` is an instance of the `Photo` model, when it's actually an instance of [the `Document` class](http://lithify.me/docs/lithium/data/entity/Document), which is bound to the `Photo` model. Because the `Document` class proxies its method calls back to the model it's bound to, calling `$photo->save($this->request->data)` (as in `PhotosController::edit()`) actually results in a call to `Photo::save()`, where `$entity` is the `$photo` object, and `$data` is `$this->request->data`. The first thing to do is to make sure any data being passed to `save()` is assigned to the object we're working with before we continue:

{{{ embed:photoblog\models\Photo::save(1-3) }}}

Next, we'll check to see if the `tags` property is defined, and if so, we'll make sure it's not already an array. If these conditions are met, then we'll treat `tags` as a comma-separated string and split it, while trimming any extra whitespace. Since MongoDB is able to store arrays natively, we don't need to do anything with them.

{{{ embed:photoblog\models\Photo::save(9-12) }}}

Finally, we'll call the parent method and return the result. Note that we don't need to pass `$data` on, since we already assigned it to the object being saved.

Last but not least, we'll include an edit link on the photo view page, so we can go back to each photo and add some tags. We can add this link to `views/photos/view.html.php`, right below the title and description:

{{{ embed:views/photos/view.html.php::1-3 }}}

Now we can browse through our photos and add tags.

### Querying by tags

Once we've tagged our photos, we can implement a way to filter on them. Let's start with the display. In `views/photos/view.html.php`, we can add a loop that will display each of our tags with a link:

{{{ embed:views/photos/view.html.php::5-7 }}}

You'll notice that these links use the `'args'` key. This is a special key that matches any number of route parameters. When a request is dispatched, these caputred values are passed as parameters to the called action. In this case, we'll be passing the name of the tag we want to filter on to the `index()` method of `PhotosController`. Alternatively, we could create a separate route for tag URLs, but we'll keep it simple.

For now, the links take us to `PhotosController::index()`, but no filtering is happening yet. We first need to change the method signature of `index()` in order to capture the parameter:

{{{ embed:photoblog\controllers\PhotosController::index(0-0) }}}

When working with passed arguments, always be sure to specify a default value, since passed arguments are optional. Next, we'll use the value of `$tag` (if present) to match against the `tag` field of the photo documents. Because MongoDB is smart about matching values in arrays, we don't need any extra querying syntax. The entire method listing should appear as follows:

{{{ embed:photoblog\controllers\PhotosController::index(1-3) }}}

Refreshing the index page or clicking on a tag link from the view page will now show us a tag-filtered photo set.

### Adding location and geo-indexing

Let's say we also want our photo blog to track where each photo came from. Since many cameras embed geo-location data in photos, we can extract it on upload and store it in MongoDB. Fortunately, MongoDB includes built-in facilities for indexing and querying on geospatial data.
