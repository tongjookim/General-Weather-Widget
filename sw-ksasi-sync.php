<?php
/**
 * Plugin Name:       SW KASI Sync - 일출/일몰·월출/월몰 위젯 + 기상청 지상관측 페이지
 * Description:       한국천문연구원(KASI) 출몰시각 정보제공 OpenAPI(RiseSetInfoService)를 이용해 일출/일몰, 월출/월몰 위젯을 숏코드로 출력합니다. 사용법: [sw_ksasi_riseset location="서울"], 기상청 API허브 지상관측(ASOS)·에어코리아 대기질 날씨 페이지 [sw_kma_weather]
 * Version:           1.5.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            SW Lab
 * License:           GPL-2.0-or-later
 * Text Domain:       sw-ksasi-sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SW_KSASI_VERSION', '1.5.1' );
define( 'SW_KSASI_FILE', __FILE__ );
define( 'SW_KSASI_PATH', plugin_dir_path( __FILE__ ) );
define( 'SW_KSASI_URL', plugin_dir_url( __FILE__ ) );
define( 'SW_KSASI_OPTION', 'sw_ksasi_settings' );

require_once SW_KSASI_PATH . 'includes/functions.php';
require_once SW_KSASI_PATH . 'includes/class-sw-ksasi-api.php';
require_once SW_KSASI_PATH . 'includes/class-sw-ksasi-admin.php';
require_once SW_KSASI_PATH . 'includes/class-sw-ksasi-shortcode.php';
require_once SW_KSASI_PATH . 'includes/kma/class-sw-kma-data.php';
require_once SW_KSASI_PATH . 'includes/kma/class-sw-kma-api.php';
require_once SW_KSASI_PATH . 'includes/air/class-sw-air-api.php';
require_once SW_KSASI_PATH . 'includes/kma/class-sw-kma-page.php';

add_action( 'plugins_loaded', function () {
	SW_KSASI_Admin::init();
	SW_KSASI_Shortcode::init();
	SW_KMA_Page::init();
} );

register_activation_hook( __FILE__, function () {
	if ( false === get_option( SW_KSASI_OPTION ) ) {
		add_option( SW_KSASI_OPTION, sw_ksasi_default_settings() );
	}
} );

register_deactivation_hook( __FILE__, 'sw_ksasi_flush_cache' );
