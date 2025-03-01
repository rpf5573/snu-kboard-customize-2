<?php
if(!defined('ABSPATH')) exit;

require_once dirname(__DIR__) . '/snu-clothing-menber/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

define('KBOARD_snu_clothing_menber_VERSION', '1.6');

load_plugin_textdomain('kboard-snu-clothing-menber', false, dirname(plugin_basename(__FILE__)) . '/languages');

if(!function_exists('kboard_snu_clothing_menber_scripts')){
	add_action('wp_enqueue_scripts', 'kboard_snu_clothing_menber_scripts', 999);
	add_action('kboard_iframe_head', 'kboard_snu_clothing_menber_scripts');
	function kboard_snu_clothing_menber_scripts(){
		$localize = array(
			'missing_link_address' => __('Missing link address.', 'kboard-snu-clothing-menber'),
			'no_more_data' => __('There is no more data to display.', 'kboard-snu-clothing-menber'),
		);
		wp_localize_script('kboard-script', 'kboard_snu_clothing_menber_localize_strings', $localize);
	}
}

if (!function_exists('kboard_snu_more_view_action')) {
	add_action('init', 'kboard_snu_more_view_action');
	function kboard_snu_more_view_action(){
		$action = isset($_GET['action'])?$_GET['action']:'';
		$board_id = isset($_GET['board_id'])?intval($_GET['board_id']):'';
		
		if(!$board_id){
			$board_id = isset($_GET['kboard_id'])?intval($_GET['kboard_id']):'';
		}
	
		if($action == 'kboard_snu_more_view_action' && $board_id){
			echo kboard_builder(array('id'=>$board_id));
			exit;
		}
	}
}

if(!function_exists('kboard_snu_clothing_menber_shortcusts')){
	add_action('wp_ajax_kboard_snu_clothing_menber_shortcusts', 'kboard_snu_clothing_menber_shortcusts');
	add_action('wp_ajax_nopriv_kboard_snu_clothing_menber_shortcusts', 'kboard_snu_clothing_menber_shortcusts');
	function kboard_snu_clothing_menber_shortcusts(){
		$content_uid = isset($_POST['content_uid'])?intval($_POST['content_uid']):'';
		if($content_uid){
			$content = new KBContent();
			$content->initWithUID($content_uid);
			$content->increaseView();
		}
		exit;
	}
}

if(!function_exists('kboard_snu_clothing_menber_print')){
	function kboard_snu_clothing_menber_print($link){
		if(strpos($link, 'http://') !== false || strpos($link, 'https://') !== false){
			return $link;
		}
		return "http://{$link}";
	}
}

// 생년월일 -> 나이 구하기 - 시작
if (!function_exists('kboard_snu_is_valid_date')) {
  function kboard_snu_is_valid_date($date) {
    $format = 'Y-m-d';
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
  }
}

if (!function_exists('kboard_snu_get_age')) {
  function kboard_snu_get_age($birth_date) {
    $birth_date = new DateTime($birth_date);
    $now = new DateTime();
    $interval = $now->diff($birth_date);
    return $interval->y;
  }
}
// 생년월일 -> 나이 구하기 - 끝

// 이름으로 정렬하기 - 시작
if (!function_exists('kboard_snu_add_name_sorting_type')) {
  add_filter('kboard_list_sorting_types', 'kboard_snu_add_name_sorting_type', 1, 1);
  function kboard_snu_add_name_sorting_type($sorting_types) {
      if (empty($sorting_types)) $sorting_types = [];
      $sorting_types[] = 'name';
      return array_unique($sorting_types);
  }
}

