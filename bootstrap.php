<?php

declare(strict_types=1);

if (!defined('_GNUBOARD_')) {
    exit;
}

// 로그인하지 않은 상태에서는 모든 동작이 필요하지 않음
if (!$member['mb_id']) {
    return;
}

define('DA_PLUGIN_MEMO_VERSION', 10000);
define('DA_PLUGIN_MEMO_PATH', __DIR__);
define('DA_PLUGIN_MEMO_DIR', basename(DA_PLUGIN_MEMO_PATH));
define('DA_PLUGIN_MEMO_URL', G5_PLUGIN_URL . '/' . DA_PLUGIN_MEMO_DIR);

include_once DA_PLUGIN_MEMO_PATH . '/src/DamoangMemberMemo.php';

// DB 마이그레이션
add_replace('admin_dbupgrade', function ($is_check = false) {
    $tableName = \DamoangMemberMemo::tableName();

    // 테이블 생성
    sql_query("CREATE TABLE IF NOT EXISTS `{$tableName}` (
        `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
        `member_uid` int(11) DEFAULT NULL,
        `member_id` varchar(20) CHARACTER SET ascii NOT NULL,
        `target_member_uid` int(11) DEFAULT NULL,
        `target_member_id` varchar(20) CHARACTER SET ascii NOT NULL,
        `color` varchar(20) CHARACTER SET ascii DEFAULT NULL,
        `memo` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
        `memo_detail` text COLLATE utf8mb4_unicode_ci,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_keys` (`member_id`,`target_member_id`)
    );", true);

    // DB 마이그레이션 결과를 캐시에 저장
    // 캐시는 삭제될 수 있으므로, 마이그레이션 코드가 반복 실행될 수 있으므로 주의해야 함
    g5_set_cache('da-installed-member-memo', \DA_PLUGIN_MEMO_VERSION);

    return $is_check;
}, \G5_HOOK_DEFAULT_PRIORITY, 1);


// 설치, 마이그레이션이 완료되지 았았다면 동작을 멈춤
if (!\DamoangMemberMemo::installed()) {
    return;
}


// assets
add_stylesheet('<link rel="stylesheet" href="' . G5_PLUGIN_URL . '/da_member_memo/assets/memo.css" />');
add_javascript('<script src="' . G5_PLUGIN_URL . '/da_member_memo/assets/memo.js" data-cfasync="false"></script>');

/**
 * 글 목록 배열에 `da_member_memo` 메모를 출력하는 HTML을 추가
 */
add_replace('da_board_list', function ($list = []) {
    foreach ($list as &$item) {
        if (empty ($item['mb_id'])) {
            continue;
        }

        $item['da_member_memo'] = \DamoangMemberMemo::printMemo(
            $item['mb_id'],
            /* 목록 용 템플릿 */
            \DamoangMemberMemo::PRINT_PRESET_LIST
        );
    }

    return $list;
}, \G5_HOOK_DEFAULT_PRIORITY, 1);


/**
 * 글 보기에 메모를 출력하는 HTML을 추가
 */
add_replace('da_board_view', function ($view = []) {
    // 비회원 글이면 패스
    if (empty ($view['mb_id'])) {
        return $view;
    }

    $view['da_member_memo'] = \DamoangMemberMemo::printMemo(
        $view['mb_id'],
        /* 글 보기용 템플릿 */
        \DamoangMemberMemo::PRINT_PRESET_VIEW
    );

    return $view;
}, \G5_HOOK_DEFAULT_PRIORITY, 1);

/**
 * 댓글 목록에 메모를 출력하는 HTML을 추가
 */
add_replace('da_comment_list', function ($list = []) {
    foreach ($list as &$item) {
        if (empty ($item['mb_id'])) {
            continue;
        }

        $item['da_member_memo'] = \DamoangMemberMemo::printMemo(
            $item['mb_id'],
            /* 글 보기용 템플릿 */
            \DamoangMemberMemo::PRINT_PRESET_VIEW
        );
    }

    return $list;
}, \G5_HOOK_DEFAULT_PRIORITY, 1);


/**
 * 회원 사이드뷰 메뉴
 * 
 * 메모, 차단하기 메뉴를 출력
 */
add_replace('member_sideview_items', function ($sideview, $data = []) {
    global $member;

    if (empty ($data['mb_id'] ?? '') || $data['mb_id'] === $member['mb_id']) {
        return $sideview;
    }

    // 메모
    $sideview['menus']['member_memo'] = '<a href="#dummy-memo" data-bs-toggle="modal" data-bs-target="#memberMemoEdit" data-bs-member-id="' . $data['mb_id'] . '">메모</a>';

    // 차단
    // 이건 나리야 빌더에서 제공하는 기능.
    if (!in_array($data['mb_id'], explode(',', $member['as_chadan'] ?? ''))) {
        $sideview['menus']['member_chadan'] = '<a href="#dummy-chadan" onclick="na_chadan(\'' . $data['mb_id'] . '\');">차단하기</a>';
    } else {
        // TODO: 차단해제 기능이 없어!
        // $sideview['menus']['member_chadan'] = '<a href="#dummy-chadan" onclick="na_chadan(\'' . $data['mb_id'] . '\');">차단 해제</a>';
    }

    return $sideview;
}, \G5_HOOK_DEFAULT_PRIORITY, 2);


/**
 * 메모 편집 등 UI 출력
 */
add_replace('html_process_buffer', function ($html = '') {
    $modal = file_get_contents(DA_PLUGIN_MEMO_PATH . '/templates/memo_edit.html');

    $html = \DamoangMemberMemo::replaceLast('</body>', $modal . '</body>', $html);

    return $html;
});
