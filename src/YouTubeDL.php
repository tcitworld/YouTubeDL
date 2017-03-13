<?php

namespace YouTubeDL;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class YouTubeDL
{
	/**
	 * @var string
	 */
	private $audio;

	/**
	 * @var string
	 */
	private $video;

	/**
	 * @var string
	 */
	private $thumb;

	/**
	 * @var Video[]
	 */
	private $videos;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var string
	 */
	private $defaultDownload = 'video';

	/**
	 * @var int
	 */
    private $videoQuality = 1;

	/**
	 * @var int
	 */
    private $audioQuality = 320;

	/**
	 * @var string
	 */
    private $audioFormat = 'mp3';

	/**
	 * @var string
	 */
    private $downloadsDir = 'videos/';

	/**
	 * @var bool
	 */
	private $downloadThumbs = true;

	/**
	 * @var string
	 */
	private $videoThumbSize = 'l';

	/**
	 * @var string
	 */
	private $FfmpegLogsDir = 'logs/';

	/**
	 * @var string
	 */
	private $ffmpegLogfile = 'log.err';

	/**
	 * @var bool
	 */
	private $FfmpegLogsActive = false;

	private $downloadType = self::VIDEO_TYPE;

	const YT_BASE_URL = "http://www.youtube.com/";

	const YT_INFO_URL = self::YT_BASE_URL . "get_video_info?video_id=%s&el=embedded&ps=default&eurl=&hl=en_US";
	const YT_INFO_ALT = self::YT_BASE_URL . "oembed?url=%s&format=json";
	const YT_THUMB_URL = "http://img.youtube.com/vi/%s/%s.jpg";
	const YT_THUMB_ALT = "http://i1.ytimg.com/vi/%s/%s.jpg";

	const CURL_UA = "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:11.0) Gecko Firefox/11.0";

	/**
	 * Types
	 */
	const VIDEO_TYPE = 0;
	const AUDIO_TYPE = 1;

	/**
	 * States
	 */
	const STATE_ADDED = 1;
	const STATE_CHECKED = 2;
	const STATE_FETCHED = 3;
	const STATE_DOWNLOADED = 4;
	const STATE_CONVERTED = 5;
	const STATE_FINISHED = 10;

	/**
	 *  Class constructor method.
	 *
	 * @param Logger|LoggerInterface $logger
	 * @param string $url
	 * @param int $type
	 */
    public function __construct(Logger $logger, string $url = null, int $type = self::VIDEO_TYPE)
    {
		$stream = new StreamHandler(__DIR__.'/../logs/youtubedl.log', Logger::DEBUG);
    	$this->logger = $logger;
    	$this->logger->pushHandler($stream);

		if (null != $type) {
    		$this->setDownloadType = $type;
		}

		$this->videos = [];

		if (null != $url) {
			try {
				$videoId = self::parseYouTubeUrl($url);
				$video = new Video($videoId);
				$video->setUrl($url);
				$video->setState(self::STATE_ADDED);
				$this->videos[] = $video;
			} catch (\Exception $e) {
				$this->logger->warn("Failed to get youtube video id");
			}
		};
    }

    public function addUrl(string $url): YouTubeDL
	{
		try {
			$videoId = self::parseYouTubeUrl($url);
			$video = new Video($videoId);
			$video->setUrl($url);
			$video->setState(self::STATE_ADDED);
			$this->videos[] = $video;
		} catch (\Exception $e) {
			$this->logger->warn("Failed to get youtube video id");
		}
		return $this;
	}

    public function setDownloadType(int $type): YouTubeDL
	{
		if ($type === self::VIDEO_TYPE || $type === self::AUDIO_TYPE) {
			return $this;
		} else {
			throw new \Exception("Type not existing");
		}
	}

    public function download(Video $video)
    {
        if ($this->downloadType === self::AUDIO_TYPE) {
        	return $this->downloadAudio($video);
		} else {
        	return $this->downloadVideo($video);
		}
    }

