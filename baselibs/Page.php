<?php 
/******** Syndk8's OpenBH *********
 *
 * This program is free software
 * licensed under the GPLv2 license.
 * You may redistribute it and/or
 * modify it under the terms of
 * the GPLv2 license (see license.txt)
 *
 * Warning:
 * OpenBH is for educational use
 * Use OpenBH at your own risk !
 *
 * Credits:
 * https://www.syndk8.com/openbh/people.html
 *
 ********************************/

/**
 * baselibs/Page.php
 * The Page class is used to store (serialize) and load (deserialize) created pages 
 * or generate new pages as we need them
 * 
 * Special Page wtf?
 * special pages is simply missusing the page object to 
 * also serve robots/sitemaps/rss feeds/redirects/whatever
 * but is not building any content
 * 
 * @author Neek
 */
class Page
{
	// Content
	public $keyword;
	public $title;
	public $content;
	public $h1;
	public $h2;
	public $metakw;
	public $metadesc;
	
	// Randomized data for quicker access
	public $last;
	public $last_kw;
	public $next;
	public $next_kw;
	public $navlinks = array();
	
	// special cases
	public $responsecode;
	public $dontskin = false;
	public $redirlink;
	
	// datafeed related
	public $advertisment;
	// template
	private $template;
	
	// because of patterns..
	public $filename; // randomized name
	public $filter;  // the filter used ??
	
	// optionally we can configure an advertisment with the initial keyword import (datafeeds etc/csv parser)
	public $staticAdContainer = array();

        private $cachedFuncStorage = array();

        /**
         *  Creating a new page either with content or not 
         */
	function __construct($keyword,$special=false,$advertisment=null,$empty=false) //$title,$content,$h1,$h2,$last,$next,$navlinks)
	{
		if (!file_exists(sprintf('templates/%s/site.html',OpenBHConf::get('template')))) {
			return false;
		}
		$this->template = file_get_contents(sprintf('templates/%s/site.html',OpenBHConf::get('template')));
		
		$this->advertisment = $advertisment;
		
		// default is not a special page ;)
		$this->responsecode = 200;
		$this->dontskin = 1;
		$this->redirlink = null;
		$this->keyword = trim($keyword);
		$this->filename = $this->BuildFilename();
                
                // static content snippets because this parts should really not be scraped on the fly
                $this->title = $this->TextSnippet($this->keyword,'title.txt');
		$this->h1 = $this->TextSnippet($this->keyword,'h1.txt');
		$this->h2 = $this->TextSnippet($this->keyword,'h2.txt');

		if ($special==false && $empty==false) {
			$this->Init();
		}
		if ($empty==true) {
			$this->SetCache();
		}
	}

        /**
         * Content gets produced here
         */
	public function Init() {
		$this->content = $this->BuildContent(OpenBHConf::get('hooks'));
		
		$this->metadesc = $this->TextSnippet($this->keyword,'description.txt');
		$this->metakw = '';
		
		// navigation elements
		$this->BuildNav();
		
		$this->dontskin = 0;
		
		// store ..
		$this->SetCache(); 
	}

        /**
         *  Building filenames as configured in config/config.php filename generator
         */
	private function BuildFilename() {
		$filegen = OpenBHConf::get('filename_generator');
		$s = array('%keyword%','%datecreated%');
		$r = array($this->keyword,date('h-i-s'));
		$filegen = str_replace($s,$r,$filegen);
		$filegen = preg_replace_callback(	"/{(.+?)}/",
                                                        create_function(    '$matches',
                                                                            '$expl = explode(",",$matches[1]);
                                                                            if (function_exists($expl[0])) { if (isset($expl[1])) { return $expl[0]($expl[1]); } else { return $expl[0](); } } return "";'
                                                        ),
                                                        $filegen);
		/* cleanup ;) */
		$filegen = str_replace('{','',$filegen);
		$filegen = str_replace('}','',$filegen);
		$filegen = str_replace(' ','-',$filegen);
		return sprintf("%s%s",$filegen,OpenBHConf::get('filetype'));
	}
	
