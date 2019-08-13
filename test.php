<?php

phpinfo();

exit;
include_once("lib/init.php");
include_once("lib/lib.php");
include_once("lib/shopdata.php");

$orStr = implode( ',', $orInsertArr );
$prStr = implode( ',', $prInsertArr );

$ordercode = "2019080714585282995A";

if(ord($ordercode)) {
    # select table의 컬럼을 지정해준다
    $orStr = implode( ',', $orInsertArr );
    $prStr = implode( ',', $prInsertArr );
    //pmysql_query("INSERT INTO tblorderinfo SELECT * FROM tblorderinfotemp WHERE ordercode='{$ordercode}'",get_db_conn());
    pmysql_query("INSERT INTO tblorderinfo ( ".$orStr." ) ( SELECT ".$orStr." FROM tblorderinfotemp WHERE ordercode='{$ordercode}' ) ",get_db_conn());
    if (pmysql_errno()!=1062) $pmysql_errno+=pmysql_errno();
    //pmysql_query("INSERT INTO tblorderproduct SELECT * FROM tblorderproducttemp WHERE ordercode='{$ordercode}'",get_db_conn());
    pmysql_query("INSERT INTO tblorderproduct ( ".$prStr." ) ( SELECT ".$prStr." FROM tblorderproducttemp WHERE ordercode='{$ordercode}' ) ",get_db_conn());
    if (pmysql_errno()!=1062) $pmysql_errno+=pmysql_errno();
    pmysql_query("INSERT INTO tblorderoption SELECT * FROM tblorderoptiontemp WHERE ordercode='{$ordercode}'",get_db_conn());
    if (pmysql_errno()!=1062) $pmysql_errno+=pmysql_errno();
    if($pmysql_errno) $okmail="YES";
}

$sql="UPDATE tblorderinfo SET regdt='".date('YmdHis')."' WHERE ordercode='{$ordercode}'";
pmysql_query($sql,get_db_conn());

$update_infos = $fldb->updateData(array(
    'tblorderinfo',
    array(
        'oi_step1' => '1'
    ),
    array('ordercode' => $ordercode)
)); 
$update_products = $fldb->updateData(array(
    'tblorderproduct',
    array(
        'op_step'     => '1'
    ),
    array('ordercode' => $ordercode)
)); 

exit;

$var = "https://accounts.kakao.com/login?continue=https://kauth.kakao.com/oauth/authorize?response_type%3Dcode%26client_id%3D0c60ce0d0a832c38bb2b1a2ddd13c98a%26redirect_uri%3Dhttps%253A%252F%252Fwww.idus.com%252Fw%252Fkakao%252Flogin_callback%26state%3D5d4949ec49d32";
echo urldecode($var);
exit;
/*
 * 뿌리오 발송API 경로 - 서버측 인코딩과 응답형태에 따라 선택
 */
$_api_url = 'https://www.ppurio.com/api/send_utf8_json.php';     // UTF-8 인코딩과 JSON 응답용 호출 페이지
// $_api_url = 'https://www.ppurio.com/api/send_utf8_xml.php';   // UTF-8 인코딩과 XML 응답용 호출 페이지
// $_api_url = 'https://www.ppurio.com/api/send_utf8_text.php';  // UTF-8 인코딩과 TEXT 응답용 호출 페이지
// $_api_url = 'https://www.ppurio.com/api/send_euckr_json.php'; // EUC-KR 인코딩과 JSON 응답용 호출 페이지
// $_api_url = 'https://www.ppurio.com/api/send_euckr_xml.php';  // EUC-KR 인코딩과 XML 응답용 호출 페이지
// $_api_url = 'https://www.ppurio.com/api/send_euckr_text.php'; // EUC-KR 인코딩과 TEXT 응답용 호출 페이지


/*
 * 요청값
 */
