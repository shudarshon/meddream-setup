<?php
/*
	Original name: Branding.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		Handle data from rebranding/rebranding_configuration.js
 */
namespace Softneta\MedDream\Core;


/** @brief Server-side implementation of the rebranding mechanism. */
class Branding
{
	/** @brief Array with rebranding parameters */
	public $attributes = array();

	/** @brief Raw contents of the branding file */
	public $brandingContent = '';


	/** @brief Full path to the rebranding directory (for scripts). */
	public function getBrandingLocation()
	{
		return __DIR__ . DIRECTORY_SEPARATOR . 'rebranding' .
			DIRECTORY_SEPARATOR;
	}


	/** @brief Relative path to the rebranding directory (for browsers etc). */
	public function getBrandingRealLocation()
	{
		return 'rebranding/';
	}


	/** @brief Get the value of a rebranding parameter.

		@param string $name  Name of the parameter

		Loads rebranding data if needed (only once due to caching).
	 */
	public function getAttribute($name)
	{
		if (empty($name))
			return '';

		$list = $this->getAttributes();

		if (!empty($list))
		{
			if (isset($list[$name]))
				return $list[$name];
		}
		return '';
	}


	/** @brief Get branding file content.

		@return string  Contents of the branding file
	 */
	public function getBrandingContent()
	{
		if (empty($this->brandingContent))
		{
			$file = $this->getBrandingLocation() . 'rebranding_configuration.json';
			$content = array();
			if (file_exists($file))
				$this->brandingContent = @file_get_contents($file);
		}
		return $this->brandingContent;
	}


	/** @brief Load rebranding parameters from the configuration file.
	 */
	public function getAttributes()
	{
		if (empty($this->attributes))
		{
			$content = $this->getBrandingContent();
			if (!empty($content))
			{
				$parsedData = @json_decode($content, true);
				if (is_null($parsedData))
					$this->attributes = array();
				else
					$this->attributes = $parsedData;
			}
			unset($content);
		}
		return $this->attributes;
	}


	/** @brief Return an image attribute with a relative path.

		@param string $name  Name of an image attribute ('companyLogoFile' etc)

		@return string Empty in case of missing attribute, or if the full path resolves
		               to a missing file
	 */
	public function getImageAttributeLocation($name)
	{
		$imageName = $this->getAttribute($name);
		if (!empty($imageName))
		{
			$realPath = $this->getBrandingLocation() . $imageName;

			clearstatcache(false, $realPath);
			if (@file_exists($realPath))
				$imageName = $this->getBrandingRealLocation() . $imageName;
			else
				$imageName = '';
		}
		else
			$imageName = '';
		return $imageName;
	}


	/** @brief Determine if rebranding file is valid.

		@return boolean false  %Configuration is invalid
	 */
	public function isValid()
	{
		$list = $this->getAttributes();
		return !empty($list);
	}


	/** @brief Determine if rebranding is active.

		@return boolean false  Rebranding is explicitly disabled
	 */
	public function active()
	{
		$content = $this->getBrandingContent();
		if (!empty($content))
		{
			$data = array();
			@preg_match('/isRebranded":(\\s+|)true/', $content, $data);
			unset($content);
			if(!empty($data))
				return true;
		}
		return false;
	}
}
