<?php
/**
 * Name: SVG File Handler
 * Author: Shish <webmaster@shishnet.org>
 * Description: Handle SVG files
 */

class SVGFileHandler extends Extension {
	var $theme;

	public function receive_event($event) {
		if(is_null($this->theme)) $this->theme = get_theme_object("handle_svg", "SVGFileHandlerTheme");

		if(is_a($event, 'DataUploadEvent') && $event->type == "svg" && $this->check_contents($event->tmpname)) {
			$hash = $event->hash;
			$ha = substr($hash, 0, 2);
			if(!copy($event->tmpname, "images/$ha/$hash")) {
				$event->veto("SVG Handler failed to move file from uploads to archive");
				return;
			}
			send_event(new ThumbnailGenerationEvent($event->hash, $event->type));
			$image = $this->create_image_from_data("images/$ha/$hash", $event->metadata);
			if(is_null($image)) {
				$event->veto("SVG Handler failed to create image object from data");
				return;
			}
			send_event(new ImageAdditionEvent($event->user, $image));
		}

		if(is_a($event, 'ThumbnailGenerationEvent') && $event->type == "svg") {
			$hash = $event->hash;
			$ha = substr($hash, 0, 2);
			// FIXME: scale image, as not all boards use 192x192
			copy("ext/handle_svg/thumb.jpg", "thumbs/$ha/$hash");
		}

		if(is_a($event, 'DisplayingImageEvent') && $event->image->ext == "svg") {
			$this->theme->display_image($event->page, $event->image);
		}
		
		if(is_a($event, 'PageRequestEvent') && ($event->page_name == "get_svg")) {
			global $database;
			$id = int_escape($event->get_arg(0));
			$image = $database->get_image($id);
			$hash = $image->hash;
			$ha = substr($hash, 0, 2);
			
			$event->page->set_type("image/svg+xml");
			$event->page->set_mode("data");
			$event->page->set_data(file_get_contents("images/$ha/$hash"));
		}
	}

	private function create_image_from_data($filename, $metadata) {
		global $config;

		$image = new Image();

		// FIXME: ugh, xml parsing :|
		$image->width = 0;
		$image->height = 0;
		
		$image->filesize  = $metadata['size'];
		$image->hash      = $metadata['hash'];
		$image->filename  = $metadata['filename'];
		$image->ext       = $metadata['extension'];
		$image->tag_array = tag_explode($metadata['tags']);
		$image->source    = $metadata['source'];

		return $image;
	}

	private function check_contents($file) {
		// FIXME: magic header?
		return (file_exists($file));
	}
}
add_event_listener(new SVGFileHandler());
?>