	/**
	 *  Set the YouTube Video that shall be downloaded.
	 *
	 * @param string $str
	 * @return YouTubeDL
	 * @throws \Exception
	 */
    public function checkVideo(Video $video): YouTubeDL
    {
        /**
         *  Check the public video info feed to check if we got a
         *  valid Youtube ID. If not, throw an exception and exit.
         */
        $url = sprintf(self::YT_BASE_URL . "watch?v=%s", $video->getId());
        $url = sprintf(self::YT_INFO_ALT, urlencode($url));

        $this->logger->info('URL is '. $url);
		$httpCode = $this->curlHttpStatus($url);
		$this->logger->info('HTTP code is ' . $httpCode);
        if ($httpCode !== 200) {
			throw new \Exception("Invalid Youtube video ID: " . $video->getId(). ". Error code was $httpCode");
		}
		$video->setState(self::STATE_CHECKED);
        return $this;
    }

	/**
	 *  Get the direct links to the YouTube Video.
	 *
	 * @throws \Exception
	 */
    public function getDownloads()
    {
    	foreach ($this->videos as $video) {

			/**
			 *  Try to parse the YouTube Video-Info file to get the video URL map,
			 *  that holds the locations on the YouTube media servers.
			 */
			$this->logger->info('Trying to get videos informations...');
			$video->setInfo(self::getVideoInfo($video));
			$this->logger->info('Found video infos... ', [$video->getInfo()]);
			$video->setTitle(self::extractVideoTitle($video));
			$this->logger->info('Found video title... ', [$video->getTitle()]);
			$vids = self::getUrlMap($video->getInfo());
			$this->logger->info('Found video urls map... ', [$vids]);
			/**
			 *  If extracting the URL map failed, throw an exception
			 *  and exit. Try to include the original YouTube error
			 *  - eg "forbidden by country"-message.
			 */
			if (sizeof($vids) == 0) {
				$err_msg = "";
				if (strpos($video->getInfo(), "status=fail") !== FALSE) {
					preg_match_all('#reason=(.*?)$#si', $video->getInfo(), $err_matches);
					if (isset($err_matches[1][0])) {
						$err_msg = urldecode($err_matches[1][0]);
						$err_msg = str_replace("Watch on YouTube", "", strip_tags($err_msg));
						$err_msg = "Youtube error message: " . $err_msg;
					}
				}
				throw new \Exception($err_msg);
			} else {
				$quality = $this->getVideoQuality();

				if ($quality === 1) {
					usort($vids, 'asc_by_quality');
				} else if ($quality === 0) {
					usort($vids, 'desc_by_quality');
				}

				$video->setUrlsMap($vids);
			}
		}
    }

	/**
	 * Process all downloads
	 */
	public function downloadAll()
	{
		foreach ($this->videos as $video) {
			$this->downloadVideo($video);
		}
	}

