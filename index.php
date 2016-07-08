<?php
require "vendor/autoload.php";

use Symfony\Component\DomCrawler\Crawler;

$base_url = "http://agency.governmentjobs.com/kingcounty/";
$allJobs = array();

$client = new \GuzzleHttp\Client();

$res = $client->request('GET', 'http://agency.governmentjobs.com/kingcounty/default.cfm?action=jobs&sortBy=&sortByASC=ASC&bHideSearchBox=1&PROMOTIONALJOBS=0&TRANSFER=0&SEARCHAPPLIED=0', [
    'auth' => ['user', 'pass']
]);


$response_html = $res->getBody()->getContents();
// ->find('table.NEOGOV_joblist');
$job_links = extract_links($response_html);

$number = 0;

foreach ($job_links as &$value) {
 	$data = array();
	$job_links_decode = str_replace('&amp;', '&', $value);
	$detail_page_url = 'http://agency.governmentjobs.com/kingcounty/' . $job_links_decode;

	//--get the title, salary, location, department, summary, duties, ex_qu_kn_sk, supplemental
    $each_PageRes = $client->request('GET', $detail_page_url , [
    	'auth' => ['user', 'pass']
	]);
    $each_PageHtml = $each_PageRes->getBody()->getContents();
	// echo $each_PageHtml;

	$doc = new \DOMDocument();
    libxml_use_internal_errors(true);

    $doc->loadHTML($each_PageHtml);
    $xpath = new \DOMXPath($doc);
    	//--get the title, salary, location, department
    $nodes = $xpath->query("//table[@summary = 'Job Information']");

	foreach ($nodes as $node) {
		
		$num = 0; 
	    foreach ($node->getElementsByTagName('td') as $a) {
	      // echo $a->nodeValue. "\n";
	      if($num == 0)
	      		$data["title"] =  $a->nodeValue;	      
	      if($num == 3)
	      		$data["salary"] =  $a->nodeValue;	      
	      if($num == 5)
	      		$data["location"] =  $a->nodeValue;	      
	      if($num == 6)
	      		$data["department"] =  $a->nodeValue;
	      
	      $num ++ ;
	    }

	   
	}
		//--get the summary, duties, ex_qu_kn_sk, supplemental
	$nodes = $xpath->query("//table[@summary = '']//tr//td[@class='jobdetail']");
	$num = 0;
	foreach ($nodes as $node) {
		if($num == 0)
			$data["summary"] = $node->nodeValue;
		if($num == 1)
			$data["ex_qu_kn_sk"] = $node->nodeValue;
		if($num == 2)
			$data["summary"] = $node->nodeValue;
		if($num == 3)
			$data["supplemental"] = $node->nodeValue;
			$num  ++;
	
	}

	//--get Benefits
	
    $benefits_url_part =  str_replace("hit_count","ViewBenefits",$job_links_decode);
    $detail_page_url = 'http://agency.governmentjobs.com/kingcounty/' . $benefits_url_part;
	$benefits_PageRes = $client->request('GET', $detail_page_url , [
    	'auth' => ['user', 'pass']
	]);

	$benefits_PageHtml = $benefits_PageRes->getBody()->getContents();

	$doc = new \DOMDocument();
    $doc->loadHTML($benefits_PageHtml);
    $xpath = new \DOMXPath($doc);
    	//--get the title, salary, location, department
    $nodes = $xpath->query("//table[@summary = '']//tr//td[@class='jobdetail']");
    $data["benefits"] = $nodes->item(0)->nodeValue;
   	
   	$allJobs[$number] = $data;
   	$number ++;	
}

var_dump($allJobs);


function extract_links($content) {
    $regexp = "<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>";
    $links = array();
    if(preg_match_all("/$regexp/siU", $content, $matches)) {
        foreach($matches[2] as $link) {
             if(substr($link, 0, 7) == 'default')
            	array_push($links,$link);
        }
    }
    return $links;
}