	private function BuildNav() {
		/* !!! */
		$datafeed = new DataFeed();
		$filenames = array();
		$keywords = $datafeed->ReturnRandomEntries(rand(OpenBHConf::get('navlinks_min'),OpenBHConf::get('navlinks_max')));
		foreach($keywords as $keyword) {
                        if ($keyword=='') {
                            continue;
                        }
			/* we need to check if we already generated this page (because of the randomized filename..) */
			$tmpPage = Page::GetCache($keyword);
			if (is_null($tmpPage)) {
				/* we didnt already created it we need to assign a random filename now !! and store it as empty page..) */
				$p = new Page($keyword,false,null,true); // create empty page .. will generate filename 
				array_push($filenames,array('kw'=>$keyword,'filename'=>$p->filename));
				$p = null;
				continue;
			}
			array_push($filenames,array('kw'=>$keyword,'filename'=>$tmpPage->filename));
		}
		$this->navlinks = $filenames;
		
		$this->last_kw = $datafeed->ReturnPrevKw($this->keyword);
		$prevPage = Page::GetCache($this->last_kw);
		if (is_null($prevPage)) {
			$prevPage = new Page($this->last_kw,false,null,true);
			$this->last = $prevPage->filename;
		} else {
			$this->last = $prevPage->filename;
		}
		
		$this->next_kw = $datafeed->ReturnNextKw($this->keyword);
		$nextPage = Page::GetCache($this->next_kw);
		if (is_null($nextPage)) {
			$nextPage = new Page($this->next_kw,false,null,true);
			$this->next = $nextPage->filename;
		} else {
			$this->next = $nextPage->filename;
		}
	}
	
	function SkinIndex($pageArr) {
		/*
		 * replace [[article]] section 
		 */
                 $c = '';
                 preg_match("/\[\[article\]\](.+?)\[\[\/article\]\]/si",$this->template,$articlesection);
                 foreach($pageArr as $p) {
                     $s = array('[[content]]','[[title]]','[[h1]]','[[h2]]','[[keyword]]');
                     $r = array(sprintf('%s <a href="%s">%s</a>...',substr($p->content,0,rand(200,300)),$p->filename,$p->keyword),$p->title,$p->h1,$p->h2,$p->keyword);
                     $c .= str_ireplace($s,$r,$articlesection[1]);
                 }
		$this->template = preg_replace('/\[\[article\]\].+?\[\[\/article\]\]/si',$c,$this->template);
		return $this->Skin();	
	}
	