	/**
	 *  Try to download the defined YouTube Video.
	 *
	 * @param null $c
	 * @return int Returns (int)0 if download succeded, or (int)1 if
	 *                    the video already exists on the download directory.
	 * @throws \Exception
	 */
    public function downloadVideo(Video $video)
    {
        /**
         *  If we have a valid Youtube Video Id, try to get the real location
         *  and download the video. If not, throw an exception and exit.
         */
		if ($video->getUrlsMap() === []) {
			$this->getDownloads();
		}

		/**
		 *  Format video title and set download and file preferences.
		 */
		$title   = $video->getTitle();
		$path    = $this->getDownloadsDir();

		$YT_Video_URL = $vids[$c]["url"];
		$res = $vids[$c]["type"];
		$ext = $vids[$c]["ext"];

		$videoTitle    = $title . "_-_" . $res ."_-_youtubeid-$id";
		$videoFilename = "$videoTitle.$ext";
		$thumbFilename = "$videoTitle.jpg";
		$video         = $path . $videoFilename;

		$this->setVideo($videoFilename);
		$this->setThumb($thumbFilename);

		/**
		 *  PHP doesn't cache information about non-existent files.
		 *  So, if you call file_exists() on a file that doesn't exist,
		 *  it will return FALSE until you create the file. The problem is,
		 *  that once you've created a file, file_exists() will return TRUE -
		 *  even if you've deleted the file meanwhile and the cache haven't
		 *  been cleared! Even though unlink() clears the cache automatically,
		 *  since we don't know which way a file may have been deleted (if it existed),
		 *  we clear the file status cache to ensure a valid file_exists result.
		 */
		clearstatcache();

		/**
		 *  If the video does not already exist in the download directory,
		 *  try to download the video and the video preview image.
		 */
		if(!file_exists($video))
		{
			$download_thumbs = $this->getDownloadThumbnail();
			if($download_thumbs === TRUE) {
				$this->checkThumbs($id);
			}
			touch($video);
			chmod($video, 0775);

			// Download the video.
			try {
				$this->curlGetFile($YT_Video_URL, $video);
				if ($download_thumbs === true) {
					// Download the video preview image.
					$thumb = $this->getVideoThumb();
					if ($thumb !== false)
					{
						$thumbnail = $path . $thumbFilename;
						$this->curlGetFile($thumb, $thumbnail);
						chmod($thumbnail, 0775);
					}
				}
				return 0;
			} catch (\Exception $e) {
				throw new \Exception("Saving $videoFilename to $path failed.");
			}
		}
		else {
			return 1;
		}
	}

    public function downloadAudio(Video $video): int
    {
        $ffmpeg = $this->hasFfmpeg();

        if ($ffmpeg === FALSE) {
            throw new \Exception("You must have Ffmpeg installed in order to use this function.");
        } else if ($ffmpeg === true) {

            $this->setVideoQuality(1);
            $dl = $this->downloadVideo($video);

            if ($dl == 0 || $dl == 1) {

                $title = $video->getTitle();
                $path = $this->getDownloadsDir();
                $ext = $this->getAudioFormat();

                $ffmpeg_infile = $path . $this->getVideo();
                $ffmpeg_outfile = $path . $title .".". $ext;

                if (!file_exists($ffmpeg_outfile)) {

                    // Whether to log the ffmpeg process.
                    $logging = $this->getFfmpegLogsActive();

                    // Ffmpeg command to convert the input video file into an audio file.
                    $ab = $this->getAudioQuality() . "k";
                    $cmd = "ffmpeg -i \"$ffmpeg_infile\" -ar 44100 -ab $ab -ac 2 \"$ffmpeg_outfile\"";

                    if ($logging !== FALSE) {
                        // Create a unique log file name per process.
                        $ffmpeg_logspath = $this->getFfmpegLogsDir();
                        $ffmpeg_logfile = "ffmpeg." . date("Ymdhis") . ".log";
                        $logfile = "./" . $ffmpeg_logspath . $ffmpeg_logfile;
                        $this->setFfmpegLogFile($logfile);

                        // Create new log file (via command line).
                        exec("touch $logfile");
                        exec("chmod 777 $logfile");
                    }

                    // Execute Ffmpeg command line command.
                    $Ffmpeg = exec($cmd);
                    $log_it = "echo \"$Ffmpeg\" > \"$logfile\"";
                    $lg = `$log_it`;

                    // If the video did not already existed, delete it (since we probably wanted the soundtrack only).
                    if($dl == 0) {
                        unlink($ffmpeg_infile);
                    }

                    clearstatcache();
                    if(file_exists($ffmpeg_outfile) !== FALSE) {
                        $this->setAudio($title .".". $ext);
                        return 0;
                    } else {
                        throw new \Exception("Something went wrong while converting the video into $ext format, sorry!");
                    }
                }
                else {
                	$this->setAudio($title .".". $ext);
                    return 1;
                }
            }
        } else {
            throw new \Exception("Cannot locate your Ffmpeg installation?! Thus, cannot convert the video into $this->audioFormat format.");
        }
        return 1;
    }

