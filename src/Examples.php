<?php

namespace YouTubeDL;

use Monolog\Logger;

class Examples
{

	private $logger;

	function __construct()
	{
		$this->logger = new Logger('youtube');
	}

	public function exampleVideo1()
	{
		try {
			new YouTubeDL($this->logger, "http://www.youtube.com/watch?v=px17OLxdDMU");
		}
		catch (\Exception $e) {
			die($e->getMessage());
		}
	}

	public function exampleVideo2()
	{
		try {
			$mytube = new YouTubeDL($this->logger, "http://www.youtube.com/watch?v=px17OLxdDMU");

			$video = $mytube->getVideo();
			$path_dl = $mytube->getDownloadsDir();

			clearstatcache();
			if ($video !== false && file_exists($path_dl . $video))
			{
				print "<a href='". $path_dl . $video ."' target='_blank'>Click, to open downloaded video file.</a>";
			} else {
				print "Oups. Something went wrong.";
			}
		}
		catch (\Exception $e) {
			die($e->getMessage());
		}
	}

	public function exampleVideo3()
	{
		try
		{
			$mytube = new YouTubeDL($this->logger);

			$mytube->setYoutubeURL('my url');     # YouTube URL (or ID) of the video to download.
			$mytube->setVideoQuality(1);          # Change default output video file quality.
			$mytube->setThumbSize('s');           # Change default video preview image size.

			$download = $mytube->downloadVideo();

			if($download == 0 || $download == 1)
			{
				$video = $mytube->getVideo();

				if($download == 0) {
					print "<h2><code>$video</code><br>succesfully downloaded into your Downloads Folder.</h2>";
				}
				else if($download == 1) {
					print "<h2><code>$video</code><br>already exists in your your Downloads Folder.</h2>";
				}

				$filestats = $mytube->getVideoStats();
				if($filestats !== FALSE) {
					print "<h3>File statistics for <code>$video</code></h3>";
					print "Filesize: " . $filestats["size"] . "<br>";
					print "Created: " . $filestats["created"] . "<br>";
					print "Last modified: " . $filestats["modified"] . "<br>";
				}

				$path = $mytube->getDownloadsDir();
				print "<br><a href='". $path . $video ."' target='_blank'>Click, to open downloaded video file.</a>";

				$thumb = $mytube->getThumb();
				clearstatcache();
				if($thumb !== FALSE && file_exists($path . $thumb)) {
					print "<hr><img src=\"". $path . $thumb ."\"><hr>";
				}
			}
		}
		catch (\Exception $e) {
			die($e->getMessage());
		}
	}

	public function exampleAudio1()
	{
		try {
			new YouTubeDL($this->logger, "http://www.youtube.com/watch?v=px17OLxdDMU", YouTubeDL::AUDIO_TYPE);
		} catch (\Exception $e) {
			die($e->getMessage());
		}
	}

	public function exampleAudio2()
	{
		try {
			$mytube = new YouTubeDL($this->logger, "http://www.youtube.com/watch?v=px17OLxdDMU", YouTubeDL::AUDIO_TYPE);

			$audio = $mytube->getAudio();
			$path_dl = $mytube->getDownloadsDir();

			clearstatcache();
			if($audio !== false && file_exists($path_dl . $audio) !== false)
			{
				print "<a href='". $path_dl . $audio ."' target='_blank'>Click, to open downloaded audio file.</a>";
			} else {
				print "Oups. Something went wrong.";
			}

			$log = $mytube->getFfmpegLogFile();
			if($log !== false) {
				print "<br><a href='" . $log . "' target='_blank'>Click, to view the Ffmpeg file.</a>";
			}
		}
		catch (\Exception $e) {
			die($e->getMessage());
		}

	}

	public function exampleAudio3()
	{
		try
		{
			$mytube = new YouTubeDL($this->logger, "http://www.youtube.com/watch?v=px17OLxdDMU", YouTubeDL::AUDIO_TYPE);

			$mytube->setAudioFormat("wav");        # Change default audio output filetype.
			$mytube->setFfmpegLogsActive(false);   # Disable Ffmpeg process logging.

			$mytube->downloadAudio();
		}
		catch (\Exception $e) {
			die($e->getMessage());
		}
	}
}

$tests = new Examples();

$tests->exampleVideo1();