	function Skin() {
		/* basic elements */
		$this->template = str_ireplace("[[content]]",$this->content,$this->template);
		$this->template = str_ireplace("[[title]]",$this->title,$this->template);
		$this->template = str_ireplace("[[h1]]",$this->h1,$this->template);
		$this->template = str_ireplace("[[h2]]",$this->h2,$this->template);
		
		preg_match("/\[\[nav\]\](.+?)\[\[\/nav\]\]/is",$this->template,$navmatch);
		$nav = '';
                foreach($this->navlinks as $kwf) {
			$nav .= str_replace("[[nav_url]]",$kwf['filename'],str_replace("[[keyword]]",$kwf['kw'],$navmatch[1]));
		}
		$this->template = preg_replace("/\[\[nav\]\](.+?)\[\[\/nav\]\]/is",$nav,$this->template);
		
		/*
		$this->template = str_ireplace("[[nav_lastkw]]",sprintf('<a href="%s">%s</a>',$this->last,$this->last_kw),$this->template);
		$this->template = str_ireplace("[[nav_nextkw]]",sprintf('<a href="%s">%s</a>',$this->next,$this->next_kw),$this->template);
		*/
		$this->template = str_ireplace("[[nav_last]]",$this->last,$this->template);
		$this->template = str_ireplace("[[nav_next]]",$this->next,$this->template);
		$this->template = str_ireplace("[[nav_lastkw]]",$this->last_kw,$this->template);
		$this->template = str_ireplace("[[nav_nextkw]]",$this->next_kw,$this->template);
		
		$this->template = str_ireplace("[[keyword]]",$this->keyword,$this->template);
		
		/* ads */
		if (stripos($this->template,"[[staticad(js)]]")!==FALSE || stripos($this->template,"[[staticad(html)]]")!==FALSE) {
			$ad = new StaticAdvertising($this->advertisment);
			$this->template = str_ireplace("[[staticad(js)]]",$ad->ServeAdJS(),$this->template);
			$this->template = str_ireplace("[[staticad(html)]]",$ad->ServeAdHTML(),$this->template);
		}
		
		if (stripos($this->template,"[[dynamicad(js)#")!==FALSE || stripos($this->template,"[[dynamicad(html)]]")!==FALSE) {
			$ad = new DynamicAdvertising($this->keyword,OpenBHConf::get('dynadhook'));
			$this->template = str_ireplace("[[dynamicad(js)]]",$ad->ServeAdJS());
			$this->template = str_ireplace("[[dynamicad(html)]]",$ad->ServeAdHTML());
		}

		/* on the fly function tokens.. {{funcName}} */
 		$this->template = preg_replace_callback(	"/{{(.+?)}}/",
												create_function(    '$matches',
																	'$expl = explode(",",$matches[1]);
																	if (function_exists($expl[0])) { if (isset($expl[1])) { return $expl[0]($expl[1]); } else { return $expl[0](); } } return "";'
												),
												$this->template);

		/* cached function tokens ((funcName)) */
		preg_match_all('/\(\((.+?)\)\)/is',$this->template,$cachedFuncs);
		foreach($cachedFuncs[1] as $cachedFunc) {
			if (!array_key_exists($cachedFunc,$this->cachedFuncStorage)) {
				if (function_exists($cachedFunc)) {
					$this->cachedFuncStorage[$cachedFunc] = $cachedFunc($this);
				} else {
					$this->cachedFuncStorage[$cachedFunc] = '';
				}
			}
			$this->template = str_ireplace(sprintf('((%s))',$cachedFunc),$this->cachedFuncStorage[$cachedFunc],$this->template);
		}
		
		/* i love syndk8 */
		$cc = OpenBHConf::get('cc');
		$cn = " ".OpenBHConf::get('cn');
		if (strtoupper($cc)=='US') {
			$cc = '';
			$cn = '';
		}
		$this->template = str_ireplace('[LOVE]',"<a href='https://www.syndk8.com/{$cc}'>Make Money Online{$cn}",$this->template);
                
		/* replace the rest */
		$this->template = preg_replace('/\[\[.+?\]\]/','',$this->template);
		$this->template = preg_replace('/\[\[\/.+?\]\]/','',$this->template);
		
		return $this->template;
	}
	
	/**
	 * Using the configured hooks (see config.php) to generate the content
	 * 
	 * @param $HookList
	 * @return unknown_type
	 */
	private function BuildContent($HookList)
	{
		$content = '';
		foreach(array_keys($HookList) as $hook) {
			if (!class_exists($hook)) {
				continue;
			}
			if (!array_key_exists('prob',$HookList[$hook])) {
				continue; // missconfigured class - check $conf['hooks'] ..
			}
			if (rand(0,100)<$HookList[$hook]['prob']) {
				$h = new $hook();
				$content = $h->EnrichContent($content,$this->keyword,$HookList[$hook]);
			}
		}
		return $this->finalizeContent($content);
	}
	
	private function TextSnippet($keyword,$file) {
		$path = sprintf("config/text/%s",$file);
		if (!file_exists($path)) {
			return false;
		}
		$lines = file($path);
		if (count($lines)<=0) {
			return false;
		} 
		shuffle($lines);
		return str_ireplace("#keyword#",$keyword,$lines[0]);
	}
	