if (!function_exists('kboard_snu_sort_by_name')) {
  add_filter('kboard_list_orderby', 'kboard_snu_sort_by_name', 1, 3);
  function kboard_snu_sort_by_name($orderby, $board_id, $obj) {
      global $wpdb;

      if (!isset($_REQUEST['kboard_list_sort'])) return $orderby;
      if (empty($_REQUEST['kboard_list_sort'])) return $orderby;
      if ($_REQUEST['kboard_list_sort'] !== 'name') return $orderby;

      return "`{$wpdb->prefix}kboard_board_content`.`title` ASC";
  }
}
// 이름으로 정렬하기 - 끝


// 검색 고도화 하기 - 시작
if (!function_exists('kboard_snu_get_custom_fields')) {
  // 검색시 이 필드들이 query에 들어가도록 한다
  function kboard_snu_get_custom_fields() {
    return $custom_fields = [
        'academic_change',
        'student_id',
        'graduation_date',
        'academic_year',
        'type_classification',
        'birth_date',
        'department',
        'registration_category',
        'graduation_school',
        'email_out_of_campus',
        'name_english',
        'advisor',
        'classification_of_major',
        'course',
        'academic_status',
        'completion_date',
        'major',
        'semester_leave_no',
        'semesters_enrolled_no',
        'academic_classification',
        'nationality',
        'current_job',
        'university',
        'day_night',
        'admission_classification',
        'graduate_school',
        'attendance',
        'mobile_no',
        'name_chinese',
        'tel',
        'entrance_date',
        'gender',
        'email_on_campus'
    ];
  }
}

// 검색 옵션에 위의 커스텀 필드들을 넣는다
if (!function_exists('kboard_snu_kboard_list_search_option')) {
  add_filter('kboard_list_search_option', 'kboard_snu_kboard_list_search_option', 1, 3);
  function kboard_snu_kboard_list_search_option($search_option, $board_id, $obj) {
    if (!isset($_REQUEST['keyword'])) return $search_option;
    if (empty($_REQUEST['keyword'])) return $search_option;

    $keyword = $_REQUEST['keyword'];

    $custom_fields = kboard_snu_get_custom_fields();

    $custom_search_options = array_reduce($custom_fields, function($acc, $cur) use ($keyword) {
        if (!$acc) $acc = [];
        $acc[$cur] = array(
            'key' => $cur,
            'compare' => 'LIKE',
            'wildcard' => 'both',
            'value' => $keyword,
        );
        return $acc;
    });
    $custom_search_options['relation'] = "OR";

    return $custom_search_options;
  }
}

// 커스텀 필드를 바탕으로 생성된 수많은 INNER_JOIN을 하나로 바꿔준다
if (!function_exists('kboard_snu_list_from')) {
  add_filter('kboard_list_from', 'kboard_snu_list_from', 1, 3);
  function kboard_snu_list_from($from_str, $board_id, $obj){
    global $wpdb;
    if (!isset($_REQUEST['keyword'])) return $from_str;
    if (empty($_REQUEST['keyword'])) return $from_str;

    $new_from_str = "`{$wpdb->prefix}kboard_board_content` INNER JOIN `{$wpdb->prefix}kboard_board_option` ON `{$wpdb->prefix}kboard_board_content`.`uid`=`{$wpdb->prefix}kboard_board_option`.`content_uid`";
    return $new_from_str;
  }
}

