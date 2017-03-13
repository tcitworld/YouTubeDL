<?php
/**
 * Created by PhpStorm.
 * User: tcit
 * Date: 10/03/17
 * Time: 14:20
 */

namespace YouTubeDL;


class Video
{
	/**
	 * @var string
	 */
	private $id;

	/**
	 * @var string
	 */
	private $title;

	/**
	 * @var string
	 */
	private $url;

	/**
	 * @var int
	 */
	private $state;

	/**
	 * @var string
	 */
	private $info;

	/**
	 * @var string[]
	 */
	private $urlsMap;

	/**
	 * @var string
	 */
	private $thumb;

	/**
	 * Video constructor.
	 * @param string $id
	 */
	function __construct(string $id)
	{
		if (strlen($id) == 11) {
			$this->id = $id;
		} else {
			throw new \Exception("$id is not a valid Youtube Video ID.");
		}
		$this->urlsMap = [];
	}

	/**
	 * @return string
	 */
	public function getId(): string
	{
		return $this->id;
	}

	/**
	 * @param string $id
	 */
	public function setId(string $id)
	{
		$this->id = $id;
	}

	/**
	 * @return string
	 */
	public function getTitle(): string
	{
		return $this->title;
	}

	/**
	 * @param string $title
	 */
	public function setTitle(string $title)
	{
		$this->title = $title;
	}

	/**
	 * @return string
	 */
	public function getUrl(): string
	{
		return $this->url;
	}

	/**
	 * @param string $url
	 */
	public function setUrl(string $url)
	{
		$this->url = $url;
	}

	public function __toString(): string
	{
		return $this->id;
	}

	/**
	 * @return int
	 */
	public function getState()
	{
		return $this->state;
	}

	/**
	 * @param int $state
	 */
	public function setState($state)
	{
		$this->state = $state;
	}

	/**
	 * @return string
	 */
	public function getInfo(): string
	{
		return $this->info;
	}

	/**
	 * @param string $info
	 */
	public function setInfo(string $info)
	{
		$this->info = $info;
	}

	/**
	 * @return string[]
	 */
	public function getUrlsMap(): array
	{
		return $this->urlsMap;
	}

	/**
	 * @param string[] $urlsMap
	 */
	public function setUrlsMap($urlsMap)
	{
		$this->urlsMap = $urlsMap;
	}

	/**
	 * @return string
	 */
	public function getThumb(): string
	{
		return $this->thumb;
	}

	/**
	 * @param string $img
	 * @return YouTubeDL
	 */
	private function setThumb(string $img): Video
	{
		$this->thumb = $img;
		return $this;
	}
}