	private function FinalizeContent($content) {
		// Strip html tags that didn't get filtered out in the hooks
		$content = strip_tags(html_entity_decode($content),'<li><ul><p>');

		// todo: remove CDATA and random chars that can't be displayed (shown as squares)

		// Reduce all multiple whitespaces to singles
		$content = preg_replace('/\s+/',' ',$content); 
		// Reduce all multiple .,;:!? to singles
		$content = preg_replace_callback('/([\.,;:!\?]+)/',create_function('$matches','return substr($matches[1],0,1);'),$content);
		// Remove spaces in front of ,.;!?
		$content = preg_replace('/\s([,\.;!\?])/','${1}',$content);
		// Insert space when ,.;!? is followed by a letter (new sentence)
		$content = preg_replace('/([,\.;!\?])(\w)/','${1} ${2}',$content);
		// Capitalize the first word of a sentence
		$content = preg_replace_callback('/([,\.;!\?])\s(\w)/',create_function('$matches','return sprintf("%s %s",$matches[1],ucfirst($matches[2]));'),$content);
		return $content;
	}
	
	private function SetCache() {
		if ($this->keyword=='') {
			return false;
		} else {
			//i realize we return before breaking, it's just good form
			switch (OpenBHConf::get('cache')) {
				case "database":
					$this->SetCacheDB();
					return;
					break;
				case "standard":
					$path = sprintf('data/content/%s',base64_encode($this->keyword));
					file_put_contents($path,gzcompress(serialize($this)));
					return;
					break;
				case "master":
					$this->SetMasterCache('data/content',$this->keyword,serialize($this));
					return;
					break;
			}
		}
	}
	
	// static cache/object loader 
	public static function GetCache($keyword) {
		if ($keyword == '') {
			return null;
		} else {
			//i realize we return before breaking, it's just good form
			switch (OpenBHConf::get('cache')) {
				case "database":
					return Page::GetCacheDB($keyword);
					break;
				case "standard":
					$path = sprintf('data/content/%s',base64_encode($keyword));
					if (!file_exists($path)) {
						return null;
					} else {
						return unserialize(gzuncompress(file_get_contents(sprintf('data/content/%s',base64_encode($keyword)))));
					}
					break;
				case "master":
					$ser = Page::GetMasterCache('data/content',$keyword,OpenBHConf::get('mclockwait'));
					if ($ser==null) {
						return null;
					} else {
						return unserialize($ser);
					}
					break;
			}
		}
		return null;
	}
	
	private function SetCacheDB() {
		$oc_identifier = base64_encode($this->keyword);
		$oc_data = gzcompress(serialize($this));
		$dbl = new DBLayer();
		if ($dbl->Exists("SELECT oc_id FROM openbh_cache WHERE oc_identifier = '{$oc_identifier}'")) {
			if ($dbl->Query("UPDATE openbh_cache SET oc_data = '{$oc_data}' WHERE oc_identifier = '{$oc_identifier}'")) {
				$dbl->EndSession(true);
				return true;
			}
			$dbl->EndSession(false);
			return false;
		}
		if ($dbl->Query("INSERT INTO openbh_cache SET oc_data = '{$oc_data}', oc_identifier = '{$oc_identifier}'")) {
			$dbl->EndSession(true);
			return true;
		}
		$dbl->EndSession(false);
		return false;
	}

