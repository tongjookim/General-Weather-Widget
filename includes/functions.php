<?php
/**
 * 공통 헬퍼 함수
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 기본 설정값
 */
function sw_ksasi_default_settings() {
	return array(
		'service_key' => '',
		'air_service_key' => '',
		'location'    => '서울',
		'cache_hours' => 12,
		'kma_auth_key' => '',
		'kma_stn'      => 108,
		'kma_page_id'  => 0,
	);
}

/**
 * 설정값 조회
 *
 * @param string|null $key 키 (null 이면 전체 배열).
 */
function sw_ksasi_get_setting( $key = null ) {
	$settings = wp_parse_args( (array) get_option( SW_KSASI_OPTION, array() ), sw_ksasi_default_settings() );
	if ( null === $key ) {
		return $settings;
	}
	return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
}

/**
 * 지역별 조회(getAreaRiseSetInfo)에서 사용할 수 있는 대표 지역명 목록
 */
function sw_ksasi_locations() {
	return array(
		'서울', '인천', '수원', '춘천', '강릉', '속초', '원주', '청주', '충주', '대전',
		'세종', '천안', '전주', '군산', '광주', '목포', '여수', '순천', '대구', '안동',
		'포항', '울산', '부산', '창원', '진주', '통영', '제주', '서귀포', '울릉도', '독도',
	);
}

/**
 * API 시각 문자열("0731", " 1802 ", "------")을 "07:31" 형식으로 변환
 *
 * @param string $raw 원본 값.
 * @return string 변환된 시각, 현상이 없으면 빈 문자열.
 */
function sw_ksasi_format_time( $raw ) {
	$digits = preg_replace( '/\D/', '', (string) $raw );
	if ( strlen( $digits ) < 4 ) {
		return '';
	}
	$digits = substr( $digits, 0, 4 );
	return substr( $digits, 0, 2 ) . ':' . substr( $digits, 2, 2 );
}

/**
 * "HH:MM" 두 개의 차이를 "N시간 M분"으로 반환
 */
function sw_ksasi_duration( $start, $end ) {
	if ( '' === $start || '' === $end ) {
		return '';
	}
	list( $sh, $sm ) = array_map( 'intval', explode( ':', $start ) );
	list( $eh, $em ) = array_map( 'intval', explode( ':', $end ) );
	$diff = ( $eh * 60 + $em ) - ( $sh * 60 + $sm );
	if ( $diff <= 0 ) {
		return '';
	}
	return sprintf( '%d시간 %d분', floor( $diff / 60 ), $diff % 60 );
}

/**
 * Ymd 날짜 문자열 검증. 올바르지 않으면 오늘(사이트 시간대) 반환.
 */
function sw_ksasi_sanitize_date( $date ) {
	$date = preg_replace( '/\D/', '', (string) $date );
	if ( 8 === strlen( $date ) && checkdate( (int) substr( $date, 4, 2 ), (int) substr( $date, 6, 2 ), (int) substr( $date, 0, 4 ) ) ) {
		return $date;
	}
	return wp_date( 'Ymd' );
}

/**
 * 플러그인이 생성한 캐시(transient) 전체 삭제
 */
function sw_ksasi_flush_cache() {
	global $wpdb;
	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_sw\_ksasi\_%' OR option_name LIKE '\_transient\_timeout\_sw\_ksasi\_%'"
	);
}

/* ── 달의 위상 ───────────────────────────────────────── */

/** 삭망월(일) */
define( 'SW_KSASI_SYNODIC', 29.530588853 );

/**
 * 해당 날짜 정오(사이트 시간대)의 달 위상 계산 (Meeus, Astronomical Algorithms 48장 간략식)
 *
 * @param string $ymd Ymd.
 * @return array{illum: float, waxing: bool, age: float}
 */
function sw_ksasi_calc_moon( $ymd ) {
	$dt = DateTimeImmutable::createFromFormat( '!Ymd H:i', $ymd . ' 12:00', wp_timezone() );
	$jd = $dt->getTimestamp() / 86400 + 2440587.5;
	$t  = ( $jd - 2451545.0 ) / 36525;

	$d  = fmod( 297.8501921 + 445267.1114034 * $t - 0.0018819 * $t * $t, 360 ); // 평균 이각
	$m  = deg2rad( 357.5291092 + 35999.0502909 * $t );                           // 태양 평균근점이각
	$mp = deg2rad( 134.9633964 + 477198.8675055 * $t );                          // 달 평균근점이각
	$d  = $d < 0 ? $d + 360 : $d;
	$dr = deg2rad( $d );

	$i = 180 - $d
		- 6.289 * sin( $mp )
		+ 2.100 * sin( $m )
		- 1.274 * sin( 2 * $dr - $mp )
		- 0.658 * sin( 2 * $dr )
		- 0.214 * sin( 2 * $mp )
		- 0.110 * sin( $dr );

	return array(
		'illum'  => ( 1 + cos( deg2rad( $i ) ) ) / 2,
		'waxing' => $d < 180,
		'age'    => $d / 360 * SW_KSASI_SYNODIC,
	);
}