$_param['userid'] = 'flescompany';           // [필수] 뿌리오 아이디
$_param['callback'] = '01050681090';    // [필수] 발신번호 - 숫자만
$_param['phone'] = '01027642111';       // [필수] 수신번호 - 여러명일 경우 |로 구분 '010********|010********|010********'
$_param['msg'] = '테스트 발송입니다';   // [필수] 문자내용 - 이름(names)값이 있다면 [*이름*]가 치환되서 발송됨
//$_param['names'] = '홍길동';            // [선택] 이름 - 여러명일 경우 |로 구분 '홍길동|이순신|김철수'
//$_param['appdate'] = '20190502093000';  // [선택] 예약발송 (현재시간 기준 10분이후 예약가능)
//$_param['subject'] = '테스트';          // [선택] 제목 (30byte)


$_curl = curl_init();
curl_setopt($_curl,CURLOPT_URL,$_api_url);
curl_setopt($_curl,CURLOPT_POST,true);
curl_setopt($_curl,CURLOPT_SSL_VERIFYPEER,false);
curl_setopt($_curl,CURLOPT_RETURNTRANSFER,true);
curl_setopt($_curl,CURLOPT_POSTFIELDS,$_param);
$_result = curl_exec($_curl);
curl_close($_curl);

$_result = json_decode($_result);

print_r($_result);


exit;
$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" ?><rss version="2.0" />');

include_once 'lib/flDbo.php';
$dbo = new flDbo();
$dbo->dbConnect();

$list = $dbo->fetchList(
    "select * from tblproduct where display = $1 order by pridx desc limit 1", 
    array('Y')
);

$x_channel = $xml->addChild('channel');
$x_channel->addChild('title', 'JDX 멀티스포츠- 라이프스타일');
$x_channel->addChild('link', 'jdx.co.kr');
$x_channel->addChild('description', 'JDX 상품을 판매하는 공식페이지입니다.');

$item = $x_channel->addChild('item');
//$item->addChild('g:id', $list[0]->productcode);
foreach ($list as $value) {
    $item->addChild('g:id', $value->productcode);
    $item->addChild('g:title', $value->productname);
    $item->addChild('g:description', $value->productname);
    $item->addChild('g:google_product_category', 'Women’s > Shoes > Working Boots');
    $item->addChild('g:link', urlencode('jdx.co.kr/front/productdetail.php?productcode='.$value->productcode));
    $item->addChild('g:image_link', urlencode('jdx.co.kr/data/shopimages/product/'.$value->maximage));
    $item->addChild('g:additional_image_link', urlencode('jdx.co.kr/data/shopimages/product/'.$value->tinyimage));
    $item->addChild('g:availability', "재고");
    $item->addChild('g:price', $value->consumerprice);
    $item->addChild('g:sale_price', $value->sellprice);
    $item->addChild('g:gtin', '신한코리아');
    $item->addChild('g:mpn', '신한코리아');
    $item->addChild('g:brand', 'jdx');
    $item->addChild('g:product_type', 'WOMAN(OUTLET) > 니트/가디건');
    $item->addChild('g:product_type_key', 'XY1 &gt; YZ1 &gt; Z1');
    $item->addChild('g:number_of_review', '12');
    $item->addChild('g:product_rating', '4.5');
    $item->addChild('g:filter', '색상 = 적색|흑색, 사이즈 = XL, 화면 크기= 15');
    $item->addChild('g:adult', '아니오');
}








Header('Content-Type: text/xml');
echo $xml->asXML(); 
exit;


//$xml = new SimpleXMLElement('<SABANG_CATEGORY_LIST />');
//$x_members = $xml->addChild('HEADER');
//$x_members->addChild('SEND_COMPAYNY_ID', 'jdx123');
//$x_members->addChild('SEND_AUTH_KEY', 'N0SWd2REb6rTEx0CM83r6FNMZSr8MNJbZEx');
//$x_members->addChild('SEND_DATE', date('Ymd'));
//Header('Content-Type: application/xml');
//print($xml->asXML());
//exit;


//header("Content-Type: application/rss+xml");
//header('Content-Type: text/xml');
//echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<rss version="2.0">
    <channel>
        
    </channel>
</rss>