    /**
     *  Get filestats for the downloaded audio/video file.
     *  @access  public
     *  @return  mixed   Returns an array containing formatted filestats
     */
    public function getVideoStats(): array
    {
        $file = $this->getVideo();
        $path = $this->getDownloadsDir();

        clearstatcache();
        $filestats = stat($path . $file);
        if ($filestats !== false) {
            return [
                "size" => $this->getHumanBytes($filestats["size"]),
                "created" => date ("d.m.Y H:i:s.", $filestats["ctime"]),
                "modified" => date ("d.m.Y H:i:s.", $filestats["mtime"]),
            ];
        }
        else {
        	throw new \Exception("Couldn't extrct informations from file");
        }
    }

	/**
	 *  Check if input string is a valid YouTube URL
	 *  and try to extract the YouTube Video ID from it.
	 *
	 * @param string $url
	 * @return string Returns YouTube Video ID
	 * @throws \Exception
	 */
    public static function parseYouTubeUrl(string $url): string
    {
		preg_match("/^(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user)\/))([^\?&\"'>]+)/", $url, $matches);
		if (isset($matches[1])) {
			return $matches[1];
		} else {
			throw new \Exception("Coudn't extract informations from URL");
		}
    }

    public function getVideoInfo(Video $video): string
	{
		$url = sprintf(self::YT_INFO_URL, $video->getId());
		$this->logger->info('Fetching informations at url', [$url]);
		return $this->curlGet($url);
	}

    /**
     *  Get internal YouTube info for a Video.
     *  @access  private
     *  @return  string   Returns video info as string.
     */
    public function extractVideoTitle(Video $video): string
    {
		$pb_info = $this->getPublicInfo($video);

		if ($pb_info !== false) {
			$htmlTitle = htmlentities(utf8_decode($pb_info["title"]));
			$videoTitle = self::canonicalize($htmlTitle);
		}
		else {
			$videoTitle = self::formatVideoTitle($video->getInfo());
		}

		if (is_string($videoTitle) && strlen($videoTitle) > 0) {
			return $videoTitle;
		}
		return '';
    }

    /**
     *  Get the public YouTube Info-Feed for a Video.
     *  @access  private
     *  @return  mixed    Returns array, containing the YouTube Video-Title
     *                    and preview image URL, or (boolean) FALSE
     *                    if parsing the feed failed.
     */
    public function getPublicInfo(Video $video)
    {
        $url = sprintf(self::YT_BASE_URL . "watch?v=%s", $video->getId());
        $url = sprintf(self::YT_INFO_ALT, urlencode($url));
        $info = json_decode($this->curlGet($url), true);

        if (is_array($info) && sizeof($info) > 0) {
            return [
                'title' => $info["title"],
                'thumb' => $info["thumbnail_url"]
            ];
        }
        else {
        	return false;
        }
    }

