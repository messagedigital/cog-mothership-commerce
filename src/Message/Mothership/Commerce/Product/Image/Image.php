<?php

namespace Message\Mothership\Commerce\Product\Image;

use Message\Cog\ImageResize\ResizableInterface;
use Message\Cog\ValueObject\Authorship;
use Message\Mothership\FileManager\File\File;

use Exception;

class Image implements ResizableInterface
{
	public $id;
	public $authorship;
	public $type;
	public $locale;
	public $file;
	public $fileID;
	public $options = array();

	public $product;

	protected $_fileLoader;

	public function __construct()
	{
		$this->authorship = new Authorship;

		$this->authorship
			->disableUpdate()
			->disableDelete();
	}

	public function getUrl()
	{
		if (! $this->file or ! $this->file instanceof File) {
			return "";
			// throw new Exception(sprintf("No file set for image with id #%s", $this->id));
		}

		return $this->file->getUrl();
	}

	public function getAltText()
	{
		if (! $this->file or ! $this->file instanceof File) {
			return "";
			// throw new Exception(sprintf("No file set for image with id #%s", $this->id));
		}

		return $this->file->getAltText();
	}

	public function setFileLoader($fileLoader)
	{
		$this->_fileLoader = $fileLoader;
	}

	public function __get($key)
	{
		if ('file' == $key) {
			return $this->_fileLoader->getByID($this->fileID);
		}
	}

	public function __sleep()
	{
		return array(
			'id',
			'authorship',
			'type',
			'locale',
			'options',
			'product',
		);
	}

	public function __wakeup()
	{
		$this->file = \Message\Cog\Service\Container::get('file_manager.file.loader')->getByID($this->id);
	}
}