<?php
include_once('./outline/header_m.php');
include_once($Dir."lib/jungbo_code.php"); //정보고시 코드를 가져온다
include_once($Dir."conf/npay.php");


# 시중가 대비 할인가 % 2016-02-02 유동혁
function get_price_percent( $consumerprice, $sellprice ){
    $per = round( ( ( $consumerprice - $sellprice ) / $consumerprice ) * 100 );
    return $per;
}


// 쿠폰의 할인 / 적립 text를 반환
function CouponText( $sale_type ){

    $text_arr = array(
        'text'=>'',
        'won'=>''
    );

    switch( $sale_type ){
        case '1' :
            $text_arr['text'] = '적립';
            $text_arr['won'] = '%';
            break;
        case '2' :
            $text_arr['text'] = '할인';
            $text_arr['won'] = '%';
            break;
        case '3' :
            $text_arr['text'] = '적립';
            $text_arr['won'] = '원';
            break;
        case '4' :
            $text_arr['text'] = '할인';
            $text_arr['won'] = '원';
            break;
        default :
            break;
    } //switch

    return $text_arr;
}

$prod_cate_code = $_REQUEST["code"];
$productcode    = $_REQUEST["productcode"];
$sort           = $_REQUEST["sort"];
$brandcode      = $_REQUEST["brandcode"]+0;
$_cdata         = "";
$_pdata         = "";
$staff_yn       = $_ShopInfo->staff_yn;

$get_product_code = $_GET['productcode'];
$get_product_idx = "SELECT pridx FROM tblproduct WHERE productcode = '{$get_product_code}' ";
$get_product_idx_result = pmysql_query($get_product_idx);
$get_product_idx_row = pmysql_fetch_object($get_product_idx_result);
$product_idx = $get_product_idx_row->pridx;


if( $staff_yn == '' ) $staff_yn = 'N';

if( strlen($productcode) > 0 ) {

    $sql = "
        SELECT
        a.*,b.c_maincate,b.c_category
        FROM tblproductcode a
        ,tblproductlink b
        WHERE a.code_a||a.code_b||a.code_c||a.code_d = b.c_category
        AND c_maincate = 1
        AND group_code = ''
        AND c_productcode = '{$productcode}'
    ";
    //exdebug($sql);
    $result=pmysql_query($sql,get_db_conn());
    while($row=pmysql_fetch_object($result)){
        if($row->c_maincate == 1){
            $mainCate = $row;
        }
        $cateProduct[] = $row;
    }

    if($cateProduct) {
        if($mainCate) $_cdata=$mainCate;
        else $_cdata=$cateProduct[0];
        if(count($cateProduct)==0 || !$cateProduct){
            $group_sql = "
                SELECT
                a.group_code
                FROM tblproductcode a
                ,tblproductlink b
                WHERE a.code_a||a.code_b||a.code_c||a.code_d = b.c_category
                AND group_code != ''
                AND c_productcode = '{$productcode}'
                GROUP BY a.group_code
            ";
            $gruop_res = pmysql_query($group_sql,get_db_conn());
            while($gruop_row = pmysql_fetch_object($gruop_res)){
                if($row->group_code=="ALL" && strlen($_ShopInfo->getMemid())==0) {  //회원만 접근가능
                    Header("Location:/");
                    exit;
                }else if(ord($row->group_code) && $row->group_code!="ALL" && $row->group_code!=$_ShopInfo->getMemgroup()) { //그룹회원만 접근
                    alert_go('해당 분류의 접근 권한이 없습니다.',-1);
                }
            }
            alert_go('판매가 종료된 상품입니다.',"/");
        }

        //Wishlist 담기
        if($mode=="wishlist") {
            if(strlen($_ShopInfo->getMemid())==0) { //비회원
                alert_go('로그인을 하셔야 본 서비스를 이용하실 수 있습니다.',$Dir.FrontDir."login.php?chUrl=".getUrl());
            }
            $sql = "SELECT COUNT(*) as totcnt FROM tblwishlist WHERE id='".$_ShopInfo->getMemid()."' ";
            $result2=pmysql_query($sql,get_db_conn());
            $row2=pmysql_fetch_object($result2);
            $totcnt=$row2->totcnt;
            pmysql_free_result($result2);
            $maxcnt=20;
            if($totcnt>=$maxcnt) {
                $sql = "SELECT b.productcode ";
                $sql.= "FROM tblwishlist a, view_tblproduct b ";
                $sql.= "LEFT OUTER JOIN tblproductgroupcode c ON b.productcode=c.productcode ";
                $sql.= "WHERE a.id='".$_ShopInfo->getMemid()."' AND a.productcode=b.productcode ";
                $sql.= "AND b.display='Y' ";
                $sql.= "AND (b.group_check='N' OR c.group_code='".$_ShopInfo->getMemgroup()."') ";
                $sql.= "GROUP BY b.productcode ";

                $result2=pmysql_query($sql,get_db_conn());
                $i=0;
                $wishprcode="";
                while($row2=pmysql_fetch_object($result2)) {
                    $wishprcode.="'{$row2->productcode}',";
                    $i++;
                }
                pmysql_free_result($result2);
                $totcnt=$i;
                $wishprcode=substr($wishprcode,0,-1);
                if(ord($wishprcode)) {
                    $sql = "DELETE FROM tblwishlist WHERE id='".$_ShopInfo->getMemid()."' AND productcode NOT IN ({$wishprcode}) ";
                    pmysql_query($sql,get_db_conn());
                }
            }
            if($totcnt<$maxcnt) {
                $sql = "SELECT COUNT(*) as cnt FROM tblwishlist WHERE id='".$_ShopInfo->getMemid()."' AND productcode='{$productcode}' ";
                $result2=pmysql_query($sql,get_db_conn());
                $row2=pmysql_fetch_object($result2);
                $cnt=$row2->cnt;
                pmysql_free_result($result2);
                if($cnt>0) {
                    alert_go('WishList에 이미 등록된 상품입니다.',-1);
                } else {
                    $sql = "INSERT INTO tblwishlist (
                    id          ,
                    productcode ,
                    date        ) VALUES (
                    '".$_ShopInfo->getMemid()."',
                    '{$productcode}',
                    '".date("YmdHis")."')";
                    pmysql_query($sql,get_db_conn());
                    alert_go('WishList에 해당 상품을 등록하였습니다.',-1);
                }
            } else {
                alert_go("WishList에는 {$maxcnt}개 까지만 등록이 가능합니다.\\n\\nWishList에서 다른 상품을 삭제하신 후 등록하시기 바랍니다.",-1);
            }
        }
    } else {
        alert_go('해당 분류가 존재하지 않습니다.',"/");
    }
    pmysql_free_result($result);

    $sql = "SELECT * ";
    $sql.= "FROM tblproduct ";
    $sql.= "WHERE productcode='{$productcode}' ";
    $sql.= "AND display='Y' ";

    $result=pmysql_query($sql,get_db_conn());

    if($row=pmysql_fetch_object($result)) {
        $_pdata=$row;
        $_pdata->brand += 0;
        $sql = "SELECT * FROM tblproductbrand ";
        $sql.= "WHERE bridx='{$_pdata->brand}' ";
        $bresult=pmysql_query($sql,get_db_conn());
        $brow=pmysql_fetch_object($bresult);
        $_pdata->brandcode = $_pdata->brand;
        $_pdata->brand = $brow->brandname;
        $oldprice = $_pdata->sellprice;
        include_once '../lib/PriceSetClass.php';
        $pcs = new PriceSetClass();
        //if($_SERVER['REMOTE_ADDR'] == '121.170.154.241' || $_SERVER['REMOTE_ADDR'] == '1.215.33.150'){
            $pro_sale = $pcs->saleCheck($_pdata->productcode);
            
            $newprice = '';
            $salemode = '';
            $coupon_txt = '';
            if($pro_sale->productcode && ( $pro_sale->couponset == 'A' || $pro_sale->couponset == 'M') ){
                if($pro_sale->saletype == 'w'){
                    $newprice = $_pdata->sellprice - $pro_sale->saleprice;
                    $coupon_txt = $pro_sale->saleprice.'원 할인';
                }else{
                    $percentprice = ($pro_sale->saleprice/100) * $_pdata->sellprice;
                    $newprice = $_pdata->sellprice - $percentprice;
                    $coupon_txt = $pro_sale->saleprice.'% 할인';
                }

                if(strlen( $_ShopInfo->getMemid() ) > 0){
                    $_pdata->sellprice = $newprice;
                }
                $salemode = 'activ';
            }
        //}

        pmysql_free_result($result);

        if($_pdata->assembleuse=="Y") {
            $sql = "SELECT * FROM tblassembleproduct ";
            $sql.= "WHERE productcode='{$productcode}' ";
            $result=pmysql_query($sql,get_db_conn());
            if($row=@pmysql_fetch_object($result)) {
                $_adata=$row;
                pmysql_free_result($result);
                $assemble_list_pridx = str_replace("","",$_adata->assemble_list);

                if(ord($assemble_list_pridx)) {
                    $sql = "SELECT pridx,productcode,productname,sellprice,quantity,tinyimage FROM tblproduct ";
                    $sql.= "WHERE pridx IN ('".str_replace(",","','",trim($assemble_list_pridx,','))."') ";
                    $sql.= "AND assembleuse!='Y' ";
                    $sql.= "AND display='Y' ";
                    $result=pmysql_query($sql,get_db_conn());
                    while($row=@pmysql_fetch_object($result)) {
                        $_acdata[$row->pridx] = $row;
                    }
                    pmysql_free_result($result);
                }
            }
        }
    } else {
        alert_go('해당 상품 정보가 존재하지 않습니다.',-1);
    }

} else {
    alert_go('해당 상품 정보가 존재하지 않습니다.',"/");
}
# 상품상세 뷰 카운트 update 2016-01-26 유동혁
$vcnt_sql = "UPDATE tblproduct SET vcnt = vcnt + 1 WHERE productcode = '".$productcode."'";
pmysql_query( $vcnt_sql, get_db_conn() );


$ref=$_REQUEST["ref"];
if (ord($ref)==0) {
    $ref=strtolower(str_replace("http://","",$_SERVER["HTTP_REFERER"]));
    if(strpos($ref,"/") != false) $ref=substr($ref,0,strpos($ref,"/"));
}

if(ord($ref) && strlen($_ShopInfo->getRefurl())==0) {
    $sql2="SELECT * FROM tblpartner WHERE url LIKE '%{$ref}%' ";
    $result2 = pmysql_query($sql2,get_db_conn());
    if ($row2=pmysql_fetch_object($result2)) {
        pmysql_query("UPDATE tblpartner SET hit_cnt = hit_cnt+1 WHERE url = '{$row2->url}'",get_db_conn());
        $_ShopInfo->setRefurl($row2->id);
        $_ShopInfo->Save();
    }
    pmysql_free_result($result2);
}

$miniq = 1;
if (ord($_pdata->etctype)) {
    $etctemp = explode("",$_pdata->etctype);
    for ($i=0;$i<count($etctemp);$i++) {
        if (strpos($etctemp[$i],"MINIQ=")===0)          $miniq=substr($etctemp[$i],6);
        if (strpos($etctemp[$i],"DELIINFONO=")===0) $deliinfono=substr($etctemp[$i],11);
    }
}

//입점업체 정보 관련
if($_pdata->vender>0) {
    $sql = "SELECT a.vender, a.id, a.brand_name, a.deli_info, b.prdt_cnt ";
    $sql.= "FROM tblvenderstore a, tblvenderstorecount b ";
    $sql.= "WHERE a.vender='{$_pdata->vender}' AND a.vender=b.vender ";
    $result=pmysql_query($sql,get_db_conn());
    if(!$_vdata=pmysql_fetch_object($result)) {
        $_pdata->vender=0;
    }
    pmysql_free_result($result);
}

//배송/교환/환불정보 노출

$deli_info="";
if($deliinfono!="Y") {  //개별상품별 배송/교환/환불정보 노출일 경우
    $deli_info_data="";
    if( $_pdata->vender > 0 ) { //입점업체 상품이면 입점업체 배송/교환/환불정보 누출
        $tempvdeli_info = explode( "=", stripslashes( $_vdata->deli_info ) );
        if ( $_vdata->deli_info && $tempvdeli_info[0] == "Y" ) {
            $deli_info_data  = $_vdata->deli_info;
            if( is_file( $Dir.DataDir."shopimages/vender/aboutdeliinfo_{$_vdata->vender}_m.gif" ) ){
                $aboutdeliinfofile = $Dir.DataDir."shopimages/vender/aboutdeliinfo_{$_vdata->vender}_m.gif";
            } else if( is_file( $Dir.DataDir."shopimages/vender/aboutdeliinfo_{$_vdata->vender}.gif" ) ) {
                $aboutdeliinfofile = $Dir.DataDir."shopimages/vender/aboutdeliinfo_{$_vdata->vender}.gif";
            }

        } else {
            $deli_info_data    = $_data->deli_info;
            if( is_file( $Dir.DataDir."shopimages/etc/aboutdeliinfo_m.gif" ) ){
                $aboutdeliinfofile = $Dir.DataDir."shopimages/etc/aboutdeliinfo_m.gif";
            } else if( is_file( $Dir.DataDir."shopimages/etc/aboutdeliinfo.gif" ) ) {
                $aboutdeliinfofile = $Dir.DataDir."shopimages/etc/aboutdeliinfo.gif";
            }
        }
    } else {
        $deli_info_data    = $_data->deli_info;
        if( is_file( $Dir.DataDir."shopimages/etc/aboutdeliinfo_m.gif" ) ){
            $aboutdeliinfofile = $Dir.DataDir."shopimages/etc/aboutdeliinfo_m.gif";
        } else if( is_file( $Dir.DataDir."shopimages/etc/aboutdeliinfo.gif" ) ) {
            $aboutdeliinfofile = $Dir.DataDir."shopimages/etc/aboutdeliinfo.gif";
        }
    }
    if( ord( $deli_info_data ) ) {
        $tempdeli_info = explode( "=", stripslashes( $deli_info_data ) );
        if( $tempdeli_info[0] == "Y" ) {
            if( $tempdeli_info[1] == "TEXT" ) {     //텍스트형
                $allowedTags = "<h1><b><i><a><ul><li><pre><hr><blockquote><u><img><br><font>";

                if( ord( $tempdeli_info[2] ) || ord( $tempdeli_info[3] ) ) {
                    if(ord( $tempdeli_info[2] ) ) { //배송정보 텍스트
                        $deli_info .= " <div class='delivery_info'><dd>".nl2br(strip_tags($tempdeli_info[2],$allowedTags))."</dd></div>\n";
                    }
                    if( ord( $tempdeli_info[3] ) ) { //교환/환불정보 텍스트
                        $deli_info .= "  <dl class='delivery_info'><dd>".nl2br(strip_tags($tempdeli_info[3],$allowedTags))."</dd></dl>\n";
                    }
                }
            } else if( $tempdeli_info[1] == "IMAGE" ) { //이미지형
                if( file_exists( $aboutdeliinfofile ) ) {
                    $deli_info = "<img src=\"{$aboutdeliinfofile}\" align=absmiddle border=0>\n";
                }
            } else if( $tempdeli_info[1] == "HTML" ) {  //HTML로 입력
                if( ord( $tempdeli_info[3] ) ) {
                    $deli_info = "{$tempdeli_info[3]}\n";
                } else if( ord( $tempdeli_info[2] ) ) {
                    $deli_info = "{$tempdeli_info[2]}\n";
                }
            }
        }
    }
}