// 필드들을 넣으면 where절이 막 생기는데 이를 수정한다
// 1. 검색어와 옵션검색을 AND로 연결하는데, 이것을 OR로 바꿔준다
// 2. from 구문에서 INNER_JOIN을 하나 빼고 삭제했기 때문에, 커스텀 필드용 INNER_JOIN을 삭제하고 정식 INNER_JOIN 하나만 남겨놓는다
if (!function_exists('kboard_snu_list_where')) {
  add_filter('kboard_list_where', 'kboard_snu_list_where', 1, 3);
  function kboard_snu_list_where($query_str_where, $board_id, $obj) {
    global $wpdb;

    if (!isset($_REQUEST['keyword'])) return $query_str_where;
    if (empty($_REQUEST['keyword'])) return $query_str_where;

    $keyword = $_REQUEST['keyword'];
    $prefix = $wpdb->prefix;

    $query = "`snuclothingmenber_kboard_board_content`.`board_id`='{$board_id}'";

    // 카테고리도 반영해주자
    if (isset($_REQUEST['category1']) && !empty($_REQUEST['category1'])) {
      $category1 = $_REQUEST['category1'];
      $category1 = esc_sql($category1);
			$query = $query . " AND `{$prefix}kboard_board_content`.`category1`='{$category1}'";
    }

    $query = $query . "
      AND (
        (
          `{$prefix}kboard_board_content`.`title` LIKE '%{$keyword}%' 
          OR `{$prefix}kboard_board_content`.`content` LIKE '%{$keyword}%'
        )
    ";

    $custom_fields = kboard_snu_get_custom_fields();
    $sub_where = [];
    foreach($custom_fields as $field) {
      $q = "`{$prefix}kboard_board_option`.`option_key`='{$field}' AND `{$prefix}kboard_board_option`.`option_value` LIKE '%{$keyword}%'";
      $sub_where[] = ' OR (' . $q . ')';
    }

    $query = $query . implode('', $sub_where) . ')'; // 여기서 AND 끝내고

    $query = $query . "
      AND `{$prefix}kboard_board_content`.`notice`=''
      AND (`{$prefix}kboard_board_content`.`status` IS NULL
      OR `{$prefix}kboard_board_content`.`status`=''
      OR `{$prefix}kboard_board_content`.`status`='pending_approval')
      GROUP BY `{$prefix}kboard_board_content`.`uid`
    ";

    return $query;
  }
}

if (!function_exists('kboard_snu_content_list_total_count')) {
  add_filter('kboard_content_list_total_count', 'kboard_snu_content_list_total_count', 1, 3);
  function kboard_snu_content_list_total_count($total, $board, $obj) {
    global $wpdb;
    if (!isset($_REQUEST['keyword'])) return $total;
    if (empty($_REQUEST['keyword'])) return $total;

    $keyword = $_REQUEST['keyword'];
    $prefix = $wpdb->prefix;
    $board_id = $board->id;

    // 여기 AND (( 주의!
    $query = "
      SELECT COUNT(*)
      FROM `{$prefix}kboard_board_content`
      INNER JOIN `{$prefix}kboard_board_option`
      ON `{$prefix}kboard_board_content`.`uid` = `{$prefix}kboard_board_option`.`content_uid`
      WHERE `{$prefix}kboard_board_content`.`board_id` = '{$board_id}'
    ";

    // 카테고리도 반영해주자
    if (isset($_REQUEST['category1']) && !empty($_REQUEST['category1'])) {
      $category1 = $_REQUEST['category1'];
      $category1 = esc_sql($category1);
			$query = $query . " AND `{$prefix}kboard_board_content`.`category1`='{$category1}'";
    }

    $query = $query . "
      AND (
        (
          `{$prefix}kboard_board_content`.`title` LIKE '%{$keyword}%' 
          OR `{$prefix}kboard_board_content`.`content` LIKE '%{$keyword}%'
        )
    ";

    $custom_fields = kboard_snu_get_custom_fields();
    $sub_where = [];
    foreach($custom_fields as $field) {
      $q = "`{$prefix}kboard_board_option`.`option_key`='{$field}' AND `{$prefix}kboard_board_option`.`option_value` LIKE '%{$keyword}%'";
      $sub_where[] = ' OR (' . $q . ')';
    }

    $query = $query . implode('', $sub_where) . ')'; // 여기서 AND 끝내고

    $query = $query . "
      AND `{$prefix}kboard_board_content`.`notice`=''
      AND (`{$prefix}kboard_board_content`.`status` IS NULL
      OR `{$prefix}kboard_board_content`.`status`=''
      OR `{$prefix}kboard_board_content`.`status`='pending_approval')
      GROUP BY `{$prefix}kboard_board_content`.`uid`
    ";

    $count = count($wpdb->get_results($query));
    return $count;
  }
}

if (!function_exists('kboard_snu_list_rpp')) {
  add_filter('kboard_list_rpp', 'kboard_snu_list_rpp', 1, 3);
  function kboard_snu_list_rpp($rpp, $board_id, $obj) {
    if (!isset($_REQUEST['kboard_list_rpp'])) return $rpp;
    if (empty($_REQUEST['kboard_list_rpp'])) return $rpp;

    $_rpp = $_REQUEST['kboard_list_rpp'];
    if (!is_int($_rpp)) return $rpp;

    return $_rpp;
  }  
}

// 검색 고도화 하기 - 끝


// 상세보기를 누르면 조회수를 올린다
if (!function_exists('kboard_snu_update_view_count_action')) {
  add_action( 'wp_ajax_kboard_snu_update_view_count_action', 'kboard_snu_update_view_count_action' );
  add_action( 'wp_ajax_nopriv_kboard_snu_update_view_count_action', 'kboard_snu_update_view_count_action' );
  function kboard_snu_update_view_count_action() {
    global $wpdb;

    if (!isset($_POST['uid'])) {
      wp_send_json_error(array(
        "code" => 404,
        "message" => 'no value'
      ));
      return;
    }
    $uid = intval($_POST['uid']);

    if (empty($uid)) {
      wp_send_json_error(array(
        "code" => 403,
        "message" => 'empty value'
      ), 401);
      return;
    }

    $wpdb->query("UPDATE `{$wpdb->prefix}kboard_board_content` SET `view`=`view`+1 WHERE `uid`='{$uid}'");
  }
}

if (!function_exists('kboard_snu_get_list_count_by_category')) {
  function kboard_snu_get_list_count_by_category($board) {
    if (!isset($_GET['category1'])) return number_format($board->getListTotal());
    if (empty($_GET['category1'])) return number_format($board->getListTotal());

    $category1 = $_GET['category1'];
    return number_format($board->getCategoryCount(array('category1' => $category1)));
  }
}

// 엑셀 다운로드 기능을 추가한다
if (!function_exists('kboard_snu_download_xlsx')) {
  add_action('init', 'kboard_snu_download_xlsx');
  function kboard_snu_download_xlsx() {
    if (!isset($_GET['kboard_snu_xlsx_download'])) return;
    if (!current_user_can('manage_kboard')) wp_die(__('You do not have permission.', 'kboard'));
    if (!wp_verify_nonce( $_GET['kboard_snu_xlsx_download_nonce'], 'kboard_snu_xlsx_download_action' )) wp_die(__('Invalid Access', 'kboard'));

    $board_id = isset($_GET['board_id_for_xlsx_download'])?$_GET['board_id_for_xlsx_download']:'';
    $board = new KBoard($board_id);

    if ($board->id) {
      $spreadsheet = new Spreadsheet();
      $sheet = $spreadsheet->getActiveSheet();

      $cols = array(
        'A' => array(
          'col_name' => '순번',
          'option_key' => 'uid',
        ),
        'B' => array(
          'col_name' => '과정',
          'option_key' => 'course'
        ),
        'C' => array(
          'col_name' => '대학(원)',
          'option_key' => 'university'
        ),
        'D' => array(
          'col_name' => '학과(부)',
          'option_key' => 'department'
        ),
        'E' => array(
          'col_name' => '전공',
          'option_key' => 'major'
        ),
        'F' => array(
          'col_name' => '전공구분',
          'option_key' => 'classification_of_major'
        ),
        'G' => array(
          'col_name' => '학적구분',
          'option_key' => 'academic_classification'
        ),
        'H' => array(
          'col_name' => '학적상태',
          'option_key' => 'academic_status'
        ),
        'I' => array(
          'col_name' => '수료일자',
          'option_key' => 'completion_date'
        ),
        'J' => array(
          'col_name' => '졸업일자',
          'option_key' => 'graduation_date'
        ),
        'K' => array(
          'col_name' => '학번',
          'option_key' => 'student_id'
        ),
        'L' => array(
          'col_name' => '이름',
          'option_key' => 'title'
        ),
        'M' => array(
          'col_name' => '성별',
          'option_key' => 'gender'
        ),
        'N' => array(
          'col_name' => '입학년도',
          'option_key' => 'entrance_date'
        ),
        'O' => array(
          'col_name' => '영문성명',
          'option_key' => 'name_english'
        ),
        'P' => array(
          'col_name' => '한자성명',
          'option_key' => 'name_chinese'
        ),
        'Q' => array(
          'col_name' => '생년월일',
          'option_key' => 'birth_date'
        ),
        'R' => array(
          'col_name' => '핸드폰번호',
          'option_key' => 'mobile_no'
        ),
        'S' => array(
          'col_name' => '주야구분',
          'option_key' => 'day_night'
        ),
        'T' => array(
          'col_name' => '지도교수',
          'option_key' => 'advisor'
        ),
        'U' => array(
          'col_name' => '입학구분',
          'option_key' => 'admission_classification'
        ),
        'V' => array(
          'col_name' => '학년',
          'option_key' => 'academic_year'
        ),
        'W' => array(
          'col_name' => '휴학학기수',
          'option_key' => 'semester_leave_no'
        ),
        'X' => array(
          'col_name' => '등록학기수',
          'option_key' => 'semesters_enrolled_no'
        ),
        'Y' => array(
          'col_name' => '국적',
          'option_key' => 'nationality'
        ),
        'Z' => array(
          'col_name' => '출신학교',
          'option_key' => 'graduation_school'
        ),
        'AA' => array(
          'col_name' => '유형구분',
          'option_key' => 'type_classification'
        ),
        'AB' => array(
          'col_name' => '입학일자',
          'option_key' => 'entrance_date'
        ),
        'AC' => array(
          'col_name' => '학적변동',
          'option_key' => 'academic_change'
        ),
        'AD' => array(
          'col_name' => '등록구분',
          'option_key' => 'registration_category'
        ),
        'AE' => array(
          'col_name' => '이메일(교내)',
          'option_key' => 'email_on_campus'
        ),
        'AF' => array(
          'col_name' => '이메일(외부)',
          'option_key' => 'email_out_of_campus'
        ),
        'AG' => array(
          'col_name' => '대학원(졸업연도)',
          'option_key' => 'graduate_school'
        ),
        'AH' => array(
          'col_name' => '직업',
          'option_key' => 'current_job'
        ),
        'AI' => array(
          'col_name' => '비고',
          'option_key' => 'content'
        ),
      );

      $index = 1;
      foreach ($cols as $col => $arr) {
        $sheet->setCellValue("{$col}{$index}", $arr['col_name']);
      }

      $list = new KBContentList($board_id);
      $list->rpp(2000);
      $list->orderASC('uid');
      $list->initFirstList();

      while($list->hasNextList()){
        while($content = $list->hasNext()){
          $index += 1;
          foreach($cols as $col => $arr) {
            $option_key = $arr['option_key'];
            $option_value = $content->option->{$option_key};
            if ($option_key === 'uid') {
              $option_value = $content->uid;
            }
            if ($option_key === 'title') {
              $option_value = $content->row->title;
            }
            if ($option_key === 'content') {
              $option_value = $content->row->content;
            }
            
            $sheet->setCellValue("{$col}{$index}", $option_value);
          }
        }
      }

      @ob_end_clean();
      $writer = new Xlsx($spreadsheet);

      $filename = 'users' . Date('Y-m-d H:i:s') . '.xlsx';

      header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
      header('Content-Disposition: attachment;filename="' . $filename . '"');
      header('Cache-Control: max-age=0');
      $writer->save('php://output');
    }
    exit;
  }
}
