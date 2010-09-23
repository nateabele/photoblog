<?=$this->form->create($photo, array('type' => 'file')); ?>
	<?=$this->form->field('title'); ?>
	<?=$this->form->field('description'); ?>
	<?php if (!$photo->exists()) { ?>
		<?=$this->form->field('file', array('type' => 'file')); ?>
	<?php } ?>
	<?=$this->form->field('tags', array('label' => 'Add tags separated by commas')); ?>
	<?=$this->form->submit('Save'); ?>
<?=$this->form->end(); ?>