//리뷰관련 환경 설정
$reviewlist=$_data->ETCTYPE["REVIEWLIST"];
$reviewdate=$_data->ETCTYPE["REVIEWDATE"];
if(ord($reviewlist)==0) $reviewlist="N";

//상품QNA 게시판 존재여부 확인 및 설정정보 확인
$prqnaboard=getEtcfield($_data->etcfield,"PRQNA");
if(ord($prqnaboard)) {
    $sql = "SELECT * FROM tblboardadmin WHERE board='{$prqnaboard}' ";
    $result=pmysql_query($sql,get_db_conn());
    $qnasetup=pmysql_fetch_object($result);
    pmysql_free_result($result);
    if($qnasetup->use_hidden=="Y") $qnasetup=null;
}

//상품다중이미지 확인
$multi_img="N";
$sql2 ="SELECT * FROM tblmultiimages WHERE productcode='{$productcode}' ";
$result2=pmysql_query($sql2,get_db_conn());
if($row2=pmysql_fetch_object($result2)) {
    if($_data->multi_distype=="0") {
        $multi_img="I";
    } else if($_data->multi_distype=="1") {
        $multi_img="Y";
        $multi_imgs=array(&$row2->primg01,&$row2->primg02,&$row2->primg03,&$row2->primg04,&$row2->primg05,&$row2->primg06,&$row2->primg07,&$row2->primg08,&$row2->primg09,&$row2->primg10);
        $thumbcnt=0;
        for($j=0;$j<10;$j++) {
            if(ord($multi_imgs[$j])) {
                $thumbcnt++;
            }
        }
        $multi_height=430;
        $thumbtype=1;
        if($thumbcnt>5) {
            $multi_height=490;
            $thumbtype=2;
        }
    }
}
pmysql_free_result($result2);

//멀티 이미지 관련()2013-12-23 멀티 이미지 기능만 추가함. 확대보기 없음.

if($multi_img=="Y") {
    $imagepath=$Dir.DataDir."shopimages/multi/";
    //$dispos=$row->multi_dispos;
    // 멀티이미지 설정
    $changetype=$_data->multi_changetype;
    $bgcolor=$_data->multi_bgcolor;

    $sql = "SELECT * FROM tblmultiimages WHERE productcode='{$productcode}' ";
    $result=pmysql_query($sql,get_db_conn());
    if($row=pmysql_fetch_object($result)) {
        $multi_imgs = array(
            &$row->primg01,
            &$row->primg02,
            &$row->primg03,
            &$row->primg04,
            &$row->primg05,
            &$row->primg06,
            &$row->primg07,
            &$row->primg08,
            &$row->primg09,
            &$row->primg10
        );

        $tmpsize=explode("",$row->size);

        $insize="";
        $updategbn="N";

        $y=0;
        for($i=0;$i<10;$i++) {
            if(ord($multi_imgs[$i])) {
                $yesimage[$y]=$multi_imgs[$i];
                if(ord($tmpsize[$i])==0) {
                    if ( strpos("http://", $multi_imgs[$i]) === false ) {
                        $size=getimagesize($Dir.DataDir."shopimages/multi/".$multi_imgs[$i]);
                    }
                    $xsize[$y]=$size[0];
                    $ysize[$y]=$size[1];
                    $insize.="{$size[0]}X".$size[1];
                    $updategbn="Y";
                } else {
                    $insize.="".$tmpsize[$i];
                    $tmp=explode("X",$tmpsize[$i]);
                    $xsize[$y]=$tmp[0];
                    $ysize[$y]=$tmp[1];
                }
                $y++;
            } else {
                $insize.="";
            }
        }

        $makesize=$maxsize;
        for($i=0;$i<$y;$i++){
            if($xsize[$i]>$makesize || $ysize[$i]>$makesize) {
                if($xsize[$i]>=$ysize[$i]) {
                    $tempxsize=$makesize;
                    $tempysize=($ysize[$i]*$makesize)/$xsize[$i];
                } else {
                    $tempxsize=($xsize[$i]*$makesize)/$ysize[$i];
                    $tempysize=$makesize;
                }
                $xsize[$i]=$tempxsize;
                $ysize[$i]=$tempysize;
            }
        }
        if($updategbn=="Y"){
            $sql = "UPDATE tblmultiimages SET size='".ltrim($insize,'')."' ";
            $sql.= "WHERE productcode='{$productcode}'";
            pmysql_query($sql,get_db_conn());
        }

        pmysql_free_result($result);
    }
}


# 상품 이미지 path
$imagepath_product = $Dir.DataDir.'shopimages/product/';
$imagepath_multi = $Dir.DataDir.'shopimages/multi/';
if(strpos($_pdata->maximage, "http://") === false) {
    $width= GetImageSize( $imagepath_product.$_pdata->maximage );
}

# 해당 유저에 맞는 상품 메뉴를 가져옴
$cateSql = "
    SELECT code_a||code_b||code_c||code_d AS prcode, code_name
    FROM tblproductcode
    WHERE type = 'LMX'
    AND code_a = '".substr($_cdata->c_category, 0, 3)."'
    AND code_b = '".substr($_cdata->c_category, 3, 3)."'
    ORDER BY cate_sort ASC
";
$cateRes = pmysql_query( $cateSql, get_db_conn() );
while( $cateRow = pmysql_fetch_array( $cateRes ) ){
    $cateLoc[] = $cateRow;
}
pmysql_free_result( $cateRes );
//$thisCate = getCodeLoc3( $_cdata->c_category );
//$thisCate = getDecoCodeLoc( $_pdata->productcode );
$optionNames = explode( '@#', $_pdata->option1 );
$option_depth = count( $optionNames );

$addOptionNames = explode( '@#', $_pdata->option2 );
$addOption_tf = explode( '@#', $_pdata->option2_tf );
$addOption_maxlen = explode( '@#', $_pdata->option2_maxlen );

#####색상정보 가져오기(같은 코드 상품들)######
$p_code = $_pdata->model_code;
//exdebug($productcode);
if($p_code){
    $p_product="";
    $p_sql = " select productcode,minimage,tinyimage, over_minimage from tblproduct ";
    $p_sql .= " where 1=1 AND model_code = '{$p_code}' AND productcode not in ('{$productcode}')  AND display ='Y' ";
    $p_result = pmysql_query($p_sql,get_db_conn());
    while($p_row = pmysql_fetch_object($p_result) ){
        $p_product[] = $p_row;
    }
    //exdebug($p_product);
}
######################################

#연관상품
//상품의 조회순 , 등록날짜로 10개
$related_sql = "WITH related AS ( SELECT c_productcode  FROM tblproductlink  WHERE c_category like '". substr($_cdata->c_category, 0, 9) ."%' ";
$related_sql.= " AND c_maincate = 1 GROUP BY c_productcode ) ";
$related_sql.= "SELECT pr.productcode, pr.productname, pr.sellprice, ";
$related_sql.= "pr.consumerprice, pr.buyprice, pr.brand, pr.maximage, ";
$related_sql.= "pr.minimage, pr.tinyimage, pr.mdcomment, pr.review_cnt, ";
$related_sql.= "pr.icon, pr.soldout, pr.quantity, pr.over_minimage FROM tblproduct pr ";
$related_sql.= "JOIN related r ON pr.productcode = r.c_productcode ";
$related_sql.= "WHERE pr.productcode <> '{$productcode}' "; // 현재 자신은 제외
$related_sql.= "AND pr.display = 'Y' ";

// ================================================================
// 승인대기중인 브랜드에 속한 상품은 리스트에서 제외처리
// ================================================================
$sub_sql = "SELECT b.bridx FROM tblvenderinfo a JOIN tblproductbrand b ON a.vender = b.vender WHERE a.delflag='N' AND a.disabled='1' ";
$sub_result = pmysql_query($sub_sql);

$arrNotAllowedBrandList = array();
while ( $sub_row = pmysql_fetch_object($sub_result) ) {
    array_push($arrNotAllowedBrandList, $sub_row->bridx);
}
pmysql_free_result($sub_result);

if ( count($arrNotAllowedBrandList) >= 1 ) {
    $related_sql .= "AND pr.brand not in ( " . implode(",", $arrNotAllowedBrandList) . " ) ";
}

$related_sql.= "ORDER BY pr.vcnt DESC, date DESC LIMIT 6 ";

$related_html = productlist_print( $related_sql, 'W_016' );

#상품정보고시
// 2016 01 13 유동혁
$jungbo_option = explode( '||', $_pdata->sabangnet_prop_option );
$jungbo_val = explode( '||', $_pdata->sabangnet_prop_val );
//$jungbo_cnt = strlen( str_replace( '||', '',$_pdata->sabangnet_prop_val ) );
//정보고시 내용 없으면 노출안되도록 (앞에 3자리 코드 자르고 || 구분자로 배열변경) 2016-03-07
$jungbo_arr = explode("||",substr($_pdata->sabangnet_prop_val,'3'));
$jungbo_cnt=0;
//정보고시 내용이 빈값인지 체크 2016-03-07
foreach($jungbo_arr as $jk){
    if($jk) $jungbo_cnt++;
}
$jungbo_title = $jungbo_code[$jungbo_option[0]]['title'];

#상품의 메인 브랜드 정보
$brand_sql   = "SELECT bridx FROM tblbrandproduct WHERE productcode = '".$_pdata->productcode."' ORDER BY sort ASC LIMIT 1";
list($brand_code) = pmysql_fetch($brand_sql);

$brand_name = "";
if ( !empty($brand_code) ) {
    $brand_sql = " SELECT bridx, brandname, vender FROM tblproductbrand WHERE bridx = ";
    $brand_sql.= "( SELECT bridx FROM tblbrandproduct WHERE productcode = '".$_pdata->productcode."' ORDER BY sort ASC LIMIT 1 )";
    $brand_res = pmysql_query( $brand_sql, get_db_conn() );
    $brand_row = pmysql_fetch_object( $brand_res );
    $brand_code = $brand_row->bridx;
    //$brand_vender = $brand_row->vender;
    $brand_name = $brand_row->brandname;
    pmysql_free_result( $brand_res );
}

// ======================================================================================
// 브랜드 정보 조회
// ======================================================================================

$brand_desc = "";
if ( !empty($brand_code) ) {
    $sql  = "SELECT a.*, b.brandname ";
    $sql .= "FROM tblvenderinfo_add a LEFT JOIN tblproductbrand b ON a.vender = b.vender ";
    $sql .= "WHERE a.vender = '".$_pdata->vender."' ";
    $row  = pmysql_fetch_object(pmysql_query($sql));

    $brand_desc = $row->description;
}

// 롤링할 이미지
$arrRollingBannerImg = array();
for ( $i = 1; $i <= 10; $i++ ) {
    $varName = "b_img" . $i;

    if ( !empty($row->$varName) ) {
        array_push($arrRollingBannerImg, $row->$varName);
    }
}

// ======================================================================================
// 찜한 리스트(로그인한 상태인 경우)
// ======================================================================================
$arrBrandWishList = array();
$onBrandWishClass = "";
if (strlen($_ShopInfo->getMemid()) > 0) {
    $sql  = "SELECT a.bridx, b.brandname ";
    $sql .= "FROM tblbrandwishlist a LEFT JOIN tblproductbrand b ON a.bridx = b.bridx ";
    $sql .= "WHERE id = '" . $_ShopInfo->getMemid() . "' ";
    $sql .= "ORDER BY wish_idx desc ";

    $result = pmysql_query($sql);
    while ($row = pmysql_fetch_array($result)) {
        $arrBrandWishList[$row['bridx']] = $row['brandname'];

        // 내가 찜한 브랜드인 경우
        if ( $row['bridx'] == $bridx ) {
            $onBrandWishClass = "on";
        }
    }
}

// ======================================================================================
// 관련 프로모션 정보
// ======================================================================================

// 기획전 중에서 현재 진행중인것들을 조회
$sql  = "SELECT a.special_list, c.idx, c.title ";
$sql .= "FROM tblspecialpromo a ";
$sql .= "   LEFT JOIN tblpromotion b ON a.special::integer = b.seq ";
$sql .= "   LEFT JOIN tblpromo c ON b.promo_idx = c.idx ";
$sql .= "WHERE c.display_type in ('A', 'P') and current_date <= c.end_date ";
$sql .= "ORDER BY c.rdate desc ";

$result = pmysql_query($sql);

$bLoopBreak = false;
$limitCount = 2;
$arrPromotionIdx = array();
$arrPromotionTitle = array();
while ($row = pmysql_fetch_array($result)) {
    $special_list   = str_replace(",", "','", $row['special_list']);
    $promo_idx      = $row['idx'];
    $promo_title    = $row['title'];

    // 해당 브랜드에 속한 상품 리스트 조회

    if ( !empty($brand_code) ) {
        $sub_sql  = "SELECT count(*) ";
        $sub_sql .= "FROM tblbrandproduct ";
        $sub_sql .= "WHERE bridx = {$brand_code} AND productcode in ( '{$special_list}' ) ";
        $sub_sql .= "LIMIT 1 ";

        $sub_row  = pmysql_fetch_object(pmysql_query($sub_sql));

        if ( $sub_row->count >= 1 ) {
            if ( !in_array($promo_idx, $arrPromotionIdx) ) {
                array_push($arrPromotionIdx, $promo_idx);
                array_push($arrPromotionTitle, $promo_title);
            }

            if (count($arrPromotionIdx) >= $limitCount) { break; }
        }
    }
}



