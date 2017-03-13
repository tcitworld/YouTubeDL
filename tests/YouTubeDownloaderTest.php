<?php

namespace Tests;

use Monolog\Logger;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\TestCase;
use YouTubeDL\YouTubeDL;

class YouTubeDownloaderTest extends TestCase
{
	public function testConstructDefault()
	{
		$logger = new Logger('foo');
		$handler = new TestHandler();
		$logger->pushHandler($handler);

		new YouTubeDL($logger);
	}

	public function testConstructWithUrl()
	{
		$logger = new Logger('foo');
		$handler = new TestHandler();
		$logger->pushHandler($handler);

		$yt = new YouTubeDL($logger, "https://www.youtube.com/watch?v=_PerU_i0RR4");

		$this->assertEquals($yt->getVideos()[0]->getId(), '_PerU_i0RR4');
	}

	public function testDefaultConstructWithFetch()
	{
		$logger = new Logger('foo');
		$handler = new TestHandler();
		$logger->pushHandler($handler);

		$yt = new YouTubeDL($logger);
		$yt->addUrl("https://www.youtube.com/watch?v=_PerU_i0RR4");

		$this->assertEquals($yt->getVideos()[0]->getId(), '_PerU_i0RR4');
	}

	public function testgetVideoInfo()
	{
		$logger = new Logger('foo');
		$handler = new TestHandler();
		$logger->pushHandler($handler);

		$yt = new YouTubeDL($logger);
		$yt->addUrl("https://www.youtube.com/watch?v=_PerU_i0RR4");

		$yt->downloadAll();
	}
}