    /**
     *  Get the URL map for a YouTube Video.
     *  @access  private
     *  @param   string   $data  Info-File contents for a YouTube Video.
     *  @return  mixed    Returns an array, containg the Video URL map,
     *                    or (boolean) FALSE if extracting failed.
     */
    private function getUrlMap(string $data): array
    {
    	$this->logger->info('Starting to get the URL map for the YouTube video');

        preg_match('/stream_map=(.[^&]*?)&/i', $data, $match);
        if (!isset($match[1]))
        {
        	$this->logger->warn("Couln't find any string in data ", [$data]);
        	throw new \Exception("Couln't find any string in data");
        } else {
            $fmt_url =  urldecode($match[1]);
            if (preg_match('/^(.*?)\\\\u0026/', $fmt_url, $match2)) {
                $fmt_url = $match2[1];
            }

            $urls = explode(',', $fmt_url);
            $tmp = array();

            foreach($urls as $url) {
                if(preg_match('/itag=([0-9]+)&url=(.*?)&.*?/si', $url, $um))
                {
                    $u = urldecode($um[2]);
                    $tmp[$um[1]] = $u;
                }
            }

            $formats = array(
                '13' => array('3gp', '240p', '10'),
                '17' => array('3gp', '240p', '9'),
                '36' => array('3gp', '320p', '8'),
                '5'  => array('flv', '240p', '7'),
                '6'  => array('flv', '240p', '6'),
                '34' => array('flv', '320p', '5'),
                '35' => array('flv', '480p', '4'),
                '18' => array('mp4', '480p', '3'),
                '22' => array('mp4', '720p', '2'),
                '37' => array('mp4', '1080p', '1')
            );

            $videos = [];

            foreach ($formats as $format => $meta) {
                if (isset($tmp[$format])) {
                    $videos[] = array('pref' => $meta[2], 'ext' => $meta[0], 'type' => $meta[1], 'url' => $tmp[$format]);
                }
            }
            return $videos;
        }
    }

    /**
     *  Get the preview image for a YouTube Video.
     *  @access  private
     *  @param   string   $id  Valid YouTube Video-ID.
     *  @return  string    Returns the image URL as string
     */
    private function checkThumbs(string $id): string
    {
        $thumbsize = $this->getThumbSize();
        $thumbUri = sprintf(self::YT_THUMB_URL, $id, $thumbsize);

        if($this->curlHttpStatus($thumbUri) == 200) {
            $th = $thumbUri;
        }
        else {
			$thumbUri = sprintf(self::YT_THUMB_ALT, $id, $thumbsize);

            if ($this->curlHttpStatus($thumbUri) == 200) {
                $th = $thumbUri;
            }
            else {
            	throw new \Exception("Preview picture didn't return 200 http code");
            }
        }
        $this->setVideoThumb($th);
        return $th;
    }

    /**
     *  Get the YouTube Video Title and format it.
	 *
     *  @param   string   $str  Input string.
     *  @return  string   Returns cleaned input string.
     */
    private function formatVideoTitle(string $str): string
    {
        preg_match_all('#title=(.*?)$#si', urldecode($str), $matches);

        $title = explode("&", $matches[1][0]);
        $title = $title[0];
        $title = htmlentities(utf8_decode($title));

        return self::canonicalize($title);
    }

    /**
     *  Format the YouTube Video Title into a valid filename.
	 *
     *  @param   string   $str  Input string.
     *  @return  string   Returns cleaned input string.
     */
    private function canonicalize($str): string
    {
        $str = trim($str); # Strip unnecessary characters from the beginning and the end of string.
        $str = str_replace("&quot;", "", $str); # Strip quotes.
        $str = self::strynonym($str); # Replace special character vowels by their equivalent ASCII letter.
        $str = preg_replace("/[[:blank:]]+/", "_", $str); # Replace all blanks by an underscore.
        $str = preg_replace('/[^\x9\xA\xD\x20-\x7F]/', '', $str); # Strip everything what is not valid ASCII.
        $str = preg_replace('/[^\w\d_-]/si', '', $str); # Strip everything what is not a word, a number, "_", or "-".
        $str = str_replace('__', '_', $str); # Fix duplicated underscores.
        $str = str_replace('--', '-', $str); # Fix duplicated minus signs.
        if(substr($str, -1) == "_" OR substr($str, -1) == "-") {
            $str = substr($str, 0, -1); # Remove last character, if it's an underscore, or minus sign.
        }
        return trim($str);
    }

