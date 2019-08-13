<?php
ini_set('memory_limit', '512M');
// CL 엑셀관련 파일 => 기존에 있는 소스를 조금만 변형해서 만듬
// 큰변동사항 없음.

$Dir="../";
include_once($Dir."lib/init.php");
include_once($Dir."lib/lib.php");
include("access.php");

include $_SERVER["DOCUMENT_ROOT"]."/PHPExcel/Classes/PHPExcel.php";

$UpFile = $_FILES["upfile_cl"];
$UpFileName = $_FILES["upfile_cl"]["name"];
$UpFilePathInfo = pathinfo($UpFileName);
$UpFileExt = strtolower($UpFilePathInfo["extension"]);

if($UpFileExt != "xls" && $UpFileExt != "xlsx") {
        echo "엑셀파일만 업로드 가능합니다. (xls, xlsx 확장자의 파일포멧)";
        exit;
}

//업로드된 엑셀파일을 서버의 지정된 곳에 옮기기 위해 경로 적절히 설정
$upload_path = $_SERVER["DOCUMENT_ROOT"]."/PHPExcel/Uploads";
$upfile_path = $upload_path."/".date("Ymd_His").'_cl_uploaded_file.xls';
        

if(move_uploaded_file($_FILES["upfile_cl"][tmp_name], $upfile_path)) {
            
            
    //파일 타입 설정 (확자자에 따른 구분)
    $inputFileType = 'Excel2007';
    if($UpFileExt == "xls") {
        $inputFileType = 'Excel5';
    }

    // 엑셀리더 초기화
    $excel_object_ = PHPExcel_IOFactory::createReader($inputFileType);
    
    //데이터만 읽기(서식을 모두 무시해서 속도 증가 시킴)
    $excel_object_->setReadDataOnly(true);


    //범위 지정(위에 작성한 범위필터 적용)
    // $objReader->setReadFilter($filterSubset);

    //업로드된 엑셀 파일 읽기
    $excel_object_ = $excel_object_->load($upfile_path);

    //첫번째 시트로 고정
    $excel_sheet_ = @$excel_object_->setActiveSheetIndex(0);


    $excel_sheet_array_ = $excel_sheet_->toArray();

    // 문제생길시 주석풀고 테스트용
    //if($_SERVER['REMOTE_ADDR'] == '211.55.81.119'){
        //print_r($excel_sheet_array_[3]);
        //exit;
    //}

    //고정된 시트 로드
    // $objWorksheet = $objPHPExcel->getActiveSheet();

    //시트의 지정된 범위 데이터를 모두 읽어 배열로 저장
    // $sheetData = $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);

    //엑셀 데이터 결과값 Array
    $results_ = array();

    //시트의 처음 상품코드
    $product_code_before = $excel_sheet_array_[1][0];
    // echo '$product_code_before : '. $product_code_before;

    //총 재고수량 0으로 세팅
    $total_stocks = 0;
    echo '
        <html>
        <head>
        <title>재고수정 완료</title>
        <style>
        .title{
                margin: 20px;
        font-size: 20px;
        }
        table{
                text-align: center;
        }
        th, td{
            border-bottom: 1px solid #333;
                padding: 5px 20px;
        }
        </style>
        </head>
        <body>
        <div class="title">아래와 같이 재고수정이 완료되었습니다.</div>
        <div class="table_style01">
        <table>
        <tr>
        <th scope="col">상품코드</th>
        <th scope="col">옵션값</th>
        <th scope="col">재고수량</th>
        <th scope="col">총 재고수량</th>
        </tr>
        <tr>';


    for($index_ = 1; $index_ < count($excel_sheet_array_)-1; ++$index_){
        $row_data_ = $excel_sheet_array_[$index_];
        //data 없을 땐,
        if(!strlen($row_data_[0])) break;
        $product_code = $row_data_[0];  //상품코드

        // 상품코드가 존재 할때만 재고수정 처리
        list($productcode_chk)=pmysql_fetch("SELECT productcode FROM tblproduct WHERE productcode='".$product_code."'");
        
        if(!$productcode_chk){
            echo '<tr><td>'.$product_code.'</td><td colspan=3>모음전 상품이거나 상품코드가 존재하지 않습니다.</td></tr>';
        }else{
            //필요한 데이터	
            $product_option = $row_data_[1]; //옵션 사이즈 또는 FREE
            echo '<td>'.$product_code.'</td>';
            $outquan = $row_data_[2]; // 재고
            
            if($outquan < 1){ $outquan = 0; }
            
            if($product_option == 'Free'){ $product_option = 'FREE'; }

                // 옵션별 ID값 찾기
                $option_sql = "SELECT * FROM tblproduct_option ";
                $option_sql.= "WHERE productcode = '$product_code' and option_code = '$product_option' ";
                $option_que = pmysql_query($option_sql);
                $option_row = pmysql_fetch_object($option_que);

                $noOption = false;
                // 옵션이 있을 때
                if($option_row){
                        $option_num = $option_row->option_num;
                        // 옵션별 재고수량 업데이트
                        $update_sql_optionquantity = "UPDATE tblproduct_option SET ";
                        $update_sql_optionquantity.= "option_quantity	= $outquan ";
                        $update_sql_optionquantity.= "WHERE option_num = '$option_num' ";
                        pmysql_query( $update_sql_optionquantity, get_db_conn() );
                        echo '<td>option_num : '.$option_num. '/ option명 :'.$product_option.'</td>';
                // 옵션이 없을 때
            }else{
                if($product_option == 'FREE'){
                    //CL에서 FREE로 처리된 것 중에 'F 옵션 처리'
                    $option_sql = "SELECT * FROM tblproduct_option ";
                    $option_sql.= "WHERE productcode = '$product_code' and option_code = 'F' ";
                    $option_que = pmysql_query($option_sql);
                    $option_row = pmysql_fetch_object($option_que);
                    //기존에 F 로 들어간 것 처리
                    if($option_row){
                        $f_option_num = $option_row->option_num;
                        // 옵션별 재고수량 업데이트
                        $update_sql_optionquantity = "UPDATE tblproduct_option SET ";
                        $update_sql_optionquantity.= "option_quantity	= $outquan ";
                        $update_sql_optionquantity.= "WHERE option_num = '$f_option_num' ";
                        pmysql_query( $update_sql_optionquantity, get_db_conn() );
                    //기존에 옵션이 없는 것 처리
                    }else{
                        $noOption = true;
                    }
                }
                echo '<td>FREE 처리 / option_num : '.$option_num. '/ option명 :'.$product_option.'</td>';
            }
            
            echo '<td>'.$outquan.'</td>';

            // 총 재고수량 체크
            $after_index = $index_ + 1;
            if($excel_sheet_array_[$after_index][0] !== $product_code || $excel_sheet_array_[$after_index][0] === null){
                
                if($noOption){
                    // CL 엑셀에 있는 재고로 처리
                    if($outquan <= 0){
                        // -1 : 품절 상품 표시상태 처리
                        $update_sql_product_status = "UPDATE tblproduct SET ";
                        $update_sql_product_status.= "display = 'N', quantity = $outquan ";
                        $update_sql_product_status.= "WHERE productcode = '$product_code' ";
                        pmysql_query( $update_sql_product_status, get_db_conn() );
                        echo '<td>품절처리 Total 재고 : '.$outquan.'</td>';
                    }else{
                        $update_sql_product_status = "UPDATE tblproduct SET ";
                        $update_sql_product_status.= "display = 'Y', quantity = $outquan ";
                        $update_sql_product_status.= "WHERE productcode = '$product_code' ";
                        pmysql_query( $update_sql_product_status, get_db_conn() );
                        echo '<td>품절 미처리 Total 재고 : '.$outquan.'</td>';
                    }
                    
                }else{
                    // 옵션이 들어가 있는 상품 : product_option 내 총 재고수량 SUM 값으로 확인
                    $total_count_sql = "SELECT SUM(option_quantity) as cnt FROM tblproduct_option ";
                    $total_count_sql.= "WHERE productcode = '$product_code'";
                    $total_count_result = pmysql_query($total_count_sql);
                    $total_count_result = pmysql_fetch_object($total_count_result);
                    $total_count_result = $total_count_result->cnt;
                    
                    if($total_count_result <= 0){
                        // -1 : 품절 상품 표시상태 처리
                        $update_sql_product_status = "UPDATE tblproduct SET ";
                        $update_sql_product_status.= "display = 'N', quantity = $total_count_result ";
                        $update_sql_product_status.= "WHERE productcode = '$product_code' ";
                        pmysql_query( $update_sql_product_status, get_db_conn() );
                        echo '<td>품절처리 Total 재고 : '.$total_count_result.'</td>';
                        
                    }else{
                        $update_sql_product_status = "UPDATE tblproduct SET ";
                        $update_sql_product_status.= "display = 'Y', quantity = $total_count_result ";
                        $update_sql_product_status.= "WHERE productcode = '$product_code' ";
                        pmysql_query( $update_sql_product_status, get_db_conn() );
                        echo '<td>품절 미처리 Total 재고 : '.$total_count_result.'</td>';
                    }
                    
                }	
            }else{
                echo '<td>동일한 상품코드 처리</td>';
            }
                echo '</tr>';
        }
    }
    echo '</table>
    </div>
    </body>
    </html>';
}else{
    echo '<pre>';
    echo "업로드된 파일을 옮기는 중 에러가 발생했습니다.";
    print_r($_FILES);
    echo '<pre>';
    exit;
}
?>