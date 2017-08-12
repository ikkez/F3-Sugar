<?php
/**
 *	Image TagHandler
 *
 *	The contents of this file are subject to the terms of the GNU General
 *	Public License Version 3.0. You may not use this file except in
 *	compliance with the license. Any of the license terms and conditions
 *	can be waived if you get permission from the copyright holder.
 *
 *	Copyright (c) 2015 ~ ikkez
 *	Christian Knuth <ikkez0n3@gmail.com>
 *
 *	@version: 0.5.0
 *	@date: 05.11.2015
 *
 **/

namespace Template\Tags;

class Image extends \Template\TagHandler {

	function __construct() {
		/** @var \Base $f3 */
		$f3 = \Base::instance();
		if (!$f3->exists('template.image.tempDir'))
			$f3->set('template.image.tempDir',$f3->get('TEMP').'img/');
		parent::__construct();
	}

	/**
	 * build tag string
	 * @param $attr
	 * @param $content
	 * @return string
	 */
	function build($attr, $content) {

		if (isset($attr['src']) && (isset($attr['width'])||isset($attr['height']))) {
			$opt = array(
				'width'=>null,
				'height'=>null,
				'crop'=>false,
				'enlarge'=>false,
				'quality'=>75,
			);
			// merge into defaults
			$opt = array_intersect_key($attr + $opt, $opt);
			// get dynamic path
			$path = preg_match('/{{(.+?)}}/s',$attr['src']) ?
					$this->tmpl->token($attr['src']) : var_export($attr['src'],true);
			// clean up attributes
			$attr=array_diff_key($attr,$opt);
			$opt = var_export($opt,true);
			unset($attr['src']);
			$out='<img src="<?php echo \Template\Tags\Image::instance()->resize('.
				$path.','.$opt.');?>"'.$this->resolveParams($attr).' />';
		} else
			// just forward / bypass further processing
			$out = '<img'.$this->resolveParams($attr).' />';

		return $out;
	}

	/**
	 * on demand image resize
	 * @param $path
	 * @param $opt
	 * @return string
	 */
	function resize($path,$opt) {
		$f3 = \Base::instance();
		$hash = $f3->hash($path.$f3->serialize($opt));
		// TODO: file ext
		$new_file_name = $hash.'.jpg';
		$dst_path = $f3->get('template.image.tempDir');
		$path = explode('/', $path);
		$file = array_pop($path);
		$src_path = implode('/',$path).'/';
		if (!is_dir($dst_path))
			mkdir($dst_path,0775,true);
		if (!file_exists($dst_path.$new_file_name)) {
			if (file_exists($src_path.$file))
				$imgObj = new \Image($file, false, $src_path);
			elseif ($f3->exists('template.image.not_found',$nfPath))
				$imgObj = new \Image($nfPath, false);
			else
				return 'http://placehold.it/250x250?text=Not+Found';
			$ow = $imgObj->width();
			$oh = $imgObj->height();
			if (!$opt['width'])
				$opt['width'] = round(($opt['height']/$oh)*$ow);
			if (!$opt['height'])
				$opt['height'] = round(($opt['width']/$ow)*$oh);
			$imgObj->resize((int)$opt['width'], (int)$opt['height'], $opt['crop'], $opt['enlarge']);
			// TODO: file ext
			$file_data = $imgObj->dump('jpeg', $opt['quality']);
			$f3->write($dst_path.$new_file_name, $file_data);
		}
		return $dst_path.$new_file_name;
	}
}