    /**
     *  Replace common special entity codes for special character
     *  vowels by their equivalent ASCII letter.
     *  @access  private
     *  @param   string   $str  Input string.
     *  @return  string   Returns cleaned input string.
     */
    private function strynonym($str): string
    {
        $SpecialVowels = array(
            '&Agrave;'=>'A', '&agrave;'=>'a', '&Egrave;'=>'E', '&egrave;'=>'e', '&Igrave;'=>'I', '&igrave;'=>'i', '&Ograve;'=>'O', '&ograve;'=>'o', '&Ugrave;'=>'U', '&ugrave;'=>'u',
            '&Aacute;'=>'A', '&aacute;'=>'a', '&Eacute;'=>'E', '&eacute;'=>'e', '&Iacute;'=>'I', '&iacute;'=>'i', '&Oacute;'=>'O', '&oacute;'=>'o', '&Uacute;'=>'U', '&uacute;'=>'u', '&Yacute;'=>'Y', '&yacute;'=>'y',
            '&Acirc;'=>'A', '&acirc;'=>'a', '&Ecirc;'=>'E', '&ecirc;'=>'e', '&Icirc;'=>'I',  '&icirc;'=>'i', '&Ocirc;'=>'O', '&ocirc;'=>'o', '&Ucirc;'=>'U', '&ucirc;'=>'u',
            '&Atilde;'=>'A', '&atilde;'=>'a', '&Ntilde;'=>'N', '&ntilde;'=>'n', '&Otilde;'=>'O', '&otilde;'=>'o',
            '&Auml;'=>'Ae', '&auml;'=>'ae', '&Euml;'=>'E', '&euml;'=>'e', '&Iuml;'=>'I', '&iuml;'=>'i', '&Ouml;'=>'Oe', '&ouml;'=>'oe', '&Uuml;'=>'Ue', '&uuml;'=>'ue', '&Yuml;'=>'Y', '&yuml;'=>'y',
            '&Aring;'=>'A', '&aring;'=>'a', '&AElig;'=>'Ae', '&aelig;'=>'ae', '&Ccedil;'=>'C', '&ccedil;'=>'c', '&OElig;'=>'OE', '&oelig;'=>'oe', '&szlig;'=>'ss', '&Oslash;'=>'O', '&oslash;'=>'o'
        );
        return strtr($str, $SpecialVowels);
    }

    /**
     *  Check if given directory exists. If not, try to create it.
     *  @access  private
     *  @param   string   $dir  Path to the directory.
     *  @return  boolean  Returns (boolean) TRUE if directory exists,
     *                    or was created, or FALSE if creating non-existing
     *                    directory failed.
     */
    private function validDir(string $dir): bool
    {
        if(is_dir($dir) !== false) {
            chmod($dir, 0777); # Ensure permissions. Otherwise CURLOPT_FILE will fail!
            return true;
        }
        else {
            return (bool) ! @mkdir($dir, 0777);
        }
    }

    /**
     *  Check on the command line if we can find an Ffmpeg installation on the script host.
     *  @access  private
     *  @return  boolean  Returns (boolean) TRUE if Ffmpeg is installed on the server,
     *                    or FALSE if not.
     */
    private function hasFfmpeg(): bool
    {
        $sh = `which ffmpeg`;
        return (bool) (strlen(trim($sh)) > 0);
    }

