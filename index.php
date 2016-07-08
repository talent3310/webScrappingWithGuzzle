<?php
require "vendor/autoload.php";

//==============================================================================================
// create the objects and initialize
//==============================================================================================
$base_url = "http://agency.governmentjobs.com/kingcounty/";
$allJobs = array(); //main array, this stores all jobs
$client = new \GuzzleHttp\Client(); // create guzzle

$main_part_url = 'default.cfm?action=jobs&sortBy=&sortByASC=ASC&bHideSearchBox=1&PROMOTIONALJOBS=0&TRANSFER=0&SEARCHAPPLIED=0';//  the main page part url
//--scrape the data from the main page
try {
	$res = $client->request('GET', $base_url . $main_part_url , [
	    'auth' => ['user', 'pass']
	]);
} catch( \GuzzleHttp\Exception\ClientException $e ) {
         echo "Error: ".$e->getCode()." ".$e->getMessage();
}

//--get the html body content
$response_html = $res->getBody()->getContents();

//--get the links related each page. 
$job_links = extract_links($response_html);

$number = 0; // array index number of main array

//==============================================================================================
// get the result array: main array - $allJobs, the child of main array - $data
//==============================================================================================
foreach ($job_links as &$value) {
 	$data = array(); // child array of main one
	$job_links_decode = str_replace('&amp;', '&', $value); // link decode, &amp replace to '&'
	$detail_page_url = $base_url . $job_links_decode;

	//--get the title, salary, location, department, summary, duties, ex_qu_kn_sk, supplemental
	try {
	    $each_PageRes = $client->request('GET', $detail_page_url , [
	    	'auth' => ['user', 'pass']
		]);
	} catch( \GuzzleHttp\Exception\ClientException $e ) {
        echo "Error: ".$e->getCode()." ".$e->getMessage();
	}

	//--get the html body content
    $each_PageHtml = $each_PageRes->getBody()->getContents();

	$doc = new \DOMDocument();
    libxml_use_internal_errors(true);

    $doc->loadHTML($each_PageHtml);
    $xpath = new \DOMXPath($doc);

    //--get the title, salary, location, department
    $nodes = $xpath->query("//table[@summary = 'Job Information']");
		
	$num = 0; 
    foreach ($nodes->item(0)->getElementsByTagName('td') as $a) {
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

	//--get the summary, duties, ex_qu_kn_sk, supplemental
	$nodes = $xpath->query("//table[@summary = '']//tr//td[@class='jobdetail']"); // find the table which class is jobdetail

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

	//--get the Benefits
    $benefits_url_part =  str_replace("hit_count","ViewBenefits",$job_links_decode);
    $benefits_detail_page_url = $base_url . $benefits_url_part;

    //--get the Benefits contents
    try {
		$benefits_PageRes = $client->request('GET', $benefits_detail_page_url , [
	    	'auth' => ['user', 'pass']
		]);
	} catch( \GuzzleHttp\Exception\ClientException $e ) {
        echo "Error: ".$e->getCode()." ".$e->getMessage();
	}

	$benefits_PageHtml = $benefits_PageRes->getBody()->getContents(); 

	$doc = new \DOMDocument();
    $doc->loadHTML($benefits_PageHtml);

    $xpath = new \DOMXPath($doc);
    $nodes = $xpath->query("//table[@summary = '']//tr//td[@class='jobdetail']"); // find the td of table which class is jobdetail
    $data["benefits"] = $nodes->item(0)->nodeValue;// value insert to child array
   	
   	$allJobs[$number] = $data; // insert the child array into parent array  
   	$number ++;	
}

var_dump($allJobs);// display the scrapped data from the http://agency.governmentjobs.com/kingcounty/

//==============================================================================================
// save the result to the xml file.
//==============================================================================================
$xml_data = new SimpleXMLElement('<?xml version="1.0"?><items></items>');

array_to_xml($allJobs, $xml_data);

$dom = new DOMDocument('1.0');
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;
$dom->loadXML($xml_data->asXML());

$xml_data = new SimpleXMLElement($dom->saveXML());
$xml_data->asXML('result.xml');
//--dislpay finish
echo "[Finished]\n";

//==============================================================================================
//functions definition
//==============================================================================================
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

function array_to_xml( $data, &$xml_data ) {
    foreach( $data as $key => $value ) {
        if( is_array($value) ) {
            if( is_numeric($key) ){
                $key = 'item'; //dealing with <0/>..<n/> issues
            }
            $subnode = $xml_data->addChild($key);
            array_to_xml($value, $subnode);
        } else {
            $xml_data->addChild("$key",strip_garbage_characters(htmlspecialchars("$value")));
        }
    }
}

function strip_garbage_characters($string) {
    $strip = ['–' => '-', "’" => "'"];
    foreach($strip as $key => $value) {
        $string = str_replace($key, $value, $string);
    }
    return $string;
}
