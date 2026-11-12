<?php
/**
 * 기상청 API허브 지상관측 API 클라이언트 (typ01 텍스트 응답)
 *
 * 명세: kma-api.md
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SW_KMA_API {

	const BASE_URL = 'https://apihub.kma.go.kr/api/typ01/url/';

	/** 음수가 정상값일 수 있는 항목(기온류, 기압변화량). 이 항목은 -50 이하만 결측으로 본다. */
	const SIGNED_FIELDS = '/^(TA|TD|TS|TG|TE|PR)(_|$)/';

	/** 숫자가 아닌 문자열/시각 항목 */
	const STRING_FIELDS = '/^(TM|WW|CT|ST)$|_TM$/';

	/** 페이지에서 사용하는 컬럼 — 응답에 없더라도 null 로 채워 둔다. */
	const HOURLY_KEYS = array( 'TM', 'STN', 'WD', 'WS', 'PA', 'PS', 'TA', 'TD', 'HM', 'RN', 'RN_DAY', 'SD_TOT', 'WC', 'CA_TOT', 'VS', 'SS' );
	const DAILY_KEYS  = array( 'TM', 'STN', 'TA_AVG', 'TA_MAX', 'TA_MIN', 'RN_DAY', 'WS_AVG', 'HM_AVG', 'SS_DAY' );
	const NORMAL_KEYS = array( 'TA', 'TA_MAX', 'TA_MIN', 'RN' );

	/**
	 * 요청 시각 기준(사이트 시간대)
	 */
	private static function now() {
		return new DateTimeImmutable( 'now', wp_timezone() );
	}

	/**
	 * 지점의 최근 N시간 시간자료 (kma_sfctm3.php)
	 *
	 * @return array|WP_Error 시간순 행 배열
	 */
	public static function hourly( $stn, $hours = 24 ) {
		$end   = self::now();
		$start = $end->modify( '-' . (int) $hours . ' hours' );
		return self::request( 'kma_sfctm3.php', array(
			'tm1' => $start->format( 'YmdH' ) . '00',
			'tm2' => $end->format( 'YmdHi' ),
			'stn' => (int) $stn,
		), 10 * MINUTE_IN_SECONDS, self::HOURLY_KEYS, 'hourly_' . (int) $stn . '_' . (int) $hours . '_' . $end->format( 'YmdH' ) . floor( (int) $end->format( 'i' ) / 10 ) );
	}

	/**
	 * 지점의 가장 최근 관측 1건
	 *
	 * @return array|WP_Error
	 */
	public static function latest( $stn ) {
		$rows = self::hourly( $stn, 6 );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		for ( $i = count( $rows ) - 1; $i >= 0; $i-- ) {
			if ( null !== $rows[ $i ]['TA'] ) {
				return $rows[ $i ];
			}
		}
		return new WP_Error( 'sw_kma_empty', '최근 관측자료가 없습니다.' );
	}

	/**
	 * 전국 지점의 최신 정시 관측 (kma_sfctm2.php, stn=0)
	 * 정시 자료는 수 분 뒤 올라오므로 비어 있으면 한 시간 전 자료를 쓴다.
	 *
	 * @return array|WP_Error [지점번호 => 행]
	 */
	public static function all_latest() {
		$t = self::now();
		for ( $back = 0; $back < 3; $back++ ) {
			$tm   = $t->modify( '-' . $back . ' hours' )->format( 'YmdH' ) . '00';
			$rows = self::request( 'kma_sfctm2.php', array( 'tm' => $tm, 'stn' => 0 ), 10 * MINUTE_IN_SECONDS, self::HOURLY_KEYS );
			if ( is_wp_error( $rows ) ) {
				return $rows;
			}
			if ( count( $rows ) > 10 ) {
				$out = array();
				foreach ( $rows as $r ) {
					$out[ (int) $r['STN'] ] = $r;
				}
				return $out;
			}
		}
		return new WP_Error( 'sw_kma_empty', '전국 관측자료가 아직 없습니다.' );
	}

	/**
	 * 지점의 최근 N일 일자료 (kma_sfcdd3.php) — 어제까지
	 *
	 * @return array|WP_Error
	 */
	public static function daily( $stn, $days = 7 ) {
		$y = self::now()->modify( '-1 day' );
		return self::request( 'kma_sfcdd3.php', array(
			'tm1' => $y->modify( '-' . ( (int) $days - 1 ) . ' days' )->format( 'Ymd' ),
			'tm2' => $y->format( 'Ymd' ),
			'stn' => (int) $stn,
		), 3 * HOUR_IN_SECONDS, self::DAILY_KEYS );
	}

	/**
	 * 일 평년값 (sfc_norm1.php, 1991~2020)
	 *
	 * @return array|WP_Error 행 1건
	 */
	public static function normal( $stn, $month, $day ) {
		$rows = self::request( 'sfc_norm1.php', array(
			'norm' => 'D',
			'tmst' => 2021,
			'stn'  => (int) $stn,
			'MM1'  => (int) $month,
			'DD1'  => (int) $day,
			'MM2'  => (int) $month,
			'DD2'  => (int) $day,
		), 30 * DAY_IN_SECONDS, self::NORMAL_KEYS );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		return $rows ? $rows[0] : new WP_Error( 'sw_kma_empty', '평년값이 없습니다.' );
	}

	/**
	 * 지점 좌표 [지점번호 => [lat, lon, ht]]
	 *
	 * 지점정보 API(stn_inf.php)는 별도 활용신청이 필요하므로, 지상관측의 요소별 조회(kma_sfctm5.php)가
	 * 함께 돌려주는 LON/LAT/HT 를 사용한다. 지점 위치는 거의 바뀌지 않아 30일간 캐시한다.
	 *
	 * @return array
	 */
	public static function station_coords() {
		$tm   = self::now()->modify( '-1 hour' )->format( 'YmdH' ) . '00';
		$rows = self::request( 'kma_sfctm5.php', array(
			'tm2'  => $tm,
			'obs'  => 'TA',
			'stn'  => 0,
			'disp' => 0,
		), 30 * DAY_IN_SECONDS, array( 'STN', 'LON', 'LAT', 'HT' ), 'station_coords' );
		if ( is_wp_error( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $r ) {
			if ( null !== $r['LAT'] && null !== $r['LON'] ) {
				$out[ (int) $r['STN'] ] = array( 'lat' => (float) $r['LAT'], 'lon' => (float) $r['LON'], 'ht' => $r['HT'] );
			}
		}
		return $out;
	}

	/**
	 * 공통 요청 + 파싱 + 캐시
	 *
	 * @param string      $file   API 파일명.
	 * @param array       $params 요청 인자.
	 * @param int         $ttl    캐시 시간(초).
	 * @param array       $keys   항상 존재해야 하는 컬럼.
	 * @param string|null $key    캐시 키(없으면 인자로 생성).
	 * @return array|WP_Error
	 */
	private static function request( $file, array $params, $ttl, array $keys = array(), $key = null ) {
		$auth = (string) sw_ksasi_get_setting( 'kma_auth_key' );
		if ( '' === $auth ) {
			return new WP_Error( 'sw_kma_no_key', '기상청 API허브 인증키가 설정되지 않았습니다. [설정 > KASI·기상청]에서 입력하세요.' );
		}

		$cache_key = 'sw_ksasi_kma_' . md5( $file . ( $key ? $key : wp_json_encode( $params ) ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$params['help']    = 1; // 컬럼 설명을 받아 컬럼 순서를 응답에서 직접 읽는다.
		$params['authKey'] = $auth;
		$url               = self::BASE_URL . $file . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );

		$res = wp_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'sw_kma_http', '기상청 API 요청 실패: ' . $res->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = (string) wp_remote_retrieve_body( $res );

		// 인증 실패 등은 JSON 으로 온다: {"result":{"status":401,"message":"..."}}
		$json = json_decode( $body, true );
		if ( isset( $json['result']['status'] ) ) {
			return new WP_Error( 'sw_kma_api', sprintf( '기상청 API 오류 (%s): %s', $json['result']['status'], $json['result']['message'] ?? '' ) );
		}
		if ( 200 !== $code ) {
			return new WP_Error( 'sw_kma_http', sprintf( '기상청 API 응답 오류 (HTTP %d)', $code ) );
		}

		$rows     = self::parse( $body );
		$defaults = array_fill_keys( $keys, null );
		foreach ( $rows as &$row ) {
			$row += $defaults;
		}
		unset( $row );
		set_transient( $cache_key, $rows, $ttl );
		return $rows;
	}

	/**
	 * typ01 텍스트 응답 파싱
	 *
	 * help=1 의 "#  3. WD     : 풍향" 형식 줄에서 컬럼 순서를 읽고, 주석이 아닌 줄을 공백(또는 쉼표)으로 나눈다.
	 *
	 * @return array 행 배열 [컬럼명 => 값]
	 */
	public static function parse( $body ) {
		if ( ! mb_check_encoding( $body, 'UTF-8' ) ) {
			$body = mb_convert_encoding( $body, 'UTF-8', 'EUC-KR' );
		}

		$cols     = array(); // 번호 붙은 도움말에서 읽은 컬럼
		$csv_cols = array(); // "# ST,STN, MM, DD, ..." 형식 머리줄에서 읽은 컬럼 (평년값 API)
		$rows     = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $body ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( '#' === $line[0] ) {
				if ( preg_match( '/^#\s*(\d+)\.\s*([A-Z][A-Z0-9_]*)\s*:/', $line, $m ) ) {
					$cols[ (int) $m[1] - 1 ] = $m[2];
				} elseif ( preg_match( '/^#\s*[A-Z][A-Z0-9_]*\s*,(\s*[A-Z][A-Z0-9_]*\s*,)+/', $line ) ) {
					$csv_cols = array_values( array_filter( array_map( 'trim', explode( ',', rtrim( ltrim( $line, '# ' ), ',= ' ) ) ) ) );
				}
				continue;
			}
			if ( ! $cols && $csv_cols ) {
				$cols = $csv_cols;
			}
			if ( ! $cols ) {
				continue;
			}
			$vals = preg_split( '/[\s,]+/', rtrim( $line, ",= " ) );
			$row  = array();
			foreach ( $cols as $i => $name ) {
				if ( isset( $row[ $name ] ) ) {
					continue; // 같은 이름이 두 번 나오면 첫 번째를 쓴다.
				}
				$row[ $name ] = self::clean( $name, isset( $vals[ $i ] ) ? $vals[ $i ] : null );
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * 결측 처리 및 형 변환
	 */
	private static function clean( $name, $v ) {
		if ( null === $v || '' === $v ) {
			return null;
		}
		if ( preg_match( self::STRING_FIELDS, $name ) ) {
			return ( '-9' === $v || '-' === $v ) ? null : $v;
		}
		if ( ! is_numeric( $v ) ) {
			return null;
		}
		$f = (float) $v;
		if ( preg_match( self::SIGNED_FIELDS, $name ) ) {
			return $f <= -50 ? null : $f;
		}
		return $f < 0 ? null : $f;
	}
}
