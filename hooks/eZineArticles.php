<?php
class eZineArticles implements HookBase
{
    function EnrichContent($content,$keyword,$args)
	{
	    $articles_url = $this->grab_url($keyword);
	    $article_parts = $this->html2text($articles_url[0]);
	    $title = $article_parts[0];
	    $body = $article_parts[1];
            return $title.$body;
	}

//grab the urls
    function grab_url($keyword,$numofarti=100) {
	$query = urlencode($keyword);

	$url = 'http://www.google.com/custom?num='.$numofarti.'&hl=en&client=pub-3754405753000444&cof=FORID:10%3BAH:left%3BCX:EzineArticles%2520Top%2520Search%3BL:http://www.google.com/intl/en/images/logos/custom_search_logo_sm.gif%3BLH:30%3BLP:1%3BLC:%230000ff%3BVLC:%23663399%3BDIV:%23336699%3B&cx=partner-pub-3754405753000444:3ldnyrvij91&ad=w9&adkw=AELymgWEYVAEJiFoI_QbIJiuiFtPWIsq0CUDRBk2ojriJqQ4mvGj4JogyItjPzxDik4ilHAo_bSVKuK_Jg3GXrOcTJM-pwfCmEkaILXT6jsRaAgIYsoy4uc&channel=4551525989&boostcse=0&oe=ISO-8859-1&ei=T2F3ToK6HcueiAf60fG2DQ&q='.$query.'&start=0&sa=N';

	$html = file_get_html($url);

	$filterone = explode("a class=", $html);
	$totalurl = array();
	for ($i=1; $i<count($filterone); $i++) {
	    $temarr = explode('"', $filterone[$i]);
	    $totalurl[$i-1] = $temarr[3];
	}
	shuffle($totalurl);
	return $totalurl;
    }

    function html2text($url) {
	$data = file_get_contents($url);

	//$data = str_ireplace("</","<",$data);
	preg_match_all('/\<title\>(.*?)\<\/title\>/s', $data, $titleMatch);
	$title = $titleMatch[1][0];

	preg_match_all('/\<div id\=[\"]article-content[\"]\>(.*?)\<\/div\>/s', $data, $matches);
	$content = $matches[1][0];

	$final = strip_tags($content,"<p><strong><br><h1><h2><h3><h4><em><u><ul><li><img><b>");

	$artDetail = array();
	$artDetail[0] = $title;
	$artDetail[1] = $final;

	return $artDetail;

    }
}

?>