    /**
     *  HTTP HEAD request with curl.
     *  @access  private
     *  @param   string   $url  String, containing the URL to curl.
     *  @return  int   Returns a HTTP status code.
     */
    private function curlHttpStatus(string $url): int
    {
        $ch = curl_init($url);
		curl_setopt($ch, CURLOPT_USERAGENT, self::CURL_UA);
		curl_setopt($ch, CURLOPT_REFERER, self::YT_BASE_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_exec($ch);
        $int = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return (int) $int;
    }

    /**
     *  HTTP GET request with curl.
     *  @access  private
     *  @param   string   $url  String, containing the URL to curl.
     *  @return  string   Returns string, containing the curl result.
     */
    private function curlGet(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, self::CURL_UA);
        curl_setopt($ch, CURLOPT_REFERER, self::YT_BASE_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $contents = curl_exec ($ch);
        curl_close ($ch);
        return $contents;
    }

    /**
     *  HTTP GET request with curl that writes the curl result into a local file.
     *  @access  private
     *  @param   string   $remote_file  String, containing the remote file URL to curl.
     *  @param   string   $local_file   String, containing the path to the file to save
     *                                  the curl result in to.
     */
    private function curlGetFile(string $remote_file, string $local_file)
    {
        $ch = curl_init($remote_file);
        curl_setopt($ch, CURLOPT_USERAGENT, self::CURL_UA);
        curl_setopt($ch, CURLOPT_REFERER, self::YT_BASE_URL);
        $fp = fopen($local_file, 'w');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_exec ($ch);
        curl_close ($ch);
        fclose($fp);
    }

	/**
	 * @return string
	 */
    public function getAudio(): string
	{
        return $this->audio;
	}

	/**
	 * @param string $audio
	 * @return YouTubeDL
	 */
    private function setAudio(string $audio): YouTubeDL
	{
        $this->audio = $audio;
        return $this;
	}

	/**
	 * @return string
	 */
    public function getVideo(): string
	{
        return $this->video;
    }

	/**
	 * @param $video
	 * @return YouTubeDL
	 */
    private function setVideo(string $video): YouTubeDL
	{
        $this->video = $video;
        return $this;
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
    private function setThumb(string $img): YouTubeDL
	{
        $this->thumb = $img;
        return $this;
    }

	/**
	 * @return string
	 */
    public function getDefaultDownload(): string
	{
        return $this->defaultDownload;
	}

	/**
	 * @param string $action
	 * @return YouTubeDL
	 * @throws \Exception
	 */
    public function setDefaultDownload(string $action): YouTubeDL
	{
        if($action == "audio" || $action == "video") {
            $this->defaultDownload = $action;
            return $this;
        } else {
            throw new \Exception("Invalid download type. Must be either 'audio', or 'video'."); }
    }

	/**
	 * @return bool
	 */
    public function getVideoQuality()
	{
        return $this->videoQuality;
	}

	/**
	 * @param $q
	 * @return YouTubeDL
	 * @throws \Exception
	 */
    public function setVideoQuality($q): YouTubeDL
	{
        if(in_array($q, [0,1])) {
            $this->videoQuality = $q;
            return $this;
        } else {
            throw new \Exception("Invalid video quality."); }
    }

	/**
	 * @return int
	 */
    public function getAudioQuality(): int
	{
        return $this->audioQuality;
	}

	/**
	 * @param int $q
	 * @return YouTubeDL
	 * @throws \Exception
	 */
    public function setAudioQuality(int $q): YouTubeDL
	{
        if($q >= 128 && $q <= 320) {
            $this->audioQuality = $q;
            return $this;
        } else {
            throw new \Exception("Audio sample rate must be between 128 and 320."); }
    }

	/**
	 * @return string
	 */
    public function getAudioFormat(): string
	{
        return $this->audioFormat;
	}

	/**
	 * @param string $ext
	 * @return YouTubeDL
	 * @throws \Exception
	 */
    public function setAudioFormat(string $ext): YouTubeDL
	{
        $validExts = ["mp3", "wav", "ogg", "mp4"];
        if (in_array($ext, $validExts)) {
            $this->audioFormat = $ext;
            return $this;
        } else {
            throw new \Exception("Invalid audio filetype '$ext' defined. Valid filetypes are: " . implode(", ", $validExts) );
        }
    }

	/**
	 * @return string
	 */
    public function getDownloadsDir(): string
	{
        return $this->downloadsDir;
    }

	/**
	 * @param string $dir
	 * @return YouTubeDL
	 * @throws \Exception
	 */
    public function setDownloadsDir(string $dir): YouTubeDL
	{
        if($this->validDir($dir) !== FALSE) {
            $this->downloadsDir = $dir;
            return $this;
        } else {
            throw new \Exception("Can neither find, nor create download folder: $dir"); }
    }

	/**
	 * @return bool
	 */
    public function getFfmpegLogsActive(): bool
	{
        return $this->FfmpegLogsDir;
	}

	/**
	 * @param bool $ffmpegLogsActive
	 * @return YouTubeDL
	 * @internal param bool $b
	 */
    public function setFfmpegLogsActive(bool $ffmpegLogsActive): YouTubeDL
	{
        $this->FfmpegLogsActive = $ffmpegLogsActive;
        return $this;
	}

	/**
	 * @return bool
	 */
    public function getDownloadThumbnail()
	{
        return $this->downloadThumbs;
	}

	/**
	 * @param bool $downloadThumbail
	 * @return YouTubeDL
	 */
    public function set_download_thumbnail(bool $downloadThumbail): YouTubeDL
	{
		$this->downloadThumbs = $downloadThumbail;
		return $this;
    }

	/**
	 * @return string
	 */
	public function getThumbSize(): string
	{
        return $this->videoThumbSize;
	}

	/**
	 * @param string $size
	 * @return YouTubeDL
	 * @throws \Exception
	 */
    public function setThumbSize(string $size): YouTubeDL
	{
        if($size == "s")
        {
            $this->videoThumbSize = "default";
            return $this;
        }
        else if($size == "l")
        {
            $this->videoThumbSize = "hqdefault";
            return $this;
        }
        else
		{
            throw new \Exception("Invalid thumbnail size specified.");
		}
    }

	/**
	 * @return string
	 */
    public function getFfmpegLogFile(): string
	{
        return $this->ffmpegLogfile;
	}

	/**
	 * @param $str
	 * @return YouTubeDL
	 */
	private function setFfmpegLogFile($str): YouTubeDL
	{
        $this->ffmpegLogfile = $str;
        return $this;
	}

	/**
	 * @return string
	 */
    public function getFfmpegLogsDir(): string
	{
        return $this->FfmpegLogsDir;
	}

	/**
	 * @param string $dir
	 * @return YouTubeDL
	 * @throws \Exception
	 */
    public function setFfmpegLogsDir(string $dir): YouTubeDL
	{
        if($this->validDir($dir) !== FALSE) {
            $this->FfmpegLogsDir = $dir;
            return $this;
        } else {
            throw new \Exception("Can neither find, nor create ffmpeg log directory '$dir', but logging is enabled."); }
    }

    /**
     *  Format file size in bytes into human-readable string.
     *  @access  public
     *  @param   string   $bytes   Filesize in bytes.
     *  @return  string   Returns human-readable formatted filesize.
     */
    public function getHumanBytes($bytes)
    {
        $fsize = $bytes;
        switch ($bytes):
            case $bytes < 1024:
                $fsize = $bytes .' B'; break;
            case $bytes < 1048576:
                $fsize = round($bytes / 1024, 2) .' KiB'; break;
            case $bytes < 1073741824:
                $fsize = round($bytes / 1048576, 2) . ' MiB'; break;
            case $bytes < 1099511627776:
                $fsize = round($bytes / 1073741824, 2) . ' GiB'; break;
        endswitch;
        return $fsize;
    }

	/**
	 * @return int
	 */
	public function getDownloadType(): int
	{
		return $this->downloadType;
	}

	/**
	 * @return Video[]
	 */
	public function getVideos(): array
	{
		return $this->videos;
	}

	/**
	 * @param Video[] $videos
	 */
	public function setVideos(array $videos)
	{
		$this->videos = $videos;
	}

	public function addVideo(Video $video)
	{
		$this->videos[] = $video;
	}
}

function asc_by_quality($val_a, $val_b)
{
	$a = $val_a['pref'];
	$b = $val_b['pref'];
	if ($a == $b) return 0;
	return ($a < $b) ? -1 : +1;
}

function desc_by_quality($val_a, $val_b)
{
	$a = $val_a['pref'];
	$b = $val_b['pref'];
	if ($a == $b) return 0;
	return ($a > $b) ? -1 : +1;
}