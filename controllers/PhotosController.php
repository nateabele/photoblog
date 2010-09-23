<?php

namespace photoblog\controllers;

use photoblog\models\Photo;
use li3_geo\extensions\Geocoder;

class PhotosController extends \lithium\action\Controller {

	public function index($tags = null) {
		$conditions = $tags ? compact('tags') : array();
		$photos = Photo::all(compact('conditions'));
		return compact('photos');
	}

	public function view() {
		$photo = Photo::first($this->request->id);
		return compact('photo');
	}

	public function near($place = null) {
		$this->_render['template'] = 'index';
		$coords = Geocoder::find('google', $place);

		$photos = Photo::within(array($coords, $coords), array('limit' => 1));
		return compact('photos');
	}

	public function add() {
		$photo = Photo::create();

		if (($this->request->data) && $photo->save($this->request->data)) {
			$this->redirect(array('Photos::view', 'id' => $photo->_id));
		}
		$this->_render['template'] = 'edit';
		return compact('photo');
	}

	public function edit() {
		$photo = Photo::find($this->request->id);

		if (!$photo) {
			$this->redirect('Photos::index');
		}
		if (($this->request->data) && $photo->save($this->request->data)) {
			$this->redirect(array('Photos::view', 'id' => $photo->_id));
		}
		return compact('photo');
	}
}

?>