/**
 * 월령(일)에 따른 위상 이름
 */
function sw_ksasi_phase_name( $age ) {
	$names = array( '삭', '초승달', '상현달', '차가는 달', '보름달', '기우는 달', '하현달', '그믐달' );
	$idx   = (int) floor( fmod( $age / SW_KSASI_SYNODIC * 8 + 0.5, 8 ) );
	return $names[ $idx ];
}

/**
 * 실제 달 표면 이미지에 위상(명암 경계)을 합성한 SVG
 *
 * 북반구(한국) 기준: 차는 달은 오른쪽, 기우는 달은 왼쪽이 밝다.
 *
 * @param float  $illum  밝은 면 비율 0~1.
 * @param bool   $waxing 차는 달 여부.
 * @param string $label  접근성용 설명.
 */
function sw_ksasi_moon_svg( $illum, $waxing, $label = '' ) {
	$uid = 'swk' . substr( md5( uniqid( '', true ) ), 0, 8 );
	$img = esc_url( SW_KSASI_URL . 'assets/img/moon.webp' );
	$r   = 50;

	if ( $illum >= 0.995 ) {
		$lit = 'M50 0 A50 50 0 1 1 50 100 A50 50 0 1 1 50 0 Z';
	} elseif ( $illum <= 0.005 ) {
		$lit = '';
	} else {
		$k  = 1 - 2 * $illum;           // 위상각의 cos. 양수=초승/그믐(가는 달), 음수=볼록한 달
		$rx = round( abs( $k ) * $r, 3 );
		// 밝은 쪽 가장자리 반원 + 명암 경계(타원 호)
		$limb_sweep = $waxing ? 1 : 0;
		$term_sweep = $waxing ? ( $k > 0 ? 0 : 1 ) : ( $k > 0 ? 1 : 0 );
		$lit        = sprintf( 'M50 0 A50 50 0 0 %d 50 100 A%s 50 0 0 %d 50 0 Z', $limb_sweep, $rx, $term_sweep );
	}

	ob_start();
	?>
	<svg class="sw-ksasi__moon-svg" viewBox="0 0 100 100" role="img" aria-label="<?php echo esc_attr( $label ); ?>">
		<defs>
			<clipPath id="<?php echo esc_attr( $uid ); ?>c"><circle cx="50" cy="50" r="50" /></clipPath>
			<filter id="<?php echo esc_attr( $uid ); ?>b" x="-10" y="-10" width="120" height="120" filterUnits="userSpaceOnUse">
				<feGaussianBlur stdDeviation="1.1" />
			</filter>
			<mask id="<?php echo esc_attr( $uid ); ?>m" maskUnits="userSpaceOnUse" x="0" y="0" width="100" height="100">
				<?php if ( $lit ) : ?>
					<path d="<?php echo esc_attr( $lit ); ?>" fill="#fff" filter="url(#<?php echo esc_attr( $uid ); ?>b)" />
				<?php endif; ?>
			</mask>
			<radialGradient id="<?php echo esc_attr( $uid ); ?>g" cx="50%" cy="50%" r="50%">
				<stop offset="80%" stop-color="#000" stop-opacity="0" />
				<stop offset="100%" stop-color="#000" stop-opacity=".25" />
			</radialGradient>
		</defs>
		<g clip-path="url(#<?php echo esc_attr( $uid ); ?>c)">
			<circle class="sw-ksasi__moon-dark" cx="50" cy="50" r="50" />
			<image class="sw-ksasi__moon-earthshine" href="<?php echo $img; // phpcs:ignore WordPress.Security.EscapeOutput ?>" x="0" y="0" width="100" height="100" />
			<image class="sw-ksasi__moon-lit" href="<?php echo $img; // phpcs:ignore WordPress.Security.EscapeOutput ?>" x="0" y="0" width="100" height="100" mask="url(#<?php echo esc_attr( $uid ); ?>m)" />
			<circle cx="50" cy="50" r="50" fill="url(#<?php echo esc_attr( $uid ); ?>g)" />
		</g>
	</svg>
	<?php
	return trim( ob_get_clean() );
}