	public static function GetCacheDB($keyword) {
		$oc_identifier = base64_encode($keyword);
		$dbl = new DBLayer();
		$oc = $dbl->QueryAndReturn("SELECT oc_data FROM openbh_cache WHERE oc_identifier = '{$oc_identifier}'");
		foreach($oc as $c) {
			return unserialize(gzuncompress($c['oc_data']));
		}
	}
	
	
	public static function GetMasterCache($path,$fname,$wait_for_unlock_true_false = true)
	{
		$path.="/";
		$path=str_replace("//","/",$path);
		$mi = fopen($path.'masterindex.dat', 'r+');
		$fp = fopen($path.'masterfile.dat', 'r+');
		if ($mi==null) {
			return null;
		}
		if (!$wait_for_unlock_true_false) {
			if (!flock($mi, LOCK_EX|LOCK_NB)) {
				$busy="";
				if (file_exists("busylist.txt")) {
					$busy = file_get_contents("busylist.txt");
				}
				$busy.="Busy when trying to load: ".$this->keyword."\r\n";
				file_put_contents("busylist.txt",$busy);
				fclose($fp);
				fclose($mi);
				return null;
			}
			if (!flock($fp, LOCK_EX|LOCK_NB)) {
				$busy="";
				if (file_exists("busylist.txt")) {
					$busy = file_get_contents("busylist.txt");
				}
				$busy.="Busy when trying to load: ".$this->keyword."\r\n";
				file_put_contents("busylist.txt",$busy);
				fclose($fp);
				fclose($mi);
				return null;
			}
		} else {
			flock($mi, LOCK_EX);
			flock($fp, LOCK_EX);
		}
		$sermasterindex='';
		if ($mi!=null && $mi!==false) {
			while (!feof($mi)) {
				$sermasterindex .= fread($mi, filesize($path.'masterindex.dat'));
			}
		} else {
			return false;
		}
		$masterindex=unserialize($sermasterindex);
		if ($masterindex[$fname]!=null) {
			$start = $masterindex[$fname]["start"];
			$size = $masterindex[$fname]["stop"]-$start;
			fseek($fp,$start);
			$ser='';
			if ($fp!=null && $fp!==false) {
				$newsize=0;
				while (!$newsize==$size) {
					$ser .= fread($fp, $size);
					$newsize=strlen($ser);
				}
			} else {
				return false;
			}
			fclose($fp);
			fclose($mi);	
			return $ser;	
		}
		fclose($fp);
		fclose($mi);
		return null;
	}
	
	public static function SetMasterCache($path,$fname,$data)
	{
		$path.="/";
		$path=str_replace("//","/",$path);
		if (file_exists($path."masterindex.dat")) {
			$mi = fopen($path.'masterindex.dat', 'r+');
			$fp = fopen($path.'masterfile.dat', 'r+');
			flock($mi, LOCK_EX);
			flock($fp, LOCK_EX);
			if ($mi!=null && $mi!==false) {
				while (!feof($mi)) {
					$sermasterindex .= fread($mi, filesize($path.'masterindex.dat'));
				}
			} else {
				return null;
			}
			$masterindex=unserialize($sermasterindex);
			if ($masterindex[$fname]==null) {
				fseek($fp,0,SEEK_END);
				$masterindex[$fname]["start"]=ftell($fp);				
				fwrite($fp,$data);
				$masterindex[$fname]["stop"]=ftell($fp);
				$sermasterindex=serialize($masterindex);
				$test = fseek($mi,0);
				$test = fwrite($mi,$sermasterindex);
			} else {
				$start = $masterindex[$fname]["start"];
				$oldsize = $masterindex[$fname]["stop"]-$start;
				$datalen=strlen($data);
				if ($datalen>$oldsize)
				{
					fseek($fp,0,SEEK_END);
					$masterindex[$fname]["start"]=ftell($fp);				
					fwrite($fp,$data);
					$masterindex[$fname]["stop"]=ftell($fp);
					$sermasterindex=serialize($masterindex);
					$test = fseek($mi,0);
					$test = fwrite($mi,$sermasterindex);
				}
			}
			fclose($fp);
			fclose($mi);
		} else {
			$mi = fopen($path.'masterindex.dat', 'x+');
			$fp = fopen($path.'masterfile.dat', 'x+');
			flock($mi, LOCK_EX);
			flock($fp, LOCK_EX);
			$masterindex=array();
			$masterindex[$fname]["start"]=0;
			fwrite($fp,$data);
			$masterindex[$fname]["stop"]=ftell($fp);
			$sermasterindex=serialize($masterindex);
			fwrite($mi,$sermasterindex);
			fclose($fp);
			fclose($mi);
		}
	}
}

?>