#위시리스트 정보
$wish_row->cnt = 0;
if ( strlen( $_ShopInfo->getMemid() ) > 0 ) {
    $wish_sql = "SELECT COUNT(*) AS cnt FROM tblwishlist WHERE productcode = '".$_pdata->productcode."' AND id = '".$_ShopInfo->getMemid()."'";
    $wish_res = pmysql_query( $wish_sql, get_db_conn() );
    $wish_row = pmysql_fetch_object( $wish_res );
    pmysql_free_result( $wish_res );
}
if( $wish_row->cnt > 0 ) $wishlist_class = 'on';
else $wishlist_class = '';

# 최근 상품 프로모션
$promo_sql =" SELECT pm.idx, pm.title, pm.rdate, pmt.title AS subtitle FROM tblpromo pm ";
$promo_sql.=" JOIN tblpromotion pmt ON pmt.promo_idx = pm.idx ";
$promo_sql.=" JOIN tblspecialpromo sp ON sp.special::int = pmt.seq ";
$promo_sql.=" WHERE sp.special_list LIKE '%".$_pdata->productcode."%' ";
$promo_sql.=" ORDER BY pm.rdate DESC LIMIT 2";
$promo_res = pmysql_query( $promo_sql, get_db_conn() );
$promo_link = array();

$promo_target    = "";
if($popup == "ok") $promo_target     = " target='_parent'";

while( $promo_row = pmysql_fetch_object( $promo_res ) ) {
    $promo_link[] = "<a href='../front/promotion_detail.php?idx=".$promo_row->idx."'".$promo_target.">&gt; ".$promo_row->title."</a>";
}
pmysql_free_result( $promo_res );

#리뷰 베너
$review_banner = get_banner( 94 );

#회원 쿠폰정보
$member_coupon = MemberCoupon( 1, 'M' );
#사용 가능한 쿠폰 정보
$possible_coupon = PossibleCoupon( $_pdata->productcode );

//쿠폰 레이어팝업 내용
function CouponLayer( $member_coupon, $possible_coupon ){
    $member_layerHtml = array();
    $possible_layerHtml = array();
    $layerHtml = array();
    $mem_layerText = '';
    $possible_layerText = '';
    $tmpPossibelCoupon = $possible_coupon;
    $coupons = array();

    foreach( $member_coupon as $mcKey=>$mcVal ){
        if( !in_array( $mcVal->coupon_code, $coupons ) ){
            $coupons[] = $mcVal->coupon_code;
            $pricetype_text = CouponText( $mcVal->sale_type );
            $mem_layerText = "<tr name='TR_memcoupon' data-code='".$mcVal->coupon_code."' >";
            $mem_layerText.= "  <td>".$mcVal->coupon_name."</td>";
            $mem_layerText.= "  <td>".$mcVal->sale_money.' '.$pricetype_text['won']."</td>";
            $mem_layerText.= "  <td>";
            $mem_layerText.= "      ".toDate( $mcVal->date_start, '-' )."<br>";
            $mem_layerText.= "      ~ ".toDate( $mcVal->date_end, '-' );
            $mem_layerText.= "      </td>";
            $mem_layerText.= "</tr>";
            $member_layerHtml[] = $mem_layerText;
        }
    }

    foreach( $possible_coupon as $pcKey=>$pcVal ){
        $pricetype_text = CouponText( $pcVal->sale_type );
        $possible_layerText = "<tr>";
        $possible_layerText.= " <td>".$pcVal->coupon_name."</td>";
        $possible_layerText.= " <td>".$pcVal->sale_money.' '.$pricetype_text['won']."</td>";
        $possible_layerText.= " <td>";
        $possible_layerText.= " <button type='button' class='btn-dib-function CLS_coupon_download' data-coupon='".$pcVal->coupon_code."' >";
        $possible_layerText.= "     <span>쿠폰받기</span>";
        $possible_layerText.= " </button>";
        $possible_layerText.= "     </td>";
        $possible_layerText.= "</tr>";
        $possible_layerHtml[] = $possible_layerText;
    }

    $layerHtml[] = $member_layerHtml;
    $layerHtml[] = $possible_layerHtml;

    return $layerHtml;

}
//쿠폰 레이어팝업 내용
$coupon_layer = CouponLayer( $member_coupon, $possible_coupon );

//카드혜택 베너
$card_banner = get_banner( '111' );

// 상품 썸네일 옆에 작은 이미지들을 배열에 저장해서 한번에 그려준다.
$arrMiniThumbList = array();
#카카오 이미지
$tmp_kakao_img = '';
if( strpos( $_pdata->maximage, "http://" ) !== false ){
    $tmp_kakao_img = $_pdata->maximage;
} else if( is_file( $imagepath_product.$_pdata->maximage ) ) {
    $tmp_kakao_img = 'http://'.$_SERVER['HTTP_HOST'].'/front/'.$imagepath_product.$_pdata->maximage;
}

# 상품 큰 이미지
if( is_file( $imagepath_product.$_pdata->maximage ) || strpos($_pdata->maximage, "http://") !== false ) {
    $tmp_imgCont = getProductImage($imagepath_product, $_pdata->maximage);
    array_push($arrMiniThumbList, $tmp_imgCont);
}

