<h1><?=$photo->title; ?></h1>
<p><?=$photo->description; ?></p>
<p><?=$this->html->link('Edit', array('Photos::edit', 'id' => $photo->_id)); ?></p>

<?php foreach ($photo->tags as $tag): ?>
	<?=$this->html->link($tag, array('Photos::index', 'args' => array($tag))); ?>
<?php endforeach ?>

<?=$this->html->image("/photos/view/{$photo->_id}.jpg", array('alt' => $photo->title)); ?>