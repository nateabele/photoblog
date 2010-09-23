<?php if (!count($photos)): ?>
	<em>No photos</em>. <?=$this->html->link('Add one', 'Photos::add'); ?>.
<?php endif ?>

<?php foreach ($photos as $photo): ?>
	<?=$this->html->image("/photos/view/{$photo->_id}.jpg", array('width'=> 100)); ?>
	<?=$this->html->link($photo->title, array('Photos::view', 'id' => $photo->_id)); ?>
<?php endforeach ?>