if ( $multi_img=="Y" && $yesimage[0] ) {

    $arrMultiImg = array(); // 상품 상세 설명이 없는 경우 노출하기 위해 배열에 저장
    foreach( $yesimage as $mImgKey=>$mImgVal ){
        $multiImg = getProductImage($imagepath_multi, $mImgVal);
        array_push($arrMultiImg, $multiImg);

        $tmp_imgCont = $multiImg;
        array_push($arrMiniThumbList, $tmp_imgCont);
    }
}
//exdebug($arrMiniThumbList);
$thisCate = getDecoCodeLoc( $_pdata->productcode, $prod_cate_code );
//exdebug($_pdata);
?>
<style>
    .coupontxt {padding: 3px 10px;margin-left: 4px;color: #da2128;border: 1px solid #da2128;}
</style>
    <!-- <a class="btn-prev-hide" href="javascript:history.go(-1);"><img src="<?=$Dir?>/m/static/img/btn/btn_page_prev.png" alt="이전 페이지"></a> -->

    <!-- <div class="sub-title">
        <h2>상품정보</h2>
        <a class="btn-prev" href="javascript:history.go(-1);"><img src="<?=$Dir?>/m/static/img/btn/btn_page_prev.png" alt="이전 페이지"></a>
    </div> -->

    <!-- <div class="goods-detail-breadcrumb">
        <ol>
                <li><a href="/m">HOME</a></li>
                <li><a href="<?=$Dir?>m/productlist.php?code=<?=substr($thisCate[0]->category, 0, 3)?>"><?=$thisCate[0]->code_name?></a></li>
<?php
if( count( $thisCate ) > 1 ){
    $loop_cnt = count($thisCate);
    for ( $i = 1; $i < $loop_cnt; $i++ ) {
        $classOn = "";
        if ( $i == $loop_cnt - 1 ) {
            $classOn = "on";    // 마지막 카테고리에 on 처리
        }
?>
                <li class="<?=$classOn?>"><a href="<?=$Dir?>m/productlist.php?code=<?=$thisCate[$i]->category?>"><?=$thisCate[$i]->code_name?></a></li>

<?
    } // end of for
}
?>
        </ol>
    </div> -->

    

    <!-- 상단 이미지 -->
    <!-- <div class="js-goods-detail-img">
        <div class="js-carousel-list">
            <ul>
<?php
    for( $i=0; $i < count( $arrMiniThumbList ); $i++ ){
?>
                <li class="js-carousel-content"><a href="javascript:;"><img src="<?=$arrMiniThumbList[$i]?>" alt=""></a></li>
<?php
    }
?>
            </ul>
        </div>
        <div class="page <? if( count( $arrMiniThumbList ) < 1 ){ echo 'hide'; }?>">
            <ul>
<?php
    for( $i=0; $i < count( $arrMiniThumbList ); $i++ ){
        $onClass = "";
        if ( $i == 0 ) {
            $onClass = "on";
        }
?>
                <li class="js-carousel-page <?=$onClass?>"><a href="javascript:;"><span class="ir-blind"><?=( $i + 1 )?></span></a></li>
<?php
    }
?>
            </ul>
        </div>
        <button class="js-carousel-arrow" data-direction="prev" type="button"><img src="<?=$Dir?>/m/static/img/btn/btn_slider_arrow_prev.png" alt="이전"></button>
        <button class="js-carousel-arrow" data-direction="next" type="button"><img src="<?=$Dir?>/m/static/img/btn/btn_slider_arrow_next.png" alt="다음"></button>
    </div> -->
    <?php
    if(count($arrMiniThumbList) == 1){
    ?>
    <div class="detail-goods-visual">
        <ul>
        <?php if($arrMiniThumbList){ ?>
            <?foreach($arrMiniThumbList as $p_img){?>
                <li><a href="#"><img src="<?=$p_img?>" alt=""></a></li>
            <?}?>
        <?php } ?>
        </ul>
        <div class="bx-pager bx-default-pager"><div class="bx-pager-item"><a href="" data-slide-index="0" class="bx-pager-link active">1</a></div></div>
    </div>
    <?php
    }else{
    ?>
    <div class="detail-goods-visual">
        <ul class="detail-goods-slider">
        <?if($arrMiniThumbList){?>
            <?foreach($arrMiniThumbList as $p_img){?>
                <li><a href="#"><img src="<?=$p_img?>" alt=""></a></li>
            <?}?>
        <?}?>
            <!-- <li><a href="#"><img src="static/img/test/@detail_goods01.jpg" alt=""></a></li>
            <li><a href="#"><img src="static/img/test/@detail_goods01.jpg" alt=""></a></li>
            <li><a href="#"><img src="static/img/test/@detail_goods01.jpg" alt=""></a></li> -->
        </ul>
    </div>
    <?php } ?>
    <!-- // 상단 이미지 -->
    <!-- 상품명 -->
    <div class="detail-goods-name" style="font-weight: bold;"><?=$_pdata->productname?><!-- <span class="code">(X3LSWJW51OR)</span> --></div>
    <!-- //상품명 -->

    <!-- 상단 정보 -->
    <div class="goods-detail-info">


<?php
if( $_pdata->consumerprice > 0 && $_pdata->consumerprice > $_pdata->sellprice ){
    
$down_percent = ($_pdata->sellprice/$_pdata->consumerprice)*100;
?>
        <section>
            <h4>정상가격</h4>
            <span><?=number_format( $_pdata->consumerprice )?></span>
        </section>
<?php
}
?>
        <section>
            <h4>판매가격</h4>
                        <span class="price"><?=number_format( $oldprice )?></span><span> (↓<?=ceil($down_percent)?>%)</span>
        </section>
        
        <?php if( $salemode == 'activ'){
            if(strlen( $_ShopInfo->getMemid() ) > 0){
        ?>
        <section>
            <h4>쿠폰적용가격</h4>
            <span style="font-size:14px;color:#df252d"><strong><?=number_format( $_pdata->sellprice )?>원</strong></span>
            <span class="coupontxt"><?=$coupon_txt?></span>
        </section>
        <?php }else{ ?>
        <section>
            <h4>회원혜택가</h4>
            <span style="font-size:14px;color:#df252d"><strong><?=number_format( $newprice )?>원</strong></span>
            <span class="coupontxt"><?=$coupon_txt?></span>
        </section>
        <?php } } ?>

        <section>
            <h4>상품코드</h4>
            <span><?=$_pdata->model_code?></span>
        </section>

                <section class="new_sec">
            <h4><!--색상--></h4>
            <ul class="select-color">
            <?php if($p_product){ ?>
                <?php foreach($p_product as $p_val){ ?>
                <li><a href="/m/productdetail.php?productcode=<?=$p_val->productcode.$layer_url?>"><img src="<?=getProductImage($imagepath_product, $p_val->minimage)?>" style="width:33px;height:33px;"></a></li>
                <?php } ?>
            <?php } ?>
                <!-- <li class="on"><a href="#"><img src="../static/img/test/@select_color1.jpg" alt=""></a></li>
                <li><a href="#"><img src="../static/img/test/@select_color2.jpg" alt=""></a></li> -->
            </ul>
        </section>

<!--AceCounter-Plus eCommerce Product Start -->
<script type="text/javascript">
var _AceTM=(_AceTM||{});
    _AceTM.Product={
        pCode:'<?=$_pdata->pridx?>',   //제품아이디(필수)
        pName:'<?=strip_tags($_pdata->productname)?>',  //제품이름(필수)
        pPrice:'<?=number_format( $_pdata->sellprice )?>'.replace(/[^0-9]/g,''),           //판매가(필수)
        pCategory:'',    //제품 카테고리명(선택)
        pImageURl:'<?=getProductImage($imagepath_product, $_pdata->minimage)?>',
        oItem:[]
    };  
    fbq('track', 'ViewContent', {
        content_ids: '<?=$product_idx?>',
        content_type: 'product',
    });
$(document).ready( function() {
    mcroPageLoaded();
});
function mcroPageLoaded(){
    var product_code = '<?=$_REQUEST["productcode"]?>';
    var code = '<?=$_REQUEST["code"]?>';

    var category = code.substr(0,3);
    var sub_cate = code.substr(3,3);

    var product_name = '<?=addslashes($_pdata->productname)?>';
    var product_price = '<?=$_pdata->sellprice?>';//판매가
    var buy_price = '<?=$_pdata->consumerprice?>';//정가
    var image = 'http://<?=$_SERVER['HTTP_HOST'].'/data/shopimages/product/'.$_pdata->maximage?>';
    var url = "http://<?=$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']?>";

    var m_url = 'http://<?=$_SERVER['HTTP_HOST'].'/m/productdetail.php?productcode='?>'+product_code;
    try{
        var evt_data = {};
        evt_data.evt = 'view';                  // 이벤트코드 'view' 고정.
        evt_data.p_no = product_code;           // url상에서 쓰이는 상품코드
        evt_data.p_name = product_name;         // 상품명
        evt_data.price = product_price;         // 상품판매가격(숫자만 포함한 문자열)
        evt_data.regular_price = buy_price;     // 상품정가(숫자만 포함한 문자열)
        evt_data.thumb = image;                 // 상품이미지 url
        evt_data.p_url = url;                   // 해당 상품페이지 url(트래킹 코드 등이 포함되지 않은 순수 url)
        evt_data.p_url_m = m_url;   // 해당 상품페이지 모바일 url (optional)
        evt_data.cate1 = category;              // 카테고리 대분류. 존재하지 않으면 ''
        evt_data.cate2 = sub_cate;              // 카테고리 중분류. 존재하지 않으면 ''
        evt_data.cate3 = '';                    // 카테고리 소분류. 존재하지 않으면 ''
        evt_data.soldout = '0';                 // 품절여부. (품절이 아닐경우 '0', 품절일 경우 '1')
        mcroPushEvent(evt_data);
    }catch(e){}
    

}
</script>
<!--AceCounter-Plus eCommerce Product End -->

            <script type="text/javascript" src="http://<?=$cfg_npay["pay_domain"]?>pay.naver.com/customer/js/mobile/naverPayButton.js" charset="UTF-8"></script>
            <!-- <script type="text/javascript" src="http://<?=$cfg_npay["pay_domain"]?>pay.naver.com/customer/js/naverPayButton.js" charset="UTF-8"></script> -->

            <script type="text/javascript" src="../js/npay.js"></script>
            <div style = "">
                <script>viewNpayButton('<?=$cfg_npay["btn_cert_key"]?>', '<?=$cfg_npay["btn_enabled"]?>', 'MA', '1', '2');</script>
            </div>

    

        <!-- <section>
            <h4>사이즈</h4>
            <ul class="select-size">
                <li class="on"><a href="#">95</a></li>
                <li><a href="#">100</a></li>
                <li><a href="#">105</a></li>
                <li><a href="#">110</a></li>
                <li><a href="#">100</a></li>
                <li><a href="#">105</a></li>
                <li><a href="#">110</a></li>
            </ul>
        </section> -->


        <!-- <div class="goods-detail-info-option">
<?php
        if( strlen( $_pdata->option1 ) > 0 || strlen( $_pdata->option2 ) > 0 ){ // 옵션정보 확인
            if( strlen( $_pdata->option1 ) > 0 ){
                $opt1_subject = option_slice( $_pdata->option1, '1' );
                //$opt1_content = option_slice( $_pdata->option1, $_pdata->option_type );
                $opt_tf       = option_slice( $_pdata->option1_tf, '1' );
                $select_option_code = array();
                $option_depth = count( $opt1_subject ); // 옵션 길이
                foreach( $opt1_subject as $subjectKey=>$subjectVal ){
?>
            <section name='opt' >
                <h4><?=$subjectVal?></h4>
                <div class="select-def">
                    <select name='opt_value'
                        data-type='<?=$_pdata->option_type?>'
                        data-prcode='<?=$_pdata->productcode?>'
                        data-depth='<?=($subjectKey + 1)?>'
                        data-qty='<?=$_pdata->quantity?>'
                        data-tf='<?=$opt_tf[$subjectKey]?>'
                    >
                        <option value='' data-price='0' > 선택 </option>
<?php
                    if( ( $subjectKey == 0 && $_pdata->option_type == '0' ) || $_pdata->option_type == '1' ){
                        //옵션정보를 가져온다
                        if( $_pdata->option_type == '0' ){
                            $options = get_option( $_pdata->productcode );
                        } else if( $_pdata->option_type == '1' ){
                            $options = mobile_get_alone_option( $_pdata->productcode, $subjectVal );
                        } else {
                            $options = array();
                        }
                        foreach( $options as $contentKey=>$contentVal ) { //옵션내용
                            $option_qty = $contentVal['qty']; // 수량
                            $option_text = ''; // 품절 text
                            $priceText = ''; // 가격
                            $option_desabled = false;
                            $alone_opt = array();

                            if( $_pdata->option_type == '0' && $subjectKey == 0 ) {
                                $select_code = $contentVal['code']; //조합형 옵션 코드형태 + 1depth 일때
                            } else if( $_pdata->option_type == '1' ) {
                                $select_code = $contentVal['option_code']; // 독립형 옵션일때
                                //$alone_opt = explode( chr( 30 ), $opt1_content[$subjectKey] );
                            } else {
                                $select_code = '';
                            }

                            //상품가격 text 처리 ( 조합형일 경우 마지막 depth의 옵션만 적용, 독립형일경우 전부다 적용 )
                            if(
                                (
                                  ( $_pdata->option_type == '0' && $subjectKey + 1 == $option_depth ) ||
                                  ( $_pdata->option_type == '1' )
                                ) && $contentVal['price'] > 0
                            ) {
                                $priceText = ' ( + '.number_format($contentVal['price']).' 원 )';
                            } else if(
                                (
                                  ( $_pdata->option_type == '0' && $subjectKey + 1 == $option_depth ) ||
                                  ( $_pdata->option_type == '1' )
                                ) && $contentVal['price'] < 0
                            ) {
                                $priceText = ' ( - '.number_format($contentVal['price']).' 원 )';
                            } // 상품가격 if

                            //품절 text 처리
                            if(
                                ( $option_qty !== null && $option_qty <= 0 ) &&
                                $_pdata->option_type == '0' &&
                                $_pdata->quantity < 999999999 &&
                                $subjectKey + 1 == $option_depth
                            ){
                                $option_text = '[품절]&nbsp;';
                                $option_desabled = true;
                            } //품절 id
?>
                        <option value="<?=$select_code?>"
                            <? if( $contentVal['code'] == $opt1_content[$subjectKey] && $_pdata->option_type == '0' ){ echo ' selected '; } ?>
                            <?// if( $contentVal['code'] == $alone_opt[1] && $_pdata->option_type == '1' ){ echo ' selected '; } ?>
                            <? if( $option_desabled ) { echo ' disabled '; } ?>
                            <? if( $_pdata->option_type == '0' && $subjectKey + 1 == $option_depth ) { echo 'data-qty="'.$option_qty.'" '; } ?>
                            <? echo 'data-price="'.$contentVal['price'].'" '; ?>
                        >
                            <?=$option_text.$contentVal['code'].$priceText?>
                        </option>
<?php
                        } // get_option if
                    }
?>
                    </select>
                </div>
            </section>

<?php
                } // opt_subject foreach
            } // opt1_name if

            if( strlen( $_pdata->option2 ) > 0 ){ // 텍스트 옵션
                $text_opt_subject = option_slice( $_pdata->option2, '1' );
                //$text_opt_content = option_slice( $_pdata->text_opt_content, '1' );
                $text_opt_tf      = option_slice( $_pdata->option2_tf, '1' );
                $test_opt_maxln   = option_slice( $_pdata->option2_maxlen, '1' );
                foreach( $text_opt_subject as $textOptKey=>$textOptVal ){
                    $text_opt_tf_msg = '';
                    if( $text_opt_tf[$textOptKey] == 'T' ) $text_opt_tf_msg = '(필수)';

?>
            <section name='text-opt'>
                <h4><?=$textOptVal?></h4>
                <div class="">
                    <input type='text' name='text_opt_value' value='<?=$text_opt_content[$textOptKey]?>' maxlength='<?=$test_opt_maxln[$textOptKey]?>' data-tf="<?=$text_opt_tf[$textOptKey]?>" >
                    <span class="byte">(<strong><?=strlen($text_opt_content[$textOptKey])?></strong>/<?=$test_opt_maxln[$textOptKey]?>)</span>
                </div>
            </section>
<?php
                } // text_opt_subject foreach
            } // text_opt_subject if
?>
<?php
        }// option if
        if( $_pdata->quantity <= 0 || $_pdata->soldout == 'Y' ){  // 품절
?>
            <section name='sc_quantity' >
                <input type="hidden" name='quantity' id='quantity' value="0">
            </section>
        </div>
        <div class="goods-detail-info-btn">
            (D) 위시리스트 담기 버튼 선택 시 class="on" title="담겨짐"을 추가합니다.
            <button class="btn-wishlist <? if( $wish_row->cnt > 0 ) { echo 'on'; } ?>" type="button" title="담겨짐" onClick="javascript:alert('품절된 상품입니다.');" ><span>WISH LIST</span></button>
            <button class="btn-share" type="button" onClick="javascript:sns_pop();" ><span>SHARE</span></button>
        </div>
        // 상단 정보

        구매버튼
        <div class="goods-detail-buy">
            <a class="btn-buy" href="javascript:alert('품절된 상품입니다.');">SOLD OUT</a>
            <a class="btn-shoppingbag" href="javascript:alert('품절된 상품입니다.');">
                <img src="<?=$Dir?>/m/static/img/btn/goods_detail_shoppingbag.png" alt="">SHOPPING BAG
            </a>
            <div class="box">
                <a class="btn-brandshop" href="<?=$Dir?>m/brand_detail.php?bridx=<?=$brand_code?>">
                    <span><?=$brand_name?><br><strong>브랜드 샵 가기</strong></span>
                </a>
            </div>
        </div>
        // 구매버튼
<?
        } else {  // 상품 품절
?>
                <section name='sc_quantity' >
                    <h4>QUANTITY</h4>
                    <div class="qty">
                        <button class="btn-qty-subtract" type="button"><span>수량 1빼기</span></button>
                        <input type="text" value="1" name='quantity' title="수량">
                        <button class="btn-qty-add" type="button"><span>수량 1더하기</span></button>
                    </div>
                </section>
            </div>
            <div class="goods-detail-info-btn">
                (D) 위시리스트 담기 버튼 선택 시 class="on" title="담겨짐"을 추가합니다.
<?php
    if( strlen( $_ShopInfo->getMemid() ) > 0 ) {

?>
                <button class="btn-wishlist <? if( $wish_row->cnt > 0 ) { echo 'on'; } ?>" type="button"  title="담겨짐" onClick="javascript:wish_check();" >
<?php
    } else {
?>
                <button class="btn-wishlist" type="button"  title="담겨짐" onClick="popup_open('#popup-login');return false;" >
<?php
    }
?>
                    <span>WISH LIST</span>
                </button>
                <button class="btn-share" type="button" onClick="javascript:sns_pop();" ><span>SHARE</span></button>
            </div>
        </div>
        // 상단 정보

        구매버튼
        <div class="goods-detail-buy <?php if( $staff_yn == 'Y' ) { echo 'staff'; } ?>">
<?php
    if( $staff_yn == 'N' ) {
?>
            <a class="btn-buy" href="javascript:order_check(0,'N');">BUY NOW</a>
<?php
    } else if( $staff_yn == 'Y' ) {
?>
            <a class="btn-buy" href="javascript:order_check(0,'N');">BUY NOW</a>
            <a class="btn-buy" href="javascript:order_check(0,'Y');">BUY NOW ( staff )</a>
<?php
    }
?>
            <a class="btn-shoppingbag" href="javascript:basket_insert(0);"><img src="<?=$Dir?>/m/static/img/btn/goods_detail_shoppingbag.png" alt="">SHOPPING BAG</a>
            <div class="box">
                <a class="btn-brandshop" href="<?=$Dir?>m/brand_detail.php?bridx=<?=$brand_code?>">
                    <span><?=$brand_name?><br><strong>브랜드 샵 가기</strong></span>
                </a>
            </div>
        </div>
        // 구매버튼
<?php
    }
?>
 -->
        <div class="goods-detail-buy">

            <!-- <a class="btn-buy" href="javascript:order_check(0,'N');">BUY NOW</a>
            <a class="btn-shoppingbag" href="javascript:basket_insert(0);"><img src="<?=$Dir?>/m/static/img/btn/goods_detail_shoppingbag.png" alt="">SHOPPING BAG</a>
            <div class="box">
                <a class="btn-brandshop" href="<?=$Dir?>m/brand_detail.php?bridx=<?=$brand_code?>">
                    <span><?=$brand_name?><br><strong>브랜드 샵 가기</strong></span>
                </a>
            </div> -->
        </div>
    </div>


<!-- 상품내용 -->

<!-- MD 코멘트 -->
<?php if ($_pdata->mdcomment){ ?>
<div class="goods-detail-comment"> 
    <section>
        <h4>MD Comment</h4>
        <p><?=$_pdata->mdcomment?></p>
    </section>
</div>
<?php } ?>
    
    <div class="">
        <img style="width: 100%;" src="/static/img/rv_point_img.jpeg">
    </div>
    
    <!-- 상세보기 -->
    <a name="tab-product-info" style="display:none;"></a>
    <div class="product-info-wrap" id="local1">
        <div class="tab-detail">
            <ul>
                <li class="on"><a href="#tab-product-info">상세보기</a></li>
                <li><a href="#tab-product-review" class="count_review">리뷰/Q&A</a></li>
                <!-- <li><a href="#tab-product-qna" class="count_qna">상품 Q&amp;A</a></li> -->
                <li><a href="#delivery-guide">배송/반품/교환</a></li>
            </ul>
        </div>
        <div class="detail-contents">
            <div><!-- <img src="static/img/test/@detail_goods_info01.jpg" alt=""> --></div>
            <!-- <img src="static/img/test/@detail_goods_info02.jpg" alt=""> -->

<?php
    // ================================================================================
    // PRODUCT INFO // 모바일용으로 변경해야함
    // ================================================================================
    if( strlen( trim( preg_replace( array("/<br\/>/", "/<br>/"), "", $_pdata->content_m ) ) ) > 0 ) {
        $_pdata_content = stripslashes($_pdata->content_m);
    } else {
        $_pdata_content = stripslashes($_pdata->content);
    }

    // 상품상세의 내용중 이미지의 스타일일 제거한다. (2016-03-31 김재수 추가)-------------------------------
    preg_match_all("/<IMG[^>]*style=[\"']?([^>\"']+)[\"']?[^>]*>/i",$_pdata_content,$_pdata_content_img);
    if ($_pdata_content_img) {
        foreach($_pdata_content_img[0] as $con_img_arr => $con_img) {
            $tem_con_img=$con_img;
            $tem_con_img=preg_replace("/ zzstyle=([^\"\']+) /"," ",$tem_con_img);
            $tem_con_img=preg_replace("/ style=(\"|\')?([^\"\']+)(\"|\')?/","",$tem_con_img);
            $_pdata_content = str_replace($con_img, $tem_con_img, $_pdata_content);
        }
    }
    // ---------------------------------------------------------------------------------------------------
    if( strlen($detail_filter) > 0 ) {
        $_pdata_content = preg_replace($filterpattern,$filterreplace,$_pdata_content);
    }

    // <br>태그 제거
    $arrList = array("/<br\/>/", "/<br>/");
    $_pdata_content_tmp = trim(preg_replace($arrList, "", $_pdata_content));

    if ( empty($_pdata_content_tmp) ) {
        echo "<ul class=\"detail-thumb\">";
        foreach ( $arrMultiImg as $key => $val ) {
            echo "<li><img src=\"{$val}\" alt=\"\"></li>";
        }
        echo "</ul>";
    } else {
        if ( strpos($_pdata_content,"table>")!=false || strpos($_pdata_content,"TABLE>")!=false)
            echo "<pre>".$_pdata_content."</pre>";
        else if(strpos($_pdata_content,"</")!=false)
            echo nl2br($_pdata_content);
        else if(strpos($_pdata_content,"img")!=false || strpos($_pdata_content,"IMG")!=false)
            echo nl2br($_pdata_content);
        else
            echo str_replace(" ","&nbsp;",nl2br($_pdata_content));
    }
?>
            <!-- 추가예정 -->
            <!-- <table class="info-size">
                <caption>SIZE<span class="unit">단위(cm)</span></caption>
                <colgroup>
                    <col style="width:19.35%">
                    <col style="width:16.13%">
                    <col style="width:16.13%">
                    <col style="width:16.13%">
                    <col style="width:16.13%">
                    <col style="width:16.13%">
                </colgroup>
                <thead>
                    <tr>
                        <th scope="row">사이즈</th>
                        <th scope="col">90</th>
                        <th scope="col">95</th>
                        <th scope="col">100</th>
                        <th scope="col">105</th>
                        <th scope="col">110</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th scope="row">가슴둘레</th>
                        <td>103</td>
                        <td>108</td>
                        <td>113</td>
                        <td>118</td>
                        <td>120</td>
                    </tr>
                    <tr>
                        <th scope="row">목둘레</th>
                        <td>56.5</td>
                        <td>58</td>
                        <td>59.5</td>
                        <td>61</td>
                        <td>62</td>
                    </tr>
                    <tr>
                        <th scope="row">밑단둘레</th>
                        <td>77</td>
                        <td>82</td>
                        <td>100</td>
                        <td>105</td>
                        <td>110</td>
                    </tr>
                    <tr>
                        <th scope="row">상의길이</th>
                        <td>60</td>
                        <td>62</td>
                        <td>63</td>
                        <td>86</td>
                        <td>95</td>
                    </tr>
                    <tr>
                        <th scope="row">소매길이</th>
                        <td>60</td>
                        <td>62</td>
                        <td>63</td>
                        <td>86</td>
                        <td>95</td>
                    </tr>
                    <tr>
                        <th scope="row">어깨너비</th>
                        <td>77</td>
                        <td>82</td>
                        <td>100</td>
                        <td>105</td>
                        <td>110</td>
                    </tr>
                    <tr>
                        <th scope="row">총길이</th>
                        <td>77</td>
                        <td>82</td>
                        <td>100</td>
                        <td>105</td>
                        <td>110</td>
                    </tr>
                </tbody>
            </table>
            <ul class="info-size-note">
                <li>위 사이즈는 해당 브랜드의 표준상품 사이즈이며, 단위는 cm 입니다.</li>
                <li>사이즈를 재는 위치나 방법에 따라 약간의 오차가 있을수있습니다.</li>
                <li>위 사항들은 교환 및 반품, 환불의 사유가 될수 없으며, 고객의 단순변심으로 분류됩니다.</li>
            </ul> -->
<?php
if( $jungbo_cnt >= 1 ) {
?>
            <table class="info-info" style="display:none;">
                <caption>INFO</caption>
                <colgroup>
                    <col style="width:19.35%">
                    <col style="width:auto">
                </colgroup>
                <tbody>

                    <tr>
                        <th scope="row">소재</th>
                        <td>
                            <?=$jungbo_val[1]?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="col">제조년월</th>
                        <td><?=$jungbo_val[2]?></td>
                    </tr>
                    <tr>
                        <th scope="col">제조사<br>원산지</th>
                        <td><?=$jungbo_val[3]?></td>
                    </tr>
                    <tr>
                        <th scope="col">품질보증<br>기간</th>
                        <td><?=$jungbo_val[4]?></td>
                    </tr>
                    <tr>
                        <th scope="col">A/S문의</th>
                        <td><?=$jungbo_val[5]?></td>
                    </tr>
                    <tr>
                        <th scope="row">세탁방법</th>
                        <td>
                            <?=$jungbo_val[6]?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">주의사항</th>
                        <td>
                            <?=$jungbo_val[7]?>
                        </td>
                    </tr>
                </tbody>
            </table>
<?php
}
?>
        </div>
    </div>
    <!-- //상세보기 -->


<?php
// 리뷰 작성 가능 리스트 조회
$sql  = "SELECT tblResult.ordercode, tblResult.idx ";
$sql .= "FROM ";
$sql .= "   ( ";
$sql .= "       SELECT a.*, b.regdt  ";
$sql .= "       FROM tblorderproduct a LEFT JOIN tblorderinfo b ON a.ordercode = b.ordercode ";
$sql .= "       WHERE a.productcode = '" . $productcode . "' AND b.id = '" . $_ShopInfo->getMemid()  . "' and ( (b.oi_step1 = 3 AND b.oi_step2 = 0) OR (b.oi_step1 = 4 AND b.oi_step2 = 0) ) ";
$sql .= "       ORDER BY a.idx DESC ";
$sql .= "   ) AS tblResult LEFT ";
$sql .= "   OUTER JOIN tblproductreview tpr ON tblResult.productcode = tpr.productcode and tblResult.ordercode = tpr.ordercode and tblResult.idx = tpr.productorder_idx ";
$sql .= "WHERE tpr.productcode is null ";
$sql .= "ORDER BY tblResult.idx asc ";
$sql .= "LIMIT 1 ";

$result = pmysql_query($sql);
list($review_ordercode, $review_order_idx) = pmysql_fetch($sql);
pmysql_free_result($result);

$qry = "WHERE a.productcode='{$productcode}' ";
$sql = "SELECT COUNT(*) as t_count, SUM(a.marks) as totmarks FROM tblproductreview a ";
$sql.= $qry;
$result=pmysql_query($sql,get_db_conn());
$row=pmysql_fetch_object($result);
$t_count_review = (int)$row->t_count;
$totmarks = (int)$row->totmarks;
$marks=@ceil($totmarks/$t_count_review);
pmysql_free_result($result);
$paging = new New_Templet_mobile_paging($t_count_review,1,1,'GoPageAjax');
$gotopage = $paging->gotopage;

# 리뷰 리스트를 불러온다
//$reviewlist = 'Y';
$sql  = "SELECT a.*, b.productname FROM tblproductreview a LEFT JOIN tblproduct b ON a.productcode = b.productcode ";
$sql .= "{$qry} ORDER BY a.date DESC, a.num DESC ";

$sql = $paging->getSql($sql);
$result=pmysql_query($sql,get_db_conn());
$j=0;
$reviewList = array();
while($row=pmysql_fetch_object($result)) {

    $reviewComment = array();

    $reviewList[$j]['idx'] = $row->num;
    $reviewList[$j]['num'] = $row->num;
    $reviewList[$j]['number'] = ($t_count_review-($setup['list_num'] * ($gotopage-1))-$j);
    $reviewList[$j]['id'] = $row->id;
    $reviewList[$j]['name'] = $row->name;
    $reviewList[$j]['subject'] = $row->subject;
    $reviewList[$j]['productcode'] = $row->productcode;
    $reviewList[$j]['productname'] = $row->productname;
    $reviewList[$j]['ordercode'] = $row->ordercode;
    $reviewList[$j]['productorder_idx'] = $row->productorder_idx;
    $reviewList[$j]['marks'] = $row->marks;
    $reviewList[$j]['hit'] = $row->hit;
    $reviewList[$j]['type'] = $row->type;

    // 별표시하기
    $reviewList[$j]['marks_sp'] = '';
    for ( $i = 0; $i < $row->marks; $i++ ) {
        $reviewList[$j]['marks_sp'] .= '<img src="./static/img/icon/icon_star.png">';
    }

    $reviewList[$j]['best_type'] = $row->best_type;

    $reviewList[$j]['upfile'] = $row->upfile;       // 첨부파일1
    $reviewList[$j]['upfile2'] = $row->upfile2;     // 첨부파일2
    $reviewList[$j]['upfile3'] = $row->upfile3;     // 첨부파일3
    $reviewList[$j]['upfile4'] = $row->upfile4;     // 첨부파일4

    $reviewList[$j]['up_rfile'] = $row->up_rfile;   // 첨부파일1(실제 업로드한 파일명)
    $reviewList[$j]['up_rfile2'] = $row->up_rfile2; // 첨부파일2(실제 업로드한 파일명)
    $reviewList[$j]['up_rfile3'] = $row->up_rfile3; // 첨부파일3(실제 업로드한 파일명)
    $reviewList[$j]['up_rfile4'] = $row->up_rfile4; // 첨부파일4(실제 업로드한 파일명)

    //exdebug($reviewList);
    $reviewList[$j]['date'] = substr($row->date,0,4).".".substr($row->date,4,2).".".substr($row->date,6,2);
    $reviewList[$j]['date'].= '&nbsp;'.substr($row->date,8,2).":".substr($row->date,10,2).":".substr($row->date,12,2);
    $reviewList[$j]['content'] = explode("=",$row->content);

    # 코멘트 가져오기
    $comment_sql  = "SELECT no, id, name, content, regdt, pnum ";
    $comment_sql .= "FROM tblproductreview_comment ";
    $comment_sql .= "WHERE pnum = '".$row->num."' ";
    $comment_sql .= "ORDER BY no desc ";

    $comment_res = pmysql_query( $comment_sql, get_db_conn() );
    while( $comment_row = pmysql_fetch_object( $comment_res ) ){
        $reviewComment[] = $comment_row;
    }
    pmysql_free_result( $comment_res );
    $reviewList[$j]['comment'] = $reviewComment;
    $j++;
}
pmysql_free_result($result);

//exdebug( $_SERVER );

?>
<input id="reloadValue" type="hidden" name="reloadValue" value="" />

<script type="text/javascript">
$(document).ready(function(){//뒤로가기버튼으로 상품상세로 돌아왔을대 옵션 제데로 안나오는 현상 때문에 강제로 새로고침 줌 07 04 원재 ㅠㅠ

    var d = new Date();
    d = d.getTime();
    if ($('#reloadValue').val().length == 0)
    {
        $('#reloadValue').val(d);
        $('body').show();
    }else{ // 백키를 눌러서 왔을때 여기로 오므로 화면 갱신됨.
        $('#reloadValue').val('');
        location.reload();
    }
});
// 리뷰 & qna 리스트 토글
    $(document).on('click','.list-review .title-area',function(){

        if($(this).attr('is_secret')=='1'){
            if( $(this).attr('view_ok') != "OK"){
                return;
            }
        }

        if($(this).next('.list-review .content-area').css('display') == 'none'){
            $('.list-review .content-area').slideUp('fast');
            $(this).next('.list-review .content-area').slideDown('fast');
        }else{
            $(this).next('.list-review .content-area').slideUp('fast');
        }
    });

    var listnum_comment = "<?=$listnum_comment?>";

    function goLogin() {
        <?php $url = $Dir.FrontDir."login.php?chUrl="; ?>
        if ( confirm("로그인이 필요합니다.") ) {
            location.href = "<?=$url?>" + encodeURIComponent('<?=$_SERVER['REQUEST_URI']?>');
        }
    }

    function delete_review_comment(obj) {
        var review_comment_num = $(obj).attr("ids");
        var review_num = $(obj).attr("ids2");

        if ( review_comment_num != "" ) {
            if ( confirm("댓글을 삭제하시겠습니까?") ) {
                $.ajax({
                    type        : "GET",
                    url         : "../front/ajax_delete_review_comment.php",
                    data        : { review_comment_num : review_comment_num }
                }).done(function ( result ) {
                    if ( result == "SUCCESS" ) {
                        alert("댓글이 삭제되었습니다.");

                        $(obj).parent().parent().parent().remove();
                    } else {
                        alert("댓글이 삭제가 실패했습니다.");
                    }
                });
            }
        }
    }

    // 리뷰에 댓글달기
    function review_comment_write(obj) {
        var frm = $(obj).parent();            // form
        var obj_comment = $(frm).find("input[name=review_comment]");      // textarea
        var pnum = $(frm).find("input[name=pnum]").val();      // pnum
        var mem_id = $(frm).find("input[name=mem_id]").val();
        var now_date = $(frm).find("input[name=now_date]").val();
        var inElement = frm.parent().parent().find('.list-con');

        var review_comment = $(obj_comment).val().trim();

        if ( review_comment == "" ) {
            alert("댓글을 입력해 주세요.");
            $(obj_comment).val("").focus();
            return false;
        }

        var fd = new FormData($(frm)[0]);

        $.ajax({
            url: "../front/ajax_insert_review_comment.php",
            type: "POST",
            data: fd,
            async: false,
            cache: false,
            contentType: false,
            processData: false,
        }).success(function(data){
                data_arr    = data.split("|");
            if ( data_arr[0] === "SUCCESS" ) {
                alert("댓글이 등록되었습니다.");
                $(obj_comment).val("");
                inElement.removeClass("hide");
                inElement.prepend( '<div class="list-comment"><ul><li class="data"><span class="id">'+mem_id+'</span><span class="date">('+now_date+')</span></li><li>'+review_comment+' <a class="btn-delete" href="javascript:;" onClick="javascript:delete_review_comment(this);" ids="'+data_arr[1]+'" ids2="'+pnum+'"><img src="../static/img/btn/close.png" alt="닫기"></a></li></ul></div>');
            } else {
                alert("댓글 등록이 실패하였습니다.");
            }
        }).error(function () {
            alert("다시 시도해 주세요.");
        });
    }

    //리뷰 paging ajax
    function GoPageAjax(block,gotopage) {
        gBlock = block;
        gGotopage = gotopage;
        $.ajax({
            type: "GET",
            url: "../m/jdx_review_ajax.php",
            contentType: "application/x-www-form-urlencoded; charset=UTF-8",
            data: "productcode="+$("input[name='productcode']").val()+"&block="+block+"&gotopage="+gotopage
        }).done(function ( data ) {
            $(".review-list-box").html(data);
            //$(".js-review-accordion").accordion();
        });
    }

    //리뷰 수정
    function send_review_write_page(
        productcode,
        ordercode,
        productorder_idx,
        review_num) {

        if ( review_num == undefined ) {
            review_num = 0;
        }

        var frm = document.reviewForm;

        frm.productcode.value = productcode;
        frm.ordercode.value = ordercode;
        frm.productorder_idx.value = productorder_idx;
        frm.review_num.value = review_num;
        frm.mode.value = "modify";
        frm.submit();
    }

    // 리뷰삭제
    function delete_review(review_num) {
        if ( confirm("삭제하시겠습니까?") ) {
            $.ajax({
                type        : "GET",
                url         : "../front/ajax_delete_review.php",
                contentType : "application/x-www-form-urlencoded; charset=UTF-8",
                data        : { review_num : review_num }
            }).done(function ( data ) {
                if ( data === "SUCCESS" ) {
                    alert("리뷰가 삭제되었습니다.");
                    location.reload();
                }
            });
        }
    }

</script>
 
<style type="text/css">
/* 20190128 추가*/
    #local2,#local3,#local4{display: none;}
    .qna-list-box .view .write{padding: 15px 10px;}
    .wrap-input input[type="button"]{height: 30px;}
    .tab-detail ul{padding: 5px;}
    .tab-detail li.on{border-top: 1px solid #da2128; border-right: 1px solid #da2128; border-left: 1px solid #da2128; height: 35px;}
    .tab-detail li.on a{color: #da2128 !important; font-size: 15px; line-height: 35px;}
    .tab-detail li.on a:after{border: none;}
    .tab-detail li{ font-size: 13px; background-color: #da2128; height: 35px;}
    .tab-detail li a{line-height: 35px; color: #fff;}
    .wrap-input.allreviews input {background: #fff;color: #da2128;font-weight: bold;border: 1px solid #da2128;}
</style>
<script type="text/javascript">
    $(document).ready(function(){
        $('.tab-detail li:nth-of-type(1)').click(function(){
            $('#local1,#local2,#local3,#local4').css('display','none');
            $( '#local1' ).css('display', 'block');
            $( 'html,  body' ).stop().animate({ scrollTop : $( '#local1' ).offset().top - 80 },500)
        });
        $('.tab-detail li:nth-of-type(2)').click(function(){
            $('#local1,#local2,#local3,#local4').css('display','none');
            $( '#local2' ).css('display', 'block');
            $( 'html,  body' ).stop().animate({ scrollTop : $( '#local2' ).offset().top - 80 },500)
        });
        $('.tab-detail li:nth-of-type(3)').click(function(){
            $('#local1,#local2,#local3,#local4').css('display','none');
            $( '#local4' ).css('display', 'block');
            $( 'html,  body' ).stop().animate({ scrollTop : $( '#local4' ).offset().top - 80 },500)
        });
    });
</script>

        <!-- 상품리뷰 -->
        <a name="tab-product-review" style="display:none;"></a>
        <div class="product-review-wrap" id="local2">
            <div class="tab-detail">
                <ul>
                    <li><a href="#tab-product-info">상세보기</a></li>
                    <li class="on"><a href="#tab-product-review" class="count_review">리뷰/Q&A</a></li>
                    <!-- <li><a href="#tab-product-qna" class="count_qna">상품 Q&amp;A</a></li> -->
                    <li><a href="#delivery-guide">배송/반품/교환</a></li>
                </ul>
            </div>

            <form name=reviewForm method="POST" action="mypage_review_write.php">
            <input type="hidden" name="productcode" id="productcode" value="<?=$productcode?>" />
            <input type="hidden" name="ordercode" id="ordercode" value="<?=$review_ordercode?>" />
            <input type="hidden" name="productorder_idx" id="productorder_idx" value="<?=$review_order_idx?>" />
            <input type="hidden" name="review_num" id="review_num" value="0" />
            <input type="hidden" name="mode" id="mode" value="" />
            </form>
            <div class="review-list-box" style="padding-bottom:50px;">
                <h5 style="display:none;">고객님이 작성해 주신 상품 상품평 (<strong><?=$t_count_review?></strong>)</h5>
                
                <div class="write">
                    <div class="wrap-input allreviews">
                        <input type="button" name="" value="전체리뷰보기" onClick="javascript:location.href='/m/reviewList.php'">
                    </div>
                    <div class="wrap-input">
                    <?if((strlen($_ShopInfo->getMemid())==0) ){ //&& $_data->review_memtype=="Y"?>
                        <input type="button" onclick='javascript:location.href="<?=$Dir.MDir."login.php?chUrl=".$_SERVER["REQUEST_URI"]?>";'  value="리뷰 글쓰기">
                    <?}else if( ( (!empty($review_ordercode) && !empty($review_order_idx)) || $_ShopInfo->getStaffType() == 1) && strlen($_ShopInfo->getMemid()) > 0 ){?>
                        <input type="button" name="" value="리뷰 글쓰기" onClick="javascript:document.reviewForm.mode.value='write';document.reviewForm.submit();">
                    <?}else{?>
                        <input type="button" name="" value="리뷰 글쓰기" onClick="javascript:document.reviewForm.mode.value='write';document.reviewForm.submit();">
                        <!-- <input type="button" name="" value="리뷰 글쓰기" onclick="javascript:alert('상품을 주문하신후에 후기 등록이 가능합니다. 마이페이지->주문상세내역에서 확인해주세요.');"> -->
                    <?}?>
                    </div>
                </div>

                <div class="list-review">
                <ul>
<?php
    if( count( $reviewList ) > 0 ) {
        foreach( $reviewList as $rKey=>$rVal ) {  //exdebug($rVal);
?>
                    <li>
                        <!-- 상품리뷰 제목 -->
                        <div class="title-area">
                            <div class="info">
                                <span class="star-score"><strong style="width:<?=($rVal['marks']) * 20?>%"></strong></span>
                                <span class="userid"><?=setIDEncryp($rVal['id'])?></span>
                                <span class="date"><?= $rVal['date'] ?></span>
                            </div>
                            <p class="title"><?=$rVal['subject']?></p>
                        </div>
                        <!-- 상품리뷰 내용 -->
                        <div class="content-area">
                            <div class="review-area">
                                <?if ( !empty($rVal['upfile']) || !empty($rVal['upfile2']) || !empty($rVal['upfile3']) || !empty($rVal['upfile4']) ) {?>
                                <ul class="img-list">
                                <?
                                    if ( !empty($rVal['upfile']) ) echo "<li><img src='" . $Dir.DataDir."shopimages/review/" . $rVal['upfile'] . "' /></li>";
                                    if ( !empty($rVal['upfile2']) ) echo "<li><img src='" . $Dir.DataDir."shopimages/review/" . $rVal['upfile2'] . "' /></li>";
                                    if ( !empty($rVal['upfile3']) ) echo "<li><img src='" . $Dir.DataDir."shopimages/review/" . $rVal['upfile3'] . "' /></li>";
                                    if ( !empty($rVal['upfile4']) ) echo "<li><img src='" . $Dir.DataDir."shopimages/review/" . $rVal['upfile4'] . "' /></li>";
                                ?>
                                </ul>
                                <?}?>
                                <?=nl2br($rVal['content'][0])?>
                            </div>
                            <!-- <div class="btn-delete-area"><a href="#">삭제</a></div> -->
                            <!-- 상품리뷰 댓글 -->
                            <ul class="reply-area" style="display:none;">
                                <li>
                                    <span class="left">너무 좋아하는 제품이에요. </span>
                                    <span class="right">2015-05-12 <a href="#">삭제</a></span>
                                </li>
                                <li>
                                    <span class="left">너무 좋아하는 제품이에요. </span>
                                    <span class="right">2015-05-12 <a href="#">삭제</a></span>
                                </li>
                                <!-- 상품리뷰 댓글 입력창 -->
                                <li class="write-reply">
                                    <input type="text" name=""> <input type="submit" value="확인">
                                </li>
                                <!-- //상품리뷰 댓글 입력창 -->
                            </ul>
                            <!-- //상품리뷰 댓글 -->
                        </div>
                        <!-- //상품리뷰 내용 -->
                    </li>

                    <li style="display:none;">

                            <dt class="js-accordion-menu">
                                <button type="button" title="펼쳐보기">
                                    <span class="list-score" title=""><?=$rVal['marks_sp']?></span>
                                    <span class="box">
                                        <span class="list-id"><?=setIDEncryp($rVal['id'])?></span>
                                        <span class="list-date"><?= $rVal['date'] ?></span>
                                    </span>
                                    <span class="list-title"><?=$rVal['subject']?><? if( $rVal['type'] == "1" ) { ?><img class="ico-photo" src="<?=$Dir?>/m/static/img/icon/ico_review_photo.png" alt="사진첨부"><? } ?></span>
                                </button>
                            </dt>
                            <dd class="js-accordion-content">
                                <p class="list-content"><?=nl2br($rVal['content'][0])?>

                            <?
                            if ( $_ShopInfo->getMemid() == $rVal['id'] ) {
                                echo '
                                    <div class="btn-place">
                                        <button class="btn-dib-line " type="button" onclick="javascript:send_review_write_page(
                                            \'' . $rVal['productcode'] . '\',
                                            \'' . $rVal['ordercode'] . '\',
                                            \'' . $rVal['productorder_idx'] . '\',
                                            \'' . $rVal['num'] . '\');"><span>[수정]</span></button>
                                        <button class="btn-delete" type="button" onclick="javascript:delete_review(\'' . $rVal['num'] . '\');"><span>[삭제]</span></button>
                                    </div>';

                            }
                            ?></p>
                            <?if ( !empty($rVal['upfile']) || !empty($rVal['upfile2']) || !empty($rVal['upfile3']) || !empty($rVal['upfile4']) ) {?>
                                <ul class="img-list">
                            <?
                            if ( !empty($rVal['upfile']) ) echo "<li><img src='" . $Dir.DataDir."shopimages/review/" . $rVal['upfile'] . "' /></li>";
                            if ( !empty($rVal['upfile2']) ) echo "<li><img src='" . $Dir.DataDir."shopimages/review/" . $rVal['upfile2'] . "' /></li>";
                            if ( !empty($rVal['upfile3']) ) echo "<li><img src='" . $Dir.DataDir."shopimages/review/" . $rVal['upfile3'] . "' /></li>";
                            if ( !empty($rVal['upfile4']) ) echo "<li><img src='" . $Dir.DataDir."shopimages/review/" . $rVal['upfile4'] . "' /></li>";
                            ?>
                                </ul>
                            <?}?>
                                <div class="list-comment">
                                <form onsubmit="return false;">
                                <input type="hidden" name="pnum" value="<?=$rVal['idx']?>">
                                <input type="hidden" name="mem_id" value="<?=$_ShopInfo->getMemid()?>">
                                <input type="hidden" name="now_date" value="<?=date("Y.m.d")?>">
                                <input type="hidden" name="return" value="OK">
                                    <input type="text" name="review_comment">
                                    <?php if(strlen($_ShopInfo->getMemid())==0) { ?>
                                    <button class="btn-def" type="button" onClick="javascript:goLogin();"><span>OK</span></button>
                                    <?php } else { ?>
                                    <button class="btn-def" type="button" onClick="javascript:review_comment_write(this);"><span>OK</span></button>
                                    <?php } ?>
                                </form>
                                </div>
<?php
            if( count( $rVal['comment'] ) == 0 ){
                $class_add  = " hide";
            } // comment if
?>
                                <div class="list-con<?=$class_add?>" id="reply_comment_<?=$rVal['idx']?>">
<?
                foreach( $rVal['comment'] as $commentKey=>$commentVal ){

                    echo '<div class="list-comment"><ul>
                        <li class="data"><span class="id">' . $commentVal->id . '</span><span class="date">(' . substr($commentVal->regdt,0,4).".".substr($commentVal->regdt,4,2).".".substr($commentVal->regdt,6,2) . ')</span></li>
                        <li>' . $commentVal->content;

                    if ( $commentVal->id == $_ShopInfo->getMemid() ) {
                        echo ' <a class="btn-delete" href="javascript:;" onClick="javascript:delete_review_comment(this);" ids="' . $commentVal->no . '" ids2="' . $commentVal->pnum . '"><img src="../static/img/btn/close.png" alt="닫기"></a>';
                    }
                    echo '</li></ul></div>';

                } // comment foreach
?>
                                </div>
                            </dd>
                        </dl>
                    </li>

<?php
        } // reviewList foreach
    } else { // reviewList else
?>
                    <li class='ta-c pt-30 pb-30 mb-20'>
                    등록된 상품 후기가 없습니다.
                    </li>
<?
}
?>
                </ul>
<?
if( count( $reviewList ) > 0 ) {
?>
                <div class="paginate">
                    <div class="box">
                        <?=$paging->a_prev_page.' '.$paging->print_page.' '.$paging->a_next_page?>
                    </div>
                </div>
<?
}
?>



                <div class="btnwrap" style="display:none;">
                    <div class="box">
<?php
    if((strlen($_ShopInfo->getMemid())==0) ) { //&& $_data->review_memtype=="Y"
?>
        <a class="btn-def" onclick='javascript:location.href="<?=$Dir.MDir."login.php?chUrl=".$_SERVER["REQUEST_URI"]?>";' >리뷰 글쓰기</a>
<?php
    } else if( ( (!empty($review_ordercode) && !empty($review_order_idx)) || $_ShopInfo->getStaffType() == 1) && strlen($_ShopInfo->getMemid()) > 0 ){ // && $_data->review_memtype=="Y"
?>
        <a class="btn-def" onClick="javascript:document.reviewForm.mode.value='write';document.reviewForm.submit();">리뷰 글쓰기</a>
<?php
    } else{//임시로 상품 구매 없이 글 쓸 수 있또록 함 ㅠㅠ
?>
        <a class="btn-def" onClick="javascript:document.reviewForm.mode.value='write';document.reviewForm.submit();">리뷰 글쓰기</a>
        <!-- <a class="btn-def" onclick="javascript:alert('상품을 주문하신후에 후기 등록이 가능합니다. 마이페이지->주문상세내역에서 확인해주세요.');">리뷰 글쓰기</a> -->
<?php
    }
?>
                    </div>
                </div>

                <!-- --------------------------절취선---------------------------------------------->
                <div class="write" style="display:none">
                    <div class="wrap-input"><input type="text" name="" placeholder="제목을 입력해주세요."></div>
                    <div class="wrap-input">
                        <p id="gradeListHeader" class="select-star"><span id="selectedGrade"></span></p>
                        <ul id="gradeList" class="option-star" style="display:none;">
                            <li data-gradeID="5">
                                <span class="star-score"><strong style="width:100%">5점만점에 5점</strong></span>
                            </li>
                            <li data-gradeID="4">
                                <span class="star-score"><strong style="width:80%">5점만점에 4점</strong></span>
                            </li>
                            <li data-gradeID="3">
                                <span class="star-score"><strong style="width:60%">5점만점에 3점</strong></span>
                            </li>
                            <li data-gradeID="2">
                                <span class="star-score"><strong style="width:40%">5점만점에 2점</strong></span>
                            </li>
                            <li data-gradeID="1">
                                <span class="star-score"><strong style="width:20%">5점만점에 1점</strong></span>
                            </li>
                        </ul>
                        <input type="hidden" id="selectedGradeInput" />
                    </div>
                    <div class="wrap-input"><textarea name="" placeholder="내용을 입력해주세요."></textarea></div>
                    <div class="wrap-input">
                        <div class="filebox">
                            <label for="ex_filename">파일등록</label>
                            <input type="file" id="ex_filename" class="upload-hidden">

                            <input class="upload-name" value="선택된 파일 없음" disabled="disabled">
                        </div>
                        <p class="file-info">(* 한글,영문, 숫자 / 800K 이하 / 파일명 : GIF, JPG, JPEG)</p>
                    </div>
                    <div class="wrap-input"><input type="button" name="" value="등록하기"></div>
                </div>

                <div class="view" style="display:none;">

                    <!-- 상품리뷰 리스트 -->
                    <div class="list-review">
                        <ul>
                            <li>
                                <!-- 상품리뷰 제목 -->
                                <div class="title-area">
                                    <div class="info">
                                        <span class="star-score"><strong style="width:100%">5점만점에 5점</strong></span>
                                        <span class="userid">JUN**********</span>
                                        <span class="date">2015-05-10</span>
                                    </div>
                                    <p class="title">너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.</p>
                                </div>
                                <!-- //상품리뷰 제목 -->
                                <!-- 상품리뷰 내용 -->
                                <div class="content-area">
                                    <div class="review-area">
                                        너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.
                                    </div>
                                    <div class="btn-delete-area"><a href="#">삭제</a></div>
                                    <!-- 상품리뷰 댓글 -->
                                    <ul class="reply-area">
                                        <li>
                                            <span class="left">너무 좋아하는 제품이에요. </span>
                                            <span class="right">2015-05-12 <a href="#">삭제</a></span>
                                        </li>
                                        <li>
                                            <span class="left">너무 좋아하는 제품이에요. </span>
                                            <span class="right">2015-05-12 <a href="#">삭제</a></span>
                                        </li>
                                        <!-- 상품리뷰 댓글 입력창 -->
                                        <li class="write-reply">
                                            <input type="text" name=""> <input type="submit" value="확인">
                                        </li>
                                        <!-- //상품리뷰 댓글 입력창 -->
                                    </ul>
                                    <!-- //상품리뷰 댓글 -->
                                </div>
                                <!-- //상품리뷰 내용 -->
                            </li>
                            <li>
                                <!-- 상품리뷰 제목 -->
                                <div class="title-area">
                                    <div class="info">
                                        <span class="star-score"><strong style="width:80%">5점만점에 4점</strong></span>
                                        <span class="userid">JUN**********</span>
                                        <span class="date">2015-05-10</span>
                                    </div>
                                    <p class="title">너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.</p>
                                </div>
                                <!-- //상품리뷰 제목 -->
                                <!-- 상품리뷰 내용 -->
                                <div class="content-area">
                                    <div class="review-area">
                                        너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.
                                    </div>
                                    <div class="btn-delete-area"><a href="#">삭제</a></div>
                                </div>
                                <!-- //상품리뷰 내용 -->
                            </li>
                            <li>
                                <!-- 상품리뷰 제목 -->
                                <div class="title-area">
                                    <div class="info">
                                        <span class="star-score"><strong style="width:80%">5점만점에 4점</strong></span>
                                        <span class="userid">JUN**********</span>
                                        <span class="date">2015-05-10</span>
                                    </div>
                                    <p class="title">너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.</p>
                                </div>
                                <!-- //상품리뷰 제목 -->
                                <!-- 상품리뷰 내용 -->
                                <div class="content-area">
                                    <div class="review-area">
                                        너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.
                                    </div>
                                    <div class="btn-delete-area"><a href="#">삭제</a></div>
                                </div>
                                <!-- //상품리뷰 내용 -->
                            </li>
                            <li>
                                <!-- 상품리뷰 제목 -->
                                <div class="title-area">
                                    <div class="info">
                                        <span class="star-score"><strong style="width:80%">5점만점에 4점</strong></span>
                                        <span class="userid">JUN**********</span>
                                        <span class="date">2015-05-10</span>
                                    </div>
                                    <p class="title">너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.</p>
                                </div>
                                <!-- //상품리뷰 제목 -->
                                <!-- 상품리뷰 내용 -->
                                <div class="content-area">
                                    <div class="review-area">
                                        너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.너무 좋아하는 제품이에요. 너무 좋아하는 제품이에요.
                                    </div>
                                    <div class="btn-delete-area"><a href="#">삭제</a></div>
                                </div>
                                <!-- //상품리뷰 내용 -->
                            </li>
                        </ul>
                    </div>
                    <!-- // 상품리뷰 리스트 -->

                    <!-- 상품리뷰 리스트 - 페이징 -->
                    <div class="paginate">
                        <div class="box">
                            <a class="btn-page-prev" href="#"><span class="ir-blind">이전</span></a>
                            <ul>
                                <li class="on" title="선택됨"><a href="#">1</a></li>
                                <li><a href="#">2</a></li>
                                <li><a href="#">3</a></li>
                                <li><a href="#">4</a></li>
                                <li><a href="#">5</a></li>
                                <li><a href="#">6</a></li>
                                <li><a href="#">7</a></li>
                                <li><a href="#">8</a></li>
                            </ul>
                            <a class="btn-page-next" href="#"><span class="ir-blind">다음</span></a>
                        </div>
                    </div>
                    <!-- // 상품리뷰 리스트 - 페이징 -->
                </div>

            </div><!-- //.review-list-box -->
        </div>
        <!-- //상품리뷰 -->
    <!-- </div> -->
        <!-- 상품 Q&A -->
        <a name="tab-product-qna" style="display:none;"></a>
        <?
        ####qna리스트 가져오기#######

        $qna_sql = "SELECT COUNT(*) as t_count from tblboard where board='qna'  ";
        $qna_sql .= " AND pridx = '{$_pdata->pridx}' ";

        $result2=pmysql_query($qna_sql,get_db_conn());
        $row2=pmysql_fetch_object($result2);
        //exdebug($row2);
        $t_count_qna = (int)$row2->t_count;
        pmysql_free_result($result2);

        $paging2 = new New_Templet_mobile_paging($t_count_qna,2,1,'GoPageAjax2');

        $gotopage2 = $paging2->gotopage;

        $qna_sql = "SELECT * from tblboard where board='qna'  ";
        $qna_sql .= " AND pridx = '{$_pdata->pridx}' ";
        $qna_sql = $paging2->getSql($qna_sql);

        $qna_result = pmysql_query($qna_sql);

        while( $row = pmysql_fetch_object($qna_result) ){
            $qna_list[] = $row;
        }
        //exdebug($qna_list);
        ?>
        <script>
            function jdx_qna_wirte(pridx)
            {
                location.href="mypage_qna.php?mode=write&pridx="+pridx+"&page=pr";
            }

            function GoPageAjax2(block,gotopage) {
                gBlock = block;
                gGotopage = gotopage;
                $.ajax({
                    type: "GET",
                    url: "../m/jdx_qna_ajax.php",
                    contentType: "application/x-www-form-urlencoded; charset=UTF-8",
                    data: "pridx=<?=$_pdata->pridx?>&block="+block+"&gotopage="+gotopage
                }).done(function ( data ) {
                    $(".qna-list-box").html(data);
                });
            }
        </script>
        <!-- <div class="product-qna-wrap" id="local3">
            <div class="tab-detail">
                <ul>
                    <li><a href="#tab-product-info">상세보기</a></li>
                    <li class="on"><a href="#tab-product-review" class="count_review">상품리뷰</a></li>
                    <li class="on"><a href="#tab-product-qna" class="count_qna">상품 Q&amp;A</a></li>
                    <li><a href="#delivery-guide">배송/반품/교환</a></li>
                </ul>
            </div> -->
            <div class="qna-list-box">

                <div class="write" style="display:none">

                    <div class="wrap-input"><input type="text" name="" placeholder="이름을 입력해주세요."></div>
                    <div class="wrap-input"><input type="password" name="" placeholder="비밀번호를 입력해주세요."></div>
                    <div class="wrap-input"><input type="text" name="" placeholder="이메일을 입력해주세요."></div>
                    <div class="wrap-input"><input type="text" name="" placeholder="제목을 입력해주세요."></div>
                    <div class="wrap-input"><textarea name="" placeholder="내용을 입력해주세요."></textarea></div>
                    <div class="wrap-input"><input type="button" name="" value="등록하기"></div>
                </div>

                <div class="view">

                    <div class="write">
                        <div class="wrap-input">
                        <?if((strlen($_ShopInfo->getMemid())==0) ){ //&& $_data->review_memtype=="Y"?>
                            <input type="button" onclick='javascript:location.href="<?=$Dir.MDir."login.php?chUrl=".$_SERVER["REQUEST_URI"]?>";'  value="Q&A 작성하기">
                        <?}else{?>
                            <input type="button" name="" value="Q&A 작성하기" onClick="jdx_qna_wirte('<?=$_pdata->pridx?>');">
                        <?}?>
                        </div>
                    </div>

                    <!-- 상품리뷰 리스트 -->
                    <div class="list-review">
                    <?if($qna_list){?>
                        <ul>
                        <?foreach($qna_list as $qval){?>
                        <?$view_ok="";?>
                        <?if( ($qval->is_secret=="1") && ($qval->mem_id == $_ShopInfo->getMemid()) ) $view_ok="OK"; ?>
                            <li>
                                <!-- 상품리뷰 제목 -->
                                <div class="title-area" is_secret="<?=$qval->is_secret?>" view_ok="<?=$view_ok?>">
                                    <div class="info">
                                        <span class="userid"><?=setIDEncryp($qval->mem_id)?></span>
                                        <span class="date"><?=date("Y-m-d",$qval->writetime)?></span>

                                    </div>
                                    <p class="title">
                                    <?if($qval->is_secret=="1"){?>
                                        <img class="ico-lock" src="./static/img/icon/ico_lock_open.png" alt="내가 쓴 비밀글">
                                    <?}?>
                                    <?=$qval->title?></p>
                                </div>
                                <!-- //상품리뷰 제목 -->
                                <!-- 상품리뷰 내용 -->
                                <div class="content-area">
                                    <div class="review-area">
                                        <?=$qval->content?>
                                    </div>
                                    <!-- <div class="btn-delete-area"><a href="#" class="modify">수정</a> <a href="#">삭제</a></div> -->
                                </div>
                                <!-- //상품리뷰 내용 -->
                            </li>
                        <?}?>
                        </ul>
                    <?}else{?>
                        <ul><li class='ta-c pt-30 pb-30 mb-20'>등록된 Q&A가 없습니다</li></ul>
                    <?}?>
                    </div>
                    <!-- // 상품리뷰 리스트 -->

                    <!-- 상품리뷰 리스트 - 페이징 -->
                    <?if($qna_list){?>
                    <div class="paginate">
                        <div class="box">
                            <?=$paging2->a_prev_page.' '.$paging2->print_page.' '.$paging2->a_next_page?>
                        </div>
                    </div>
                    <?}?>
                    <!-- // 상품리뷰 리스트 - 페이징 -->
                </div>

            </div>
        </div>
        <!-- //상품 Q&A -->

        <!-- 배송/반품/교환 -->
        <a name="delivery-guide" style="display:none;"></a>
        <div class="delivery-guide-wrap" id="local4">
            <div class="tab-detail">
                <ul>
                    <li><a href="#tab-product-info">상세보기</a></li>
                    <li><a href="#tab-product-review" class="count_review">리뷰/Q&A</a></li>
                    <!-- <li><a href="#tab-product-qna" class="count_qna">상품 Q&amp;A</a></li> -->
                    <li class="on"><a href="#delivery-guide">배송/반품/교환</a></li>
                </ul>
            </div>
            <div class="delivery-info">
            <?php //$deli_info ?>

                            <div class="tit">배송안내</div>
                            <div class="txt">
                                <ul>
                                    <li>- 당사는 롯데택배(구 현대택배)를 이용하고 있습니다.</li>
                                    <li>- 당일 배송 마감은 오후 2시 주문까지이며, 결제 완료 후 평균 3~5일(주말, 공휴일 제외) 이내에 배송됩니다.</li>
                                    <li>- 도소/산간 지역은 배송 기일이 추가적으로 소요될 수 있으며, 상품의 재고 상황에 따라 지연될 수도 있습니다.</li>
                                    <li>- 천재지변에 의한 기간은 배송 기간에서 제외됩니다.</li>
                                </ul>
                            </div>

                            <div class="tit">교환/반품</div>
                            <div class="txt">
                                <ul>
                                    <li>- 교환/반품 가능 기간은 택배 수령일로부터 7일 이내이며, 제품 검수시 재판매가 가능한(하자 없는) 상태를 확인 후 진행됩니다.</li>
                                    <li>- 교환/반품 처리 기간은 물품을 회수한 날로부터 5~10일 이내에 처리됩니다.</li>
                                    <li>- 교환품을 먼저 보내면서 회수하는 맞교환 방식은 처리가 불가능하며, 먼저 받으셨던 제품이 당사로 반품된 후 교환제품을 발송합니다.</li>
                                    <li>- 교환/반품 배송비는 5,000원(왕복)이며 지역에 따라 추가 요금이 발생될 수 있습니다.</li>
                                    <li>- 타 택배를 이용하여 고객님께서 보내시는 경우 선불 결제 또는 2,500원을 동봉 또는 당사 계좌로 입금해 주셔야 합니다.</li>
                                    <li>- 계좌안내 : 농협 301-0181-1288-91 (예금주 (주)신한코리아)</li>
                                    <li>- 다른 상품으로 교환을 원하실 경우, 기존 제품은 반품처리가 되며 신규로 주문을 해주셔야 합니다.</li>
                                    <li>- 제품의 불량, 오배송으로 인한 교환/반품 시 택배비는 당사에서 부담하며, 제품의 상태를 확인 후 최종 환불처리 됩니다.</li>
                                    <li>- 제품의 불량 발견 시 3일 이내에 당사로 연락주시기 바라며, 접수가 된 후 7일 이내 제품을 보내주셔야 합니다.</li>
                                </ul>
                            </div>

                            <div class="tit">교환/반품이 불가한 경우</div>
                            <div class="txt">
                                <ul>
                                    <li>- 고객님의 책임 사유로 인한 제품의 훼손 또는 멸실</li>
                                    <li>- 당사로 반송된 제품이 손상되어 있는 경우</li>
                                    <li>- 제품의 구성품 분실 및 파손, 고장, 오염, 이염의 경우</li>
                                    <li>- 착용 흔적이나 세탁, 수선의 흔적이 있는 경우</li>
                                    <li>- 제품의 TAG(라벨)을 분실한 경우</li>
                                    <li>- 프로모션으로 발송한 사은품이 동시 반송되지 않은 경우</li>
                                </ul>
                            </div> 
                            <div class="tit">교환/반품 주소지</div>
                            <div class="txt">
                                <ul>
                                    <li>- 경기도 평택시 서탄면 발안로 1198-5, 1층 온라인팀 물류센터</li>
                                    <li>- 교환/반품 신청서를 기재 및 동봉하여 롯데택배(1588-2121)를 이용해 보내주시면 됩니다.</li>
                                    <li>- 고객센터 : 1644-3346. 운영시간 평일 오전 9시~12시, 오후 1시 30분~6시, 토/일/공휴일 휴무</li>
                                    <li>- JDX.CO.KR 공식몰에서 주문하신 고객에 한해 고객센터로 전화주시면 기사님 방문 반품 접수를 도와드립니다.</li>
                                </ul>
                            </div> 
                            <div class="tit">환불</div>
                            <div class="txt">
                                <ul>
                                    <li>- 당사로 반송된 제품의 하자가 없는 상태임을 확인 후 환불 처리를 해드립니다.</li>
                                    <li>- 카드결제의 경우 : 당일 환불 진행 > 카드결제 대행사(KCP)에 환불 요청 . 실제 카드취소까지 3일 가량 소요</li>
                                    <li>- 현금 결제 후 환불의 경우 : 제품 입고 확인 후 고객센터에서 고객님께 연락하여 계좌번호를 받은 후 3일 이내(공휴일 제외)</li>
                                </ul>
                            </div> 
            </div><!-- //.delivery-info -->
        </div>
        <!-- //배송/반품/교환 -->
                
                <!-- 연관상품 -->
                <div class="goods-detail-comment"> 
                    <section>
                        <h4>고객님께 추천하는 아이템</h4>
                        <ul>
                            <?php foreach($related_html as $rp_val){
                                echo $rp_val;
                            }?>
                        </ul>
                    </section>
                </div>

    <!-- // 상품내용 -->

<form name=form1 method=post id = 'prForm'>
<input type="hidden" id="mem_productcode" value="<?=$_pdata->productcode?>">
<input type="hidden" id="mem_opt_type" value="<?=$_pdata->option_type?>">
<input type="hidden" id="mem_option2" value="<?=$_pdata->option2?>">
<input type='hidden' name='prcode' id='prcode' value='<?=$_pdata->productcode?>' >
<input type="hidden" name=npay_mode value = 'detail_m'>
<input type="hidden" name=option_code id=option_code>
<input type="hidden" name="quantity" id="nquantity" value="1">
</form>

<!-- 상품 주문관련 스크립트 -->
<script>
    // 상품 품절정보
    var _qty            = 0 //상품 수량
    var _soldout        = 'N' //품절 유무
    var _main           = null  // 메인 상품정보
    var _bottom         = null  // 하단 상품정보
    var _bottom_select  = null  // 선택된 상품/옵션 가격정보
    var _productname    = "<?=$_pdata->productname?>";
    var _productcode    = '<?=$_pdata->productcode?>';
    var _price          = '<?=$_pdata->sellprice?>';
    var _opt_type       = '<?=$_pdata->option_type?>'; // 옵션 type ( 0 - 조합형, 1 - 독립형 )
    var _memchk         = '<?=strlen( $_ShopInfo->getMemid() )?>'; // 회원 체크


    $(document).ready( function( ) {
        _qty     = <?=$_pdata->quantity?> //상품 수량
        _soldout = '<?=$_pdata->soldout?>' //품절 유무
        var bottom_layer = $('.js-tool-buy');
                if(_opt_type==' '){
                    var qty_content ="<option value='0'>수량 선택</option>";

                    for(i=1; i <= _qty;i++ ){
                        qty_content += "<option value='"+i+"'>"+i+"</option>";
                    }
                    $("#m_jdx_quantity").html(qty_content);
                }
    });


    //옵션변경
    $(document).on( 'change', 'select[name="opt_value"]', function( event ){

        $("#chk_qty").val(0);//옵션이 변경되면 체크된 수량을 0으로 초기화 합니다.

        if($(this).val() ==0){//옵션 초기화 동작
            $("#chk_select").val(0);//체크된 옵션값 초기화
            $("#m_jdx_quantity").html(''); //수량 초기화
        }else{//옵션을 선택했을경우 동작
            var option_qty =        $(this).find(":selected").attr("data-qty");
            var option_type     = $(this).data('type');
            var option_code     = '';
            m_jdx_quntity(option_qty);
            $(this).find('option').each( function(){
                if( $(this).prop( 'selected' ) ){
                    option_code = $(this).val();
                    $("#chk_opt").val(option_code);
                    $("#option_code").val(option_code);
                }
            });
        }
     });

     function npay_order_check( list_index="0", staffchk="N" ){
         var check="IN";
    
         var option_type   = _opt_type;
        var chk_select = $("select[name='opt_value']").val();

        if( (chk_select == 0) && option_type !=' '){
            alert('옵션을 선택하셔야 합니다');
            check="OUT";
            $(".open_buy").trigger("click");
            return check;
        }

        var quantity      =  $("#chk_qty").val();
        var opt_code      = $("#chk_opt").val();
        if(opt_code ==0) opt_code=null; //옵션이 존재하지 않는 상품은 옵션코드 값 null

        if( quantity < 1 ) {
            alert('상품수량을 1개 이상 선택하셔야 합니다.');
            check="OUT";
            $(".open_buy").trigger("click");
            return check;
        }
    
        return check;

    }

    $(document).on("change","#m_jdx_quantity",function(){
        var p_quantity = $(this).val();
        $("#nquantity").val(p_quantity);
        $("#chk_qty").val(p_quantity);
    });

    function m_jdx_quntity(product_qty)
    {
        var qty_content ="<option value='0'>수량 선택</option>";

        for(i=1; i <= product_qty;i++ ){
            qty_content += "<option value='"+i+"'>"+i+"</option>";
        }
        $("#m_jdx_quantity").html(qty_content);
    }

    //장바구니
    function basket_insert(){

         var option_type   = _opt_type;
        var chk_select = $("select[name='opt_value']").val();

        if( (chk_select == 0) && option_type !=' '){
            alert('옵션을 선택하셔야 합니다');
            return;
        }

        var quantity      =  $("#chk_qty").val();
        var opt_code      = $("#chk_opt").val();
        if(opt_code ==0) opt_code=null; //옵션이 존재하지 않는 상품은 옵션코드 값 null

        if( quantity < 1 ) {
            alert('상품수량을 1개 이상 선택하셔야 합니다.');
            return;
        }


        $.ajax({
            method : 'POST',
            url : '../front/confirm_basket_proc.php',
            data : {
                productcode : _productcode, option_code : opt_code, quantity : quantity,
                option_type : option_type, mode : 'insert',
              },
            dataType : 'json'
        }).done( function( data ) {
           var _nasa={};
           _nasa["cnv"] = wcs.cnv("3", "<?=$product_idx?>"); // 전환유형, 전환가치 설정해야함. 설치매뉴얼 참고
           wcs_do(_nasa);

           fbq('track', 'AddToCart', {
                content_ids: _productcode,
                content_type: 'product'
           });
           
           alert(data.msg);
           location.reload();
        });
    }

    function order_check(){

        var chk_login = "<?=$_ShopInfo->getMemid()?>";
        //console.log(chk_login);
        var option_type   = _opt_type;
        var chk_select = $("select[name='opt_value']").val();

        if( (chk_select == 0) && option_type != ' '){
            alert('옵션을 선택하셔야 합니다');
            return;
        }

        var quantity      =  $("#chk_qty").val();
        var opt_code      = $("#chk_opt").val();

        if(opt_code ==0) opt_code=null; //옵션이 존재하지 않는 상품은 옵션코드 값 null

        if( quantity < 1 ) {
            alert('상품수량을 1개 이상 선택하셔야 합니다.');
            return;
        }

        $.ajax({
            method : 'POST',
            url : '../front/confirm_basket_proc.php',
            data : {
                productcode : _productcode, option_code : opt_code, quantity : quantity,
                option_type : option_type, mode : 'order',

            },
            dataType : 'json'
        }).done( function( data ) {
            if( data.basketidx ){
               if(chk_login){
                   location.href = "order.php?"+"basketidxs=" + data.basketidx;
               }else{
                   location.href = "login.php?"+"chUrl=/m/order.php?basketidxs=" + data.basketidx;
               }
             } else {
                alert('장바구니 등록이 실패되었습니다.');
            }
        });


    }

    function wish_check(){
        var productcode = _productcode;

        // 장바구니를 거쳐서 가는것을 ajax로 변경 2015 11 09 유동혁
        $.ajax({
            method : 'POST',
            url : '../front/confirm_wishlist_proc.php',
            data: { productcode : productcode, mode : 'insert' }
            //dataType : 'json'
        }).done( function( data ) {
            if( data.length == 0 ){
                alert('선택하신 상품이 위시리스트에 등록 되었습니다');
                location.reload();
            } else {
                alert( data );
            }
        });
    }

    // php chr() 대응
    function chr(code)
    {
        return String.fromCharCode(code);
    }

</script>
<!-- //상품 주문관련 스크립트 -->
<!-- 배너관련 스크립트 -->
<script>

    function card_banner_pop(){
        $('.popup-layer').show();
        $('#popup-card').fadeIn();
    }

    function prcoupon_pop(){
        $('.popup-layer').show();
        $('#popup-coupon').fadeIn();
    }

    // 쿠폰 다운로드
    $(document).on( 'click', '.CLS_coupon_download', function( event ) {
        var coupon_code = $(this).attr('data-coupon');
        var coupon_button = $(this);
        var buttonHtml_target = coupon_button.parent();
        var buttonHtml = $(this)[0].outerHTML;
        var mem_coupon = true;

        coupon_button.remove();
        $('tr[name="TR_memcoupon"]').each( function( i, obj ) { // 같은 종류의 쿠폰이 존재 하는지 확인
            if( $(this).attr('data-code') == coupon_code ) {
                mem_coupon = false;
            }
        });

        if( coupon_code.length > 0 ) {
            $.ajax({
                type: "POST",
                url: "../front/ajax_coupon_download.php",
                data : { coupon_code : coupon_code },
                dataType : 'json'
            }).done( function( data ){
                if( data.success === true ){
                    alert('쿠폰이 발급 되었습니다.');
                    if( $('#ID_coupon_no').length > 0  ) $('#ID_coupon_no').remove();
                    if( mem_coupon ) $('#ID_coupon_layer').append( data.html );
                    if( data.next_down === true ) {
                        buttonHtml_target.html( buttonHtml );
                    } else {
                        buttonHtml_target.html( '최대 수량 보유' );
                    }
                } else {
                    alert('발급 가능한 쿠폰이 아닙니다.');
                }
            });
        } else {
            alert('발급 가능한 쿠폰이 아닙니다.');
        }
    });

    function sns_pop(){
        $('.popup-layer').show();
        $('#popup-sns').fadeIn();
    }

    function sns(select){

        var Link_url = "http://<?=$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']?>";

        if(select =='facebook'){//페이스북
            var sns_url = "http://www.facebook.com/sharer.php?u="+encodeURIComponent(Link_url);
        }
        if(select =='twitter'){//트위터
            var text = "<?=$_data->shoptitle?>";
            var sns_url = "http://twitter.com/intent/tweet?text="+encodeURIComponent(text)+"&url="+ Link_url + "&img" ;
        }
        if( select == 'kakao' ){

            Kakao.Story.share({
              url: Link_url,
              text: "<?=addslashes($_pdata->productname)?>"
            });

        } else {
            var popup= window.open(sns_url,"_snsPopupWindow", "width=500, height=500");
            popup.focus();
        }
    }

</script>
<!-- kakao pixel 상세보기완료 -->
<script type="text/javascript">
      kakaoPixel('5644026324440334964').viewContent({
        id: '<?=$productcode?>'
      });
</script>
<script type="text/javascript">
(function(w, d, a){
   w.__beusablerumclient__ = {
      load : function(src){
         var b = d.createElement("script");
         b.src = src; b.async=true; b.type = "text/javascript";
         d.getElementsByTagName("head")[0].appendChild(b);
      }
   };w.__beusablerumclient__.load(a);
})(window, document, '//rum.beusable.net/script/b190111e113946u289/4d6c7ba583');
</script>

<!-- criteo -->
<script type="text/javascript" src="//static.criteo.net/js/ld/ld.js" async="true"></script>
<script type="text/javascript">
window.criteo_q = window.criteo_q || [];
window.criteo_q.push(
{ event: "setAccount", account: 57802 },
{ event: "setEmail", email: "cosmokwh23@gmail.com" },
{ event: "setSiteType", type: "m" },
{ event: "viewItem", item: '<?=$productcode?>' }
);
</script>
<!-- criteo end -->

<script type="text/javascript">
// 상품 상세페이지 스크롤 올릴 때 뒤로가기버튼 나타나기
/*
$(function(){
  var lastScroll = 89;
  $('#page').scroll(function(event){
      var st = $(this).scrollTop();
      if (st < lastScroll){
         $('.btn-prev-hide').show();
      }
      else {
         $('.btn-prev-hide').hide();
      }
      lastScroll = st;
  });
});
*/

function m_jdx_basket()
{
    var _productcode='X4MSTLW51GN';
    var opt_code = '90';
    var option_type='0';
    var quantity='1';

    $.ajax({
        method : 'POST',
        url : '../front/confirm_basket_proc.php',
        data : {
            productcode : _productcode, option_code : opt_code, quantity : quantity,
            option_type : option_type, mode : 'insert',
        },
        dataType : 'json'
    }).done( function( data ) {
        console.log(data);
    });
}

var meta = document.createElement('meta');
meta.setAttribute('name', 'more_page_type');
meta.setAttribute('content', 'detail');  // 메인페이지 : 'main', 카테고리페이지 : 'category', 상세페이지 : 'detail'
document.getElementsByTagName('head')[0].appendChild(meta);


function PaymentOpenNewMobile(){
    //var cval = getCookie("okpayment");
    //var paygate_frame = window.parent.document.getElementById('CHECK_PAYGATE');
    //window.parent.nicepayStart('<?=$hashString?>','<?=$ediDate?>',cval,'<?=$basket_code?>');
    window.open.nicepayStart();
}
</script>



<?php
include_once('./outline/footer_m.php')
?>
